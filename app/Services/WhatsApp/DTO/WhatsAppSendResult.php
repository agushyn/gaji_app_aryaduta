<?php

namespace App\Services\WhatsApp\DTO;

use App\Enums\DeliveryStatus;

class WhatsAppSendResult
{
    /**
     * @param  bool  $success  Whether the request was accepted by the gateway
     * @param  DeliveryStatus  $status  Normalized delivery status (e.g. SENT, QUEUED, FAILED)
     * @param  string|null  $providerMessageId  Unique message identifier returned by the provider
     * @param  string|null  $errorCode  Machine-readable error code if failed
     * @param  string|null  $errorMessage  Sanitized human-readable error description
     * @param  array<string, mixed>  $rawResponse  Sanitized gateway response payload
     */
    public function __construct(
        public bool $success,
        public DeliveryStatus $status,
        public ?string $providerMessageId = null,
        public ?string $errorCode = null,
        public ?string $errorMessage = null,
        public array $rawResponse = []
    ) {}

    public static function success(
        string $providerMessageId,
        DeliveryStatus $status = DeliveryStatus::SENT,
        array $rawResponse = []
    ): self {
        return new self(
            success: true,
            status: $status,
            providerMessageId: $providerMessageId,
            rawResponse: $rawResponse
        );
    }

    public static function failure(
        string $errorCode,
        string $errorMessage,
        array $rawResponse = []
    ): self {
        return new self(
            success: false,
            status: DeliveryStatus::FAILED,
            errorCode: $errorCode,
            errorMessage: $errorMessage,
            rawResponse: $rawResponse
        );
    }
}
