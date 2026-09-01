<?php
/**
 * Monthly Clients dashboard.
 *
 * Ordered by what needs acting on first: the money summary, then invoices
 * waiting to be raised, then what is due or overdue, then the recent trail,
 * then the full searchable client list.
 *
 * @var string $csrf @var array $clients @var array $counts @var array $summary
 * @var array $dueForBilling @var string $bucket @var array $bucketRows @var array $bucketCounts
 * @var array $recentInvoices @var array $recentPayments @var array $filters
 * @var array $frequencies @var array $methods @var array $terms
 * @var string $today @var bool $openCreateForm @var bool $canManage
 */

$canManage = $canManage ?? false;

use App\Models\MonthlyClient;
use App\Models\MonthlyInvoice;
use App\Models\MonthlyPayment;

$money = static fn($n): string => $n === null || $n === '' ? '—' : '₹' . number_format((float) $n, 2);
$date  = static fn(?string $d): string => $d ? date('j M Y', strtotime($d)) : '—';

/** "5 days left" / "3 days ago" / "Today" — the countdown, in words. */
$daysText = static function (?int $d): string {
    if ($d === null) return '—';
    if ($d === 0)    return 'Today';
    if ($d > 0)      return $d . ' day' . ($d === 1 ? '' : 's') . ' left';
    $d = abs($d);
    return $d . ' day' . ($d === 1 ? '' : 's') . ' ago';
};

/** The pill colour that matches a countdown: past due is red, this week amber. */
$dayClass = static function (?int $d): string {
    if ($d === null) return 'active';
    if ($d < 0)      return 'expired';
    if ($d <= 7)     return 'due';
    return 'active';
};

/** Rebuild the current URL with one filter changed — keeps the others intact. */
$withFilter = static function (array $changes) use ($filters, $bucket): string {
    $q = array_filter(array_merge($filters, ['bucket' => $bucket], $changes),
                      static fn($v): bool => $v !== '' && $v !== null);
    return url('/monthly-clients') . ($q ? '?' . http_build_query($q) : '');
};

$statusTabs = array_merge(['' => 'All'], MonthlyClient::FILTER_LABELS);
$dueToRaise = count($dueForBilling);
?>
<header class="page-head">
  <div>
    <h1 class="page-head__title">Monthly Clients</h1>
    <p class="page-head__sub">Every client on a recurring retainer — their contract, the invoice for each billing cycle, what they have paid, and what is still due.</p>
  </div>
  <?php if ($canManage): ?>
    <button type="button" class="btn btn--primary" data-toggle="monthly-form"
            data-label-open="+ Add Monthly Client" data-label-close="Cancel">+ Add Monthly Client</button>
  <?php else: ?>
    <span class="lock-hint">🔒 View only</span>
  <?php endif; ?>
</header>

<!-- ---------- Summary ---------- -->
<section class="stat-grid">
  <a class="stat" href="<?= e($withFilter(['status' => ''])) ?>">
    <span class="stat__label">Monthly clients</span><span class="stat__value"><?= (int) $summary['total'] ?></span>
  </a>
  <a class="stat" href="<?= e($withFilter(['status' => 'active'])) ?>">
    <span class="stat__label">Active</span><span class="stat__value"><?= (int) $summary['active'] ?></span>
  </a>
  <div class="stat">
    <span class="stat__label">Monthly recurring</span>
    <span class="stat__value stat__value--money"><?= e($money($summary['recurring'])) ?></span>
  </div>
  <div class="stat">
    <span class="stat__label">Due this month</span>
    <span class="stat__value stat__value--money"><?= e($money($summary['due_this_month'])) ?></span>
  </div>
  <div class="stat">
    <span class="stat__label">Paid this month</span>
    <span class="stat__value stat__value--money"><?= e($money($summary['paid_this_month'])) ?></span>
  </div>
  <div class="stat<?= $summary['outstanding'] > 0 ? ' stat--warn' : '' ?>">
    <span class="stat__label">Outstanding</span>
    <span class="stat__value stat__value--money"><?= e($money($summary['outstanding'])) ?></span>
  </div>
  <a class="stat<?= $summary['overdue'] > 0 ? ' stat--alert' : '' ?>" href="<?= e($withFilter(['bucket' => 'overdue'])) ?>#dues">
    <span class="stat__label">Overdue</span>
    <span class="stat__value stat__value--money"><?= e($money($summary['overdue'])) ?></span>
  </a>
