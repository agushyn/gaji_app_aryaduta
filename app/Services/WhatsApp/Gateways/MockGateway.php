<?php

namespace App\Services\WhatsApp\Gateways;

use App\Enums\DeliveryStatus;
use App\Services\WhatsApp\DTO\WhatsAppCallbackResult;
use App\Services\WhatsApp\DTO\WhatsAppSendResult;
use App\Services\WhatsApp\DTO\WhatsAppStatusResult;
use Illuminate\Http\Request;

class MockGateway extends BaseWhatsAppGateway
{
    /**
     * Stored messages in memory for test assertions.
     *
     * @var array<int, array<string, mixed>>
     */
    public static array $sentMessages = [];

    public function __construct(
        protected bool $shouldFail = false,
        protected ?string $failureReason = null,
        protected bool $supportsDocument = true
    ) {}

    public function getName(): string
    {
        return 'mock';
    }

    public static function reset(): void
    {
        self::$sentMessages = [];
    }

    public function validateConfiguration(): bool
    {
        return true;
    }

    public function supportsDocumentDelivery(): bool
    {
        return $this->supportsDocument;
    }

    public function sendDocument(
        string $recipient,
        string $pdfContent,
        string $filename,
        ?string $caption = null,
        ?string $idempotencyKey = null,
        ?string $documentUrl = null
    ): WhatsAppSendResult {
        if ($this->shouldFail) {
            return WhatsAppSendResult::failure(
                'MOCK_SIMULATED_FAILURE',
                $this->failureReason ?? 'Simulasi kegagalan pengiriman WhatsApp'
            );
        }

        if (! $this->supportsDocument) {
            return WhatsAppSendResult::failure(
                'DOCUMENT_NOT_SUPPORTED',
                'Gateway ini tidak mendukung pengiriman berkas dokumen PDF.'
            );
        }

        $formattedTarget = $this->formatRecipientNumber($recipient);
        $messageId = 'mock_msg_'.uniqid();

        self::$sentMessages[] = [
            'type' => 'document',
            'recipient' => $formattedTarget,
            'filename' => $filename,
            'caption' => $caption,
            'content_size' => strlen($pdfContent),
            'idempotency_key' => $idempotencyKey,
            'message_id' => $messageId,
            'timestamp' => now()->toISOString(),
        ];

        // Normalisasi status ke SENT. Mock tidak mengklaim DELIVERED tanpa bukti DLR provider!
        return WhatsAppSendResult::success(
            providerMessageId: $messageId,
            status: DeliveryStatus::SENT,
            rawResponse: ['mock' => true, 'target' => $formattedTarget]
        );
    }

    public function sendTextMessage(string $recipient, string $message): WhatsAppSendResult
    {
        if ($this->shouldFail) {
            return WhatsAppSendResult::failure(
                'MOCK_SIMULATED_FAILURE',
                $this->failureReason ?? 'Simulasi kegagalan pengiriman teks WhatsApp'
            );
        }

        $formattedTarget = $this->formatRecipientNumber($recipient);
        $messageId = 'mock_txt_'.uniqid();

        self::$sentMessages[] = [
            'type' => 'text',
            'recipient' => $formattedTarget,
            'message' => $message,
            'message_id' => $messageId,
            'timestamp' => now()->toISOString(),
        ];

        return WhatsAppSendResult::success(
            providerMessageId: $messageId,
            status: DeliveryStatus::SENT
        );
    }

    public function getMessageStatus(string $providerMessageId): WhatsAppStatusResult
    {
        return WhatsAppStatusResult::create(DeliveryStatus::SENT, $providerMessageId);
    }

    public function verifyWebhookSignature(Request $request): bool
    {
        return true;
    }

    public function parseWebhookCallback(Request $request): ?WhatsAppCallbackResult
    {
        $id = $request->input('message_id') ?? $request->input('id');
        if (empty($id)) {
            return null;
        }

        $rawStatus = strtolower((string) ($request->input('status') ?? ''));
        $target = $request->input('recipient') ?? $request->input('target');

        $status = match ($rawStatus) {
            'delivered' => DeliveryStatus::DELIVERED,
            'failed' => DeliveryStatus::FAILED,
            default => DeliveryStatus::SENT,
        };

        return new WhatsAppCallbackResult(
            providerMessageId: (string) $id,
            status: $status,
            recipient: is_string($target) ? $target : null,
            errorCode: $status === DeliveryStatus::FAILED ? 'MOCK_ERROR' : null,
            errorMessage: $status === DeliveryStatus::FAILED ? 'Mock failure' : null
        );
    }
}
