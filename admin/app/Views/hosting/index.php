<?php
/**
 * Hosting & Domain dashboard.
 *
 * Ordered by what the admin needs to see first: the alert, then what is due,
 * then the full searchable list. Every row leads with renewal date and days
 * remaining, because that is the only thing that decides what to act on.
 *
 * @var string $csrf @var array $records @var array $summary @var array $upcoming
 * @var array $providers @var array $clients @var array $projects @var array $filters
 * @var array $cycles @var string $today
 */
$money = static fn($n): string => $n === null || $n === '' ? '—' : '₹' . number_format((float) $n, 2);

/** "in 12 days" / "8 days ago" / "today" — the countdown, in words. */
$daysText = static function (int $d): string {
    if ($d === 0) return 'Today';
    if ($d > 0)   return $d . ' day' . ($d === 1 ? '' : 's') . ' left';
    $d = abs($d);
    return $d . ' day' . ($d === 1 ? '' : 's') . ' ago';
};

$cycleLabel = static fn(string $c): string => ucwords(str_replace('_', ' ', $c));

/** Rebuild the current URL with one filter changed — keeps the others intact. */
$withFilter = static function (array $changes) use ($filters): string {
    $q = array_filter(array_merge($filters, $changes), static fn($v): bool => $v !== '' && $v !== null);
    return url('/hosting') . ($q ? '?' . http_build_query($q) : '');
};

$statusTabs = [
    ''              => 'All',
    'active'        => 'Active',
    'renewing_soon' => 'Renewing Soon',
    'due'           => 'Renewal Due',
    'expired'       => 'Expired',
    'renewed'       => 'Renewed',
];

$expired = (int) $summary['expired'];
$due     = (int) $summary['due'];
$soon    = (int) $summary['renewing_soon'];
$within30 = $due + $soon;
?>
<header class="page-head">
  <div>
    <h1 class="page-head__title">Hosting &amp; Domains</h1>
    <p class="page-head__sub">Every hosting plan and domain you manage for a client, with its renewal date and how long is left on it.</p>
  </div>
  <button type="button" class="btn btn--primary" data-toggle="hosting-form"
          data-label-open="+ Add Hosting" data-label-close="Cancel">+ Add Hosting</button>
</header>

<!-- ---------- Summary ---------- -->
<section class="stat-grid">
  <a class="stat" href="<?= e($withFilter(['status' => ''])) ?>">
    <span class="stat__label">Total</span><span class="stat__value"><?= (int) $summary['total'] ?></span>
  </a>
  <a class="stat" href="<?= e($withFilter(['status' => 'active'])) ?>">
    <span class="stat__label">Active</span><span class="stat__value"><?= (int) $summary['active'] ?></span>
  </a>
  <a class="stat<?= $soon > 0 ? ' stat--warn' : '' ?>" href="<?= e($withFilter(['status' => 'renewing_soon'])) ?>">
    <span class="stat__label">Renewing soon</span><span class="stat__value"><?= $soon ?></span>
  </a>
  <a class="stat<?= $expired > 0 ? ' stat--alert' : '' ?>" href="<?= e($withFilter(['status' => 'expired'])) ?>">
    <span class="stat__label">Expired</span><span class="stat__value"><?= $expired ?></span>
  </a>
  <a class="stat" href="<?= e($withFilter(['status' => 'renewed'])) ?>">
    <span class="stat__label">Renewed this month</span><span class="stat__value"><?= (int) $summary['renewed_this_month'] ?></span>
  </a>
</section>

<!-- ---------- Reminder banner ---------- -->
<?php if ($expired > 0 || $within30 > 0): ?>
  <a class="renew-alert<?= $expired > 0 ? ' renew-alert--danger' : '' ?>" href="#upcoming">
    <span class="renew-alert__icon">⚠️</span>
    <span>
      <?php if ($expired > 0): ?>
        <strong><?= $expired ?> hosting record<?= $expired === 1 ? ' has' : 's have' ?> already expired.</strong>
      <?php endif; ?>
      <?php if ($within30 > 0): ?>
        <strong><?= $within30 ?> renewal<?= $within30 === 1 ? ' is' : 's are' ?> due within the next <?= (int) \App\Models\HostingService::SOON_DAYS ?> days</strong><?php if ($due > 0): ?>, <?= $due ?> of them inside a week<?php endif; ?>.
      <?php endif; ?>
    </span>
    <span class="renew-alert__go">View renewals &rarr;</span>
  </a>
<?php endif; ?>

