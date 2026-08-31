<?php
/**
 * @var string $userName @var array $hostingAlerts @var array $hostingUpcoming
 * @var array|null $hostingSummary @var int|null $clientsTotal @var array|null $clientFinancials
 * @var array|null $userRoleCounts @var array $recentLeads
 * @var int $totalLeads @var array $leadStatusCounts
 * @var int $projectTotal @var array $projectStatusCounts @var array $taskProgress
 */
$isAdmin = \App\Core\Auth::isAdmin();
$money   = static fn(float $n): string => '₹' . number_format($n, 0);

// ---------- Enquiries donut ----------
$enqColors = [
    'new' => '#6366f1', 'contacted' => '#d97706', 'quoted' => '#3b82f6',
    'won' => '#059669', 'lost' => '#dc2626', 'spam' => '#64748b',
];
$enqSegments = [];
foreach ($leadStatusCounts as $status => $count) {
    $enqSegments[] = ['value' => $count, 'color' => $enqColors[$status] ?? '#94a3b8', 'label' => ucfirst($status)];
}

// ---------- Projects donut ----------
$projColors = [
    'planning' => '#64748b', 'in_progress' => '#6366f1', 'on_hold' => '#d97706',
    'completed' => '#059669', 'cancelled' => '#dc2626',
];
$projSegments = [];
foreach ($projectStatusCounts as $status => $count) {
    $projSegments[] = ['value' => $count, 'color' => $projColors[$status] ?? '#94a3b8', 'label' => ucwords(str_replace('_', ' ', $status))];
}

// ---------- Task completion ring ----------
$taskDone      = $taskProgress['done'];
$taskRemaining = max($taskProgress['total'] - $taskDone, 0);
$taskPct       = $taskProgress['total'] > 0 ? (int) round($taskDone / $taskProgress['total'] * 100) : 0;

// ---------- Hosting donut (admin) ----------
$hostColors = ['active' => '#059669', 'renewing_soon' => '#f59e0b', 'due' => '#f97316', 'expired' => '#ef4444'];
$hostSegments = [];
if ($hostingSummary !== null) {
    foreach (['active', 'renewing_soon', 'due', 'expired'] as $status) {
        $hostSegments[] = [
            'value' => $hostingSummary[$status],
            'color' => $hostColors[$status],
            'label' => \App\Models\HostingService::STATUS_LABELS[$status],
        ];
    }
}

// ---------- Clients collection ring (admin) ----------
$invoiced    = $clientFinancials['invoiced'] ?? 0.0;
$outstanding = max($invoiced - ($clientFinancials['paid'] ?? 0.0), 0.0);
$collectPct  = $invoiced > 0 ? (int) round(($clientFinancials['paid'] / $invoiced) * 100) : 0;

// ---------- Team donut (admin) ----------
$teamColors = ['admin' => '#4f46e5', 'manager' => '#64748b'];
$teamSegments = [];
if ($userRoleCounts !== null) {
    foreach ($userRoleCounts as $role => $count) {
        $teamSegments[] = ['value' => $count, 'color' => $teamColors[$role] ?? '#94a3b8', 'label' => ucfirst($role)];
    }
}
?>
<header class="page-head">
  <div>
    <h1 class="page-head__title">Dashboard</h1>
    <p class="page-head__sub">Welcome back, <?= e($userName) ?>. Here's how every module looks right now.</p>
  </div>
</header>

