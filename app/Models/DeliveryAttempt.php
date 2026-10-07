<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryAttempt extends Model
{
    use HasFactory;

    protected $fillable = [
        'payslip_delivery_id',
        'attempt_number',
        'status',
        'provider_message_id',
        'error_code',
        'error_summary',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'attempt_number' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * Relationship with parent delivery.
     */
    public function delivery(): BelongsTo
    {
        return $this->belongsTo(PayslipDelivery::class, 'payslip_delivery_id');
    }
}
