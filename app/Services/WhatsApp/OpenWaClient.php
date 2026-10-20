<?php

namespace App\Services\WhatsApp;

use App\Enums\DeliveryStatus;
use App\Services\WhatsApp\DTO\WhatsAppSendResult;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

class OpenWaClient
{
    public function __construct(
        protected string $baseUrl,
        protected string $apiKey,
        protected string $sessionId = 'default',
        protected int $connectionTimeout = 10,
        protected int $requestTimeout = 30
    ) {
        $this->baseUrl = rtrim(trim($baseUrl), '/');
        $this->sessionId = trim($sessionId) !== '' ? trim($sessionId) : 'default';
    }

    /**
     * Get base URL of the OpenWA Gateway.
     */
    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    /**
     * Get Session ID.
     */
    public function getSessionId(): string
    {
        return $this->sessionId;
    }

    /**
     * Check API connectivity and session ready state with the OpenWA Gateway.
     * Distinguishes between API server reachability and WhatsApp session readiness.
     *
     * @return array{success: bool, api_connected: bool, session_status: string, is_ready: bool, session_id: string, message: string}
     */
    public function checkSessionStatus(): array
    {
        if (empty($this->baseUrl)) {
            return [
                'success' => false,
                'api_connected' => false,
                'session_status' => 'UNCONFIGURED',
                'is_ready' => false,
                'session_id' => $this->sessionId,
                'message' => 'URL Endpoint API OpenWA belum dikonfigurasi.',
            ];
        }

        $statusUrl = "{$this->baseUrl}/sessions/{$this->sessionId}/status";

        try {
            $response = $this->client()
                ->connectTimeout($this->connectionTimeout)
                ->timeout($this->requestTimeout)
                ->get($statusUrl);

            // Handle 404: Session not created/found
            if ($response->status() === 404) {
                // Fallback attempt: check /sessions list to verify API Key validity
                $sessionsResponse = $this->client()
                    ->connectTimeout($this->connectionTimeout)
                    ->timeout($this->requestTimeout)
                    ->get("{$this->baseUrl}/sessions");

                if ($sessionsResponse->successful()) {
                    return [
                        'success' => false,
                        'api_connected' => true,
                        'session_status' => 'NOT_FOUND',
                        'is_ready' => false,
                        'session_id' => $this->sessionId,
                        'message' => "API Gateway terhubung, namun Session ID '{$this->sessionId}' belum dibuat di OpenWA.",
                    ];
                }

                return [
                    'success' => false,
                    'api_connected' => true,
                    'session_status' => 'NOT_FOUND',
                    'is_ready' => false,
                    'session_id' => $this->sessionId,
                    'message' => "Session ID '{$this->sessionId}' tidak ditemukan pada gateway OpenWA (HTTP 404).",
                ];
            }

            // Handle 401 / 403: API Key Invalid
            if ($response->status() === 401 || $response->status() === 403) {
                return [
                    'success' => false,
                    'api_connected' => false,
                    'session_status' => 'UNAUTHORIZED',
                    'is_ready' => false,
                    'session_id' => $this->sessionId,
                    'message' => 'Koneksi ditolak: API Key OpenWA tidak valid atau otorisasi gagal (HTTP '.$response->status().').',
                ];
            }

            if ($response->failed()) {
                return [
                    'success' => false,
                    'api_connected' => false,
                    'session_status' => 'ERROR',
                    'is_ready' => false,
                    'session_id' => $this->sessionId,
                    'message' => "Gateway OpenWA mengembalikan status galat: HTTP {$response->status()}.",
                ];
            }

            $json = $response->json() ?? [];
            $rawStatus = $json['status'] ?? ($json['state'] ?? ($json['data']['status'] ?? ($json['data']['state'] ?? 'CONNECTED')));
            $statusUpper = strtoupper((string) $rawStatus);

            // OpenWA ready states: CONNECTED, READY, NORMAL, OPEN, PAIRING_READY
            $isReady = in_array($statusUpper, ['CONNECTED', 'READY', 'NORMAL', 'OPEN', 'PAIRING_READY'], true);

            $message = match ($statusUpper) {
                'CONNECTED', 'READY', 'NORMAL', 'OPEN' => "Koneksi API dan Session OpenWA ({$this->sessionId}) berhasil terverifikasi. Siap mengirim pesan.",
                'PAIRING', 'QR', 'UNPAIRED' => "API terhubung, namun session '{$this->sessionId}' belum login WhatsApp (status: {$statusUpper}). Silakan scan QR code pada gateway.",
                'STARTING' => "API terhubung, session '{$this->sessionId}' sedang dalam proses inisialisasi browser (STARTING).",
                'DISCONNECTED' => "API terhubung, namun sesi WhatsApp '{$this->sessionId}' terputus (DISCONNECTED).",
                'TIMEOUT' => "Sesi WhatsApp '{$this->sessionId}' mengalami waktu habis (TIMEOUT).",
                default => "API terhubung dengan status session: {$statusUpper}.",
            };

            return [
                'success' => true,
                'api_connected' => true,
                'session_status' => $statusUpper,
                'is_ready' => $isReady,
                'session_id' => $this->sessionId,
                'message' => $message,
            ];
        } catch (ConnectionException $e) {
            return [
                'success' => false,
                'api_connected' => false,
                'session_status' => 'UNREACHABLE',
                'is_ready' => false,
                'session_id' => $this->sessionId,
                'message' => "Tidak dapat menghubungi server OpenWA di {$this->baseUrl} (Connection Timeout / Network Unreachable).",
            ];
        } catch (Throwable $e) {
            return [
                'success' => false,
                'api_connected' => false,
                'session_status' => 'ERROR',
                'is_ready' => false,
                'session_id' => $this->sessionId,
                'message' => 'Kesalahan saat memeriksa status OpenWA: '.$this->sanitizeError($e->getMessage()),
            ];
        }
    }

