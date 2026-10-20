<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * All SQL that touches the users table. Every query uses bound parameters.
 */
final class UserRepository
{
    private static function db(): PDO
    {
        return Database::connection();
    }

    public static function find(int $id): ?array
    {
        $stmt = self::db()->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function findByLogin(string $usernameOrEmail): ?array
    {
        $stmt = self::db()->prepare('SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1');
        $stmt->execute([$usernameOrEmail, $usernameOrEmail]);
        return $stmt->fetch() ?: null;
    }

    public static function findByUsername(string $username): ?array
    {
        $stmt = self::db()->prepare('SELECT * FROM users WHERE username = ?');
        $stmt->execute([$username]);
        return $stmt->fetch() ?: null;
    }

    public static function findByEmail(string $email): ?array
    {
        $stmt = self::db()->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([$email]);
        return $stmt->fetch() ?: null;
    }

    public static function create(string $username, string $email, string $passwordHash, string $role = 'user'): int
    {
        self::db()->prepare('INSERT INTO users (username, email, password, role, password_changed_at) VALUES (?, ?, ?, ?, NOW())')
            ->execute([$username, $email, $passwordHash, $role]);
        return (int) self::db()->lastInsertId();
    }

    public static function passwordHash(int $id): string
    {
        $stmt = self::db()->prepare('SELECT password FROM users WHERE id = ?');
        $stmt->execute([$id]);
        return (string) $stmt->fetchColumn();
    }

    public static function updatePasswordHash(int $id, string $hash, bool $userChanged): void
    {
        $sql = $userChanged
            ? 'UPDATE users SET password = ?, password_changed_at = NOW() WHERE id = ?'
            : 'UPDATE users SET password = ? WHERE id = ?';
        self::db()->prepare($sql)->execute([$hash, $id]);
    }

    public static function recordLogin(int $id, string $ip): void
    {
        self::db()->prepare('UPDATE users SET last_login_at = NOW(), last_login_ip = ? WHERE id = ?')->execute([$ip, $id]);
    }

    /**
     * Incrementing the version invalidates every session that stored the old value.
     */
    public static function bumpSessionVersion(int $id): int
    {
        self::db()->prepare('UPDATE users SET session_version = session_version + 1 WHERE id = ?')->execute([$id]);
        $stmt = self::db()->prepare('SELECT session_version FROM users WHERE id = ?');
        $stmt->execute([$id]);
        return (int) $stmt->fetchColumn();
    }

    public static function setRole(int $id, string $role): void
    {
        self::db()->prepare('UPDATE users SET role = ?, session_version = session_version + 1 WHERE id = ?')->execute([$role, $id]);
    }

    public static function setActive(int $id, bool $active): void
    {
        self::db()->prepare('UPDATE users SET is_active = ?, session_version = session_version + 1 WHERE id = ?')->execute([$active ? 1 : 0, $id]);
    }

    public static function delete(int $id): void
    {
        self::db()->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
    }

    public static function enableTotp(int $id, string $encryptedSecret, int $step): void
    {
        self::db()->prepare('UPDATE users SET totp_secret = ?, totp_enabled = 1, totp_last_step = ? WHERE id = ?')
            ->execute([$encryptedSecret, $step, $id]);
    }

    public static function disableTotp(int $id, bool $signOutEverywhere = false): void
    {
        $sql = 'UPDATE users SET totp_enabled = 0, totp_secret = NULL, totp_last_step = NULL'
            . ($signOutEverywhere ? ', session_version = session_version + 1' : '') . ' WHERE id = ?';
        self::db()->prepare($sql)->execute([$id]);
        self::db()->prepare('DELETE FROM recovery_codes WHERE user_id = ?')->execute([$id]);
    }

    /**
     * Records a used TOTP time step only if it is newer than the last one, in one atomic UPDATE.
     * Returns false when this step was already used (a replay, or two requests racing).
     */
    public static function claimTotpStep(int $id, int $step): bool
    {
        $stmt = self::db()->prepare('UPDATE users SET totp_last_step = ? WHERE id = ? AND (totp_last_step IS NULL OR totp_last_step < ?)');
        $stmt->execute([$step, $id, $step]);
        return $stmt->rowCount() === 1;
    }

    public static function countActiveAdmins(): int
    {
        return (int) self::db()->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND is_active = 1")->fetchColumn();
    }

    /** All accounts with their ticket counts, privileged roles first. */
    public static function allWithTicketCounts(): array
    {
        return self::db()->query(
            "SELECT u.id, u.username, u.email, u.role, u.is_active, u.totp_enabled, u.created_at, u.last_login_at,
                    (SELECT COUNT(*) FROM tickets t WHERE t.user_id = u.id) AS ticket_count
             FROM users u
             ORDER BY u.role = 'admin' DESC, u.role = 'technician' DESC, u.username"
        )->fetchAll();
    }

    public static function totals(): array
    {
        return array_map('intval', self::db()->query(
            'SELECT COUNT(*) AS users, COALESCE(SUM(totp_enabled = 1), 0) AS mfa FROM users'
        )->fetch());
    }
}
