<?php

namespace App\Models;

use App\Enums\DeliveryStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayslipDelivery extends Model
{
    use HasFactory;

    protected $fillable = [
        'payslip_id',
        'channel',
        'recipient',
        'status',
        'provider_message_id',
        'idempotency_key',
        'attempt_count',
        'last_attempt_at',
        'sent_at',
        'delivered_at',
        'last_error_code',
        'last_error_summary',
    ];

    protected function casts(): array
    {
        return [
            'attempt_count' => 'integer',
            'last_attempt_at' => 'datetime',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    /**
     * Relationship with the payslip.
     */
    public function payslip(): BelongsTo
    {
        return $this->belongsTo(Payslip::class);
    }

    /**
     * Relationship with the delivery attempts.
     */
    public function attempts(): HasMany
    {
        return $this->hasMany(DeliveryAttempt::class)->orderBy('attempt_number', 'asc');
    }

    /**
     * Check if delivery can be retried.
     */
    public function canRetry(): bool
    {
        return in_array($this->status, [
            DeliveryStatus::FAILED->value,
            DeliveryStatus::UNKNOWN->value,
        ], true);
    }

    /**
     * Check if delivery is already completed.
     */
    public function isSent(): bool
    {
        return in_array($this->status, [
            DeliveryStatus::SENT->value,
            DeliveryStatus::DELIVERED->value,
        ], true);
    }

    /**
     * Status label attribute.
     */
    public function getStatusLabelAttribute(): string
    {
        return DeliveryStatus::tryFrom($this->status)?->label() ?? $this->status;
    }

    /**
     * Badge class attribute.
     */
    public function getBadgeClassAttribute(): string
    {
        return DeliveryStatus::tryFrom($this->status)?->badgeClass() ?? 'bg-slate-100 text-slate-700 border-slate-200';
    }

    /**
     * Scope for a specific delivery channel.
     */
    public function scopeForChannel(Builder $query, string $channel): Builder
    {
        return $query->where('channel', $channel);
    }

    /**
     * Scope for status filter.
     */
    public function scopeFilterStatus(Builder $query, ?string $status): Builder
    {
        if (empty($status) || $status === 'all') {
            return $query;
        }

        return $query->where('status', $status);
    }
}
