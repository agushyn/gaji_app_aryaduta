<?php

namespace App\Services\WhatsApp\DTO;

use App\Enums\DeliveryStatus;

class WhatsAppCallbackResult
{
    /**
     * @param  string  $providerMessageId  Unique message identifier from provider
     * @param  DeliveryStatus  $status  Normalized status (e.g. DELIVERED, SENT, FAILED)
     * @param  string|null  $recipient  Phone number of the recipient
     * @param  string|null  $errorCode  Error code reported by provider
     * @param  string|null  $errorMessage  Sanitized error message reported by provider
     * @param  array<string, mixed>  $rawPayload  Webhook payload
     */
    public function __construct(
        public string $providerMessageId,
        public DeliveryStatus $status,
        public ?string $recipient = null,
        public ?string $errorCode = null,
        public ?string $errorMessage = null,
        public array $rawPayload = []
    ) {}
}
