<?php

namespace App\Services\WhatsApp;

use App\Enums\DeliveryStatus;
use App\Services\WhatsApp\DTO\WhatsAppSendResult;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class OpenWaClient
{
    protected string $baseUrl;

    protected string $rawBaseUrl;

    public function __construct(
        string $baseUrl,
        protected string $apiKey,
        protected string $sessionId = 'default',
        protected int $connectionTimeout = 10,
        protected int $requestTimeout = 30
    ) {
        $this->rawBaseUrl = trim($baseUrl);
        $this->baseUrl = self::normalizeBaseUrl($baseUrl);
        $this->sessionId = trim($sessionId) !== '' ? trim($sessionId) : 'default';
    }

    /**
     * Normalize any user-provided Base URL to guarantee a valid absolute URL ending in /api.
     * Handles missing schemes, trailing slashes, missing /api prefix, and preserves custom sub-paths.
     *
     * Examples:
     * - http://100.112.241.87:2785       => http://100.112.241.87:2785/api
     * - http://100.112.241.87:2785/      => http://100.112.241.87:2785/api
     * - http://100.112.241.87:2785/api   => http://100.112.241.87:2785/api
     * - http://100.112.241.87:2785/api/  => http://100.112.241.87:2785/api
     * - https://wa.example.com           => https://wa.example.com/api
     * - 100.112.241.87:2785              => http://100.112.241.87:2785/api
     */
    public static function normalizeBaseUrl(?string $url): string
    {
        $raw = trim((string) $url);
        if ($raw === '') {
            return '';
        }

        // Add scheme if missing (only if no RFC 3986 scheme is present)
        if (! preg_match('#^[a-zA-Z][a-zA-Z0-9+.-]*://#', $raw)) {
            $raw = 'http://'.$raw;
        }

        $parts = parse_url($raw);
        if ($parts === false || empty($parts['host'])) {
            return '';
        }

        $scheme = strtolower($parts['scheme'] ?? 'http');
        $host = $parts['host'];
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';
        $path = trim($parts['path'] ?? '', '/');

        // Check if path already ends with 'api'
        if ($path === '' || $path === '/') {
            $path = 'api';
        } elseif (! preg_match('#(^|/)api$#i', $path)) {
            $path = $path.'/api';
        }

        return "{$scheme}://{$host}{$port}/{$path}";
    }

    /**
     * Validate that the Base URL has a valid scheme and hostname.
     * Returns null if valid, or a descriptive error message if invalid.
     */
    public function validateBaseUrl(): ?string
    {
        if (empty($this->rawBaseUrl) || empty($this->baseUrl)) {
            return 'URL Endpoint API OpenWA belum dikonfigurasi (kosong). Silakan masukkan Base URL gateway OpenWA (contoh: http://localhost:3000/api) pada menu Pengaturan WhatsApp.';
        }

        $parts = parse_url($this->baseUrl);
        if ($parts === false || empty($parts['host'])) {
            return "URL Endpoint API OpenWA ('{$this->rawBaseUrl}') tidak valid atau tidak memiliki hostname/IP yang sah.";
        }

        if (! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)) {
            return "URL Endpoint API OpenWA ('{$this->rawBaseUrl}') harus menggunakan protokol http:// atau https://.";
        }

        return null;
    }

    /**
     * Get the normalized base URL ending with /api.
     */
    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    /**
     * Get the raw base URL as originally entered.
     */
    public function getRawBaseUrl(): string
    {
        return $this->rawBaseUrl;
    }

    /**
     * Get Session ID.
     */
    public function getSessionId(): string
    {
        return $this->sessionId;
    }

    /**
     * Build an explicit final URL by appending a sub-path to the normalized base URL.
     */
    public function buildUrl(string $path): string
    {
        return rtrim($this->baseUrl, '/').'/'.ltrim($path, '/');
    }

    /**
     * Explicit URL builder for session status:
     * {baseUrl}/sessions/{sessionId}/status
     */
    public function buildSessionStatusUrl(): string
    {
        return $this->buildUrl("sessions/{$this->sessionId}/status");
    }

    /**
     * Explicit URL builder for sending plain text:
     * {baseUrl}/sessions/{sessionId}/messages/send-text
     */
    public function buildSendTextUrl(): string
    {
        return $this->buildUrl("sessions/{$this->sessionId}/messages/send-text");
    }

    /**
     * Explicit URL builder for sending document PDF:
     * {baseUrl}/sessions/{sessionId}/messages/send-document
     */
    public function buildSendDocumentUrl(): string
    {
        return $this->buildUrl("sessions/{$this->sessionId}/messages/send-document");
    }

    /**
     * Explicit URL builder for sessions list:
     * {baseUrl}/sessions
     */
    public function buildSessionsListUrl(): string
    {
        return $this->buildUrl('sessions');
    }

    /**
     * Test gateway HTTP connectivity without requiring a recipient or sending any message.
     * Verifies hostname reachability, port availability, and API Key validity.
     *
     * @return array{success: bool, api_connected: bool, session_status: string, is_ready: bool, session_id: string, message: string}
     */
    public function testConnectivity(): array
    {
        $validationError = $this->validateBaseUrl();
        if ($validationError !== null) {
            return [
                'success' => false,
                'api_connected' => false,
                'session_status' => 'INVALID_CONFIG',
                'is_ready' => false,
                'session_id' => $this->sessionId,
                'message' => $validationError,
            ];
        }

        $statusUrl = $this->buildSessionStatusUrl();

        try {
            $response = $this->client()
                ->connectTimeout($this->connectionTimeout)
                ->timeout($this->requestTimeout)
                ->get($statusUrl);

            $this->logDiagnostic('test_connectivity', $statusUrl, $response);

            // Handle 404: Session not created/found
            if ($response->status() === 404) {
                // Check if API endpoint is reachable by querying /sessions
                $sessionsUrl = $this->buildSessionsListUrl();
                $sessionsResponse = $this->client()
                    ->connectTimeout($this->connectionTimeout)
                    ->timeout($this->requestTimeout)
                    ->get($sessionsUrl);

                $this->logDiagnostic('test_connectivity_sessions_list', $sessionsUrl, $sessionsResponse);

                if ($sessionsResponse->successful()) {
                    return [
                        'success' => true,
                        'api_connected' => true,
                        'session_status' => 'NOT_FOUND',
                        'is_ready' => false,
                        'session_id' => $this->sessionId,
                        'message' => "Koneksi ke gateway OpenWA ({$this->baseUrl}) berhasil, namun session '{$this->sessionId}' belum dibuat di server OpenWA.",
                    ];
                }

                return [
                    'success' => false,
                    'api_connected' => true,
                    'session_status' => 'NOT_FOUND',
                    'is_ready' => false,
                    'session_id' => $this->sessionId,
                    'message' => "Session ID '{$this->sessionId}' tidak ditemukan pada gateway OpenWA ({$statusUrl}).",
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
                    'message' => "Koneksi ke gateway OpenWA ditolak: API Key tidak valid atau otorisasi gagal (HTTP {$response->status()}).",
                ];
            }

            if ($response->failed()) {
                return [
                    'success' => false,
                    'api_connected' => false,
                    'session_status' => 'ERROR',
                    'is_ready' => false,
                    'session_id' => $this->sessionId,
                    'message' => "Gateway OpenWA mengembalikan galat HTTP {$response->status()} pada {$statusUrl}.",
                ];
            }

            $json = $response->json() ?? [];
            $rawStatus = $json['status'] ?? ($json['state'] ?? ($json['data']['status'] ?? ($json['data']['state'] ?? 'CONNECTED')));
            $statusUpper = strtoupper((string) $rawStatus);
            $isReady = in_array($statusUpper, ['CONNECTED', 'READY', 'NORMAL', 'OPEN', 'PAIRING_READY'], true);

            return [
                'success' => true,
                'api_connected' => true,
                'session_status' => $statusUpper,
                'is_ready' => $isReady,
                'session_id' => $this->sessionId,
                'message' => "Koneksi ke gateway OpenWA ({$this->baseUrl}) berhasil terverifikasi! Status session: {$statusUpper} (HTTP 200).",
            ];
        } catch (ConnectionException $e) {
            $this->logDiagnostic('test_connectivity', $statusUrl, null, $e);

            return [
                'success' => false,
                'api_connected' => false,
                'session_status' => 'UNREACHABLE',
                'is_ready' => false,
                'session_id' => $this->sessionId,
                'message' => "Tidak dapat menghubungi server OpenWA di {$this->baseUrl} (Connection Timeout / Network Unreachable).",
            ];
        } catch (Throwable $e) {
            $this->logDiagnostic('test_connectivity', $statusUrl, null, $e);

            return [
                'success' => false,
                'api_connected' => false,
                'session_status' => 'ERROR',
                'is_ready' => false,
                'session_id' => $this->sessionId,
                'message' => 'Kesalahan saat menguji koneksi OpenWA: '.$this->sanitizeError($e->getMessage()),
            ];
        }
    }

    /**
     * Check API connectivity and session ready state with the OpenWA Gateway.
     * Distinguishes between API server reachability and WhatsApp session readiness.
     *
     * @return array{success: bool, api_connected: bool, session_status: string, is_ready: bool, session_id: string, message: string}
     */
    public function checkSessionStatus(): array
    {
        $validationError = $this->validateBaseUrl();
        if ($validationError !== null) {
            return [
                'success' => false,
                'api_connected' => false,
                'session_status' => 'INVALID_CONFIG',
                'is_ready' => false,
                'session_id' => $this->sessionId,
                'message' => $validationError,
            ];
        }

        $statusUrl = $this->buildSessionStatusUrl();

        try {
            $response = $this->client()
                ->connectTimeout($this->connectionTimeout)
                ->timeout($this->requestTimeout)
                ->get($statusUrl);

            $this->logDiagnostic('check_session_status', $statusUrl, $response);

            // Handle 404: Session not created/found
            if ($response->status() === 404) {
                $sessionsUrl = $this->buildSessionsListUrl();
                $sessionsResponse = $this->client()
                    ->connectTimeout($this->connectionTimeout)
                    ->timeout($this->requestTimeout)
                    ->get($sessionsUrl);

                $this->logDiagnostic('check_session_status_sessions_list', $sessionsUrl, $sessionsResponse);

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
                    'message' => "Session ID '{$this->sessionId}' tidak ditemukan pada gateway OpenWA (HTTP 404 pada {$statusUrl}).",
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
                    'message' => "Gateway OpenWA mengembalikan status galat HTTP {$response->status()} pada {$statusUrl}.",
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
            $this->logDiagnostic('check_session_status', $statusUrl, null, $e);

            return [
                'success' => false,
                'api_connected' => false,
                'session_status' => 'UNREACHABLE',
                'is_ready' => false,
                'session_id' => $this->sessionId,
                'message' => "Tidak dapat menghubungi server OpenWA di {$this->baseUrl} (Connection Timeout / Network Unreachable).",
            ];
        } catch (Throwable $e) {
            $this->logDiagnostic('check_session_status', $statusUrl, null, $e);

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
     * Explicit Endpoint: POST {baseUrl}/sessions/{sessionId}/messages/send-text
     */
    public function sendText(string $chatId, string $text, ?string $idempotencyKey = null): WhatsAppSendResult
    {
        $validationError = $this->validateBaseUrl();
        if ($validationError !== null) {
            return WhatsAppSendResult::failure('CONFIG_ERROR', $validationError);
        }

        $url = $this->buildSendTextUrl();

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

            $this->logDiagnostic('send_text', $url, $response);

            return $this->parseResponse($response, 'send-text');
        } catch (Throwable $e) {
            $this->logDiagnostic('send_text', $url, null, $e);

            return WhatsAppSendResult::failure(
                'OPENWA_HTTP_EXCEPTION',
                $this->sanitizeError("Gagal menghubungi endpoint OpenWA send-text ({$url}): {$e->getMessage()}")
            );
        }
    }

    /**
     * Send a document (PDF) message via OpenWA REST API.
     * Supports Base64 mode (prioritized, pure base64 without Data URI prefix) and Signed URL mode.
     * Explicit Endpoint: POST {baseUrl}/sessions/{sessionId}/messages/send-document
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
        $validationError = $this->validateBaseUrl();
        if ($validationError !== null) {
            return WhatsAppSendResult::failure('CONFIG_ERROR', $validationError);
        }

        $url = $this->buildSendDocumentUrl();
        $contentLength = strlen($pdfContent);
        // Media size threshold: 15 MB for direct base64 transfer in HTTP JSON
        $maxBase64Bytes = 15 * 1024 * 1024;

        if ($contentLength > $maxBase64Bytes && ! empty($documentUrl)) {
            // Automatic safe fallback to signed URL mode if base64 payload exceeds HTTP gateway limit
            $mode = 'url';
        } elseif ($contentLength > $maxBase64Bytes && empty($documentUrl)) {
            return WhatsAppSendResult::failure(
                'PAYLOAD_TOO_LARGE',
                'Ukuran berkas PDF (' . round($contentLength / (1024 * 1024), 2) . ' MB) melebihi batas transmisi JSON (15 MB).'
            );
        }

        $payload = [
            'chatId' => $chatId,
            'filename' => $filename,
            'mimetype' => 'application/pdf',
        ];

        if ($mode === 'url' && ! empty($documentUrl)) {
            // URL mode: OpenWA fetches the document from the temporary signed route
            $payload['url'] = $documentUrl;
        } else {
            // Base64 mode: pure base64 encoded string of the PDF content (NO data URI prefix, NO file path)
            $payload['base64'] = base64_encode($pdfContent);
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

            $this->logDiagnostic('send_document', $url, $response);

            return $this->parseResponse($response, 'send-document');
        } catch (Throwable $e) {
            $this->logDiagnostic('send_document', $url, null, $e);

            return WhatsAppSendResult::failure(
                'OPENWA_HTTP_EXCEPTION',
                $this->sanitizeError("Gagal menghubungi endpoint OpenWA send-document ({$url}): {$e->getMessage()}")
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
     * Safe diagnostic logging that logs hostname, endpoint path, HTTP status, and sanitized response body.
     * NEVER logs API keys, bearer tokens, or sensitive credentials.
     */
    protected function logDiagnostic(string $action, string $url, ?Response $response = null, ?Throwable $exception = null): void
    {
        $parts = parse_url($url);
        $host = ($parts['host'] ?? 'unknown').(isset($parts['port']) ? ':'.$parts['port'] : '');
        $path = $parts['path'] ?? '/';

        $context = [
            'gateway' => 'openwa',
            'action' => $action,
            'hostname' => $host,
            'path' => $path,
            'final_url' => $url,
            'session_id' => $this->sessionId,
        ];

        if ($response !== null) {
            $context['http_status'] = $response->status();
            $body = $response->body();
            $context['response_body'] = $this->sanitizeError(mb_substr($body, 0, 500));
        }

        if ($exception !== null) {
            $context['exception_type'] = get_class($exception);
            $context['exception'] = $this->sanitizeError($exception->getMessage());
        }

        if ($response && $response->successful()) {
            Log::info("OpenWA [{$action}] sukses ke {$host}{$path} (HTTP {$response->status()})", $context);
        } else {
            $status = $response ? $response->status() : ($context['exception_type'] ?? 'EXCEPTION');
            Log::warning("OpenWA [{$action}] gagal pada {$host}{$path} ({$status})", $context);
        }
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
     * Sanitize error message to ensure API Keys, sensitive tokens, PDF base64, and salary amounts are never exposed.
     */
    protected function sanitizeError(string $rawMessage): string
    {
        $sanitized = preg_replace('/(bearer|token|secret|password|key|auth|api-key)[:=\s]+[^\s;&",]+/i', '$1=***', $rawMessage);
        $sanitized = preg_replace('/[a-zA-Z0-9_\-]{32,}/', '***', $sanitized ?? '');
        $sanitized = preg_replace('/data:application\/pdf;base64,[A-Za-z0-9+\/=]+/', '[BASE64_PDF_OMITTED]', $sanitized ?? '');
        $sanitized = preg_replace('/("base64"\s*:\s*")[^"]+(")/', '$1[BASE64_OMITTED]$2', $sanitized ?? '');
        $sanitized = preg_replace('/(Rp\.?\s*)[0-9\.\,]+/i', '$1***', $sanitized ?? '');

        return mb_substr(trim($sanitized ?? ''), 0, 500);
    }
}
