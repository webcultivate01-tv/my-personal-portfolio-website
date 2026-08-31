<?php
namespace App\Models;

use App\Core\Model;

/**
 * One invoice per billing cycle of a monthly client.
 *
 * Everything on an invoice is frozen when it is generated — the service text,
 * the rate, the discount, the tax and the total — so it stays a correct
 * historical document even after the client's rate changes.
 *
 * What is NOT stored is how much has been paid and where the invoice stands.
 * Both are worked out from monthly_payments and the due date every time it is
 * read, which is what makes "paid / partially paid / overdue" automatic:
 *
 *     cancelled by hand           -> Cancelled
 *     balance <= 0                -> Paid
 *     still a draft               -> Draft
 *     due date in the past        -> Overdue
 *     something paid, not all     -> Partially Paid
 *     otherwise                   -> Sent
 *
 * An invoice that is both part-paid and past due reads as Overdue (the more
 * urgent of the two), but still answers the "Partially Paid" filter — the
 * is_partial flag on every decorated row is what that filter matches.
 */
class MonthlyInvoice extends Model
{
    /** The lifecycle values actually stored in the status column. */
    public const LIFECYCLE = ['draft', 'sent', 'cancelled'];

    /** Every status an invoice can be shown as. */
    public const STATUSES = ['draft', 'sent', 'partially_paid', 'paid', 'overdue', 'cancelled'];

    public const STATUS_LABELS = [
        'draft'          => 'Draft',
        'sent'           => 'Sent',
        'partially_paid' => 'Partially Paid',
        'paid'           => 'Paid',
        'overdue'        => 'Overdue',
        'cancelled'      => 'Cancelled',
    ];

    /** The due/overdue buckets the dashboard slices unpaid invoices into. */
    public const BUCKETS = [
        'overdue'        => 'Overdue',
        'due_today'      => 'Due today',
        'due_week'       => 'Due this week',
        'upcoming'       => 'Upcoming',
        'partially_paid' => 'Partially paid',
        'paid'           => 'Paid',
    ];

    /** Total paid against an invoice, as a SQL expression over the `i` alias. */
    private const PAID_SQL = "COALESCE((SELECT SUM(p.amount) FROM monthly_payments p WHERE p.invoice_id = i.id), 0)";

    /** What is still owed on an invoice, never below zero. */
    private const BALANCE_SQL = "GREATEST(i.total_amount - " . self::PAID_SQL . ", 0)";

    /** Every read pulls the client's identity along with the invoice. */
    private const SELECT = "SELECT i.*, " . self::PAID_SQL . " AS amount_paid,
                                   mc.client_name, mc.company, mc.email, mc.mobile, mc.billing_address,
                                   mc.billing_frequency, mc.payment_method, mc.payment_terms, mc.status AS client_status
                            FROM monthly_invoices i
                            JOIN monthly_clients mc ON mc.id = i.monthly_client_id";

    // ---------------- Derived fields ----------------

    /** Attach balance, effective status and the overdue countdown onto a row. */
    public static function decorate(array $row): array
    {
        $total = (float) $row['total_amount'];
        $paid  = (float) ($row['amount_paid'] ?? 0);

        $balance = round(max($total - $paid, 0), 2);
        $stored  = (string) $row['status'];
        $late    = $stored === 'sent' && $balance > 0.004 && strtotime((string) $row['due_date']) < strtotime('today');

        if ($stored === 'cancelled') {
            $status = 'cancelled';
        } elseif ($balance <= 0.004 && $total > 0) {
            $status = 'paid';
        } elseif ($stored === 'draft') {
            $status = 'draft';
        } elseif ($late) {
            $status = 'overdue';
        } elseif ($paid > 0.004) {
            $status = 'partially_paid';
        } else {
            $status = 'sent';
        }

        $days = MonthlyClient::daysUntil((string) $row['due_date']);

        $row['amount_paid']    = round($paid, 2);
        $row['balance_due']    = $balance;
        $row['display_status'] = $status;
        $row['status_label']   = self::STATUS_LABELS[$status];
        $row['is_partial']     = $paid > 0.004 && $balance > 0.004;
        $row['is_overdue']     = $late;
        $row['days_to_due']    = $days;
        $row['days_overdue']   = $late && $days !== null ? abs($days) : 0;
        $row['period_label']   = date('j M Y', strtotime((string) $row['period_start']))
                               . ' – ' . date('j M Y', strtotime((string) $row['period_end']));

        return $row;
    }

    /** @param array<int,array> $rows */
    private static function decorateAll(array $rows): array
    {
        return array_map([self::class, 'decorate'], $rows);
    }

    // ---------------- Reads ----------------

    /** Every invoice raised for one client, newest period first. */
    public function forClient(int $clientId): array
    {
        return self::decorateAll($this->all(
            self::SELECT . ' WHERE i.monthly_client_id = ? ORDER BY i.period_start DESC, i.id DESC',
            [$clientId]
        ));
    }