</section>

<!-- ---------- Invoices waiting to be raised ---------- -->
<?php if ($dueToRaise > 0): ?>
  <a class="renew-alert" href="#billing-due">
    <span class="renew-alert__icon">🧾</span>
    <span>
      <strong><?= $dueToRaise ?> client<?= $dueToRaise === 1 ? '' : 's' ?></strong>
      <?= $dueToRaise === 1 ? 'has' : 'have' ?> reached the start of a new billing period and
      <?= $dueToRaise === 1 ? 'is' : 'are' ?> waiting for an invoice.
    </span>
    <span class="renew-alert__go">See who &rarr;</span>
  </a>
<?php endif; ?>

<!-- ---------- Add a monthly client ---------- -->
<?php if ($canManage): ?>
<section class="panel panel--pad" id="monthly-form"<?= $openCreateForm ? '' : ' hidden' ?>>
  <div class="panel__head panel__head--plain">
    <h2 class="panel__title">Add a monthly client</h2>
  </div>
  <p class="form-intro">
    The monthly amount and billing frequency decide what each invoice comes to
    (a monthly rate billed quarterly raises three months at a time), and the
    payment terms decide when each invoice falls due. Billing starts on the
    start date — nothing is invoiced until you generate it.
  </p>
  <form class="form-grid js-monthly-form" method="post" action="<?= url('/monthly-clients/create') ?>">
    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">

    <p class="form-grid__section">Client details</p>
    <div class="field">
      <label class="form__label" for="client_name">Client name</label>
      <input class="form__input" type="text" id="client_name" name="client_name" placeholder="Jane Doe" required>
    </div>
    <div class="field">
      <label class="form__label" for="company">Company name</label>
      <input class="form__input" type="text" id="company" name="company" placeholder="Acme Pvt Ltd">
    </div>
    <div class="field">
      <label class="form__label" for="email">Email</label>
      <input class="form__input" type="email" id="email" name="email" placeholder="jane@example.com">
    </div>
    <div class="field">
      <label class="form__label" for="mobile">Mobile number</label>
      <input class="form__input" type="text" id="mobile" name="mobile" placeholder="10-digit mobile number" pattern="\d{10}" maxlength="10">
    </div>
    <div class="field field--wide">
      <label class="form__label" for="billing_address">Billing address</label>
      <textarea class="form__input" id="billing_address" name="billing_address" rows="2" placeholder="Printed on every invoice and receipt"></textarea>
    </div>

    <p class="form-grid__section">Service</p>
    <div class="field">
      <label class="form__label" for="service_name">Service name</label>
      <input class="form__input" type="text" id="service_name" name="service_name" placeholder="e.g. Social media management" required>
    </div>
    <div class="field field--wide">
      <label class="form__label" for="service_description">Service description</label>
      <textarea class="form__input" id="service_description" name="service_description" rows="2" placeholder="What the retainer covers each month"></textarea>
    </div>

    <p class="form-grid__section">Billing</p>
    <div class="field">
      <label class="form__label" for="monthly_amount">Monthly amount (₹)</label>
      <input class="form__input js-amount" type="number" id="monthly_amount" name="monthly_amount" step="0.01" min="0" placeholder="e.g. 15000.00" required>
    </div>
    <div class="field">
      <label class="form__label" for="billing_frequency">Billing frequency</label>
      <select class="form__input js-frequency" id="billing_frequency" name="billing_frequency">
        <?php foreach ($frequencies as $f): ?>
          <option value="<?= e($f) ?>"><?= e(MonthlyClient::frequencyLabel($f)) ?></option>
        <?php endforeach; ?>
      </select>
      <span class="field__hint">How often an invoice is actually raised.</span>
    </div>
    <div class="field">
      <label class="form__label" for="discount_type">Discount</label>
      <select class="form__input js-discount-type" id="discount_type" name="discount_type">
        <option value="none">No discount</option>
        <option value="percent">Percentage of the invoice</option>
        <option value="amount">Flat amount off</option>
      </select>
    </div>
    <div class="field js-discount-value" hidden>
      <label class="form__label" for="discount_value">Discount value</label>
      <input class="form__input js-discount" type="number" id="discount_value" name="discount_value" step="0.01" min="0" value="0">
    </div>
    <div class="field">
      <label class="form__label" for="tax_percent">Tax (%)</label>
      <input class="form__input js-tax" type="number" id="tax_percent" name="tax_percent" step="0.01" min="0" value="0" placeholder="e.g. 18">
      <span class="field__hint">Leave at 0 if no tax applies.</span>
    </div>
    <div class="field">
      <label class="form__label" for="payment_method">Payment method</label>
      <select class="form__input" id="payment_method" name="payment_method">
        <?php foreach ($methods as $m): ?>
          <option value="<?= e($m) ?>"><?= e(MonthlyClient::methodLabel($m)) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label class="form__label" for="payment_terms">Payment terms</label>
      <select class="form__input" id="payment_terms" name="payment_terms">
        <?php foreach ($terms as $t): ?>
          <option value="<?= e($t) ?>"<?= $t === 'net_7' ? ' selected' : '' ?>><?= e(MonthlyClient::termsLabel($t)) ?></option>
        <?php endforeach; ?>
      </select>
      <span class="field__hint">Sets each invoice's due date.</span>
    </div>
    <div class="field field--wide">
      <p class="form-intro form-intro--tight">
        Each invoice will come to <strong class="js-preview">—</strong>.
      </p>
    </div>

    <p class="form-grid__section">Contract</p>
    <div class="field">
      <label class="form__label" for="start_date">Start date</label>
      <input class="form__input" type="date" id="start_date" name="start_date" value="<?= e($today) ?>" required>
      <span class="field__hint">The first billing period starts here.</span>
    </div>
    <div class="field">
      <label class="form__label" for="contract_end_date">Contract end date</label>
      <input class="form__input" type="date" id="contract_end_date" name="contract_end_date">
      <span class="field__hint">Leave blank for an open-ended retainer.</span>
    </div>
    <div class="field">
      <label class="form__label" for="renewal_date">Renewal date</label>
      <input class="form__input" type="date" id="renewal_date" name="renewal_date">
    </div>
    <div class="field field--wide">
      <label class="form__label" for="contract_notes">Contract notes</label>
      <textarea class="form__input" id="contract_notes" name="contract_notes" rows="2" placeholder="Scope, agreed terms, anything the contract says"></textarea>
    </div>
    <div class="field field--wide">
      <label class="form__label" for="notes">Notes</label>
      <textarea class="form__input" id="notes" name="notes" rows="2" placeholder="Anything worth remembering about this client"></textarea>
    </div>
    <div class="field field--action">
      <button type="submit" class="btn btn--primary">Add monthly client</button>
      <button type="button" class="btn btn--ghost" data-toggle="monthly-form"
              data-label-open="+ Add Monthly Client" data-label-close="Cancel">Cancel</button>
    </div>
  </form>
