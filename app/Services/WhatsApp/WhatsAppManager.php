<?php

namespace App\Services\WhatsApp;

use App\Models\IntegrationSetting;
use App\Services\WhatsApp\Contracts\WhatsAppGatewayInterface;
use App\Services\WhatsApp\Gateways\FonnteGateway;
use App\Services\WhatsApp\Gateways\GenericWebhookGateway;
use App\Services\WhatsApp\Gateways\MockGateway;
use DomainException;
use Illuminate\Support\Facades\RateLimiter;

class WhatsAppManager
{
    /**
     * Runtime overridden gateway instance (useful for testing or switching).
     */
    protected ?WhatsAppGatewayInterface $customGateway = null;

    /**
     * Set a custom gateway implementation at runtime.
     */
    public function setGateway(WhatsAppGatewayInterface $gateway): self
    {
        $this->customGateway = $gateway;

        return $this;
    }

    /**
     * Resolve the active WhatsApp gateway based on current database configuration.
     */
    public function getActiveGateway(): WhatsAppGatewayInterface
    {
        if ($this->customGateway !== null) {
            return $this->customGateway;
        }

        $setting = IntegrationSetting::forProvider('whatsapp');

        if (! $setting->exists || ! $setting->is_active) {
            // Default to Mock gateway if not configured or disabled
            return new MockGateway;
        }

        $config = $setting->config_json ?? [];
        $driver = strtolower($config['driver'] ?? 'fonnte');

        return $this->driver($driver, [
            'token' => $setting->getDecryptedSecret(),
            'secret' => $setting->getDecryptedSecret(),
            'endpoint' => $setting->endpoint,
            'sender' => $setting->sender_identifier,
            'webhook_secret' => $config['webhook_secret'] ?? null,
            'timeout' => $config['timeout'] ?? 25,
            'simulate_fail' => $config['simulate_fail'] ?? false,
            'fail_reason' => $config['fail_reason'] ?? null,
            'supports_document' => $config['supports_document'] ?? true,
        ]);
    }

    /**
     * Resolve a gateway driver instance directly.
     */
    public function driver(string $driver, array $customConfig = []): WhatsAppGatewayInterface
    {
        return match (strtolower($driver)) {
            'fonnte' => new FonnteGateway(
                token: $customConfig['token'] ?? '',
                endpoint: ! empty($customConfig['endpoint']) ? $customConfig['endpoint'] : 'https://api.fonnte.com/send',
                deviceSender: $customConfig['sender'] ?? null,
                webhookSecret: $customConfig['webhook_secret'] ?? null,
                timeoutSeconds: (int) ($customConfig['timeout'] ?? 25)
            ),
            'generic', 'webhook' => new GenericWebhookGateway(
                endpoint: $customConfig['endpoint'] ?? '',
                secretToken: $customConfig['secret'] ?? null,
                senderIdentifier: $customConfig['sender'] ?? null,
                webhookSecret: $customConfig['webhook_secret'] ?? null,
                timeoutSeconds: (int) ($customConfig['timeout'] ?? 25)
            ),
            'mock' => new MockGateway(
                shouldFail: (bool) ($customConfig['simulate_fail'] ?? false),
                failureReason: $customConfig['fail_reason'] ?? null,
                supportsDocument: (bool) ($customConfig['supports_document'] ?? true)
            ),
            default => throw new DomainException("Driver gateway WhatsApp '{$driver}' tidak didukung sistem."),
        };
    }

    /**
     * Check if sending rate limit has been exceeded.
     * Default limit: 20 messages per minute to respect carrier safety.
     *
     * @return bool True if allowed to proceed, false if rate limited.
     */
    public function checkRateLimit(string $key = 'whatsapp_global_dispatch', int $maxAttempts = 30): bool
    {
        return RateLimiter::attempt(
            $key,
            $maxAttempts,
            function () {
                return true;
            },
            decaySeconds: 60
        );
    }
}
