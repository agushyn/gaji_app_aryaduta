<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SecurityPayrollConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'hourly_rate',
        'first_hours_limit',
        'first_hours_multiplier',
        'subsequent_hours_multiplier',
        'break_deduction_rule',
        'break_deduction_default',
        'max_break_deduction',
        'weekday_multiplier',
        'holiday_multiplier',
        'round_rule',
        'version',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'hourly_rate' => 'decimal:2',
            'first_hours_limit' => 'decimal:2',
            'first_hours_multiplier' => 'decimal:2',
            'subsequent_hours_multiplier' => 'decimal:2',
            'break_deduction_default' => 'decimal:2',
            'max_break_deduction' => 'decimal:2',
            'weekday_multiplier' => 'decimal:2',
            'holiday_multiplier' => 'decimal:2',
            'version' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get or create the active security payroll configuration.
     */
    public static function getActiveConfig(): self
    {
        $config = self::where('is_active', true)->first();

        if (! $config) {
            $config = self::create([
                'code' => 'SECURITY_DEFAULT',
                'name' => 'Konfigurasi Lembur Security Default',
                'hourly_rate' => '14000.00',
                'first_hours_limit' => '5.00',
                'first_hours_multiplier' => '1.50',
                'subsequent_hours_multiplier' => '2.00',
                'break_deduction_rule' => 'fixed',
                'break_deduction_default' => '0.00',
                'max_break_deduction' => '20.00',
                'weekday_multiplier' => '1.00',
                'holiday_multiplier' => '1.00',
                'round_rule' => 'round',
                'version' => 1,
                'is_active' => true,
            ]);
        }

        return $config;
    }
}
