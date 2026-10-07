<?php

namespace App\Services\WhatsApp\Gateways;

use App\Enums\DeliveryStatus;
use App\Services\WhatsApp\DTO\WhatsAppCallbackResult;
use App\Services\WhatsApp\DTO\WhatsAppSendResult;
use App\Services\WhatsApp\DTO\WhatsAppStatusResult;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Throwable;

class GenericWebhookGateway extends BaseWhatsAppGateway
{
    public function __construct(
        protected string $endpoint,
        protected ?string $secretToken = null,
        protected ?string $senderIdentifier = null,
        protected ?string $webhookSecret = null,
        protected int $timeoutSeconds = 25
    ) {}

    public function getName(): string
    {
        return 'generic';
    }

    public function validateConfiguration(): bool
    {
        return ! empty($this->endpoint) && filter_var($this->endpoint, FILTER_VALIDATE_URL) !== false;
    }

    public function supportsDocumentDelivery(): bool
    {
        return true;
    }

    public function sendDocument(
        string $recipient,
        string $pdfContent,
        string $filename,
        ?string $caption = null,
        ?string $idempotencyKey = null
    ): WhatsAppSendResult {
        if (! $this->validateConfiguration()) {
            return WhatsAppSendResult::failure(
                'CONFIG_ERROR',
                'Endpoint Generic WhatsApp API belum diisi atau tidak valid.'
            );
        }

        $formattedTarget = $this->formatRecipientNumber($recipient);

        try {
            $headers = ['Content-Type' => 'application/json'];
            if (! empty($this->secretToken)) {
                $headers['Authorization'] = 'Bearer '.$this->secretToken;
                $headers['X-API-Key'] = $this->secretToken;
            }

            $payload = [
                'type' => 'document',
                'recipient' => $formattedTarget,
                'sender' => $this->senderIdentifier,
                'caption' => $caption ?? "Dokumen Slip Gaji ({$filename})",
                'filename' => $filename,
                'file_base64' => base64_encode($pdfContent),
                'idempotency_key' => $idempotencyKey,
            ];

            $response = Http::timeout($this->timeoutSeconds)
                ->withHeaders($headers)
                ->post($this->endpoint, $payload);

            if ($response->failed()) {
                return WhatsAppSendResult::failure(
                    "HTTP_{$response->status()}",
                    $this->sanitizeError($response->body())
                );
            }

            $data = $response->json() ?? [];
            $messageId = $data['message_id'] ?? $data['id'] ?? $data['data']['id'] ?? ('gw_'.uniqid());

            return WhatsAppSendResult::success(
                providerMessageId: (string) $messageId,
                status: DeliveryStatus::SENT,
                rawResponse: [
                    'recipient' => $formattedTarget,
                    'status' => $data['status'] ?? 'sent',
                ]
            );
        } catch (Throwable $e) {
            return WhatsAppSendResult::failure(
                'CONNECTION_ERROR',
                $this->sanitizeError($e->getMessage())
            );
        }
    }

    public function sendTextMessage(string $recipient, string $message): WhatsAppSendResult
    {
        if (! $this->validateConfiguration()) {
            return WhatsAppSendResult::failure(
                'CONFIG_ERROR',
                'Endpoint Generic WhatsApp API belum diisi atau tidak valid.'
            );
        }

        $formattedTarget = $this->formatRecipientNumber($recipient);

        try {
            $headers = ['Content-Type' => 'application/json'];
            if (! empty($this->secretToken)) {
                $headers['Authorization'] = 'Bearer '.$this->secretToken;
                $headers['X-API-Key'] = $this->secretToken;
            }

            $response = Http::timeout($this->timeoutSeconds)
                ->withHeaders($headers)
                ->post($this->endpoint, [
                    'type' => 'text',
                    'recipient' => $formattedTarget,
                    'sender' => $this->senderIdentifier,
                    'message' => $message,
                ]);

            if ($response->failed()) {
                return WhatsAppSendResult::failure(
                    "HTTP_{$response->status()}",
                    $this->sanitizeError($response->body())
                );
            }

            $data = $response->json() ?? [];
            $messageId = $data['message_id'] ?? $data['id'] ?? ('gw_txt_'.uniqid());

            return WhatsAppSendResult::success(
                providerMessageId: (string) $messageId,
                status: DeliveryStatus::SENT
            );
        } catch (Throwable $e) {
            return WhatsAppSendResult::failure(
                'CONNECTION_ERROR',
                $this->sanitizeError($e->getMessage())
            );
        }
    }

    public function getMessageStatus(string $providerMessageId): WhatsAppStatusResult
    {
        return WhatsAppStatusResult::create(DeliveryStatus::SENT, $providerMessageId);
    }

    public function verifyWebhookSignature(Request $request): bool
    {
        if (empty($this->webhookSecret)) {
            return true;
        }

        $incoming = $request->header('X-Webhook-Secret')
            ?? $request->header('Authorization')
            ?? $request->header('X-Token')
            ?? $request->input('secret')
            ?? $request->input('token');

        return hash_equals($this->webhookSecret, (string) $incoming);
    }

    public function parseWebhookCallback(Request $request): ?WhatsAppCallbackResult
    {
        $id = $request->input('message_id') ?? $request->input('id');
        if (empty($id)) {
            return null;
        }

        $rawStatus = strtolower((string) ($request->input('status') ?? ''));
        $target = $request->input('recipient') ?? $request->input('target') ?? $request->input('phone');
        $error = $request->input('error') ?? $request->input('reason');

        $status = match ($rawStatus) {
            'delivered', 'read' => DeliveryStatus::DELIVERED,
            'sent', 'queued', 'pending' => DeliveryStatus::SENT,
            'failed', 'rejected', 'undelivered' => DeliveryStatus::FAILED,
            default => DeliveryStatus::UNKNOWN,
        };

        return new WhatsAppCallbackResult(
            providerMessageId: (string) $id,
            status: $status,
            recipient: is_string($target) ? $target : null,
            errorCode: $status === DeliveryStatus::FAILED ? 'GATEWAY_ERROR' : null,
            errorMessage: $status === DeliveryStatus::FAILED ? $this->sanitizeError((string) $error) : null,
            rawPayload: [
                'status' => $rawStatus,
                'recipient' => $target,
            ]
        );
    }
}
