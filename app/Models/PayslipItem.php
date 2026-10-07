<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayslipItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'payslip_id',
        'pay_component_id',
        'calculation_config_id',
        'calculation_config_version',
        'item_code_snapshot',
        'description_snapshot',
        'item_type',
        'category',
        'quantity',
        'unit',
        'rate',
        'note',
        'calculation_snapshot',
        'amount',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'quantity' => 'decimal:2',
            'rate' => 'decimal:2',
            'sort_order' => 'integer',
            'calculation_config_version' => 'integer',
            'calculation_snapshot' => 'array',
        ];
    }

    /**
     * Parent payslip.
     */
    public function payslip(): BelongsTo
    {
        return $this->belongsTo(Payslip::class);
    }

    /**
     * Calculation configuration used when generating this item.
     */
    public function calculationConfig(): BelongsTo
    {
        return $this->belongsTo(PayrollCalculationConfig::class, 'calculation_config_id');
    }

    /**
     * Original pay component reference if still existing.
     */
    public function payComponent(): BelongsTo
    {
        return $this->belongsTo(PayComponent::class);
    }

    /**
     * Formatted amount display.
     */
    public function getFormattedAmountAttribute(): string
    {
        return 'Rp '.number_format((float) $this->amount, 0, ',', '.');
    }

    /**
     * Alias for item_code_snapshot to match snapshot specification.
     */
    public function getComponentCodeAttribute(): string
    {
        return (string) ($this->item_code_snapshot ?? '');
    }

    /**
     * Alias for description_snapshot to match snapshot specification.
     */
    public function getComponentNameAttribute(): string
    {
        return (string) ($this->description_snapshot ?? '');
    }

    /**
     * Alias for item_type to match snapshot specification.
     */
    public function getComponentTypeAttribute(): string
    {
        return (string) ($this->item_type ?? 'earning');
    }

    /**
     * Alias for sort_order to match snapshot specification.
     */
    public function getDisplayOrderAttribute(): int
    {
        return (int) $this->sort_order;
    }

    /**
     * Formatted slip amount: displays '-' for 0/null and formatted Rupiah for positive values.
     */
    public function getSlipAmountDisplayAttribute(): string
    {
        if ($this->amount === null || (float) $this->amount == 0.0) {
            return '-';
        }

        return 'Rp '.number_format((float) $this->amount, 0, ',', '.');
    }
}
