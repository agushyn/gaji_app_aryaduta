<?php

namespace Tests\Feature;

use App\Enums\PayrollPeriodStatus;
use App\Enums\PayslipStatus;
use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Models\Payslip;
use App\Models\User;
use App\Services\PayslipFinalizationService;
use App\Services\PayslipService;
use Database\Seeders\PayComponentSeeder;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutoGeneratePayrollAndSalaryComponentsTest extends TestCase
{
    use RefreshDatabase;

    protected User $personalia;

    protected User $approver;

    protected User $auditor;

    protected PayslipService $payslipService;

    protected PayslipFinalizationService $finalizationService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
        $this->seed(PayComponentSeeder::class);

        $this->personalia = User::where('email', 'personalia@example.com')->first();
        $this->approver = User::where('email', 'approver@example.com')->first();
        $this->auditor = User::where('email', 'auditor@example.com')->first();

        $this->payslipService = app(PayslipService::class);
        $this->finalizationService = app(PayslipFinalizationService::class);
    }

    /**
     * Helper to create an active employee with customized salary attributes.
     */
    protected function createEmployee(array $attributes = []): Employee
    {
        return Employee::create(array_merge([
            'employee_number' => 'EMP-TEST-'.uniqid(),
            'full_name' => 'Karyawan Test',
            'department' => 'Keuangan',
            'position' => 'Staff',
            'email' => 'test_'.uniqid().'@example.com',
            'phone' => '62812345678'.rand(10, 99),
            'employment_status' => 'active',
            'gaji_pokok' => 5000000,
            'tunjangan_kehadiran' => 500000,
            'tunjangan_keluarga' => 300000,
            'tunjangan_jabatan' => 1000000,
            'uang_makan_per_hari' => 50000,
            'uang_transportasi_per_hari' => 25000,
            'bpjs_ketenagakerjaan_enabled' => true,
            'bpjs_ketenagakerjaan_amount' => 150000,
        ], $attributes));
    }

    /**
     * Helper to create a payroll period.
     */
    protected function createPeriod(int $workingDays = 25, array $attributes = []): PayrollPeriod
    {
        return PayrollPeriod::create(array_merge([
            'period_code' => '2026-'.rand(10, 99),
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-31',
            'period_month_label' => 'Oktober 2026',
            'working_days' => $workingDays,
            'status' => PayrollPeriodStatus::DRAFT->value,
            'created_by' => $this->personalia->id,
        ], $attributes));
    }

    /**
     * Test 1: Komponen gaji tetap tidak berubah ketika jumlah hari berubah.
     */
    public function test_fixed_salary_components_do_not_change_when_working_days_change(): void
    {
        $employee = $this->createEmployee([
            'gaji_pokok' => 6000000,
            'tunjangan_kehadiran' => 600000,
            'tunjangan_keluarga' => 400000,
            'tunjangan_jabatan' => 1500000,
        ]);

        $items20Days = $employee->getDefaultSalaryItems(20);
        $items25Days = $employee->getDefaultSalaryItems(25);
        $items30Days = $employee->getDefaultSalaryItems(30);

        $getAmountByCode = function (array $items, string $code) {
            foreach ($items as $item) {
                if ($item['item_code_snapshot'] === $code) {
                    return (float) $item['amount'];
                }
            }

            return null;
        };

        // Gaji Pokok remains 6,000,000 across any days
        $this->assertEquals(6000000, $getAmountByCode($items20Days, 'BASIC_SALARY'));
        $this->assertEquals(6000000, $getAmountByCode($items25Days, 'BASIC_SALARY'));
        $this->assertEquals(6000000, $getAmountByCode($items30Days, 'BASIC_SALARY'));

        // Tunjangan Kehadiran remains 600,000 across any days
        $this->assertEquals(6000000, $getAmountByCode($items20Days, 'BASIC_SALARY'));
        $this->assertEquals(600000, $getAmountByCode($items20Days, 'ATTENDANCE_INCENTIVE'));
        $this->assertEquals(600000, $getAmountByCode($items25Days, 'ATTENDANCE_INCENTIVE'));
        $this->assertEquals(600000, $getAmountByCode($items30Days, 'ATTENDANCE_INCENTIVE'));

        // Tunjangan Keluarga remains 400,000 across any days
        $this->assertEquals(400000, $getAmountByCode($items20Days, 'FAMILY_ALLOWANCE'));
        $this->assertEquals(400000, $getAmountByCode($items25Days, 'FAMILY_ALLOWANCE'));
        $this->assertEquals(400000, $getAmountByCode($items30Days, 'FAMILY_ALLOWANCE'));

        // Tunjangan Jabatan remains 1,500,000 across any days
        $this->assertEquals(1500000, $getAmountByCode($items20Days, 'POSITION_ALLOWANCE'));
        $this->assertEquals(1500000, $getAmountByCode($items25Days, 'POSITION_ALLOWANCE'));
        $this->assertEquals(1500000, $getAmountByCode($items30Days, 'POSITION_ALLOWANCE'));
    }

    /**
     * Test 2: Tunjangan makan dihitung dari tarif harian x jumlah hari.
     */
    public function test_meal_allowance_is_calculated_as_daily_rate_multiplied_by_working_days(): void
    {
        $employee = $this->createEmployee([
            'uang_makan_per_hari' => 45000,
        ]);

        $workingDays = 22;
        $items = $employee->getDefaultSalaryItems($workingDays);

        $mealItem = collect($items)->firstWhere('item_code_snapshot', 'MEAL_ALLOWANCE');
        $this->assertNotNull($mealItem);
        $this->assertEquals(45000, (float) $mealItem['rate']);
        $this->assertEquals(22, (float) $mealItem['quantity']);
        $this->assertEquals(45000 * 22, (float) $mealItem['amount']); // 990,000
    }

    /**
     * Test 3: Tunjangan transportasi dihitung dari tarif harian x jumlah hari.
     */
    public function test_transport_allowance_is_calculated_as_daily_rate_multiplied_by_working_days(): void
    {
        $employee = $this->createEmployee([
            'uang_transportasi_per_hari' => 30000,
        ]);

        $workingDays = 24;
        $items = $employee->getDefaultSalaryItems($workingDays);

        $transportItem = collect($items)->firstWhere('item_code_snapshot', 'TRANSPORT_ALLOWANCE');
        $this->assertNotNull($transportItem);
        $this->assertEquals(30000, (float) $transportItem['rate']);
        $this->assertEquals(24, (float) $transportItem['quantity']);
        $this->assertEquals(30000 * 24, (float) $transportItem['amount']); // 720,000
    }

    /**
     * Test 4: Checkbox BPJS aktif menghasilkan potongan sesuai nominal master.
     */
    public function test_bpjs_enabled_creates_deduction_matching_master_amount(): void
    {
        $employee = $this->createEmployee([
            'bpjs_ketenagakerjaan_enabled' => true,
            'bpjs_ketenagakerjaan_amount' => 175000,
        ]);

        $items = $employee->getDefaultSalaryItems(25);

        $bpjsItem = collect($items)->firstWhere('item_code_snapshot', 'BPJS_TK');
        $this->assertNotNull($bpjsItem);
        $this->assertEquals('deduction', $bpjsItem['item_type']);
        $this->assertEquals(175000, (float) $bpjsItem['amount']);

        // Check via auto generate payroll
        $period = $this->createPeriod(25);
        $this->payslipService->generatePayrollForPeriod($period, $this->personalia->id);

        $payslip = Payslip::where('payroll_period_id', $period->id)
            ->where('employee_id', $employee->id)
            ->first();

        $this->assertNotNull($payslip);
        $payslipItem = $payslip->items()->where('item_code_snapshot', 'BPJS_TK')->first();
        $this->assertNotNull($payslipItem);
        $this->assertEquals(175000, (float) $payslipItem->amount);
    }

    /**
     * Test 5: Checkbox BPJS nonaktif menghasilkan potongan 0 / tidak ada potongan BPJS.
     */
    public function test_bpjs_disabled_results_in_zero_deduction(): void
    {
        $employee = $this->createEmployee([
            'bpjs_ketenagakerjaan_enabled' => false,
            'bpjs_ketenagakerjaan_amount' => 0,
        ]);

        $items = $employee->getDefaultSalaryItems(25);

        $bpjsItem = collect($items)->firstWhere('item_code_snapshot', 'BPJS_TK');
        $this->assertNull($bpjsItem);

        // Check via auto generate payroll
        $period = $this->createPeriod(25);
        $this->payslipService->generatePayrollForPeriod($period, $this->personalia->id);

        $payslip = Payslip::where('payroll_period_id', $period->id)
            ->where('employee_id', $employee->id)
            ->first();

        $this->assertNotNull($payslip);
        $payslipItem = $payslip->items()->where('item_code_snapshot', 'BPJS_TK')->first();
        $this->assertNull($payslipItem);
    }

    /**
     * Test 6: Nominal BPJS tidak dapat negatif (backend validation).
     */
    public function test_bpjs_amount_cannot_be_negative(): void
    {
        $response = $this->actingAs($this->personalia)->post('/employees', [
            'employee_number' => 'EMP-NEG-01',
            'full_name' => 'Testing Negatif BPJS',
            'employment_status' => 'active',
            'gaji_pokok' => 5000000,
            'bpjs_ketenagakerjaan_enabled' => true,
            'bpjs_ketenagakerjaan_amount' => -50000,
        ]);

        $response->assertSessionHasErrors('bpjs_ketenagakerjaan_amount');
    }

    /**
     * Test 7: Generate payroll ulang tidak menggandakan komponen dan menjaga komponen dinamis.
     */
    public function test_re_generating_payroll_does_not_duplicate_components_and_preserves_dynamic_items(): void
    {
        $employee = $this->createEmployee([
            'gaji_pokok' => 5000000,
            'uang_makan_per_hari' => 40000,
            'uang_transportasi_per_hari' => 20000,
            'bpjs_ketenagakerjaan_enabled' => true,
            'bpjs_ketenagakerjaan_amount' => 100000,
        ]);

        $period = $this->createPeriod(25);

        // First generation
        $result1 = $this->payslipService->generatePayrollForPeriod($period, $this->personalia->id);
        $this->assertEquals(1, $result1['created']);

        $payslip = Payslip::where('payroll_period_id', $period->id)
            ->where('employee_id', $employee->id)
            ->first();

        // Add a custom dynamic item (e.g. Lembur / Overtime)
        $payslip->items()->create([
            'item_code_snapshot' => 'OVERTIME',
            'description_snapshot' => 'Uang Lembur Proyek',
            'item_type' => 'earning',
            'quantity' => 5,
            'unit' => 'jam',
            'rate' => 50000,
            'amount' => 250000,
            'sort_order' => 99,
        ]);

        // Second generation (re-generate)
        $result2 = $this->payslipService->generatePayrollForPeriod($period, $this->personalia->id);
        $this->assertEquals(0, $result2['created']);
        $this->assertEquals(1, $result2['updated']);

        $payslip->refresh();
        $payslip->load('items');

        // Check that Basic Salary exists exactly once
        $basicSalaryCount = $payslip->items->where('item_code_snapshot', 'BASIC_SALARY')->count();
        $this->assertEquals(1, $basicSalaryCount);

        // Check that Overtime is preserved and not lost
        $overtimeItem = $payslip->items->firstWhere('item_code_snapshot', 'OVERTIME');
        $this->assertNotNull($overtimeItem);
        $this->assertEquals(250000, (float) $overtimeItem->amount);

        // Total slips in period remains 1
        $this->assertEquals(1, Payslip::where('payroll_period_id', $period->id)->count());
    }

    /**
     * Test 8: Perubahan master tidak mengubah payroll yang sudah difinalisasi.
     */
    public function test_master_changes_do_not_alter_finalized_payslip(): void
    {
        $employee = $this->createEmployee([
            'gaji_pokok' => 5000000,
            'uang_makan_per_hari' => 40000,
            'uang_transportasi_per_hari' => 20000,
        ]);

        $period = $this->createPeriod(20);

        // Generate payslip
        $this->payslipService->generatePayrollForPeriod($period, $this->personalia->id);

        $payslip = Payslip::where('payroll_period_id', $period->id)
            ->where('employee_id', $employee->id)
            ->first();

        // Approve and finalize payslip
        $payslip->update(['status' => PayslipStatus::APPROVED->value]);
        $this->finalizationService->finalizePayslip($payslip, $this->personalia);
        $payslip->refresh();
        $this->assertEquals(PayslipStatus::FINALIZED->value, $payslip->status);

        $originalNet = (float) $payslip->net_salary;
        $originalBasic = (float) $payslip->basic_salary;

        // Change master employee salary to 10,000,000
        $employee->update([
            'gaji_pokok' => 10000000,
            'uang_makan_per_hari' => 100000,
        ]);

        // Re-run generate on the period
        $result = $this->payslipService->generatePayrollForPeriod($period, $this->personalia->id);
        $this->assertEquals(1, $result['skipped']);

        // Payslip must remain untouched
        $payslip->refresh();
        $this->assertEquals($originalNet, (float) $payslip->net_salary);
        $this->assertEquals($originalBasic, (float) $payslip->basic_salary);

        $basicItem = $payslip->items()->where('item_code_snapshot', 'BASIC_SALARY')->first();
        $this->assertEquals(5000000, (float) $basicItem->amount);
    }

    /**
     * Test 9: Role yang tidak berwenang tidak dapat mengubah nominal gaji.
     */
    public function test_unauthorized_role_cannot_modify_employee_salary(): void
    {
        $employee = $this->createEmployee([
            'gaji_pokok' => 5000000,
        ]);

        // Auditor does not have employees.manage permission
        $response = $this->actingAs($this->auditor)->put("/employees/{$employee->id}", [
            'employee_number' => $employee->employee_number,
            'full_name' => $employee->full_name,
            'employment_status' => 'active',
            'gaji_pokok' => 99000000,
        ]);

        $response->assertStatus(403);

        $employee->refresh();
        $this->assertEquals(5000000, (float) $employee->gaji_pokok);
    }

    /**
     * Test 10: Total bruto, total potongan, dan total terima sesuai rincian komponen.
     */
    public function test_gross_deductions_and_net_totals_match_component_breakdowns(): void
    {
        $employee = $this->createEmployee([
            'gaji_pokok' => 4000000,
            'tunjangan_jabatan' => 1000000,
            'tunjangan_kehadiran' => 500000,
            'tunjangan_keluarga' => 200000,
            'uang_makan_per_hari' => 40000,
            'uang_transportasi_per_hari' => 20000,
            'bpjs_ketenagakerjaan_enabled' => true,
            'bpjs_ketenagakerjaan_amount' => 120000,
        ]);

        $workingDays = 20;
        $period = $this->createPeriod($workingDays);

        $this->payslipService->generatePayrollForPeriod($period, $this->personalia->id);

        $payslip = Payslip::where('payroll_period_id', $period->id)
            ->where('employee_id', $employee->id)
            ->first();

        // Expected values:
        // Earnings:
        // Gaji Pokok: 4,000,000
        // Tunjangan Jabatan: 1,000,000
        // Tunjangan Kehadiran: 500,000
        // Tunjangan Keluarga: 200,000
        // Uang Makan: 40,000 * 20 = 800,000
        // Uang Transport: 20,000 * 20 = 400,000
        // Total Earnings / Gaji Bruto = 6,900,000
        // Deductions:
        // BPJS TK: 120,000
        // Total Deductions = 120,000
        // Total Terima (Net) = 6,900,000 - 120,000 = 6,780,000

        $expectedEarnings = 4000000 + 1000000 + 500000 + 200000 + (40000 * 20) + (20000 * 20); // 6,900,000
        $expectedDeductions = 120000;
        $expectedNet = $expectedEarnings - $expectedDeductions; // 6,780,000

        $this->assertEquals($expectedEarnings, (float) $payslip->total_earnings);
        $this->assertEquals($expectedEarnings, (float) $payslip->gaji_bruto);
        $this->assertEquals($expectedDeductions, (float) $payslip->total_deductions);
        $this->assertEquals($expectedNet, (float) $payslip->net_salary);
    }
}
