<?php

namespace App\Enums;

enum PayslipStatus: string
{
    case DRAFT = 'draft';
    case PENDING_REVIEW = 'pending_review';
    case CHANGES_REQUESTED = 'changes_requested';
    case APPROVED = 'approved';
    case FINALIZED = 'finalized';
    case VOIDED = 'voided';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::PENDING_REVIEW => 'Menunggu Review',
            self::CHANGES_REQUESTED => 'Perlu Revisi',
            self::APPROVED => 'Disetujui',
            self::FINALIZED => 'Final',
            self::VOIDED => 'Dibatalkan',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::DRAFT => 'bg-slate-50 text-slate-700 border-slate-200',
            self::PENDING_REVIEW => 'bg-amber-50 text-amber-700 border-amber-200',
            self::CHANGES_REQUESTED => 'bg-rose-50 text-rose-700 border-rose-200',
            self::APPROVED => 'bg-indigo-50 text-indigo-700 border-indigo-200',
            self::FINALIZED => 'bg-emerald-50 text-emerald-700 border-emerald-200',
            self::VOIDED => 'bg-rose-100 text-rose-800 border-rose-300',
        };
    }
}
