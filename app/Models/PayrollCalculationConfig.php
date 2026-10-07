<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

class PayrollCalculationConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'component_id',
        'day_type',
        'is_active',
        'current_version',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'current_version' => 'integer',
        ];
    }

    /**
     * Associated pay component.
     */
    public function component(): BelongsTo
    {
        return $this->belongsTo(PayComponent::class, 'component_id');
    }

    public function payComponent(): BelongsTo
    {
        return $this->belongsTo(PayComponent::class, 'component_id');
    }

    /**
     * All versions of this configuration.
     */
    public function versions(): HasMany
    {
        return $this->hasMany(PayrollCalculationConfigVersion::class, 'config_id');
    }

    /**
     * Currently active version model.
     */
    public function currentVersionRecord(): HasOne
    {
        return $this->hasOne(PayrollCalculationConfigVersion::class, 'config_id')
            ->where('is_active', true);
    }

    /**
     * Creator of the configuration.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Last updater of the configuration.
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Check if this configuration has been used in any finalized payslips.
     */
    public function isUsedInFinalizedPayslips(): bool
    {
        return DB::table('payslip_items')
            ->join('payslips', 'payslips.id', '=', 'payslip_items.payslip_id')
            ->where('payslip_items.calculation_config_id', $this->id)
            ->where('payslips.status', 'finalized')
            ->exists();
    }

    /**
     * Scope for active configurations.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for specific day type.
     */
    public function scopeForDayType(Builder $query, string $dayType): Builder
    {
        return $query->where(function (Builder $q) use ($dayType) {
            $q->where('day_type', $dayType)
                ->orWhere('day_type', 'all');
        });
    }

    /**
     * Scope for specific component code.
     */
    public function scopeForComponentCode(Builder $query, string $componentCode): Builder
    {
        return $query->whereHas('component', function (Builder $q) use ($componentCode) {
            $q->where('code', $componentCode);
        });
    }
}
