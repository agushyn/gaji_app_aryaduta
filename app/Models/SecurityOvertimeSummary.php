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

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
