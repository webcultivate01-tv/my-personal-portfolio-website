<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\ProjectTaskNote;
use App\Models\Client;
use App\Models\User;

/**
 * Project Management — admins and managers work from here together.
 *
 * Both roles can view every project and its task board, and comment on a
 * task. Creating/editing/deleting projects and tasks is admin-only; moving a
 * task's status (todo -> in_progress -> review -> done) is allowed for an
 * admin or whoever the task is assigned to, so a developer can update their
 * own work without needing full access.
 */
class ProjectController extends Controller
{
    /** The project list, optionally filtered by status. */
    public function index(): void
    {
        $this->requireAuth();

        $status = $_GET['status'] ?? 'all';
        $status = in_array($status, Project::STATUSES, true) ? $status : 'all';

        $projects = new Project();

        $this->view('projects/index', [
            'title'          => 'Project Management',
            'active'         => 'projects',
            'csrf'           => $this->csrfToken(),
            'projects'       => $projects->allWithSummary($status),
            'status'         => $status,
            'totalCount'     => $projects->totalCount(),
            'planningCount'  => $projects->countByStatus('planning'),
            'inProgressCount'=> $projects->countByStatus('in_progress'),
            'completedCount' => $projects->countByStatus('completed'),
            'myOpenTasks'    => (new ProjectTask())->forAssignee((int) Auth::id()),
            'clients'        => Auth::isAdmin() ? (new Client())->allWithSummary() : [],
            'projectStatuses'   => Project::STATUSES,
            'projectPriorities' => Project::PRIORITIES,
        ]);
    }

    /** One project's full board: details + its tasks. */
    public function show(): void
    {
        $this->requireAuth();

        $id      = (int) ($_GET['id'] ?? 0);
        $project = (new Project())->find($id);
        if ($project === null) {
            $this->flash('error', 'That project no longer exists.');
            $this->redirect('/projects');
        }

        $tasks = (new ProjectTask())->forProject($id);

        $notesByTask = [];
        foreach ($tasks as $t) {
            $notesByTask[(int) $t['id']] = (new ProjectTaskNote())->forTask((int) $t['id']);
        }

        $this->view('projects/show', [
            'title'          => $project['name'],
            'active'         => 'projects',
            'csrf'           => $this->csrfToken(),
            'project'        => $project,
            'tasks'          => $tasks,
            'notesByTask'    => $notesByTask,
            'users'          => (new User())->allUsers(),
            'clients'        => Auth::isAdmin() ? (new Client())->allWithSummary() : [],
            'taskStatuses'   => ProjectTask::STATUSES,
            'taskPriorities' => ProjectTask::PRIORITIES,
            'projectStatuses'   => Project::STATUSES,
            'projectPriorities' => Project::PRIORITIES,
        ]);
    }

    /** Add a new project. */
    public function store(): void
    {
        $this->requireAdmin();
        $this->verifyCsrf();

        $data  = $this->collectProjectInput();
        $error = $this->validateProject($data);
        if ($error !== null) {
            $this->flash('error', $error);
            $this->redirect('/projects');
        }

        $id = (new Project())->create($data, Auth::id());
        $this->flash('success', 'Project "' . $data['name'] . '" was created.');
        $this->redirect('/projects/view?id=' . $id);
    }

    /** Save changes to a project's details. */
    public function update(): void
    {
        $this->requireAdmin();
        $this->verifyCsrf();

        $id       = $this->intInput('id');
        $projects = new Project();
        if ($projects->find($id) === null) {
            $this->flash('error', 'That project no longer exists.');
            $this->redirect('/projects');
        }

        $data  = $this->collectProjectInput();
        $error = $this->validateProject($data);
        if ($error !== null) {
            $this->flash('error', $error);
            $this->redirect('/projects/view?id=' . $id);
        }

        $projects->update($id, $data);
        $this->flash('success', 'Project details updated.');
        $this->redirect('/projects/view?id=' . $id);
    }

    /** Delete a project along with all its tasks and comments. */
    public function destroy(): void
    {
        $this->requireAdmin();
        $this->verifyCsrf();

        $id       = $this->intInput('id');
        $projects = new Project();
        $project  = $projects->find($id);
        if ($project === null) {
            $this->flash('error', 'That project no longer exists.');
            $this->redirect('/projects');
        }

        $projects->delete($id);
        $this->flash('success', 'Project "' . $project['name'] . '" and all its tasks were removed.');
        $this->redirect('/projects');
    }

    // ---------- Tasks ----------

    /** Add a task to a project. */
    public function storeTask(): void
    {
        $this->requireAdmin();
        $this->verifyCsrf();

        $projectId = $this->intInput('project_id');
        if ((new Project())->find($projectId) === null) {
            $this->flash('error', 'That project no longer exists.');
            $this->redirect('/projects');
        }

        $data  = $this->collectTaskInput();
        $error = $this->validateTask($data);
        if ($error !== null) {
            $this->flash('error', $error);
            $this->redirect('/projects/view?id=' . $projectId);
        }

        (new ProjectTask())->create($projectId, $data, Auth::id());
        $this->flash('success', 'Task "' . $data['title'] . '" added.');
        $this->redirect('/projects/view?id=' . $projectId);
    }

