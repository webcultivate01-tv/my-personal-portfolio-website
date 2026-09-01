<?php
/**
 * One hosting plan / domain in full.
 *
 * Renewal state sits at the top because it is the only thing that is ever
 * urgent; the client, website, hosting and history details follow.
 *
 * @var string $csrf @var array $record @var array $renewals @var float $renewedSum
 * @var array $clients @var array $projects @var array $cycles @var array $payStatuses @var string $today
 * @var bool $canManage
 */
$canManage = $canManage ?? false;
$money = static fn($n): string => $n === null || $n === '' ? '—' : '₹' . number_format((float) $n, 2);

$daysText = static function (int $d): string {
    if ($d === 0) return 'Expires today';
    if ($d > 0)   return $d . ' day' . ($d === 1 ? '' : 's') . ' left';
    $d = abs($d);
    return 'Expired ' . $d . ' day' . ($d === 1 ? '' : 's') . ' ago';
};

$date       = static fn(?string $d): string => $d ? date('j M Y', strtotime($d)) : '—';
$cycleLabel = static fn(string $c): string => ucwords(str_replace('_', ' ', $c));

$days     = (int) $record['days_remaining'];
$isDomain = $record['service_type'] === 'domain';
$custom   = $record['custom_cycle_months'] !== null ? (int) $record['custom_cycle_months'] : '';
$heading  = $record['website_name'] ?: ($record['domain'] ?: $record['client_name']);
?>
<header class="page-head">
  <div>
    <h1 class="page-head__title">
      <?= e($heading) ?>
      <span class="type-tag type-tag--<?= e($record['service_type']) ?>"><?= $isDomain ? 'Domain' : 'Hosting' ?></span>
    </h1>
    <p class="page-head__sub">
      <?= e($record['client_name']) ?>
      <?php if (!empty($record['company'])): ?>&nbsp;·&nbsp;<?= e($record['company']) ?><?php endif; ?>
      <?php if (!empty($record['provider'])): ?>&nbsp;·&nbsp;<?= e($record['provider']) ?><?php endif; ?>
    </p>
  </div>
  <div class="row-actions">
    <?php if ($canManage): ?>
      <button type="button" class="btn btn--primary" data-toggle="renew-form"
              data-label-open="Mark as Renewed" data-label-close="Cancel">Mark as Renewed</button>
    <?php else: ?>
      <span class="lock-hint">🔒 View only</span>
    <?php endif; ?>
    <a class="btn btn--ghost btn--sm" href="<?= url('/hosting') ?>">&larr; Back to hosting</a>
  </div>
</header>

<!-- ---------- Renewal state ---------- -->
<section class="stat-grid">
  <div class="stat">
    <span class="stat__label">Renewal date</span>
    <span class="stat__value stat__value--money"><?= e($date($record['renewal_date'])) ?></span>
  </div>
  <div class="stat<?= in_array($record['status'], ['expired', 'due'], true) ? ' stat--alert' : ($record['status'] === 'renewing_soon' ? ' stat--warn' : '') ?>">
    <span class="stat__label">Days remaining</span>
    <span class="stat__value"><?= $days ?></span>
  </div>
  <div class="stat">
    <span class="stat__label">Status</span>
    <span class="stat__value stat__value--sm">
      <span class="badge badge--host-<?= e($record['status']) ?>"><?= e($record['status_label']) ?></span>
      <?php if ($record['renewed_this_month']): ?>
        <span class="badge badge--host-renewed">Renewed</span>
      <?php endif; ?>
    </span>
  </div>
  <div class="stat">
    <span class="stat__label">Renewal cost</span>
    <span class="stat__value stat__value--money"><?= e($money($record['renewal_cost'] ?? $record['cost'])) ?></span>
  </div>
  <div class="stat">
    <span class="stat__label">Renewals so far</span>
    <span class="stat__value"><?= count($renewals) ?></span>
  </div>
</section>

<?php if ($record['reminder_label'] !== null): ?>
  <div class="renew-alert<?= in_array($record['status'], ['expired', 'due'], true) ? ' renew-alert--danger' : '' ?>">
    <span class="renew-alert__icon"><?= $record['status'] === 'expired' ? '🔴' : '⚠️' ?></span>
    <span>
      <strong><?= e($record['reminder_label']) ?></strong> —
      <?= e($daysText($days)) ?>. Renewal date <?= e($date($record['renewal_date'])) ?>.
    </span>
  </div>
<?php endif; ?>

