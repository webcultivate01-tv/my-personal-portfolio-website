<?php
namespace App\Models;

use App\Core\Model;

/**
 * Internal notes on an enquiry — a running timeline (`lead_notes`).
 *
 * Every save adds a new row instead of overwriting the last one, so the
 * whole team can see who said what and when.
 */
class LeadNote extends Model
{
    /** All notes for one enquiry, newest first, with the author's name. */
    public function forLead(int $leadId): array
    {
        return $this->all(
            'SELECT n.id, n.note, n.created_at, u.name AS author
             FROM lead_notes n
             LEFT JOIN users u ON u.id = n.user_id
             WHERE n.lead_id = ?
             ORDER BY n.created_at DESC, n.id DESC',
            [$leadId]
        );
    }

    /** Append a note to an enquiry's timeline. */
    public function add(int $leadId, ?int $userId, string $note): void
    {
        $this->run(
            'INSERT INTO lead_notes (lead_id, user_id, note) VALUES (?, ?, ?)',
            [$leadId, $userId, $note]
        );
    }

    /** How many notes an enquiry has. */
    public function countFor(int $leadId): int
    {
        $row = $this->one('SELECT COUNT(*) AS c FROM lead_notes WHERE lead_id = ?', [$leadId]);
        return (int) ($row['c'] ?? 0);
    }
}
