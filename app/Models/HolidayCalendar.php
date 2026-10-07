<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HolidayCalendar extends Model
{
    use HasFactory;

    protected $fillable = [
        'date',
        'name',
        'type',
        'is_joint_leave',
        'is_payroll_holiday',
        'year',
        'source',
        'source_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'is_joint_leave' => 'boolean',
            'is_payroll_holiday' => 'boolean',
            'year' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Scope for active holidays.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for specific year.
     */
    public function scopeForYear(Builder $query, int $year): Builder
    {
        return $query->where('year', $year);
    }

    /**
     * Scope for holidays effective in payroll calculation.
     */
    public function scopePayrollHoliday(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where('is_payroll_holiday', true);
    }

    /**
     * Scope for national holidays.
     */
    public function scopeNational(Builder $query): Builder
    {
        return $query->where('type', 'national_holiday');
    }

    /**
     * Scope for joint leaves.
     */
    public function scopeJointLeave(Builder $query): Builder
    {
        return $query->where('type', 'joint_leave');
    }
}
