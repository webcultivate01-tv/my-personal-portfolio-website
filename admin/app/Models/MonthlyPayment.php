<?php
namespace App\Models;

use App\Core\Model;

/**
 * A payment taken against a monthly client's invoice.
 *
 * A partial payment is nothing special — it is simply a smaller amount, and
 * several payments can sit against one invoice. The invoice's balance is
 * always its total minus the sum of these rows, so recording a payment is all
 * it takes for an invoice to become partially paid, then paid.
 *
 * Every payment gets its own receipt number the moment it is recorded, and
 * freezes the balance that was left at that point (balance_after) so a
 * receipt reprinted months later still shows what the client was told on the
 * day.
 */
class MonthlyPayment extends Model
{
    /** How a payment can come in. */
    public const METHODS = ['upi', 'bank_transfer', 'cash', 'card', 'other'];

    /** Every read carries the invoice and client identity a receipt needs. */
    private const SELECT = 'SELECT pm.*, i.invoice_number, i.invoice_date, i.due_date, i.total_amount,
                                   i.period_start, i.period_end, i.service_name, i.service_description,
                                   mc.client_name, mc.company, mc.email, mc.mobile, mc.billing_address,
                                   u.name AS recorded_by
                            FROM monthly_payments pm
                            JOIN monthly_invoices i  ON i.id  = pm.invoice_id
                            JOIN monthly_clients mc  ON mc.id = pm.monthly_client_id
                            LEFT JOIN users u        ON u.id  = pm.created_by';

    /** Human label for a payment method. */
    public static function methodLabel(string $m): string
    {
        return $m === 'upi' ? 'UPI' : ucwords(str_replace('_', ' ', $m));
    }

    // ---------------- Reads ----------------

    /** Every payment one client has ever made, newest first. */
    public function forClient(int $clientId): array
    {
        return $this->all(
            self::SELECT . ' WHERE pm.monthly_client_id = ? ORDER BY pm.payment_date DESC, pm.id DESC',
            [$clientId]
        );
    }

    /** Every payment recorded against one invoice, oldest first (the order they were taken). */
    public function forInvoice(int $invoiceId): array
    {
        return $this->all(
            self::SELECT . ' WHERE pm.invoice_id = ? ORDER BY pm.payment_date ASC, pm.id ASC',
            [$invoiceId]
        );
    }

    /** One payment, with everything its receipt needs printed on it. */
    public function find(int $id): ?array
    {
        return $this->one(self::SELECT . ' WHERE pm.id = ? LIMIT 1', [$id]);
    }

    /** The newest payments across every client, for the dashboard table. */
    public function recent(int $limit = 8): array
    {
        return $this->all(self::SELECT . ' ORDER BY pm.payment_date DESC, pm.id DESC LIMIT ' . (int) $limit);
    }

    /** Total already paid against one invoice. */
    public function totalForInvoice(int $invoiceId): float
    {
        $row = $this->one('SELECT COALESCE(SUM(amount), 0) AS t FROM monthly_payments WHERE invoice_id = ?', [$invoiceId]);
        return (float) ($row['t'] ?? 0);
    }

    // ---------------- Writes ----------------

    /**
     * Record a payment and hand it a receipt number. $balanceAfter is what is
     * still owed on the invoice once this payment is counted — the caller works
     * it out, because it is the same figure it validated the amount against.
     *
     * Returns the new payment id.
     */
    public function create(array $d, float $balanceAfter, ?int $createdBy): int
    {
        $method = in_array($d['method'], self::METHODS, true) ? $d['method'] : 'other';

        $this->run(
            'INSERT INTO monthly_payments
               (invoice_id, monthly_client_id, receipt_number, payment_date, amount, method,
                reference, notes, balance_after, created_by)
             VALUES (?,?,?,?,?,?,?,?,?,?)',
            [
                $d['invoice_id'],
                $d['monthly_client_id'],
                $this->nextReceiptNumber(),
                $d['payment_date'],
                $d['amount'],
                $method,
                $d['reference'] ?: null,
                $d['notes'] ?: null,
                round($balanceAfter, 2),
                $createdBy,
            ]
        );
        return (int) $this->db->lastInsertId();
    }

    /**
     * Next receipt number for the current year: RCP-2026-001, RCP-2026-002, …
     * Read from the highest existing sequence, so a deleted payment never lets
     * a receipt number be reused.
     */
    private function nextReceiptNumber(): string
    {
        $year = date('Y');
        $row  = $this->one(
            'SELECT receipt_number FROM monthly_payments WHERE receipt_number LIKE ? ORDER BY receipt_number DESC LIMIT 1',
            ['RCP-' . $year . '-%']
        );

        $seq = 1;
        if ($row !== null && preg_match('/-(\d+)$/', (string) $row['receipt_number'], $m)) {
            $seq = (int) $m[1] + 1;
        }

        return 'RCP-' . $year . '-' . str_pad((string) $seq, 3, '0', STR_PAD_LEFT);
    }
}
