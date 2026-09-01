<?php
namespace App\Models;

use App\Core\Model;

/**
 * Clients managed through the admin-only Client Management module.
 * Meetings, invoices and payments all hang off a client's id.
 */
class Client extends Model
{
    /** Every client, newest name first, with running totals for the list page. */
    public function allWithSummary(): array
    {
        return $this->all(
            "SELECT c.*,
                    (SELECT COUNT(*) FROM client_meetings m WHERE m.client_id = c.id) AS meeting_count,
                    (SELECT COALESCE(SUM(i.amount), 0) FROM client_invoices i
                       WHERE i.client_id = c.id AND i.status != 'cancelled') AS total_invoiced,
                    (SELECT COALESCE(SUM(p.amount), 0) FROM client_payments p WHERE p.client_id = c.id) AS total_paid
             FROM clients c
             ORDER BY c.name ASC"
        );
    }

    public function find(int $id): ?array
    {
        return $this->one('SELECT * FROM clients WHERE id = ? LIMIT 1', [$id]);
    }

    /** Just id + name + company, for "pick a client" dropdowns in other modules. */
    public function allForSelect(): array
    {
        return $this->all('SELECT id, name, company FROM clients ORDER BY name ASC');
    }

    /** Balance filter keys the client list's filter bar offers. */
    public const BALANCE_FILTERS = ['outstanding', 'paid_up'];

    /**
     * Clients narrowed by the list page's filter bar: free-text search
     * (name/company/email/phone/services), whether they have an outstanding
     * balance, and sort order. Any filter left blank is skipped.
     */
    public function search(array $f = []): array
    {
        $where  = [];
        $params = [];

        $q = trim((string) ($f['q'] ?? ''));
        if ($q !== '') {
            $where[] = '(c.name LIKE ? OR c.company LIKE ? OR c.email LIKE ? OR c.phone LIKE ? OR c.services LIKE ?)';
            $like    = '%' . $q . '%';
            array_push($params, $like, $like, $like, $like, $like);
        }

        $sql = "SELECT c.*,
                       (SELECT COUNT(*) FROM client_meetings m WHERE m.client_id = c.id) AS meeting_count,
                       (SELECT COALESCE(SUM(i.amount), 0) FROM client_invoices i
                          WHERE i.client_id = c.id AND i.status != 'cancelled') AS total_invoiced,
                       (SELECT COALESCE(SUM(p.amount), 0) FROM client_payments p WHERE p.client_id = c.id) AS total_paid
                FROM clients c";
        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $balance = (string) ($f['balance'] ?? '');
        if ($balance === 'outstanding') {
            $sql .= ' HAVING (total_invoiced - total_paid) > 0';
        } elseif ($balance === 'paid_up') {
            $sql .= ' HAVING (total_invoiced - total_paid) <= 0';
        }

        $sorts = [
            'name'         => 'c.name ASC',
            'outstanding'  => '(total_invoiced - total_paid) DESC',
            'project_cost' => 'c.project_cost DESC',
            'meetings'     => 'meeting_count DESC',
            'newest'       => 'c.created_at DESC',
        ];
        $sql .= ' ORDER BY ' . ($sorts[(string) ($f['sort'] ?? 'name')] ?? $sorts['name']) . ', c.id DESC';

        return $this->all($sql, $params);
    }

    public function totalCount(): int
    {
        $row = $this->one('SELECT COUNT(*) AS c FROM clients');
        return (int) ($row['c'] ?? 0);
    }

    /** Total invoiced vs total received across every client, for the dashboard's collection ring. */
    public function financialSummary(): array
    {
        $row = $this->one(
            "SELECT COALESCE((SELECT SUM(amount) FROM client_invoices WHERE status != 'cancelled'), 0) AS invoiced,
                    COALESCE((SELECT SUM(amount) FROM client_payments), 0) AS paid"
        ) ?? [];
        return [
            'invoiced' => (float) ($row['invoiced'] ?? 0),
            'paid'     => (float) ($row['paid'] ?? 0),
        ];
    }

    /** Add a new client. Returns its new id. */
    public function create(array $d, ?int $createdBy): int
    {
        $this->run(
            'INSERT INTO clients (name, company, email, phone, address, services, project_cost, notes, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$d['name'], $d['company'] ?: null, $d['email'] ?: null, $d['phone'] ?: null,
             $d['address'] ?: null, $d['services'] ?: null,
             $d['project_cost'] === '' ? null : (float) $d['project_cost'],
             $d['notes'] ?: null, $createdBy]
        );
        return (int) $this->db->lastInsertId();
    }

    /** Save an existing client's details. */
    public function update(int $id, array $d): void
    {
        $this->run(
            'UPDATE clients SET name = ?, company = ?, email = ?, phone = ?, address = ?,
                                services = ?, project_cost = ?, notes = ?
             WHERE id = ?',
            [$d['name'], $d['company'] ?: null, $d['email'] ?: null, $d['phone'] ?: null,
             $d['address'] ?: null, $d['services'] ?: null,
             $d['project_cost'] === '' ? null : (float) $d['project_cost'],
             $d['notes'] ?: null, $id]
        );
    }

    /** Permanently remove a client (meetings/invoices/payments cascade with it). */
    public function delete(int $id): void
    {
        $this->run('DELETE FROM clients WHERE id = ?', [$id]);
    }
}
