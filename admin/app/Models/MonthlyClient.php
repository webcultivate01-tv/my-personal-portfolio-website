<?php
namespace App\Models;

use App\Core\Model;
use DateTimeImmutable;

/**
 * A recurring ("monthly") client on a retainer: they pay the same amount
 * every billing cycle for as long as the contract runs.
 *
 * Only the lifecycle an admin actually chooses is stored — active, paused or
 * cancelled. "Payment Due" and "Overdue" are never written to the database:
 * they are worked out from the client's unpaid invoices every time a page is
 * drawn, so a client's status can never go stale (the same approach the
 * Hosting module takes with renewal dates).
 *
 *     cancelled                      -> Cancelled
 *     paused                         -> Paused
 *     any invoice past its due date  -> Overdue
 *     anything still owed            -> Payment Due
 *     otherwise                      -> Active
 *
 * next_billing_date is the one piece of billing state that IS stored: it is
 * the first day of the period the next invoice will cover, and it steps
 * forward by one cycle each time an invoice is generated. Pausing or
 * cancelling leaves it where it is, and both block new invoices outright.
 */
class MonthlyClient extends Model
{
    /** Billing frequency => how many months one invoice covers. */
    public const FREQUENCY_MONTHS = [
        'monthly'     => 1,
        'quarterly'   => 3,
        'half_yearly' => 6,
        'yearly'      => 12,
    ];

    /** Payment terms => how many days after the invoice date it falls due. */
    public const TERM_DAYS = [
        'due_on_receipt' => 0,
        'net_7'          => 7,
        'net_15'         => 15,
        'net_30'         => 30,
        'net_45'         => 45,
        'net_60'         => 60,
    ];

    /** How a recurring client pays. */
    public const METHODS = ['upi', 'bank_transfer', 'cash', 'card', 'other'];

    /** How a standing discount is expressed. */
    public const DISCOUNT_TYPES = ['none', 'percent', 'amount'];

    /** The lifecycle values actually stored in the status column. */
    public const LIFECYCLE = ['active', 'paused', 'cancelled'];

    /** Every status a client can be shown as, most urgent first. */
    public const STATUSES = ['overdue', 'payment_due', 'active', 'paused', 'cancelled'];

    public const STATUS_LABELS = [
        'active'      => 'Active',
        'payment_due' => 'Payment Due',
        'overdue'     => 'Overdue',
        'paused'      => 'Paused',
        'cancelled'   => 'Cancelled',
    ];

    /** Filter keys the list page offers, including the two invoice-derived ones. */
    public const FILTERS = ['active', 'payment_due', 'paid', 'partially_paid', 'overdue', 'paused', 'cancelled'];

    public const FILTER_LABELS = [
        'active'         => 'Active',
        'payment_due'    => 'Payment Due',
        'paid'           => 'Paid',
        'partially_paid' => 'Partially Paid',
        'overdue'        => 'Overdue',
        'paused'         => 'Paused',
        'cancelled'      => 'Cancelled',
    ];

    // ---------------- Labels ----------------

    public static function frequencyLabel(string $f): string
    {
        return ucwords(str_replace('_', ' ', $f));
    }

    public static function methodLabel(string $m): string
    {
        return $m === 'upi' ? 'UPI' : ucwords(str_replace('_', ' ', $m));
    }

    public static function termsLabel(string $t): string
    {
        return $t === 'due_on_receipt' ? 'Due on receipt' : 'Net ' . self::TERM_DAYS[$t] . ' days';
    }

    // ---------------- Date maths ----------------

    /** How many months one invoice covers. Unknown frequencies fall back to a month. */
    public static function cycleMonths(string $frequency): int
    {
        return self::FREQUENCY_MONTHS[$frequency] ?? 1;
    }

    /**
     * Add whole months to a Y-m-d date, clamped to the end of the target month
     * so 31 Jan + 1 month lands on 28 Feb instead of spilling into March —
     * a billing date must never quietly drift into the following month.
     */
    public static function addMonths(?string $date, int $months): ?string
    {
        if ($date === null || $date === '') {
            return null;
        }
        $start = date_create_immutable($date);
        if ($start === false) {
            return null;
        }

        $target = $start->modify('first day of this month')->modify('+' . $months . ' months');
        $day    = min((int) $start->format('j'), (int) $target->format('t'));

        return $target->setDate((int) $target->format('Y'), (int) $target->format('n'), $day)->format('Y-m-d');
    }

