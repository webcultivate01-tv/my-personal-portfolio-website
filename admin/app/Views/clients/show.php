<?php
/**
 * One client's full record. The page shows only what's already been recorded —
 * each "+ Add" button in a panel head reveals that section's form.
 *
 * @var string $csrf @var array $client @var array $meetings @var array $invoices @var array $payments
 * @var array $bills @var float $invoiced @var float $paid @var array $statuses @var array $methods
 */
$money       = static fn(float $n): string => '₹' . number_format($n, 2);
$outstanding = $invoiced - $paid;
$cost        = $client['project_cost'] !== null ? (float) $client['project_cost'] : null;
$toInvoice   = $cost !== null ? $cost - $invoiced : null;
$today       = date('Y-m-d');

/** Payments can only be linked to invoices that are still open. */
$openInvoices = array_filter($invoices, static fn(array $i): bool => $i['status'] !== 'cancelled');
?>
<header class="page-head">
  <div>
    <h1 class="page-head__title"><?= e($client['name']) ?></h1>
    <p class="page-head__sub">
      <?php if (!empty($client['company'])): ?><?= e($client['company']) ?>&nbsp;·&nbsp;<?php endif; ?>
      Client since <?= e(date('M j, Y', strtotime($client['created_at']))) ?>
    </p>
  </div>
  <a class="btn btn--ghost btn--sm" href="<?= url('/clients') ?>">&larr; Back to clients</a>
</header>

<section class="stat-grid">
  <div class="stat"><span class="stat__label">Meetings held</span><span class="stat__value"><?= count($meetings) ?></span></div>
  <div class="stat">
    <span class="stat__label">Project cost</span>
    <span class="stat__value stat__value--money"><?= $cost !== null ? e($money($cost)) : '—' ?></span>
  </div>
  <div class="stat"><span class="stat__label">Invoiced</span><span class="stat__value stat__value--money"><?= e($money($invoiced)) ?></span></div>
  <div class="stat"><span class="stat__label">Received</span><span class="stat__value stat__value--money"><?= e($money($paid)) ?></span></div>
  <div class="stat<?= $outstanding > 0 ? ' stat--alert' : '' ?>">
    <span class="stat__label">Outstanding</span>
    <span class="stat__value stat__value--money"><?= e($money($outstanding)) ?></span>
  </div>
</section>

