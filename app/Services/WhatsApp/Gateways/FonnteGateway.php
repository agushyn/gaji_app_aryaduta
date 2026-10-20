<?php

namespace App\Services\WhatsApp\Gateways;

use App\Enums\DeliveryStatus;
use App\Services\WhatsApp\DTO\WhatsAppCallbackResult;
use App\Services\WhatsApp\DTO\WhatsAppSendResult;
use App\Services\WhatsApp\DTO\WhatsAppStatusResult;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Throwable;

class FonnteGateway extends BaseWhatsAppGateway
{
    public function __construct(
        protected string $token,
        protected string $endpoint = 'https://api.fonnte.com/send',
        protected ?string $deviceSender = null,
        protected ?string $webhookSecret = null,
        protected int $timeoutSeconds = 25
    ) {}

    public function getName(): string
    {
        return 'fonnte';
    }

    public function validateConfiguration(): bool
    {
        return ! empty($this->token) && ! empty($this->endpoint);
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
        ?string $idempotencyKey = null,
        ?string $documentUrl = null
    ): WhatsAppSendResult {
        if (! $this->validateConfiguration()) {
            return WhatsAppSendResult::failure(
                'CONFIG_ERROR',
                'Kredensial atau endpoint WhatsApp Fonnte belum dikonfigurasi dengan benar.'
            );
        }

        $formattedTarget = $this->formatRecipientNumber($recipient);

        try {
            $response = Http::timeout($this->timeoutSeconds)
                ->withHeaders([
                    'Authorization' => $this->token,
                ])
                ->attach('file', $pdfContent, $filename)
                ->post($this->endpoint, [
                    'target' => $formattedTarget,
                    'message' => $caption ?? "Berikut adalah berkas resmi Slip Gaji Anda ({$filename}).",
                    'filename' => $filename,
                    'countryCode' => '62',
                ]);

            if ($response->failed()) {
                $status = $response->status();
                $body = $response->json() ?? [];
                $reason = $body['reason'] ?? $response->body() ?? 'HTTP error';

                return WhatsAppSendResult::failure(
                    "HTTP_{$status}",
                    $this->sanitizeError("Fonnte request failed: {$reason}")
                );
            }

            $data = $response->json();
            $isSuccess = (bool) ($data['status'] ?? false);

            if (! $isSuccess) {
                $reason = $data['reason'] ?? 'Permintaan ditolak oleh server Fonnte.';

                return WhatsAppSendResult::failure(
                    'FONNTE_REJECTED',
                    $this->sanitizeError($reason)
                );
            }

            // Extract message ID
            $rawId = $data['id'] ?? null;
            $messageId = is_array($rawId) ? ($rawId[0] ?? null) : $rawId;
            if (empty($messageId)) {
                $messageId = 'fonnte_'.uniqid();
            }

            // Normalisasi status ke SENT, bukan DELIVERED (DELIVERED hanya dari webhook DLR)
            return WhatsAppSendResult::success(
                providerMessageId: (string) $messageId,
                status: DeliveryStatus::SENT,
                rawResponse: [
                    'target' => $data['target'] ?? $formattedTarget,
                    'process' => $data['process'] ?? 'sent',
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
                'Kredensial atau endpoint WhatsApp Fonnte belum dikonfigurasi dengan benar.'
            );
        }

        $formattedTarget = $this->formatRecipientNumber($recipient);

        try {
            $response = Http::asForm()
                ->timeout($this->timeoutSeconds)
                ->withHeaders([
                    'Authorization' => $this->token,
                ])
                ->post($this->endpoint, [
                    'target' => $formattedTarget,
                    'message' => $message,
                    'countryCode' => '62',
                ]);

            if ($response->failed()) {
                return WhatsAppSendResult::failure(
                    "HTTP_{$response->status()}",
                    $this->sanitizeError($response->body())
                );
            }

            $data = $response->json();
            $isSuccess = (bool) ($data['status'] ?? false);

            if (! $isSuccess) {
                $reason = $data['reason'] ?? 'Permintaan pesan teks ditolak oleh server Fonnte.';

                return WhatsAppSendResult::failure('FONNTE_REJECTED', $this->sanitizeError($reason));
            }

            $rawId = $data['id'] ?? null;
            $messageId = is_array($rawId) ? ($rawId[0] ?? null) : $rawId;
            if (empty($messageId)) {
                $messageId = 'fonnte_txt_'.uniqid();
            }

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
        // Fonnte does not provide a separate synchronous status pull endpoint; relies on webhook callbacks
        return WhatsAppStatusResult::create(DeliveryStatus::SENT, $providerMessageId);
    }

    public function verifyWebhookSignature(Request $request): bool
    {
        // If a webhook secret is configured, require match in Authorization header or secret query parameter
        if (empty($this->webhookSecret)) {
            return true;
        }

        $authHeader = $request->header('Authorization')
            ?? $request->header('X-Webhook-Secret')
            ?? $request->header('X-Token')
            ?? $request->input('secret')
            ?? $request->input('token');

        return hash_equals($this->webhookSecret, (string) $authHeader);
    }

    public function parseWebhookCallback(Request $request): ?WhatsAppCallbackResult
    {
        $id = $request->input('id') ?? $request->input('message_id');
        if (empty($id)) {
            return null;
        }

        $rawStatus = strtolower((string) ($request->input('status') ?? ''));
        $target = $request->input('target') ?? $request->input('phone');
        $reason = $request->input('reason') ?? $request->input('message');

        // Normalize status
        $status = match ($rawStatus) {
            'delivered', 'read' => DeliveryStatus::DELIVERED,
            'sent', 'pending', 'process' => DeliveryStatus::SENT,
            'failed', 'undelivered', 'reject' => DeliveryStatus::FAILED,
            default => DeliveryStatus::UNKNOWN,
        };

        return new WhatsAppCallbackResult(
            providerMessageId: (string) $id,
            status: $status,
            recipient: is_string($target) ? $target : null,
            errorCode: $status === DeliveryStatus::FAILED ? 'WEBHOOK_FAILURE' : null,
            errorMessage: $status === DeliveryStatus::FAILED ? $this->sanitizeError((string) $reason) : null,
            rawPayload: [
                'status' => $rawStatus,
                'target' => $target,
            ]
        );
    }
}
