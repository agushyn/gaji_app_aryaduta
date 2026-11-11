<?php

namespace App\Services;

use App\Models\IntegrationSetting;
use App\Models\User;
use App\Services\WhatsApp\WhatsAppManager;
use App\Support\PhoneNumberNormalizer;
use Illuminate\Support\Facades\Crypt;
use Throwable;

class WhatsAppConfigService
{
    public function __construct(
        protected WhatsAppManager $whatsAppManager,
        protected AuditLogService $auditLogService
    ) {}

    /**
     * Get sanitized configuration safe for frontend presentation.
     * Masked: never exposes secret or decrypted tokens.
     */
    public function getSafeConfig(): array
    {
        $setting = IntegrationSetting::forProvider('whatsapp');
        $config = $setting->config_json ?? [];

        return [
            'provider' => 'whatsapp',
            'is_active' => (bool) $setting->is_active,
            'driver' => $config['driver'] ?? 'fonnte',
            'endpoint' => $setting->endpoint ?? 'https://api.fonnte.com/send',
            'sender_identifier' => $setting->sender_identifier ?? '',
            'rate_limit_per_minute' => $config['rate_limit_per_minute'] ?? 30,
            'timeout' => $config['timeout'] ?? 25,
            'webhook_secret' => ! empty($config['webhook_secret']) ? '••••••••' : '',
            'has_webhook_secret' => ! empty($config['webhook_secret']),
            'has_secret' => ! empty($setting->secret_encrypted),
            'last_tested_at' => $setting->last_tested_at?->format('d/m/Y H:i:s'),
            'last_test_status' => $setting->last_test_status,
            'webhook_url' => url('/api/webhooks/whatsapp'),
            'session_id' => $config['session_id'] ?? ($setting->sender_identifier ?? 'default'),
            'connection_timeout' => (int) ($config['connection_timeout'] ?? 10),
            'request_timeout' => (int) ($config['request_timeout'] ?? 30),
            'send_interval_seconds' => (int) ($config['send_interval_seconds'] ?? 2),
            'max_retries' => (int) ($config['max_retries'] ?? 3),
            'document_mode' => $config['document_mode'] ?? 'base64',
            'last_session_status' => $config['last_session_status'] ?? null,
            'last_session_checked_at' => ! empty($config['last_session_checked_at'])
                ? \Carbon\Carbon::parse($config['last_session_checked_at'])->format('d/m/Y H:i:s')
                : null,
        ];
    }

