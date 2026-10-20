<?php

namespace App\Security;

use App\Core\Config;
use App\Core\Database;

/**
 * Brute-force protection backed by the auth_attempts table.
 * Counters are kept per account name and per IP address.
 */
final class RateLimiter
{
    /**
     * One canonical form for every identifier: valid UTF-8, lower case, at most 254 characters
     * (the column size). Recording and counting must use exactly the same value.
     */
    public static function normalize(string $identifier): string
    {
        return mb_strtolower(mb_substr(mb_scrub($identifier, 'UTF-8'), 0, 254));
    }

    public static function record(string $action, string $identifier, bool $success): void
    {
        Database::connection()
            ->prepare('INSERT INTO auth_attempts (action, identifier, ip, success) VALUES (?, ?, ?, ?)')
            ->execute([$action, self::normalize($identifier), client_ip(), $success ? 1 : 0]);
    }

    /**
     * Failures for one identifier inside the window, counting only those after its last success.
     * "After" is decided by row order (id), not by timestamp: timestamps have one-second
     * precision, so failures in the same second as a success would otherwise be missed.
     */
    public static function failures(string $action, string $identifier, int $window): int
    {
        $id = self::normalize($identifier);
        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) FROM auth_attempts
             WHERE action = ? AND identifier = ? AND success = 0
               AND created_at > NOW() - INTERVAL ? SECOND
               AND id > COALESCE((SELECT MAX(id) FROM auth_attempts
                                  WHERE action = ? AND identifier = ? AND success = 1), 0)'
        );
        $stmt->execute([$action, $id, $window, $action, $id]);
        return (int) $stmt->fetchColumn();
    }

    public static function attemptsFromIp(string $action, int $window, bool $failuresOnly = true): int
    {
        $sql = 'SELECT COUNT(*) FROM auth_attempts WHERE action = ? AND ip = ? AND created_at > NOW() - INTERVAL ? SECOND';
        if ($failuresOnly) {
            $sql .= ' AND success = 0';
        }
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([$action, client_ip(), $window]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * True when the account or the IP address has too many recent failures.
     * Behaves the same for names that do not exist, so lockouts reveal nothing.
     */
    public static function isLoginLocked(string $identifier): bool
    {
        $s = Config::get('security');
        return self::failures('login', $identifier, $s['lockout_window']) >= $s['max_login_failures_per_account']
            || self::attemptsFromIp('login', $s['lockout_window']) >= $s['max_login_failures_per_ip'];
    }

    public static function lockAccount(string $identifier): void
    {
        $max = (int) Config::get('security.max_login_failures_per_account');
        for ($i = 0; $i < $max; $i++) {
            self::record('login', $identifier, false);
        }
    }

    public static function clear(string $identifier): void
    {
        Database::connection()
            ->prepare("DELETE FROM auth_attempts WHERE action IN ('login', '2fa', 'reauth') AND identifier = ? AND success = 0")
            ->execute([self::normalize($identifier)]);
    }
}
