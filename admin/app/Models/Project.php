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

    /** Every project with task counts, newest first. Optionally narrowed by status. */
    public function allWithSummary(string $status = 'all'): array
    {
        $sql = "SELECT p.*, c.name AS client_name,
                       (SELECT COUNT(*) FROM project_tasks t WHERE t.project_id = p.id) AS task_count,
                       (SELECT COUNT(*) FROM project_tasks t WHERE t.project_id = p.id AND t.status = 'done') AS done_count
                FROM projects p
                LEFT JOIN clients c ON c.id = p.client_id";
        $params = [];
        if (in_array($status, self::STATUSES, true)) {
            $sql .= ' WHERE p.status = ?';
            $params[] = $status;
        }
        $sql .= ' ORDER BY p.created_at DESC';
        return $this->all($sql, $params);
    }

    /** Just id + name + client, for "pick a project" dropdowns in other modules. */
    public function allForSelect(): array
    {
        return $this->all('SELECT id, name, client_id FROM projects ORDER BY name ASC');
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
