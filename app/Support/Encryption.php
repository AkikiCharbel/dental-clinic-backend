<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Crypt;
use RuntimeException;

/**
 * Encryption helper for sensitive data (PII).
 *
 * Use this for:
 * - Social Security Numbers (SSN)
 * - Medical record identifiers
 * - Insurance policy numbers
 * - Financial account numbers
 * - Any data classified as PII
 *
 * Note: For database-level encryption, consider using
 * Eloquent casts or database-native encryption.
 */
final class Encryption
{
    /**
     * Encrypt a value if not already encrypted.
     */
    public static function encrypt(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        // Check if already encrypted (starts with eyJ - base64 JSON)
        if (self::isEncrypted($value)) {
            return $value;
        }

        return Crypt::encryptString($value);
    }

    /**
     * Decrypt a value if encrypted.
     */
    public static function decrypt(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        if (! self::isEncrypted($value)) {
            return $value;
        }

        try {
            return Crypt::decryptString($value);
        } catch (RuntimeException) {
            // Log decryption failure but don't expose error
            logger()->error('Failed to decrypt value', [
                'value_length' => strlen($value),
            ]);

            return null;
        }
    }

    /**
     * Check if a value appears to be encrypted.
     */
    public static function isEncrypted(?string $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        // Laravel's encrypted strings are base64-encoded JSON
        // They typically start with 'eyJ' (base64 for '{"')
        if (! str_starts_with($value, 'eyJ')) {
            return false;
        }

        // Try to decode and verify structure
        $decoded = base64_decode($value, true);

        if ($decoded === false) {
            return false;
        }

        $data = json_decode($decoded, true);

        return is_array($data)
            && isset($data['iv'], $data['value'], $data['mac']);
    }

    /**
     * Hash a value for searching (deterministic).
     *
     * Use this when you need to search encrypted fields.
     * Store both the encrypted value and the search hash.
     */
    public static function searchHash(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        /** @var string $appKey */
        $appKey = config('app.key');

        // Use HMAC for deterministic hashing with app key
        return hash_hmac('sha256', strtolower(trim($value)), $appKey);
    }

    /**
     * Mask a sensitive value for display.
     *
     * @param  int  $visibleStart  Number of characters visible at start
     * @param  int  $visibleEnd  Number of characters visible at end
     */
    public static function mask(
        ?string $value,
        int $visibleStart = 0,
        int $visibleEnd = 4,
        string $maskChar = '*',
    ): ?string {
        if ($value === null || $value === '') {
            return $value;
        }

        $length = strlen($value);

        if ($length <= $visibleStart + $visibleEnd) {
            return str_repeat($maskChar, $length);
        }

        $start = substr($value, 0, $visibleStart);
        $end = substr($value, -$visibleEnd);
        $maskLength = $length - $visibleStart - $visibleEnd;

        return $start.str_repeat($maskChar, $maskLength).$end;
    }

    /**
     * Mask an email address.
     */
    public static function maskEmail(?string $email): ?string
    {
        if ($email === null || ! str_contains($email, '@')) {
            return $email;
        }

        [$local, $domain] = explode('@', $email);

        $maskedLocal = self::mask($local, 1, 1);

        return $maskedLocal.'@'.$domain;
    }

    /**
     * Mask a phone number (show last 4 digits).
     */
    public static function maskPhone(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        // Remove non-numeric characters for consistent masking
        $digits = preg_replace('/\D/', '', $phone);

        if ($digits === null || strlen($digits) < 4) {
            return str_repeat('*', strlen($phone));
        }

        return '***-***-'.substr($digits, -4);
    }
}
