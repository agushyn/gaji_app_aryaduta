<?php

namespace Tests\Unit;

use App\Models\SecurityPayrollConfig;
use App\Services\Security\SecurityAttendanceCalculator;
use App\Services\Security\SecurityOvertimeCalculator;
use Tests\TestCase;

class SecurityCalculationEngineTest extends TestCase
{
    protected SecurityAttendanceCalculator $attendanceCalc;

    protected SecurityOvertimeCalculator $overtimeCalc;

    protected SecurityPayrollConfig $config;

    protected function setUp(): void
    {
        parent::setUp();
        $this->attendanceCalc = new SecurityAttendanceCalculator();
        $this->overtimeCalc = new SecurityOvertimeCalculator();

        $this->config = new SecurityPayrollConfig([
            'hourly_rate' => '14000.00',
            'first_hours_limit' => '5.00',
            'first_hours_multiplier' => '1.50',
            'subsequent_hours_multiplier' => '2.00',
            'holiday_multiplier' => '2.00',
            'break_deduction_rule' => 'per_8_hours',
            'break_deduction_default' => '0.00',
            'max_break_deduction' => '20.00',
            'round_rule' => 'round',
        ]);
    }

    /**
     * Requirement: attendance_hours = present_days * 8.
     */
    public function test_attendance_hours_formula_is_present_days_multiplied_by_eight(): void
    {
        $this->assertEquals(0.0, $this->attendanceCalc->calculateAttendanceHours(0));
        $this->assertEquals(160.0, $this->attendanceCalc->calculateAttendanceHours(20));
        $this->assertEquals(208.0, $this->attendanceCalc->calculateAttendanceHours(26));
        $this->assertEquals(248.0, $this->attendanceCalc->calculateAttendanceHours(31));
    }

    /**
     * Requirement: Target hours = (calendar_days - off_days - total_absence_days) * 8.
     * Excel cell AP = AK - AM - AO.
     */
    public function test_target_hours_formula_matches_excel_cell_ap(): void
    {
        // 31 calendar days, 5 off days, 0 absences => (31 - 5 - 0) * 8 = 208 hours (Wawan fixture)
        $target = $this->attendanceCalc->calculateTargetHours(31, 5, 0);
        $this->assertEquals(208.0, $target);

        // 30 calendar days, 4 off days, 2 absence days (1 sick, 1 absent) => (30 - 4 - 2) * 8 = 192 hours
        $target2 = $this->attendanceCalc->calculateTargetHours(30, 4, 2);
        $this->assertEquals(192.0, $target2);

        // All days off or absent => target is 0, never negative
        $target3 = $this->attendanceCalc->calculateTargetHours(30, 20, 15);
        $this->assertEquals(0.0, $target3);
    }

    /**
     * Requirement: Current shortage = max(0, target_hours - attendance_hours).
     * Excel cell AR = IF(AQ < AP, AP - AQ, 0).
     */
    public function test_current_shortage_calculation_matches_excel_cell_ar(): void
    {
        // Attendance 208 >= Target 208 => shortage 0
        $shortage1 = $this->attendanceCalc->calculateCurrentShortage(208.0, 208.0);
        $this->assertEquals(0.0, $shortage1);

        // Attendance 216 > Target 208 => shortage 0
        $shortage2 = $this->attendanceCalc->calculateCurrentShortage(208.0, 216.0);
        $this->assertEquals(0.0, $shortage2);

        // Attendance 192 < Target 208 => shortage 16
        $shortage3 = $this->attendanceCalc->calculateCurrentShortage(208.0, 192.0);
        $this->assertEquals(16.0, $shortage3);
    }

    /**
     * Requirement: Total shortage = current_shortage + previous_shortage.
     * Excel cell AT = AR + AS.
     */
    public function test_total_shortage_calculation_matches_excel_cell_at(): void
    {
        $total = $this->attendanceCalc->calculateTotalShortage(8.0, 12.0);
        $this->assertEquals(20.0, $total);

        $totalZero = $this->attendanceCalc->calculateTotalShortage(0.0, 0.0);
        $this->assertEquals(0.0, $totalZero);
    }

