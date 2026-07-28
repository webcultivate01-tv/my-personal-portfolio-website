<?php
namespace App\Models;

use App\Core\Model;

/**
 * Invoices raised against a client.
 */
class ClientInvoice extends Model
{
    /** Statuses an invoice can move through. */
    public const STATUSES = ['unpaid', 'paid', 'overdue', 'cancelled'];

    /** All invoices for one client, newest first. */
    public function forClient(int $clientId): array
    {
        return $this->all(
            'SELECT * FROM client_invoices WHERE client_id = ? ORDER BY issue_date DESC, id DESC',
            [$clientId]
        );
    }

    public function find(int $id): ?array
    {
        return $this->one('SELECT * FROM client_invoices WHERE id = ? LIMIT 1', [$id]);
    }

    /** Raise a new invoice. Returns its new id. */
    public function add(int $clientId, string $number, float $amount, string $issueDate, string $dueDate, string $notes): int
    {
        $this->run(
            'INSERT INTO client_invoices (client_id, invoice_number, amount, issue_date, due_date, notes)
             VALUES (?, ?, ?, ?, ?, ?)',
            [$clientId, $number, $amount, $issueDate, $dueDate ?: null, $notes ?: null]
        );
        return (int) $this->db->lastInsertId();
    }

    /** Move an invoice to a new status. */
    public function updateStatus(int $id, string $status): void
    {
        $status = in_array($status, self::STATUSES, true) ? $status : 'unpaid';
        $this->run('UPDATE client_invoices SET status = ? WHERE id = ?', [$status, $id]);
    }

    /** Permanently remove an invoice. */
    public function delete(int $id): void
    {
        $this->run('DELETE FROM client_invoices WHERE id = ?', [$id]);
    }

    /** Total invoiced to a client (cancelled invoices don't count). */
    public function totalForClient(int $clientId): float
    {
        $row = $this->one(
            "SELECT COALESCE(SUM(amount), 0) AS t FROM client_invoices WHERE client_id = ? AND status != 'cancelled'",
            [$clientId]
        );
        return (float) ($row['t'] ?? 0);
    }
}
