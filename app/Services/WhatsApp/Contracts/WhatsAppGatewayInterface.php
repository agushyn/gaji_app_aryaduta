<?php

namespace App\Services\WhatsApp\Contracts;

use App\Services\WhatsApp\DTO\WhatsAppCallbackResult;
use App\Services\WhatsApp\DTO\WhatsAppSendResult;
use App\Services\WhatsApp\DTO\WhatsAppStatusResult;
use Illuminate\Http\Request;

interface WhatsAppGatewayInterface
{
    /**
     * Get the identifier name of this gateway adapter.
     */
    public function getName(): string;

    /**
     * Send a document (PDF) to the recipient.
     *
     * @param  string  $recipient  Normalized phone number (E.164 without leading plus or standard ID format)
     * @param  string  $pdfContent  Raw binary string of the PDF file
     * @param  string  $filename  Name of the file with extension (e.g. Slip_Gaji_2026-09.pdf)
     * @param  string|null  $caption  Optional message caption to accompany the file
     * @param  string|null  $idempotencyKey  Unique transaction key to avoid duplication at the gateway
     */
    public function sendDocument(
        string $recipient,
        string $pdfContent,
        string $filename,
        ?string $caption = null,
        ?string $idempotencyKey = null
    ): WhatsAppSendResult;

    /**
     * Send a simple text message (used for connectivity test ping without payroll data).
     *
     * @param  string  $recipient  Normalized phone number
     * @param  string  $message  Text content
     */
    public function sendTextMessage(string $recipient, string $message): WhatsAppSendResult;

    /**
     * Query delivery status for an existing message from the provider if supported.
     */
    public function getMessageStatus(string $providerMessageId): WhatsAppStatusResult;

    /**
     * Verify whether the gateway has valid endpoint and credential configuration.
     */
    public function validateConfiguration(): bool;

    /**
     * Check if this gateway provider natively supports binary document attachment delivery.
     */
    public function supportsDocumentDelivery(): bool;

    /**
     * Parse incoming webhook payload into a normalized WhatsAppCallbackResult.
     */
    public function parseWebhookCallback(Request $request): ?WhatsAppCallbackResult;

    /**
     * Verify webhook signature or token for authenticity and integrity.
     */
    public function verifyWebhookSignature(Request $request): bool;
}
