<?php
/**
 * @var string $csrf @var array $projects @var string $status @var array $myOpenTasks
 * @var int $totalCount @var int $planningCount @var int $inProgressCount @var int $completedCount
 * @var array $clients @var array $projectStatuses @var array $projectPriorities
 */
$isAdmin = \App\Core\Auth::isAdmin();
$today   = date('Y-m-d');

/** Build a filter-tab URL. */
$tab = static fn(string $key): string => url('/projects') . ($key === 'all' ? '' : '?status=' . $key);

/** Filter tabs: key => label. */
$tabs = ['all' => 'All'];
foreach ($projectStatuses as $s) {
    $tabs[$s] = ucwords(str_replace('_', ' ', $s));
}
?>
<header class="page-head">
  <div>
    <h1 class="page-head__title">Project Management</h1>
    <p class="page-head__sub">Every project the team is working on — its tasks, who they're assigned to, and how far along they are.</p>
  </div>
  <?php if ($isAdmin): ?>
    <button type="button" class="btn btn--primary" onclick="toggleProjectForm(true)">+ New project</button>
  <?php endif; ?>
</header>

<section class="stat-grid">
  <div class="stat"><span class="stat__label">Projects</span><span class="stat__value"><?= (int) $totalCount ?></span></div>
  <div class="stat"><span class="stat__label">Planning</span><span class="stat__value"><?= (int) $planningCount ?></span></div>
  <div class="stat"><span class="stat__label">In progress</span><span class="stat__value"><?= (int) $inProgressCount ?></span></div>
  <div class="stat"><span class="stat__label">Completed</span><span class="stat__value"><?= (int) $completedCount ?></span></div>
  <div class="stat<?= count($myOpenTasks) > 0 ? ' stat--alert' : '' ?>">
    <span class="stat__label">My open tasks</span><span class="stat__value"><?= count($myOpenTasks) ?></span>
  </div>
</section>

<?php if (!empty($myOpenTasks)): ?>
  <section class="panel">
    <div class="panel__head">
      <h2 class="panel__title">My tasks</h2>
      <span class="panel__count"><?= count($myOpenTasks) ?> open</span>
    </div>
    <div class="my-tasks">
      <?php foreach ($myOpenTasks as $t): ?>
        <?php $overdue = !empty($t['due_date']) && $t['due_date'] < $today && $t['status'] !== 'done'; ?>
        <div class="my-task-row">
          <div>
            <div class="my-task-row__title"><?= e($t['title']) ?></div>
            <div class="my-task-row__project">
              <a href="<?= e(url('/projects/view') . '?id=' . (int) $t['project_id']) ?>"><?= e($t['project_name']) ?></a>
              <?php if (!empty($t['due_date'])): ?>
                &nbsp;·&nbsp;<span class="<?= $overdue ? 'task-card__overdue' : '' ?>">Due <?= e(date('M j, Y', strtotime($t['due_date']))) ?></span>
              <?php endif; ?>
            </div>
          </div>
          <div class="row-actions">
            <span class="badge badge--priority-<?= e($t['priority']) ?>"><?= e($t['priority']) ?></span>
            <form method="post" action="<?= url('/projects/tasks/status') ?>" class="inline-form">
              <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
              <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
              <select name="status" class="status-select status-select--task-<?= e($t['status']) ?>" onchange="this.form.submit()">
                <?php foreach (\App\Models\ProjectTask::STATUSES as $s): ?>
                  <option value="<?= e($s) ?>"<?= $t['status'] === $s ? ' selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', $s))) ?></option>
                <?php endforeach; ?>
              </select>
            </form>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </section>
<?php endif; ?>

