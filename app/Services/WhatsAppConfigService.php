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
        ];
    }

    /**
     * Save or update WhatsApp configuration safely with encrypted secret.
     */
    public function saveConfig(array $data, ?User $actor = null): IntegrationSetting
    {
        $setting = IntegrationSetting::forProvider('whatsapp');

        $existingConfig = $setting->config_json ?? [];
        $newConfig = [
            'driver' => $data['driver'] ?? ($existingConfig['driver'] ?? 'fonnte'),
            'rate_limit_per_minute' => (int) ($data['rate_limit_per_minute'] ?? ($existingConfig['rate_limit_per_minute'] ?? 30)),
            'timeout' => (int) ($data['timeout'] ?? ($existingConfig['timeout'] ?? 25)),
            'webhook_secret' => ! empty($data['webhook_secret'])
                ? $data['webhook_secret']
                : ($existingConfig['webhook_secret'] ?? null),
        ];

        $setting->is_active = (bool) ($data['is_active'] ?? false);
        $setting->endpoint = $data['endpoint'] ?? null;
        $setting->sender_identifier = $data['sender_identifier'] ?? null;
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
     * Test WhatsApp gateway connection by sending a benign verification ping message.
     * Guaranteed zero payroll data sent.
     *
     * @return array{success: bool, message: string}
     */
    public function testConnection(string $testRecipient): array
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

            $pingMessage = "Halo,\n\nIni adalah pesan uji coba koneksi gateway WhatsApp dari Sistem Informasi Penggajian Personalia CV. Bina Pustaka.\nKoneksi berhasil diverifikasi pada ".now()->format('d/m/Y H:i:s')." WIB.\n\nCatatan: Pesan uji coba ini tidak memuat data gaji, nominal, atau rincian finansial karyawan apa pun.\n\nSalam,\nBagian Personalia / IT Support";

            $result = $gateway->sendTextMessage($normalizedPhone, $pingMessage);

            if ($result->success) {
                $setting->last_tested_at = now();
                $setting->last_test_status = 'success';
                $setting->save();

                return [
                    'success' => true,
                    'message' => "Koneksi gateway WhatsApp ({$gateway->getName()}) berhasil! Pesan uji coba telah dikirim ke {$normalizedPhone} tanpa data gaji.",
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
