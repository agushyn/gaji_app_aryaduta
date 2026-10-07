<?php

namespace App\Enums;

enum DeliveryStatus: string
{
    case QUEUED = 'queued';
    case PROCESSING = 'processing';
    case SENT = 'sent';
    case DELIVERED = 'delivered';
    case FAILED = 'failed';
    case UNKNOWN = 'unknown';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::QUEUED => 'Dalam Antrean',
            self::PROCESSING => 'Sedang Diproses',
            self::SENT => 'Terkirim',
            self::DELIVERED => 'Diterima',
            self::FAILED => 'Gagal',
            self::UNKNOWN => 'Tidak Diketahui',
            self::CANCELLED => 'Dibatalkan',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::QUEUED => 'bg-slate-100 text-slate-700 border-slate-200',
            self::PROCESSING => 'bg-blue-50 text-blue-700 border-blue-200',
            self::SENT => 'bg-emerald-50 text-emerald-700 border-emerald-200',
            self::DELIVERED => 'bg-teal-50 text-teal-800 border-teal-200',
            self::FAILED => 'bg-rose-50 text-rose-700 border-rose-200',
            self::UNKNOWN => 'bg-amber-50 text-amber-700 border-amber-200',
            self::CANCELLED => 'bg-zinc-100 text-zinc-600 border-zinc-200',
        };
    }
}