<!-- ---------- Client details ---------- -->
<section class="panel panel--pad">
  <div class="panel__head panel__head--plain">
    <h2 class="panel__title">Client details</h2>
    <button type="button" class="btn btn--sm btn--ghost" data-toggle="details-form"
            data-label-open="Edit details" data-label-close="Cancel">Edit details</button>
  </div>

  <div class="stat-grid stat-grid--profile" id="details-view">
    <div class="stat"><span class="stat__label">Company</span><span class="stat__value stat__value--sm"><?= e($client['company'] ?: '—') ?></span></div>
    <div class="stat"><span class="stat__label">Email</span><span class="stat__value stat__value--sm"><?= e($client['email'] ?: '—') ?></span></div>
    <div class="stat"><span class="stat__label">Phone</span><span class="stat__value stat__value--sm"><?= e($client['phone'] ?: '—') ?></span></div>
    <div class="stat"><span class="stat__label">Services taken</span><span class="stat__value stat__value--sm"><?= e($client['services'] ?: '—') ?></span></div>
    <div class="stat"><span class="stat__label">Total project cost</span><span class="stat__value stat__value--sm"><?= $cost !== null ? e($money($cost)) : '—' ?></span></div>
    <div class="stat">
      <span class="stat__label">Still to invoice</span>
      <span class="stat__value stat__value--sm"><?= $toInvoice !== null ? e($money($toInvoice)) : '—' ?></span>
    </div>
    <div class="stat"><span class="stat__label">Address</span><span class="stat__value stat__value--sm"><?= e($client['address'] ?: '—') ?></span></div>
    <div class="stat"><span class="stat__label">Notes</span><span class="stat__value stat__value--sm"><?= e($client['notes'] ?: '—') ?></span></div>
  </div>

  <form class="form-grid" id="details-form" method="post" action="<?= url('/clients/update') ?>" hidden>
    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
    <input type="hidden" name="id" value="<?= (int) $client['id'] ?>">

    <p class="form-grid__section">Contact</p>
    <div class="field">
      <label class="form__label" for="name">Client name</label>
      <input class="form__input" type="text" id="name" name="name" value="<?= e($client['name']) ?>" required>
    </div>
    <div class="field">
      <label class="form__label" for="company">Company</label>
      <input class="form__input" type="text" id="company" name="company" value="<?= e($client['company'] ?? '') ?>">
    </div>
    <div class="field">
      <label class="form__label" for="email">Email</label>
      <input class="form__input" type="email" id="email" name="email" value="<?= e($client['email'] ?? '') ?>">
    </div>
    <div class="field">
      <label class="form__label" for="phone">Phone</label>
      <input class="form__input" type="text" id="phone" name="phone" value="<?= e($client['phone'] ?? '') ?>" pattern="\d{10}" maxlength="10">
    </div>
    <div class="field field--wide">
      <label class="form__label" for="address">Address</label>
      <textarea class="form__input" id="address" name="address" rows="2"><?= e($client['address'] ?? '') ?></textarea>
    </div>

    <p class="form-grid__section">Project</p>
    <div class="field">
      <label class="form__label" for="project_cost">Total project cost (₹)</label>
      <input class="form__input" type="number" id="project_cost" name="project_cost" step="0.01" min="0"
             value="<?= $cost !== null ? e(number_format($cost, 2, '.', '')) : '' ?>">
    </div>
    <div class="field">
      <label class="form__label" for="services">Services they took</label>
      <input class="form__input" type="text" id="services" name="services" value="<?= e($client['services'] ?? '') ?>"
             placeholder="e.g. Website design, SEO, Hosting">
    </div>
    <div class="field field--wide">
      <label class="form__label" for="notes">Notes</label>
      <textarea class="form__input" id="notes" name="notes" rows="3"><?= e($client['notes'] ?? '') ?></textarea>
    </div>
    <div class="field field--action">
      <button type="submit" class="btn btn--primary">Save details</button>
    </div>
  </form>
</section>

<!-- ---------- Meetings ---------- -->
<section class="panel panel--pad">
  <div class="panel__head panel__head--plain">
    <h2 class="panel__title">Meetings</h2>
    <div class="panel__actions">
      <span class="panel__count"><?= count($meetings) ?> held</span>
      <button type="button" class="btn btn--sm btn--primary" data-toggle="meeting-form"
              data-label-open="+ Log meeting" data-label-close="Cancel">+ Log meeting</button>
    </div>
  </div>

  <form class="form-grid" id="meeting-form" method="post" action="<?= url('/clients/meetings/create') ?>" hidden>
    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
    <input type="hidden" name="client_id" value="<?= (int) $client['id'] ?>">
    <div class="field">
      <label class="form__label" for="meeting_date">Date</label>
      <input class="form__input" type="date" id="meeting_date" name="meeting_date" value="<?= e($today) ?>" required>
    </div>
    <div class="field">
      <label class="form__label" for="meeting_time">Time</label>
      <input class="form__input" type="time" id="meeting_time" name="meeting_time" required>
    </div>
    <div class="field">
      <label class="form__label" for="duration_minutes">Duration (minutes)</label>
      <input class="form__input" type="number" id="duration_minutes" name="duration_minutes" min="0" max="1440" placeholder="e.g. 45">
    </div>
    <div class="field field--wide">
      <label class="form__label" for="meeting_notes">What was discussed</label>
      <textarea class="form__input" id="meeting_notes" name="notes" rows="2" placeholder="Agenda, decisions, next steps…"></textarea>
    </div>
    <div class="field field--action">
      <button type="submit" class="btn btn--primary btn--sm">Log meeting</button>
    </div>
  </form>

  <?php if (empty($meetings)): ?>
    <p class="empty">No meetings logged yet. Use <strong>+ Log meeting</strong> above — each one is kept with its date, time and notes, so you always know how many times you've met this client.</p>
  <?php else: ?>
    <div class="notes-timeline">
      <?php foreach ($meetings as $m): ?>
        <div class="note-item">
          <div class="note-item__head">
            <span class="note-item__author">
              <?= e(date('M j, Y \a\t g:ia', strtotime($m['meeting_at']))) ?>
              <?php if (!empty($m['duration_minutes'])): ?>
                <span class="count-pill count-pill--soft"><?= (int) $m['duration_minutes'] ?> min</span>
              <?php endif; ?>
            </span>
            <div class="note-item__time">
              Logged by <?= e($m['created_by_name'] ?? 'Deleted user') ?>
              <form method="post" action="<?= url('/clients/meetings/delete') ?>" class="inline-form"
                    onsubmit="return confirm('Remove this meeting?')">
                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                <input type="hidden" name="client_id" value="<?= (int) $client['id'] ?>">
                <input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
                <button type="submit" class="link-danger">Remove</button>
              </form>
            </div>
          </div>
          <?php if (!empty($m['notes'])): ?>
            <div class="note-item__body"><?= nl2br(e($m['notes'])) ?></div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<!-- ---------- Invoices ---------- -->
