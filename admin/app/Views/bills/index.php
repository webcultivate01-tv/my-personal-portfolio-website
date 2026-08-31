<?php
/**
 * Bill history, plus this month's collections and the outstanding total.
 * Raising a bill here also records the payment against the client, so the
 * Client Management totals and this page never drift apart.
 *
 * @var string $csrf @var array $bills @var array $clients @var array $projects
 * @var array $paidByProject @var array $methods
 * @var float $receivedThisMonth @var float $pendingTotal @var float $totalCollected @var int $totalBills
 * @var string $today @var array $filters @var bool $openCreateForm
 */
$money = static fn(float $n): string => '₹' . number_format($n, 2);
$hasFilters = array_filter($filters) !== [];
?>
<header class="page-head">
  <div>
    <h1 class="page-head__title">Billing</h1>
    <p class="page-head__sub">Raise a bill the moment a client pays — pick their project, list what it covers, and a proper, printable receipt is ready to download.</p>
  </div>
  <button type="button" class="btn btn--primary" onclick="toggleBillForm(true)">+ Raise a bill</button>
</header>

<section class="stat-grid">
  <div class="stat"><span class="stat__label">Bills raised</span><span class="stat__value"><?= $totalBills ?></span></div>
  <div class="stat"><span class="stat__label">Received this month</span><span class="stat__value stat__value--money"><?= e($money($receivedThisMonth)) ?></span></div>
  <div class="stat"><span class="stat__label">Total collected</span><span class="stat__value stat__value--money"><?= e($money($totalCollected)) ?></span></div>
  <div class="stat<?= $pendingTotal > 0 ? ' stat--alert' : '' ?>">
    <span class="stat__label">Pending amount</span>
    <span class="stat__value stat__value--money"><?= e($money($pendingTotal)) ?></span>
  </div>
</section>

