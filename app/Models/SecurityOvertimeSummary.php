<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SecurityOvertimeSummary extends Model
{
    use HasFactory;

    protected $fillable = [
        'payroll_period_id',
        'employee_id',
        'present_days',
        'off_days',
        'sick_days',
        'permission_days',
        'leave_days',
        'absent_days',
        'attendance_hours',
        'target_hours',
        'current_shortage_hours',
        'previous_shortage_hours',
        'total_shortage_hours',
        'shortage_offset_hours',
        'remaining_shortage_hours',
        'previous_payroll_period_id',
        'regular_overtime_days',
        'regular_overtime_hours',
        'regular_break_deduction_hours',
        'regular_net_overtime_hours',
        'regular_first_hours',
        'regular_first_hours_cost',
        'regular_subsequent_hours',
        'regular_subsequent_hours_cost',
        'regular_overtime_cost',
        'off_holiday_overtime_days',
        'off_holiday_overtime_hours',
        'off_holiday_break_deduction_hours',
        'off_holiday_net_overtime_hours',
        'off_holiday_multiplier',
        'off_holiday_overtime_cost',
        'off_holiday_rate_confirmed',
        'total_days',
        'total_work_days',
        'total_off_days',
        'total_absent_days',
        'total_work_hours',
        'gross_overtime_hours',
        'break_deduction_hours',
        'net_overtime_hours',
        'hourly_rate',
        'first_hours_limit',
        'first_hours_multiplier',
        'subsequent_hours_multiplier',
        'first_hours',
        'first_hours_cost',
        'subsequent_hours',
        'subsequent_hours_cost',
        'total_overtime_cost',
        'total_period_salary',
        'config_version',
        'calculation_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'present_days' => 'integer',
            'off_days' => 'integer',
            'sick_days' => 'integer',
            'permission_days' => 'integer',
            'leave_days' => 'integer',
            'absent_days' => 'integer',
            'attendance_hours' => 'decimal:2',
            'target_hours' => 'decimal:2',
            'current_shortage_hours' => 'decimal:2',
            'previous_shortage_hours' => 'decimal:2',
            'total_shortage_hours' => 'decimal:2',
            'shortage_offset_hours' => 'decimal:2',
            'remaining_shortage_hours' => 'decimal:2',
            'previous_payroll_period_id' => 'integer',
            'regular_overtime_days' => 'integer',
            'regular_overtime_hours' => 'decimal:2',
            'regular_break_deduction_hours' => 'decimal:2',
            'regular_net_overtime_hours' => 'decimal:2',
            'regular_first_hours' => 'decimal:2',
            'regular_first_hours_cost' => 'decimal:2',
            'regular_subsequent_hours' => 'decimal:2',
            'regular_subsequent_hours_cost' => 'decimal:2',
            'regular_overtime_cost' => 'decimal:2',
            'off_holiday_overtime_days' => 'integer',
            'off_holiday_overtime_hours' => 'decimal:2',
            'off_holiday_break_deduction_hours' => 'decimal:2',
            'off_holiday_net_overtime_hours' => 'decimal:2',
            'off_holiday_multiplier' => 'decimal:2',
            'off_holiday_overtime_cost' => 'decimal:2',
            'off_holiday_rate_confirmed' => 'boolean',
            'total_days' => 'integer',
            'total_work_days' => 'integer',
            'total_off_days' => 'integer',
            'total_absent_days' => 'integer',
            'total_work_hours' => 'decimal:2',
            'gross_overtime_hours' => 'decimal:2',
            'break_deduction_hours' => 'decimal:2',
            'net_overtime_hours' => 'decimal:2',
            'hourly_rate' => 'decimal:2',
            'first_hours_limit' => 'decimal:2',
            'first_hours_multiplier' => 'decimal:2',
            'subsequent_hours_multiplier' => 'decimal:2',
            'first_hours' => 'decimal:2',
            'first_hours_cost' => 'decimal:2',
            'subsequent_hours' => 'decimal:2',
            'subsequent_hours_cost' => 'decimal:2',
            'total_overtime_cost' => 'decimal:2',
            'total_period_salary' => 'decimal:2',
            'config_version' => 'integer',
            'calculation_snapshot' => 'array',
        ];
    }

    public function payrollPeriod(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class);
    }

    public function previousPayrollPeriod(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class, 'previous_payroll_period_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
