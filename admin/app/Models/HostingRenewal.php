<?php
namespace App\Models;

use App\Core\Model;

/**
 * One entry in a hosting/domain record's renewal history.
 *
 * Each row is a renewal that actually happened: when it was renewed, what it
 * cost, whether that was paid, and which expiry date it moved (previous ->
 * new). Together with the service's purchase_date these rows answer
 * "purchased 2025, renewed 2026, next due 2027" for any client.
 */
class HostingRenewal extends Model
{
    /** Payment states a renewal can be recorded in. */
    public const PAYMENT_STATUSES = ['paid', 'partial', 'unpaid'];

    /** The full history of one service, newest renewal first. */
    public function forService(int $hostingId): array
    {
        return $this->all(
            'SELECT r.*, u.name AS recorded_by
             FROM hosting_renewals r
             LEFT JOIN users u ON u.id = r.created_by
             WHERE r.hosting_id = ?
             ORDER BY r.renewal_date DESC, r.id DESC',
            [$hostingId]
        );
    }

    public function find(int $id): ?array
    {
        return $this->one('SELECT * FROM hosting_renewals WHERE id = ? LIMIT 1', [$id]);
    }

    /** Record a renewal. Returns its new id. */
    public function add(int $hostingId, array $d, ?int $createdBy): int
    {
        $status = in_array($d['payment_status'], self::PAYMENT_STATUSES, true) ? $d['payment_status'] : 'paid';

        $this->run(
            'INSERT INTO hosting_renewals
               (hosting_id, renewal_date, previous_expiry, new_expiry, amount,
                payment_status, payment_reference, notes, created_by)
             VALUES (?,?,?,?,?,?,?,?,?)',
            [
                $hostingId,
                $d['renewal_date'],
                $d['previous_expiry'] ?: null,
                $d['new_expiry'],
                $d['amount'] === '' ? null : (float) $d['amount'],
                $status,
                $d['payment_reference'] ?: null,
                $d['notes'] ?: null,
                $createdBy,
            ]
        );
        return (int) $this->db->lastInsertId();
    }

    /** Remove a history entry (used to undo a mis-typed renewal). */
    public function delete(int $id): void
    {
        $this->run('DELETE FROM hosting_renewals WHERE id = ?', [$id]);
    }

    /** Total renewal money recorded against one service. */
    public function totalForService(int $hostingId): float
    {
        $row = $this->one(
            'SELECT COALESCE(SUM(amount), 0) AS t FROM hosting_renewals WHERE hosting_id = ?',
            [$hostingId]
        );
        return (float) ($row['t'] ?? 0);
    }
}
