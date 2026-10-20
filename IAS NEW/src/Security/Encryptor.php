<?php

namespace App\Security;

/**
 * Authenticated encryption (AES-256-GCM) for secrets stored in the database,
 * such as two-factor keys. Any modification of the ciphertext is detected.
 */
final class Encryptor
{
    private const CIPHER = 'aes-256-gcm';

    public static function encrypt(string $plain): string
    {
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plain, self::CIPHER, self::key(), OPENSSL_RAW_DATA, $iv, $tag);
        return base64_encode($iv . $tag . $cipher);
    }

    public static function decrypt(?string $encoded): ?string
    {
        $raw = $encoded ? base64_decode($encoded, true) : false;
        if ($raw === false || strlen($raw) < 29) {
            return null;
        }
        $plain = openssl_decrypt(substr($raw, 28), self::CIPHER, self::key(), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
        return $plain === false ? null : $plain;
    }

    private static function key(): string
    {
        return hex2bin(Secrets::get('encryption_key'));
    }
}
