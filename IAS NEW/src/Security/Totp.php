<?php

namespace App\Security;

/**
 * Time-based One-Time Passwords (RFC 6238), compatible with Google Authenticator,
 * Microsoft Authenticator and Authy. Implemented without external libraries.
 */
final class Totp
{
    public const PERIOD = 30;
    public const DIGITS = 6;
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function generateSecret(): string
    {
        return self::base32Encode(random_bytes(20)); // 160-bit secret
    }

    public static function code(string $secret, int $step): string
    {
        $hash = hash_hmac('sha1', pack('N2', 0, $step), self::base32Decode($secret), true);
        $offset = ord($hash[19]) & 0x0F;
        $value = ((ord($hash[$offset]) & 0x7F) << 24)
            | (ord($hash[$offset + 1]) << 16)
            | (ord($hash[$offset + 2]) << 8)
            | ord($hash[$offset + 3]);
        return str_pad((string) ($value % (10 ** self::DIGITS)), self::DIGITS, '0', STR_PAD_LEFT);
    }

    public static function currentStep(): int
    {
        return intdiv(time(), self::PERIOD);
    }

    /**
     * Accepts one step of clock drift either side. Returns the matched step so the
     * caller can store it and reject the same code if it is replayed, or null.
     */
    public static function verify(string $secret, string $code, ?int $lastUsedStep = null): ?int
    {
        $code = preg_replace('/\s+/', '', $code);
        if (!preg_match('/^\d{' . self::DIGITS . '}$/', $code)) {
            return null;
        }
        $current = self::currentStep();
        for ($step = $current - 1; $step <= $current + 1; $step++) {
            if ($lastUsedStep !== null && $step <= $lastUsedStep) {
                continue;
            }
            if (hash_equals(self::code($secret, $step), $code)) {
                return $step;
            }
        }
        return null;
    }

    public static function uri(string $secret, string $account): string
    {
        $issuer = 'USLS IT Helpdesk';
        return 'otpauth://totp/' . rawurlencode($issuer . ':' . $account)
            . '?secret=' . $secret . '&issuer=' . rawurlencode($issuer)
            . '&algorithm=SHA1&digits=' . self::DIGITS . '&period=' . self::PERIOD;
    }

    public static function base32Encode(string $data): string
    {
        $bits = '';
        foreach (str_split($data) as $char) {
            $bits .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            $out .= self::ALPHABET[bindec(str_pad($chunk, 5, '0'))];
        }
        return $out;
    }

    public static function base32Decode(string $data): string
    {
        $data = strtoupper(preg_replace('/[^A-Za-z2-7]/', '', $data));
        $bits = '';
        foreach (str_split($data) as $char) {
            $bits .= str_pad(decbin(strpos(self::ALPHABET, $char)), 5, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $out .= chr(bindec($byte));
            }
        }
        return $out;
    }
}