<section class="overview-grid">
  <!-- Enquiries -->
  <div class="ov-card">
    <div class="ov-card__head">
      <h2 class="ov-card__title">Enquiries</h2>
      <a class="ov-card__link" href="<?= url('/enquiries') ?>">View &rarr;</a>
    </div>
    <?php if ($totalLeads > 0): ?>
      <div class="ov-card__body">
        <?= donut_svg($enqSegments, '<span class="donut__value">' . (int) $totalLeads . '</span><span class="donut__label">Total</span>') ?>
        <ul class="ov-legend">
          <?php foreach ($enqSegments as $seg): if ($seg['value'] <= 0) continue; ?>
            <li><span class="ov-dot" style="background:<?= e($seg['color']) ?>"></span><?= e($seg['label']) ?><b><?= (int) $seg['value'] ?></b></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php else: ?>
      <p class="ov-card__empty">No enquiries yet. They'll show up here as soon as someone uses your contact form.</p>
    <?php endif; ?>
  </div>

  <!-- Projects -->
  <div class="ov-card">
    <div class="ov-card__head">
      <h2 class="ov-card__title">Projects</h2>
      <a class="ov-card__link" href="<?= url('/projects') ?>">View &rarr;</a>
    </div>
    <?php if ($projectTotal > 0): ?>
      <div class="ov-card__body">
        <?= donut_svg($projSegments, '<span class="donut__value">' . (int) $projectTotal . '</span><span class="donut__label">Total</span>') ?>
        <ul class="ov-legend">
          <?php foreach ($projSegments as $seg): if ($seg['value'] <= 0) continue; ?>
            <li><span class="ov-dot" style="background:<?= e($seg['color']) ?>"></span><?= e($seg['label']) ?><b><?= (int) $seg['value'] ?></b></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php else: ?>
      <p class="ov-card__empty">No projects yet. Create one under Project Management to see it here.</p>
    <?php endif; ?>
  </div>

  <!-- Task completion -->
  <div class="ov-card">
    <div class="ov-card__head">
      <h2 class="ov-card__title">Task Progress</h2>
      <a class="ov-card__link" href="<?= url('/projects') ?>">View &rarr;</a>
    </div>
    <?php if ($taskProgress['total'] > 0): ?>
      <div class="ov-card__body">
        <?= donut_svg(
          [['value' => $taskDone, 'color' => '#059669'], ['value' => $taskRemaining, 'color' => '#e2e8f0']],
          '<span class="donut__value">' . $taskPct . '%</span><span class="donut__label">Done</span>'
        ) ?>
        <ul class="ov-legend">
          <li><span class="ov-dot" style="background:#059669"></span>Completed<b><?= (int) $taskDone ?></b></li>
          <li><span class="ov-dot" style="background:#e2e8f0"></span>Remaining<b><?= (int) $taskRemaining ?></b></li>
        </ul>
      </div>
    <?php else: ?>
      <p class="ov-card__empty">No tasks yet. Add tasks to a project to track progress here.</p>
    <?php endif; ?>
  </div>

  <?php if ($isAdmin): ?>
    <!-- Hosting -->
    <div class="ov-card">
      <div class="ov-card__head">
        <h2 class="ov-card__title">Hosting &amp; Domains</h2>
        <a class="ov-card__link" href="<?= url('/hosting') ?>">View &rarr;</a>
      </div>
      <?php if ($hostingSummary['total'] > 0): ?>
        <div class="ov-card__body">
          <?= donut_svg($hostSegments, '<span class="donut__value">' . (int) $hostingSummary['total'] . '</span><span class="donut__label">Total</span>') ?>
          <ul class="ov-legend">
            <?php foreach ($hostSegments as $seg): if ($seg['value'] <= 0) continue; ?>
              <li><span class="ov-dot" style="background:<?= e($seg['color']) ?>"></span><?= e($seg['label']) ?><b><?= (int) $seg['value'] ?></b></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php else: ?>
        <p class="ov-card__empty">No hosting or domain records yet.</p>
      <?php endif; ?>
    </div>

    <!-- Clients -->
    <div class="ov-card">
      <div class="ov-card__head">
        <h2 class="ov-card__title">Clients</h2>
        <a class="ov-card__link" href="<?= url('/clients') ?>"><?= (int) $clientsTotal ?> total &rarr;</a>
      </div>
      <?php if ($invoiced > 0): ?>
        <div class="ov-card__body">
          <?= donut_svg(
            [['value' => $clientFinancials['paid'], 'color' => '#059669'], ['value' => $outstanding, 'color' => '#e2e8f0']],
            '<span class="donut__value">' . $collectPct . '%</span><span class="donut__label">Collected</span>'
          ) ?>
          <ul class="ov-legend">
            <li><span class="ov-dot" style="background:#059669"></span>Received<b><?= e($money($clientFinancials['paid'])) ?></b></li>
            <li><span class="ov-dot" style="background:#e2e8f0"></span>Outstanding<b><?= e($money($outstanding)) ?></b></li>
          </ul>
        </div>
      <?php else: ?>
        <p class="ov-card__empty"><?= (int) $clientsTotal > 0 ? 'No invoices raised yet.' : 'No clients yet. Mark an enquiry as a client, or add one directly.' ?></p>
      <?php endif; ?>
    </div>

    <!-- Team -->
    <div class="ov-card">
      <div class="ov-card__head">
        <h2 class="ov-card__title">Team</h2>
        <a class="ov-card__link" href="<?= url('/users') ?>">View &rarr;</a>
      </div>
      <?php $teamTotal = array_sum($userRoleCounts); ?>
      <?php if ($teamTotal > 0): ?>
        <div class="ov-card__body">
          <?= donut_svg($teamSegments, '<span class="donut__value">' . (int) $teamTotal . '</span><span class="donut__label">People</span>') ?>
          <ul class="ov-legend">
            <?php foreach ($teamSegments as $seg): if ($seg['value'] <= 0) continue; ?>
              <li><span class="ov-dot" style="background:<?= e($seg['color']) ?>"></span><?= e($seg['label']) ?><b><?= (int) $seg['value'] ?></b></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php else: ?>
        <p class="ov-card__empty">No team members yet.</p>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</section>

