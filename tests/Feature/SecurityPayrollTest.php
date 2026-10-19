<?php

namespace Tests\Feature;

use App\Enums\PayrollPeriodStatus;
use App\Enums\PayslipStatus;
use App\Models\Employee;
use App\Models\PayComponent;
use App\Models\PayrollPeriod;
use App\Models\Payslip;
use App\Models\Position;
use App\Models\SecurityAttendance;
use App\Models\SecurityOvertimeSummary;
use App\Models\SecurityPayrollConfig;
use App\Models\User;
use App\Services\PayslipService;
use App\Services\SecurityPayrollCalculationService;
use Database\Seeders\OrganizationMasterSeeder;
use Database\Seeders\PayComponentSeeder;
use Database\Seeders\PayrollCalculationConfigSeeder;
use Database\Seeders\RbacSeeder;
use Database\Seeders\SecurityPayrollConfigSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityPayrollTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $personalia;
    protected User $auditor;
    protected PayrollPeriod $period;
    protected Position $securityPosition;
    protected Position $regularPosition;
    protected SecurityPayrollCalculationService $securityService;
    protected PayslipService $payslipService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);
        $this->seed(OrganizationMasterSeeder::class);
        $this->seed(PayComponentSeeder::class);
        $this->seed(PayrollCalculationConfigSeeder::class);
        $this->seed(SecurityPayrollConfigSeeder::class);

        $this->admin = User::where('email', 'admin@example.com')->first();
        $this->personalia = User::where('email', 'personalia@example.com')->first();
        $this->auditor = User::where('email', 'auditor@example.com')->first();

        $this->securityService = app(SecurityPayrollCalculationService::class);
        $this->payslipService = app(PayslipService::class);

        // Security position
        $this->securityPosition = Position::firstOrCreate(
            ['code' => 'SEC'],
            [
                'name' => 'Security',
                'payroll_type' => 'security',
                'is_active' => true,
            ]
        );
        $this->securityPosition->update(['payroll_type' => 'security']);

        // Regular position
        $this->regularPosition = Position::firstOrCreate(
            ['code' => 'STF'],
            [
                'name' => 'Staff Pelaksana',
                'payroll_type' => 'regular',
                'is_active' => true,
            ]
        );

        // Example payroll period: 22 August to 21 September 2026 (31 days)
        $this->period = PayrollPeriod::create([
            'period_code' => '2026-08-SEC',
            'start_date' => '2026-08-22',
            'end_date' => '2026-09-21',
            'working_days' => 25,
            'status' => PayrollPeriodStatus::DRAFT->value,
            'created_by' => $this->personalia->id,
        ]);
    }

    /**
     * Helper to create a Security employee.
     */
    protected function createSecurityEmployee(string $number, string $name): Employee
    {
        return Employee::create([
            'employee_number' => $number,
            'full_name' => $name,
            'position_id' => $this->securityPosition->id,
            'office_id' => 1,
            'employment_status' => 'active',
            'gaji_pokok' => 3500000,
        ]);
    }

    /**
     * Requirement 1 & 2: Fixture 1 Security generates Rp945.000 and 6 identical Security generate Rp5.670.000.
     */
    public function test_fixture_one_and_six_security_generate_accurate_overtime_cost(): void
    {
        $config = SecurityPayrollConfig::getActiveConfig();

        // 1 Security Calculation
        $resultOne = $this->securityService->calculateOvertime(
            grossOvertimeHours: 40.0,
            breakDeductionHours: 5.0,
            config: $config
        );

        $this->assertEquals(35.0, $resultOne['net_overtime_hours']);
        $this->assertEquals(5.0, $resultOne['first_hours']);
        $this->assertEquals(105000.0, $resultOne['first_hours_cost']); // 5 * 14000 * 1.5
        $this->assertEquals(30.0, $resultOne['subsequent_hours']);
        $this->assertEquals(840000.0, $resultOne['subsequent_hours_cost']); // 30 * 14000 * 2.0
        $this->assertEquals(945000.0, $resultOne['total_overtime_cost']);

        // 6 identical Security
        $totalSix = 0.0;
        for ($i = 1; $i <= 6; $i++) {
            $emp = $this->createSecurityEmployee("SEC-00{$i}", "Security Member {$i}");
            $summary = $this->securityService->calculateForEmployee(
                $emp,
                $this->period,
                $config,
                ['gross_overtime_hours' => 40.0, 'break_deduction_hours' => 5.0]
            );
            $totalSix += (float) $summary->total_overtime_cost;
        }

        $this->assertEquals(5670000.0, $totalSix);
    }

    /**
     * Requirement 3: Jam lembur nol menghasilkan biaya nol.
     */
    public function test_zero_overtime_hours_results_in_zero_cost(): void
    {
        $result = $this->securityService->calculateOvertime(
            grossOvertimeHours: 0.0,
            breakDeductionHours: 0.0
        );

        $this->assertEquals(0.0, $result['net_overtime_hours']);
        $this->assertEquals(0.0, $result['first_hours']);
        $this->assertEquals(0.0, $result['first_hours_cost']);
        $this->assertEquals(0.0, $result['subsequent_hours']);
        $this->assertEquals(0.0, $result['subsequent_hours_cost']);
        $this->assertEquals(0.0, $result['total_overtime_cost']);
    }

    /**
     * Requirement 4: Potongan istirahat tidak boleh membuat jam lembur negatif.
     */
    public function test_break_deduction_never_causes_negative_overtime(): void
    {
        // Deduction greater than gross overtime
        $result = $this->securityService->calculateOvertime(
            grossOvertimeHours: 3.0,
            breakDeductionHours: 10.0
        );

        $this->assertEquals(0.0, $result['net_overtime_hours']);
        $this->assertEquals(0.0, $result['first_hours']);
        $this->assertEquals(0.0, $result['subsequent_hours']);
        $this->assertEquals(0.0, $result['total_overtime_cost']);
    }

    /**
     * Requirement 5: OFF tidak dihitung sebagai ketidakhadiran (Absen).
     */
    public function test_off_days_are_explicitly_distinguished_and_not_counted_as_absent(): void
    {
        $emp = $this->createSecurityEmployee('SEC-OFF-01', 'Security Petugas OFF');

        // Record 4 OFF days and 2 Absent days
        SecurityAttendance::create([
            'payroll_period_id' => $this->period->id,
            'employee_id' => $emp->id,
            'date' => '2026-08-22',
            'status' => 'off',
            'work_hours' => 0,
            'overtime_hours' => 0,
        ]);
        SecurityAttendance::create([
            'payroll_period_id' => $this->period->id,
            'employee_id' => $emp->id,
            'date' => '2026-08-23',
            'status' => 'off',
            'work_hours' => 0,
            'overtime_hours' => 0,
        ]);
        SecurityAttendance::create([
            'payroll_period_id' => $this->period->id,
            'employee_id' => $emp->id,
            'date' => '2026-08-24',
            'status' => 'absent',
            'work_hours' => 0,
            'overtime_hours' => 0,
        ]);

        $summary = $this->securityService->calculateForEmployee($emp, $this->period);

        $this->assertEquals(2, $summary->total_off_days);
        $this->assertEquals(1, $summary->total_absent_days);
        $this->assertNotEquals($summary->total_off_days, $summary->total_absent_days);
    }

    /**
     * Requirement 6: Periode dinamis mencakup seluruh tanggal (termasuk start_date dan end_date).
     */
    public function test_dynamic_period_covers_all_dates_inclusive(): void
    {
        // 22 August to 21 September = exactly 31 days
        $dates = $this->securityService->getPeriodDates($this->period);

        $this->assertCount(31, $dates);
        $this->assertEquals('2026-08-22', $dates[0]->format('Y-m-d'));
        $this->assertEquals('2026-09-21', end($dates)->format('Y-m-d'));
    }

    /**
     * Requirement 7: Batas jam pertama dapat diubah melalui konfigurasi.
     */
    public function test_first_hours_limit_is_configurable_via_config(): void
    {
        $config = SecurityPayrollConfig::getActiveConfig();
        $config->update([
            'first_hours_limit' => 8.0,
            'hourly_rate' => 14000.0,
            'first_hours_multiplier' => 1.5,
            'subsequent_hours_multiplier' => 2.0,
        ]);

        // Net overtime = 20h
        $calc = $this->securityService->calculateOvertime(
            grossOvertimeHours: 20.0,
            breakDeductionHours: 0.0,
            config: $config
        );

        // With limit = 8h, first hours = 8h, subsequent = 12h
        $this->assertEquals(8.0, $calc['first_hours']);
        $this->assertEquals(12.0, $calc['subsequent_hours']);
        $this->assertEquals(168000.0, $calc['first_hours_cost']); // 8 * 14000 * 1.5
        $this->assertEquals(336000.0, $calc['subsequent_hours_cost']); // 12 * 14000 * 2.0
        $this->assertEquals(504000.0, $calc['total_overtime_cost']);
    }

    /**
     * Requirement 8: Perhitungan tidak terduplikasi (idempotent updates).
     */
    public function test_calculation_is_idempotent_and_does_not_duplicate(): void
    {
        $emp = $this->createSecurityEmployee('SEC-IDEM', 'Security Idempotent');

        // Call calculate multiple times
        $firstSummary = $this->securityService->calculateForEmployee($emp, $this->period);
        $secondSummary = $this->securityService->calculateForEmployee($emp, $this->period);

        $this->assertEquals($firstSummary->id, $secondSummary->id);
        $this->assertEquals(1, SecurityOvertimeSummary::where('payroll_period_id', $this->period->id)->where('employee_id', $emp->id)->count());
    }

    /**
     * Requirement 9: Pengguna tanpa izin tidak dapat mengubah konfigurasi rumus.
     */
    public function test_unauthorized_user_cannot_update_security_config(): void
    {
        $response = $this->actingAs($this->auditor)->post(route('payroll-security.config.update'), [
            'hourly_rate' => 20000,
            'first_hours_limit' => 5,
            'first_hours_multiplier' => 1.5,
            'subsequent_hours_multiplier' => 2.0,
            'round_rule' => 'round',
        ]);

        $response->assertStatus(403);
    }

    /**
     * Requirement 10: Perubahan konfigurasi di masa depan tidak mengubah payroll final.
     */
    public function test_future_config_changes_do_not_alter_finalized_payslip(): void
    {
        $emp = $this->createSecurityEmployee('SEC-FINAL-01', 'Security Finalized');

        // 1. Create and sync draft payslip
        $this->payslipService->createPayslip($this->period, [
            'employee_id' => $emp->id,
            'working_days' => 25,
            'items' => $emp->getDefaultSalaryItems(),
        ]);

        $this->securityService->calculateForEmployee($emp, $this->period, null, [
            'gross_overtime_hours' => 40.0,
            'break_deduction_hours' => 5.0,
        ]);
        $payslip = $this->securityService->syncSecurityPayslip($this->period, $emp);

        $initialOvertimeAmount = $payslip->items()->where('item_code_snapshot', 'OVERTIME')->first()->amount;
        $this->assertEquals('945000.00', $initialOvertimeAmount);

        // Finalize payslip
        $payslip->update(['status' => PayslipStatus::FINALIZED->value]);

        // 2. Change configuration in the future
        $config = SecurityPayrollConfig::getActiveConfig();
        $this->actingAs($this->personalia)->post(route('payroll-security.config.update'), [
            'hourly_rate' => 50000, // Drastically increased
            'first_hours_limit' => 10,
            'first_hours_multiplier' => 3.0,
            'subsequent_hours_multiplier' => 4.0,
            'round_rule' => 'round',
        ]);

        // 3. Attempt re-sync
        $resyncedSlip = $this->securityService->syncSecurityPayslip($this->period, $emp);

        $resyncedOvertimeAmount = $resyncedSlip->items()->where('item_code_snapshot', 'OVERTIME')->first()->amount;
        $this->assertEquals('945000.00', $resyncedOvertimeAmount, 'Finalized slip overtime amount must remain frozen and immutable.');
    }

    /**
     * Requirement 11: Payroll reguler menghasilkan nilai yang sama seperti sebelum fitur Security ditambahkan.
     */
    public function test_regular_payroll_remains_unaltered_and_intact(): void
    {
        $regularEmployee = Employee::create([
            'employee_number' => 'REG-001',
            'full_name' => 'Karyawan Reguler Intact',
            'position_id' => $this->regularPosition->id,
            'office_id' => 1,
            'employment_status' => 'active',
            'gaji_pokok' => 5000000,
            'tunjangan_jabatan' => 500000,
        ]);

        $this->assertFalse($regularEmployee->isSecurity());

        $slip = $this->payslipService->createPayslip($this->period, [
            'employee_id' => $regularEmployee->id,
            'working_days' => 25,
            'items' => $regularEmployee->getDefaultSalaryItems(),
        ]);

        $this->assertEquals('5500000.00', $slip->total_earnings);
        $this->assertEquals('5500000.00', $slip->net_salary);
        $this->assertNull($slip->items()->where('item_code_snapshot', 'OVERTIME')->first());
    }

    /**
     * Requirement 12: Total footer sama dengan jumlah seluruh detail Security.
     */
    public function test_footer_total_equals_sum_of_all_security_overtime_details(): void
    {
        $sec1 = $this->createSecurityEmployee('SEC-F1', 'Security Footer 1');
        $sec2 = $this->createSecurityEmployee('SEC-F2', 'Security Footer 2');
        $sec3 = $this->createSecurityEmployee('SEC-F3', 'Security Footer 3');

        $sum1 = $this->securityService->calculateForEmployee($sec1, $this->period, null, [
            'gross_overtime_hours' => 20.0,
            'break_deduction_hours' => 2.0,
        ]);

        $sum2 = $this->securityService->calculateForEmployee($sec2, $this->period, null, [
            'gross_overtime_hours' => 40.0,
            'break_deduction_hours' => 5.0,
        ]);

        $sum3 = $this->securityService->calculateForEmployee($sec3, $this->period, null, [
            'gross_overtime_hours' => 0.0,
            'break_deduction_hours' => 0.0,
        ]);

        $expectedSum = (float) $sum1->total_overtime_cost + (float) $sum2->total_overtime_cost + (float) $sum3->total_overtime_cost;

        $response = $this->actingAs($this->personalia)->get(route('payroll-security.index', [
            'period_id' => $this->period->id,
        ]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('SecurityPayroll/Index')
            ->where('totalOvertimeAllSecurity', (int) $expectedSum)
        );
        $this->assertEquals($expectedSum, 1414000.0);
    }

    /**
     * Test updating daily attendance via HTTP endpoint and syncing with summary.
     */
    public function test_can_update_daily_attendance_via_http(): void
    {
        $sec = $this->createSecurityEmployee('SEC-ATT-01', 'Security Absensi Harian');

        $response = $this->actingAs($this->personalia)->post(route('payroll-security.attendance.update'), [
            'payroll_period_id' => $this->period->id,
            'employee_id' => $sec->id,
            'date' => '2026-08-25',
            'status' => 'present',
            'work_hours' => 8,
            'overtime_hours' => 8,
            'note' => 'Shift lembur pengganti',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('security_attendances', [
            'payroll_period_id' => $this->period->id,
            'employee_id' => $sec->id,
            'date' => '2026-08-25',
            'work_hours' => 8.0,
            'overtime_hours' => 8.0,
        ]);

        $summary = SecurityOvertimeSummary::where('payroll_period_id', $this->period->id)
            ->where('employee_id', $sec->id)
            ->first();

        $this->assertNotNull($summary);
        $this->assertEquals(8.0, (float) $summary->gross_overtime_hours);
    }

    /**
     * Test Excel export stream for Security Payroll.
     */
    public function test_can_export_security_payroll_excel(): void
    {
        $sec = $this->createSecurityEmployee('SEC-EXP-01', 'Security Export Excel');
        $this->securityService->calculateForEmployee($sec, $this->period);

        $response = $this->actingAs($this->personalia)->get(
            route('payroll-security.periods.export-excel', $this->period->id)
        );

        $response->assertOk();
        $this->assertTrue(
            str_contains($response->headers->get('content-disposition'), 'Rekap_Lembur_Security_')
        );
    }
}