<section class="panel panel--pad">
  <div class="panel__head panel__head--plain">
    <h2 class="panel__title">Invoices</h2>
    <div class="panel__actions">
      <span class="panel__count"><?= e($money($invoiced)) ?> invoiced</span>
      <button type="button" class="btn btn--sm btn--primary" data-toggle="invoice-form"
              data-label-open="+ Create invoice" data-label-close="Cancel">+ Create invoice</button>
    </div>
  </div>

  <form class="form-grid" id="invoice-form" method="post" action="<?= url('/clients/invoices/create') ?>" hidden>
    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
    <input type="hidden" name="client_id" value="<?= (int) $client['id'] ?>">
    <div class="field">
      <label class="form__label" for="invoice_number">Invoice number</label>
      <input class="form__input" type="text" id="invoice_number" name="invoice_number" placeholder="INV-001" required>
    </div>
    <div class="field">
      <label class="form__label" for="invoice_amount">Amount (₹)</label>
      <input class="form__input" type="number" id="invoice_amount" name="amount" step="0.01" min="0.01" placeholder="25000.00" required>
      <?php if ($toInvoice !== null): ?>
        <span class="field__hint"><?= e($money($toInvoice)) ?> of the project cost is still to be invoiced.</span>
      <?php endif; ?>
    </div>
    <div class="field">
      <label class="form__label" for="issue_date">Issue date</label>
      <input class="form__input" type="date" id="issue_date" name="issue_date" value="<?= e($today) ?>" required>
    </div>
    <div class="field">
      <label class="form__label" for="due_date">Due date</label>
      <input class="form__input" type="date" id="due_date" name="due_date">
    </div>
    <div class="field field--wide">
      <label class="form__label" for="invoice_notes">What it's for</label>
      <textarea class="form__input" id="invoice_notes" name="notes" rows="2" placeholder="Scope covered by this invoice"></textarea>
    </div>
    <div class="field field--action">
      <button type="submit" class="btn btn--primary btn--sm">Create invoice</button>
    </div>
  </form>

  <?php if (empty($invoices)): ?>
    <p class="empty">No invoices raised for this client yet. Use <strong>+ Create invoice</strong> above.</p>
  <?php else: ?>
    <table class="table">
      <thead>
        <tr><th>Invoice</th><th class="ta-right">Amount</th><th>Issued</th><th>Due</th><th>Status</th><th class="ta-right">Actions</th></tr>
      </thead>
      <tbody>
        <?php foreach ($invoices as $inv): ?>
          <tr>
            <td>
              <span class="enq-from__name"><?= e($inv['invoice_number']) ?></span>
              <?php if (!empty($inv['notes'])): ?><div class="enq-from__email"><?= e($inv['notes']) ?></div><?php endif; ?>
            </td>
            <td class="ta-right"><?= e($money((float) $inv['amount'])) ?></td>
            <td class="muted"><?= e(date('M j, Y', strtotime($inv['issue_date']))) ?></td>
            <td class="muted"><?= $inv['due_date'] ? e(date('M j, Y', strtotime($inv['due_date']))) : '—' ?></td>
            <td>
              <form method="post" action="<?= url('/clients/invoices/status') ?>" class="inline-form">
                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                <input type="hidden" name="client_id" value="<?= (int) $client['id'] ?>">
                <input type="hidden" name="id" value="<?= (int) $inv['id'] ?>">
                <select name="status" class="status-select status-select--inv-<?= e($inv['status']) ?>" onchange="this.form.submit()">
                  <?php foreach ($statuses as $s): ?>
                    <option value="<?= e($s) ?>"<?= $inv['status'] === $s ? ' selected' : '' ?>><?= e(ucfirst($s)) ?></option>
                  <?php endforeach; ?>
                </select>
              </form>
            </td>
            <td class="ta-right">
              <form method="post" action="<?= url('/clients/invoices/delete') ?>" class="inline-form"
                    onsubmit="return confirm('Delete invoice <?= e($inv['invoice_number']) ?>? This cannot be undone.')">
                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                <input type="hidden" name="client_id" value="<?= (int) $client['id'] ?>">
                <input type="hidden" name="id" value="<?= (int) $inv['id'] ?>">
                <button type="submit" class="btn btn--sm btn--danger">Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</section>