<?php if ($hostingAlerts !== null && $hostingAlerts['attention'] > 0): ?>
  <!-- Hosting renewal alert — the headline numbers, so a lapsing renewal is
       visible without opening the Hosting module. -->
  <section class="panel panel--hosting">
    <div class="panel__head">
      <h2 class="panel__title">Hosting renewals needing attention</h2>
      <a class="panel__count" href="<?= url('/hosting') ?>">View hosting &rarr;</a>
    </div>
    <div class="host-alert">
      <?php if ($hostingAlerts['expired'] > 0): ?>
        <a class="host-alert__item host-alert__item--expired" href="<?= url('/hosting') ?>?status=expired">
          <span class="host-alert__count">🔴 <?= (int) $hostingAlerts['expired'] ?></span> Expired
        </a>
      <?php endif; ?>
      <?php if ($hostingAlerts['urgent'] > 0): ?>
        <a class="host-alert__item host-alert__item--due" href="<?= url('/hosting') ?>?status=due">
          <span class="host-alert__count">🟠 <?= (int) $hostingAlerts['urgent'] ?></span> Due within 7 days
        </a>
      <?php endif; ?>
      <?php if ($hostingAlerts['soon'] > 0): ?>
        <a class="host-alert__item host-alert__item--soon" href="<?= url('/hosting') ?>?status=renewing_soon">
          <span class="host-alert__count">🟡 <?= (int) $hostingAlerts['soon'] ?></span> Due within 30 days
        </a>
      <?php endif; ?>
    </div>

    <?php if (!empty($hostingUpcoming)): ?>
      <div class="host-alert__list">
        <?php foreach ($hostingUpcoming as $h): ?>
          <?php $d = (int) $h['days_remaining']; ?>
          <a class="host-alert__row" href="<?= url('/hosting/view') ?>?id=<?= (int) $h['id'] ?>">
            <span><strong><?= e($h['client_name']) ?></strong> · <?= e($h['domain'] ?: ($h['website_name'] ?: '—')) ?></span>
            <span class="days-pill days-pill--<?= e($h['status']) ?>">
              <?= $d < 0 ? abs($d) . ' days ago' : ($d === 0 ? 'Today' : $d . ' days left') ?>
            </span>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
<?php endif; ?>

<section class="panel">
  <div class="panel__head">
    <h2 class="panel__title">Recent enquiries</h2>
    <a class="panel__count" href="<?= url('/enquiries') ?>">View all &rarr;</a>
  </div>

  <?php if (empty($recentLeads)): ?>
    <p class="empty">
      No enquiries yet. When someone submits the contact form on your website,
      their message will appear here and under <a href="<?= url('/enquiries') ?>">Enquiries</a>.
    </p>
  <?php else: ?>
    <table class="table">
      <thead>
        <tr><th>Name</th><th>Email</th><th>Subject</th><th>Status</th><th>When</th></tr>
      </thead>
      <tbody>
        <?php foreach ($recentLeads as $lead): ?>
          <tr class="<?= (int) $lead['is_client'] === 1 ? 'row--client' : '' ?>">
            <td>
              <a class="enq-from__name" href="<?= url('/enquiries/view') ?>?id=<?= (int) $lead['id'] ?>">
                <?php if ((int) $lead['is_read'] === 0): ?><span class="unread-dot" title="New — not yet opened"></span><?php endif; ?>
                <?php if ((int) $lead['is_important'] === 1): ?><span title="Important" style="color:#f59e0b">★</span> <?php endif; ?>
                <?= e($lead['name']) ?>
                <?php if ((int) $lead['is_client'] === 1): ?><span class="client-tag">Client</span><?php endif; ?>
              </a>
            </td>
            <td><?= e($lead['email']) ?></td>
            <td><?= e($lead['subject']) ?></td>
            <td><span class="badge badge--<?= e($lead['status']) ?>"><?= e($lead['status']) ?></span></td>
            <td class="muted"><?= e(date('M j, Y', strtotime($lead['created_at']))) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</section>
