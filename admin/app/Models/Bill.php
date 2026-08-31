<?php
namespace App\Models;

use App\Core\Model;

/**
 * A printable bill/receipt raised when a payment is taken from a client
 * against a project. Itemised services, the project's cost and the running
 * balance are frozen at creation time, so a bill stays accurate as a
 * historical document even if the project's budget changes afterwards.
 */
class Bill extends Model
{
    /** Every bill, newest first, with client + project names for the history page. */
    public function allWithDetails(): array
    {
        return $this->all(
            'SELECT b.*, c.name AS client_name, c.company AS client_company, p.name AS project_name
             FROM bills b
             JOIN clients c ON c.id = b.client_id
             LEFT JOIN projects p ON p.id = b.project_id
             ORDER BY b.bill_date DESC, b.id DESC'
        );
    }

    /**
     * Bills narrowed by the history page's filter bar: free-text search
     * (bill number / client / project), client, project, payment method,
     * and a bill-date range. Any filter left blank/empty is skipped.
     */
    public function search(array $f = []): array
    {
        $where  = [];
        $params = [];

        $q = trim((string) ($f['q'] ?? ''));
        if ($q !== '') {
            $where[] = '(b.bill_number LIKE ? OR c.name LIKE ? OR c.company LIKE ? OR p.name LIKE ?)';
            $like    = '%' . $q . '%';
            array_push($params, $like, $like, $like, $like);
        }

        $clientId = (int) ($f['client_id'] ?? 0);
        if ($clientId > 0) {
            $where[]  = 'b.client_id = ?';
            $params[] = $clientId;
        }

        $projectId = (int) ($f['project_id'] ?? 0);
        if ($projectId > 0) {
            $where[]  = 'b.project_id = ?';
            $params[] = $projectId;
        }

        $method = (string) ($f['payment_method'] ?? '');
        if (in_array($method, ClientPayment::METHODS, true)) {
            $where[]  = 'b.payment_method = ?';
            $params[] = $method;
        }

        $from = (string) ($f['from'] ?? '');
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
            $where[]  = 'b.bill_date >= ?';
            $params[] = $from;
        }
        $to = (string) ($f['to'] ?? '');
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
            $where[]  = 'b.bill_date <= ?';
            $params[] = $to;
        }

        $sql = 'SELECT b.*, c.name AS client_name, c.company AS client_company, p.name AS project_name
                FROM bills b
                JOIN clients c ON c.id = b.client_id
                LEFT JOIN projects p ON p.id = b.project_id';
        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY b.bill_date DESC, b.id DESC';

        return $this->all($sql, $params);
    }

    /** All bills raised for one client, newest first. */
    public function forClient(int $clientId): array
    {
        return $this->all(
            'SELECT b.*, p.name AS project_name
             FROM bills b
             LEFT JOIN projects p ON p.id = b.project_id
             WHERE b.client_id = ?
             ORDER BY b.bill_date DESC, b.id DESC',
            [$clientId]
        );
    }

    /** One bill with everything needed to render/print it. */
    public function find(int $id): ?array
    {
        return $this->one(
            'SELECT b.*,
                    c.name AS client_name, c.company AS client_company, c.email AS client_email,
                    c.phone AS client_phone, c.address AS client_address,
                    p.name AS project_name,
                    u.name AS created_by_name
             FROM bills b
             JOIN clients c ON c.id = b.client_id
             LEFT JOIN projects p ON p.id = b.project_id
             LEFT JOIN users u ON u.id = b.created_by
             WHERE b.id = ? LIMIT 1',
            [$id]
        );
    }

    /** Total already paid toward one project, across every bill raised for it. */
    public function totalPaidForProject(int $projectId): float
    {
        $row = $this->one(
            'SELECT COALESCE(SUM(amount_paid), 0) AS t FROM bills WHERE project_id = ?',
            [$projectId]
        );
        return (float) ($row['t'] ?? 0);
    }

    /** Every project's running total paid so far, keyed by project id — for the "new bill" form's live preview. */
    public function paidByProject(): array
    {
        $out = [];
        foreach ($this->all('SELECT project_id, SUM(amount_paid) AS t FROM bills WHERE project_id IS NOT NULL GROUP BY project_id') as $row) {
            $out[(int) $row['project_id']] = (float) $row['t'];
        }
        return $out;
    }

    /** Sum of amounts paid this calendar month, across every client — the dashboard's "received this month". */
    public function receivedThisMonth(): float
    {
        $row = $this->one(
            "SELECT COALESCE(SUM(amount_paid), 0) AS t FROM bills
             WHERE YEAR(bill_date) = YEAR(CURDATE()) AND MONTH(bill_date) = MONTH(CURDATE())"
        );
        return (float) ($row['t'] ?? 0);
    }

    /** Total ever collected through bills, across every client. */
    public function totalCollected(): float
    {
        $row = $this->one('SELECT COALESCE(SUM(amount_paid), 0) AS t FROM bills');
        return (float) ($row['t'] ?? 0);
    }

    /** How many bills exist in total, regardless of any filter applied to the history list. */
    public function totalCount(): int
    {
        $row = $this->one('SELECT COUNT(*) AS c FROM bills');
        return (int) ($row['c'] ?? 0);
    }

    /**
     * Money still owed across every project that has a known cost: for each
     * project with a budget set, whatever hasn't been paid through a bill yet.
     */
    public function pendingAmountTotal(): float
    {
        $row = $this->one(
            "SELECT COALESCE(SUM(GREATEST(p.budget - COALESCE(paid.total, 0), 0)), 0) AS t
             FROM projects p
             LEFT JOIN (
               SELECT project_id, SUM(amount_paid) AS total
               FROM bills
               WHERE project_id IS NOT NULL
               GROUP BY project_id
             ) paid ON paid.project_id = p.id
             WHERE p.budget IS NOT NULL"
        );
        return (float) ($row['t'] ?? 0);
    }

    /**
     * Raise a new bill. $items is a list of ['description' => string, 'amount' => float].
     * Returns its new id; the caller reads back bill_number via find().
     */
    public function create(array $d, array $items, ?int $createdBy): int
    {
        $method = in_array($d['payment_method'], ClientPayment::METHODS, true) ? $d['payment_method'] : 'other';

        $this->run(
            'INSERT INTO bills (bill_number, client_id, project_id, bill_date, items_json, project_cost,
                                 total_paid, amount_paid, balance_due, payment_method, notes, payment_id, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                'TMP-' . bin2hex(random_bytes(8)), // placeholder, replaced with a number derived from the new id right after insert
                $d['client_id'],
                $d['project_id'] > 0 ? $d['project_id'] : null,
                $d['bill_date'],
                json_encode($items, JSON_UNESCAPED_UNICODE),
                $d['project_cost'],
                $d['total_paid'],
                $d['amount_paid'],
                $d['balance_due'],
                $method,
                $d['notes'] ?: null,
                $d['payment_id'],
                $createdBy,
            ]
        );
        $id = (int) $this->db->lastInsertId();

        $number = $this->nextInvoiceNumber();
        $this->run('UPDATE bills SET bill_number = ? WHERE id = ?', [$number, $id]);

        return $id;
    }

    /**
     * Next invoice number for the current year: INV-2026-001, INV-2026-002, …
     * so the numbering starts at 2026001 for the first invoice of 2026.
     * Reads the highest existing sequence for the year and adds one, rather
     * than counting rows, so a deleted invoice never gets its number reused.
     */
    private function nextInvoiceNumber(): string
    {
        $year = date('Y');
        $row  = $this->one(
            "SELECT bill_number FROM bills WHERE bill_number LIKE ? ORDER BY bill_number DESC LIMIT 1",
            ['INV-' . $year . '-%']
        );

        $seq = 1;
        if ($row !== null && preg_match('/-(\d+)$/', $row['bill_number'], $m)) {
            $seq = (int) $m[1] + 1;
        }

        return 'INV-' . $year . '-' . str_pad((string) $seq, 3, '0', STR_PAD_LEFT);
    }

    /** Permanently remove a bill. Does not touch the linked client_payments row — the caller decides that. */
    public function delete(int $id): void
    {
        $this->run('DELETE FROM bills WHERE id = ?', [$id]);
    }
}
