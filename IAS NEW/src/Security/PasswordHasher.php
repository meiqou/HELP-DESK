<?php

namespace App\Security;

/**
 * Password hashing.
 *
 * 1. The password is combined with a secret pepper using HMAC-SHA256.
 * 2. The result is hashed with Argon2id (memory-hard, random salt per password).
 *    Bcrypt with cost 12 is used if Argon2id is not available.
 */
final class PasswordHasher
{
    public static function algorithm(): array
    {
        if (defined('PASSWORD_ARGON2ID')) {
            return [PASSWORD_ARGON2ID, ['memory_cost' => 65536, 'time_cost' => 4, 'threads' => 1]];
        }
        return [PASSWORD_BCRYPT, ['cost' => 12]];
    }

    public static function hash(string $password): string
    {
        [$algo, $options] = self::algorithm();
        return password_hash(self::pepper($password), $algo, $options);
    }

    /**
     * Returns [bool $valid, bool $needsRehash].
     * Hashes made by the previous version of the system (no pepper, weaker cost)
     * are still accepted and are upgraded the next time the user signs in.
     */
    public static function verify(string $password, string $hash): array
    {
        [$algo, $options] = self::algorithm();

        if (password_verify(self::pepper($password), $hash)) {
            return [true, password_needs_rehash($hash, $algo, $options)];
        }
        // The unpeppered fallback only runs for hashes made by the old system, so an
        // ordinary wrong password costs exactly one hash, the same as an unknown user.
        if (self::isLegacy($hash) && password_verify($password, $hash)) {
            return [true, true];
        }
        return [false, false];
    }

    /**
     * Hashes from the first version: bcrypt with a lower cost than we use now.
     */
    public static function isLegacy(string $hash): bool
    {
        $info = password_get_info($hash);
        return $info['algo'] === PASSWORD_BCRYPT && ($info['options']['cost'] ?? 0) < 12;
    }

    /**
     * Runs the same verification path against a fixed current-format hash so that a
     * missing account takes the same time to reject as a wrong password (no user enumeration).
     */
    public static function dummyVerify(string $password): void
    {
        // Fixed hashes with exactly the production cost settings. Building one per request would
        // double the work and make unknown usernames slower than real ones (a timing leak).
        $dummy = defined('PASSWORD_ARGON2ID')
            ? '$argon2id$v=19$m=65536,t=4,p=1$ckRPeUJrVG1RdG54Wmt4RA$ccjzNFPl0BfV3sld/60HAeAcxws5XuPm6crhOihvAIU'
            : '$2y$12$CqZ4WQbaU8qkYhHXPZ0jCeCoBayvrRXwa3pKJVtzVTO3c5J4NAOnm';
        self::verify($password, $dummy);
    }

    public static function pepper(string $password): string
    {
        return hash_hmac('sha256', $password, Secrets::get('pepper'));
    }
}