    /** Save changes to a task's details (title, description, assignee, priority, due date). */
    public function updateTask(): void
    {
        $this->requireAdmin();
        $this->verifyCsrf();

        $id    = $this->intInput('id');
        $tasks = new ProjectTask();
        $task  = $tasks->find($id);
        if ($task === null) {
            $this->flash('error', 'That task no longer exists.');
            $this->redirect('/projects');
        }

        $data  = $this->collectTaskInput();
        $error = $this->validateTask($data);
        if ($error !== null) {
            $this->flash('error', $error);
            $this->redirect('/projects/view?id=' . $task['project_id']);
        }

        $tasks->update($id, $data);
        $this->flash('success', 'Task updated.');
        $this->redirect('/projects/view?id=' . $task['project_id']);
    }

    /** Move a task to a new status — the admin, or whoever it's assigned to. */
    public function updateTaskStatus(): void
    {
        $this->requireAuth();
        $this->verifyCsrf();

        $id    = $this->intInput('id');
        $tasks = new ProjectTask();
        $task  = $tasks->find($id);
        if ($task === null) {
            $this->flash('error', 'That task no longer exists.');
            $this->redirect('/projects');
        }
        if (!$this->canActOnTask($task)) {
            http_response_code(403);
            $this->flash('error', 'You can only update the status of tasks assigned to you.');
            $this->redirect('/projects/view?id=' . $task['project_id']);
        }

        $tasks->updateStatus($id, $this->input('status'));
        $this->flash('success', 'Task status updated.');
        $this->redirect('/projects/view?id=' . $task['project_id']);
    }

    /** Delete a task. */
    public function destroyTask(): void
    {
        $this->requireAdmin();
        $this->verifyCsrf();

        $id    = $this->intInput('id');
        $tasks = new ProjectTask();
        $task  = $tasks->find($id);
        if ($task === null) {
            $this->flash('error', 'That task no longer exists.');
            $this->redirect('/projects');
        }

        $tasks->delete($id);
        $this->flash('success', 'Task deleted.');
        $this->redirect('/projects/view?id=' . $task['project_id']);
    }

    /** Add a comment to a task — the admin, or whoever it's assigned to. */
    public function addTaskNote(): void
    {
        $this->requireAuth();
        $this->verifyCsrf();

        $id    = $this->intInput('id');
        $note  = $this->input('note');
        $tasks = new ProjectTask();
        $task  = $tasks->find($id);
        if ($task === null) {
            $this->flash('error', 'That task no longer exists.');
            $this->redirect('/projects');
        }
        if (!$this->canActOnTask($task)) {
            http_response_code(403);
            $this->flash('error', 'You can only comment on tasks assigned to you.');
            $this->redirect('/projects/view?id=' . $task['project_id']);
        }

        if ($note !== '') {
            (new ProjectTaskNote())->add($id, Auth::id(), $note);
            $this->flash('success', 'Comment added.');
        }
        $this->redirect('/projects/view?id=' . $task['project_id']);
    }

    // ---------- Helpers ----------

    /** Admins can act on any task; everyone else only on tasks assigned to them. */
    private function canActOnTask(array $task): bool
    {
        return Auth::isAdmin() || (int) $task['assigned_to'] === (int) Auth::id();
    }

    private function collectProjectInput(): array
    {
        return [
            'name'        => $this->input('name'),
            'client_id'   => $this->intInput('client_id'),
            'description' => $this->input('description'),
            'status'      => $this->input('status', 'planning'),
            'priority'    => $this->input('priority', 'medium'),
            'start_date'  => $this->input('start_date'),
            'due_date'    => $this->input('due_date'),
            'budget'      => $this->input('budget'),
        ];
    }

    /** Returns an error message, or null when the project details are fine. */
    private function validateProject(array $d): ?string
    {
        if ($d['name'] === '') {
            return 'A project needs a name.';
        }
        if (!in_array($d['status'], Project::STATUSES, true)) {
            return 'That is not a valid project status.';
        }
        if (!in_array($d['priority'], Project::PRIORITIES, true)) {
            return 'That is not a valid priority.';
        }
        if ($d['start_date'] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $d['start_date'])) {
            return 'The start date is not a valid date.';
        }
        if ($d['due_date'] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $d['due_date'])) {
            return 'The due date is not a valid date.';
        }
        if ($d['budget'] !== '' && (!is_numeric($d['budget']) || (float) $d['budget'] < 0)) {
            return 'Budget must be a number that is zero or more.';
        }
        return null;
    }

    private function collectTaskInput(): array
    {
        return [
            'title'       => $this->input('title'),
            'description' => $this->input('description'),
            'assigned_to' => $this->intInput('assigned_to'),
            'status'      => $this->input('status', 'todo'),
            'priority'    => $this->input('priority', 'medium'),
            'due_date'    => $this->input('due_date'),
        ];
    }

    /** Returns an error message, or null when the task details are fine. */
    private function validateTask(array $d): ?string
    {
        if ($d['title'] === '') {
            return 'A task needs a title.';
        }
        if (!in_array($d['status'], ProjectTask::STATUSES, true)) {
            return 'That is not a valid task status.';
        }
        if (!in_array($d['priority'], ProjectTask::PRIORITIES, true)) {
            return 'That is not a valid priority.';
        }
        if ($d['due_date'] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $d['due_date'])) {
            return 'The due date is not a valid date.';
        }
        return null;
    }
}
