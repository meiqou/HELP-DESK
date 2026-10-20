<?php

namespace App\Repositories;

use App\Core\Database;
use App\Domain\Ticket;
use PDO;

/**
 * All SQL that touches the tickets table. Ownership checks are part of the
 * queries themselves, so a user can never reach another user's ticket by ID.
 */
final class TicketRepository
{
    private static function db(): PDO
    {
        return Database::connection();
    }

    public static function create(int $userId, string $category, string $priority, string $subject, string $description): int
    {
        self::db()->prepare("INSERT INTO tickets (user_id, category, priority, subject, description, status) VALUES (?, ?, ?, ?, ?, 'Open')")
            ->execute([$userId, $category, $priority, $subject, $description]);
        return (int) self::db()->lastInsertId();
    }

    public static function find(int $id): ?array
    {
        $stmt = self::db()->prepare('SELECT * FROM tickets WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /** @return array<string,int> counts keyed by status, for one user or for everyone */
    public static function statusCounts(?int $userId = null): array
    {
        $counts = array_fill_keys(Ticket::STATUSES, 0);
        if ($userId === null) {
            $rows = self::db()->query('SELECT status, COUNT(*) AS total FROM tickets GROUP BY status')->fetchAll();
        } else {
            $stmt = self::db()->prepare('SELECT status, COUNT(*) AS total FROM tickets WHERE user_id = ? GROUP BY status');
            $stmt->execute([$userId]);
            $rows = $stmt->fetchAll();
        }
        foreach ($rows as $row) {
            $counts[$row['status']] = (int) $row['total'];
        }
        return $counts;
    }

    public static function recentForUser(int $userId, int $limit = 4): array
    {
        $stmt = self::db()->prepare('SELECT id, subject, category, status, created_at FROM tickets WHERE user_id = ? ORDER BY created_at DESC, id DESC LIMIT ' . (int) $limit);
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public static function forUser(int $userId, string $status = ''): array
    {
        $sql = 'SELECT * FROM tickets WHERE user_id = ?';
        $params = [$userId];
        if ($status !== '') {
            $sql .= ' AND status = ?';
            $params[] = $status;
        }
        $stmt = self::db()->prepare($sql . ' ORDER BY created_at DESC, id DESC');
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Withdraws a ticket only if it belongs to the user and nobody has started on it.
     */
    public static function withdraw(int $ticketId, int $userId): bool
    {
        $stmt = self::db()->prepare("DELETE FROM tickets WHERE id = ? AND user_id = ? AND status = 'Open'");
        $stmt->execute([$ticketId, $userId]);
        return $stmt->rowCount() === 1;
    }

    public static function update(int $ticketId, string $status, string $priority, int $staffId): void
    {
        self::db()->prepare('UPDATE tickets SET status = ?, priority = ?, assigned_to = COALESCE(assigned_to, ?) WHERE id = ?')
            ->execute([$status, $priority, $staffId, $ticketId]);
    }

    public static function claim(int $ticketId, int $staffId): void
    {
        self::db()->prepare("UPDATE tickets SET assigned_to = ?, status = IF(status = 'Open', 'In Progress', status) WHERE id = ?")
            ->execute([$staffId, $ticketId]);
    }

    public static function queueSummary(): array
    {
        $row = self::db()->query(
            "SELECT COALESCE(SUM(status = 'Open'), 0) AS open,
                    COALESCE(SUM(status = 'In Progress'), 0) AS in_progress,
                    COALESCE(SUM(status = 'Resolved'), 0) AS resolved,
                    COALESCE(SUM(status <> 'Resolved' AND assigned_to IS NULL), 0) AS unassigned,
                    COALESCE(SUM(status <> 'Resolved' AND priority IN ('High', 'Critical')), 0) AS urgent
             FROM tickets"
        )->fetch();
        return array_map('intval', $row);
    }

    /**
     * Filtered, paginated queue for technicians. Filter values are whitelisted
     * by the caller and always bound as parameters.
     *
     * @return array{0: array, 1: int} rows and total count
     */
    public static function search(array $filters, int $page, int $perPage): array
    {
        $where = [];
        $params = [];
        if ($filters['status'] !== '') {
            $where[] = 't.status = ?';
            $params[] = $filters['status'];
        }
        if ($filters['priority'] !== '') {
            $where[] = 't.priority = ?';
            $params[] = $filters['priority'];
        }
        if ($filters['assignee'] !== null) {
            $where[] = 't.assigned_to = ?';
            $params[] = $filters['assignee'];
        }
        if ($filters['q'] !== '') {
            $where[] = '(t.subject LIKE ? OR t.description LIKE ? OR u.username LIKE ? OR t.category LIKE ?)';
            $like = '%' . addcslashes($filters['q'], '%_\\') . '%';
            array_push($params, $like, $like, $like, $like);
        }
        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $count = self::db()->prepare("SELECT COUNT(*) FROM tickets t JOIN users u ON u.id = t.user_id $whereSql");
        $count->execute($params);
        $total = (int) $count->fetchColumn();

        $offset = max(0, ($page - 1) * $perPage);
        $stmt = self::db()->prepare(
            "SELECT t.*, u.username, a.username AS assignee
             FROM tickets t
             JOIN users u ON u.id = t.user_id
             LEFT JOIN users a ON a.id = t.assigned_to
             $whereSql
             ORDER BY FIELD(t.status, 'Open', 'In Progress', 'Resolved'),
                      FIELD(t.priority, 'Critical', 'High', 'Normal', 'Low'),
                      t.created_at DESC
             LIMIT " . (int) $perPage . ' OFFSET ' . (int) $offset
        );
        $stmt->execute($params);
        return [$stmt->fetchAll(), $total];
    }
}
