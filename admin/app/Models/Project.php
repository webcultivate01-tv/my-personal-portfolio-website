<?php
namespace App\Models;

use App\Core\Model;

/**
 * Projects tracked in the Project Management module.
 * Optionally linked to a client; holds a list of tasks.
 */
class Project extends Model
{
    public const STATUSES  = ['planning', 'in_progress', 'on_hold', 'completed', 'cancelled'];
    public const PRIORITIES = ['low', 'medium', 'high'];

    /** Sort keys the project list's filter bar offers. */
    public const SORTS = ['newest', 'name', 'due_date', 'priority'];

    /**
     * Projects narrowed by the list page's filter bar: status tab, free-text
     * search (name/description/client), client, priority, and sort order.
     * Any filter left blank/'all' is skipped.
     */
    public function search(array $f = []): array
    {
        $where  = [];
        $params = [];

        $status = (string) ($f['status'] ?? 'all');
        if (in_array($status, self::STATUSES, true)) {
            $where[]  = 'p.status = ?';
            $params[] = $status;
        }

        $q = trim((string) ($f['q'] ?? ''));
        if ($q !== '') {
            $where[] = '(p.name LIKE ? OR p.description LIKE ? OR c.name LIKE ?)';
            $like    = '%' . $q . '%';
            array_push($params, $like, $like, $like);
        }

        $clientId = (int) ($f['client_id'] ?? 0);
        if ($clientId > 0) {
            $where[]  = 'p.client_id = ?';
            $params[] = $clientId;
        }

        $priority = (string) ($f['priority'] ?? '');
        if (in_array($priority, self::PRIORITIES, true)) {
            $where[]  = 'p.priority = ?';
            $params[] = $priority;
        }

        $sql = "SELECT p.*, c.name AS client_name,
                       (SELECT COUNT(*) FROM project_tasks t WHERE t.project_id = p.id) AS task_count,
                       (SELECT COUNT(*) FROM project_tasks t WHERE t.project_id = p.id AND t.status = 'done') AS done_count
                FROM projects p
                LEFT JOIN clients c ON c.id = p.client_id";
        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sorts = [
            'newest'   => 'p.created_at DESC',
            'name'     => 'p.name ASC',
            'due_date' => 'p.due_date IS NULL, p.due_date ASC',
            'priority' => "FIELD(p.priority, 'high', 'medium', 'low')",
        ];
        $sql .= ' ORDER BY ' . ($sorts[(string) ($f['sort'] ?? 'newest')] ?? $sorts['newest']);

        return $this->all($sql, $params);
    }

    /** Just id + name + client + budget, for "pick a project" dropdowns in other modules. */
    public function allForSelect(): array
    {
        return $this->all('SELECT id, name, client_id, budget FROM projects ORDER BY name ASC');
    }

    public function find(int $id): ?array
    {
        return $this->one(
            'SELECT p.*, c.name AS client_name
             FROM projects p
             LEFT JOIN clients c ON c.id = p.client_id
             WHERE p.id = ? LIMIT 1',
            [$id]
        );
    }

    public function totalCount(): int
    {
        $row = $this->one('SELECT COUNT(*) AS c FROM projects');
        return (int) ($row['c'] ?? 0);
    }

    public function countByStatus(string $status): int
    {
        $row = $this->one('SELECT COUNT(*) AS c FROM projects WHERE status = ?', [$status]);
        return (int) ($row['c'] ?? 0);
    }

    /** Every status count in one query, always keyed by all STATUSES (0 when empty). */
    public function countsByStatus(): array
    {
        $out = array_fill_keys(self::STATUSES, 0);
        foreach ($this->all('SELECT status, COUNT(*) AS c FROM projects GROUP BY status') as $row) {
            if (isset($out[$row['status']])) {
                $out[$row['status']] = (int) $row['c'];
            }
        }
        return $out;
    }

    /** Add a new project. Returns its new id. */
    public function create(array $d, ?int $createdBy): int
    {
        $this->run(
            'INSERT INTO projects (name, client_id, description, status, priority, start_date, due_date, budget, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $d['name'],
                $d['client_id'] > 0 ? $d['client_id'] : null,
                $d['description'] ?: null,
                $d['status'],
                $d['priority'],
                $d['start_date'] ?: null,
                $d['due_date'] ?: null,
                $d['budget'] === '' ? null : (float) $d['budget'],
                $createdBy,
            ]
        );
        return (int) $this->db->lastInsertId();
    }

    /** Save an existing project's details. */
    public function update(int $id, array $d): void
    {
        $this->run(
            'UPDATE projects SET name = ?, client_id = ?, description = ?, status = ?, priority = ?,
                                  start_date = ?, due_date = ?, budget = ?
             WHERE id = ?',
            [
                $d['name'],
                $d['client_id'] > 0 ? $d['client_id'] : null,
                $d['description'] ?: null,
                $d['status'],
                $d['priority'],
                $d['start_date'] ?: null,
                $d['due_date'] ?: null,
                $d['budget'] === '' ? null : (float) $d['budget'],
                $id,
            ]
        );
    }

    /** Permanently remove a project (its tasks cascade with it). */
    public function delete(int $id): void
    {
        $this->run('DELETE FROM projects WHERE id = ?', [$id]);
    }
}