</section>
<?php endif; ?>

<!-- ---------- Invoices waiting to be raised ---------- -->
<?php if ($dueToRaise > 0): ?>
  <section class="panel" id="billing-due">
    <div class="panel__head">
      <h2 class="panel__title">Billing due to be raised</h2>
      <span class="panel__count"><?= $dueToRaise ?> client<?= $dueToRaise === 1 ? '' : 's' ?></span>
    </div>
    <table class="table">
      <thead>
        <tr><th>Client</th><th>Service</th><th>Billing period starts</th><th></th><th class="ta-right">Invoice amount</th><th class="ta-right">Action</th></tr>
      </thead>
      <tbody>
        <?php foreach ($dueForBilling as $c): ?>
          <?php $viewUrl = url('/monthly-clients/view') . '?id=' . (int) $c['id']; ?>
          <tr>
            <td>
              <a class="enq-from" href="<?= e($viewUrl) ?>">
                <span class="enq-from__name"><?= e($c['client_name']) ?></span>
                <span class="enq-from__email"><?= e($c['company'] ?: ($c['email'] ?: '')) ?></span>
              </a>
            </td>
            <td class="muted"><?= e($c['service_name']) ?></td>
            <td><strong><?= e($date($c['next_billing_date'])) ?></strong></td>
            <td><span class="days-pill days-pill--<?= e($dayClass($c['days_to_next_billing'])) ?>"><?= e($daysText($c['days_to_next_billing'])) ?></span></td>
            <td class="ta-right"><?= e($money($c['cycle_amount'])) ?></td>
            <td class="ta-right">
              <?php if ($canManage): ?>
                <a class="btn btn--sm btn--primary" href="<?= e($viewUrl) ?>#invoice-form">Generate invoice</a>
              <?php else: ?>
                <a class="btn btn--sm btn--ghost" href="<?= e($viewUrl) ?>">Open</a>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </section>