<!-- ---------- Bills ---------- -->
<section class="panel panel--pad">
  <div class="panel__head panel__head--plain">
    <h2 class="panel__title">Bills</h2>
    <div class="panel__actions">
      <span class="panel__count"><?= count($bills) ?> raised</span>
      <a class="btn btn--sm btn--primary" href="<?= url('/bills') ?>?client_id=<?= (int) $client['id'] ?>">+ Raise a bill</a>
    </div>
  </div>

  <?php if (empty($bills)): ?>
    <p class="empty">No bills raised for this client yet. Use <strong>+ Raise a bill</strong> above the next time they pay — it produces a proper, printable receipt and records the payment here too.</p>
  <?php else: ?>
    <table class="table">
      <thead>
        <tr><th>Bill</th><th>Project</th><th class="ta-right">Amount paid</th><th class="ta-right">Balance due</th><th class="ta-right">Actions</th></tr>
      </thead>
      <tbody>
        <?php foreach ($bills as $b): ?>
          <tr>
            <td>
              <span class="enq-from__name"><?= e($b['bill_number']) ?></span>
              <div class="enq-from__email"><?= e(date('M j, Y', strtotime($b['bill_date']))) ?></div>
            </td>
            <td class="muted"><?= $b['project_name'] ? e($b['project_name']) : '—' ?></td>
            <td class="ta-right amount-paid"><?= e($money((float) $b['amount_paid'])) ?></td>
            <td class="ta-right<?= $b['balance_due'] !== null && (float) $b['balance_due'] > 0 ? ' amount-due' : '' ?>">
              <?= $b['balance_due'] !== null ? e($money((float) $b['balance_due'])) : '<span class="muted">—</span>' ?>
            </td>
            <td class="ta-right">
              <a class="btn btn--sm btn--ghost" href="<?= url('/bills/view') ?>?id=<?= (int) $b['id'] ?>">View / PDF</a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</section>

