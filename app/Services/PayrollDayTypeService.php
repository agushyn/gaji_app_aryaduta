<?php

namespace App\Services;

use App\Models\HolidayCalendar;
use Carbon\Carbon;

class PayrollDayTypeService
{
    /**
     * Determine the semantic day type for a given date.
     *
     * @param  string|Carbon  $date
     * @return string 'national_holiday' | 'joint_leave' | 'weekend' | 'working_day'
     */
    public function getDayType(string|Carbon $date): string
    {
        $carbon = is_string($date) ? Carbon::parse($date) : $date->copy();
        $dateStr = $carbon->toDateString();

        // 1. Check local holiday calendar
        $holiday = HolidayCalendar::active()
            ->whereDate('date', $dateStr)
            ->first();

        if ($holiday) {
            if ($holiday->is_joint_leave || $holiday->type === 'joint_leave') {
                return 'joint_leave';
            }

            return 'national_holiday';
        }

        // 2. Check weekend (Saturday = 6, Sunday = 0)
        if ($carbon->isWeekend()) {
            return 'weekend';
        }

        // 3. Regular working day
        return 'working_day';
    }

    /**
     * Check if a date should be treated as a holiday for payroll/overtime calculation.
     * Respects company configuration for joint leave (cuti bersama).
     */
    public function isOvertimeHoliday(string|Carbon $date): bool
    {
        $carbon = is_string($date) ? Carbon::parse($date) : $date->copy();
        $dateStr = $carbon->toDateString();

        $holiday = HolidayCalendar::active()
            ->whereDate('date', $dateStr)
            ->first();

        if ($holiday) {
            // National holiday is always a holiday for payroll
            if (! $holiday->is_joint_leave && $holiday->type === 'national_holiday') {
                return true;
            }

            // Joint leave only counts if is_payroll_holiday is explicitly enabled
            if ($holiday->is_joint_leave || $holiday->type === 'joint_leave') {
                return (bool) $holiday->is_payroll_holiday;
            }

            return (bool) $holiday->is_payroll_holiday;
        }

        // Weekend (Saturday or Sunday) is treated as non-working day / holiday for overtime
        if ($carbon->isWeekend()) {
            return true;
        }

        return false;
    }

    /**
     * Resolve configuration day_type key ('working_day' or 'holiday') for rule matching.
     */
    public function resolveRuleDayType(string|Carbon $date): string
    {
        return $this->isOvertimeHoliday($date) ? 'holiday' : 'working_day';
    }
}
