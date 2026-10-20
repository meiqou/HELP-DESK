<?php

namespace App\Auth;

use App\Core\Database;
use App\Security\PasswordHasher;

/**
 * Single-use backup codes for when the authenticator device is lost.
 * Stored hashed exactly like passwords; the plain codes are shown only once.
 */
final class RecoveryCodes
{
    public const COUNT = 8;

    /** @return string[] the new plain-text codes (display once, never store) */
    public static function regenerate(int $userId): array
    {
        $pdo = Database::connection();
        $pdo->prepare('DELETE FROM recovery_codes WHERE user_id = ?')->execute([$userId]);
        $insert = $pdo->prepare('INSERT INTO recovery_codes (user_id, code_hash) VALUES (?, ?)');

        $codes = [];
        for ($i = 0; $i < self::COUNT; $i++) {
            $code = strtoupper(bin2hex(random_bytes(3)) . '-' . bin2hex(random_bytes(3)));
            $codes[] = $code;
            $insert->execute([$userId, PasswordHasher::hash($code)]);
        }
        return $codes;
    }

    /** Marks a matching unused code as used. */
    public static function consume(int $userId, string $code): bool
    {
        $code = strtoupper(trim($code));
        if (!preg_match('/^[0-9A-F]{6}-[0-9A-F]{6}$/', $code)) {
            return false;
        }
        $stmt = Database::connection()->prepare('SELECT id, code_hash FROM recovery_codes WHERE user_id = ? AND used_at IS NULL');
        $stmt->execute([$userId]);
        foreach ($stmt->fetchAll() as $row) {
            if (password_verify(PasswordHasher::pepper($code), $row['code_hash'])) {
                // Atomic: only one request can ever mark a given code as used.
                $use = Database::connection()->prepare('UPDATE recovery_codes SET used_at = NOW() WHERE id = ? AND used_at IS NULL');
                $use->execute([$row['id']]);
                return $use->rowCount() === 1;
            }
        }
        return false;
    }

    public static function remaining(int $userId): int
    {
        $stmt = Database::connection()->prepare('SELECT COUNT(*) FROM recovery_codes WHERE user_id = ? AND used_at IS NULL');
        $stmt->execute([$userId]);
        return (int) $stmt->fetchColumn();
    }
}
