<?php

namespace App\Services;

use App\Mail\SmtpTestPingEmail;
use App\Models\IntegrationSetting;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SmtpConfigService
{
    public function __construct(
        protected AuditLogService $auditLogService
    ) {}

    /**
     * Get safe configuration data with masked secret for UI rendering.
     */
    public function getSafeConfig(): array
    {
        $setting = IntegrationSetting::forProvider('smtp');

        if (! $setting->exists) {
            return [
                'provider' => 'smtp',
                'is_active' => false,
                'endpoint' => config('mail.mailers.smtp.host', ''),
                'port' => (int) config('mail.mailers.smtp.port', 587),
                'encryption' => config('mail.mailers.smtp.encryption', 'tls'),
                'username' => config('mail.mailers.smtp.username', ''),
                'sender_identifier' => config('mail.from.address', ''),
                'from_name' => config('mail.from.name', 'HR Personalia'),
                'has_secret' => ! empty(config('mail.mailers.smtp.password')),
                'last_tested_at' => null,
                'last_test_status' => null,
            ];
        }

        return $setting->toSafeArray();
    }

    /**
     * Save SMTP configuration safely with secret encryption.
     */
    public function saveConfig(array $data, ?User $actor = null): IntegrationSetting
    {
        $setting = IntegrationSetting::firstOrNew(['provider' => 'smtp']);

        $setting->is_active = (bool) ($data['is_active'] ?? false);
        $setting->endpoint = trim($data['endpoint'] ?? '');
        $setting->sender_identifier = trim($data['sender_identifier'] ?? '');

        $config = [
            'port' => (int) ($data['port'] ?? 587),
            'encryption' => trim(strtolower($data['encryption'] ?? 'tls')),
            'username' => trim($data['username'] ?? ''),
            'from_name' => trim($data['from_name'] ?? config('app.name', 'HR Personalia')),
        ];

        $setting->config_json = $config;

        // Only update password if a new non-empty password was provided
        if (! empty($data['password'])) {
            $setting->secret = $data['password'];
        }

        $setting->save();

        $this->auditLogService->log(
            action: 'settings.smtp_updated',
            subject: $setting,
            changes: [
                'endpoint' => $setting->endpoint,
                'sender' => $setting->sender_identifier,
                'port' => $config['port'],
                'encryption' => $config['encryption'],
                'is_active' => $setting->is_active,
                'updated_by' => $actor?->name ?? 'Admin',
            ]
        );

        return $setting;
    }

    /**
     * Test the SMTP connection by sending a benign verification ping email without salary data.
     *
     * @return array{success: bool, message: string}
     */
    public function testConnection(string $testRecipient): array
    {
        $setting = IntegrationSetting::forProvider('smtp');

        try {
            if ($setting->exists) {
                $config = $setting->config_json ?? [];
                $secret = $setting->getDecryptedSecret();

                Config::set('mail.mailers.smtp.host', $setting->endpoint);
                Config::set('mail.mailers.smtp.port', (int) ($config['port'] ?? 587));
                Config::set('mail.mailers.smtp.encryption', $config['encryption'] ?? 'tls');
                Config::set('mail.mailers.smtp.username', $config['username'] ?? null);

                if (! empty($secret)) {
                    Config::set('mail.mailers.smtp.password', $secret);
                }

                if (! empty($setting->sender_identifier)) {
                    Config::set('mail.from.address', $setting->sender_identifier);
                }

                if (! empty($config['from_name'])) {
                    Config::set('mail.from.name', $config['from_name']);
                }

                Mail::purge('smtp');
            }

            // Send lightweight benign verification email without any payroll/salary data
            Mail::mailer('smtp')
                ->to($testRecipient)
                ->send(new SmtpTestPingEmail(now()->toDateTimeString()));

            $setting->last_tested_at = now();
            $setting->last_test_status = 'success';
            $setting->save();

            return [
                'success' => true,
                'message' => "Koneksi SMTP berhasil! Email uji coba telah terkirim ke {$testRecipient} tanpa memuat data finansial.",
            ];
        } catch (Throwable $e) {
            $errorMessage = $this->sanitizeError($e->getMessage());

            $setting->last_tested_at = now();
            $setting->last_test_status = 'failed';
            $setting->save();

            return [
                'success' => false,
                'message' => "Uji coba koneksi SMTP gagal: {$errorMessage}",
            ];
        }
    }

    /**
     * Sanitize error message to prevent leaking passwords or secrets in messages.
     */
    protected function sanitizeError(string $rawMessage): string
    {
        $sanitized = preg_replace('/password=[^\s;&]+/i', 'password=***', $rawMessage);
        $sanitized = preg_replace('/pass=[^\s;&]+/i', 'pass=***', $sanitized);
        $sanitized = preg_replace('/secret=[^\s;&]+/i', 'secret=***', $sanitized);

        return mb_substr(trim($sanitized), 0, 300);
    }
}
