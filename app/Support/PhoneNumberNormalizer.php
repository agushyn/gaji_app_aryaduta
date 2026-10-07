<?php

namespace App\Support;

class PhoneNumberNormalizer
{
    /**
     * Clean and strip formatting characters from a phone number string.
     */
    public static function clean(?string $number): string
    {
        if ($number === null) {
            return '';
        }

        // Remove spaces, hyphens, parentheses, dots
        return trim(preg_replace('/[\s\-\(\)\.]/', '', $number));
    }

    /**
     * Validate whether a phone number is a plausible Indonesian WhatsApp mobile number.
     * Rejects numbers that are just formatable but invalid (e.g. toll-free, landline, too short/long, foreign).
     */
    public static function isValid(?string $number): bool
    {
        if (empty($number)) {
            return false;
        }

        $cleaned = self::clean($number);

        // Disallow invalid characters (only digits and optional leading + allowed)
        if (! preg_match('/^\+?[0-9]{10,16}$/', $cleaned)) {
            return false;
        }

        // Must start with Indonesian mobile prefix: 08, 628, or +628
        if (! preg_match('/^(\+62|62|0)8[1-9][0-9]{7,10}$/', $cleaned)) {
            return false;
        }

        // Reject toll-free (0800, 0804, 0807) or premium (0809)
        if (preg_match('/^(\+62|62|0)80[0-9]/', $cleaned)) {
            return false;
        }

        return true;
    }

    /**
     * Normalize a phone number to standard international digits (e.g. 6281234567890).
     * Returns null if the number is invalid.
     */
    public static function normalize(?string $number): ?string
    {
        if (empty($number) || ! self::isValid($number)) {
            return null;
        }

        $cleaned = self::clean($number);

        // Remove leading '+'
        if (str_starts_with($cleaned, '+')) {
            $cleaned = substr($cleaned, 1);
        }

        // Convert Indonesian local format (08xx -> 628xx)
        if (str_starts_with($cleaned, '08')) {
            $cleaned = '62'.substr($cleaned, 1);
        }

        return $cleaned;
    }

    /**
     * Format a normalized number for user-friendly UI display (e.g. +62 812-3456-7890).
     */
    public static function toDisplayFormat(?string $number): string
    {
        if (empty($number)) {
            return '-';
        }

        $normalized = self::normalize($number);

        if (! $normalized) {
            return $number;
        }

        // If Indonesian 628xx
        if (str_starts_with($normalized, '628') && strlen($normalized) >= 10) {
            $prefix = '+62 ';
            $rest = substr($normalized, 2); // e.g. 81234567890

            if (strlen($rest) <= 4) {
                return $prefix.$rest;
            } elseif (strlen($rest) <= 8) {
                return $prefix.substr($rest, 0, 3).'-'.substr($rest, 3);
            } else {
                return $prefix.substr($rest, 0, 3).'-'.substr($rest, 3, 4).'-'.substr($rest, 7);
            }
        }

        return '+'.$normalized;
    }
}
