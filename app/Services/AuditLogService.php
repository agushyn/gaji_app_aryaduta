<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AuditLogService
{
    /**
     * List of sensitive keys that must never be recorded in audit logs.
     */
    protected const REDACTED_KEYS = [
        'password',
        'password_confirmation',
        'current_password',
        'smtp_password',
        'token',
        'secret',
        'secret_encrypted',
        'webhook_secret',
        'client_secret',
        'remember_token',
        'api_key',
        'access_token',
        'auth_token',
    ];

    /**
     * Record an audit log entry.
     */
    public function log(string $action, mixed $subject = null, ?array $changes = null, ?int $userId = null): AuditLog
    {
        $subjectType = null;
        $subjectId = null;

        if ($subject instanceof Model) {
            $subjectType = $subject->getMorphClass();
            $subjectId = (string) $subject->getKey();
        } elseif (is_string($subject)) {
            $subjectType = $subject;
        }

        $sanitizedChanges = $changes !== null ? $this->sanitizeChanges($changes) : null;

        return AuditLog::create([
            'user_id' => $userId ?? Auth::id(),
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'changes_json' => $sanitizedChanges,
            'ip_address' => request()?->ip(),
            'user_agent' => request() ? substr((string) request()->userAgent(), 0, 500) : null,
            'created_at' => now(),
        ]);
    }

    /**
     * Recursively sanitize sensitive keys from changes array.
     */
    protected function sanitizeChanges(array $data): array
    {
        $clean = [];

        foreach ($data as $key => $value) {
            if (in_array(strtolower((string) $key), self::REDACTED_KEYS, true)) {
                $clean[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $clean[$key] = $this->sanitizeChanges($value);
            } else {
                $clean[$key] = $value;
            }
        }

        return $clean;
    }
}