    /**
     * Send a plain text message via OpenWA REST API.
     *
     * Endpoint: POST /sessions/{sessionId}/messages/send-text
     */
    public function sendText(string $chatId, string $text, ?string $idempotencyKey = null): WhatsAppSendResult
    {
        $url = "{$this->baseUrl}/sessions/{$this->sessionId}/messages/send-text";

        $payload = [
            'chatId' => $chatId,
            'text' => $text,
        ];

        try {
            $request = $this->client();

            if (! empty($idempotencyKey)) {
                $request = $request->withHeaders(['X-Idempotency-Key' => $idempotencyKey]);
            }

            $response = $request
                ->connectTimeout($this->connectionTimeout)
                ->timeout($this->requestTimeout)
                ->post($url, $payload);

            return $this->parseResponse($response, 'send-text');
        } catch (Throwable $e) {
            return WhatsAppSendResult::failure(
                'OPENWA_HTTP_EXCEPTION',
                $this->sanitizeError("Gagal menghubungi endpoint OpenWA send-text: {$e->getMessage()}")
            );
        }
    }

    /**
     * Send a document (PDF) message via OpenWA REST API.
     * Supports both Base64 Data URI mode and Signed URL mode.
     *
     * Endpoint: POST /sessions/{sessionId}/messages/send-document
     */
    public function sendDocument(
        string $chatId,
        string $filename,
        string $pdfContent,
        ?string $caption = null,
        ?string $documentUrl = null,
        string $mode = 'base64',
        ?string $idempotencyKey = null
    ): WhatsAppSendResult {
        $url = "{$this->baseUrl}/sessions/{$this->sessionId}/messages/send-document";

        $payload = [
            'chatId' => $chatId,
            'filename' => $filename,
            'mimetype' => 'application/pdf',
        ];

        if ($mode === 'url' && ! empty($documentUrl)) {
            // URL mode: OpenWA fetches the document from the temporary signed route
            $payload['url'] = $documentUrl;
        } else {
            // Base64 mode: self-contained binary payload
            $base64 = base64_encode($pdfContent);
            $dataUri = 'data:application/pdf;base64,'.$base64;
            $payload['data'] = $dataUri;
            $payload['file'] = $dataUri;
        }

        if (! empty($caption)) {
            $payload['caption'] = $caption;
        }

        try {
            $request = $this->client();

            if (! empty($idempotencyKey)) {
                $request = $request->withHeaders(['X-Idempotency-Key' => $idempotencyKey]);
            }

            $response = $request
                ->connectTimeout($this->connectionTimeout)
                ->timeout($this->requestTimeout)
                ->post($url, $payload);

            return $this->parseResponse($response, 'send-document');
        } catch (Throwable $e) {
            return WhatsAppSendResult::failure(
                'OPENWA_HTTP_EXCEPTION',
                $this->sanitizeError("Gagal menghubungi endpoint OpenWA send-document: {$e->getMessage()}")
            );
        }
    }

