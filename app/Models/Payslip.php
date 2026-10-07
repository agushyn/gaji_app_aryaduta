<?php

namespace App\Models;

use App\Enums\PayslipStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Payslip extends Model
{
    use HasFactory;

    protected $fillable = [
        'payroll_period_id',
        'employee_id',
        'office_id',
        'slip_number',
        'employee_number_snapshot',
        'employee_name_snapshot',
        'department_snapshot',
        'position_snapshot',
        'email_snapshot',
        'phone_snapshot',
        'working_days_snapshot',
        'period_month_snapshot',
        'period_start_date_snapshot',
        'period_end_date_snapshot',
        'overtime_period_snapshot',
        'basic_salary',
        'total_earnings',
        'total_deductions',
        'net_salary',
        'gaji_bruto',
        'uang_dinas',
        'currency',
        'company_name_snapshot',
        'company_address_snapshot',
        'office_name_snapshot',
        'office_code_snapshot',
        'office_address_snapshot',
        'office_phone_snapshot',
        'office_email_snapshot',
        'division_name_snapshot',
        'position_name_snapshot',
        'pdf_path',
        'pdf_sha256',
        'pdf_generated_at',
        'signatory_personalia_name',
        'signatory_personalia_title',
        'signatory_reliever_name',
        'signatory_reliever_title',
        'signatory_receiver_name',
        'status',
        'created_by',
        'submitted_by',
        'submitted_at',
        'approved_by',
        'approved_at',
        'approval_comment',
        'finalized_by',
        'finalized_at',
        'voided_by',
        'voided_at',
        'void_reason',
    ];

    protected function casts(): array
    {
        return [
            'working_days_snapshot' => 'integer',
            'period_start_date_snapshot' => 'date',
            'period_end_date_snapshot' => 'date',
            'basic_salary' => 'decimal:2',
            'total_earnings' => 'decimal:2',
            'total_deductions' => 'decimal:2',
            'net_salary' => 'decimal:2',
            'gaji_bruto' => 'decimal:2',
            'uang_dinas' => 'decimal:2',
            'pdf_generated_at' => 'datetime',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'finalized_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    /**
     * Relationship with the payroll period.
     */
    public function payrollPeriod(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class);
    }

    /**
     * Relationship with the employee.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Relationship with the office.
     */
    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }

    /**
     * Fallback accessor for office name snapshot.
     */
    public function getOfficeNameSnapshotAttribute($value): string
    {
        return $value ?: ($this->company_name_snapshot ?? 'CV. BINA PUSTAKA');
    }

    /**
     * Fallback accessor for office address snapshot.
     */
    public function getOfficeAddressSnapshotAttribute($value): ?string
    {
        return $value ?: $this->company_address_snapshot;
    }

    /**
     * Fallback accessor for division name snapshot.
     */
    public function getDivisionNameSnapshotAttribute($value): ?string
    {
        return $value ?: $this->department_snapshot;
    }

    /**
     * Fallback accessor for position name snapshot.
     */
    public function getPositionNameSnapshotAttribute($value): ?string
    {
        return $value ?: $this->position_snapshot;
    }

    /**
     * Relationship with payslip items.
     */
    public function items(): HasMany
    {
        return $this->hasMany(PayslipItem::class)->orderBy('sort_order');
    }

    /**
     * Items of type earning.
     */
    public function earnings(): HasMany
    {
        return $this->hasMany(PayslipItem::class)
            ->where('item_type', 'earning')
            ->orderBy('sort_order');
    }

    /**
     * Items of type deduction.
     */
    public function deductions(): HasMany
    {
        return $this->hasMany(PayslipItem::class)
            ->where('item_type', 'deduction')
            ->orderBy('sort_order');
    }

    /**
     * Relationship with delivery records.
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(PayslipDelivery::class);
    }

    /**
     * Relationship with email delivery.
     */
    public function emailDelivery(): HasOne
    {
        return $this->hasOne(PayslipDelivery::class)->where('channel', 'email');
    }

    /**
     * Creator of the payslip.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Submitter of the payslip.
     */
    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /**
     * Approver of the payslip.
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Finalizer of the payslip.
     */
    public function finalizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }

    /**
     * User who voided the payslip.
     */
    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    /**
     * History of approval workflow events for this payslip.
     */
    public function approvalEvents(): HasMany
    {
        return $this->hasMany(PayrollApprovalEvent::class)->orderBy('created_at', 'desc');
    }

    /**
     * Check if payslip is currently in draft.
     */
    public function isDraft(): bool
    {
        return $this->status === PayslipStatus::DRAFT->value;
    }

    /**
     * Check if payslip is currently pending review.
     */
    public function isPendingReview(): bool
    {
        return $this->status === PayslipStatus::PENDING_REVIEW->value;
    }

    /**
     * Check if changes are requested for this payslip.
     */
    public function isChangesRequested(): bool
    {
        return $this->status === PayslipStatus::CHANGES_REQUESTED->value;
    }

    /**
     * Check if payslip is approved.
     */
    public function isApproved(): bool
    {
        return $this->status === PayslipStatus::APPROVED->value;
    }

    /**
     * Check if payslip is finalized.
     */
    public function isFinalized(): bool
    {
        return $this->status === PayslipStatus::FINALIZED->value;
    }

    /**
     * Check if payslip can be submitted for review.
     */
    public function canBeSubmitted(): bool
    {
        return in_array($this->status, [
            PayslipStatus::DRAFT->value,
            PayslipStatus::CHANGES_REQUESTED->value,
        ], true);
    }

    /**
     * Check if payslip can be approved.
     */
    public function canBeApproved(): bool
    {
        return $this->status === PayslipStatus::PENDING_REVIEW->value;
    }

    /**
     * Check if changes can be requested for this payslip.
     */
    public function canRequestChanges(): bool
    {
        return $this->status === PayslipStatus::PENDING_REVIEW->value;
    }

    /**
     * Check if payslip can be finalized.
     */
    public function canBeFinalized(): bool
    {
        return $this->status === PayslipStatus::APPROVED->value;
    }

    /**
     * Check if payslip can be voided.
     */
    public function canBeVoided(): bool
    {
        return $this->status === PayslipStatus::FINALIZED->value;
    }

    /**
     * Check if payslip can be modified.
     * Modifying a submitted/approved payslip will invalidate prior approvals.
     * Finalized or voided payslips cannot be modified directly.
     */
    public function canBeModified(): bool
    {
        return ! in_array($this->status, [
            PayslipStatus::FINALIZED->value,
            PayslipStatus::VOIDED->value,
        ], true);
    }

    /**
     * Formatted net salary in Indonesian Rupiah.
     */
    public function getFormattedNetSalaryAttribute(): string
    {
        return 'Rp '.number_format((float) $this->net_salary, 0, ',', '.');
    }

    /**
     * Formatted gaji bruto in Indonesian Rupiah.
     */
    public function getFormattedGajiBrutoAttribute(): string
    {
        return 'Rp '.number_format((float) $this->gaji_bruto, 0, ',', '.');
    }

    /**
     * Formatted uang dinas in Indonesian Rupiah.
     */
    public function getFormattedUangDinasAttribute(): string
    {
        return 'Rp '.number_format((float) $this->uang_dinas, 0, ',', '.');
    }

    /**
     * Formatted total earnings in Indonesian Rupiah.
     */
    public function getFormattedTotalEarningsAttribute(): string
    {
        return 'Rp '.number_format((float) $this->total_earnings, 0, ',', '.');
    }

    /**
     * Formatted total deductions in Indonesian Rupiah.
     */
    public function getFormattedTotalDeductionsAttribute(): string
    {
        return 'Rp '.number_format((float) $this->total_deductions, 0, ',', '.');
    }

    /**
     * Formatted basic salary in Indonesian Rupiah.
     */
    public function getFormattedBasicSalaryAttribute(): string
    {
        return 'Rp '.number_format((float) $this->basic_salary, 0, ',', '.');
    }

    /**
     * Human readable status label.
     */
    public function getStatusLabelAttribute(): string
    {
        return PayslipStatus::tryFrom($this->status)?->label() ?? $this->status;
    }

    /**
     * Scope search.
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (empty($term)) {
            return $query;
        }

        $term = trim($term);

        return $query->where(function (Builder $q) use ($term) {
            $q->where('slip_number', 'like', "%{$term}%")
                ->orWhere('employee_name_snapshot', 'like', "%{$term}%")
                ->orWhere('employee_number_snapshot', 'like', "%{$term}%")
                ->orWhere('department_snapshot', 'like', "%{$term}%")
                ->orWhere('position_snapshot', 'like', "%{$term}%");
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
