<?php

namespace App\Enums;

enum PayComponentType: string
{
    case EARNING = 'earning';
    case DEDUCTION = 'deduction';

    public function label(): string
    {
        return match ($this) {
            self::EARNING => 'Penghasilan / Tunjangan',
            self::DEDUCTION => 'Potongan',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::EARNING => 'Pendapatan',
            self::DEDUCTION => 'Potongan',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::EARNING => 'bg-emerald-50 text-emerald-700 border-emerald-200',
            self::DEDUCTION => 'bg-rose-50 text-rose-700 border-rose-200',
        };
    }
}