<?php endif; ?>

<!-- ---------- Due & overdue ---------- -->
<section class="panel" id="dues">
  <div class="panel__head">
    <div class="filter-tabs">
      <?php foreach (MonthlyInvoice::BUCKETS as $key => $label): ?>
        <a class="filter-tab<?= $bucket === $key ? ' is-active' : '' ?>" href="<?= e($withFilter(['bucket' => $key])) ?>#dues">
          <?= e($label) ?> <span class="count-pill count-pill--soft"><?= (int) ($bucketCounts[$key] ?? 0) ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  </div>

  <?php if (empty($bucketRows)): ?>
    <p class="empty">
      <?php if ($bucket === 'overdue'): ?>
        Nothing is overdue. Every invoice that has fallen due has been settled.
      <?php else: ?>
        No invoices in <strong><?= e(strtolower(MonthlyInvoice::BUCKETS[$bucket])) ?></strong> right now.
      <?php endif; ?>
    </p>
  <?php else: ?>
    <table class="table table--monthly">
      <thead>
        <tr>
          <th>Invoice</th><th>Client</th><th>Billing period</th><th>Due date</th>
          <th><?= $bucket === 'overdue' ? 'Days overdue' : 'Due in' ?></th>
          <th class="ta-right">Total</th><th class="ta-right">Paid</th><th class="ta-right">Outstanding</th>
          <th>Status</th><th class="ta-right">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($bucketRows as $inv): ?>
          <tr class="row--<?= e($inv['display_status']) ?>">
            <td><a href="<?= url('/monthly-clients/invoice') ?>?id=<?= (int) $inv['id'] ?>"><strong><?= e($inv['invoice_number']) ?></strong></a></td>
            <td>
              <a class="enq-from" href="<?= url('/monthly-clients/view') ?>?id=<?= (int) $inv['monthly_client_id'] ?>">
                <span class="enq-from__name"><?= e($inv['client_name']) ?></span>
                <span class="enq-from__email"><?= e($inv['company'] ?: $inv['service_name']) ?></span>
              </a>
            </td>
            <td class="muted"><?= e($inv['period_label']) ?></td>
            <td><?= e($date($inv['due_date'])) ?></td>
            <td>
              <?php if ($inv['is_overdue']): ?>
                <span class="days-pill days-pill--expired"><?= (int) $inv['days_overdue'] ?> day<?= (int) $inv['days_overdue'] === 1 ? '' : 's' ?> overdue</span>
              <?php else: ?>
                <span class="days-pill days-pill--<?= e($dayClass($inv['days_to_due'])) ?>"><?= e($daysText($inv['days_to_due'])) ?></span>
              <?php endif; ?>
            </td>
            <td class="ta-right"><?= e($money($inv['total_amount'])) ?></td>
            <td class="ta-right amount-paid"><?= e($money($inv['amount_paid'])) ?></td>
            <td class="ta-right<?= $inv['balance_due'] > 0 ? ' amount-due' : '' ?>"><?= e($money($inv['balance_due'])) ?></td>
            <td><span class="badge badge--inv-<?= e($inv['display_status']) ?>"><?= e($inv['status_label']) ?></span></td>
            <td class="ta-right">
              <div class="row-actions">
                <a class="btn btn--sm btn--ghost" href="<?= url('/monthly-clients/invoice') ?>?id=<?= (int) $inv['id'] ?>">Invoice</a>
                <?php if ($canManage && $inv['balance_due'] > 0 && $inv['display_status'] !== 'cancelled'): ?>
                  <a class="btn btn--sm btn--primary" href="<?= url('/monthly-clients/view') ?>?id=<?= (int) $inv['monthly_client_id'] ?>&pay=<?= (int) $inv['id'] ?>#payment-form">Record payment</a>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</section>

