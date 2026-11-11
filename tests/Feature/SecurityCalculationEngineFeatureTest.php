<?php

namespace Tests\Feature;

use App\Enums\PayrollPeriodStatus;
use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Models\Position;
use App\Models\SecurityOvertimeSummary;
use App\Models\SecurityPayrollConfig;
use App\Models\User;
use App\Services\SecurityPayrollCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityCalculationEngineFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $personalia;

    protected User $auditor;

    protected PayrollPeriod $period;

    protected Position $securityPosition;

    protected SecurityPayrollCalculationService $securityService;

    protected SecurityPayrollConfig $config;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        $this->admin = User::where('email', 'admin@example.com')->first();
        $this->personalia = User::where('email', 'personalia@example.com')->first();
        $this->auditor = User::where('email', 'auditor@example.com')->first();

        $this->securityPosition = Position::firstOrCreate(
            ['code' => 'SEC'],
            ['name' => 'Security', 'payroll_type' => 'security', 'is_active' => true]
        );
        $this->securityPosition->update(['payroll_type' => 'security']);

        $this->period = PayrollPeriod::create([
            'period_code' => '2026-08-SEC',
            'start_date' => '2026-08-22',
            'end_date' => '2026-09-21',
            'working_days' => 25,
            'status' => PayrollPeriodStatus::DRAFT->value,
            'created_by' => $this->personalia->id,
        ]);

        $this->securityService = app(SecurityPayrollCalculationService::class);
        $this->config = SecurityPayrollConfig::getActiveConfig();
    }

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
     * Test Employee 1 (Wawan - Excel Fixture):
     * Normal shift 26 days, 5 days OFF, 0 absence => target 208h, attendance 208h (shortage 0h).
     * Regular overtime 5 days, 40 hours. Break deduction 5h (40/8). Net 35h.
     * First hours 5h @ 1.5x (Rp 105.000), subsequent hours 30h @ 2.0x (Rp 840.000).
     * Total Overtime = Rp 945.000.
     */
    public function test_employee_scenario_one_wawan_verified_excel_calculation(): void
    {
        $wawan = $this->createSecurityEmployee('SEC-01', 'Wawan Security');

        $this->config->update([
            'break_deduction_rule' => 'per_8_hours',
        ]);

        $response = $this->actingAs($this->personalia)->post(route('payroll-security.summary.update'), [
            'payroll_period_id' => $this->period->id,
            'employee_id' => $wawan->id,
            'present_days' => 26,
            'off_days' => 5,
            'sick_days' => 0,
            'permission_days' => 0,
            'leave_days' => 0,
            'absent_days' => 0,
            'regular_overtime_days' => 5,
            'regular_overtime_hours' => 40.0,
            'off_holiday_overtime_days' => 0,
            'off_holiday_overtime_hours' => 0.0,
        ]);

        $response->assertRedirect();

        $summary = SecurityOvertimeSummary::where('payroll_period_id', $this->period->id)
            ->where('employee_id', $wawan->id)
            ->first();

        $this->assertNotNull($summary);
        $this->assertEquals(208.0, (float) $summary->attendance_hours); // 26 * 8
        $this->assertEquals(208.0, (float) $summary->target_hours);     // (31 - 5) * 8
        $this->assertEquals(0.0, (float) $summary->current_shortage_hours);
        $this->assertEquals(0.0, (float) $summary->total_shortage_hours);
        $this->assertEquals(0.0, (float) $summary->shortage_offset_hours);
        $this->assertEquals(5.0, (float) $summary->regular_break_deduction_hours);
        $this->assertEquals(35.0, (float) $summary->regular_net_overtime_hours);
        $this->assertEquals(5.0, (float) $summary->regular_first_hours);
        $this->assertEquals(105000.0, (float) $summary->regular_first_hours_cost);
        $this->assertEquals(30.0, (float) $summary->regular_subsequent_hours);
        $this->assertEquals(840000.0, (float) $summary->regular_subsequent_hours_cost);
        $this->assertEquals(945000.0, (float) $summary->regular_overtime_cost);
        $this->assertEquals(945000.0, (float) $summary->total_overtime_cost);
    }

    /**
     * Test Employee 2 (Budi - Carry-forward and Shortage Compensation):
     * Has 8 hours shortage from previous period.
     * In current period: 16 hours overtime.
     * Shortage offsets 8 hours of overtime, leaving 8 hours adjusted overtime.
     * Break deduction 1 hour (8/8). Net 7 hours.
     * First hours 2h (from 2 days), subsequent 5h.
     * Cost = (2 * 14000 * 1.5) + (5 * 14000 * 2.0) = 42.000 + 140.000 = Rp 182.000.
     * Remaining shortage for next month becomes 0.
     */
    public function test_employee_scenario_two_budi_shortage_compensation(): void
    {
        $budi = $this->createSecurityEmployee('SEC-02', 'Budi Security');

        $this->config->update([
            'break_deduction_rule' => 'per_8_hours',
        ]);

        $response = $this->actingAs($this->personalia)->post(route('payroll-security.summary.update'), [
            'payroll_period_id' => $this->period->id,
            'employee_id' => $budi->id,
            'present_days' => 26,
            'off_days' => 5,
            'sick_days' => 0,
            'permission_days' => 0,
            'leave_days' => 0,
            'absent_days' => 0,
            'previous_shortage_hours' => 8.0, // Manual carry-forward from past period
            'regular_overtime_days' => 2,
            'regular_overtime_hours' => 16.0,
        ]);

        $response->assertRedirect();

        $summary = SecurityOvertimeSummary::where('payroll_period_id', $this->period->id)
            ->where('employee_id', $budi->id)
            ->first();

        $this->assertNotNull($summary);
        $this->assertEquals(8.0, (float) $summary->previous_shortage_hours);
        $this->assertEquals(8.0, (float) $summary->total_shortage_hours);
        $this->assertEquals(8.0, (float) $summary->shortage_offset_hours); // 8h shortage offset
        $this->assertEquals(0.0, (float) $summary->remaining_shortage_hours); // fully settled
        $this->assertEquals(1.0, (float) $summary->regular_break_deduction_hours); // (16 - 8) / 8 = 1h
        $this->assertEquals(7.0, (float) $summary->regular_net_overtime_hours);   // 8 - 1 = 7h
        $this->assertEquals(2.0, (float) $summary->regular_first_hours);          // 2 days
        $this->assertEquals(42000.0, (float) $summary->regular_first_hours_cost); // 2 * 14000 * 1.5
        $this->assertEquals(5.0, (float) $summary->regular_subsequent_hours);     // 7 - 2 = 5h
        $this->assertEquals(140000.0, (float) $summary->regular_subsequent_hours_cost); // 5 * 14000 * 2.0
        $this->assertEquals(182000.0, (float) $summary->regular_overtime_cost);
    }

    /**
     * Test Employee 3 (Siti - OFF / Holiday Overtime with Unconfirmed Tariff Protection):
     * Siti works 1 shift (8h) on an OFF / Holiday.
     * Company tariff policy for holiday overtime is NOT confirmed.
     * System produces 0.0 nominal cost for holiday overtime with a clear status indicator,
     * protecting company payroll from arbitrary disbursements.
     */
    public function test_employee_scenario_three_siti_off_holiday_unconfirmed_rate(): void
    {
        $siti = $this->createSecurityEmployee('SEC-03', 'Siti Security');

        $response = $this->actingAs($this->personalia)->post(route('payroll-security.summary.update'), [
            'payroll_period_id' => $this->period->id,
            'employee_id' => $siti->id,
            'present_days' => 26,
            'off_days' => 5,
            'sick_days' => 0,
            'permission_days' => 0,
            'leave_days' => 0,
            'absent_days' => 0,
            'regular_overtime_days' => 0,
            'regular_overtime_hours' => 0.0,
            'off_holiday_overtime_days' => 1,
            'off_holiday_overtime_hours' => 8.0,
            'off_holiday_rate_confirmed' => false,
        ]);

        $response->assertRedirect();

        $summary = SecurityOvertimeSummary::where('payroll_period_id', $this->period->id)
            ->where('employee_id', $siti->id)
            ->first();

        $this->assertNotNull($summary);
        $this->assertEquals(1, (int) $summary->off_holiday_overtime_days);
        $this->assertEquals(8.0, (float) $summary->off_holiday_overtime_hours);
        $this->assertEquals(1.0, (float) $summary->off_holiday_break_deduction_hours);
        $this->assertEquals(7.0, (float) $summary->off_holiday_net_overtime_hours);
        $this->assertFalse((bool) $summary->off_holiday_rate_confirmed);
        $this->assertEquals(0.0, (float) $summary->off_holiday_overtime_cost);
        $this->assertEquals(0.0, (float) $summary->total_overtime_cost);

        // Check snapshot indicator
        $snapshot = $summary->calculation_snapshot;
        $this->assertFalse($snapshot['off_holiday_overtime']['rate_confirmed']);
        $this->assertStringContainsString('Menunggu konfirmasi', $snapshot['off_holiday_overtime']['rate_status_message']);
    }

    /**
     * Test Preview Endpoint POST /payroll-security/preview:
     * Returns full computation without mutating database.
     */
    public function test_preview_endpoint_returns_json_metrics_without_database_mutations(): void
    {
        $sec = $this->createSecurityEmployee('SEC-PREV-01', 'Security Preview Only');

        $response = $this->actingAs($this->personalia)->postJson(route('payroll-security.preview'), [
            'payroll_period_id' => $this->period->id,
            'employee_id' => $sec->id,
            'present_days' => 24,
            'off_days' => 5,
            'sick_days' => 1,
            'permission_days' => 1,
            'leave_days' => 0,
            'absent_days' => 0,
            'regular_overtime_days' => 3,
            'regular_overtime_hours' => 24.0,
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'data' => [
                'employee_id' => $sec->id,
                'metrics' => [
                    'present_days' => 24,
                    'attendance_hours' => 192.0,
                    'target_hours' => 192.0, // (31 - 5 - 2) * 8 = 24 * 8 = 192
                    'current_shortage_hours' => 0.0,
                ],
            ],
        ]);

        // Verify NO database row was created in preview
        $this->assertDatabaseMissing('security_overtime_summaries', [
            'payroll_period_id' => $this->period->id,
            'employee_id' => $sec->id,
        ]);
    }

    /**
     * Test Automatic Carry-Forward from Preceding Finalized Period:
     * Period 1 (August) finalized with remaining shortage of 12 hours.
     * Period 2 (September) automatically carries forward 12 hours.
     */
    public function test_automatic_carry_forward_from_preceding_finalized_period(): void
    {
        $sec = $this->createSecurityEmployee('SEC-CARRY-01', 'Security Auto Carry');

        // Period 1 (Preceding, finalized)
        $period1 = PayrollPeriod::create([
            'period_code' => '2026-07-SEC',
            'start_date' => '2026-07-22',
            'end_date' => '2026-08-21',
            'working_days' => 25,
            'status' => PayrollPeriodStatus::FINALIZED->value,
            'created_by' => $this->personalia->id,
        ]);

        // In Period 1, sec worked 22 days (target 208h, attendance 176h => 32h shortage, no overtime)
        $this->securityService->calculateForEmployee($sec, $period1, null, [
            'present_days' => 22,
            'off_days' => 5,
            'sick_days' => 0,
            'regular_overtime_days' => 0,
            'regular_overtime_hours' => 0.0,
        ]);

        $sum1 = SecurityOvertimeSummary::where('payroll_period_id', $period1->id)
            ->where('employee_id', $sec->id)
            ->first();
        $this->assertEquals(32.0, (float) $sum1->remaining_shortage_hours);

        // Period 2 (Current, draft): calculate sec with no manual previous shortage passed
        $sum2 = $this->securityService->calculateForEmployee($sec, $this->period, null, [
            'present_days' => 26,
            'off_days' => 5,
            'regular_overtime_days' => 0,
            'regular_overtime_hours' => 0.0,
        ]);

        // Must automatically carry forward 32 hours from Period 1
        $this->assertEquals(32.0, (float) $sum2->previous_shortage_hours);
        $this->assertEquals($period1->id, $sum2->previous_payroll_period_id);
        $this->assertEquals(32.0, (float) $sum2->total_shortage_hours);
    }
}