<!-- ---------- Mark as renewed ---------- -->
<?php if ($canManage): ?>
<section class="panel panel--pad" id="renew-form" hidden>
  <div class="panel__head panel__head--plain">
    <h2 class="panel__title">Record a renewal</h2>
  </div>
  <p class="form-intro">
    Saving this moves the expiry date forward, files the renewal in the history below,
    and takes this record off the urgent list.
  </p>
  <form class="form-grid js-renew-form" method="post" action="<?= url('/hosting/renew') ?>">
    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
    <input type="hidden" name="id" value="<?= (int) $record['id'] ?>">
    <!-- Drives the new-expiry calculation in the browser; the server recalculates it anyway. -->
    <input type="hidden" class="js-cycle" value="<?= e($record['billing_cycle']) ?>">
    <input type="hidden" name="custom_cycle_months" value="<?= e((string) $custom) ?>">
    <input type="hidden" class="js-expiry-from" value="<?= e((string) $record['renewal_date']) ?>">

    <div class="field">
      <label class="form__label" for="r_renewal_date">Renewal date</label>
      <input class="form__input" type="date" id="r_renewal_date" name="renewal_date" value="<?= e($today) ?>" required>
      <span class="field__hint">The day you actually renewed it.</span>
    </div>
    <div class="field">
      <label class="form__label" for="r_new_expiry">New expiry date</label>
      <input class="form__input js-new-expiry" type="date" id="r_new_expiry" name="new_expiry"
             value="<?= e((string) ($record['next_renewal'] ?? '')) ?>"
             data-auto="<?= e((string) ($record['next_renewal'] ?? '')) ?>">
      <span class="field__hint">Current expiry + <?= e($cycleLabel($record['billing_cycle'])) ?>. Edit if the provider gave a different date.</span>
    </div>
    <div class="field">
      <label class="form__label" for="r_amount">Renewal amount (₹)</label>
      <input class="form__input" type="number" id="r_amount" name="amount" step="0.01" min="0"
             value="<?= $record['renewal_cost'] !== null ? e(number_format((float) $record['renewal_cost'], 2, '.', '')) : '' ?>">
    </div>
    <div class="field">
      <label class="form__label" for="r_payment_status">Payment status</label>
      <select class="form__input" id="r_payment_status" name="payment_status">
        <?php foreach ($payStatuses as $s): ?>
          <option value="<?= e($s) ?>"><?= e(ucfirst($s)) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label class="form__label" for="r_payment_reference">Payment reference</label>
      <input class="form__input" type="text" id="r_payment_reference" name="payment_reference"
             placeholder="UPI ref / invoice no / txn id">
    </div>
    <div class="field field--wide">
      <label class="form__label" for="r_notes">Notes</label>
      <textarea class="form__input" id="r_notes" name="notes" rows="2" placeholder="Anything worth remembering about this renewal"></textarea>
    </div>
    <div class="field field--action">
      <button type="submit" class="btn btn--primary">Save renewal</button>
      <button type="button" class="btn btn--ghost" data-toggle="renew-form"
              data-label-open="Mark as Renewed" data-label-close="Cancel">Cancel</button>
    </div>
  </form>
</section>
<?php endif; ?>