    /**
     * Requirement: Shortage offsets gross overtime hours before computing overtime pay.
     * Excel cell AU = IF(AQ > AP, AQ - AT - AP, 0).
     */
    public function test_shortage_offset_reduces_overtime_and_calculates_remaining_shortage(): void
    {
        // Scenario A: Overtime 40h, Total Shortage 0h => Offset 0h, Adjusted Overtime 40h, Remaining Shortage 0h
        $resA = $this->attendanceCalc->applyShortageOffset(0.0, 40.0);
        $this->assertEquals(0.0, $resA['shortage_offset_hours']);
        $this->assertEquals(40.0, $resA['adjusted_overtime_hours']);
        $this->assertEquals(0.0, $resA['remaining_shortage_hours']);

        // Scenario B: Overtime 16h, Total Shortage 8h => Offset 8h, Adjusted Overtime 8h, Remaining Shortage 0h
        $resB = $this->attendanceCalc->applyShortageOffset(8.0, 16.0);
        $this->assertEquals(8.0, $resB['shortage_offset_hours']);
        $this->assertEquals(8.0, $resB['adjusted_overtime_hours']);
        $this->assertEquals(0.0, $resB['remaining_shortage_hours']);

        // Scenario C: Overtime 6h, Total Shortage 10h => Offset 6h, Adjusted Overtime 0h, Remaining Shortage 4h
        $resC = $this->attendanceCalc->applyShortageOffset(10.0, 6.0);
        $this->assertEquals(6.0, $resC['shortage_offset_hours']);
        $this->assertEquals(0.0, $resC['adjusted_overtime_hours']);
        $this->assertEquals(4.0, $resC['remaining_shortage_hours']);
    }

    /**
     * Requirement: Regular overtime calculation matches verified Excel formulas:
     * - Break deduction = overtime / 8 (Excel cell AV)
     * - Net overtime = gross - break (Excel cell AW)
     * - First hours = min(net, overtime_days) (Excel cell AZ)
     * - Subsequent hours = net - first_hours (Excel cell BB)
     * - First cost = first_hours * rate * 1.5 (Excel cell BA)
     * - Subsequent cost = sub_hours * rate * 2.0 (Excel cell BC)
     * - Total cost = first_cost + sub_cost (Excel cell BD)
     */
    public function test_regular_overtime_verified_excel_formula_wawan_fixture(): void
    {
        // 5 overtime days, 40 overtime hours (Wawan fixture)
        $res = $this->overtimeCalc->calculateRegularOvertime(5, 40.0, $this->config);

        $this->assertEquals(5.0, $res['break_deduction_hours']); // 40 / 8
        $this->assertEquals(35.0, $res['net_hours']);            // 40 - 5
        $this->assertEquals(5.0, $res['first_hours']);          // min(35, 5 days)
        $this->assertEquals(105000.0, $res['first_hours_cost']);// 5 * 14000 * 1.5
        $this->assertEquals(30.0, $res['subsequent_hours']);    // 35 - 5
        $this->assertEquals(840000.0, $res['subsequent_hours_cost']); // 30 * 14000 * 2.0
        $this->assertEquals(945000.0, $res['total_cost']);     // 105000 + 840000
    }

    /**
     * Requirement: OFF_HOLIDAY overtime is calculated independently, and rate protection
     * prevents unauthorized nominal costs when tariff is not confirmed.
     */
    public function test_off_holiday_overtime_separation_and_unconfirmed_rate_protection(): void
    {
        // 1 holiday shift of 8 hours, rate NOT confirmed
        $unconfirmed = $this->overtimeCalc->calculateOffHolidayOvertime(1, 8.0, $this->config, false);

        $this->assertEquals(1.0, $unconfirmed['break_deduction_hours']); // 8 / 8 = 1h
        $this->assertEquals(7.0, $unconfirmed['net_hours']);
        $this->assertFalse($unconfirmed['rate_confirmed']);
        $this->assertEquals(0.0, $unconfirmed['total_cost']); // 0.0 prevents unauthorized payroll payout
        $this->assertNotNull($unconfirmed['rate_status_message']);
        $this->assertEquals(196000.0, $unconfirmed['estimated_cost']); // 7h * 14000 * 2.0

        // When confirmed
        $confirmed = $this->overtimeCalc->calculateOffHolidayOvertime(1, 8.0, $this->config, true);
        $this->assertTrue($confirmed['rate_confirmed']);
        $this->assertEquals(196000.0, $confirmed['total_cost']);
    }
}

