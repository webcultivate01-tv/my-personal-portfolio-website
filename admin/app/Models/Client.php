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

    public function totalCount(): int
    {
        $row = $this->one('SELECT COUNT(*) AS c FROM clients');
        return (int) ($row['c'] ?? 0);
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