<!-- ---------- Add form ---------- -->
<section class="panel panel--pad" id="hosting-form" hidden>
  <div class="panel__head panel__head--plain">
    <h2 class="panel__title">Add hosting or domain</h2>
  </div>
  <p class="form-intro">
    Set the purchase date and billing cycle and the renewal date fills itself in —
    you can still overwrite it. Never type a password here: record where the login
    is kept instead.
  </p>
  <form class="form-grid js-hosting-form" method="post" action="<?= url('/hosting/create') ?>">
    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">

    <p class="form-grid__section">Client details</p>
    <div class="field">
      <label class="form__label" for="service_type">This record is a</label>
      <select class="form__input" id="service_type" name="service_type">
        <option value="hosting">Hosting plan</option>
        <option value="domain">Domain</option>
      </select>
    </div>
    <div class="field">
      <label class="form__label" for="client_id">Select client</label>
      <select class="form__input js-client-select" id="client_id" name="client_id">
        <option value="0">Not linked to a client record</option>
        <?php foreach ($clients as $c): ?>
          <option value="<?= (int) $c['id'] ?>" data-name="<?= e($c['name']) ?>" data-company="<?= e($c['company'] ?? '') ?>">
            <?= e($c['name']) ?><?= !empty($c['company']) ? ' — ' . e($c['company']) : '' ?>
          </option>
        <?php endforeach; ?>
      </select>
      <span class="field__hint">Picking a client fills the two fields below.</span>
    </div>
    <div class="field">
      <label class="form__label" for="client_name">Client name</label>
      <input class="form__input js-client-name" type="text" id="client_name" name="client_name" required>
    </div>
    <div class="field">
      <label class="form__label" for="company">Company name</label>
      <input class="form__input js-client-company" type="text" id="company" name="company">
    </div>

    <p class="form-grid__section">Website details</p>
    <div class="field">
      <label class="form__label" for="website_name">Website name</label>
      <input class="form__input" type="text" id="website_name" name="website_name" placeholder="e.g. ABC Corporate Site">
    </div>
    <div class="field">
      <label class="form__label" for="domain">Domain name</label>
      <input class="form__input" type="text" id="domain" name="domain" placeholder="abc.com">
    </div>
    <div class="field">
      <label class="form__label" for="website_url">Website URL</label>
      <input class="form__input" type="url" id="website_url" name="website_url" placeholder="https://abc.com">
    </div>
    <div class="field">
      <label class="form__label" for="project_id">Project</label>
      <select class="form__input" id="project_id" name="project_id">
        <option value="0">No project linked</option>
        <?php foreach ($projects as $p): ?>
          <option value="<?= (int) $p['id'] ?>"><?= e($p['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <p class="form-grid__section">Hosting details</p>
    <div class="field">
      <label class="form__label" for="provider">Provider</label>
      <input class="form__input" type="text" id="provider" name="provider" list="provider-list" placeholder="Hostinger, GoDaddy, Cloudflare…">
      <datalist id="provider-list">
        <?php foreach ($providers as $p): ?><option value="<?= e($p) ?>"><?php endforeach; ?>
      </datalist>
    </div>
    <div class="field">
      <label class="form__label" for="plan">Plan</label>
      <input class="form__input" type="text" id="plan" name="plan" placeholder="e.g. Premium Shared Hosting">
    </div>
    <div class="field">
      <label class="form__label" for="account_ref">Hosting account / service ID</label>
      <input class="form__input" type="text" id="account_ref" name="account_ref" placeholder="Optional">
    </div>
    <div class="field">
      <label class="form__label" for="billing_cycle">Billing cycle</label>
      <select class="form__input js-cycle" id="billing_cycle" name="billing_cycle">
        <?php foreach ($cycles as $c): ?>
          <option value="<?= e($c) ?>"<?= $c === 'yearly' ? ' selected' : '' ?>><?= e($cycleLabel($c)) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field js-custom-months" hidden>
      <label class="form__label" for="custom_cycle_months">Custom cycle length (months)</label>
      <input class="form__input" type="number" id="custom_cycle_months" name="custom_cycle_months" min="1" max="120" placeholder="e.g. 18">
    </div>
    <div class="field">
      <label class="form__label" for="purchase_date">Purchase date</label>
      <input class="form__input js-purchase" type="date" id="purchase_date" name="purchase_date" value="<?= e($today) ?>">
    </div>
    <div class="field">
      <label class="form__label" for="renewal_date">Renewal / expiry date</label>
      <input class="form__input js-renewal" type="date" id="renewal_date" name="renewal_date" required>
      <span class="field__hint">Calculated from purchase date + billing cycle. Edit it if the provider says otherwise.</span>
    </div>
    <div class="field">
      <label class="form__label" for="cost">Hosting cost (₹)</label>
      <input class="form__input" type="number" id="cost" name="cost" step="0.01" min="0" placeholder="What it cost to buy">
    </div>
    <div class="field">
      <label class="form__label" for="renewal_cost">Renewal cost (₹)</label>
      <input class="form__input" type="number" id="renewal_cost" name="renewal_cost" step="0.01" min="0" placeholder="What the next renewal costs">
    </div>

    <p class="form-grid__section">Additional information</p>
    <div class="field">
      <label class="form__label" for="login_url">Hosting login URL</label>
      <input class="form__input" type="url" id="login_url" name="login_url" placeholder="https://hpanel.hostinger.com">
    </div>
    <div class="field">
      <label class="form__label" for="credential_ref">Where the login is kept</label>
      <input class="form__input" type="text" id="credential_ref" name="credential_ref"
             placeholder="e.g. Bitwarden &gt; Hosting &gt; abc.com">
      <span class="field__hint">A pointer to your password manager — passwords are never stored in this panel.</span>
    </div>
    <div class="field field--wide">
      <label class="form__label" for="notes">Notes</label>
      <textarea class="form__input" id="notes" name="notes" rows="2" placeholder="Anything about this hosting the client may ask about"></textarea>
    </div>
    <div class="field field--wide">
      <label class="form__label" for="internal_notes">Internal notes</label>
      <textarea class="form__input" id="internal_notes" name="internal_notes" rows="2" placeholder="Team-only — not for the client"></textarea>
    </div>
    <div class="field field--action">
      <button type="submit" class="btn btn--primary">Save hosting record</button>
      <button type="button" class="btn btn--ghost" data-toggle="hosting-form"
              data-label-open="+ Add Hosting" data-label-close="Cancel">Cancel</button>
    </div>
  </form>
</section>

<!-- ---------- Upcoming renewals ---------- -->
<section class="panel" id="upcoming">
  <div class="panel__head">
    <h2 class="panel__title">Upcoming renewals</h2>
    <span class="panel__count"><?= count($upcoming) ?> need<?= count($upcoming) === 1 ? 's' : '' ?> attention</span>
  </div>

  <?php if (empty($upcoming)): ?>
    <p class="empty">Nothing expires in the next <?= (int) \App\Models\HostingService::SOON_DAYS ?> days. Everything you manage is comfortably in date.</p>
  <?php else: ?>
    <table class="table">
      <thead>
        <tr>
          <th>Client</th><th>Website / domain</th><th>Renewal date</th>
          <th>Days remaining</th><th>Amount</th><th class="ta-right">Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($upcoming as $r): ?>
          <?php $viewUrl = url('/hosting/view') . '?id=' . (int) $r['id']; ?>
          <tr class="row--<?= e($r['status']) ?>">
            <td>
              <a class="enq-from" href="<?= e($viewUrl) ?>">
                <span class="enq-from__name"><?= e($r['client_name']) ?></span>
                <span class="enq-from__email"><?= e($r['reminder_label'] ?? '') ?></span>
              </a>
            </td>
            <td><?= e($r['domain'] ?: ($r['website_name'] ?: '—')) ?></td>
            <td><strong><?= e(date('j M Y', strtotime((string) $r['renewal_date']))) ?></strong></td>
            <td><span class="days-pill days-pill--<?= e($r['status']) ?>"><?= e($daysText((int) $r['days_remaining'])) ?></span></td>
            <td><?= e($money($r['renewal_cost'] ?? $r['cost'])) ?></td>
            <td class="ta-right"><a class="btn btn--sm btn--primary" href="<?= e($viewUrl) ?>">View</a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</section>

<!-- ---------- All records ---------- -->
<section class="panel">
  <div class="panel__head">
    <div class="filter-tabs">
      <?php foreach ($statusTabs as $key => $label): ?>
        <a class="filter-tab<?= $filters['status'] === $key ? ' is-active' : '' ?>"
           href="<?= e($withFilter(['status' => $key])) ?>"><?= e($label) ?></a>
      <?php endforeach; ?>
    </div>
    <span class="panel__count"><?= count($records) ?> shown</span>
  </div>

  <form class="filter-bar" method="get" action="<?= url('/hosting') ?>">
    <input type="hidden" name="status" value="<?= e($filters['status']) ?>">
    <input class="form__input filter-bar__search" type="search" name="q" value="<?= e($filters['q']) ?>"
           placeholder="Search client, website, domain or provider…">
    <select class="form__input" name="type">
      <option value="">All types</option>
      <option value="hosting"<?= $filters['type'] === 'hosting' ? ' selected' : '' ?>>Hosting</option>
      <option value="domain"<?= $filters['type'] === 'domain' ? ' selected' : '' ?>>Domains</option>
    </select>
    <select class="form__input" name="provider">
      <option value="">All providers</option>
      <?php foreach ($providers as $p): ?>
        <option value="<?= e($p) ?>"<?= $filters['provider'] === $p ? ' selected' : '' ?>><?= e($p) ?></option>
      <?php endforeach; ?>
    </select>
    <select class="form__input" name="cycle">
      <option value="">All cycles</option>
      <?php foreach ($cycles as $c): ?>
        <option value="<?= e($c) ?>"<?= $filters['cycle'] === $c ? ' selected' : '' ?>><?= e($cycleLabel($c)) ?></option>
      <?php endforeach; ?>
    </select>
    <label class="filter-bar__date">Renewal from
      <input class="form__input" type="date" name="from" value="<?= e($filters['from']) ?>">
    </label>
    <label class="filter-bar__date">to
      <input class="form__input" type="date" name="to" value="<?= e($filters['to']) ?>">
    </label>
    <select class="form__input" name="sort">
      <option value="renewal_date"<?= $filters['sort'] === 'renewal_date' ? ' selected' : '' ?>>Sort: renewal date</option>
      <option value="client"<?= $filters['sort'] === 'client' ? ' selected' : '' ?>>Sort: client name</option>
      <option value="amount"<?= $filters['sort'] === 'amount' ? ' selected' : '' ?>>Sort: amount</option>
      <option value="status"<?= $filters['sort'] === 'status' ? ' selected' : '' ?>>Sort: status / urgency</option>
    </select>
    <select class="form__input" name="dir">
      <option value="asc"<?= $filters['dir'] !== 'desc' ? ' selected' : '' ?>>Ascending</option>
      <option value="desc"<?= $filters['dir'] === 'desc' ? ' selected' : '' ?>>Descending</option>
    </select>
    <button type="submit" class="btn btn--sm btn--primary">Apply</button>
    <a class="btn btn--sm btn--ghost" href="<?= url('/hosting') ?>">Reset</a>
  </form>

  <?php if (empty($records)): ?>
    <p class="empty">
      <?php if (array_filter($filters)): ?>
        Nothing matches those filters. <a href="<?= url('/hosting') ?>">Clear them</a> to see every record.
      <?php else: ?>
        No hosting or domains recorded yet. Use <strong>+ Add Hosting</strong> above to add the first one —
        set the purchase date and billing cycle and the renewal reminders start working straight away.
      <?php endif; ?>
    </p>
  <?php else: ?>
    <table class="table table--hosting">
      <thead>
        <tr>
          <th>Renewal date</th><th>Days left</th><th>Status</th><th>Client</th>
          <th>Website / domain</th><th>Provider</th><th>Plan</th>
          <th>Purchased</th><th>Amount</th><th class="ta-right">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($records as $r): ?>
          <?php $viewUrl = url('/hosting/view') . '?id=' . (int) $r['id']; ?>
          <tr class="row--<?= e($r['status']) ?>">
            <td><strong><?= e(date('j M Y', strtotime((string) $r['renewal_date']))) ?></strong></td>
            <td><span class="days-pill days-pill--<?= e($r['status']) ?>"><?= e($daysText((int) $r['days_remaining'])) ?></span></td>
            <td>
              <span class="badge badge--host-<?= e($r['status']) ?>"><?= e($r['status_label']) ?></span>
              <?php if ($r['renewed_this_month']): ?>
                <span class="badge badge--host-renewed" title="Renewed this month">Renewed</span>
              <?php endif; ?>
            </td>
            <td>
              <a class="enq-from" href="<?= e($viewUrl) ?>">
                <span class="enq-from__name"><?= e($r['client_name']) ?></span>
                <?php if (!empty($r['company'])): ?><span class="enq-from__email"><?= e($r['company']) ?></span><?php endif; ?>
              </a>
            </td>
            <td>
              <?= e($r['website_name'] ?: '—') ?>
              <?php if (!empty($r['domain'])): ?>
                <span class="type-tag type-tag--<?= e($r['service_type']) ?>"><?= e($r['domain']) ?></span>
              <?php endif; ?>
            </td>
            <td><?= e($r['provider'] ?: '—') ?></td>
            <td class="muted"><?= e($r['plan'] ?: '—') ?></td>
            <td class="muted"><?= !empty($r['purchase_date']) ? e(date('j M Y', strtotime((string) $r['purchase_date']))) : '—' ?></td>
            <td><?= e($money($r['renewal_cost'] ?? $r['cost'])) ?></td>
            <td class="ta-right">
              <div class="row-actions">
                <a class="btn btn--sm btn--ghost" href="<?= e($viewUrl) ?>">Open</a>
                <form method="post" action="<?= url('/hosting/delete') ?>" class="inline-form"
                      onsubmit="return confirm('Delete the hosting record for <?= e($r['client_name']) ?>? Its renewal history goes with it. This cannot be undone.')">
                  <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                  <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                  <button type="submit" class="btn btn--sm btn--danger">Delete</button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</section>

<script src="<?= asset('js/hosting.js') ?>"></script>