<?php if ($isAdmin): ?>
  <section class="panel panel--pad" id="project-form" hidden>
    <div class="panel__head panel__head--plain">
      <h2 class="panel__title">New project</h2>
    </div>
    <form class="form-grid" method="post" action="<?= url('/projects/create') ?>">
      <input type="hidden" name="csrf" value="<?= e($csrf) ?>">

      <div class="field field--wide">
        <label class="form__label" for="name">Project name</label>
        <input class="form__input" type="text" id="name" name="name" placeholder="e.g. Acme website redesign" required>
      </div>
      <div class="field">
        <label class="form__label" for="client_id">Client (optional)</label>
        <select class="form__input" id="client_id" name="client_id">
          <option value="0">No client linked</option>
          <?php foreach ($clients as $c): ?>
            <option value="<?= (int) $c['id'] ?>"><?= e($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label class="form__label" for="status">Status</label>
        <select class="form__input" id="status" name="status">
          <?php foreach ($projectStatuses as $s): ?>
            <option value="<?= e($s) ?>"<?= $s === 'planning' ? ' selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', $s))) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label class="form__label" for="priority">Priority</label>
        <select class="form__input" id="priority" name="priority">
          <?php foreach ($projectPriorities as $p): ?>
            <option value="<?= e($p) ?>"<?= $p === 'medium' ? ' selected' : '' ?>><?= e(ucfirst($p)) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label class="form__label" for="budget">Budget (₹)</label>
        <input class="form__input" type="number" id="budget" name="budget" step="0.01" min="0" placeholder="Optional">
      </div>
      <div class="field">
        <label class="form__label" for="start_date">Start date</label>
        <input class="form__input" type="date" id="start_date" name="start_date">
      </div>
      <div class="field">
        <label class="form__label" for="due_date">Due date</label>
        <input class="form__input" type="date" id="due_date" name="due_date">
      </div>
      <div class="field field--wide">
        <label class="form__label" for="description">Description</label>
        <textarea class="form__input" id="description" name="description" rows="2" placeholder="What this project covers"></textarea>
      </div>
      <div class="field field--action">
        <button type="submit" class="btn btn--primary">Create project</button>
        <button type="button" class="btn btn--ghost" onclick="toggleProjectForm(false)">Cancel</button>
      </div>
    </form>
  </section>
<?php endif; ?>

<section class="panel">
  <div class="panel__head">
    <div class="filter-tabs">
      <?php foreach ($tabs as $key => $label): ?>
        <a class="filter-tab<?= $status === $key ? ' is-active' : '' ?>" href="<?= e($tab($key)) ?>"><?= e($label) ?></a>
      <?php endforeach; ?>
    </div>
    <span class="panel__count"><?= count($projects) ?> shown</span>
  </div>

  <?php if (empty($projects)): ?>
    <p class="empty">
      <?php if ($isAdmin): ?>
        No projects yet. Use <strong>+ New project</strong> above to create your first one — then add tasks and assign them to your team.
      <?php else: ?>
        No projects to show yet.
      <?php endif; ?>
    </p>
  <?php else: ?>
    <table class="table">
      <thead>
        <tr>
          <th>Project</th><th>Client</th><th>Status</th><th>Priority</th>
          <th>Tasks</th><th>Due</th><th class="ta-right">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($projects as $p): ?>
          <?php
            $viewUrl = url('/projects/view') . '?id=' . (int) $p['id'];
            $pct     = $p['task_count'] > 0 ? (int) round(($p['done_count'] / $p['task_count']) * 100) : 0;
          ?>
          <tr>
            <td>
              <a class="enq-from" href="<?= e($viewUrl) ?>">
                <span class="enq-from__name"><?= e($p['name']) ?></span>
                <?php if (!empty($p['due_date'])): ?>
                  <span class="enq-from__email">Due <?= e(date('M j, Y', strtotime($p['due_date']))) ?></span>
                <?php endif; ?>
              </a>
            </td>
            <td><?= !empty($p['client_name']) ? e($p['client_name']) : '<span class="muted">—</span>' ?></td>
            <td><span class="badge status-select--<?= e($p['status']) ?>"><?= e(ucwords(str_replace('_', ' ', $p['status']))) ?></span></td>
            <td><span class="badge badge--priority-<?= e($p['priority']) ?>"><?= e($p['priority']) ?></span></td>
            <td>
              <div class="progress">
                <div class="progress__track"><div class="progress__fill" style="width:<?= (int) $pct ?>%"></div></div>
                <span class="progress__label"><?= (int) $p['done_count'] ?>/<?= (int) $p['task_count'] ?></span>
              </div>
            </td>
            <td class="muted"><?= !empty($p['due_date']) ? e(date('M j, Y', strtotime($p['due_date']))) : '—' ?></td>
            <td class="ta-right">
              <div class="row-actions">
                <a class="btn btn--sm btn--ghost" href="<?= e($viewUrl) ?>">Open</a>
                <?php if ($isAdmin): ?>
                  <form method="post" action="<?= url('/projects/delete') ?>" class="inline-form"
                        onsubmit="return confirm('Delete <?= e($p['name']) ?>? All its tasks and comments are deleted too. This cannot be undone.')">
                    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                    <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                    <button type="submit" class="btn btn--sm btn--danger">Delete</button>
                  </form>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</section>

<script>
  function toggleProjectForm(show) {
    var panel = document.getElementById('project-form');
    if (!panel) return;
    panel.hidden = !show;
    if (show) { panel.scrollIntoView({behavior: 'smooth'}); panel.querySelector('#name').focus(); }
  }
</script>
