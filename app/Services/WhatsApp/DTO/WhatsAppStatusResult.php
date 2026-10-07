<?php

namespace App\Services\WhatsApp\DTO;

use App\Enums\DeliveryStatus;

class WhatsAppStatusResult
{
    /**
     * @param  DeliveryStatus  $status  Normalized status
     * @param  string|null  $providerMessageId  Unique identifier from provider
     * @param  string|null  $errorCode  Error code if failed
     * @param  string|null  $errorMessage  Sanitized error message
     * @param  array<string, mixed>  $rawResponse  Sanitized raw response
     */
    public function __construct(
        public DeliveryStatus $status,
        public ?string $providerMessageId = null,
        public ?string $errorCode = null,
        public ?string $errorMessage = null,
        public array $rawResponse = []
    ) {}

    public static function create(
        DeliveryStatus $status,
        ?string $providerMessageId = null,
        array $rawResponse = []
    ): self {
        return new self(
            status: $status,
            providerMessageId: $providerMessageId,
            rawResponse: $rawResponse
        );
    }
}