    /**
     * Save or update WhatsApp configuration safely with encrypted secret.
     */
    public function saveConfig(array $data, ?User $actor = null): IntegrationSetting
    {
        $setting = IntegrationSetting::forProvider('whatsapp');

        $existingConfig = $setting->config_json ?? [];
        $driver = $data['driver'] ?? ($existingConfig['driver'] ?? 'fonnte');

        $newConfig = [
            'driver' => $driver,
            'rate_limit_per_minute' => (int) ($data['rate_limit_per_minute'] ?? ($existingConfig['rate_limit_per_minute'] ?? 30)),
            'timeout' => (int) ($data['timeout'] ?? ($existingConfig['timeout'] ?? 25)),
            'webhook_secret' => ! empty($data['webhook_secret'])
                ? $data['webhook_secret']
                : ($existingConfig['webhook_secret'] ?? null),
            'session_id' => $data['session_id'] ?? ($existingConfig['session_id'] ?? ($data['sender_identifier'] ?? 'default')),
            'connection_timeout' => (int) ($data['connection_timeout'] ?? ($existingConfig['connection_timeout'] ?? 10)),
            'request_timeout' => (int) ($data['request_timeout'] ?? ($existingConfig['request_timeout'] ?? 30)),
            'send_interval_seconds' => max(1, (int) ($data['send_interval_seconds'] ?? ($existingConfig['send_interval_seconds'] ?? 2))),
            'max_retries' => max(1, (int) ($data['max_retries'] ?? ($existingConfig['max_retries'] ?? 3))),
            'document_mode' => in_array($data['document_mode'] ?? ($existingConfig['document_mode'] ?? 'base64'), ['base64', 'url'], true)
                ? ($data['document_mode'] ?? ($existingConfig['document_mode'] ?? 'base64'))
                : 'base64',
            'last_session_status' => $existingConfig['last_session_status'] ?? null,
            'last_session_checked_at' => $existingConfig['last_session_checked_at'] ?? null,
        ];

        $endpoint = $data['endpoint'] ?? null;
        if ($driver === 'openwa' && ! empty($endpoint)) {
            $endpoint = \App\Services\WhatsApp\OpenWaClient::normalizeBaseUrl($endpoint);
        }

        $setting->is_active = (bool) ($data['is_active'] ?? false);
        $setting->endpoint = $endpoint;
        $setting->sender_identifier = $data['sender_identifier'] ?? ($data['session_id'] ?? null);
        $setting->config_json = $newConfig;

        // If a new secret was provided, encrypt it; otherwise preserve existing secret
        if (! empty($data['secret'])) {
            $setting->secret_encrypted = Crypt::encryptString($data['secret']);
        }

        $setting->save();

        $this->auditLogService->log(
            action: 'settings.whatsapp_updated',
            subject: $setting,
            changes: [
                'driver' => $newConfig['driver'],
                'endpoint' => $setting->endpoint,
                'sender' => $setting->sender_identifier,
                'is_active' => $setting->is_active,
                'updated_by' => $actor?->name ?? 'Admin',
            ]
        );

        return $setting;
    }

