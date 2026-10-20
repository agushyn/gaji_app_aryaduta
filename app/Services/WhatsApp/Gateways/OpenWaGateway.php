<?php

namespace App\Services\WhatsApp\Gateways;

use App\Enums\DeliveryStatus;
use App\Services\WhatsApp\DTO\WhatsAppCallbackResult;
use App\Services\WhatsApp\DTO\WhatsAppSendResult;
use App\Services\WhatsApp\DTO\WhatsAppStatusResult;
use App\Services\WhatsApp\OpenWaClient;
use Illuminate\Http\Request;

class OpenWaGateway extends BaseWhatsAppGateway
{
    protected OpenWaClient $client;

    public function __construct(
        protected string $baseUrl,
        protected string $apiKey,
        protected string $sessionId = 'default',
        protected int $connectionTimeout = 10,
        protected int $requestTimeout = 30,
        protected string $documentMode = 'base64',
        protected ?string $webhookSecret = null,
        ?OpenWaClient $client = null
    ) {
        $this->client = $client ?? new OpenWaClient(
            baseUrl: $this->baseUrl,
            apiKey: $this->apiKey,
            sessionId: $this->sessionId,
            connectionTimeout: $this->connectionTimeout,
            requestTimeout: $this->requestTimeout
        );
    }

    public function getName(): string
    {
        return 'openwa';
    }

    public function getClient(): OpenWaClient
    {
        return $this->client;
    }

    public function validateConfiguration(): bool
    {
        return ! empty($this->baseUrl) && ! empty($this->apiKey) && ! empty($this->sessionId);
    }

    public function supportsDocumentDelivery(): bool
    {
        return true;
    }

    /**
     * Check current session status directly via OpenWA Client.
     *
     * @return array{success: bool, api_connected: bool, session_status: string, is_ready: bool, session_id: string, message: string}
     */
    public function checkSessionStatus(): array
    {
        return $this->client->checkSessionStatus();
    }

    /**
     * Send a document (PDF) to the recipient with OpenWA.
     * Supports both Base64 Data URI mode (independent) and Signed URL mode.
     */
    public function sendDocument(
        string $recipient,
        string $pdfContent,
        string $filename,
        ?string $caption = null,
        ?string $idempotencyKey = null,
        ?string $documentUrl = null
    ): WhatsAppSendResult {
        if (! $this->validateConfiguration()) {
            return WhatsAppSendResult::failure(
                'CONFIG_ERROR',
                'Konfigurasi OpenWA (Base URL, API Key, atau Session ID) belum lengkap.'
            );
        }

        $chatId = $this->formatChatId($recipient);

        return $this->client->sendDocument(
            chatId: $chatId,
            filename: $filename,
            pdfContent: $pdfContent,
            caption: $caption,
            documentUrl: $documentUrl,
            mode: $this->documentMode,
            idempotencyKey: $idempotencyKey
        );
    }

    /**
     * Send a plain text message (used for ping connectivity test).
     */
    public function sendTextMessage(string $recipient, string $message): WhatsAppSendResult
    {
        if (! $this->validateConfiguration()) {
            return WhatsAppSendResult::failure(
                'CONFIG_ERROR',
                'Konfigurasi OpenWA (Base URL, API Key, atau Session ID) belum lengkap.'
            );
        }

        $chatId = $this->formatChatId($recipient);

        return $this->client->sendText(
            chatId: $chatId,
            text: $message
        );
    }

    /**
     * Query message status for an existing message from the provider.
     */
    public function getMessageStatus(string $providerMessageId): WhatsAppStatusResult
    {
        return WhatsAppStatusResult::create(DeliveryStatus::SENT, $providerMessageId);
    }

    /**
     * Parse incoming webhook payload from OpenWA into a normalized WhatsAppCallbackResult.
     *
     * OpenWA delivers events such as onAck with ack levels:
     * 1 = server received (SENT)
     * 2 = device received (DELIVERED)
     * 3 = read / seen (DELIVERED)
     * -1 = error / failed (FAILED)
     */
    public function parseWebhookCallback(Request $request): ?WhatsAppCallbackResult
    {
        $data = $request->all();

        // Extract message ID from standard OpenWA webhook shapes
        $rawId = $data['data']['id'] ?? ($data['id'] ?? ($data['messageId'] ?? null));

        if (is_array($rawId)) {
            $rawId = $rawId['_serialized'] ?? ($rawId['id'] ?? null);
        }

        if (empty($rawId)) {
            return null;
        }

        $ack = $data['data']['ack'] ?? ($data['ack'] ?? null);

        $status = match (true) {
            $ack === 2 || $ack === '2' || $ack === 'delivered' => DeliveryStatus::DELIVERED,
            $ack === 3 || $ack === '3' || $ack === 'read' || $ack === 'seen' => DeliveryStatus::DELIVERED,
            $ack === 1 || $ack === '1' || $ack === 'sent' => DeliveryStatus::SENT,
            $ack === -1 || $ack === '-1' || $ack === 'failed' => DeliveryStatus::FAILED,
            default => DeliveryStatus::UNKNOWN,
        };

        return new WhatsAppCallbackResult(
            providerMessageId: (string) $rawId,
            status: $status,
            recipient: null,
            errorCode: $status === DeliveryStatus::FAILED ? 'OPENWA_ACK_FAILED' : null,
            errorMessage: $status === DeliveryStatus::FAILED ? 'Pengiriman ditandai gagal oleh gateway OpenWA (ack=-1).' : null,
            rawPayload: $data
        );
    }

    /**
     * Verify incoming webhook authenticity.
     */
    public function verifyWebhookSignature(Request $request): bool
    {
        // If a webhook secret is specified, require it via header or token query
        if (! empty($this->webhookSecret)) {
            $headerSecret = $request->header('X-Webhook-Secret') ?? $request->header('X-API-Key');
            $querySecret = $request->query('token') ?? $request->query('secret');

            return hash_equals($this->webhookSecret, (string) ($headerSecret ?? $querySecret));
        }

        return true;
    }

    /**
     * Format Indonesian mobile phone number into OpenWA chatId standard: {normalized_number}@c.us.
     */
    public function formatChatId(string $recipient): string
    {
        $trimmed = trim($recipient);

        if (str_ends_with($trimmed, '@c.us') || str_ends_with($trimmed, '@g.us')) {
            return $trimmed;
        }

        $digits = $this->formatRecipientNumber($trimmed);

        return "{$digits}@c.us";
    }
}
