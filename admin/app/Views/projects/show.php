<?php
/**
 * One project's board: details + its tasks, each with a comment timeline.
 *
 * @var string $csrf @var array $project @var array $tasks @var array $notesByTask
 * @var array $users @var array $clients @var array $taskStatuses @var array $taskPriorities
 * @var array $projectStatuses @var array $projectPriorities
 */
$isAdmin = \App\Core\Auth::isAdmin();
$myId    = (int) \App\Core\Auth::id();
$today   = date('Y-m-d');
$money   = static fn(?float $n): string => $n !== null ? '₹' . number_format($n, 2) : '—';

/** Admins can act on any task; everyone else only on tasks assigned to them. */
$canAct = static fn(array $t): bool => $isAdmin || (int) $t['assigned_to'] === $myId;

$doneCount = 0;
foreach ($tasks as $t) {
    if ($t['status'] === 'done') $doneCount++;
}
?>
<header class="page-head">
  <div>
    <h1 class="page-head__title"><?= e($project['name']) ?></h1>
    <p class="page-head__sub">
      <?php if (!empty($project['client_name'])): ?><?= e($project['client_name']) ?>&nbsp;·&nbsp;<?php endif; ?>
      Created <?= e(date('M j, Y', strtotime($project['created_at']))) ?>
    </p>
  </div>
  <a class="btn btn--ghost btn--sm" href="<?= url('/projects') ?>">&larr; Back to projects</a>
</header>

<section class="stat-grid">
  <div class="stat"><span class="stat__label">Tasks</span><span class="stat__value"><?= count($tasks) ?></span></div>
  <div class="stat"><span class="stat__label">Done</span><span class="stat__value"><?= (int) $doneCount ?></span></div>
  <div class="stat"><span class="stat__label">Status</span><span class="stat__value stat__value--sm"><?= e(ucwords(str_replace('_', ' ', $project['status']))) ?></span></div>
  <div class="stat"><span class="stat__label">Priority</span><span class="stat__value stat__value--sm"><?= e(ucfirst($project['priority'])) ?></span></div>
  <div class="stat"><span class="stat__label">Budget</span><span class="stat__value stat__value--money"><?= e($money($project['budget'] !== null ? (float) $project['budget'] : null)) ?></span></div>
</section>

<!-- ---------- Project details ---------- -->
<section class="panel panel--pad">
  <div class="panel__head panel__head--plain">
    <h2 class="panel__title">Project details</h2>
    <?php if ($isAdmin): ?>
      <button type="button" class="btn btn--sm btn--ghost" data-toggle="details-form"
              data-label-open="Edit details" data-label-close="Cancel">Edit details</button>
    <?php endif; ?>
  </div>

  <div class="stat-grid stat-grid--profile" id="details-view">
    <div class="stat"><span class="stat__label">Client</span><span class="stat__value stat__value--sm"><?= e($project['client_name'] ?? '—') ?></span></div>
    <div class="stat"><span class="stat__label">Start date</span><span class="stat__value stat__value--sm"><?= !empty($project['start_date']) ? e(date('M j, Y', strtotime($project['start_date']))) : '—' ?></span></div>
    <div class="stat"><span class="stat__label">Due date</span><span class="stat__value stat__value--sm"><?= !empty($project['due_date']) ? e(date('M j, Y', strtotime($project['due_date']))) : '—' ?></span></div>
    <div class="stat"><span class="stat__label">Description</span><span class="stat__value stat__value--sm"><?= e($project['description'] ?: '—') ?></span></div>
  </div>

  <?php if ($isAdmin): ?>
    <form class="form-grid" id="details-form" method="post" action="<?= url('/projects/update') ?>" hidden>
      <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
      <input type="hidden" name="id" value="<?= (int) $project['id'] ?>">

      <div class="field field--wide">
        <label class="form__label" for="name">Project name</label>
        <input class="form__input" type="text" id="name" name="name" value="<?= e($project['name']) ?>" required>
      </div>
      <div class="field">
        <label class="form__label" for="client_id">Client</label>
        <select class="form__input" id="client_id" name="client_id">
          <option value="0">No client linked</option>
          <?php foreach ($clients as $c): ?>
            <option value="<?= (int) $c['id'] ?>"<?= (int) $project['client_id'] === (int) $c['id'] ? ' selected' : '' ?>><?= e($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label class="form__label" for="status">Status</label>
        <select class="form__input" id="status" name="status">
          <?php foreach ($projectStatuses as $s): ?>
            <option value="<?= e($s) ?>"<?= $project['status'] === $s ? ' selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', $s))) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label class="form__label" for="priority">Priority</label>
        <select class="form__input" id="priority" name="priority">
          <?php foreach ($projectPriorities as $p): ?>
            <option value="<?= e($p) ?>"<?= $project['priority'] === $p ? ' selected' : '' ?>><?= e(ucfirst($p)) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label class="form__label" for="budget">Budget (₹)</label>
        <input class="form__input" type="number" id="budget" name="budget" step="0.01" min="0"
               value="<?= $project['budget'] !== null ? e(number_format((float) $project['budget'], 2, '.', '')) : '' ?>">
      </div>
      <div class="field">
        <label class="form__label" for="start_date">Start date</label>
        <input class="form__input" type="date" id="start_date" name="start_date" value="<?= e($project['start_date'] ?? '') ?>">
      </div>
      <div class="field">
        <label class="form__label" for="due_date">Due date</label>
        <input class="form__input" type="date" id="due_date" name="due_date" value="<?= e($project['due_date'] ?? '') ?>">
      </div>
      <div class="field field--wide">
        <label class="form__label" for="description">Description</label>
        <textarea class="form__input" id="description" name="description" rows="3"><?= e($project['description'] ?? '') ?></textarea>
      </div>
      <div class="field field--action">
        <button type="submit" class="btn btn--primary">Save details</button>
      </div>
    </form>
  <?php endif; ?>
