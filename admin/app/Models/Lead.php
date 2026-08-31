<?php
namespace App\Models;

use App\Core\Model;

/**
 * Enquiries from the public contact form (stored in the `leads` table).
 *
 * Beyond the dashboard summary counts, this model backs the Enquiry
 * Management module: listing, filtering, and the Important / Client flags
 * that admins and managers toggle.
 */
class Lead extends Model
{
    /** Statuses an enquiry can move through. */
    public const STATUSES = ['new', 'contacted', 'quoted', 'won', 'lost'];

    // ---------- Dashboard summaries ----------

    public function totalCount(): int
    {
        $row = $this->one('SELECT COUNT(*) AS c FROM leads');
        return (int) ($row['c'] ?? 0);
    }

    public function countByStatus(string $status): int
    {
        $row = $this->one('SELECT COUNT(*) AS c FROM leads WHERE status = ?', [$status]);
        return (int) ($row['c'] ?? 0);
    }

    /** How many enquiries are flagged important / from clients. */
    public function countFlag(string $flag): int
    {
        $flag = $flag === 'is_client' ? 'is_client' : 'is_important';
        $row  = $this->one("SELECT COUNT(*) AS c FROM leads WHERE $flag = 1");
        return (int) ($row['c'] ?? 0);
    }

    public function recent(int $limit = 5): array
    {
        // LIMIT can't be a bound param in MySQL prepared statements, so cast to int.
        $limit = max(1, min(50, $limit));
        return $this->all(
            "SELECT id, name, email, phone, subject, status, is_important, is_client, is_read, created_at
             FROM leads ORDER BY created_at DESC LIMIT $limit"
        );
    }

    /** How many enquiries have never been opened yet (the red-dot count). */
    public function countUnread(): int
    {
        $row = $this->one('SELECT COUNT(*) AS c FROM leads WHERE is_read = 0');
        return (int) ($row['c'] ?? 0);
    }

    // ---------- Enquiry management ----------

    /**
     * List enquiries, newest first, with an optional filter.
     * $filter: 'all' | 'important' | 'client' | one of STATUSES.
     * Important enquiries are floated to the top of the list.
     */
    public function list(string $filter = 'all'): array
    {
        $where  = '';
        $params = [];

        if ($filter === 'important') {
            $where = 'WHERE is_important = 1';
        } elseif ($filter === 'client') {
            $where = 'WHERE is_client = 1';
        } elseif (in_array($filter, self::STATUSES, true)) {
            $where  = 'WHERE status = ?';
            $params = [$filter];
        }

        return $this->all(
            "SELECT id, name, email, phone, subject, message, status, is_important, is_client, is_read, notes, created_at
             FROM leads $where
             ORDER BY is_important DESC, created_at DESC",
            $params
        );
    }

    /** A single enquiry (or null). */
    public function find(int $id): ?array
    {
        return $this->one(
            'SELECT id, name, email, phone, subject, message, status, is_important, is_client, is_read, notes, created_at
             FROM leads WHERE id = ? LIMIT 1',
            [$id]
        );
    }

    /** Mark an enquiry as opened — clears its red "new" dot. */
    public function markRead(int $id): void
    {
        $this->run('UPDATE leads SET is_read = 1 WHERE id = ? AND is_read = 0', [$id]);
    }

    /** Flip the Important star on/off and return the new state. */
    public function toggleImportant(int $id): void
    {
        $this->run('UPDATE leads SET is_important = 1 - is_important WHERE id = ?', [$id]);
    }

    /** Flip the "this is a client" flag (green) on/off. */
    public function toggleClient(int $id): void
    {
        $this->run('UPDATE leads SET is_client = 1 - is_client WHERE id = ?', [$id]);
    }

    /** Move an enquiry to a new status. */
    public function updateStatus(int $id, string $status): void
    {
        $status = in_array($status, self::STATUSES, true) ? $status : 'new';
        $this->run('UPDATE leads SET status = ? WHERE id = ?', [$status, $id]);
    }

    /** Permanently remove an enquiry. */
    public function delete(int $id): void
    {
        $this->run('DELETE FROM leads WHERE id = ?', [$id]);
    }

    // ---------- Public capture ----------

    /** Save a new enquiry coming from the public contact form. */
    public function create(string $name, string $email, string $phone, string $subject, string $message): int
    {
        $this->run(
            'INSERT INTO leads (name, email, phone, subject, message, status, is_read) VALUES (?, ?, ?, ?, ?, ?, 0)',
            [$name, $email, $phone, $subject, $message, 'new']
        );
        return (int) $this->db->lastInsertId();
    }
}
