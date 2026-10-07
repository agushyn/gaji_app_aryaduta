<?php

namespace Tests\Feature;

use App\Enums\PayrollPeriodStatus;
use App\Enums\PayslipStatus;
use App\Models\Employee;
use App\Models\HolidayCalendar;
use App\Models\PayComponent;
use App\Models\PayrollCalculationConfig;
use App\Models\PayrollCalculationConfigVersion;
use App\Models\PayrollPeriod;
use App\Models\Payslip;
use App\Models\User;
use App\Services\FormulaEvaluatorService;
use App\Services\HolidaySyncService;
use App\Services\OvertimeCalculationService;
use App\Services\PayrollDayTypeService;
use App\Services\PayslipService;
use Carbon\Carbon;
use Database\Seeders\PayComponentSeeder;
use Database\Seeders\PayrollCalculationConfigSeeder;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollCalculationConfigTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $personalia;
    protected User $auditor;
    protected PayComponent $overtimeComponent;
    protected OvertimeCalculationService $overtimeService;
    protected PayrollDayTypeService $dayTypeService;
    protected FormulaEvaluatorService $formulaEvaluator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
        $this->seed(PayComponentSeeder::class);
        $this->seed(PayrollCalculationConfigSeeder::class);

        $this->admin = User::where('email', 'admin@example.com')->first();
        $this->personalia = User::where('email', 'personalia@example.com')->first();
        $this->auditor = User::where('email', 'auditor@example.com')->first();

        $this->overtimeComponent = PayComponent::where('code', 'OVERTIME')->first();
        $this->overtimeService = app(OvertimeCalculationService::class);
        $this->dayTypeService = app(PayrollDayTypeService::class);
        $this->formulaEvaluator = app(FormulaEvaluatorService::class);
    }

    /**
     * Test 1: rate = 25.000, days = 3, hours = 8
     * Sesuai aturan: 8 > 3 jam -> Potongan = 3 * 0.5 = 1.5 jam -> Bersih = 6.5 jam.
     * Jam Pertama: 25.000 * 3 * 1.5 = 112.500
     * Jam Berikutnya: (6.5 - 3 = 3.5) * 25.000 * 2 = 175.000
     * Total: 112.500 + 175.000 = 287.500
     */
    public function test_1_overtime_calculation_standard_working_day(): void
    {
        $employee = Employee::create([
            'employee_number' => 'EMP-001',
            'full_name' => 'Agus Hendra',
            'default_overtime_rate' => 25000,
            'employment_status' => 'active',
            'basic_salary' => 5000000,
        ]);

        $result = $this->overtimeService->calculateForEmployee($employee, [
            'working_day_hours' => 8,
            'working_day_days' => 3,
            'holiday_hours' => 0,
        ]);

        $this->assertEquals('287500.00', $result['amount']);
        $this->assertEquals('6.50', $result['quantity']);
        $this->assertEquals('1.50', $result['break_deduction']);
        $this->assertNotEmpty($result['snapshot']['working_day']['trace']);
        $this->assertStringContainsString('Potongan Istirahat: 1.5 jam', implode("\n", $result['trace']));
        $this->assertStringContainsString('Jam Lembur Bersih: 6.5 jam', implode("\n", $result['trace']));
    }

    /**
     * Test 2: rate = 25.000, days = 1, hours = 1, multiplier = 1.5 -> Expected: 37.500
     */
    public function test_2_overtime_calculation_single_hour(): void
    {
        $employee = Employee::create([
            'employee_number' => 'EMP-002',
            'full_name' => 'Budi Santoso',
            'default_overtime_rate' => 25000,
            'employment_status' => 'active',
            'basic_salary' => 5000000,
        ]);

        $result = $this->overtimeService->calculateForEmployee($employee, [
            'working_day_hours' => 1,
            'working_day_days' => 1,
            'holiday_hours' => 0,
        ]);

        // First hour: 25,000 * 1 * 1.5 = 37,500
        // Subsequent hours: (1 - 1 = 0) = 0
        // Total: 37,500
        $this->assertEquals('37500.00', $result['amount']);
        $this->assertEquals('1.00', $result['quantity']);
        $this->assertEquals('0.00', $result['break_deduction']);
    }

    /**
     * Test 3: rate = 25.000, days = 1, hours = 2, first = 1.5, next = 2 -> Expected: 37.500 + 50.000 = 87.500
     */
    public function test_3_overtime_calculation_three_hours_one_day(): void
    {
        $employee = Employee::create([
            'employee_number' => 'EMP-003',
            'full_name' => 'Citra Dewi',
            'default_overtime_rate' => 25000,
            'employment_status' => 'active',
            'basic_salary' => 5000000,
        ]);

        $resultFor2Hours = $this->overtimeService->calculateForEmployee($employee, [
            'working_day_hours' => 2,
            'working_day_days' => 1,
            'holiday_hours' => 0,
        ]);
        $this->assertEquals('87500.00', $resultFor2Hours['amount']);
        $this->assertEquals('2.00', $resultFor2Hours['quantity']);
        $this->assertEquals('0.00', $resultFor2Hours['break_deduction']);
    }

    /**
     * Test 4 — Holiday: rate = 25.000, hours = 8, days = 1 -> 8 > 4 -> Potongan = 1 jam -> Bersih = 7 jam -> Expected: 7 * 25.000 * 2 = 350.000
     */
    public function test_4_overtime_calculation_holiday(): void
    {
        $employee = Employee::create([
            'employee_number' => 'EMP-004',
            'full_name' => 'Dedi Prasetyo',
            'default_overtime_rate' => 25000,
            'employment_status' => 'active',
            'basic_salary' => 5000000,
        ]);

        $result = $this->overtimeService->calculateForEmployee($employee, [
            'working_day_hours' => 0,
            'working_day_days' => 0,
            'holiday_hours' => 8,
            'holiday_days' => 1,
        ]);

        // 7 * 25,000 * 2 = 350,000
        $this->assertEquals('350000.00', $result['amount']);
        $this->assertEquals(25000, (float) $result['rate']);
        $this->assertEquals(7, (float) $result['quantity']);
        $this->assertEquals('1.00', $result['break_deduction']);
    }

    /**
     * Test 5 — No overtime: hours = 0 -> Expected: 0
     */
    public function test_5_overtime_calculation_zero_hours(): void
    {
        $employee = Employee::create([
            'employee_number' => 'EMP-005',
            'full_name' => 'Eka Saputra',
            'default_overtime_rate' => 25000,
            'employment_status' => 'active',
            'basic_salary' => 5000000,
        ]);

        $result = $this->overtimeService->calculateForEmployee($employee, [
            'working_day_hours' => 0,
            'working_day_days' => 0,
            'holiday_hours' => 0,
        ]);

        $this->assertEquals('0.00', $result['amount']);
    }

    /**
     * Test 6 — Holiday detection:
     * Tanggal yang terdapat pada kalender nasional harus menghasilkan: national_holiday
     */
    public function test_6_holiday_detection_national_holiday(): void
    {
        // Insert August 17 as national holiday
        HolidayCalendar::create([
            'date' => '2026-08-17',
            'name' => 'Hari Kemerdekaan RI',
            'type' => 'national_holiday',
            'is_joint_leave' => false,
            'is_payroll_holiday' => true,
            'year' => 2026,
            'source' => 'skb_3_menteri',
            'is_active' => true,
        ]);

        $dayType = $this->dayTypeService->getDayType('2026-08-17');
        $this->assertEquals('national_holiday', $dayType);

        $isOvertimeHoliday = $this->dayTypeService->isOvertimeHoliday('2026-08-17');
        $this->assertTrue($isOvertimeHoliday);
    }

    /**
     * Test 7 — Joint leave:
     * Tanggal cuti bersama menghasilkan: joint_leave tetapi hanya diperlakukan sebagai holiday payroll jika setting: is_payroll_holiday = true
     */
    public function test_7_joint_leave_payroll_holiday_behavior(): void
    {
        // 1. Joint leave with is_payroll_holiday = false
        $jointLeave = HolidayCalendar::create([
            'date' => '2026-02-16',
            'name' => 'Cuti Bersama Tahun Baru Imlek',
            'type' => 'joint_leave',
            'is_joint_leave' => true,
            'is_payroll_holiday' => false,
            'year' => 2026,
            'source' => 'skb_3_menteri',
            'is_active' => true,
        ]);

        $dayType = $this->dayTypeService->getDayType('2026-02-16');
        $this->assertEquals('joint_leave', $dayType);

        // Since is_payroll_holiday = false and Feb 16, 2026 is Monday, isOvertimeHoliday should be false
        $isOvertimeHoliday = $this->dayTypeService->isOvertimeHoliday('2026-02-16');
        $this->assertFalse($isOvertimeHoliday);

        // 2. Toggle is_payroll_holiday = true
        $jointLeave->update(['is_payroll_holiday' => true]);

        $isOvertimeHolidayNow = $this->dayTypeService->isOvertimeHoliday('2026-02-16');
        $this->assertTrue($isOvertimeHolidayNow);
    }

    /**
     * Test 8 — Formula version:
     * Payroll finalized dengan version 1 tidak berubah ketika configuration berubah menjadi version 2.
     */
    public function test_8_formula_version_immutability_for_finalized_payslip(): void
    {
        $employee = Employee::create([
            'employee_number' => 'EMP-008',
            'full_name' => 'Gilang Ramadhan',
            'default_overtime_rate' => 25000,
            'employment_status' => 'active',
            'basic_salary' => 5000000,
        ]);

        $period = PayrollPeriod::create([
            'period_code' => '2026-09',
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
            'cutoff_date' => '2026-09-25',
            'working_days' => 25,
            'status' => PayrollPeriodStatus::DRAFT->value,
            'created_by' => $this->admin->id,
        ]);

        $payslipService = app(PayslipService::class);

        // Calculate overtime item using Version 1
        $overtimeItem = $payslipService->calculateOvertimeItem($employee, [
            'working_day_hours' => 8,
            'working_day_days' => 3,
        ]);

        $this->assertEquals('287500.00', $overtimeItem['amount']);

        $payslip = $payslipService->createPayslip($period, [
            'employee_id' => $employee->id,
            'items' => [
                [
                    'pay_component_id' => PayComponent::where('code', 'BASIC_SALARY')->first()->id,
                    'item_code_snapshot' => 'BASIC_SALARY',
                    'description_snapshot' => 'Gaji Pokok',
                    'item_type' => 'earning',
                    'amount' => 5000000,
                ],
                $overtimeItem,
            ],
        ]);

        // Finalize the payslip
        $payslip->update(['status' => PayslipStatus::FINALIZED->value]);

        $savedItem = $payslip->items()->where('item_code_snapshot', 'OVERTIME')->first();
        $this->assertNotNull($savedItem);
        $this->assertEquals(1, $savedItem->calculation_config_version);
        $this->assertEquals('287500.00', $savedItem->amount);

        // Now modify configuration to Version 2: Change first hour multiplier from 1.5 to 2.0
        $workingConfig = PayrollCalculationConfig::where('code', 'OVERTIME_WORKING_DAY')->first();
        $configService = app(\App\Services\PayrollCalculationConfigService::class);

        $newFormula = $this->overtimeService->getDefaultWorkingDayFormula();
        // Modify multiplier
        $newFormula['operands'][0]['operands'][2]['default'] = 2.0;

        $updatedConfig = $configService->updateConfig($workingConfig, [
            'name' => $workingConfig->name,
            'component_id' => $workingConfig->component_id,
            'day_type' => $workingConfig->day_type,
            'formula' => $newFormula,
            'description' => 'Versi 2 dengan multiplier jam pertama 2.0',
        ]);

        $this->assertEquals(2, $updatedConfig->current_version);

        // Verify the finalized payslip item STILL has Version 1 and identical amount/snapshot!
        $finalizedItemRefreshed = $payslip->items()->where('item_code_snapshot', 'OVERTIME')->first();
        $this->assertEquals(1, $finalizedItemRefreshed->calculation_config_version);
        $this->assertEquals('287500.00', $finalizedItemRefreshed->amount);
        $this->assertEquals(1, $finalizedItemRefreshed->calculation_snapshot['working_day']['config_version']);
    }

    /**
     * Test interactive formula test endpoint.
     */
    public function test_interactive_formula_test_endpoint(): void
    {
        $formula = $this->overtimeService->getDefaultWorkingDayFormula();

        $response = $this->actingAs($this->personalia)->postJson('/payroll-configurations/test', [
            'formula' => $formula,
            'rate' => 25000,
            'days' => 3,
            'hours' => 8,
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'result' => '362500.00',
        ]);
        $response->assertJsonStructure(['preview', 'trace', 'steps', 'inputs']);
    }

    /**
     * Test prevention of hard-deleting configuration used in finalized payslips.
     */
    public function test_cannot_delete_config_used_in_finalized_payslips(): void
    {
        $workingConfig = PayrollCalculationConfig::where('code', 'OVERTIME_WORKING_DAY')->first();

        $employee = Employee::create([
            'employee_number' => 'EMP-009',
            'full_name' => 'Hendra Setiawan',
            'default_overtime_rate' => 25000,
            'employment_status' => 'active',
            'basic_salary' => 5000000,
        ]);

        $period = PayrollPeriod::create([
            'period_code' => '2026-10',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-31',
            'cutoff_date' => '2026-10-25',
            'working_days' => 25,
            'status' => PayrollPeriodStatus::FINALIZED->value,
            'created_by' => $this->admin->id,
        ]);

        $payslip = Payslip::create([
            'payroll_period_id' => $period->id,
            'employee_id' => $employee->id,
            'slip_number' => 'SLIP/2026-10/EMP-009',
            'employee_number_snapshot' => $employee->employee_number,
            'employee_name_snapshot' => $employee->full_name,
            'department_snapshot' => 'HRD',
            'position_snapshot' => 'Staff',
            'status' => PayslipStatus::FINALIZED->value,
            'basic_salary' => 5000000,
            'total_earnings' => 5362500,
            'total_deductions' => 0,
            'net_salary' => 5362500,
            'created_by' => $this->admin->id,
        ]);

        $payslip->items()->create([
            'calculation_config_id' => $workingConfig->id,
            'calculation_config_version' => 1,
            'item_code_snapshot' => 'OVERTIME',
            'description_snapshot' => 'Uang Lembur',
            'item_type' => 'earning',
            'amount' => 362500,
            'sort_order' => 1,
        ]);

        $this->assertTrue($workingConfig->isUsedInFinalizedPayslips());

        // Attempt delete with admin (who has delete permission)
        $response = $this->actingAs($this->admin)->delete("/payroll-configurations/{$workingConfig->id}");
        $response->assertSessionHas('error');

        $this->assertDatabaseHas('payroll_calculation_configs', ['id' => $workingConfig->id]);
    }

    /**
     * Test skenario potongan istirahat Hari Kerja:
     * 1 hari, 3 jam  → potongan 0     → bersih 3 jam   → nominal 137.500
     * 1 hari, 4 jam  → potongan 0.5   → bersih 3.5 jam → nominal 162.500
     * 2 hari, 6 jam  → potongan 1     → bersih 5 jam   → nominal 225.000
     * 3 hari, 8 jam  → potongan 1.5   → bersih 6.5 jam → nominal 287.500
     */
    public function test_working_day_break_deduction_scenarios(): void
    {
        $employee = Employee::create([
            'employee_number' => 'EMP-TEST-WD',
            'full_name' => 'Karyawan Test Hari Kerja',
            'default_overtime_rate' => 25000,
            'employment_status' => 'active',
            'basic_salary' => 5000000,
        ]);

        // Skenario 1a: 1 hari, 2 jam (< 3 jam -> tidak dipotong -> bersih 2 jam)
        $res1a = $this->overtimeService->calculateForEmployee($employee, [
            'working_day_hours' => 2,
            'working_day_days' => 1,
        ]);
        $this->assertEquals('87500.00', $res1a['amount']);
        $this->assertEquals('2.00', $res1a['quantity']);
        $this->assertEquals('0.00', $res1a['break_deduction']);

        // Skenario 1b: 1 hari, 3 jam (>= 3 jam -> dipotong 0.5 jam -> bersih 2.5 jam)
        // Jam pertama: 1 jam * 1.5 * 25.000 = 37.500
        // Jam berikutnya: (2.5 - 1) = 1.5 jam * 2.0 * 25.000 = 75.000 -> Total 112.500
        $res1b = $this->overtimeService->calculateForEmployee($employee, [
            'working_day_hours' => 3,
            'working_day_days' => 1,
        ]);
        $this->assertEquals('112500.00', $res1b['amount']);
        $this->assertEquals('2.50', $res1b['quantity']);
        $this->assertEquals('0.50', $res1b['break_deduction']);

        // Skenario 2: 1 hari, 4 jam (>= 3 jam -> potong 0.5 jam -> bersih 3.5 jam)
        $res2 = $this->overtimeService->calculateForEmployee($employee, [
            'working_day_hours' => 4,
            'working_day_days' => 1,
        ]);
        $this->assertEquals('162500.00', $res2['amount']);
        $this->assertEquals('3.50', $res2['quantity']);
        $this->assertEquals('0.50', $res2['break_deduction']);

        // Skenario 3: 2 hari, 6 jam (>= 3 jam -> potong 2 * 0.5 = 1 jam -> bersih 5 jam)
        $res3 = $this->overtimeService->calculateForEmployee($employee, [
            'working_day_hours' => 6,
            'working_day_days' => 2,
        ]);
        $this->assertEquals('225000.00', $res3['amount']);
        $this->assertEquals('5.00', $res3['quantity']);
        $this->assertEquals('1.00', $res3['break_deduction']);

        // Skenario 4: 3 hari, 8 jam (>= 3 jam -> potong 3 * 0.5 = 1.5 jam -> bersih 6.5 jam)
        $res4 = $this->overtimeService->calculateForEmployee($employee, [
            'working_day_hours' => 8,
            'working_day_days' => 3,
        ]);
        $this->assertEquals('287500.00', $res4['amount']);
        $this->assertEquals('6.50', $res4['quantity']);
        $this->assertEquals('1.50', $res4['break_deduction']);
    }

    /**
     * Test skenario potongan istirahat Hari Libur:
     * 1 hari, 3 jam  → potongan 0   → bersih 3 jam → nominal 150.000
     * 1 hari, 4 jam  → potongan 1   → bersih 3 jam → nominal 150.000
     * 1 hari, 5 jam  → potongan 1   → bersih 4 jam → nominal 200.000
     * 2 hari, 10 jam → potongan 2   → bersih 8 jam → nominal 400.000
     * 3 hari, 12 jam → potongan 3   → bersih 9 jam → nominal 450.000
     */
    public function test_holiday_break_deduction_scenarios(): void
    {
        $employee = Employee::create([
            'employee_number' => 'EMP-TEST-HL',
            'full_name' => 'Karyawan Test Hari Libur',
            'default_overtime_rate' => 25000,
            'employment_status' => 'active',
            'basic_salary' => 5000000,
        ]);

        // Skenario 1a: 1 hari, 3 jam (< 4 jam -> tidak dipotong -> bersih 3 jam)
        $res1a = $this->overtimeService->calculateForEmployee($employee, [
            'holiday_hours' => 3,
            'holiday_days' => 1,
        ]);
        $this->assertEquals('150000.00', $res1a['amount']);
        $this->assertEquals('3.00', $res1a['quantity']);
        $this->assertEquals('0.00', $res1a['break_deduction']);

        // Skenario 1b: 1 hari, 4 jam (>= 4 jam -> dipotong 1 jam -> bersih 3 jam)
        $res1b = $this->overtimeService->calculateForEmployee($employee, [
            'holiday_hours' => 4,
            'holiday_days' => 1,
        ]);
        $this->assertEquals('150000.00', $res1b['amount']);
        $this->assertEquals('3.00', $res1b['quantity']);
        $this->assertEquals('1.00', $res1b['break_deduction']);

        // Skenario 2: 1 hari, 5 jam (>= 4 jam -> potong 1 jam -> bersih 4 jam)
        $res2 = $this->overtimeService->calculateForEmployee($employee, [
            'holiday_hours' => 5,
            'holiday_days' => 1,
        ]);
        $this->assertEquals('200000.00', $res2['amount']);
        $this->assertEquals('4.00', $res2['quantity']);
        $this->assertEquals('1.00', $res2['break_deduction']);

        // Skenario 3: 2 hari, 10 jam (>= 4 jam -> potong 2 * 1 = 2 jam -> bersih 8 jam)
        $res3 = $this->overtimeService->calculateForEmployee($employee, [
            'holiday_hours' => 10,
            'holiday_days' => 2,
        ]);
        $this->assertEquals('400000.00', $res3['amount']);
        $this->assertEquals('8.00', $res3['quantity']);
        $this->assertEquals('2.00', $res3['break_deduction']);

        // Skenario 4: 3 hari, 12 jam (>= 4 jam -> potong 3 * 1 = 3 jam -> bersih 9 jam)
        $res4 = $this->overtimeService->calculateForEmployee($employee, [
            'holiday_hours' => 12,
            'holiday_days' => 3,
        ]);
        $this->assertEquals('450000.00', $res4['amount']);
        $this->assertEquals('9.00', $res4['quantity']);
        $this->assertEquals('3.00', $res4['break_deduction']);
    }

    /**
     * Test edge cases:
     * - Hari kerja 2:59 (179 menit): tidak dipotong
     * - Hari kerja tepat 3 jam (180 menit): dipotong 0.5 * hari -> 2.5 jam
     * - Hari kerja 3.01 jam (181 menit): dipotong 0.5 * hari
     * - Hari libur 3:59 (239 menit): tidak dipotong
     * - Hari libur tepat 4 jam (240 menit): dipotong 1 * hari -> 3.0 jam
     * - Hari libur 4.01 jam (241 menit): dipotong 1 * hari
     * - Total jam 0: potongan 0
     * - Jam negatif: throws InvalidArgumentException
     */
    public function test_break_deduction_threshold_edge_cases(): void
    {
        $employee = Employee::create([
            'employee_number' => 'EMP-TEST-EDGE',
            'full_name' => 'Edge Case Karyawan',
            'default_overtime_rate' => 25000,
            'employment_status' => 'active',
            'basic_salary' => 5000000,
        ]);

        // 1. 2:59 hari kerja (179 menit): TIDAK dipotong
        $wd259 = $this->overtimeService->calculateBreakDeduction('2:59', 1, 'working_day');
        $this->assertFalse($wd259['is_deducted']);
        $this->assertEquals(0.0, $wd259['deduction']);
        $this->assertEquals(round(179 / 60, 4), $wd259['net_hours']);

        // 2. Tepat 3 jam hari kerja (180 menit): DIPOTONG 0.5 jam -> 2.5 jam
        $wdExact3 = $this->overtimeService->calculateBreakDeduction(3.0, 1, 'working_day');
        $this->assertTrue($wdExact3['is_deducted']);
        $this->assertEquals(0.5, $wdExact3['deduction']);
        $this->assertEquals(2.5, $wdExact3['net_hours']);

        // 3. 3.01 jam hari kerja (181 menit): DIPOTONG 0.5 jam -> 2.51 jam
        $wdOver3 = $this->overtimeService->calculateBreakDeduction(3.01, 1, 'working_day');
        $this->assertTrue($wdOver3['is_deducted']);
        $this->assertEquals(0.5, $wdOver3['deduction']);
        $this->assertEquals(2.51, $wdOver3['net_hours']);

        // 4. 3:59 hari libur (239 menit): TIDAK dipotong
        $hl359 = $this->overtimeService->calculateBreakDeduction('3:59', 1, 'holiday');
        $this->assertFalse($hl359['is_deducted']);
        $this->assertEquals(0.0, $hl359['deduction']);
        $this->assertEquals(round(239 / 60, 4), $hl359['net_hours']);

        // 5. Tepat 4 jam hari libur (240 menit): DIPOTONG 1 jam -> 3.0 jam
        $hlExact4 = $this->overtimeService->calculateBreakDeduction(4.0, 1, 'holiday');
        $this->assertTrue($hlExact4['is_deducted']);
        $this->assertEquals(1.0, $hlExact4['deduction']);
        $this->assertEquals(3.0, $hlExact4['net_hours']);

        // 6. 4.01 jam hari libur (241 menit): DIPOTONG 1 jam -> 3.01 jam
        $hlOver4 = $this->overtimeService->calculateBreakDeduction(4.01, 1, 'holiday');
        $this->assertTrue($hlOver4['is_deducted']);
        $this->assertEquals(1.0, $hlOver4['deduction']);
        $this->assertEquals(3.01, $hlOver4['net_hours']);

        // 7. Total jam 0
        $zero = $this->overtimeService->calculateBreakDeduction(0, 0, 'working_day');
        $this->assertFalse($zero['is_deducted']);
        $this->assertEquals(0.0, $zero['deduction']);
        $this->assertEquals(0.0, $zero['net_hours']);

        // 8. Jam negatif: exception
        $this->expectException(\InvalidArgumentException::class);
        $this->overtimeService->calculateBreakDeduction(-2, 1, 'working_day');
    }

    /**
     * Test calculation trace memuat rincian potongan istirahat secara lengkap.
     */
    public function test_calculation_trace_contains_break_deduction_details(): void
    {
        $employee = Employee::create([
            'employee_number' => 'EMP-TEST-TRACE',
            'full_name' => 'Karyawan Audit Trace',
            'default_overtime_rate' => 25000,
            'employment_status' => 'active',
            'basic_salary' => 5000000,
        ]);

        // Hari Kerja: 8 jam, 3 hari
        $resWd = $this->overtimeService->calculateForEmployee($employee, [
            'working_day_hours' => 8,
            'working_day_days' => 3,
        ]);
        $traceWd = implode("\n", $resWd['trace']);
        $this->assertStringContainsString('Total Jam Lembur: 8 jam', $traceWd);
        $this->assertStringContainsString('Jumlah Hari Lembur: 3 hari', $traceWd);
        $this->assertStringContainsString('Day Type: Hari Kerja', $traceWd);
        $this->assertStringContainsString('Batas Potongan: 3 jam', $traceWd);
        $this->assertStringContainsString('Potongan Istirahat: 1.5 jam', $traceWd);
        $this->assertStringContainsString('Jam Lembur Bersih: 6.5 jam', $traceWd);

        // Hari Libur: 10 jam, 2 hari
        $resHl = $this->overtimeService->calculateForEmployee($employee, [
            'holiday_hours' => 10,
            'holiday_days' => 2,
        ]);
        $traceHl = implode("\n", $resHl['trace']);
        $this->assertStringContainsString('Total Jam Lembur: 10 jam', $traceHl);
        $this->assertStringContainsString('Jumlah Hari Lembur: 2 hari', $traceHl);
        $this->assertStringContainsString('Day Type: Hari Libur', $traceHl);
        $this->assertStringContainsString('Batas Potongan: 4 jam', $traceHl);
        $this->assertStringContainsString('Potongan Istirahat: 2 jam', $traceHl);
        $this->assertStringContainsString('Jam Lembur Bersih: 8 jam', $traceHl);

        // Hari Kerja tidak melewati threshold: 2 jam, 1 hari (< 3 jam)
        $resNoBreak = $this->overtimeService->calculateForEmployee($employee, [
            'working_day_hours' => 2,
            'working_day_days' => 1,
        ]);
        $traceNoBreak = implode("\n", $resNoBreak['trace']);
        $this->assertStringContainsString('Potongan Istirahat: 0 jam (< batas potongan)', $traceNoBreak);
        $this->assertStringContainsString('Jam Lembur Bersih: 2 jam', $traceNoBreak);
    }

    /**
     * Test case wajib Hari Biasa:
     * 2:59 → tidak dipotong
     * 3:00 → dipotong
     * 3:01 → dipotong
     * 3:05 → dipotong
     * 3:10 → dipotong
     * 4:00 → dipotong
     */
    public function test_mandatory_working_day_time_format_scenarios(): void
    {
        // 2:59 (179 menit) → tidak dipotong
        $c0 = $this->overtimeService->calculateBreakDeduction('2:59', 1, 'working_day');
        $this->assertFalse($c0['is_deducted'], '2:59 tidak boleh dipotong');
        $this->assertEquals(0.0, $c0['deduction']);
        $this->assertEquals(round(179 / 60, 4), $c0['net_hours']);
        $this->assertEquals(179, $c0['gross_minutes']);

        // 3:00 (180 menit) → dipotong 0.5 jam (>= 180 menit)
        $c1 = $this->overtimeService->calculateBreakDeduction('3:00', 1, 'working_day');
        $this->assertTrue($c1['is_deducted'], '3:00 wajib dipotong');
        $this->assertEquals(0.5, $c1['deduction']);
        $this->assertEquals(2.5, $c1['net_hours']);
        $this->assertEquals(180, $c1['gross_minutes']);

        // 3:01 (181 menit) → dipotong 0.5 jam
        $c2 = $this->overtimeService->calculateBreakDeduction('3:01', 1, 'working_day');
        $this->assertTrue($c2['is_deducted'], '3:01 wajib dipotong');
        $this->assertEquals(0.5, $c2['deduction']);
        $this->assertEquals(round(181 / 60 - 0.5, 4), $c2['net_hours']);
        $this->assertEquals(181, $c2['gross_minutes']);

        // 3:05 (185 menit) → dipotong 0.5 jam
        $c3 = $this->overtimeService->calculateBreakDeduction('3:05', 1, 'working_day');
        $this->assertTrue($c3['is_deducted'], '3:05 wajib dipotong');
        $this->assertEquals(0.5, $c3['deduction']);
        $this->assertEquals(round(185 / 60 - 0.5, 4), $c3['net_hours']);
        $this->assertEquals(185, $c3['gross_minutes']);

        // 3:10 (190 menit) → dipotong 0.5 jam
        $c4 = $this->overtimeService->calculateBreakDeduction('3:10', 1, 'working_day');
        $this->assertTrue($c4['is_deducted'], '3:10 wajib dipotong');
        $this->assertEquals(0.5, $c4['deduction']);
        $this->assertEquals(round(190 / 60 - 0.5, 4), $c4['net_hours']);
        $this->assertEquals(190, $c4['gross_minutes']);

        // 4:00 (240 menit) → dipotong 0.5 jam
        $c5 = $this->overtimeService->calculateBreakDeduction('4:00', 1, 'working_day');
        $this->assertTrue($c5['is_deducted'], '4:00 wajib dipotong');
        $this->assertEquals(0.5, $c5['deduction']);
        $this->assertEquals(3.5, $c5['net_hours']);
        $this->assertEquals(240, $c5['gross_minutes']);
    }

    /**
     * Test case wajib Hari Libur:
     * 3:59 → tidak dipotong
     * 4:00 → dipotong
     * 4:01 → dipotong
     * 4:05 → dipotong
     * 4:10 → dipotong
     * 5:00 → dipotong
     */
    public function test_mandatory_holiday_time_format_scenarios(): void
    {
        // 3:59 (239 menit) → tidak dipotong
        $c0 = $this->overtimeService->calculateBreakDeduction('3:59', 1, 'holiday');
        $this->assertFalse($c0['is_deducted'], '3:59 holiday tidak boleh dipotong');
        $this->assertEquals(0.0, $c0['deduction']);
        $this->assertEquals(round(239 / 60, 4), $c0['net_hours']);
        $this->assertEquals(239, $c0['gross_minutes']);

        // 4:00 (240 menit) → dipotong 1.0 jam (>= 240 menit)
        $c1 = $this->overtimeService->calculateBreakDeduction('4:00', 1, 'holiday');
        $this->assertTrue($c1['is_deducted'], '4:00 holiday wajib dipotong');
        $this->assertEquals(1.0, $c1['deduction']);
        $this->assertEquals(3.0, $c1['net_hours']);
        $this->assertEquals(240, $c1['gross_minutes']);

        // 4:01 (241 menit) → dipotong 1.0 jam
        $c2 = $this->overtimeService->calculateBreakDeduction('4:01', 1, 'holiday');
        $this->assertTrue($c2['is_deducted'], '4:01 holiday wajib dipotong');
        $this->assertEquals(1.0, $c2['deduction']);
        $this->assertEquals(round(241 / 60 - 1.0, 4), $c2['net_hours']);
        $this->assertEquals(241, $c2['gross_minutes']);

        // 4:05 (245 menit) → dipotong 1.0 jam
        $c3 = $this->overtimeService->calculateBreakDeduction('4:05', 1, 'holiday');
        $this->assertTrue($c3['is_deducted'], '4:05 holiday wajib dipotong');
        $this->assertEquals(1.0, $c3['deduction']);
        $this->assertEquals(round(245 / 60 - 1.0, 4), $c3['net_hours']);
        $this->assertEquals(245, $c3['gross_minutes']);

        // 4:10 (250 menit) → dipotong 1.0 jam
        $c4 = $this->overtimeService->calculateBreakDeduction('4:10', 1, 'holiday');
        $this->assertTrue($c4['is_deducted'], '4:10 holiday wajib dipotong');
        $this->assertEquals(1.0, $c4['deduction']);
        $this->assertEquals(round(250 / 60 - 1.0, 4), $c4['net_hours']);
        $this->assertEquals(250, $c4['gross_minutes']);

        // 5:00 (300 menit) → dipotong 1.0 jam
        $c5 = $this->overtimeService->calculateBreakDeduction('5:00', 1, 'holiday');
        $this->assertTrue($c5['is_deducted'], '5:00 holiday wajib dipotong');
        $this->assertEquals(1.0, $c5['deduction']);
        $this->assertEquals(4.0, $c5['net_hours']);
        $this->assertEquals(300, $c5['gross_minutes']);
    }

    /**
     * Test UpdatePayrollCalculationConfigRequest:
     * - Request valid
     * - Request dengan field nullable
     * - Request dengan field tidak dikirim
     * - Request update existing configuration
     * - Request dengan configuration/data null
     * Memastikan TIDAK ADA: Trying to access array offset on null
     */
    public function test_update_payroll_calculation_config_request_scenarios(): void
    {
        $config = PayrollCalculationConfig::where('code', 'OVERTIME_WORKING_DAY')->first();
        $formula = $this->overtimeService->getDefaultWorkingDayFormula();

        // 1. Request valid update
        $resValid = $this->actingAs($this->admin)->put("/payroll-configurations/{$config->id}", [
            'name' => 'Lembur Hari Biasa Update',
            'code' => $config->code,
            'component_id' => $config->component_id,
            'day_type' => 'working_day',
            'is_active' => true,
            'formula' => $formula,
            'description' => 'Update deskripsi valid',
        ]);
        $resValid->assertSessionHasNoErrors();
        $resValid->assertRedirect(route('payroll-configurations.index'));

        // 2. Request dengan field nullable bernilai null (code null, description null)
        $resNullable = $this->actingAs($this->admin)->put("/payroll-configurations/{$config->id}", [
            'name' => 'Lembur Nullable Test',
            'code' => null,
            'component_id' => $config->component_id,
            'day_type' => 'working_day',
            'formula' => $formula,
            'description' => null,
        ]);
        $resNullable->assertSessionHasNoErrors();

        // 3. Request dengan field tidak dikirim (description omitted)
        $resOmitted = $this->actingAs($this->admin)->put("/payroll-configurations/{$config->id}", [
            'name' => 'Lembur Omitted Field Test',
            'component_id' => $config->component_id,
            'day_type' => 'working_day',
            'formula' => $formula,
        ]);
        $resOmitted->assertSessionHasNoErrors();

        // 4. Request dengan formula null (harus ditolak dengan validasi 'formula', BUKAN ErrorException array offset on null)
        $resNullFormula = $this->actingAs($this->admin)->put("/payroll-configurations/{$config->id}", [
            'name' => 'Lembur Null Formula Test',
            'component_id' => $config->component_id,
            'day_type' => 'working_day',
            'formula' => null,
        ]);
        $resNullFormula->assertSessionHasErrors(['formula']);

        // 5. Request dengan invalid formula AST (operator tidak valid)
        $resInvalidAst = $this->actingAs($this->admin)->put("/payroll-configurations/{$config->id}", [
            'name' => 'Lembur Invalid AST Test',
            'component_id' => $config->component_id,
            'day_type' => 'working_day',
            'formula' => ['type' => 'invalid_operator', 'operands' => [1]],
        ]);
        $resInvalidAst->assertSessionHasErrors(['formula']);
    }

    /**
     * Test StorePayrollCalculationConfigRequest:
     * - Request valid store
     * - Request dengan formula null (validasi bersih tanpa exception)
     */
    public function test_store_payroll_calculation_config_request_scenarios(): void
    {
        $formula = $this->overtimeService->getDefaultHolidayFormula();

        // 1. Request valid store
        $resStore = $this->actingAs($this->admin)->post('/payroll-configurations', [
            'name' => 'Lembur Custom Baru',
            'code' => 'OVERTIME_CUSTOM_NEW',
            'component_id' => $this->overtimeComponent->id,
            'day_type' => 'holiday',
            'is_active' => true,
            'formula' => $formula,
            'description' => 'Konfigurasi custom baru',
        ]);
        $resStore->assertSessionHasNoErrors();
        $resStore->assertRedirect(route('payroll-configurations.index'));

        // 2. Request store dengan formula null
        $resNullStore = $this->actingAs($this->admin)->post('/payroll-configurations', [
            'name' => 'Lembur Fail',
            'component_id' => $this->overtimeComponent->id,
            'day_type' => 'holiday',
            'formula' => null,
        ]);
        $resNullStore->assertSessionHasErrors(['formula']);
    }

    /**
     * Test Formula Preview for Working Day:
     * - Threshold >= 3 jam (>= 180 menit)
     * - Tidak mengandung 3.1
     * - Contoh 2:59, 3:00, 3:01, 4:00
     */
    public function test_formula_preview_working_day_reflects_actual_business_rule(): void
    {
        $formula = $this->overtimeService->getDefaultWorkingDayFormula();
        $preview = $this->formulaEvaluator->toHumanReadable($formula, 'working_day');

        $this->assertStringContainsString('LEMBUR HARI BIASA', $preview);
        $this->assertStringContainsString('3 jam', $preview);
        $this->assertStringContainsString('180', $preview);
        $this->assertStringContainsString('2:59  → Tidak dipotong', $preview);
        $this->assertStringContainsString('3:00  → Dipotong', $preview);
        $this->assertStringContainsString('3:01  → Dipotong', $preview);
        $this->assertStringContainsString('4:00  → Dipotong', $preview);

        // Pastikan tidak menggunakan threshold usang 3.1
        $this->assertStringNotContainsString('3.1', $preview);
        $this->assertStringNotContainsString('4.1', $preview);
    }

    /**
     * Test Formula Preview for Holiday:
     * - Threshold >= 4 jam (>= 240 menit)
     * - Tidak mengandung 4.1
     * - Contoh 3:59, 4:00, 4:01, 5:00
     */
    public function test_formula_preview_holiday_reflects_actual_business_rule(): void
    {
        $formula = $this->overtimeService->getDefaultHolidayFormula();
        $preview = $this->formulaEvaluator->toHumanReadable($formula, 'holiday');

        $this->assertStringContainsString('LEMBUR HARI LIBUR', $preview);
        $this->assertStringContainsString('4 jam', $preview);
        $this->assertStringContainsString('240', $preview);
        $this->assertStringContainsString('3:59  → Tidak dipotong', $preview);
        $this->assertStringContainsString('4:00  → Dipotong', $preview);
        $this->assertStringContainsString('4:01  → Dipotong', $preview);
        $this->assertStringContainsString('5:00  → Dipotong', $preview);

        // Pastikan tidak menggunakan threshold usang 4.1
        $this->assertStringNotContainsString('3.1', $preview);
        $this->assertStringNotContainsString('4.1', $preview);
    }

    /**
     * Test Payroll Configurations Index and Edit responses return preview matching calculation engine:
     */
    public function test_payroll_configurations_endpoints_return_consistent_previews(): void
    {
        $workingConfig = PayrollCalculationConfig::where('code', 'OVERTIME_WORKING_DAY')->first();
        $holidayConfig = PayrollCalculationConfig::where('code', 'OVERTIME_HOLIDAY')->first();

        // 1. Index page
        $resIndex = $this->actingAs($this->personalia)->get('/payroll-configurations');
        $resIndex->assertOk();
        $configsData = $resIndex->inertiaPage()['props']['configs']['data'];

        $workingIndexItem = collect($configsData)->firstWhere('code', 'OVERTIME_WORKING_DAY');
        $this->assertNotNull($workingIndexItem);
        $this->assertStringContainsString('3 jam', $workingIndexItem['formula_preview']);
        $this->assertStringNotContainsString('3.1', $workingIndexItem['formula_preview']);
        $this->assertEquals(3.0, $workingIndexItem['preview_details']['threshold_hours']);
        $this->assertEquals(180, $workingIndexItem['preview_details']['threshold_minutes']);

        $holidayIndexItem = collect($configsData)->firstWhere('code', 'OVERTIME_HOLIDAY');
        $this->assertNotNull($holidayIndexItem);
        $this->assertStringContainsString('4 jam', $holidayIndexItem['formula_preview']);
        $this->assertStringNotContainsString('4.1', $holidayIndexItem['formula_preview']);
        $this->assertEquals(4.0, $holidayIndexItem['preview_details']['threshold_hours']);
        $this->assertEquals(240, $holidayIndexItem['preview_details']['threshold_minutes']);

        // 2. Edit page
        $resEdit = $this->actingAs($this->admin)->get("/payroll-configurations/{$workingConfig->id}/edit");
        $resEdit->assertOk();
        $versions = $resEdit->inertiaPage()['props']['versions'];
        $this->assertNotEmpty($versions);
        $this->assertStringContainsString('3 jam', $versions[0]['preview']);
        $this->assertStringNotContainsString('3.1', $versions[0]['preview']);
    }
}