    /** Add days to a Y-m-d date. */
    public static function addDays(?string $date, int $days): ?string
    {
        if ($date === null || $date === '') {
            return null;
        }
        $start = date_create_immutable($date);
        return $start === false ? null : $start->modify('+' . $days . ' days')->format('Y-m-d');
    }

    /** The last day covered by a billing period starting on $periodStart. */
    public static function periodEnd(string $periodStart, string $frequency): string
    {
        $next = self::addMonths($periodStart, self::cycleMonths($frequency)) ?? $periodStart;
        return self::addDays($next, -1) ?? $periodStart;
    }

    /** When an invoice raised on $invoiceDate falls due under these terms. */
    public static function dueDateFor(string $invoiceDate, string $terms): string
    {
        return self::addDays($invoiceDate, self::TERM_DAYS[$terms] ?? 0) ?? $invoiceDate;
    }

    /** Whole days from today until a date (negative once it has passed). */
    public static function daysUntil(?string $date): ?int
    {
        if ($date === null || $date === '') {
            return null;
        }
        $target = date_create_immutable($date);
        if ($target === false) {
            return null;
        }
        return (int) (new DateTimeImmutable('today'))->diff($target->setTime(0, 0))->format('%r%a');
    }

    /**
     * What one invoice comes to, given the client's rate and their standing
     * discount and tax. Shared by the "generate invoice" form's preview and by
     * the controller that actually writes the invoice, so both always agree.
     *
     * @return array{recurring:float,discount:float,taxable:float,tax:float,total:float}
     */
    public static function amountsFor(float $monthlyAmount, string $frequency, string $discountType, float $discountValue, float $taxPercent): array
    {
        $recurring = round($monthlyAmount * self::cycleMonths($frequency), 2);

        $discount = 0.0;
        if ($discountType === 'percent') {
            $discount = round($recurring * ($discountValue / 100), 2);
        } elseif ($discountType === 'amount') {
            $discount = round($discountValue, 2);
        }
        $discount = max(0.0, min($discount, $recurring));

        $taxable = round($recurring - $discount, 2);
        $tax     = round($taxable * ($taxPercent / 100), 2);

        return [
            'recurring' => $recurring,
            'discount'  => $discount,
            'taxable'   => $taxable,
            'tax'       => $tax,
            'total'     => round($taxable + $tax, 2),
        ];
    }

    // ---------------- Derived fields ----------------

    /**
     * Attach everything the views need onto a row already carrying the
     * money aggregates from AGGREGATES below.
     */
    public static function decorate(array $row): array
    {
        $outstanding = (float) ($row['total_outstanding'] ?? 0);
        $overdue     = (float) ($row['total_overdue'] ?? 0);

        if ($row['status'] === 'cancelled') {
            $status = 'cancelled';
        } elseif ($row['status'] === 'paused') {
            $status = 'paused';
        } elseif ($overdue > 0.004) {
            $status = 'overdue';
        } elseif ($outstanding > 0.004) {
            $status = 'payment_due';
        } else {
            $status = 'active';
        }

        $row['display_status'] = $status;
        $row['status_label']   = self::STATUS_LABELS[$status];

        // Billing only continues while the client is active — a paused or
        // cancelled client shows no next billing date at all, because none
        // will be raised until they are resumed. scheduled_billing_date keeps
        // the stored value either way, so the resume form can offer to pick up
        // exactly where billing left off.
        $row['bills_recurring']        = $row['status'] === 'active';
        $row['scheduled_billing_date'] = $row['next_billing_date'];
        $row['next_billing_date']      = $row['bills_recurring'] ? $row['next_billing_date'] : null;
        $row['next_period_end']   = $row['next_billing_date'] !== null
            ? self::periodEnd((string) $row['next_billing_date'], (string) $row['billing_frequency'])
            : null;

        $row['days_to_next_billing'] = self::daysUntil($row['next_billing_date']);
        $row['days_to_next_due']     = self::daysUntil($row['next_due_date'] ?? null);
        $row['cycle_amount']         = round((float) $row['monthly_amount'] * self::cycleMonths((string) $row['billing_frequency']), 2);

        // A contract is "ending" once its end date is inside the next 30 days.
        $row['contract_days_left'] = self::daysUntil($row['contract_end_date'] ?? null);
        $row['contract_status']    = self::contractStatus($row);

        return $row;
    }

