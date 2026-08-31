<?php
namespace App\Models;

use App\Core\Model;

/**
 * Reporting queries — one filtered read per report offered in the Reports module.
 *
 * Every method here returns plain rows in the order they should print; the
 * headline numbers on each report are worked out in PHP from those same rows
 * (see ReportController::summarise), so a summary can never disagree with the
 * table printed underneath it.
 *
 * Bills and hosting reuse the search() their own models already expose, so a
 * report and that module's own list page always filter identically.
 */
class Report extends Model
{
    /** Flag filters offered on the enquiry report, on top of the status filter. */
    public const ENQUIRY_FLAGS = ['important', 'client', 'unread', 'read'];

    /** Balance filters offered on the client report. */
    public const CLIENT_BALANCES = ['outstanding', 'settled'];

    /**
     * Enquiries, newest first.
     *
     * @param array $f  status, flag, subject, q, from, to
     */
    public function enquiries(array $f = []): array
    {
        $where  = [];
        $params = [];

        $status = (string) ($f['status'] ?? '');
        if (in_array($status, Lead::STATUSES, true)) {
            $where[]  = 'status = ?';
            $params[] = $status;
        }

        switch ((string) ($f['flag'] ?? '')) {
            case 'important': $where[] = 'is_important = 1'; break;
            case 'client':    $where[] = 'is_client = 1';    break;
            case 'unread':    $where[] = 'is_read = 0';      break;
            case 'read':      $where[] = 'is_read = 1';      break;
        }

        $subject = (string) ($f['subject'] ?? '');
        if (in_array($subject, Lead::SUBJECTS, true)) {
            $where[]  = 'subject = ?';
            $params[] = $subject;
        }

        $q = trim((string) ($f['q'] ?? ''));
        if ($q !== '') {
            $where[] = '(name LIKE ? OR email LIKE ? OR phone LIKE ? OR subject LIKE ? OR message LIKE ?)';
            $like    = '%' . $q . '%';
            array_push($params, $like, $like, $like, $like, $like);
        }

        $this->dateRange($where, $params, 'created_at', $f, true);

        return $this->all(
            'SELECT id, name, email, phone, subject, status, is_important, is_client, is_read, created_at
             FROM leads' . $this->whereSql($where) . '
             ORDER BY created_at DESC, id DESC',
            $params
        );
    }

    /**
     * Projects with their client and task progress, newest first.
     *
     * @param array $f  status, priority, client_id, q, from, to
     */
    public function projects(array $f = []): array
    {
        $where  = [];
        $params = [];

        $status = (string) ($f['status'] ?? '');
        if (in_array($status, Project::STATUSES, true)) {
            $where[]  = 'p.status = ?';
            $params[] = $status;
        }

        $priority = (string) ($f['priority'] ?? '');
        if (in_array($priority, Project::PRIORITIES, true)) {
            $where[]  = 'p.priority = ?';
            $params[] = $priority;
        }

        $clientId = (int) ($f['client_id'] ?? 0);
        if ($clientId > 0) {
            $where[]  = 'p.client_id = ?';
            $params[] = $clientId;
        }

        $q = trim((string) ($f['q'] ?? ''));
        if ($q !== '') {
            $where[] = '(p.name LIKE ? OR p.description LIKE ? OR c.name LIKE ?)';
            $like    = '%' . $q . '%';
            array_push($params, $like, $like, $like);
        }

        $this->dateRange($where, $params, 'p.created_at', $f, true);

        return $this->all(
            "SELECT p.id, p.name, p.status, p.priority, p.start_date, p.due_date, p.budget, p.created_at,
                    c.name AS client_name,
                    (SELECT COUNT(*) FROM project_tasks t WHERE t.project_id = p.id) AS task_count,
                    (SELECT COUNT(*) FROM project_tasks t WHERE t.project_id = p.id AND t.status = 'done') AS done_count
             FROM projects p
             LEFT JOIN clients c ON c.id = p.client_id" . $this->whereSql($where) . '
             ORDER BY p.created_at DESC, p.id DESC',
            $params
        );
    }

    /**
     * Clients with their billing totals, alphabetical.
     *
     * @param array $f  q, balance, from, to
     */
    public function clients(array $f = []): array
    {
        $where  = [];
        $params = [];

        $q = trim((string) ($f['q'] ?? ''));
        if ($q !== '') {
            $where[] = '(c.name LIKE ? OR c.company LIKE ? OR c.email LIKE ? OR c.phone LIKE ?)';
            $like    = '%' . $q . '%';
            array_push($params, $like, $like, $like, $like);
        }

        $this->dateRange($where, $params, 'c.created_at', $f, true);

        // "Outstanding" is derived from the two sub-selects, so it filters in HAVING.
        $having  = '';
        $balance = (string) ($f['balance'] ?? '');
        if ($balance === 'outstanding') {
            $having = ' HAVING outstanding > 0';
        } elseif ($balance === 'settled') {
            $having = ' HAVING outstanding <= 0';
        }

        return $this->all(
            "SELECT c.id, c.name, c.company, c.email, c.phone, c.project_cost, c.created_at,
                    (SELECT COUNT(*) FROM client_meetings m WHERE m.client_id = c.id) AS meeting_count,
                    (SELECT COUNT(*) FROM projects pr WHERE pr.client_id = c.id) AS project_count,
                    (SELECT COALESCE(SUM(i.amount), 0) FROM client_invoices i
                       WHERE i.client_id = c.id AND i.status != 'cancelled') AS total_invoiced,
                    (SELECT COALESCE(SUM(pay.amount), 0) FROM client_payments pay WHERE pay.client_id = c.id) AS total_paid,
                    (SELECT COALESCE(SUM(i.amount), 0) FROM client_invoices i
                       WHERE i.client_id = c.id AND i.status != 'cancelled')
                      - (SELECT COALESCE(SUM(pay.amount), 0) FROM client_payments pay WHERE pay.client_id = c.id) AS outstanding
             FROM clients c" . $this->whereSql($where) . $having . '
             ORDER BY c.name ASC',
            $params
        );
    }

    /** Bills — the history page's own filtered read, so the two stay in step. */
    public function bills(array $f = []): array
    {
        return (new Bill())->search($f);
    }

    /** Hosting & domains — the hosting page's own filtered read. */
    public function hosting(array $f = []): array
    {
        return (new HostingService())->search($f);
    }

    // ---------------- helpers ----------------

    /**
     * Append a 'YYYY-MM-DD' from/to range on $column to the WHERE parts.
     * $isDateTime widens the bounds to cover the whole day on a TIMESTAMP column.
     */
    private function dateRange(array &$where, array &$params, string $column, array $f, bool $isDateTime): void
    {
        $from = (string) ($f['from'] ?? '');
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
            $where[]  = $column . ' >= ?';
            $params[] = $isDateTime ? $from . ' 00:00:00' : $from;
        }

        $to = (string) ($f['to'] ?? '');
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
            $where[]  = $column . ' <= ?';
            $params[] = $isDateTime ? $to . ' 23:59:59' : $to;
        }
    }

    /** ' WHERE a AND b' from a list of conditions, or '' when there are none. */
    private function whereSql(array $where): string
    {
        return $where ? ' WHERE ' . implode(' AND ', $where) : '';
    }
}
