<?php
namespace App\Models;

use App\Core\Model;

/**
 * Meetings held with a client — a running timeline (date + time together
 * in meeting_at), so "how many meetings have we had" is a plain COUNT(*).
 */
class ClientMeeting extends Model
{
    /** All meetings for one client, most recent first. */
    public function forClient(int $clientId): array
    {
        return $this->all(
            'SELECT m.*, u.name AS created_by_name
             FROM client_meetings m
             LEFT JOIN users u ON u.id = m.created_by
             WHERE m.client_id = ?
             ORDER BY m.meeting_at DESC, m.id DESC',
            [$clientId]
        );
    }

    /** How many meetings have been logged for a client. */
    public function countForClient(int $clientId): int
    {
        $row = $this->one('SELECT COUNT(*) AS c FROM client_meetings WHERE client_id = ?', [$clientId]);
        return (int) ($row['c'] ?? 0);
    }

    /** Log a meeting. $meetingAt is a 'Y-m-d H:i:s' string. */
    public function add(int $clientId, string $meetingAt, ?int $duration, string $notes, ?int $userId): void
    {
        $this->run(
            'INSERT INTO client_meetings (client_id, meeting_at, duration_minutes, notes, created_by)
             VALUES (?, ?, ?, ?, ?)',
            [$clientId, $meetingAt, $duration, $notes ?: null, $userId]
        );
    }

    /** Remove a logged meeting. */
    public function delete(int $id): void
    {
        $this->run('DELETE FROM client_meetings WHERE id = ?', [$id]);
    }
}