<!-- ---------- Recent payments ---------- -->
<section class="panel">
  <div class="panel__head">
    <h2 class="panel__title">Recent payments</h2>
    <span class="panel__count"><?= count($recentPayments) ?> shown</span>
  </div>
  <?php if (empty($recentPayments)): ?>
    <p class="empty">No payments recorded yet. Once you record one against an invoice, it appears here with its receipt.</p>
  <?php else: ?>
    <table class="table">
      <thead>
        <tr><th>Receipt</th><th>Client</th><th>Invoice</th><th>Paid on</th><th>Method</th>
            <th class="ta-right">Amount</th><th class="ta-right">Balance left</th><th class="ta-right">Actions</th></tr>
      </thead>
      <tbody>
        <?php foreach ($recentPayments as $p): ?>
          <tr>
            <td><a href="<?= url('/monthly-clients/receipt') ?>?id=<?= (int) $p['id'] ?>"><strong><?= e($p['receipt_number']) ?></strong></a></td>
            <td>
              <a class="enq-from" href="<?= url('/monthly-clients/view') ?>?id=<?= (int) $p['monthly_client_id'] ?>">
                <span class="enq-from__name"><?= e($p['client_name']) ?></span>
                <span class="enq-from__email"><?= e($p['company'] ?: '') ?></span>
              </a>
            </td>
            <td class="muted"><?= e($p['invoice_number']) ?></td>
            <td><?= e($date($p['payment_date'])) ?></td>
            <td class="muted"><?= e(MonthlyPayment::methodLabel((string) $p['method'])) ?></td>
            <td class="ta-right amount-paid"><?= e($money($p['amount'])) ?></td>
            <td class="ta-right<?= (float) $p['balance_after'] > 0 ? ' amount-due' : '' ?>"><?= e($money($p['balance_after'])) ?></td>
            <td class="ta-right"><a class="btn btn--sm btn--ghost" href="<?= url('/monthly-clients/receipt') ?>?id=<?= (int) $p['id'] ?>">Receipt</a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</section>

<!-- ---------- Recent invoices ---------- -->
<section class="panel">
  <div class="panel__head">
    <h2 class="panel__title">Recent invoices</h2>
    <span class="panel__count"><?= count($recentInvoices) ?> shown</span>
  </div>
  <?php if (empty($recentInvoices)): ?>
    <p class="empty">No invoices raised yet. Open a client and use <strong>Generate invoice</strong> to raise the first billing cycle.</p>
  <?php else: ?>
    <table class="table">
      <thead>
        <tr><th>Invoice</th><th>Client</th><th>Billing period</th><th>Invoice date</th><th>Due date</th>
            <th class="ta-right">Total</th><th class="ta-right">Outstanding</th><th>Status</th><th class="ta-right">Actions</th></tr>
      </thead>
      <tbody>
        <?php foreach ($recentInvoices as $inv): ?>
          <tr class="row--<?= e($inv['display_status']) ?>">
            <td><a href="<?= url('/monthly-clients/invoice') ?>?id=<?= (int) $inv['id'] ?>"><strong><?= e($inv['invoice_number']) ?></strong></a></td>
            <td>
              <a class="enq-from" href="<?= url('/monthly-clients/view') ?>?id=<?= (int) $inv['monthly_client_id'] ?>">
                <span class="enq-from__name"><?= e($inv['client_name']) ?></span>
                <span class="enq-from__email"><?= e($inv['service_name']) ?></span>
              </a>
            </td>
            <td class="muted"><?= e($inv['period_label']) ?></td>
            <td><?= e($date($inv['invoice_date'])) ?></td>
            <td><?= e($date($inv['due_date'])) ?></td>
            <td class="ta-right"><?= e($money($inv['total_amount'])) ?></td>
            <td class="ta-right<?= $inv['balance_due'] > 0 ? ' amount-due' : '' ?>"><?= e($money($inv['balance_due'])) ?></td>
            <td><span class="badge badge--inv-<?= e($inv['display_status']) ?>"><?= e($inv['status_label']) ?></span></td>
            <td class="ta-right"><a class="btn btn--sm btn--ghost" href="<?= url('/monthly-clients/invoice') ?>?id=<?= (int) $inv['id'] ?>">Open</a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</section>

