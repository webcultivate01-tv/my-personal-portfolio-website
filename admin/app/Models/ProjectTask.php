<?php
namespace App\Models;

use App\Core\Model;

/**
 * A unit of work inside a project, assigned to one team member.
 */
class ProjectTask extends Model
{
    public const STATUSES   = ['todo', 'in_progress', 'review', 'done'];
    public const PRIORITIES = ['low', 'medium', 'high'];

    /** All tasks for one project, open ones first then by priority/due date. */
    public function forProject(int $projectId): array
    {
        return $this->all(
            "SELECT t.*, u.name AS assigned_to_name, cu.name AS created_by_name
             FROM project_tasks t
             LEFT JOIN users u  ON u.id = t.assigned_to
             LEFT JOIN users cu ON cu.id = t.created_by
             WHERE t.project_id = ?
             ORDER BY FIELD(t.status, 'todo','in_progress','review','done'),
                      FIELD(t.priority, 'high','medium','low'),
                      t.due_date IS NULL, t.due_date ASC, t.id DESC",
            [$projectId]
        );
    }

    /** Task counts across every project, for the dashboard's overall completion ring. */
    public function overallProgress(): array
    {
        $row = $this->one("SELECT COUNT(*) AS total, SUM(status = 'done') AS done FROM project_tasks") ?? [];
        return [
            'total' => (int) ($row['total'] ?? 0),
            'done'  => (int) ($row['done'] ?? 0),
        ];
    }

    /** Every task assigned to one user, across all projects — for "My Tasks". */
    public function forAssignee(int $userId): array
    {
        return $this->all(
            "SELECT t.*, p.name AS project_name, p.status AS project_status
             FROM project_tasks t
             JOIN projects p ON p.id = t.project_id
             WHERE t.assigned_to = ? AND t.status != 'done'
             ORDER BY FIELD(t.priority, 'high','medium','low'), t.due_date IS NULL, t.due_date ASC",
            [$userId]
        );
    }

    public function find(int $id): ?array
    {
        return $this->one('SELECT * FROM project_tasks WHERE id = ? LIMIT 1', [$id]);
    }

    /** Add a new task. Returns its new id. */
    public function create(int $projectId, array $d, ?int $createdBy): int
    {
        $this->run(
            'INSERT INTO project_tasks (project_id, title, description, assigned_to, status, priority, due_date, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $projectId,
                $d['title'],
                $d['description'] ?: null,
                $d['assigned_to'] > 0 ? $d['assigned_to'] : null,
                $d['status'],
                $d['priority'],
                $d['due_date'] ?: null,
                $createdBy,
            ]
        );
        return (int) $this->db->lastInsertId();
    }

    /** Save an existing task's details. */
    public function update(int $id, array $d): void
    {
        $this->run(
            'UPDATE project_tasks SET title = ?, description = ?, assigned_to = ?, priority = ?, due_date = ?
             WHERE id = ?',
            [
                $d['title'],
                $d['description'] ?: null,
                $d['assigned_to'] > 0 ? $d['assigned_to'] : null,
                $d['priority'],
                $d['due_date'] ?: null,
                $id,
            ]
        );
    }

    /** Move a task to a new status. */
    public function updateStatus(int $id, string $status): void
    {
        if (!in_array($status, self::STATUSES, true)) {
            return;
        }
        $this->run('UPDATE project_tasks SET status = ? WHERE id = ?', [$status, $id]);
    }

    /** Permanently remove a task (its comments cascade with it). */
    public function delete(int $id): void
    {
        $this->run('DELETE FROM project_tasks WHERE id = ?', [$id]);
    }
}
