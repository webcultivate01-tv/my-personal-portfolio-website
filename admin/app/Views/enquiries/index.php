<?php
/**
 * @var string $csrf @var string $filter @var array $enquiries
 * @var int $total @var int $newCount @var int $unreadCount @var int $importantCount @var int $clientCount
 * @var array $statuses @var string $dateFrom @var string $dateTo @var string $subject @var array $subjects
 */
$isAdmin = \App\Core\Auth::isAdmin();

/** Build a filter-tab URL, preserving the current date range and subject. */
$tab = static function (string $key) use ($dateFrom, $dateTo, $subject): string {
    $params = [];
    if ($key !== 'all') {
        $params['filter'] = $key;
    }
    if ($dateFrom !== '') {
        $params['date_from'] = $dateFrom;
    }
    if ($dateTo !== '') {
        $params['date_to'] = $dateTo;
    }
    if ($subject !== '') {
        $params['subject'] = $subject;
    }
    return url('/enquiries') . ($params ? '?' . http_build_query($params) : '');
};

/** Export PDF URL, honouring the current filter + date range + subject. */
$exportUrl = url('/enquiries/export') . '?' . http_build_query(array_filter([
    'filter'    => $filter !== 'all' ? $filter : null,
    'date_from' => $dateFrom !== '' ? $dateFrom : null,
    'date_to'   => $dateTo !== '' ? $dateTo : null,
    'subject'   => $subject !== '' ? $subject : null,
]));

/** Current status tab with the date range and subject stripped, for "Clear dates". */
$clearDatesUrl = url('/enquiries') . ($filter !== 'all' ? '?filter=' . $filter : '');

/** Filter tabs: key => label. */
$tabs = [
    'all'       => 'All',
    'important' => 'Important',
    'client'    => 'Clients',
    'unread'    => 'Unread',
    'new'       => 'New',
    'contacted' => 'Contacted',
    'quoted'    => 'Quoted',
    'won'       => 'Won',
    'lost'      => 'Lost',
    'spam'      => 'Spam',
];
?>
<header class="page-head">
  <div>
    <h1 class="page-head__title">Enquiries</h1>
    <p class="page-head__sub">Messages sent through your website contact form. Star the ones that matter and flag real clients in green.</p>
  </div>
  <?php if ($isAdmin): ?>
    <a class="btn btn--ghost btn--sm" href="<?= e($exportUrl) ?>" target="_blank" rel="noopener">⬇ Export PDF</a>
  <?php endif; ?>
</header>

<section class="stat-grid">
  <a class="stat<?= $filter === 'all' ? ' is-active' : '' ?>" href="<?= e($tab('all')) ?>">
    <span class="stat__label">Total</span><span class="stat__value"><?= (int) $total ?></span>
  </a>
  <a class="stat<?= $filter === 'new' ? ' is-active' : '' ?>" href="<?= e($tab('new')) ?>">
    <span class="stat__label">New (status)</span><span class="stat__value"><?= (int) $newCount ?></span>
  </a>
  <a class="stat<?= $unreadCount > 0 ? ' stat--alert' : '' ?><?= $filter === 'unread' ? ' is-active' : '' ?>" href="<?= e($tab('unread')) ?>">
    <span class="stat__label"><?php if ($unreadCount > 0): ?><span class="unread-dot"></span><?php endif; ?>Unread</span>
    <span class="stat__value"><?= (int) $unreadCount ?></span>
  </a>
  <a class="stat<?= $filter === 'important' ? ' is-active' : '' ?>" href="<?= e($tab('important')) ?>">
    <span class="stat__label">Important</span><span class="stat__value"><?= (int) $importantCount ?></span>
  </a>
  <a class="stat<?= $filter === 'client' ? ' is-active' : '' ?>" href="<?= e($tab('client')) ?>">
    <span class="stat__label">Clients</span><span class="stat__value"><?= (int) $clientCount ?></span>
  </a>
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

  <form class="filter-bar" method="get" action="<?= url('/enquiries') ?>">
    <input type="hidden" name="filter" value="<?= e($filter) ?>">
    <label class="filter-bar__date">From
      <input class="form__input" type="date" name="date_from" value="<?= e($dateFrom) ?>">
    </label>
    <label class="filter-bar__date">to
      <input class="form__input" type="date" name="date_to" value="<?= e($dateTo) ?>">
    </label>
    <label class="filter-bar__date">Subject
      <select class="form__input" name="subject">
        <option value="">All subjects</option>
        <?php foreach ($subjects as $subj): ?>
          <option value="<?= e($subj) ?>"<?= $subject === $subj ? ' selected' : '' ?>><?= e($subj) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <button type="submit" class="btn btn--sm btn--primary">Apply</button>
    <?php if ($dateFrom !== '' || $dateTo !== '' || $subject !== ''): ?>
      <a class="btn btn--sm btn--ghost" href="<?= e($clearDatesUrl) ?>">Clear filters</a>
    <?php endif; ?>
  </form>

  <?php if (empty($enquiries)): ?>
    <p class="empty">
      <?php if ($filter !== 'all' || $dateFrom !== '' || $dateTo !== '' || $subject !== ''): ?>
        Nothing matches that filter<?= ($dateFrom !== '' || $dateTo !== '' || $subject !== '') ? ' and criteria' : '' ?>.
        <a href="<?= url('/enquiries') ?>">Clear it</a> to see every enquiry.
      <?php else: ?>
        No enquiries here yet. When someone submits the contact form on your site,
        their message will appear in this list.
      <?php endif; ?>
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
