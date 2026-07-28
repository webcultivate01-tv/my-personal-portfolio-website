<?php
namespace App\Models;

use App\Core\Model;

/**
 * Comments on a task — a running timeline (mirrors LeadNote), so admin and
 * developer updates stack up instead of overwriting each other.
 */
class ProjectTaskNote extends Model
{
    /** All comments on one task, oldest first (a conversation reads top-down). */
    public function forTask(int $taskId): array
    {
        return $this->all(
            'SELECT n.*, u.name AS author_name
             FROM project_task_notes n
             LEFT JOIN users u ON u.id = n.user_id
             WHERE n.task_id = ?
             ORDER BY n.created_at ASC, n.id ASC',
            [$taskId]
        );
    }

    public function add(int $taskId, ?int $userId, string $note): void
    {
        $this->run(
            'INSERT INTO project_task_notes (task_id, user_id, note) VALUES (?, ?, ?)',
            [$taskId, $userId, $note]
        );
    }
}
