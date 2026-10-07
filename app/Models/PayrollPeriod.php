<?php

namespace App\Models;

use App\Enums\PayrollPeriodStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollPeriod extends Model
{
    use HasFactory;

    protected $fillable = [
        'period_code',
        'start_date',
        'end_date',
        'working_days',
        'period_month_label',
        'status',
        'created_by',
        'submitted_by',
        'submitted_at',
        'approved_by',
        'approved_at',
        'approval_comment',
        'finalized_by',
        'finalized_at',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'working_days' => 'integer',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'finalized_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /**
     * Relationship with payslips.
     */
    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class);
    }

    /**
     * Creator of the payroll period.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * User who submitted the period for review.
     */
    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /**
     * User who approved the period.
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * User who finalized the period.
     */
    public function finalizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }

    /**
     * History of approval workflow events for this period.
     */
    public function approvalEvents(): HasMany
    {
        return $this->hasMany(PayrollApprovalEvent::class)->orderBy('created_at', 'desc');
    }

    /**
     * Check if the period is in draft state.
     */
    public function isDraft(): bool
    {
        return $this->status === PayrollPeriodStatus::DRAFT->value;
    }

    /**
     * Check if the period is currently under review.
     */
    public function isInReview(): bool
    {
        return $this->status === PayrollPeriodStatus::IN_REVIEW->value;
    }

    /**
     * Check if the period has changes requested.
     */
    public function isChangesRequested(): bool
    {
        return $this->status === PayrollPeriodStatus::CHANGES_REQUESTED->value;
    }

    /**
     * Check if the period is approved.
     */
    public function isApproved(): bool
    {
        return $this->status === PayrollPeriodStatus::APPROVED->value;
    }

    /**
     * Check if the period is finalized.
     */
    public function isFinalized(): bool
    {
        return $this->status === PayrollPeriodStatus::FINALIZED->value;
    }

    /**
     * Check if the period can be submitted for review.
     */
    public function canBeSubmitted(): bool
    {
        return in_array($this->status, [
            PayrollPeriodStatus::DRAFT->value,
            PayrollPeriodStatus::CHANGES_REQUESTED->value,
        ], true) && $this->payslips()->exists();
    }

    /**
     * Check if the period can be approved.
     */
    public function canBeApproved(): bool
    {
        return $this->status === PayrollPeriodStatus::IN_REVIEW->value;
    }

    /**
     * Check if changes can be requested for this period.
     */
    public function canRequestChanges(): bool
    {
        return $this->status === PayrollPeriodStatus::IN_REVIEW->value;
    }

    /**
     * Check if the period can be finalized.
     */
    public function canBeFinalized(): bool
    {
        return $this->status === PayrollPeriodStatus::APPROVED->value;
    }

    /**
     * Check if the period can be modified by personalia.
     */
    public function canBeModified(): bool
    {
        return in_array($this->status, [
            PayrollPeriodStatus::DRAFT->value,
            PayrollPeriodStatus::CHANGES_REQUESTED->value,
        ], true);
    }

    /**
     * Human readable status label.
     */
    public function getStatusLabelAttribute(): string
    {
        return PayrollPeriodStatus::tryFrom($this->status)?->label() ?? $this->status;
    }

    /**
     * Scope search by period code or date.
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (empty($term)) {
            return $query;
        }

        $term = trim($term);

        return $query->where(function (Builder $q) use ($term) {
            $q->where('period_code', 'like', "%{$term}%")
                ->orWhere('approval_comment', 'like', "%{$term}%");
        });
    }

    /**
     * Scope filter by status.
     */
    public function scopeFilterStatus(Builder $query, ?string $status): Builder
    {
        if (empty($status)) {
            return $query;
        }

        return $query->where('status', $status);
    }
}
