<?php

namespace App\Services\Security;

use App\Enums\PayrollPeriodStatus;
use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Models\SecurityOvertimeSummary;

class SecurityAttendanceCalculator
{
    /**
     * Standard working hours per normal day.
     */
    public const STANDARD_HOURS_PER_DAY = 8.0;

    /**
     * Calculate total standard calendar hours in period.
     */
    public function calculateCalendarHours(int $calendarDays): float
    {
        return round(max(0, $calendarDays) * self::STANDARD_HOURS_PER_DAY, 2);
    }

    /**
     * Calculate target working hours in period based on Excel cell AP = AK - AM - AO:
     * target_hours = (calendar_days - off_days - total_absence_days) * 8.
     */
    public function calculateTargetHours(int $calendarDays, int $offDays, int $totalAbsenceDays): float
    {
        $netWorkingDays = max(0, $calendarDays - $offDays - $totalAbsenceDays);

        return round($netWorkingDays * self::STANDARD_HOURS_PER_DAY, 2);
    }

    /**
     * Calculate actual attendance hours from present days (backend is source of truth):
     * attendance_hours = present_days * 8.
     */
    public function calculateAttendanceHours(int $presentDays): float
    {
        return round(max(0, $presentDays) * self::STANDARD_HOURS_PER_DAY, 2);
    }

    /**
     * Calculate current period shortage hours based on Excel cell AR = IF(AQ < AP, AP - AQ, 0).
     */
    public function calculateCurrentShortage(float $targetHours, float $attendanceHours): float
    {
        if ($attendanceHours < $targetHours) {
            return round(max(0.0, $targetHours - $attendanceHours), 2);
        }

        return 0.0;
    }

    /**
     * Resolve carry-forward shortage from the immediately preceding approved/finalized payroll period.
     * Prevents circular references and does not carry forward unverified draft periods.
     *
     * @return array{
     *     previous_period: ?PayrollPeriod,
     *     previous_shortage_hours: float,
     *     source: string
     * }
     */
    public function resolvePreviousShortage(
        Employee $employee,
        PayrollPeriod $currentPeriod,
        ?float $manualOverride = null
    ): array {
        if ($manualOverride !== null) {
            return [
                'previous_period' => null,
                'previous_shortage_hours' => max(0.0, round($manualOverride, 2)),
                'source' => 'manual',
            ];
        }

        // Strictly search preceding periods chronologically before current period start
        $previousPeriod = PayrollPeriod::where('end_date', '<=', $currentPeriod->start_date)
            ->where('id', '!=', $currentPeriod->id)
            ->whereIn('status', [PayrollPeriodStatus::FINALIZED->value, PayrollPeriodStatus::APPROVED->value])
            ->orderByDesc('end_date')
            ->orderByDesc('start_date')
            ->first();

        if (! $previousPeriod) {
            return [
                'previous_period' => null,
                'previous_shortage_hours' => 0.0,
                'source' => 'none',
            ];
        }

        $previousSummary = SecurityOvertimeSummary::where('payroll_period_id', $previousPeriod->id)
            ->where('employee_id', $employee->id)
            ->first();

        if (! $previousSummary) {
            return [
                'previous_period' => $previousPeriod,
                'previous_shortage_hours' => 0.0,
                'source' => 'previous_period_empty',
            ];
        }

        // Use remaining_shortage_hours if tracked; fallback to total_shortage_hours
        $remaining = (float) (
            $previousSummary->remaining_shortage_hours ??
            $previousSummary->total_shortage_hours ??
            0.0
        );

        return [
            'previous_period' => $previousPeriod,
            'previous_shortage_hours' => max(0.0, round($remaining, 2)),
            'source' => 'carry_forward',
        ];
    }

    /**
     * Calculate total shortage hours based on Excel cell AT = AR + AS.
     */
    public function calculateTotalShortage(float $currentShortage, float $previousShortage): float
    {
        return round(max(0.0, $currentShortage) + max(0.0, $previousShortage), 2);
    }

    /**
     * Apply shortage offset against gross overtime hours based on Excel cell AU = IF(AQ > AP, AQ - AT - AP, 0).
     * Shortage directly offsets overtime hours before calculating overtime pay.
     * Returns the offset applied, the remaining shortage (for next month), and the adjusted overtime.
     *
     * @return array{
     *     shortage_offset_hours: float,
     *     adjusted_overtime_hours: float,
     *     remaining_shortage_hours: float
     * }
     */
    public function applyShortageOffset(float $totalShortage, float $grossOvertime): array
    {
        $shortage = max(0.0, round($totalShortage, 2));
        $overtime = max(0.0, round($grossOvertime, 2));

        $offset = min($shortage, $overtime);
        $adjustedOvertime = max(0.0, round($overtime - $offset, 2));
        $remainingShortage = max(0.0, round($shortage - $offset, 2));

        return [
            'shortage_offset_hours' => $offset,
            'adjusted_overtime_hours' => $adjustedOvertime,
            'remaining_shortage_hours' => $remainingShortage,
        ];
    }
}

