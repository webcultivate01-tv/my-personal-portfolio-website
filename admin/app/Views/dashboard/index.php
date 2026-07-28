<?php
/** @var string $userName @var int $totalLeads @var int $newLeads @var int $wonLeads @var array $recentLeads */
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