<!-- ---------- All monthly clients ---------- -->
<section class="panel" id="clients">
  <div class="panel__head">
    <div class="filter-tabs">
      <?php foreach ($statusTabs as $key => $label): ?>
        <a class="filter-tab<?= $filters['status'] === $key ? ' is-active' : '' ?>" href="<?= e($withFilter(['status' => $key])) ?>#clients">
          <?= e($label) ?> <span class="count-pill count-pill--soft"><?= (int) ($counts[$key] ?? 0) ?></span>
        </a>
      <?php endforeach; ?>
    </div>
    <span class="panel__count"><?= count($clients) ?> shown</span>
  </div>

  <form class="filter-bar" method="get" action="<?= url('/monthly-clients') ?>">
    <input type="hidden" name="status" value="<?= e($filters['status']) ?>">
    <input type="hidden" name="bucket" value="<?= e($bucket) ?>">
    <input class="form__input filter-bar__search" type="search" name="q" value="<?= e($filters['q']) ?>"
           placeholder="Search client, company, email, service or invoice number…">
    <select class="form__input" name="sort">
      <option value="name"<?= $filters['sort'] === 'name' ? ' selected' : '' ?>>Sort: client name</option>
      <option value="amount"<?= $filters['sort'] === 'amount' ? ' selected' : '' ?>>Sort: monthly amount</option>
      <option value="billing"<?= $filters['sort'] === 'billing' ? ' selected' : '' ?>>Sort: next billing date</option>
    </select>
    <select class="form__input" name="dir">
      <option value="asc"<?= $filters['dir'] !== 'desc' ? ' selected' : '' ?>>Ascending</option>
      <option value="desc"<?= $filters['dir'] === 'desc' ? ' selected' : '' ?>>Descending</option>
    </select>
    <button type="submit" class="btn btn--sm btn--primary">Apply</button>
    <a class="btn btn--sm btn--ghost" href="<?= url('/monthly-clients') ?>">Reset</a>
  </form>

  <?php if (empty($clients)): ?>
    <p class="empty">
      <?php if ($filters['q'] !== '' || $filters['status'] !== ''): ?>
        Nothing matches that search. <a href="<?= url('/monthly-clients') ?>">Clear it</a> to see every monthly client.
      <?php else: ?>
        No monthly clients yet. Use <strong>+ Add Monthly Client</strong> above to add the first retainer —
        then generate its invoice each cycle and record payments against it.
      <?php endif; ?>
    </p>
  <?php else: ?>
    <table class="table table--monthly">
      <thead>
        <tr>
          <th>Client</th><th>Service</th><th>Frequency</th><th class="ta-right">Per cycle</th>
          <th>Next billing</th><th>Next payment due</th>
          <th class="ta-right">Billed</th><th class="ta-right">Paid</th><th class="ta-right">Outstanding</th>
          <th>Status</th><th class="ta-right">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($clients as $c): ?>
          <?php $viewUrl = url('/monthly-clients/view') . '?id=' . (int) $c['id']; ?>
          <tr class="row--<?= e($c['display_status']) ?>">
            <td>
              <a class="enq-from" href="<?= e($viewUrl) ?>">
                <span class="enq-from__name"><?= e($c['client_name']) ?></span>
                <span class="enq-from__email"><?= e($c['company'] ?: ($c['email'] ?: ($c['mobile'] ?: ''))) ?></span>
              </a>
            </td>
            <td class="muted"><?= e($c['service_name']) ?></td>
            <td class="muted"><?= e(MonthlyClient::frequencyLabel((string) $c['billing_frequency'])) ?></td>
            <td class="ta-right"><?= e($money($c['cycle_amount'])) ?></td>
            <td>
              <?php if ($c['next_billing_date'] !== null): ?>
                <?= e($date($c['next_billing_date'])) ?>
                <span class="days-pill days-pill--<?= e($dayClass($c['days_to_next_billing'])) ?>"><?= e($daysText($c['days_to_next_billing'])) ?></span>
              <?php else: ?>
                <span class="muted">Billing stopped</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if (!empty($c['next_due_date'])): ?>
                <?= e($date($c['next_due_date'])) ?>
                <span class="muted"><?= e($money($c['next_due_amount'])) ?></span>
              <?php else: ?>
                <span class="muted">—</span>
              <?php endif; ?>
            </td>
            <td class="ta-right"><?= e($money($c['total_billed'])) ?></td>
            <td class="ta-right amount-paid"><?= e($money($c['total_paid'])) ?></td>
            <td class="ta-right<?= (float) $c['total_outstanding'] > 0 ? ' amount-due' : '' ?>"><?= e($money($c['total_outstanding'])) ?></td>
            <td><span class="badge badge--mc-<?= e($c['display_status']) ?>"><?= e($c['status_label']) ?></span></td>
            <td class="ta-right"><a class="btn btn--sm btn--ghost" href="<?= e($viewUrl) ?>"><?= $canManage ? 'Manage' : 'View' ?></a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</section>

<script src="<?= asset('js/monthly.js') ?>"></script>
