<?php
namespace App\Models;

use App\Core\Model;
use DateTimeImmutable;

/**
 * A hosting plan or a domain managed on a client's behalf.
 *
 * Hosting and domains share this one table (service_type tells them apart)
 * because they behave identically: bought from a provider, they expire, and
 * they need the same countdown and the same renewal reminders.
 *
 * Nothing about a record's status is stored — it is always derived from
 * renewal_date vs today, so a record can never go stale in the database:
 *
 *     days remaining = renewal_date - today
 *
 *     > 30   Active
 *     8..30  Renewing Soon
 *     0..7   Renewal Due (urgent)
 *     < 0    Expired
 *
 * The two thresholds live in SOON_DAYS / URGENT_DAYS below, so making them
 * admin-configurable later means reading them from settings instead of here.
 */
class HostingService extends Model
{
    /** A record is "Renewing Soon" from this many days out. */
    public const SOON_DAYS = 30;

    /** ...and "Renewal Due" (urgent) from this many days out. */
    public const URGENT_DAYS = 7;

    /** What a record can be. */
    public const TYPES = ['hosting', 'domain'];

    /** Billing cycles => how many months each one adds. 'custom' uses custom_cycle_months. */
    public const CYCLE_MONTHS = [
        'monthly'     => 1,
        'quarterly'   => 3,
        'half_yearly' => 6,
        'yearly'      => 12,
        'custom'      => null,
    ];

    /** Derived statuses, most urgent first — also the order of the filter tabs. */
    public const STATUSES = ['expired', 'due', 'renewing_soon', 'active'];

    public const STATUS_LABELS = [
        'expired'       => 'Expired',
        'due'           => 'Renewal Due',
        'renewing_soon' => 'Renewing Soon',
        'active'        => 'Active',
        'renewed'       => 'Renewed',
    ];

    /** Columns the list may be sorted by => the SQL that sorts them. */
    private const SORTS = [
        'renewal_date' => 'h.renewal_date',
        'client'       => 'h.client_name',
        'amount'       => 'COALESCE(h.renewal_cost, h.cost, 0)',
        'status'       => 'DATEDIFF(h.renewal_date, CURDATE())',
    ];

    // ---------------- Date maths ----------------

    /** How many months one billing cycle covers (null when it can't be worked out). */
    public static function cycleMonths(string $cycle, ?int $customMonths = null): ?int
    {
        if ($cycle === 'custom') {
            return $customMonths !== null && $customMonths > 0 ? $customMonths : null;
        }
        return self::CYCLE_MONTHS[$cycle] ?? null;
    }

    /**
     * Add one billing cycle to a date: 2026-09-15 + yearly => 2027-09-15.
     *
     * PHP's "+1 month" overflows (Jan 31 + 1 month = Mar 3), which would quietly
     * shift a renewal date, so a date that would overflow is pulled back to the
     * last day of the intended month instead (Jan 31 + 1 month = Feb 28).
     *
     * Returns null when the date or the cycle isn't usable — the caller then
     * falls back to whatever the admin typed in.
     */
    public static function addCycle(?string $date, string $cycle, ?int $customMonths = null): ?string
    {
        $months = self::cycleMonths($cycle, $customMonths);
        if ($date === null || $date === '' || $months === null) {
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

    /** Whole days from today until a renewal date (negative once it has passed). */
    public static function daysUntil(string $renewalDate): int
    {
        $today  = new DateTimeImmutable('today');
        $target = date_create_immutable($renewalDate);
        if ($target === false) {
            return 0;
        }
        return (int) $today->diff($target->setTime(0, 0))->format('%r%a');
    }

    /** Status key for a given days-remaining count. */
    public static function statusFor(int $days): string
    {
        if ($days < 0) {
            return 'expired';
        }
        if ($days <= self::URGENT_DAYS) {
            return 'due';
        }
        if ($days <= self::SOON_DAYS) {
            return 'renewing_soon';
        }
        return 'active';
    }

    /**
     * The reminder wording for a record, or null when it's too far out to
     * need one: 30 days => Upcoming, 15 => Approaching, 7 => Urgent,
     * 0 => Due today, past => Expired.
     */
    public static function reminderFor(int $days): ?string
    {
        if ($days < 0) {
            return 'Expired';
        }
        if ($days === 0) {
            return 'Renewal Due Today';
        }
        if ($days <= self::URGENT_DAYS) {
            return 'Urgent Renewal';
        }
        if ($days <= 15) {
            return 'Renewal Approaching';
        }
        if ($days <= self::SOON_DAYS) {
            return 'Upcoming Renewal';
        }
        return null;
    }

    /** Attach the derived fields every view needs onto a database row. */
    public static function decorate(array $row): array
    {
        $days = self::daysUntil((string) $row['renewal_date']);

        $row['days_remaining'] = $days;
        $row['status']         = self::statusFor($days);
        $row['status_label']   = self::STATUS_LABELS[$row['status']];
        $row['reminder_label'] = self::reminderFor($days);
        $row['next_renewal']   = self::addCycle(
            (string) $row['renewal_date'],
            (string) $row['billing_cycle'],
            isset($row['custom_cycle_months']) && $row['custom_cycle_months'] !== null
                ? (int) $row['custom_cycle_months']
                : null
        );
        // "Renewed" is shown alongside the live status for anything renewed
        // this calendar month, which is also what the summary card counts.
        $row['renewed_this_month'] = !empty($row['last_renewed_at'])
            && substr((string) $row['last_renewed_at'], 0, 7) === date('Y-m');

        return $row;
    }

    /** Decorate a whole result set. */
    private static function decorateAll(array $rows): array
    {
        return array_map([self::class, 'decorate'], $rows);
    }

    // ---------------- Reads ----------------

    /**
     * The filtered, sorted list behind the Hosting table.
     *
     * @param array $f  q, type, status, provider, cycle, from, to, sort, dir
     */
    public function search(array $f = []): array
    {
        $where  = [];
        $params = [];

        $q = trim((string) ($f['q'] ?? ''));
        if ($q !== '') {
            $where[] = '(h.client_name LIKE ? OR h.company LIKE ? OR h.website_name LIKE ?
                         OR h.domain LIKE ? OR h.website_url LIKE ? OR h.provider LIKE ?)';
            $like    = '%' . $q . '%';
            array_push($params, $like, $like, $like, $like, $like, $like);
        }

        $type = (string) ($f['type'] ?? '');
        if (in_array($type, self::TYPES, true)) {
            $where[]  = 'h.service_type = ?';
            $params[] = $type;
        }

        $provider = trim((string) ($f['provider'] ?? ''));
        if ($provider !== '') {
            $where[]  = 'h.provider = ?';
            $params[] = $provider;
        }

        $cycle = (string) ($f['cycle'] ?? '');
        if ($cycle !== '' && array_key_exists($cycle, self::CYCLE_MONTHS)) {
            $where[]  = 'h.billing_cycle = ?';
            $params[] = $cycle;
        }

        $from = (string) ($f['from'] ?? '');
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
            $where[]  = 'h.renewal_date >= ?';
            $params[] = $from;
        }
        $to = (string) ($f['to'] ?? '');
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
            $where[]  = 'h.renewal_date <= ?';
            $params[] = $to;
        }