</section>

<!-- ---------- Tasks ---------- -->
<section class="panel">
  <div class="panel__head panel__head--plain">
    <h2 class="panel__title">Tasks</h2>
    <div class="panel__actions">
      <span class="panel__count"><?= (int) $doneCount ?> / <?= count($tasks) ?> done</span>
      <?php if ($isAdmin): ?>
        <button type="button" class="btn btn--sm btn--primary" data-toggle="task-form"
                data-label-open="+ Add task" data-label-close="Cancel">+ Add task</button>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($isAdmin): ?>
    <form class="form-grid" id="task-form" method="post" action="<?= url('/projects/tasks/create') ?>" hidden>
      <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
      <input type="hidden" name="project_id" value="<?= (int) $project['id'] ?>">
      <div class="field field--wide">
        <label class="form__label" for="title">Task title</label>
        <input class="form__input" type="text" id="title" name="title" placeholder="e.g. Build the contact page" required>
      </div>
      <div class="field">
        <label class="form__label" for="assigned_to">Assign to</label>
        <select class="form__input" id="assigned_to" name="assigned_to">
          <option value="0">Unassigned</option>
          <?php foreach ($users as $u): ?>
            <option value="<?= (int) $u['id'] ?>"><?= e($u['name']) ?> (<?= e(ucfirst($u['role'])) ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label class="form__label" for="status">Status</label>
        <select class="form__input" id="status" name="status">
          <?php foreach ($taskStatuses as $s): ?>
            <option value="<?= e($s) ?>"<?= $s === 'todo' ? ' selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', $s))) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label class="form__label" for="priority">Priority</label>
        <select class="form__input" id="priority" name="priority">
          <?php foreach ($taskPriorities as $p): ?>
            <option value="<?= e($p) ?>"<?= $p === 'medium' ? ' selected' : '' ?>><?= e(ucfirst($p)) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label class="form__label" for="due_date">Due date</label>
        <input class="form__input" type="date" id="due_date" name="due_date">
      </div>
      <div class="field field--wide">
        <label class="form__label" for="description">Description</label>
        <textarea class="form__input" id="description" name="description" rows="2" placeholder="What needs to be done"></textarea>
      </div>
      <div class="field field--action">
        <button type="submit" class="btn btn--primary btn--sm">Add task</button>
      </div>
    </form>
  <?php endif; ?>

  <?php if (empty($tasks)): ?>
    <p class="empty">
      <?php if ($isAdmin): ?>
        No tasks yet. Use <strong>+ Add task</strong> above to break this project down and assign work.
      <?php else: ?>
        No tasks have been added to this project yet.
      <?php endif; ?>
    </p>
  <?php else: ?>
    <div class="task-list">
      <?php foreach ($tasks as $t): ?>
        <?php
          $mayAct  = $canAct($t);
          $overdue = !empty($t['due_date']) && $t['due_date'] < $today && $t['status'] !== 'done';
          $notes   = $notesByTask[(int) $t['id']] ?? [];
        ?>
        <div class="task-card<?= $t['status'] === 'done' ? ' task-card--done' : '' ?>">
          <div class="task-card__head">
            <div class="task-card__title">
              <span class="badge badge--priority-<?= e($t['priority']) ?>"><?= e($t['priority']) ?></span>
              <strong><?= e($t['title']) ?></strong>
            </div>
            <div class="row-actions">
              <form method="post" action="<?= url('/projects/tasks/status') ?>" class="inline-form">
                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
                <select name="status" class="status-select status-select--task-<?= e($t['status']) ?>"
                        onchange="this.form.submit()"<?= $mayAct ? '' : ' disabled' ?>>
                  <?php foreach ($taskStatuses as $s): ?>
                    <option value="<?= e($s) ?>"<?= $t['status'] === $s ? ' selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', $s))) ?></option>
                  <?php endforeach; ?>
                </select>
              </form>
              <?php if ($isAdmin): ?>
                <button type="button" class="btn btn--sm btn--ghost" data-toggle="task-edit-<?= (int) $t['id'] ?>"
                        data-label-open="Edit" data-label-close="Cancel">Edit</button>
                <form method="post" action="<?= url('/projects/tasks/delete') ?>" class="inline-form"
                      onsubmit="return confirm('Delete task &quot;<?= e($t['title']) ?>&quot;?')">
                  <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                  <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
                  <button type="submit" class="btn btn--sm btn--danger">Delete</button>
                </form>
              <?php endif; ?>
            </div>
          </div>

          <div class="task-card__meta">
            <span>Assigned to <strong><?= e($t['assigned_to_name'] ?? 'Unassigned') ?></strong></span>
            <?php if (!empty($t['due_date'])): ?>
              <span class="<?= $overdue ? 'task-card__overdue' : '' ?>">Due <?= e(date('M j, Y', strtotime($t['due_date']))) ?><?= $overdue ? ' (overdue)' : '' ?></span>
            <?php endif; ?>
            <span>Added by <?= e($t['created_by_name'] ?? 'Deleted user') ?></span>
          </div>

          <?php if (!empty($t['description'])): ?>
            <div class="task-card__desc"><?= nl2br(e($t['description'])) ?></div>
          <?php endif; ?>

          <?php if ($isAdmin): ?>
            <form class="form-grid" id="task-edit-<?= (int) $t['id'] ?>" method="post" action="<?= url('/projects/tasks/update') ?>" hidden>
              <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
              <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
              <div class="field field--wide">
                <label class="form__label">Task title</label>
                <input class="form__input" type="text" name="title" value="<?= e($t['title']) ?>" required>
              </div>
              <div class="field">
                <label class="form__label">Assign to</label>
                <select class="form__input" name="assigned_to">
                  <option value="0">Unassigned</option>
                  <?php foreach ($users as $u): ?>
                    <option value="<?= (int) $u['id'] ?>"<?= (int) $t['assigned_to'] === (int) $u['id'] ? ' selected' : '' ?>><?= e($u['name']) ?> (<?= e(ucfirst($u['role'])) ?>)</option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="field">
                <label class="form__label">Priority</label>
                <select class="form__input" name="priority">
                  <?php foreach ($taskPriorities as $p): ?>
                    <option value="<?= e($p) ?>"<?= $t['priority'] === $p ? ' selected' : '' ?>><?= e(ucfirst($p)) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="field">
                <label class="form__label">Due date</label>
                <input class="form__input" type="date" name="due_date" value="<?= e($t['due_date'] ?? '') ?>">
              </div>
              <div class="field field--wide">
                <label class="form__label">Description</label>
                <textarea class="form__input" name="description" rows="2"><?= e($t['description'] ?? '') ?></textarea>
              </div>
              <div class="field field--action">
                <button type="submit" class="btn btn--primary btn--sm">Save task</button>
              </div>
            </form>
          <?php endif; ?>

          <details class="task-card__notes">
            <summary><?= count($notes) ?> comment<?= count($notes) === 1 ? '' : 's' ?></summary>
            <?php if (!empty($notes)): ?>
              <div class="notes-timeline">
                <?php foreach ($notes as $n): ?>
                  <div class="note-item">
                    <div class="note-item__head">
                      <span class="note-item__author"><?= e($n['author_name'] ?? 'Deleted user') ?></span>
                      <span class="note-item__time"><?= e(date('M j, Y \a\t g:ia', strtotime($n['created_at']))) ?></span>
                    </div>
                    <div class="note-item__body"><?= nl2br(e($n['note'])) ?></div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
            <?php if ($mayAct): ?>
              <form class="note-form" method="post" action="<?= url('/projects/tasks/notes') ?>">
                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
                <textarea class="form__input" name="note" rows="1" placeholder="Add a comment…" required></textarea>
                <button type="submit" class="btn btn--sm btn--primary">Post</button>
              </form>
            <?php endif; ?>
          </details>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<script>
  // Each "+ Add" / "Edit" button reveals its section's form and flips to "Cancel".
  document.addEventListener('click', function (ev) {
    var btn = ev.target.closest('[data-toggle]');
    if (!btn) return;

    var form = document.getElementById(btn.dataset.toggle);
    var open = form.hidden;
    form.hidden = !open;
    btn.textContent = open ? btn.dataset.labelClose : btn.dataset.labelOpen;

    var view = document.getElementById('details-view');
    if (view && form.id === 'details-form') view.hidden = open;

    if (open) form.querySelector('input:not([type=hidden]), select, textarea').focus();
  });
</script>
