<?php

namespace App\Models;

use Exception;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class IntegrationSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'provider',
        'is_active',
        'endpoint',
        'sender_identifier',
        'config_json',
        'secret_encrypted',
        'last_tested_at',
        'last_test_status',
    ];

    protected $hidden = [
        'secret_encrypted',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'config_json' => 'array',
            'last_tested_at' => 'datetime',
        ];
    }

    /**
     * Retrieve decrypted secret securely.
     */
    public function getDecryptedSecret(): ?string
    {
        if (empty($this->secret_encrypted)) {
            return null;
        }

        try {
            return Crypt::decryptString($this->secret_encrypted);
        } catch (Exception) {
            return null;
        }
    }

    /**
     * Set encrypted secret safely.
     */
    public function setSecretAttribute(?string $value): void
    {
        if ($value !== null && $value !== '') {
            $this->attributes['secret_encrypted'] = Crypt::encryptString($value);
        }
    }

    /**
     * Get safe array for frontend representation with secret masking.
     * Never exposes raw or decrypted secrets.
     */
    public function toSafeArray(): array
    {
        $config = $this->config_json ?? [];

        return [
            'id' => $this->id,
            'provider' => $this->provider,
            'is_active' => (bool) $this->is_active,
            'endpoint' => $this->endpoint ?? '',
            'sender_identifier' => $this->sender_identifier ?? '',
            'port' => $config['port'] ?? 587,
            'encryption' => $config['encryption'] ?? 'tls',
            'username' => $config['username'] ?? '',
            'from_name' => $config['from_name'] ?? config('app.name', 'HR Personalia'),
            'has_secret' => ! empty($this->secret_encrypted),
            'last_tested_at' => $this->last_tested_at?->format('d/m/Y H:i:s'),
            'last_test_status' => $this->last_test_status,
        ];
    }

    /**
     * Find or instantiate integration settings for a specific provider.
     */
    public static function forProvider(string $provider): self
    {
        return static::firstOrNew(['provider' => $provider]);
    }
}
