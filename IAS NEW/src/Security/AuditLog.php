<?php

namespace App\Security;

use App\Core\Database;
use PDO;
use Throwable;

/**
 * Append-only security audit trail.
 *
 * Every entry stores an HMAC of its contents chained to the previous entry.
 * Editing or deleting a row directly in the database breaks the chain, which
 * verifyChain() detects. The HMAC key is not stored in the database, so an
 * attacker with database access alone cannot forge a valid chain.
 */
final class AuditLog
{
    /** Events shown as warnings in the interface and counted on the dashboards. */
    public const WARNING_EVENTS = [
        'login_failed', '2fa_failed', 'account_locked', 'login_blocked', 'access_denied', 'csrf_rejected',
        'session_hijack_blocked', 'reauth_failed', 'password_change_failed', 'ticket_cancel_denied', 'register_rate_limited',
    ];

    private const GENESIS = '0000000000000000000000000000000000000000000000000000000000000000';

    public static function record(string $event, string $details = '', ?int $userId = null, ?string $username = null): void
    {
        $pdo = Database::connection();
        try {
            $row = [
                'user_id'    => $userId ?? (isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null),
                'username'   => self::clean($username ?? ($_SESSION['username'] ?? null), 254),
                'event'      => $event,
                'details'    => self::clean($details, 500),
                'ip'         => client_ip(),
                'user_agent' => self::clean($_SERVER['HTTP_USER_AGENT'] ?? '', 255),
                'created_at' => date('Y-m-d H:i:s'),
            ];

            // A named lock serialises writers so two simultaneous requests can never fork the chain.
            $pdo->query("SELECT GET_LOCK('helpdesk_audit_chain', 5)")->fetchColumn();
            $pdo->beginTransaction();
            $prev = $pdo->query('SELECT row_hash FROM audit_log ORDER BY id DESC LIMIT 1 FOR UPDATE')->fetchColumn() ?: self::GENESIS;
            $pdo->prepare('INSERT INTO audit_log (user_id, username, event, details, ip, user_agent, created_at, row_hash)
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
                ->execute([
                    $row['user_id'], $row['username'], $row['event'], $row['details'],
                    $row['ip'], $row['user_agent'], $row['created_at'], self::hash($row, $prev),
                ]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Audit log failed: ' . $e->getMessage());
        } finally {
            $pdo->query("SELECT RELEASE_LOCK('helpdesk_audit_chain')")->fetchColumn();
        }
    }

    /**
     * Valid UTF-8, cut to the column size. The stored value must be byte-for-byte what was
     * hashed, otherwise the integrity check would report tampering that never happened.
     */
    private static function clean(?string $value, int $max): ?string
    {
        return $value === null ? null : mb_substr(mb_scrub($value, 'UTF-8'), 0, $max);
    }

    /**
     * Returns null when the chain is intact, otherwise the ID of the first entry that no longer matches.
     */
    public static function verifyChain(): ?int
    {
        $prev = self::GENESIS;
        $stmt = Database::connection()->query('SELECT * FROM audit_log ORDER BY id ASC');
        while ($row = $stmt->fetch()) {
            if (!hash_equals(self::hash($row, $prev), (string) $row['row_hash'])) {
                return (int) $row['id'];
            }
            $prev = $row['row_hash'];
        }
        return null;
    }

    private static function hash(array $row, string $prevHash): string
    {
        $payload = implode('|', [
            $prevHash, $row['user_id'] ?? '', $row['username'] ?? '', $row['event'],
            $row['details'], $row['ip'], $row['user_agent'], $row['created_at'],
        ]);
        return hash_hmac('sha256', $payload, Secrets::get('encryption_key'));
    }

    /* ------------------------------------------------------------------
     * Read queries used by the dashboards and the audit log page
     * --------------------------------------------------------------- */

    /** @return array{0: array, 1: int} rows and total count */
    public static function search(string $event, string $q, int $page, int $perPage): array
    {
        $where = [];
        $params = [];
        if ($event !== '') {
            $where[] = 'event = ?';
            $params[] = $event;
        }
        if ($q !== '') {
            $where[] = '(username LIKE ? OR ip LIKE ? OR details LIKE ?)';
            $like = '%' . addcslashes($q, '%_\\') . '%';
            array_push($params, $like, $like, $like);
        }
        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $pdo = Database::connection();

        $count = $pdo->prepare("SELECT COUNT(*) FROM audit_log $whereSql");
        $count->execute($params);
        $total = (int) $count->fetchColumn();

        $offset = max(0, ($page - 1) * $perPage);
        $stmt = $pdo->prepare("SELECT * FROM audit_log $whereSql ORDER BY id DESC LIMIT " . (int) $perPage . ' OFFSET ' . (int) $offset);
        $stmt->execute($params);
        return [$stmt->fetchAll(), $total];
    }

    public static function eventNames(): array
    {
        return Database::connection()->query('SELECT DISTINCT event FROM audit_log ORDER BY event')->fetchAll(PDO::FETCH_COLUMN);
    }

    public static function summary(): array
    {
        $in = implode(',', array_fill(0, count(self::WARNING_EVENTS), '?'));
        $stmt = Database::connection()->prepare(
            "SELECT COUNT(*) AS total,
                    COALESCE(SUM(created_at > NOW() - INTERVAL 1 DAY), 0) AS day,
                    COALESCE(SUM(created_at > NOW() - INTERVAL 1 DAY AND event IN ($in)), 0) AS warnings,
                    COALESCE(SUM(created_at > NOW() - INTERVAL 1 DAY AND event IN ('login_failed', '2fa_failed')), 0) AS failed_logins,
                    COALESCE(SUM(created_at > NOW() - INTERVAL 1 DAY AND event = 'account_locked'), 0) AS lockouts
             FROM audit_log"
        );
        $stmt->execute(self::WARNING_EVENTS);
        return array_map('intval', $stmt->fetch());
    }

    public static function recentWarnings(int $limit = 6): array
    {
        $in = implode(',', array_fill(0, count(self::WARNING_EVENTS), '?'));
        $stmt = Database::connection()->prepare("SELECT event, username, ip, created_at FROM audit_log WHERE event IN ($in) ORDER BY id DESC LIMIT " . (int) $limit);
        $stmt->execute(self::WARNING_EVENTS);
        return $stmt->fetchAll();
    }

    public static function recentForUser(int $userId, int $limit = 6): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT event, ip, created_at FROM audit_log
             WHERE user_id = ? AND event IN ('login_success', 'login_success_2fa', 'login_success_recovery_code', 'login_failed', 'password_changed', '2fa_enabled', '2fa_disabled')
             ORDER BY id DESC LIMIT " . (int) $limit
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }
}
