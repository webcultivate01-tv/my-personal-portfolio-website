<?php
/**
 * @var string $csrf @var string $filter @var array $enquiries
 * @var int $total @var int $newCount @var int $unreadCount @var int $importantCount @var int $clientCount
 * @var array $statuses
 */
$isAdmin = \App\Core\Auth::isAdmin();

/** Build a filter-tab URL. */
$tab = static fn(string $key): string => url('/enquiries') . ($key === 'all' ? '' : '?filter=' . $key);

/** Filter tabs: key => label. */
$tabs = [
    'all'       => 'All',
    'important' => 'Important',
    'client'    => 'Clients',
    'new'       => 'New',
    'contacted' => 'Contacted',
    'quoted'    => 'Quoted',
    'won'       => 'Won',
    'lost'      => 'Lost',
];
?>
<header class="page-head">
  <div>
    <h1 class="page-head__title">Enquiries</h1>
    <p class="page-head__sub">Messages sent through your website contact form. Star the ones that matter and flag real clients in green.</p>
  </div>
</header>

<section class="stat-grid">
  <div class="stat"><span class="stat__label">Total</span><span class="stat__value"><?= (int) $total ?></span></div>
  <div class="stat"><span class="stat__label">New (status)</span><span class="stat__value"><?= (int) $newCount ?></span></div>
  <div class="stat<?= $unreadCount > 0 ? ' stat--alert' : '' ?>">
    <span class="stat__label"><?php if ($unreadCount > 0): ?><span class="unread-dot"></span><?php endif; ?>Unread</span>
    <span class="stat__value"><?= (int) $unreadCount ?></span>
  </div>
  <div class="stat"><span class="stat__label">Important</span><span class="stat__value"><?= (int) $importantCount ?></span></div>
  <div class="stat"><span class="stat__label">Clients</span><span class="stat__value"><?= (int) $clientCount ?></span></div>
</section>

<section class="panel">
  <div class="panel__head">
    <div class="filter-tabs">
      <?php foreach ($tabs as $key => $label): ?>
        <a class="filter-tab<?= $filter === $key ? ' is-active' : '' ?>" href="<?= e($tab($key)) ?>"><?= e($label) ?></a>
      <?php endforeach; ?>
    </div>
    <span class="panel__count"><?= count($enquiries) ?> shown</span>
  </div>

  <?php if (empty($enquiries)): ?>
    <p class="empty">
      No enquiries here yet. When someone submits the contact form on your site,
      their message will appear in this list.
    </p>
  <?php else: ?>
    <table class="table table--enquiries">
      <thead>
        <tr>
          <th class="ta-center">★</th>
          <th>From</th>
          <th>Subject</th>
          <th>Status</th>
          <th>When</th>
          <th class="ta-right">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($enquiries as $enq): ?>
          <?php
            $isClient    = (int) $enq['is_client'] === 1;
            $isImportant = (int) $enq['is_important'] === 1;
            $isUnread    = (int) $enq['is_read'] === 0;
            $rowClass    = trim(($isClient ? 'row--client ' : '') . ($isImportant ? 'row--important ' : '') . ($isUnread ? 'row--unread' : ''));
            $viewUrl     = url('/enquiries/view') . '?id=' . (int) $enq['id'];
          ?>
          <tr class="<?= e($rowClass) ?>">
            <!-- Important star (both admin & manager) -->
            <td class="ta-center">
              <form method="post" action="<?= url('/enquiries/important') ?>" class="inline-form">
                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                <input type="hidden" name="id" value="<?= (int) $enq['id'] ?>">
                <button type="submit" class="star-btn<?= $isImportant ? ' is-on' : '' ?>"
                        title="<?= $isImportant ? 'Remove from important' : 'Mark as important' ?>"
                        aria-label="Toggle important">
                  <svg viewBox="0 0 24 24" width="18" height="18"><path d="M12 2.5l2.9 5.88 6.49.94-4.7 4.58 1.11 6.46L12 17.9l-5.8 3.05 1.1-6.46-4.69-4.58 6.49-.94L12 2.5z"/></svg>
                </button>
              </form>
            </td>

            <td>
              <a class="enq-from" href="<?= e($viewUrl) ?>">
                <span class="enq-from__name">
                  <?php if ($isUnread): ?><span class="unread-dot" title="New — not yet opened"></span><?php endif; ?>
                  <?= e($enq['name']) ?>
                  <?php if ($isClient): ?><span class="client-tag">Client</span><?php endif; ?>
                </span>
                <span class="enq-from__email"><?= e($enq['email']) ?></span>
                <?php if (!empty($enq['phone'])): ?><span class="enq-from__email"><?= e($enq['phone']) ?></span><?php endif; ?>
              </a>
            </td>

            <td>
              <a class="enq-subject" href="<?= e($viewUrl) ?>">
                <?= e($enq['subject'] !== '' && $enq['subject'] !== null ? $enq['subject'] : '(no subject)') ?>
              </a>
            </td>

            <!-- Inline status change -->
            <td>
              <form method="post" action="<?= url('/enquiries/status') ?>" class="inline-form">
                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                <input type="hidden" name="id" value="<?= (int) $enq['id'] ?>">
                <select name="status" class="status-select status-select--<?= e($enq['status']) ?>" onchange="this.form.submit()">
                  <?php foreach ($statuses as $s): ?>
                    <option value="<?= e($s) ?>"<?= $enq['status'] === $s ? ' selected' : '' ?>><?= e(ucfirst($s)) ?></option>
                  <?php endforeach; ?>
                </select>
              </form>
            </td>

            <td class="muted"><?= e(date('M j, Y', strtotime($enq['created_at']))) ?></td>

            <td class="ta-right">
              <div class="row-actions">
                <!-- Client (green) toggle -->
                <form method="post" action="<?= url('/enquiries/client') ?>" class="inline-form">
                  <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                  <input type="hidden" name="id" value="<?= (int) $enq['id'] ?>">
                  <button type="submit" class="btn btn--sm <?= $isClient ? 'btn--client-on' : 'btn--client' ?>">
                    <?= $isClient ? 'Client ✓' : 'Mark client' ?>
                  </button>
                </form>

                <a class="btn btn--sm btn--ghost" href="<?= e($viewUrl) ?>">View</a>

                <?php if ($isAdmin): ?>
                  <form method="post" action="<?= url('/enquiries/delete') ?>" class="inline-form"
                        onsubmit="return confirm('Delete this enquiry from <?= e($enq['name']) ?>? This cannot be undone.')">
                    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                    <input type="hidden" name="id" value="<?= (int) $enq['id'] ?>">
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
