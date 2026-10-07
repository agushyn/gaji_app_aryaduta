<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollApprovalEvent extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'payroll_period_id',
        'payslip_id',
        'actor_user_id',
        'decision',
        'comment',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
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
     * Optional relationship with a specific payslip.
     */
    public function payslip(): BelongsTo
    {
        return $this->belongsTo(Payslip::class);
    }

    /**
     * Relationship with the actor user who made the decision.
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    /**
     * Human readable decision label.
     */
    public function getDecisionLabelAttribute(): string
    {
        return match ($this->decision) {
            'submitted' => 'Diajukan untuk Review',
            'approved' => 'Disetujui',
            'changes_requested' => 'Permintaan Perbaikan',
            'reopened' => 'Dibuka Kembali (Perubahan Data)',
            default => ucfirst(str_replace('_', ' ', $this->decision)),
        };
    }

    /**
     * Tailwind badge CSS classes for decision.
     */
    public function getBadgeClassAttribute(): string
    {
        return match ($this->decision) {
            'submitted' => 'bg-amber-50 text-amber-700 border-amber-200',
            'approved' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
            'changes_requested' => 'bg-rose-50 text-rose-700 border-rose-200',
            'reopened' => 'bg-slate-100 text-slate-700 border-slate-300',
            default => 'bg-slate-50 text-slate-700 border-slate-200',
        };
    }
}