<!-- ---------- Details (read-only + edit form) ---------- -->
<section class="panel panel--pad">
  <div class="panel__head panel__head--plain">
    <h2 class="panel__title">Record details</h2>
    <?php if ($canManage): ?>
      <button type="button" class="btn btn--sm btn--ghost" data-toggle="details-form"
              data-label-open="Edit details" data-label-close="Cancel">Edit details</button>
    <?php endif; ?>
  </div>

  <div id="details-form-view">
    <p class="form-grid__section" style="padding:0 22px">Client information</p>
    <div class="stat-grid stat-grid--profile">
      <div class="stat"><span class="stat__label">Client</span><span class="stat__value stat__value--sm">
        <?php if (!empty($record['client_id'])): ?>
          <a href="<?= url('/clients/view') ?>?id=<?= (int) $record['client_id'] ?>"><?= e($record['client_name']) ?></a>
        <?php else: ?><?= e($record['client_name']) ?><?php endif; ?>
      </span></div>
      <div class="stat"><span class="stat__label">Company</span><span class="stat__value stat__value--sm"><?= e($record['company'] ?: ($record['client_company'] ?? '') ?: '—') ?></span></div>
      <div class="stat"><span class="stat__label">Email</span><span class="stat__value stat__value--sm"><?= e($record['client_email'] ?: '—') ?></span></div>
      <div class="stat"><span class="stat__label">Phone</span><span class="stat__value stat__value--sm"><?= e($record['client_phone'] ?: '—') ?></span></div>
    </div>

    <p class="form-grid__section" style="padding:0 22px">Website information</p>
    <div class="stat-grid stat-grid--profile">
      <div class="stat"><span class="stat__label">Website</span><span class="stat__value stat__value--sm"><?= e($record['website_name'] ?: '—') ?></span></div>
      <div class="stat"><span class="stat__label">Domain</span><span class="stat__value stat__value--sm"><?= e($record['domain'] ?: '—') ?></span></div>
      <div class="stat"><span class="stat__label">URL</span><span class="stat__value stat__value--sm">
        <?php if (!empty($record['website_url'])): ?>
          <a href="<?= e($record['website_url']) ?>" target="_blank" rel="noopener noreferrer"><?= e($record['website_url']) ?></a>
        <?php else: ?>—<?php endif; ?>
      </span></div>
      <div class="stat"><span class="stat__label">Project</span><span class="stat__value stat__value--sm">
        <?php if (!empty($record['project_id'])): ?>
          <a href="<?= url('/projects/view') ?>?id=<?= (int) $record['project_id'] ?>"><?= e($record['project_name']) ?></a>
        <?php else: ?>—<?php endif; ?>
      </span></div>
    </div>

    <p class="form-grid__section" style="padding:0 22px">Hosting information</p>
    <div class="stat-grid stat-grid--profile">
      <div class="stat"><span class="stat__label">Provider</span><span class="stat__value stat__value--sm"><?= e($record['provider'] ?: '—') ?></span></div>
      <div class="stat"><span class="stat__label">Plan</span><span class="stat__value stat__value--sm"><?= e($record['plan'] ?: '—') ?></span></div>
      <div class="stat"><span class="stat__label">Account / service ID</span><span class="stat__value stat__value--sm"><?= e($record['account_ref'] ?: '—') ?></span></div>
      <div class="stat"><span class="stat__label">Billing cycle</span><span class="stat__value stat__value--sm">
        <?= e($cycleLabel($record['billing_cycle'])) ?><?= $custom !== '' ? ' (' . (int) $custom . ' months)' : '' ?>
      </span></div>
      <div class="stat"><span class="stat__label">Purchase date</span><span class="stat__value stat__value--sm"><?= e($date($record['purchase_date'])) ?></span></div>
      <div class="stat"><span class="stat__label">Renewal date</span><span class="stat__value stat__value--sm"><?= e($date($record['renewal_date'])) ?></span></div>
      <div class="stat"><span class="stat__label">Hosting cost</span><span class="stat__value stat__value--sm"><?= e($money($record['cost'])) ?></span></div>
      <div class="stat"><span class="stat__label">Renewal cost</span><span class="stat__value stat__value--sm"><?= e($money($record['renewal_cost'])) ?></span></div>
      <div class="stat"><span class="stat__label">Login URL</span><span class="stat__value stat__value--sm">
        <?php if (!empty($record['login_url'])): ?>
          <a href="<?= e($record['login_url']) ?>" target="_blank" rel="noopener noreferrer">Open control panel</a>
        <?php else: ?>—<?php endif; ?>
      </span></div>
      <div class="stat"><span class="stat__label">Login kept in</span><span class="stat__value stat__value--sm"><?= e($record['credential_ref'] ?: '—') ?></span></div>
      <div class="stat"><span class="stat__label">Notes</span><span class="stat__value stat__value--sm"><?= e($record['notes'] ?: '—') ?></span></div>
      <div class="stat"><span class="stat__label">Internal notes</span><span class="stat__value stat__value--sm"><?= e($record['internal_notes'] ?: '—') ?></span></div>
    </div>
  </div>

  <?php if ($canManage): ?>
  <form class="form-grid js-hosting-form" id="details-form" method="post" action="<?= url('/hosting/update') ?>" hidden>
    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
    <input type="hidden" name="id" value="<?= (int) $record['id'] ?>">

    <p class="form-grid__section">Client details</p>
    <div class="field">
      <label class="form__label" for="service_type">This record is a</label>
      <select class="form__input" id="service_type" name="service_type">
        <option value="hosting"<?= !$isDomain ? ' selected' : '' ?>>Hosting plan</option>
        <option value="domain"<?= $isDomain ? ' selected' : '' ?>>Domain</option>
      </select>
    </div>
    <div class="field">
      <label class="form__label" for="client_id">Select client</label>
      <select class="form__input js-client-select" id="client_id" name="client_id">
        <option value="0">Not linked to a client record</option>
        <?php foreach ($clients as $c): ?>
          <option value="<?= (int) $c['id'] ?>" data-name="<?= e($c['name']) ?>" data-company="<?= e($c['company'] ?? '') ?>"
                  <?= (int) $record['client_id'] === (int) $c['id'] ? ' selected' : '' ?>>
            <?= e($c['name']) ?><?= !empty($c['company']) ? ' — ' . e($c['company']) : '' ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label class="form__label" for="client_name">Client name</label>
      <input class="form__input js-client-name" type="text" id="client_name" name="client_name" value="<?= e($record['client_name']) ?>" required>
    </div>
    <div class="field">
      <label class="form__label" for="company">Company name</label>
      <input class="form__input js-client-company" type="text" id="company" name="company" value="<?= e($record['company'] ?? '') ?>">
    </div>

    <p class="form-grid__section">Website details</p>
    <div class="field">
      <label class="form__label" for="website_name">Website name</label>
      <input class="form__input" type="text" id="website_name" name="website_name" value="<?= e($record['website_name'] ?? '') ?>">
    </div>
    <div class="field">
      <label class="form__label" for="domain">Domain name</label>
      <input class="form__input" type="text" id="domain" name="domain" value="<?= e($record['domain'] ?? '') ?>">
    </div>
    <div class="field">
      <label class="form__label" for="website_url">Website URL</label>
      <input class="form__input" type="url" id="website_url" name="website_url" value="<?= e($record['website_url'] ?? '') ?>">
    </div>
    <div class="field">
      <label class="form__label" for="project_id">Project</label>
      <select class="form__input" id="project_id" name="project_id">
        <option value="0">No project linked</option>
        <?php foreach ($projects as $p): ?>
          <option value="<?= (int) $p['id'] ?>"<?= (int) $record['project_id'] === (int) $p['id'] ? ' selected' : '' ?>><?= e($p['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <p class="form-grid__section">Hosting details</p>
    <div class="field">
      <label class="form__label" for="provider">Provider</label>
      <input class="form__input" type="text" id="provider" name="provider" value="<?= e($record['provider'] ?? '') ?>">
    </div>
    <div class="field">
      <label class="form__label" for="plan">Plan</label>
      <input class="form__input" type="text" id="plan" name="plan" value="<?= e($record['plan'] ?? '') ?>">
    </div>
    <div class="field">
      <label class="form__label" for="account_ref">Hosting account / service ID</label>
      <input class="form__input" type="text" id="account_ref" name="account_ref" value="<?= e($record['account_ref'] ?? '') ?>">
    </div>
    <div class="field">
      <label class="form__label" for="billing_cycle">Billing cycle</label>
      <select class="form__input js-cycle" id="billing_cycle" name="billing_cycle">
        <?php foreach ($cycles as $c): ?>
          <option value="<?= e($c) ?>"<?= $record['billing_cycle'] === $c ? ' selected' : '' ?>><?= e($cycleLabel($c)) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field js-custom-months"<?= $record['billing_cycle'] === 'custom' ? '' : ' hidden' ?>>
      <label class="form__label" for="custom_cycle_months">Custom cycle length (months)</label>
      <input class="form__input" type="number" id="custom_cycle_months" name="custom_cycle_months" min="1" max="120" value="<?= e((string) $custom) ?>">
    </div>
    <div class="field">
      <label class="form__label" for="purchase_date">Purchase date</label>
      <input class="form__input js-purchase" type="date" id="purchase_date" name="purchase_date" value="<?= e((string) ($record['purchase_date'] ?? '')) ?>">
    </div>
    <div class="field">
      <label class="form__label" for="renewal_date">Renewal / expiry date</label>
      <input class="form__input js-renewal" type="date" id="renewal_date" name="renewal_date" value="<?= e((string) $record['renewal_date']) ?>" required>
    </div>
    <div class="field">
      <label class="form__label" for="cost">Hosting cost (₹)</label>
      <input class="form__input" type="number" id="cost" name="cost" step="0.01" min="0"
             value="<?= $record['cost'] !== null ? e(number_format((float) $record['cost'], 2, '.', '')) : '' ?>">
    </div>
    <div class="field">
      <label class="form__label" for="renewal_cost">Renewal cost (₹)</label>
      <input class="form__input" type="number" id="renewal_cost" name="renewal_cost" step="0.01" min="0"
             value="<?= $record['renewal_cost'] !== null ? e(number_format((float) $record['renewal_cost'], 2, '.', '')) : '' ?>">
    </div>

    <p class="form-grid__section">Additional information</p>
    <div class="field">
      <label class="form__label" for="login_url">Hosting login URL</label>
      <input class="form__input" type="url" id="login_url" name="login_url" value="<?= e($record['login_url'] ?? '') ?>">
    </div>
    <div class="field">
      <label class="form__label" for="credential_ref">Where the login is kept</label>
      <input class="form__input" type="text" id="credential_ref" name="credential_ref" value="<?= e($record['credential_ref'] ?? '') ?>">
      <span class="field__hint">A pointer to your password manager — passwords are never stored in this panel.</span>
    </div>
    <div class="field field--wide">
      <label class="form__label" for="notes">Notes</label>
      <textarea class="form__input" id="notes" name="notes" rows="2"><?= e($record['notes'] ?? '') ?></textarea>
    </div>
    <div class="field field--wide">
      <label class="form__label" for="internal_notes">Internal notes</label>
      <textarea class="form__input" id="internal_notes" name="internal_notes" rows="2"><?= e($record['internal_notes'] ?? '') ?></textarea>
    </div>
    <div class="field field--action">
      <button type="submit" class="btn btn--primary">Save details</button>
      <button type="button" class="btn btn--ghost" data-toggle="details-form"
              data-label-open="Edit details" data-label-close="Cancel">Cancel</button>
    </div>
  </form>
  <?php endif; ?>
</section>

<!-- ---------- Renewal history ---------- -->
<section class="panel">
  <div class="panel__head">
    <h2 class="panel__title">Renewal history</h2>
    <div class="panel__actions">
      <span class="panel__count"><?= count($renewals) ?> renewal<?= count($renewals) === 1 ? '' : 's' ?> · <?= e($money($renewedSum)) ?></span>
    </div>
  </div>

  <div class="renew-timeline">
    <span><strong>Purchased</strong> <?= e($date($record['purchase_date'])) ?></span>
    <?php if (!empty($record['last_renewed_at'])): ?>
      <span>&rarr;</span><span><strong>Last renewed</strong> <?= e($date($record['last_renewed_at'])) ?></span>
    <?php endif; ?>
    <span>&rarr;</span><span><strong>Expires</strong> <?= e($date($record['renewal_date'])) ?></span>
    <?php if (!empty($record['next_renewal'])): ?>
      <span>&rarr;</span><span class="muted"><strong>Then</strong> <?= e($date($record['next_renewal'])) ?></span>
    <?php endif; ?>
  </div>

  <?php if (empty($renewals)): ?>
    <p class="empty">No renewals recorded yet. When you renew this <?= $isDomain ? 'domain' : 'hosting' ?>,
      use <strong>Mark as Renewed</strong> above — the expiry date moves forward and the renewal is filed here.</p>
  <?php else: ?>
    <table class="table">
      <thead>
        <tr>
          <th>Renewed on</th><th>Expiry moved</th><th>Amount</th><th>Payment</th>
          <th>Reference</th><th>Notes</th><th>Recorded by</th><th class="ta-right">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($renewals as $r): ?>
          <tr>
            <td><strong><?= e($date($r['renewal_date'])) ?></strong></td>
            <td class="muted"><?= e($date($r['previous_expiry'])) ?> &rarr; <?= e($date($r['new_expiry'])) ?></td>
            <td><?= e($money($r['amount'])) ?></td>
            <td><span class="badge badge--pay-<?= e($r['payment_status']) ?>"><?= e(ucfirst($r['payment_status'])) ?></span></td>
            <td class="muted"><?= e($r['payment_reference'] ?: '—') ?></td>
            <td class="muted"><?= e($r['notes'] ?: '—') ?></td>
            <td class="muted"><?= e($r['recorded_by'] ?: '—') ?></td>
            <td class="ta-right">
              <?php if ($canManage): ?>
                <form method="post" action="<?= url('/hosting/renewals/delete') ?>" class="inline-form"
                      onsubmit="return confirm('Remove this renewal entry? The expiry date on the record stays as it is.')">
                  <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                  <input type="hidden" name="hosting_id" value="<?= (int) $record['id'] ?>">
                  <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                  <button type="submit" class="btn btn--sm btn--danger">Delete</button>
                </form>
              <?php else: ?>
                <span class="muted">—</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</section>

<script src="<?= asset('js/hosting.js') ?>"></script>