    /** One invoice with everything needed to print it. */
    public function find(int $id): ?array
    {
        $row = $this->one(self::SELECT . ' WHERE i.id = ? LIMIT 1', [$id]);
        return $row === null ? null : self::decorate($row);
    }

    /** The invoice covering a given billing period, if one was already raised. */
    public function findForPeriod(int $clientId, string $periodStart): ?array
    {
        return $this->one(
            'SELECT * FROM monthly_invoices WHERE monthly_client_id = ? AND period_start = ? LIMIT 1',
            [$clientId, $periodStart]
        );
    }

    /**
     * Unpaid (or recently settled) invoices in one of the dashboard buckets.
     * Ordered by due date so the most pressing sits at the top.
     */
    public function bucket(string $bucket, int $limit = 50): array
    {
        $paid    = self::PAID_SQL;
        $balance = self::BALANCE_SQL;
        $unpaid  = "i.status = 'sent' AND $balance > 0";

        switch ($bucket) {
            case 'overdue':
                $where = "$unpaid AND i.due_date < CURDATE()";
                $order = 'i.due_date ASC';
                break;
            case 'due_today':
                $where = "$unpaid AND i.due_date = CURDATE()";
                $order = 'i.due_date ASC';
                break;
            case 'due_week':
                $where = "$unpaid AND i.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
                $order = 'i.due_date ASC';
                break;
            case 'upcoming':
                $where = "$unpaid AND i.due_date > CURDATE()";
                $order = 'i.due_date ASC';
                break;
            case 'partially_paid':
                $where = "i.status <> 'cancelled' AND $paid > 0 AND $paid < i.total_amount";
                $order = 'i.due_date ASC';
                break;
            case 'paid':
                $where = "i.status <> 'cancelled' AND $paid >= i.total_amount";
                $order = 'i.invoice_date DESC';
                break;
            default:
                $where = '1 = 1';
                $order = 'i.invoice_date DESC';
        }

        return self::decorateAll($this->all(
            self::SELECT . " WHERE $where ORDER BY $order, i.id DESC LIMIT " . (int) $limit
        ));
    }

    /** How many invoices sit in each dashboard bucket, for the tab counts. */
    public function bucketCounts(): array
    {
        $out = [];
        foreach (array_keys(self::BUCKETS) as $key) {
            $out[$key] = count($this->bucket($key, 1000));
        }
        return $out;
    }

    /** The newest invoices across every client, for the dashboard table. */
    public function recent(int $limit = 8): array
    {
        return self::decorateAll($this->all(
            self::SELECT . ' ORDER BY i.invoice_date DESC, i.id DESC LIMIT ' . (int) $limit
        ));
    }

    // ---------------- Writes ----------------

    /**
     * Raise an invoice for one billing period. Returns its new id.
     *
     * The unique key on (monthly_client_id, period_start) is the real guard
     * against a duplicate invoice for the same period — the controller checks
     * first for a friendly message, this makes it impossible either way.
     */
    public function create(array $d, ?int $createdBy): int
    {
        $status = in_array($d['status'], self::LIFECYCLE, true) ? $d['status'] : 'sent';

        $this->run(
            'INSERT INTO monthly_invoices
               (monthly_client_id, invoice_number, invoice_date, due_date, period_start, period_end,
                service_name, service_description, recurring_amount, discount_amount, tax_percent,
                tax_amount, total_amount, status, notes, created_by)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [
                $d['monthly_client_id'],
                $this->nextInvoiceNumber(),
                $d['invoice_date'],
                $d['due_date'],
                $d['period_start'],
                $d['period_end'],
                $d['service_name'],
                $d['service_description'] ?: null,
                $d['recurring_amount'],
                $d['discount_amount'],
                $d['tax_percent'],
                $d['tax_amount'],
                $d['total_amount'],
                $status,
                $d['notes'] ?: null,
                $createdBy,
            ]
        );
        return (int) $this->db->lastInsertId();
    }

    /**
     * Move an invoice between the statuses a human controls (draft, sent,
     * cancelled). Paid / partially paid / overdue are never set by hand —
     * they follow from the payments recorded against it.
     */
    public function updateStatus(int $id, string $status): void
    {
        $status = in_array($status, self::LIFECYCLE, true) ? $status : 'sent';
        $this->run('UPDATE monthly_invoices SET status = ? WHERE id = ?', [$status, $id]);
    }

    /**
     * Next invoice number for the current year: MC-2026-001, MC-2026-002, …
     * The highest existing sequence is read rather than the row count, so a
     * number is never handed out twice.
     */
    private function nextInvoiceNumber(): string
    {
        $year = date('Y');
        $row  = $this->one(
            'SELECT invoice_number FROM monthly_invoices WHERE invoice_number LIKE ? ORDER BY invoice_number DESC LIMIT 1',
            ['MC-' . $year . '-%']
        );

        $seq = 1;
        if ($row !== null && preg_match('/-(\d+)$/', (string) $row['invoice_number'], $m)) {
            $seq = (int) $m[1] + 1;
        }

        return 'MC-' . $year . '-' . str_pad((string) $seq, 3, '0', STR_PAD_LEFT);
    }
}