        // Status is derived, so it filters on the day count rather than a column.
        $statusSql = $this->statusCondition((string) ($f['status'] ?? ''));
        if ($statusSql !== '') {
            $where[] = $statusSql;
        }

        $sortKey = (string) ($f['sort'] ?? 'renewal_date');
        $sort    = self::SORTS[$sortKey] ?? self::SORTS['renewal_date'];
        $dir     = strtolower((string) ($f['dir'] ?? 'asc')) === 'desc' ? 'DESC' : 'ASC';

        $sql = 'SELECT h.*, c.email AS client_email, c.phone AS client_phone, p.name AS project_name,
                       (SELECT COUNT(*) FROM hosting_renewals r WHERE r.hosting_id = h.id) AS renewal_count
                FROM hosting_services h
                LEFT JOIN clients  c ON c.id = h.client_id
                LEFT JOIN projects p ON p.id = h.project_id';

        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY ' . $sort . ' ' . $dir . ', h.id DESC';

        return self::decorateAll($this->all($sql, $params));
    }

    /**
     * SQL for one status filter. The thresholds are class constants cast to
     * int, never user input, so they are safe to inline.
     */
    private function statusCondition(string $status): string
    {
        $soon   = (int) self::SOON_DAYS;
        $urgent = (int) self::URGENT_DAYS;
        $days   = 'DATEDIFF(h.renewal_date, CURDATE())';

        switch ($status) {
            case 'expired':
                return $days . ' < 0';
            case 'due':
                return $days . ' BETWEEN 0 AND ' . $urgent;
            case 'renewing_soon':
                return $days . ' BETWEEN ' . ($urgent + 1) . ' AND ' . $soon;
            case 'active':
                return $days . ' > ' . $soon;
            case 'renewed':
                return "DATE_FORMAT(h.last_renewed_at, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')";
            default:
                return '';
        }
    }

    /** One record with its client and project details joined on. */
    public function find(int $id): ?array
    {
        $row = $this->one(
            'SELECT h.*, c.name AS client_record_name, c.email AS client_email, c.phone AS client_phone,
                    c.company AS client_company, p.name AS project_name
             FROM hosting_services h
             LEFT JOIN clients  c ON c.id = h.client_id
             LEFT JOIN projects p ON p.id = h.project_id
             WHERE h.id = ? LIMIT 1',
            [$id]
        );
        return $row === null ? null : self::decorate($row);
    }

    /** The summary numbers across the top of the Hosting dashboard. */
    public function summary(): array
    {
        $soon   = (int) self::SOON_DAYS;
        $urgent = (int) self::URGENT_DAYS;
        $days   = 'DATEDIFF(renewal_date, CURDATE())';

        $row = $this->one(
            "SELECT COUNT(*) AS total,
                    SUM($days > $soon) AS active,
                    SUM($days BETWEEN " . ($urgent + 1) . " AND $soon) AS renewing_soon,
                    SUM($days BETWEEN 0 AND $urgent) AS due,
                    SUM($days < 0) AS expired,
                    SUM(DATE_FORMAT(last_renewed_at, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')) AS renewed_this_month
             FROM hosting_services"
        ) ?? [];

        return [
            'total'              => (int) ($row['total'] ?? 0),
            'active'             => (int) ($row['active'] ?? 0),
            'renewing_soon'      => (int) ($row['renewing_soon'] ?? 0),
            'due'                => (int) ($row['due'] ?? 0),
            'expired'            => (int) ($row['expired'] ?? 0),
            'renewed_this_month' => (int) ($row['renewed_this_month'] ?? 0),
        ];
    }

    /**
     * Everything that needs attention, most urgent first: already expired,
     * plus anything renewing inside the given window.
     */
    public function needingAttention(int $withinDays = self::SOON_DAYS): array
    {
        return self::decorateAll($this->all(
            'SELECT h.*, c.email AS client_email
             FROM hosting_services h
             LEFT JOIN clients c ON c.id = h.client_id
             WHERE DATEDIFF(h.renewal_date, CURDATE()) <= ?
             ORDER BY h.renewal_date ASC',
            [$withinDays]
        ));
    }

    /**
     * Counts for the sidebar dot and the main dashboard widget — expired,
     * due within a week, and due within a month.
     */
    public function alertCounts(): array
    {
        $s = $this->summary();
        return [
            'expired'   => $s['expired'],
            'urgent'    => $s['due'],
            'soon'      => $s['renewing_soon'],
            'attention' => $s['expired'] + $s['due'] + $s['renewing_soon'],
        ];
    }

    /** Distinct providers already in use, for the provider filter dropdown. */
    public function providers(): array
    {
        $rows = $this->all(
            "SELECT DISTINCT provider FROM hosting_services
             WHERE provider IS NOT NULL AND provider != '' ORDER BY provider ASC"
        );
        return array_column($rows, 'provider');
    }

    // ---------------- Writes ----------------

    /** Add a hosting or domain record. Returns its new id. */
    public function create(array $d, ?int $createdBy): int
    {
        $this->run(
            'INSERT INTO hosting_services
               (service_type, client_id, client_name, company, project_id, website_name, website_url, domain,
                provider, plan, account_ref, purchase_date, renewal_date, cost, renewal_cost,
                billing_cycle, custom_cycle_months, login_url, credential_ref, notes, internal_notes, created_by)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
            array_merge($this->columnValues($d), [$createdBy])
        );
        return (int) $this->db->lastInsertId();
    }

    /** Save changes to a record's details. */
    public function update(int $id, array $d): void
    {
        $this->run(
            'UPDATE hosting_services SET
               service_type = ?, client_id = ?, client_name = ?, company = ?, project_id = ?,
               website_name = ?, website_url = ?, domain = ?, provider = ?, plan = ?, account_ref = ?,
               purchase_date = ?, renewal_date = ?, cost = ?, renewal_cost = ?,
               billing_cycle = ?, custom_cycle_months = ?, login_url = ?, credential_ref = ?,
               notes = ?, internal_notes = ?
             WHERE id = ?',
            array_merge($this->columnValues($d), [$id])
        );
    }

    /**
     * Move a record's expiry forward after a renewal and stamp the date it
     * was renewed on (which is what "Renewed this month" counts).
     */
    public function applyRenewal(int $id, string $newExpiry, string $renewedOn): void
    {
        $this->run(
            'UPDATE hosting_services SET renewal_date = ?, last_renewed_at = ? WHERE id = ?',
            [$newExpiry, $renewedOn, $id]
        );
    }

    /** Permanently remove a record (its renewal history cascades with it). */
    public function delete(int $id): void
    {
        $this->run('DELETE FROM hosting_services WHERE id = ?', [$id]);
    }

    /** The column values shared by create() and update(), in column order. */
    private function columnValues(array $d): array
    {
        return [
            $d['service_type'],
            (int) $d['client_id'] > 0 ? (int) $d['client_id'] : null,
            $d['client_name'],
            $d['company'] ?: null,
            (int) $d['project_id'] > 0 ? (int) $d['project_id'] : null,
            $d['website_name'] ?: null,
            $d['website_url'] ?: null,
            $d['domain'] ?: null,
            $d['provider'] ?: null,
            $d['plan'] ?: null,
            $d['account_ref'] ?: null,
            $d['purchase_date'] ?: null,
            $d['renewal_date'],
            $d['cost'] === '' ? null : (float) $d['cost'],
            $d['renewal_cost'] === '' ? null : (float) $d['renewal_cost'],
            $d['billing_cycle'],
            $d['billing_cycle'] === 'custom' && (int) $d['custom_cycle_months'] > 0
                ? (int) $d['custom_cycle_months']
                : null,
            $d['login_url'] ?: null,
            $d['credential_ref'] ?: null,
            $d['notes'] ?: null,
            $d['internal_notes'] ?: null,
        ];
    }
}
