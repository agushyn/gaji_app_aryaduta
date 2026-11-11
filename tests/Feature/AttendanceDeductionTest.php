<?php

namespace Tests\Feature;

use App\Enums\PayrollPeriodStatus;
use App\Enums\PayslipStatus;
use App\Models\Employee;
use App\Models\PayComponent;
use App\Models\PayrollPeriod;
use App\Models\Payslip;
use App\Models\User;
use App\Services\AttendanceDeductionService;
use App\Services\PayslipService;
use Database\Seeders\PayComponentSeeder;
use Database\Seeders\RbacSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceDeductionTest extends TestCase
{
    use RefreshDatabase;

    protected User $personalia;
    protected User $approver;
    protected PayslipService $payslipService;
    protected AttendanceDeductionService $deductionService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
        $this->seed(PayComponentSeeder::class);

        $this->personalia = User::where('email', 'personalia@example.com')->first();
        $this->approver = User::where('email', 'approver@example.com')->first();

        $this->payslipService = app(PayslipService::class);
        $this->deductionService = app(AttendanceDeductionService::class);
    }

    protected function createEmployee(array $attributes = []): Employee
    {
        return Employee::create(array_merge([
            'employee_number' => 'EMP-'.uniqid(),
            'full_name' => 'Budi Santoso',
            'department' => 'Operasional',
            'position' => 'Staff',
            'email' => 'budi_'.uniqid().'@example.com',
            'phone' => '62812345678'.rand(10, 99),
            'employment_status' => 'active',
            'gaji_pokok' => 5000000,
            'tunjangan_kehadiran' => 500000,
            'tunjangan_keluarga' => 300000,
            'tunjangan_jabatan' => 1000000,
            'uang_makan_per_hari' => 25000,
            'uang_transportasi_per_hari' => 15000,
            'bpjs_ketenagakerjaan_enabled' => false,
            'bpjs_ketenagakerjaan_amount' => 0,
        ], $attributes));
    }

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
     * 1. Pengaturan potongan aktif dan nonaktif dapat disimpan dan diubah.
     */
    public function test_configuration_can_be_saved_and_toggled_active_and_inactive(): void
    {
        $mealComp = PayComponent::where('code', 'MEAL_ALLOWANCE')->first();
        $transportComp = PayComponent::where('code', 'TRANSPORT_ALLOWANCE')->first();

        // 1. Activate and set components
        $response = $this->actingAs($this->personalia)
            ->post(route('payroll-configurations.attendance-deduction-settings'), [
                'is_active' => true,
                'component_ids' => [$mealComp->id, $transportComp->id],
            ]);

        $response->assertRedirect(route('payroll-configurations.index'));
        $response->assertSessionHas('success');

        $this->assertTrue($this->deductionService->isEnabled());
        $this->assertEqualsCanonicalizing(
            [$mealComp->id, $transportComp->id],
            $this->deductionService->getSelectedComponentIds()
        );

        // 2. Deactivate
        $response2 = $this->actingAs($this->personalia)
            ->post(route('payroll-configurations.attendance-deduction-settings'), [
                'is_active' => false,
                'component_ids' => [$mealComp->id],
            ]);

        $response2->assertRedirect(route('payroll-configurations.index'));
        $this->assertFalse($this->deductionService->isEnabled());
    }

    /**
     * 2. Pemilihan beberapa komponen sekaligus (dinamis).
     */
    public function test_selection_of_multiple_components_simultaneously(): void
    {
        $mealComp = PayComponent::where('code', 'MEAL_ALLOWANCE')->first();
        $transportComp = PayComponent::where('code', 'TRANSPORT_ALLOWANCE')->first();
        $positionComp = PayComponent::where('code', 'POSITION_ALLOWANCE')->first();

        $this->deductionService->updateConfig([
            'is_active' => true,
            'component_ids' => [$mealComp->id, $transportComp->id, $positionComp->id],
        ], $this->personalia);

        $selected = $this->deductionService->getSelectedComponentIds();
        $this->assertCount(3, $selected);
        $this->assertContains($mealComp->id, $selected);
        $this->assertContains($transportComp->id, $selected);
        $this->assertContains($positionComp->id, $selected);
    }

    /**
     * 3. Tidak masuk 0 hari: tidak ada potongan kehadiran.
     */
    public function test_deduction_calculation_when_absent_is_zero_days(): void
    {
        $mealComp = PayComponent::where('code', 'MEAL_ALLOWANCE')->first();
        $transportComp = PayComponent::where('code', 'TRANSPORT_ALLOWANCE')->first();

        $this->deductionService->updateConfig([
            'is_active' => true,
            'component_ids' => [$mealComp->id, $transportComp->id],
        ]);

        $employee = $this->createEmployee([
            'uang_makan_per_hari' => 25000,
            'uang_transportasi_per_hari' => 15000,
        ]);
        $period = $this->createPeriod(25);

        // Generate payroll
        $this->payslipService->generatePayrollForPeriod($period, $this->personalia->id);

        $payslip = Payslip::where('payroll_period_id', $period->id)
            ->where('employee_id', $employee->id)
            ->first();

        $this->assertEquals(0, (float) $payslip->absent_days);

        // Check items: no attendance deduction item
        $attItems = $payslip->items()->where('category', 'attendance_deduction')->get();
        $this->assertCount(0, $attItems);
        $this->assertEquals(0, (float) $payslip->total_deductions);
    }

    /**
     * 4. Tidak masuk 1 hari: uang makan Rp25.000 dan transportasi Rp15.000 terpotong Rp40.000.
     */
    public function test_deduction_calculation_when_absent_is_one_day(): void
    {
        $mealComp = PayComponent::where('code', 'MEAL_ALLOWANCE')->first();
        $transportComp = PayComponent::where('code', 'TRANSPORT_ALLOWANCE')->first();

        $this->deductionService->updateConfig([
            'is_active' => true,
            'component_ids' => [$mealComp->id, $transportComp->id],
        ]);

        $employee = $this->createEmployee([
            'gaji_pokok' => 5000000,
            'uang_makan_per_hari' => 25000,
            'uang_transportasi_per_hari' => 15000,
        ]);
        $period = $this->createPeriod(25);

        // First generate payroll
        $this->payslipService->generatePayrollForPeriod($period, $this->personalia->id);

        $payslip = Payslip::where('payroll_period_id', $period->id)
            ->where('employee_id', $employee->id)
            ->first();

        // Update absent_days to 1 day and recalculate
        $updatedPayslip = $this->payslipService->recalculateAttendanceDeductions($payslip, 1.0);

        $this->assertEquals(1.0, (float) $updatedPayslip->absent_days);

        // Meal cut: 25.000, Transport cut: 15.000 -> Total cut: 40.000
        $mealCut = $updatedPayslip->items()->where('item_code_snapshot', 'ATT_CUT_MEAL_ALLOWANCE')->first();
        $transportCut = $updatedPayslip->items()->where('item_code_snapshot', 'ATT_CUT_TRANSPORT_ALLOWANCE')->first();

        $this->assertNotNull($mealCut);
        $this->assertEquals(25000.0, (float) $mealCut->amount);
        $this->assertEquals(25000.0, (float) $mealCut->rate);
        $this->assertEquals(1.0, (float) $mealCut->quantity);

        $this->assertNotNull($transportCut);
        $this->assertEquals(15000.0, (float) $transportCut->amount);
        $this->assertEquals(15000.0, (float) $transportCut->rate);
        $this->assertEquals(1.0, (float) $transportCut->quantity);

        $this->assertEquals(40000.0, (float) $updatedPayslip->total_deductions);

        // Check net salary consistency
        $expectedNet = (float) $updatedPayslip->total_earnings - 40000.0;
        $this->assertEquals($expectedNet, (float) $updatedPayslip->net_salary);
    }

    /**
     * 5. Tidak masuk beberapa hari (misal 3.5 hari desimal).
     */
    public function test_deduction_calculation_when_absent_is_multiple_and_decimal_days(): void
    {
        $mealComp = PayComponent::where('code', 'MEAL_ALLOWANCE')->first();
        $transportComp = PayComponent::where('code', 'TRANSPORT_ALLOWANCE')->first();

        $this->deductionService->updateConfig([
            'is_active' => true,
            'component_ids' => [$mealComp->id, $transportComp->id],
        ]);

        $employee = $this->createEmployee([
            'uang_makan_per_hari' => 20000,
            'uang_transportasi_per_hari' => 10000,
        ]);
        $period = $this->createPeriod(25);

        $this->payslipService->generatePayrollForPeriod($period, $this->personalia->id);
        $payslip = Payslip::where('payroll_period_id', $period->id)->where('employee_id', $employee->id)->first();

        // Recalculate with 3.5 absent days
        $updated = $this->payslipService->recalculateAttendanceDeductions($payslip, 3.5);

        // Meal cut: 20.000 * 3.5 = 70.000
        // Transport cut: 10.000 * 3.5 = 35.000
        // Total cut: 105.000
        $mealCut = $updated->items()->where('item_code_snapshot', 'ATT_CUT_MEAL_ALLOWANCE')->first();
        $transportCut = $updated->items()->where('item_code_snapshot', 'ATT_CUT_TRANSPORT_ALLOWANCE')->first();

        $this->assertEquals(70000.0, (float) $mealCut->amount);
        $this->assertEquals(35000.0, (float) $transportCut->amount);
        $this->assertEquals(105000.0, (float) $updated->total_deductions);
    }

    /**
     * 6. Validasi nilai hari tidak masuk invalid (negatif).
     */
    public function test_invalid_negative_absent_days_validation(): void
    {
        $employee = $this->createEmployee();
        $period = $this->createPeriod(25);

        $this->payslipService->generatePayrollForPeriod($period, $this->personalia->id);
        $payslip = Payslip::where('payroll_period_id', $period->id)->where('employee_id', $employee->id)->first();

        $response = $this->actingAs($this->personalia)
            ->put(route('payslips.update', $payslip->id), [
                'absent_days' => -2,
                'items' => [
                    [
                        'description_snapshot' => 'Gaji Pokok',
                        'item_type' => 'earning',
                        'amount' => 5000000,
                    ],
                ],
            ]);

        $response->assertSessionHasErrors('absent_days');
    }

    /**
     * 7. Komponen yang tidak dipilih tidak terkena potongan.
     */
    public function test_unselected_components_are_not_deducted(): void
    {
        $mealComp = PayComponent::where('code', 'MEAL_ALLOWANCE')->first();

        // Only select Meal Allowance (Transport allowance NOT selected)
        $this->deductionService->updateConfig([
            'is_active' => true,
            'component_ids' => [$mealComp->id],
        ]);

        $employee = $this->createEmployee([
            'uang_makan_per_hari' => 25000,
            'uang_transportasi_per_hari' => 15000,
            'gaji_pokok' => 5000000,
        ]);
        $period = $this->createPeriod(25);

        $this->payslipService->generatePayrollForPeriod($period, $this->personalia->id);
        $payslip = Payslip::where('payroll_period_id', $period->id)->where('employee_id', $employee->id)->first();

        $updated = $this->payslipService->recalculateAttendanceDeductions($payslip, 2.0);

        // Only Meal is deducted: 25.000 * 2 = 50.000
        $this->assertNotNull($updated->items()->where('item_code_snapshot', 'ATT_CUT_MEAL_ALLOWANCE')->first());
        $this->assertNull($updated->items()->where('item_code_snapshot', 'ATT_CUT_TRANSPORT_ALLOWANCE')->first());
        $this->assertNull($updated->items()->where('item_code_snapshot', 'ATT_CUT_BASIC_SALARY')->first());

        $this->assertEquals(50000.0, (float) $updated->total_deductions);
    }

    /**
     * 8. Perhitungan uang makan dan transportasi sesuai master rate.
     */
    public function test_meal_and_transport_calculation_rates(): void
    {
        $mealComp = PayComponent::where('code', 'MEAL_ALLOWANCE')->first();
        $transportComp = PayComponent::where('code', 'TRANSPORT_ALLOWANCE')->first();

        $this->deductionService->updateConfig([
            'is_active' => true,
            'component_ids' => [$mealComp->id, $transportComp->id],
        ]);

        $emp1 = $this->createEmployee([
            'uang_makan_per_hari' => 30000,
            'uang_transportasi_per_hari' => 20000,
        ]);

        $emp2 = $this->createEmployee([
            'uang_makan_per_hari' => 45000,
            'uang_transportasi_per_hari' => 10000,
        ]);

        $period = $this->createPeriod(25);
        $this->payslipService->generatePayrollForPeriod($period, $this->personalia->id);

        $slip1 = Payslip::where('payroll_period_id', $period->id)->where('employee_id', $emp1->id)->first();
        $slip2 = Payslip::where('payroll_period_id', $period->id)->where('employee_id', $emp2->id)->first();

        $updated1 = $this->payslipService->recalculateAttendanceDeductions($slip1, 1.0);
        $updated2 = $this->payslipService->recalculateAttendanceDeductions($slip2, 1.0);

        // Emp1: 30.000 + 20.000 = 50.000
        $this->assertEquals(50000.0, (float) $updated1->total_deductions);

        // Emp2: 45.000 + 10.000 = 55.000
        $this->assertEquals(55000.0, (float) $updated2->total_deductions);
    }

    /**
     * 9. Periode payroll dengan jumlah hari berbeda (misal 20 hari vs 25 hari untuk fixed component).
     */
    public function test_payroll_period_with_different_working_days(): void
    {
        $posComp = PayComponent::where('code', 'POSITION_ALLOWANCE')->first();

        // Personal selects monthly fixed component Position Allowance
        $this->deductionService->updateConfig([
            'is_active' => true,
            'component_ids' => [$posComp->id],
        ]);

        $employee = $this->createEmployee([
            'tunjangan_jabatan' => 1000000, // Rp 1.000.000 / month
        ]);

        // Period A: 20 working days -> Daily rate = 1.000.000 / 20 = 50.000
        $periodA = $this->createPeriod(20);
        $this->payslipService->generatePayrollForPeriod($periodA, $this->personalia->id);
        $slipA = Payslip::where('payroll_period_id', $periodA->id)->where('employee_id', $employee->id)->first();
        $updatedA = $this->payslipService->recalculateAttendanceDeductions($slipA, 1.0);
        $this->assertEquals(50000.0, (float) $updatedA->total_deductions);

        // Period B: 25 working days -> Daily rate = 1.000.000 / 25 = 40.000
        $periodB = $this->createPeriod(25);
        $this->payslipService->generatePayrollForPeriod($periodB, $this->personalia->id);
        $slipB = Payslip::where('payroll_period_id', $periodB->id)->where('employee_id', $employee->id)->first();
        $updatedB = $this->payslipService->recalculateAttendanceDeductions($slipB, 1.0);
        $this->assertEquals(40000.0, (float) $updatedB->total_deductions);
    }

    /**
     * 10. Generate ulang tidak menggandakan potongan.
     */
    public function test_recalculating_or_regenerating_payroll_does_not_duplicate_deductions(): void
    {
        $mealComp = PayComponent::where('code', 'MEAL_ALLOWANCE')->first();
        $transportComp = PayComponent::where('code', 'TRANSPORT_ALLOWANCE')->first();

        $this->deductionService->updateConfig([
            'is_active' => true,
            'component_ids' => [$mealComp->id, $transportComp->id],
        ]);

        $employee = $this->createEmployee([
            'uang_makan_per_hari' => 25000,
            'uang_transportasi_per_hari' => 15000,
        ]);
        $period = $this->createPeriod(25);

        // Initial generate
        $this->payslipService->generatePayrollForPeriod($period, $this->personalia->id);
        $payslip = Payslip::where('payroll_period_id', $period->id)->where('employee_id', $employee->id)->first();

        // Set 2 days absent
        $this->payslipService->recalculateAttendanceDeductions($payslip, 2.0);

        // Re-run generatePayrollForPeriod (Auto Generate repeated)
        $this->payslipService->generatePayrollForPeriod($period, $this->personalia->id);

        $freshPayslip = Payslip::where('payroll_period_id', $period->id)->where('employee_id', $employee->id)->first();

        // Deductions should still be exactly 2 * 40.000 = 80.000, NOT 160.000!
        $this->assertEquals(80000.0, (float) $freshPayslip->total_deductions);

        $attItems = $freshPayslip->items()->where('category', 'attendance_deduction')->get();
        $this->assertCount(2, $attItems); // Exactly 1 for meal, 1 for transport
    }

    /**
     * 11. Perubahan konfigurasi tidak mengubah payroll lama secara otomatis.
     */
    public function test_configuration_change_does_not_retroactively_change_old_payslips(): void
    {
        $mealComp = PayComponent::where('code', 'MEAL_ALLOWANCE')->first();

        $this->deductionService->updateConfig([
            'is_active' => true,
            'component_ids' => [$mealComp->id],
        ]);

        $employee = $this->createEmployee([
            'uang_makan_per_hari' => 25000,
            'uang_transportasi_per_hari' => 15000,
        ]);
        $period = $this->createPeriod(25);

        $this->payslipService->generatePayrollForPeriod($period, $this->personalia->id);
        $payslip = Payslip::where('payroll_period_id', $period->id)->where('employee_id', $employee->id)->first();
        $this->payslipService->recalculateAttendanceDeductions($payslip, 1.0);

        $this->assertEquals(25000.0, (float) $payslip->fresh()->total_deductions);

        // Now change configuration: deactivate attendance deduction entirely
        $this->deductionService->updateConfig([
            'is_active' => false,
            'component_ids' => [],
        ]);

        // The existing payslip MUST NOT change quietly
        $this->assertEquals(25000.0, (float) $payslip->fresh()->total_deductions);
    }

    /**
     * 12. Payroll yang sudah difinalisasi tidak dapat dihitung ulang tanpa prosedur revisi.
     */
    public function test_finalized_payslip_cannot_be_recalculated(): void
    {
        $employee = $this->createEmployee();
        $period = $this->createPeriod(25);

        $this->payslipService->generatePayrollForPeriod($period, $this->personalia->id);
        $payslip = Payslip::where('payroll_period_id', $period->id)->where('employee_id', $employee->id)->first();

        // Manually mark payslip as finalized
        $payslip->update(['status' => PayslipStatus::FINALIZED->value]);

        $this->expectException(DomainException::class);
        $this->payslipService->recalculateAttendanceDeductions($payslip, 1.0);
    }

    /**
     * 13. Total pendapatan, potongan, dan gaji bersih tetap konsisten.
     */
    public function test_financial_consistency_of_net_salary(): void
    {
        $mealComp = PayComponent::where('code', 'MEAL_ALLOWANCE')->first();
        $transportComp = PayComponent::where('code', 'TRANSPORT_ALLOWANCE')->first();

        $this->deductionService->updateConfig([
            'is_active' => true,
            'component_ids' => [$mealComp->id, $transportComp->id],
        ]);

        $employee = $this->createEmployee([
            'gaji_pokok' => 4500000,
            'tunjangan_kehadiran' => 500000,
            'uang_makan_per_hari' => 25000,
            'uang_transportasi_per_hari' => 15000,
        ]);
        $period = $this->createPeriod(25);

        $this->payslipService->generatePayrollForPeriod($period, $this->personalia->id);
        $payslip = Payslip::where('payroll_period_id', $period->id)->where('employee_id', $employee->id)->first();

        $updated = $this->payslipService->recalculateAttendanceDeductions($payslip, 2.0);

        $earnings = (float) $updated->total_earnings;
        $deductions = (float) $updated->total_deductions;
        $net = (float) $updated->net_salary;

        $this->assertEquals(80000.0, $deductions);
        $this->assertEquals($earnings - $deductions, $net);
    }
}