    /**
     * Check OpenWA session status and persist the check result.
     *
     * @return array{success: bool, api_connected: bool, session_status: string, is_ready: bool, session_id?: string, message: string}
     */
    public function checkSessionStatus(): array
    {
        $setting = IntegrationSetting::forProvider('whatsapp');

        try {
            $gateway = $this->whatsAppManager->getActiveGateway();

            if (! method_exists($gateway, 'checkSessionStatus')) {
                return [
                    'success' => false,
                    'api_connected' => false,
                    'session_status' => 'UNSUPPORTED',
                    'is_ready' => false,
                    'message' => 'Pengecekan status session hanya didukung untuk gateway OpenWA.',
                ];
            }

            $result = $gateway->checkSessionStatus();

            $config = $setting->config_json ?? [];
            $config['last_session_status'] = $result['session_status'] ?? ($result['is_ready'] ? 'CONNECTED' : 'DISCONNECTED');
            $config['last_session_checked_at'] = now()->toIso8601String();
            $setting->config_json = $config;
            $setting->save();

            return $result;
        } catch (Throwable $e) {
            return [
                'success' => false,
                'api_connected' => false,
                'session_status' => 'ERROR',
                'is_ready' => false,
                'message' => 'Gagal memeriksa status session OpenWA: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Test gateway HTTP connectivity and authentication without sending any message.
     * Verifies API endpoint reachability and credentials.
     *
     * @return array{success: bool, message: string, api_connected?: bool, session_status?: string, is_ready?: bool}
     */
    public function testGatewayConnection(): array
    {
        $setting = IntegrationSetting::forProvider('whatsapp');

        try {
            $gateway = $this->whatsAppManager->getActiveGateway();

            if (method_exists($gateway, 'testGatewayConnectivity')) {
                $result = $gateway->testGatewayConnectivity();
            } else {
                $isValid = $gateway->validateConfiguration();
                $result = [
                    'success' => $isValid,
                    'message' => $isValid
                        ? "Gateway WhatsApp ({$gateway->getName()}) siap digunakan."
                        : "Konfigurasi gateway WhatsApp ({$gateway->getName()}) belum lengkap atau tidak valid.",
                ];
            }

            $setting->last_tested_at = now();
            $setting->last_test_status = $result['success'] ? 'success' : 'failed';
            $setting->save();

            return $result;
        } catch (Throwable $e) {
            $setting->last_tested_at = now();
            $setting->last_test_status = 'failed';
            $setting->save();

            return [
                'success' => false,
                'message' => 'Terjadi kesalahan saat menguji koneksi gateway: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Test WhatsApp gateway connection.
     * If $testRecipient is provided, sends a benign test verification message.
     * If $testRecipient is null/empty, checks gateway connectivity only.
     *
     * @return array{success: bool, message: string}
     */
    public function testConnection(?string $testRecipient = null): array
    {
        if (empty($testRecipient)) {
            return $this->testGatewayConnection();
        }

        return $this->sendTestMessage($testRecipient);
    }

    /**
     * Send a benign verification ping message to test end-to-end delivery to a recipient.
     * Guaranteed zero payroll data sent.
     *
     * @return array{success: bool, message: string}
     */
    public function sendTestMessage(string $testRecipient): array
    {
        if (! PhoneNumberNormalizer::isValid($testRecipient)) {
            return [
                'success' => false,
                'message' => 'Nomor WhatsApp tujuan uji coba tidak valid (gunakan format ponsel Indonesia, misal: 081234567890).',
            ];
        }

        $normalizedPhone = PhoneNumberNormalizer::normalize($testRecipient);
        $setting = IntegrationSetting::forProvider('whatsapp');

        try {
            $gateway = $this->whatsAppManager->getActiveGateway();

            // Validate gateway configuration
            if (! $gateway->validateConfiguration()) {
                return [
                    'success' => false,
                    'message' => 'Konfigurasi gateway WhatsApp belum lengkap atau tidak valid.',
                ];
            }

            // Ensure session is ready before attempting message delivery
            if (method_exists($gateway, 'checkSessionStatus')) {
                $sessionCheck = $gateway->checkSessionStatus();
                if (! ($sessionCheck['is_ready'] ?? false)) {
                    $status = $sessionCheck['session_status'] ?? 'NOT_READY';

                    return [
                        'success' => false,
                        'message' => "Sesi WhatsApp belum siap untuk pengiriman pesan (Status: {$status}). {$sessionCheck['message']}",
                        'session_status' => $status,
                    ];
                }
            }

            $pingMessage = "Halo,\n\nIni adalah pesan uji coba koneksi gateway WhatsApp dari Sistem Informasi Penggajian Personalia CV. Bina Pustaka.\nKoneksi berhasil diverifikasi pada ".now()->format('d/m/Y H:i:s')." WIB.\n\nCatatan: Pesan uji coba ini tidak memuat data gaji, nominal, atau rincian finansial karyawan apa pun.\n\nSalam,\nBagian Personalia / IT Support";

            $result = $gateway->sendTextMessage($normalizedPhone, $pingMessage);

            if ($result->success) {
                $setting->last_tested_at = now();
                $setting->last_test_status = 'success';
                $setting->save();

                return [
                    'success' => true,
                    'message' => "Koneksi gateway WhatsApp ({$gateway->getName()}) berhasil! Pesan uji coba telah dikirim ke {$normalizedPhone} tanpa data gaji.",
                    'message_id' => $result->providerMessageId,
                    'status' => $result->status->value,
                ];
            }

            $setting->last_tested_at = now();
            $setting->last_test_status = 'failed';
            $setting->save();

            return [
                'success' => false,
                'message' => "Uji coba koneksi WhatsApp gagal: {$result->errorMessage}",
            ];
        } catch (Throwable $e) {
            $setting->last_tested_at = now();
            $setting->last_test_status = 'failed';
            $setting->save();

            return [
                'success' => false,
                'message' => "Terjadi kesalahan saat menguji koneksi WhatsApp: {$e->getMessage()}",
            ];
        }
    }
}