<!-- ---------- Payments ---------- -->
<section class="panel panel--pad">
  <div class="panel__head panel__head--plain">
    <h2 class="panel__title">Payments</h2>
    <div class="panel__actions">
      <span class="panel__count"><?= e($money($paid)) ?> received</span>
      <button type="button" class="btn btn--sm btn--primary" data-toggle="payment-form"
              data-label-open="+ Record payment" data-label-close="Cancel">+ Record payment</button>
    </div>
  </div>

  <form class="form-grid" id="payment-form" method="post" action="<?= url('/clients/payments/create') ?>" hidden>
    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
    <input type="hidden" name="client_id" value="<?= (int) $client['id'] ?>">
    <div class="field">
      <label class="form__label" for="payment_amount">Amount (₹)</label>
      <input class="form__input" type="number" id="payment_amount" name="amount" step="0.01" min="0.01" placeholder="10000.00" required>
      <?php if ($outstanding > 0): ?>
        <span class="field__hint"><?= e($money($outstanding)) ?> is currently outstanding.</span>
      <?php endif; ?>
    </div>
    <div class="field">
      <label class="form__label" for="payment_date">Received on</label>
      <input class="form__input" type="date" id="payment_date" name="payment_date" value="<?= e($today) ?>" required>
    </div>
    <div class="field">
      <label class="form__label" for="method">Method</label>
      <select class="form__input" id="method" name="method">
        <?php foreach ($methods as $m): ?>
          <option value="<?= e($m) ?>"><?= e(ucwords(str_replace('_', ' ', $m))) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label class="form__label" for="invoice_id">Against invoice</label>
      <select class="form__input" id="invoice_id" name="invoice_id">
        <option value="0">Not linked to an invoice</option>
        <?php foreach ($openInvoices as $inv): ?>
          <option value="<?= (int) $inv['id'] ?>"><?= e($inv['invoice_number']) ?> — <?= e($money((float) $inv['amount'])) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field field--wide">
      <label class="form__label" for="payment_notes">Notes</label>
      <textarea class="form__input" id="payment_notes" name="notes" rows="2" placeholder="Reference number, part-payment details…"></textarea>
    </div>
    <div class="field field--action">
      <button type="submit" class="btn btn--primary btn--sm">Record payment</button>
    </div>
  </form>

  <?php if (empty($payments)): ?>
    <p class="empty">No payments recorded for this client yet. Use <strong>+ Record payment</strong> above.</p>
  <?php else: ?>
    <table class="table">
      <thead>
        <tr><th>Received on</th><th class="ta-right">Amount</th><th>Method</th><th>Invoice</th><th>Notes</th><th class="ta-right">Actions</th></tr>
      </thead>
      <tbody>
        <?php foreach ($payments as $p): ?>
          <tr>
            <td><?= e(date('M j, Y', strtotime($p['payment_date']))) ?></td>
            <td class="ta-right amount-paid"><?= e($money((float) $p['amount'])) ?></td>
            <td><span class="badge badge--quoted"><?= e(ucwords(str_replace('_', ' ', $p['method']))) ?></span></td>
            <td class="muted"><?= $p['invoice_number'] ? e($p['invoice_number']) : '—' ?></td>
            <td class="muted"><?= e($p['notes'] ?? '') ?></td>
            <td class="ta-right">
              <form method="post" action="<?= url('/clients/payments/delete') ?>" class="inline-form"
                    onsubmit="return confirm('Remove this payment?')">
                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                <input type="hidden" name="client_id" value="<?= (int) $client['id'] ?>">
                <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                <button type="submit" class="btn btn--sm btn--danger">Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</section>

<script>
  // Each "+ Add" / "Edit" button reveals its section's form and flips to "Cancel".
  document.addEventListener('click', function (ev) {
    var btn = ev.target.closest('[data-toggle]');
    if (!btn) return;

    var form = document.getElementById(btn.dataset.toggle);
    var open = form.hidden;
    form.hidden = !open;
    btn.textContent = open ? btn.dataset.labelClose : btn.dataset.labelOpen;

    // Editing the details replaces the read-only summary above it.
    var view = document.getElementById('details-view');
    if (view && form.id === 'details-form') view.hidden = open;

    if (open) form.querySelector('input:not([type=hidden]), select, textarea').focus();
  });
</script>
