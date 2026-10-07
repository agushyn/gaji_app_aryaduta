<?php

namespace App\Enums;

enum DeliveryChannel: string
{
    case EMAIL = 'email';
    case WHATSAPP = 'whatsapp';

    public function label(): string
    {
        return match ($this) {
            self::EMAIL => 'Email',
            self::WHATSAPP => 'WhatsApp',
        };
    }
}