    /** Where a client's contract stands: no end date means it simply runs on. */
    private static function contractStatus(array $row): string
    {
        if ($row['status'] === 'cancelled') {
            return 'Cancelled';
        }
        if (empty($row['contract_end_date'])) {
            return 'Ongoing — no end date';
        }
        $left = self::daysUntil((string) $row['contract_end_date']);
        if ($left === null) {
            return 'Ongoing — no end date';
        }
        if ($left < 0) {
            return 'Expired ' . abs($left) . ' day' . (abs($left) === 1 ? '' : 's') . ' ago';
        }
        if ($left === 0) {
            return 'Ends today';
        }
        if ($left <= 30) {
            return 'Ends in ' . $left . ' day' . ($left === 1 ? '' : 's');
        }
        return 'Active until ' . date('j M Y', strtotime((string) $row['contract_end_date']));
    }

    /** @param array<int,array> $rows */
    private static function decorateAll(array $rows): array
    {
        return array_map([self::class, 'decorate'], $rows);
    }

    // ---------------- Shared SQL ----------------

    /**
     * The per-client money aggregates every list and detail page needs.
     * Cancelled invoices are left out of every total — they were never owed.
     */
    private const AGGREGATES = "
        (SELECT COUNT(*) FROM monthly_invoices i
          WHERE i.monthly_client_id = mc.id AND i.status <> 'cancelled') AS invoice_count,
        (SELECT COUNT(*) FROM monthly_payments p WHERE p.monthly_client_id = mc.id) AS payment_count,
        (SELECT COALESCE(SUM(i.total_amount), 0) FROM monthly_invoices i
          WHERE i.monthly_client_id = mc.id AND i.status <> 'cancelled') AS total_billed,
        (SELECT COALESCE(SUM(p.amount), 0) FROM monthly_payments p
          WHERE p.monthly_client_id = mc.id) AS total_paid,
        (SELECT COALESCE(SUM(GREATEST(i.total_amount - COALESCE(
                  (SELECT SUM(p.amount) FROM monthly_payments p WHERE p.invoice_id = i.id), 0), 0)), 0)
           FROM monthly_invoices i
          WHERE i.monthly_client_id = mc.id AND i.status <> 'cancelled') AS total_outstanding,
        (SELECT COALESCE(SUM(GREATEST(i.total_amount - COALESCE(
                  (SELECT SUM(p.amount) FROM monthly_payments p WHERE p.invoice_id = i.id), 0), 0)), 0)
           FROM monthly_invoices i
          WHERE i.monthly_client_id = mc.id AND i.status = 'sent' AND i.due_date < CURDATE()) AS total_overdue,
        (SELECT MIN(i.due_date) FROM monthly_invoices i
          WHERE i.monthly_client_id = mc.id AND i.status = 'sent'
            AND i.total_amount > COALESCE((SELECT SUM(p.amount) FROM monthly_payments p WHERE p.invoice_id = i.id), 0)
        ) AS next_due_date,
        (SELECT GREATEST(i.total_amount - COALESCE(
                  (SELECT SUM(p.amount) FROM monthly_payments p WHERE p.invoice_id = i.id), 0), 0)
           FROM monthly_invoices i
          WHERE i.monthly_client_id = mc.id AND i.status = 'sent'
            AND i.total_amount > COALESCE((SELECT SUM(p.amount) FROM monthly_payments p WHERE p.invoice_id = i.id), 0)
          ORDER BY i.due_date ASC, i.id ASC LIMIT 1) AS next_due_amount
    ";

    // ---------------- Reads ----------------

    /**
     * The filtered list behind the Monthly Clients table.
     *
     * @param array $f  q (name/company/email/service/invoice number), status, sort, dir
     */
    public function search(array $f = []): array
    {
        $where  = [];
        $params = [];

        $q = trim((string) ($f['q'] ?? ''));
        if ($q !== '') {
            // Invoice number is searched too, so typing MC-2026-004 lands on
            // the client that invoice belongs to.
            $where[] = '(mc.client_name LIKE ? OR mc.company LIKE ? OR mc.email LIKE ?
                         OR mc.service_name LIKE ? OR mc.mobile LIKE ?
                         OR EXISTS (SELECT 1 FROM monthly_invoices i WHERE i.monthly_client_id = mc.id AND i.invoice_number LIKE ?))';
            $like    = '%' . $q . '%';
            array_push($params, $like, $like, $like, $like, $like, $like);
        }

