<?php
/**
 * @var string $userName @var int $totalLeads @var int $newLeads @var int $wonLeads
 * @var array $recentLeads @var array|null $hostingAlerts @var array $hostingUpcoming
 */
?>
<header class="page-head">
  <div>
    <h1 class="page-head__title">Dashboard</h1>
    <p class="page-head__sub">Welcome back, <?= e($userName) ?>.</p>
  </div>
</header>

<section class="stat-grid">
  <div class="stat">
    <span class="stat__label">Total leads</span>
    <span class="stat__value"><?= (int) $totalLeads ?></span>
  </div>
  <div class="stat">
    <span class="stat__label">New / unread</span>
    <span class="stat__value"><?= (int) $newLeads ?></span>
  </div>
  <div class="stat">
    <span class="stat__label">Won</span>
    <span class="stat__value"><?= (int) $wonLeads ?></span>
  </div>
</section>

<?php if ($hostingAlerts !== null && $hostingAlerts['attention'] > 0): ?>
  <!-- Hosting renewal alert — the headline numbers, so a lapsing renewal is
       visible without opening the Hosting module. -->
  <section class="panel panel--hosting">
    <div class="panel__head">
      <h2 class="panel__title">Hosting renewals</h2>
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
