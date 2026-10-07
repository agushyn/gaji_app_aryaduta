<?php

namespace App\Services\WhatsApp\Gateways;

use App\Services\WhatsApp\Contracts\WhatsAppGatewayInterface;

abstract class BaseWhatsAppGateway implements WhatsAppGatewayInterface
{
    /**
     * Sanitize raw error string or response to prevent credentials, secrets, or bearer tokens from leaking into logs.
     */
    protected function sanitizeError(string $rawMessage): string
    {
        $sanitized = preg_replace('/(bearer|token|secret|password|key|auth)[:=\s]+[^\s;&",]+/i', '$1=***', $rawMessage);
        $sanitized = preg_replace('/[a-zA-Z0-9_\-]{32,}/', '***', $sanitized ?? '');

        return mb_substr(trim($sanitized ?? ''), 0, 500);
    }

    /**
     * Clean and ensure normalized phone number for Indonesian WhatsApp gateways.
     */
    protected function formatRecipientNumber(string $recipient): string
    {
        // Strip plus and all non-numeric characters
        $digits = preg_replace('/[^0-9]/', '', $recipient);

        // Convert leading 08 to 628
        if (str_starts_with($digits, '08')) {
            return '62'.substr($digits, 1);
        }

        return $digits;
    }
}