<section class="panel panel--pad" id="bill-form"<?= $openCreateForm ? '' : ' hidden' ?>>
  <div class="panel__head panel__head--plain">
    <h2 class="panel__title">Raise a new bill</h2>
  </div>

  <form class="form-grid" id="bill-create-form" method="post" action="<?= url('/bills/create') ?>">
    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">

    <p class="form-grid__section">Who's paying</p>
    <div class="field">
      <label class="form__label" for="client_id">Client</label>
      <select class="form__input" id="client_id" name="client_id" required>
        <option value="">Select a client…</option>
        <?php foreach ($clients as $c): ?>
          <option value="<?= (int) $c['id'] ?>"<?= $filters['client_id'] === (int) $c['id'] ? ' selected' : '' ?>><?= e($c['name']) ?><?= $c['company'] ? ' — ' . e($c['company']) : '' ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label class="form__label" for="project_id">Project</label>
      <select class="form__input" id="project_id" name="project_id" required>
        <option value="">Select the client first…</option>
        <?php foreach ($projects as $p): ?>
          <option value="<?= (int) $p['id'] ?>" data-client="<?= (int) ($p['client_id'] ?? 0) ?>"
                  data-budget="<?= $p['budget'] !== null ? e((string) $p['budget']) : '' ?>" hidden>
            <?= e($p['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <span class="field__hint" id="project-cost-hint"></span>
    </div>
    <div class="field">
      <label class="form__label" for="bill_date">Bill date</label>
      <input class="form__input" type="date" id="bill_date" name="bill_date" value="<?= e($today) ?>" required>
    </div>

    <p class="form-grid__section">Services being billed</p>
    <div class="field field--wide">
      <div class="bill-items" id="bill-items">
        <div class="bill-items__row">
          <input class="form__input" type="text" name="item_desc[]" placeholder="e.g. Website design &amp; development" required>
          <input class="form__input bill-items__amount" type="number" name="item_amount[]" step="0.01" min="0" placeholder="Amount (₹)">
          <button type="button" class="btn btn--sm btn--ghost bill-items__remove" onclick="removeBillItem(this)">&times;</button>
        </div>
      </div>
      <button type="button" class="btn btn--sm btn--ghost" onclick="addBillItem()">+ Add another service</button>
    </div>

    <p class="form-grid__section">Payment received</p>
    <div class="field">
      <label class="form__label" for="amount_paid">Amount paid now (₹)</label>
      <input class="form__input" type="number" id="amount_paid" name="amount_paid" step="0.01" min="0.01" placeholder="10000.00" required>
    </div>
    <div class="field">
      <label class="form__label" for="payment_method">Method</label>
      <select class="form__input" id="payment_method" name="payment_method">
        <?php foreach ($methods as $m): ?>
          <option value="<?= e($m) ?>"><?= e(ucwords(str_replace('_', ' ', $m))) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field field--wide">
      <span class="field__hint" id="balance-preview"></span>
    </div>
    <div class="field field--wide">
      <label class="form__label" for="notes">Notes</label>
      <textarea class="form__input" id="notes" name="notes" rows="2" placeholder="Anything worth noting about this payment"></textarea>
    </div>

    <div class="field field--action">
      <button type="submit" class="btn btn--primary">Raise bill</button>
      <button type="button" class="btn btn--ghost" onclick="toggleBillForm(false)">Cancel</button>
    </div>
  </form>
</section>

<section class="panel">
  <div class="panel__head">
    <h2 class="panel__title">Bill history</h2>
    <span class="panel__count"><?= count($bills) ?> shown</span>
  </div>

  <form class="filter-bar" method="get" action="<?= url('/bills') ?>">
    <input class="form__input filter-bar__search" type="search" name="q" value="<?= e($filters['q']) ?>"
           placeholder="Search bill #, client or project…">
    <select class="form__input" name="client_id">
      <option value="">All clients</option>
      <?php foreach ($clients as $c): ?>
        <option value="<?= (int) $c['id'] ?>"<?= $filters['client_id'] === (int) $c['id'] ? ' selected' : '' ?>><?= e($c['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <select class="form__input" name="project_id">
      <option value="">All projects</option>
      <?php foreach ($projects as $p): ?>
        <option value="<?= (int) $p['id'] ?>"<?= $filters['project_id'] === (int) $p['id'] ? ' selected' : '' ?>><?= e($p['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <select class="form__input" name="payment_method">
      <option value="">All methods</option>
      <?php foreach ($methods as $m): ?>
        <option value="<?= e($m) ?>"<?= $filters['payment_method'] === $m ? ' selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', $m))) ?></option>
      <?php endforeach; ?>
    </select>
    <label class="filter-bar__date">From
      <input class="form__input" type="date" name="from" value="<?= e($filters['from']) ?>">
    </label>
    <label class="filter-bar__date">to
      <input class="form__input" type="date" name="to" value="<?= e($filters['to']) ?>">
    </label>
    <button type="submit" class="btn btn--sm btn--primary">Apply</button>
    <a class="btn btn--sm btn--ghost" href="<?= url('/bills') ?>">Reset</a>
  </form>

  <?php if (empty($bills)): ?>
    <p class="empty">
      <?php if ($hasFilters): ?>
        Nothing matches those filters. <a href="<?= url('/bills') ?>">Clear them</a> to see every bill.
      <?php else: ?>
        No bills raised yet. Use <strong>+ Raise a bill</strong> above the next time a client pays.
      <?php endif; ?>
    </p>
  <?php else: ?>
    <table class="table">
      <thead>
        <tr>
          <th>Bill</th><th>Client</th><th>Project</th>
          <th class="ta-right">Amount paid</th><th class="ta-right">Balance due</th>
          <th class="ta-right">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($bills as $b): ?>
          <?php $viewUrl = url('/bills/view') . '?id=' . (int) $b['id']; ?>
          <tr>
            <td>
              <a class="enq-from" href="<?= e($viewUrl) ?>">
                <span class="enq-from__name"><?= e($b['bill_number']) ?></span>
                <span class="enq-from__email"><?= e(date('M j, Y', strtotime($b['bill_date']))) ?></span>
              </a>
            </td>
            <td><?= e($b['client_name']) ?></td>
            <td class="muted"><?= $b['project_name'] ? e($b['project_name']) : '—' ?></td>
            <td class="ta-right amount-paid"><?= e($money((float) $b['amount_paid'])) ?></td>
            <td class="ta-right<?= $b['balance_due'] !== null && (float) $b['balance_due'] > 0 ? ' amount-due' : '' ?>">
              <?= $b['balance_due'] !== null ? e($money((float) $b['balance_due'])) : '<span class="muted">—</span>' ?>
            </td>
            <td class="ta-right">
              <div class="row-actions">
                <a class="btn btn--sm btn--ghost" href="<?= e($viewUrl) ?>">View / PDF</a>
                <form method="post" action="<?= url('/bills/delete') ?>" class="inline-form"
                      onsubmit="return confirm('Delete bill <?= e($b['bill_number']) ?>? This cannot be undone.')">
                  <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                  <input type="hidden" name="id" value="<?= (int) $b['id'] ?>">
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

<script>
  var paidByProject = <?= json_encode($paidByProject, JSON_NUMERIC_CHECK) ?>;
  var money = function (n) { return '₹' + n.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2}); };

  function toggleBillForm(show) {
    var panel = document.getElementById('bill-form');
    panel.hidden = !show;
    if (show) { panel.scrollIntoView({behavior: 'smooth'}); document.getElementById('client_id').focus(); }
  }

  function addBillItem() {
    var wrap = document.getElementById('bill-items');
    var row = document.createElement('div');
    row.className = 'bill-items__row';
    row.innerHTML = '<input class="form__input" type="text" name="item_desc[]" placeholder="Service">' +
      '<input class="form__input bill-items__amount" type="number" name="item_amount[]" step="0.01" min="0" placeholder="Amount (₹)">' +
      '<button type="button" class="btn btn--sm btn--ghost bill-items__remove" onclick="removeBillItem(this)">&times;</button>';
    wrap.appendChild(row);
  }

  function removeBillItem(btn) {
    var wrap = document.getElementById('bill-items');
    if (wrap.children.length > 1) btn.closest('.bill-items__row').remove();
  }

  // Project dropdown only shows projects belonging to the selected client.
  document.getElementById('client_id').addEventListener('change', function () {
    var clientId = this.value;
    var select = document.getElementById('project_id');
    var options = select.querySelectorAll('option[data-client]');
    select.value = '';
    options.forEach(function (opt) {
      opt.hidden = (opt.dataset.client !== clientId);
    });
    updateBalancePreview();
  });

  document.getElementById('project_id').addEventListener('change', updateBalancePreview);
  document.getElementById('amount_paid').addEventListener('input', updateBalancePreview);

  // Arriving from a client's page with ?client_id= already picks them — filter their projects immediately.
  if (document.getElementById('client_id').value) {
    document.getElementById('client_id').dispatchEvent(new Event('change'));
  }

  function updateBalancePreview() {
    var select = document.getElementById('project_id');
    var opt = select.options[select.selectedIndex];
    var hint = document.getElementById('project-cost-hint');
    var preview = document.getElementById('balance-preview');

    if (!opt || !opt.value) { hint.textContent = ''; preview.textContent = ''; return; }

    var budget = opt.dataset.budget !== '' ? parseFloat(opt.dataset.budget) : null;
    var paidSoFar = paidByProject[opt.value] || 0;
    hint.textContent = budget !== null
      ? 'Project cost ' + money(budget) + ' · ' + money(paidSoFar) + ' paid so far'
      : 'No project cost set — balance due won’t be calculated.';

    var amountNow = parseFloat(document.getElementById('amount_paid').value) || 0;
    if (budget !== null) {
      var remaining = budget - (paidSoFar + amountNow);
      preview.textContent = 'After this payment: ' + money(paidSoFar + amountNow) + ' paid, ' +
        (remaining > 0 ? money(remaining) + ' remaining' : 'fully paid' + (remaining < 0 ? ' (' + money(-remaining) + ' overpaid)' : ''));
    } else {
      preview.textContent = '';
    }
  }
</script>