    /**
     * Configure base HTTP client with official OpenWA REST API headers.
     */
    protected function client()
    {
        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];

        if (! empty($this->apiKey)) {
            $headers['X-API-Key'] = $this->apiKey;
        }

        return Http::withHeaders($headers);
    }

    /**
     * Parse and map OpenWA REST API responses to standardized WhatsAppSendResult.
     */
    protected function parseResponse(Response $response, string $action): WhatsAppSendResult
    {
        $status = $response->status();

        if ($response->failed()) {
            $json = $response->json() ?? [];
            $errorMessage = $json['message'] ?? ($json['error'] ?? ($json['reason'] ?? $response->body()));
            $errorCode = match ($status) {
                401, 403 => 'AUTH_FAILED',
                404 => 'NOT_FOUND',
                default => "HTTP_{$status}",
            };

            return WhatsAppSendResult::failure(
                $errorCode,
                $this->sanitizeError("OpenWA {$action} gagal (HTTP {$status}): {$errorMessage}"),
                $json
            );
        }

        $data = $response->json() ?? [];

        // Check if OpenWA returned an error payload with HTTP 200
        if (isset($data['success']) && $data['success'] === false) {
            $reason = $data['message'] ?? ($data['error'] ?? ($data['reason'] ?? 'Permintaan ditolak oleh OpenWA.'));

            return WhatsAppSendResult::failure(
                'OPENWA_REJECTED',
                $this->sanitizeError($reason),
                $data
            );
        }

        // Extract message ID from various OpenWA response shapes
        $rawId = $data['response']['id'] ?? ($data['id'] ?? ($data['messageId'] ?? ($data['data']['id'] ?? ($data['response'] ?? null))));

        if (is_array($rawId)) {
            $rawId = $rawId['_serialized'] ?? ($rawId['id'] ?? reset($rawId));
        }

        $messageId = ! empty($rawId) && is_string($rawId) ? $rawId : 'openwa_'.uniqid();

        return WhatsAppSendResult::success(
            providerMessageId: (string) $messageId,
            status: DeliveryStatus::SENT,
            rawResponse: $data
        );
    }

    /**
     * Sanitize error message to ensure API Keys and sensitive tokens are never exposed.
     */
    protected function sanitizeError(string $rawMessage): string
    {
        $sanitized = preg_replace('/(bearer|token|secret|password|key|auth|api-key)[:=\s]+[^\s;&",]+/i', '$1=***', $rawMessage);
        $sanitized = preg_replace('/[a-zA-Z0-9_\-]{32,}/', '***', $sanitized ?? '');
        $sanitized = preg_replace('/data:application\/pdf;base64,[A-Za-z0-9+\/=]+/', '[BASE64_PDF_OMITTED]', $sanitized ?? '');

        return mb_substr(trim($sanitized ?? ''), 0, 500);
    }
}
