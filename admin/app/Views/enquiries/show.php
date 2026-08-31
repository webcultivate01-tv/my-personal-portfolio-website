<?php
/** @var string $csrf @var array $enquiry @var array $statuses @var array $notes */
$isAdmin     = \App\Core\Auth::isAdmin();
$isClient    = (int) $enquiry['is_client'] === 1;
$isImportant = (int) $enquiry['is_important'] === 1;
$subject     = ($enquiry['subject'] !== '' && $enquiry['subject'] !== null) ? $enquiry['subject'] : '(no subject)';
?>
<header class="page-head">
  <div>
    <h1 class="page-head__title">
      <?= e($enquiry['name']) ?>
      <?php if ($isClient): ?><span class="client-tag client-tag--lg">Client</span><?php endif; ?>
    </h1>
    <p class="page-head__sub">
      <a href="mailto:<?= e($enquiry['email']) ?>"><?= e($enquiry['email']) ?></a>
      <?php if (!empty($enquiry['phone'])): ?>
        &nbsp;·&nbsp; <a href="tel:<?= e($enquiry['phone']) ?>"><?= e($enquiry['phone']) ?></a>
      <?php endif; ?>
      &nbsp;·&nbsp; <?= e(date('M j, Y \a\t g:ia', strtotime($enquiry['created_at']))) ?>
    </p>
  </div>
  <a class="btn btn--ghost btn--sm" href="<?= url('/enquiries') ?>">&larr; Back to enquiries</a>
</header>

<section class="panel<?= $isClient ? ' panel--client' : '' ?>">
  <div class="panel__head">
    <h2 class="panel__title"><?= e($subject) ?></h2>
    <span class="badge badge--<?= e($enquiry['status']) ?>"><?= e(ucfirst($enquiry['status'])) ?></span>
  </div>

  <div class="enq-body"><?= nl2br(e($enquiry['message'])) ?></div>

  <div class="enq-toolbar">
    <!-- Important -->
    <form method="post" action="<?= url('/enquiries/important') ?>" class="inline-form">
      <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
      <input type="hidden" name="id" value="<?= (int) $enquiry['id'] ?>">
      <button type="submit" class="btn btn--sm <?= $isImportant ? 'btn--star-on' : 'btn--ghost' ?>">
        <?= $isImportant ? '★ Important' : '☆ Mark important' ?>
      </button>
    </form>

    <!-- Client (green) -->
    <form method="post" action="<?= url('/enquiries/client') ?>" class="inline-form">
      <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
      <input type="hidden" name="id" value="<?= (int) $enquiry['id'] ?>">
      <button type="submit" class="btn btn--sm <?= $isClient ? 'btn--client-on' : 'btn--client' ?>">
        <?= $isClient ? 'Client ✓' : 'Mark as client' ?>
      </button>
    </form>

    <!-- Status -->
    <form method="post" action="<?= url('/enquiries/status') ?>" class="inline-form status-inline">
      <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
      <input type="hidden" name="id" value="<?= (int) $enquiry['id'] ?>">
      <select name="status" class="status-select status-select--<?= e($enquiry['status']) ?>" onchange="this.form.submit()">
        <?php foreach ($statuses as $s): ?>
          <option value="<?= e($s) ?>"<?= $enquiry['status'] === $s ? ' selected' : '' ?>><?= e(ucfirst($s)) ?></option>
        <?php endforeach; ?>
      </select>
    </form>

    <a class="btn btn--sm btn--primary" href="mailto:<?= e($enquiry['email']) ?>?subject=Re: <?= e(rawurlencode($subject)) ?>">Reply by email</a>

    <?php if ($isAdmin): ?>
      <form method="post" action="<?= url('/enquiries/delete') ?>" class="inline-form"
            onsubmit="return confirm('Delete this enquiry? This cannot be undone.')">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
        <input type="hidden" name="id" value="<?= (int) $enquiry['id'] ?>">
        <button type="submit" class="btn btn--sm btn--danger">Delete</button>
      </form>
    <?php endif; ?>
  </div>
</section>

<section class="panel panel--pad">
  <div class="panel__head panel__head--plain">
    <h2 class="panel__title">Internal notes</h2>
    <span class="panel__count"><?= count($notes) ?> note<?= count($notes) === 1 ? '' : 's' ?></span>
  </div>

  <form method="post" action="<?= url('/enquiries/notes') ?>" style="padding:0 22px">
    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
    <input type="hidden" name="id" value="<?= (int) $enquiry['id'] ?>">
    <textarea name="note" class="form__input" rows="3" placeholder="Add a note for your team…" required></textarea>
    <div style="margin-top:12px"><button type="submit" class="btn btn--primary btn--sm">Add note</button></div>
  </form>

  <?php if (empty($notes)): ?>
    <p class="empty">No notes yet. Notes added here are only visible to your team and build up as a timeline.</p>
  <?php else: ?>
    <div class="notes-timeline">
      <?php foreach ($notes as $n): ?>
        <div class="note-item">
          <div class="note-item__head">
            <span class="note-item__author"><?= e($n['author'] ?? 'Deleted user') ?></span>
            <span class="note-item__time"><?= e(date('M j, Y \a\t g:ia', strtotime($n['created_at']))) ?></span>
          </div>
          <div class="note-item__body"><?= nl2br(e($n['note'])) ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>
