<?php
namespace App\Models;

use App\Core\Model;

/**
 * Payments received from a client, optionally tied to one invoice.
 */
class ClientPayment extends Model
{
    /** How the money came in. */
    public const METHODS = ['cash', 'bank_transfer', 'upi', 'card', 'cheque', 'other'];

    /** All payments for one client, newest first, with the invoice number when linked. */
    public function forClient(int $clientId): array
    {
        return $this->all(
            'SELECT p.*, i.invoice_number
             FROM client_payments p
             LEFT JOIN client_invoices i ON i.id = p.invoice_id
             WHERE p.client_id = ?
             ORDER BY p.payment_date DESC, p.id DESC',
            [$clientId]
        );
    }

    /** Record a payment. $invoiceId is null when it isn't tied to an invoice. Returns its new id. */
    public function add(int $clientId, ?int $invoiceId, float $amount, string $date, string $method, string $notes): int
    {
        $method = in_array($method, self::METHODS, true) ? $method : 'other';
        $this->run(
            'INSERT INTO client_payments (client_id, invoice_id, amount, payment_date, method, notes)
             VALUES (?, ?, ?, ?, ?, ?)',
            [$clientId, $invoiceId, $amount, $date, $method, $notes ?: null]
        );
        return (int) $this->db->lastInsertId();
    }

    /** Remove a recorded payment. */
    public function delete(int $id): void
    {
        $this->run('DELETE FROM client_payments WHERE id = ?', [$id]);
    }

    /** Total received from a client. */
    public function totalForClient(int $clientId): float
    {
        $row = $this->one('SELECT COALESCE(SUM(amount), 0) AS t FROM client_payments WHERE client_id = ?', [$clientId]);
        return (float) ($row['t'] ?? 0);
    }
}
