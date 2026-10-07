<?php

namespace App\Models;

use App\Enums\PayComponentType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class PayComponent extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'type',
        'is_active',
        'sort_order',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Alias for sort_order to match template specification.
     */
    public function getDisplayOrderAttribute(): int
    {
        return (int) $this->sort_order;
    }

    /**
     * Relationship with payslip items.
     */
    public function payslipItems(): HasMany
    {
        return $this->hasMany(PayslipItem::class);
    }

    /**
     * Relationship with payroll calculation configurations.
     */
    public function calculationConfigs(): HasMany
    {
        return $this->hasMany(PayrollCalculationConfig::class, 'component_id');
    }

    /**
     * Creator of the pay component.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Check if this component has been used in any payslips.
     */
    public function isUsedInPayslips(): bool
    {
        return DB::table('payslip_items')
            ->where('pay_component_id', $this->id)
            ->exists();
    }

    /**
     * Check if component type is earning.
     */
    public function isEarning(): bool
    {
        return $this->type === PayComponentType::EARNING->value;
    }

    /**
     * Check if component type is deduction.
     */
    public function isDeduction(): bool
    {
        return $this->type === PayComponentType::DEDUCTION->value;
    }

    /**
     * Scope for active components.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for earnings.
     */
    public function scopeEarnings(Builder $query): Builder
    {
        return $query->where('type', PayComponentType::EARNING->value);
    }

    /**
     * Scope for deductions.
     */
    public function scopeDeductions(Builder $query): Builder
    {
        return $query->where('type', PayComponentType::DEDUCTION->value);
    }

    /**
     * Scope for default ordering.
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('code');
    }

    /**
     * Scope search by code or name.
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (empty($term)) {
            return $query;
        }

        $term = trim($term);

        return $query->where(function (Builder $q) use ($term) {
            $q->where('code', 'like', "%{$term}%")
                ->orWhere('name', 'like', "%{$term}%");
        });
    }
}