        $statusSql = $this->statusCondition((string) ($f['status'] ?? ''));
        if ($statusSql !== '') {
            $where[] = $statusSql;
        }

        $sorts = [
            'name'    => 'mc.client_name',
            'amount'  => 'mc.monthly_amount',
            'billing' => 'mc.next_billing_date',
            'due'     => 'mc.next_billing_date',
        ];
        $sort = $sorts[(string) ($f['sort'] ?? 'name')] ?? $sorts['name'];
        $dir  = strtolower((string) ($f['dir'] ?? 'asc')) === 'desc' ? 'DESC' : 'ASC';

        $sql = 'SELECT mc.*, ' . self::AGGREGATES . ' FROM monthly_clients mc';
        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY ' . $sort . ' ' . $dir . ', mc.id DESC';

        return self::decorateAll($this->all($sql, $params));
    }

    /**
     * SQL for one status filter. Every threshold is a literal, never user
     * input, so the fragments are safe to inline.
     *
     *   paid            — nothing outstanding, and they have been invoiced
     *   partially_paid  — at least one invoice part-paid but not settled
     */
    private function statusCondition(string $status): string
    {
        $balance = "GREATEST(i.total_amount - COALESCE(
                      (SELECT SUM(p.amount) FROM monthly_payments p WHERE p.invoice_id = i.id), 0), 0)";
        $owed = "(SELECT COALESCE(SUM($balance), 0) FROM monthly_invoices i
                   WHERE i.monthly_client_id = mc.id AND i.status <> 'cancelled')";
        $late = "(SELECT COALESCE(SUM($balance), 0) FROM monthly_invoices i
                   WHERE i.monthly_client_id = mc.id AND i.status = 'sent' AND i.due_date < CURDATE())";

        switch ($status) {
            case 'paused':
            case 'cancelled':
                return "mc.status = '" . $status . "'";
            case 'overdue':
                return "mc.status = 'active' AND $late > 0";
            case 'payment_due':
                return "mc.status = 'active' AND $owed > 0 AND $late = 0";
            case 'active':
                return "mc.status = 'active'";
            case 'paid':
                return "mc.status <> 'cancelled' AND $owed = 0
                        AND EXISTS (SELECT 1 FROM monthly_invoices i
                                     WHERE i.monthly_client_id = mc.id AND i.status <> 'cancelled')";
            case 'partially_paid':
                return "EXISTS (SELECT 1 FROM monthly_invoices i
                                 WHERE i.monthly_client_id = mc.id AND i.status <> 'cancelled'
                                   AND COALESCE((SELECT SUM(p.amount) FROM monthly_payments p WHERE p.invoice_id = i.id), 0) > 0
                                   AND COALESCE((SELECT SUM(p.amount) FROM monthly_payments p WHERE p.invoice_id = i.id), 0) < i.total_amount)";
            default:
                return '';
        }
    }

    /** One client with all of their money aggregates. */
    public function find(int $id): ?array
    {
        $row = $this->one(
            'SELECT mc.*, ' . self::AGGREGATES . ' FROM monthly_clients mc WHERE mc.id = ? LIMIT 1',
            [$id]
        );
        return $row === null ? null : self::decorate($row);
    }

    /** The bare row, without aggregates — for the checks a write does first. */
    public function findRaw(int $id): ?array
    {
        return $this->one('SELECT * FROM monthly_clients WHERE id = ? LIMIT 1', [$id]);
    }

    /** How many clients sit under each filter tab, so the tabs can show counts. */
    public function counts(): array
    {
        $out = ['' => 0];
        foreach (self::FILTERS as $key) {
            $sql = 'SELECT COUNT(*) AS c FROM monthly_clients mc WHERE ' . $this->statusCondition($key);
            $out[$key] = (int) (($this->one($sql)['c']) ?? 0);
        }
        $out[''] = (int) (($this->one('SELECT COUNT(*) AS c FROM monthly_clients mc')['c']) ?? 0);
        return $out;
    }

    /**
     * The figures across the top of the Monthly Clients dashboard.
     *
     * "Monthly recurring" is what active clients bring in per month (their
     * monthly rate, regardless of how often they are actually invoiced), which
     * is the number that describes the business rather than this month's post.
     */
    public function dashboard(): array
    {
        $balance = "GREATEST(i.total_amount - COALESCE(
                      (SELECT SUM(p.amount) FROM monthly_payments p WHERE p.invoice_id = i.id), 0), 0)";

        $clients = $this->one(
            "SELECT COUNT(*) AS total,
                    COALESCE(SUM(status = 'active'), 0)    AS active,
                    COALESCE(SUM(status = 'paused'), 0)    AS paused,
                    COALESCE(SUM(status = 'cancelled'), 0) AS cancelled,
                    COALESCE(SUM(CASE WHEN status = 'active' THEN monthly_amount ELSE 0 END), 0) AS recurring
             FROM monthly_clients"
        ) ?? [];

        $money = $this->one(
            "SELECT
               COALESCE((SELECT SUM(i.total_amount) FROM monthly_invoices i
                          WHERE i.status <> 'cancelled'
                            AND YEAR(i.due_date) = YEAR(CURDATE()) AND MONTH(i.due_date) = MONTH(CURDATE())), 0) AS due_this_month,
               COALESCE((SELECT SUM(p.amount) FROM monthly_payments p
                          WHERE YEAR(p.payment_date) = YEAR(CURDATE()) AND MONTH(p.payment_date) = MONTH(CURDATE())), 0) AS paid_this_month,
               COALESCE((SELECT SUM($balance) FROM monthly_invoices i WHERE i.status <> 'cancelled'), 0) AS outstanding,
               COALESCE((SELECT SUM($balance) FROM monthly_invoices i
                          WHERE i.status = 'sent' AND i.due_date < CURDATE()), 0) AS overdue"
        ) ?? [];

        return [
            'total'           => (int) ($clients['total'] ?? 0),
            'active'          => (int) ($clients['active'] ?? 0),
            'paused'          => (int) ($clients['paused'] ?? 0),
            'cancelled'       => (int) ($clients['cancelled'] ?? 0),
            'recurring'       => (float) ($clients['recurring'] ?? 0),
            'due_this_month'  => (float) ($money['due_this_month'] ?? 0),
            'paid_this_month' => (float) ($money['paid_this_month'] ?? 0),
            'outstanding'     => (float) ($money['outstanding'] ?? 0),
            'overdue'         => (float) ($money['overdue'] ?? 0),
        ];
    }

    /**
     * Active clients whose next billing date has arrived (or passed) and so
     * are waiting for an invoice to be generated.
     */
    public function dueForBilling(int $withinDays = 7): array
    {
        return self::decorateAll($this->all(
            'SELECT mc.*, ' . self::AGGREGATES . '
             FROM monthly_clients mc
             WHERE mc.status = \'active\' AND DATEDIFF(mc.next_billing_date, CURDATE()) <= ?
             ORDER BY mc.next_billing_date ASC, mc.client_name ASC',
            [$withinDays]
        ));
    }

    /** The pause history of one client, newest first. */
    public function pauses(int $clientId): array
    {
        return $this->all(
            'SELECT ph.*, u.name AS recorded_by
             FROM monthly_client_pauses ph
             LEFT JOIN users u ON u.id = ph.created_by
             WHERE ph.monthly_client_id = ?
             ORDER BY ph.paused_on DESC, ph.id DESC',
            [$clientId]
        );
    }

    // ---------------- Writes ----------------

    /** Add a recurring client. Billing starts on their start date. Returns the new id. */
    public function create(array $d, ?int $createdBy): int
    {
        $this->run(
            'INSERT INTO monthly_clients
               (client_name, company, email, mobile, billing_address, service_name, service_description,
                monthly_amount, billing_frequency, discount_type, discount_value, tax_percent,
                payment_method, payment_terms, start_date, contract_end_date, renewal_date, contract_notes,
                next_billing_date, notes, created_by)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
            array_merge($this->columnValues($d), [$d['start_date'], $d['notes'] ?: null, $createdBy])
        );
        return (int) $this->db->lastInsertId();
    }

    /**
     * Save a client's details. next_billing_date is deliberately untouched —
     * editing a rate or an address must never rewind or skip their billing.
     */
    public function update(int $id, array $d): void
    {
        $this->run(
            'UPDATE monthly_clients SET
               client_name = ?, company = ?, email = ?, mobile = ?, billing_address = ?,
               service_name = ?, service_description = ?, monthly_amount = ?, billing_frequency = ?,
               discount_type = ?, discount_value = ?, tax_percent = ?, payment_method = ?, payment_terms = ?,
               start_date = ?, contract_end_date = ?, renewal_date = ?, contract_notes = ?, notes = ?
             WHERE id = ?',
            array_merge($this->columnValues($d), [$d['notes'] ?: null, $id])
        );
    }

    /** Move billing on to the next period once an invoice has been raised. */
    public function advanceBilling(int $id, string $nextBillingDate): void
    {
        $this->run('UPDATE monthly_clients SET next_billing_date = ? WHERE id = ?', [$nextBillingDate, $id]);
    }

    /**
     * Pause billing. The client keeps every invoice and payment they already
     * have; only future invoices are blocked, until resume() is called.
     */
    public function pause(int $id, string $pausedOn, string $reason, ?string $resumeDate, string $notes, ?int $by): void
    {
        $this->run(
            'UPDATE monthly_clients SET status = ?, paused_on = ?, pause_reason = ?, resume_date = ? WHERE id = ?',
            ['paused', $pausedOn, $reason ?: null, $resumeDate ?: null, $id]
        );
        $this->run(
            'INSERT INTO monthly_client_pauses (monthly_client_id, paused_on, reason, resume_date, notes, created_by)
             VALUES (?, ?, ?, ?, ?, ?)',
            [$id, $pausedOn, $reason ?: null, $resumeDate ?: null, $notes ?: null, $by]
        );
    }

    /**
     * Resume billing and close the open pause entry. The caller decides where
     * billing picks up from and passes it in as $nextBillingDate.
     */
    public function resume(int $id, string $resumedOn, string $nextBillingDate): void
    {
        $this->run(
            'UPDATE monthly_clients SET status = ?, paused_on = NULL, pause_reason = NULL, resume_date = NULL,
                                        next_billing_date = ? WHERE id = ?',
            ['active', $nextBillingDate, $id]
        );
        $this->run(
            'UPDATE monthly_client_pauses SET resumed_on = ?
             WHERE monthly_client_id = ? AND resumed_on IS NULL',
            [$resumedOn, $id]
        );
    }

    /**
     * Cancel a client: future billing stops, and every invoice, payment and
     * receipt they already have is kept exactly as it is.
     */
    public function cancel(int $id, string $cancelledOn, string $reason, string $notes): void
    {
        $this->run(
            'UPDATE monthly_clients SET status = ?, cancelled_on = ?, cancellation_reason = ?, cancellation_notes = ?,
                                        paused_on = NULL, pause_reason = NULL, resume_date = NULL
             WHERE id = ?',
            ['cancelled', $cancelledOn, $reason ?: null, $notes ?: null, $id]
        );
    }

    /** Put a cancelled client back on billing from the given date. */
    public function reactivate(int $id, string $nextBillingDate): void
    {
        $this->run(
            'UPDATE monthly_clients SET status = ?, cancelled_on = NULL, cancellation_reason = NULL,
                                        cancellation_notes = NULL, next_billing_date = ? WHERE id = ?',
            ['active', $nextBillingDate, $id]
        );
    }

    /** The column values shared by create() and update(), in column order. */
    private function columnValues(array $d): array
    {
        $frequency = array_key_exists($d['billing_frequency'], self::FREQUENCY_MONTHS) ? $d['billing_frequency'] : 'monthly';
        $discount  = in_array($d['discount_type'], self::DISCOUNT_TYPES, true) ? $d['discount_type'] : 'none';
        $method    = in_array($d['payment_method'], self::METHODS, true) ? $d['payment_method'] : 'other';
        $terms     = array_key_exists($d['payment_terms'], self::TERM_DAYS) ? $d['payment_terms'] : 'net_7';

        return [
            $d['client_name'],
            $d['company'] ?: null,
            $d['email'] ?: null,
            $d['mobile'] ?: null,
            $d['billing_address'] ?: null,
            $d['service_name'],
            $d['service_description'] ?: null,
            (float) $d['monthly_amount'],
            $frequency,
            $discount,
            $discount === 'none' ? 0 : (float) $d['discount_value'],
            $d['tax_percent'] === '' ? 0 : (float) $d['tax_percent'],
            $method,
            $terms,
            $d['start_date'],
            $d['contract_end_date'] ?: null,
            $d['renewal_date'] ?: null,
            $d['contract_notes'] ?: null,
        ];
    }
}
