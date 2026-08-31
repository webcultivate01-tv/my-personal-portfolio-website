<?php
/**
 * One monthly client in full.
 *
 * The financial summary sits at the top because it is the only thing that is
 * ever urgent; the actions that change money (generate an invoice, record a
 * payment) come next, then the contract and details, then the complete
 * billing history — which is never removed, whatever happens to the client.
 *
 * @var string $csrf @var array $client @var array $invoices @var array $payments
 * @var array $pauses @var array $preview @var string $periodStart @var string $periodEnd
 * @var string $dueDate @var array $frequencies @var array $methods @var array $terms
 * @var array $payMethods @var string $today @var int $payInvoiceId
 */

use App\Models\MonthlyClient;
use App\Models\MonthlyInvoice;
use App\Models\MonthlyPayment;

$money = static fn($n): string => $n === null || $n === '' ? '—' : '₹' . number_format((float) $n, 2);
$date  = static fn(?string $d): string => $d ? date('j M Y', strtotime($d)) : '—';
$num   = static fn($n): string => number_format((float) $n, 2, '.', '');

$status    = (string) $client['display_status'];
$lifecycle = (string) $client['status'];
$isActive  = $lifecycle === 'active';

// Invoices that can still take a payment, for the "record a payment" picker.
$openInvoices = array_values(array_filter(
    $invoices,
    static fn(array $i): bool => $i['display_status'] !== 'cancelled' && $i['balance_due'] > 0.004
));

$paidInvoices = count($invoices) - count($openInvoices);
?>
<header class="page-head">
  <div>
    <h1 class="page-head__title">
      <?= e($client['client_name']) ?>
      <span class="badge badge--mc-<?= e($status) ?>"><?= e($client['status_label']) ?></span>
    </h1>
    <p class="page-head__sub">
      <?= e($client['service_name']) ?>
      &nbsp;·&nbsp; <?= e($money($client['monthly_amount'])) ?>/month
      &nbsp;·&nbsp; billed <?= e(strtolower(MonthlyClient::frequencyLabel((string) $client['billing_frequency']))) ?>
      <?php if (!empty($client['company'])): ?>&nbsp;·&nbsp;<?= e($client['company']) ?><?php endif; ?>
    </p>
  </div>
  <div class="row-actions">
    <?php if ($isActive): ?>
      <button type="button" class="btn btn--primary" data-toggle="invoice-form"
              data-label-open="Generate invoice" data-label-close="Cancel">Generate invoice</button>
    <?php endif; ?>
    <?php if (!empty($openInvoices)): ?>
      <button type="button" class="btn btn--primary" data-toggle="payment-form"
              data-label-open="Record payment" data-label-close="Cancel">Record payment</button>
    <?php endif; ?>
    <a class="btn btn--ghost btn--sm" href="<?= url('/monthly-clients') ?>">&larr; Back to monthly clients</a>
  </div>
</header>

<!-- ---------- Financial summary ---------- -->
<section class="stat-grid">
  <div class="stat">
    <span class="stat__label">Total billed</span>
    <span class="stat__value stat__value--money"><?= e($money($client['total_billed'])) ?></span>
  </div>
  <div class="stat">
    <span class="stat__label">Total paid</span>
    <span class="stat__value stat__value--money"><?= e($money($client['total_paid'])) ?></span>
  </div>
  <div class="stat<?= (float) $client['total_outstanding'] > 0 ? ' stat--warn' : '' ?>">
    <span class="stat__label">Total outstanding</span>
    <span class="stat__value stat__value--money"><?= e($money($client['total_outstanding'])) ?></span>
  </div>
  <div class="stat<?= (float) $client['total_overdue'] > 0 ? ' stat--alert' : '' ?>">
    <span class="stat__label">Total overdue</span>
    <span class="stat__value stat__value--money"><?= e($money($client['total_overdue'])) ?></span>
  </div>
  <div class="stat">
    <span class="stat__label">Invoices</span>
    <span class="stat__value"><?= (int) $client['invoice_count'] ?></span>
  </div>
  <div class="stat">
    <span class="stat__label">Payments</span>
    <span class="stat__value"><?= (int) $client['payment_count'] ?></span>
  </div>
  <div class="stat">
    <span class="stat__label">Next payment amount</span>
    <span class="stat__value stat__value--money"><?= e($money($client['next_due_amount'])) ?></span>
  </div>
  <div class="stat">
    <span class="stat__label">Next payment due</span>
    <span class="stat__value stat__value--sm"><?= e($date($client['next_due_date'])) ?></span>
  </div>
</section>

<!-- ---------- Where this client stands ---------- -->
<?php if ($lifecycle === 'cancelled'): ?>
  <div class="renew-alert renew-alert--danger">
    <span class="renew-alert__icon">🚫</span>
    <span>
      <strong>Cancelled on <?= e($date($client['cancelled_on'])) ?></strong>
      <?php if (!empty($client['cancellation_reason'])): ?> — <?= e($client['cancellation_reason']) ?><?php endif; ?>.
      No further invoices will be raised. Everything below is kept as a permanent record.
    </span>
  </div>
<?php elseif ($lifecycle === 'paused'): ?>
  <div class="renew-alert">
    <span class="renew-alert__icon">⏸️</span>
    <span>
      <strong>Billing paused since <?= e($date($client['paused_on'])) ?></strong>
      <?php if (!empty($client['pause_reason'])): ?> — <?= e($client['pause_reason']) ?><?php endif; ?>.
      No new invoice will be generated<?php if (!empty($client['resume_date'])): ?>; due to resume <?= e($date($client['resume_date'])) ?><?php endif; ?>.
    </span>
  </div>
<?php elseif ((float) $client['total_overdue'] > 0): ?>
  <div class="renew-alert renew-alert--danger">
    <span class="renew-alert__icon">⚠️</span>
    <span>
      <strong><?= e($money($client['total_overdue'])) ?> is overdue.</strong>
      The oldest unpaid invoice fell due on <?= e($date($client['next_due_date'])) ?>.
    </span>
  </div>
<?php elseif ($client['next_billing_date'] !== null && (int) $client['days_to_next_billing'] <= 0): ?>
  <div class="renew-alert">
    <span class="renew-alert__icon">🧾</span>
    <span>
      <strong>A new billing period has started.</strong>
      The period from <?= e($date($client['next_billing_date'])) ?> is not invoiced yet.
    </span>
  </div>
<?php endif; ?>

<!-- ---------- Generate the next invoice ---------- -->
<?php if ($isActive): ?>
  <section class="panel panel--pad" id="invoice-form" hidden>
    <div class="panel__head panel__head--plain">
      <h2 class="panel__title">Generate invoice</h2>
    </div>
    <p class="form-intro">
      This raises the invoice for one billing period. The amounts start from the
      client's rate, standing discount and tax — change them here if this cycle
      is different. Only one invoice can exist per billing period.
    </p>
    <form class="form-grid js-invoice-form" method="post" action="<?= url('/monthly-clients/invoices/create') ?>">
      <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
      <input type="hidden" name="monthly_client_id" value="<?= (int) $client['id'] ?>">
      <!-- Drives the due-date and total previews in the browser; the server recalculates both anyway. -->
      <input type="hidden" class="js-term-days" value="<?= (int) MonthlyClient::TERM_DAYS[(string) $client['payment_terms']] ?>">

      <p class="form-grid__section">Billing period</p>
      <div class="field">
        <label class="form__label" for="period_start">Period starts</label>
        <input class="form__input" type="date" id="period_start" name="period_start" value="<?= e($periodStart) ?>" required>
        <span class="field__hint">Ends <?= e($date($periodEnd)) ?> · one <?= e(strtolower(MonthlyClient::frequencyLabel((string) $client['billing_frequency']))) ?> cycle.</span>
      </div>
      <div class="field">
        <label class="form__label" for="invoice_date">Invoice date</label>
        <input class="form__input js-invoice-date" type="date" id="invoice_date" name="invoice_date" value="<?= e($today) ?>" required>
      </div>
      <div class="field">
        <label class="form__label" for="due_date">Due date</label>
        <input class="form__input js-due-date" type="date" id="due_date" name="due_date" value="<?= e($dueDate) ?>" data-auto="<?= e($dueDate) ?>">
        <span class="field__hint">Invoice date + <?= e(MonthlyClient::termsLabel((string) $client['payment_terms'])) ?>.</span>
      </div>
      <div class="field">
        <label class="form__label" for="inv_status">Invoice status</label>
        <select class="form__input" id="inv_status" name="status">
          <option value="sent">Sent — the client has it</option>
          <option value="draft">Draft — not sent yet</option>
        </select>
        <span class="field__hint">Paid, partially paid and overdue follow from payments and the due date.</span>
      </div>

      <p class="form-grid__section">Service &amp; amounts</p>
      <div class="field">
        <label class="form__label" for="inv_service_name">Service</label>
        <input class="form__input" type="text" id="inv_service_name" name="service_name" value="<?= e($client['service_name']) ?>">
      </div>
      <div class="field field--wide">
        <label class="form__label" for="inv_service_description">Service description</label>
        <textarea class="form__input" id="inv_service_description" name="service_description" rows="2"><?= e($client['service_description'] ?? '') ?></textarea>
      </div>
      <div class="field">
        <label class="form__label" for="recurring_amount">Recurring amount (₹)</label>
        <input class="form__input js-recurring" type="number" id="recurring_amount" name="recurring_amount"
               step="0.01" min="0" value="<?= e($num($preview['recurring'])) ?>" required>
        <span class="field__hint"><?= e($money($client['monthly_amount'])) ?> × <?= (int) MonthlyClient::cycleMonths((string) $client['billing_frequency']) ?> month<?= MonthlyClient::cycleMonths((string) $client['billing_frequency']) === 1 ? '' : 's' ?>.</span>
      </div>
      <div class="field">
        <label class="form__label" for="discount_amount">Discount (₹)</label>
        <input class="form__input js-discount-amount" type="number" id="discount_amount" name="discount_amount"
               step="0.01" min="0" value="<?= e($num($preview['discount'])) ?>">
      </div>
      <div class="field">
        <label class="form__label" for="inv_tax_percent">Tax (%)</label>
        <input class="form__input js-tax-percent" type="number" id="inv_tax_percent" name="tax_percent"
               step="0.01" min="0" value="<?= e($num($client['tax_percent'])) ?>">
      </div>
      <div class="field">
        <label class="form__label">Total amount</label>
        <p class="form__input form__input--static js-invoice-total"><?= e($money($preview['total'])) ?></p>
        <span class="field__hint">Recurring − discount + tax.</span>
      </div>
      <div class="field field--wide">
        <label class="form__label" for="inv_notes">Invoice notes</label>
        <textarea class="form__input" id="inv_notes" name="notes" rows="2" placeholder="Anything to print on this invoice"></textarea>
      </div>
      <div class="field field--action">
        <button type="submit" class="btn btn--primary">Generate invoice</button>
        <button type="button" class="btn btn--ghost" data-toggle="invoice-form"
                data-label-open="Generate invoice" data-label-close="Cancel">Cancel</button>
      </div>
    </form>
  </section>
<?php endif; ?>

<!-- ---------- Record a payment ---------- -->
<?php if (!empty($openInvoices)): ?>
  <section class="panel panel--pad" id="payment-form" hidden>
    <div class="panel__head panel__head--plain">
      <h2 class="panel__title">Record a payment</h2>
    </div>
    <p class="form-intro">
      Pay an invoice in full or in parts — a payment can never be more than what
      is still outstanding on it. The balance and the invoice's status update
      themselves, and a receipt is created straight away.
    </p>
    <form class="form-grid js-payment-form" method="post" action="<?= url('/monthly-clients/payments/create') ?>">
      <input type="hidden" name="csrf" value="<?= e($csrf) ?>">

      <div class="field">
        <label class="form__label" for="invoice_id">Invoice</label>
        <select class="form__input js-invoice-select" id="invoice_id" name="invoice_id" required>
          <?php foreach ($openInvoices as $i): ?>
            <option value="<?= (int) $i['id'] ?>" data-balance="<?= e($num($i['balance_due'])) ?>"
                    <?= $payInvoiceId === (int) $i['id'] ? ' selected' : '' ?>>
              <?= e($i['invoice_number']) ?> — <?= e($i['period_label']) ?> — <?= e($money($i['balance_due'])) ?> outstanding
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label class="form__label" for="payment_date">Payment date</label>
        <input class="form__input" type="date" id="payment_date" name="payment_date" value="<?= e($today) ?>" required>
      </div>
      <div class="field">
        <label class="form__label" for="amount">Payment amount (₹)</label>
        <input class="form__input js-pay-amount" type="number" id="amount" name="amount" step="0.01" min="0.01" required>
        <span class="field__hint js-pay-hint">Outstanding on the selected invoice.</span>
      </div>
      <div class="field">
        <label class="form__label" for="method">Payment method</label>
        <select class="form__input" id="method" name="method">
          <?php foreach ($payMethods as $m): ?>
            <option value="<?= e($m) ?>"<?= $m === $client['payment_method'] ? ' selected' : '' ?>><?= e(MonthlyPayment::methodLabel($m)) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label class="form__label" for="reference">Transaction / reference number</label>
        <input class="form__input" type="text" id="reference" name="reference" placeholder="UPI ref / txn id / cheque no">
      </div>
      <div class="field field--wide">
        <label class="form__label" for="pay_notes">Payment notes</label>
        <textarea class="form__input" id="pay_notes" name="notes" rows="2" placeholder="Anything worth remembering about this payment"></textarea>
      </div>
      <div class="field field--action">
        <button type="submit" class="btn btn--primary">Record payment &amp; create receipt</button>
        <button type="button" class="btn btn--ghost" data-toggle="payment-form"
                data-label-open="Record payment" data-label-close="Cancel">Cancel</button>
      </div>
    </form>
  </section>
<?php endif; ?>

<!-- ---------- Subscription controls ---------- -->
<section class="panel panel--pad">
  <div class="panel__head panel__head--plain">
    <h2 class="panel__title">Subscription</h2>
    <div class="row-actions">
      <?php if ($lifecycle === 'active'): ?>
        <button type="button" class="btn btn--sm btn--ghost" data-toggle="pause-form"
                data-label-open="Pause billing" data-label-close="Cancel">Pause billing</button>
        <button type="button" class="btn btn--sm btn--danger" data-toggle="cancel-form"
                data-label-open="Cancel client" data-label-close="Close">Cancel client</button>
      <?php elseif ($lifecycle === 'paused'): ?>
        <button type="button" class="btn btn--sm btn--primary" data-toggle="resume-form"
                data-label-open="Resume billing" data-label-close="Cancel">Resume billing</button>
        <button type="button" class="btn btn--sm btn--danger" data-toggle="cancel-form"
                data-label-open="Cancel client" data-label-close="Close">Cancel client</button>
      <?php else: ?>
        <button type="button" class="btn btn--sm btn--primary" data-toggle="reactivate-form"
                data-label-open="Reactivate client" data-label-close="Cancel">Reactivate client</button>
      <?php endif; ?>
    </div>
  </div>

  <div class="stat-grid stat-grid--profile">
    <div class="stat"><span class="stat__label">Status</span><span class="stat__value stat__value--sm">
      <span class="badge badge--mc-<?= e($status) ?>"><?= e($client['status_label']) ?></span>
    </span></div>
    <div class="stat"><span class="stat__label">Next billing date</span><span class="stat__value stat__value--sm">
      <?= $client['next_billing_date'] !== null ? e($date($client['next_billing_date'])) : 'Billing stopped' ?>
    </span></div>
    <div class="stat"><span class="stat__label">Next billing period</span><span class="stat__value stat__value--sm">
      <?= $client['next_billing_date'] !== null ? e($date($client['next_billing_date']) . ' – ' . $date($client['next_period_end'])) : '—' ?>
    </span></div>
    <div class="stat"><span class="stat__label">Amount per cycle</span><span class="stat__value stat__value--sm"><?= e($money($client['cycle_amount'])) ?></span></div>
    <?php if ($lifecycle === 'paused'): ?>
      <div class="stat"><span class="stat__label">Paused on</span><span class="stat__value stat__value--sm"><?= e($date($client['paused_on'])) ?></span></div>
      <div class="stat"><span class="stat__label">Pause reason</span><span class="stat__value stat__value--sm"><?= e($client['pause_reason'] ?: '—') ?></span></div>
      <div class="stat"><span class="stat__label">Planned resume</span><span class="stat__value stat__value--sm"><?= e($date($client['resume_date'])) ?></span></div>
    <?php elseif ($lifecycle === 'cancelled'): ?>
      <div class="stat"><span class="stat__label">Cancelled on</span><span class="stat__value stat__value--sm"><?= e($date($client['cancelled_on'])) ?></span></div>
      <div class="stat"><span class="stat__label">Cancellation reason</span><span class="stat__value stat__value--sm"><?= e($client['cancellation_reason'] ?: '—') ?></span></div>
      <div class="stat"><span class="stat__label">Cancellation notes</span><span class="stat__value stat__value--sm"><?= e($client['cancellation_notes'] ?: '—') ?></span></div>
    <?php endif; ?>
  </div>

  <!-- Pause -->
  <form class="form-grid" id="pause-form" method="post" action="<?= url('/monthly-clients/pause') ?>" hidden>
    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
    <input type="hidden" name="id" value="<?= (int) $client['id'] ?>">
    <p class="form-grid__section">Pause billing</p>
    <div class="field">
      <label class="form__label" for="paused_on">Pause date</label>
      <input class="form__input" type="date" id="paused_on" name="paused_on" value="<?= e($today) ?>" required>
    </div>
    <div class="field">
      <label class="form__label" for="resume_date">Planned resume date</label>
      <input class="form__input" type="date" id="resume_date" name="resume_date">
      <span class="field__hint">A reminder for you — resuming is still a deliberate action.</span>
    </div>
    <div class="field field--wide">
      <label class="form__label" for="pause_reason">Reason</label>
      <input class="form__input" type="text" id="pause_reason" name="pause_reason" placeholder="e.g. Client on a seasonal break">
    </div>
    <div class="field field--wide">
      <label class="form__label" for="pause_notes">Notes</label>
      <textarea class="form__input" id="pause_notes" name="pause_notes" rows="2"></textarea>
    </div>
    <div class="field field--action">
      <button type="submit" class="btn btn--primary">Pause billing</button>
      <button type="button" class="btn btn--ghost" data-toggle="pause-form"
              data-label-open="Pause billing" data-label-close="Cancel">Cancel</button>
    </div>
  </form>

  <!-- Resume -->
  <form class="form-grid" id="resume-form" method="post" action="<?= url('/monthly-clients/resume') ?>" hidden>
    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
    <input type="hidden" name="id" value="<?= (int) $client['id'] ?>">
    <p class="form-grid__section">Resume billing</p>
    <div class="field">
      <label class="form__label" for="resumed_on">Resume date</label>
      <input class="form__input" type="date" id="resumed_on" name="resumed_on" value="<?= e($today) ?>" required>
    </div>
    <div class="field">
      <label class="form__label" for="next_billing_date">Billing picks up from</label>
      <input class="form__input" type="date" id="next_billing_date" name="next_billing_date" value="<?= e((string) ($client['scheduled_billing_date'] ?: $today)) ?>">
      <span class="field__hint">Left as it was when you paused. Move it forward to skip the paused periods.</span>
    </div>
    <div class="field field--action">
      <button type="submit" class="btn btn--primary">Resume billing</button>
      <button type="button" class="btn btn--ghost" data-toggle="resume-form"
              data-label-open="Resume billing" data-label-close="Cancel">Cancel</button>
    </div>
  </form>

  <!-- Cancel -->
  <form class="form-grid" id="cancel-form" method="post" action="<?= url('/monthly-clients/cancel') ?>" hidden
        onsubmit="return confirm('Cancel <?= e($client['client_name']) ?>? Future billing stops. Every invoice, payment and receipt is kept.')">
    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
    <input type="hidden" name="id" value="<?= (int) $client['id'] ?>">
    <p class="form-grid__section">Cancel this client</p>
    <div class="field field--wide">
      <p class="form-intro form-intro--tight">
        Cancelling stops all future billing. Nothing is deleted — every invoice,
        payment, receipt and outstanding balance stays exactly as it is.
      </p>
    </div>
    <div class="field">
      <label class="form__label" for="cancelled_on">Cancellation date</label>
      <input class="form__input" type="date" id="cancelled_on" name="cancelled_on" value="<?= e($today) ?>" required>
    </div>
    <div class="field">
      <label class="form__label" for="cancellation_reason">Cancellation reason</label>
      <input class="form__input" type="text" id="cancellation_reason" name="cancellation_reason" placeholder="e.g. Contract completed">
    </div>
    <div class="field field--wide">
      <label class="form__label" for="cancellation_notes">Notes</label>
      <textarea class="form__input" id="cancellation_notes" name="cancellation_notes" rows="2"></textarea>
    </div>
    <div class="field field--action">
      <button type="submit" class="btn btn--danger">Cancel this client</button>
      <button type="button" class="btn btn--ghost" data-toggle="cancel-form"
              data-label-open="Cancel client" data-label-close="Close">Close</button>
    </div>
  </form>

  <!-- Reactivate -->
  <form class="form-grid" id="reactivate-form" method="post" action="<?= url('/monthly-clients/reactivate') ?>" hidden>
    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
    <input type="hidden" name="id" value="<?= (int) $client['id'] ?>">
    <p class="form-grid__section">Reactivate this client</p>
    <div class="field">
      <label class="form__label" for="re_next_billing_date">Billing starts again from</label>
      <input class="form__input" type="date" id="re_next_billing_date" name="next_billing_date" value="<?= e($today) ?>">
    </div>
    <div class="field field--action">
      <button type="submit" class="btn btn--primary">Reactivate</button>
      <button type="button" class="btn btn--ghost" data-toggle="reactivate-form"
              data-label-open="Reactivate client" data-label-close="Cancel">Cancel</button>
    </div>
  </form>
</section>

<!-- ---------- Details (read-only + edit form) ---------- -->
<section class="panel panel--pad">
  <div class="panel__head panel__head--plain">
    <h2 class="panel__title">Client details</h2>
    <button type="button" class="btn btn--sm btn--ghost" data-toggle="details-form"
            data-label-open="Edit details" data-label-close="Cancel">Edit details</button>
  </div>

  <div id="details-form-view">
    <p class="form-grid__section" style="padding:0 22px">Client</p>
    <div class="stat-grid stat-grid--profile">
      <div class="stat"><span class="stat__label">Client name</span><span class="stat__value stat__value--sm"><?= e($client['client_name']) ?></span></div>
      <div class="stat"><span class="stat__label">Company</span><span class="stat__value stat__value--sm"><?= e($client['company'] ?: '—') ?></span></div>
      <div class="stat"><span class="stat__label">Email</span><span class="stat__value stat__value--sm"><?= e($client['email'] ?: '—') ?></span></div>
      <div class="stat"><span class="stat__label">Mobile</span><span class="stat__value stat__value--sm"><?= e($client['mobile'] ?: '—') ?></span></div>
      <div class="stat"><span class="stat__label">Billing address</span><span class="stat__value stat__value--sm"><?= e($client['billing_address'] ?: '—') ?></span></div>
      <div class="stat"><span class="stat__label">Notes</span><span class="stat__value stat__value--sm"><?= e($client['notes'] ?: '—') ?></span></div>
    </div>

    <p class="form-grid__section" style="padding:0 22px">Service &amp; billing</p>
    <div class="stat-grid stat-grid--profile">
      <div class="stat"><span class="stat__label">Service</span><span class="stat__value stat__value--sm"><?= e($client['service_name']) ?></span></div>
      <div class="stat"><span class="stat__label">Description</span><span class="stat__value stat__value--sm"><?= e($client['service_description'] ?: '—') ?></span></div>
      <div class="stat"><span class="stat__label">Monthly amount</span><span class="stat__value stat__value--sm"><?= e($money($client['monthly_amount'])) ?></span></div>
      <div class="stat"><span class="stat__label">Billing frequency</span><span class="stat__value stat__value--sm"><?= e(MonthlyClient::frequencyLabel((string) $client['billing_frequency'])) ?></span></div>
      <div class="stat"><span class="stat__label">Discount</span><span class="stat__value stat__value--sm">
        <?php if ($client['discount_type'] === 'percent'): ?><?= e($num($client['discount_value'])) ?>%
        <?php elseif ($client['discount_type'] === 'amount'): ?><?= e($money($client['discount_value'])) ?>
        <?php else: ?>—<?php endif; ?>
      </span></div>
      <div class="stat"><span class="stat__label">Tax</span><span class="stat__value stat__value--sm"><?= (float) $client['tax_percent'] > 0 ? e($num($client['tax_percent'])) . '%' : '—' ?></span></div>
      <div class="stat"><span class="stat__label">Payment method</span><span class="stat__value stat__value--sm"><?= e(MonthlyClient::methodLabel((string) $client['payment_method'])) ?></span></div>
      <div class="stat"><span class="stat__label">Payment terms</span><span class="stat__value stat__value--sm"><?= e(MonthlyClient::termsLabel((string) $client['payment_terms'])) ?></span></div>
    </div>

    <p class="form-grid__section" style="padding:0 22px">Contract</p>
    <div class="stat-grid stat-grid--profile">
      <div class="stat"><span class="stat__label">Contract start</span><span class="stat__value stat__value--sm"><?= e($date($client['start_date'])) ?></span></div>
      <div class="stat"><span class="stat__label">Contract end</span><span class="stat__value stat__value--sm"><?= e($date($client['contract_end_date'])) ?></span></div>
      <div class="stat"><span class="stat__label">Renewal date</span><span class="stat__value stat__value--sm"><?= e($date($client['renewal_date'])) ?></span></div>
      <div class="stat"><span class="stat__label">Contract status</span><span class="stat__value stat__value--sm"><?= e($client['contract_status']) ?></span></div>
      <div class="stat"><span class="stat__label">Contract notes</span><span class="stat__value stat__value--sm"><?= e($client['contract_notes'] ?: '—') ?></span></div>
    </div>
  </div>

  <form class="form-grid js-monthly-form" id="details-form" method="post" action="<?= url('/monthly-clients/update') ?>" hidden>
    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
    <input type="hidden" name="id" value="<?= (int) $client['id'] ?>">

    <p class="form-grid__section">Client details</p>
    <div class="field">
      <label class="form__label" for="e_client_name">Client name</label>
      <input class="form__input" type="text" id="e_client_name" name="client_name" value="<?= e($client['client_name']) ?>" required>
    </div>
    <div class="field">
      <label class="form__label" for="e_company">Company name</label>
      <input class="form__input" type="text" id="e_company" name="company" value="<?= e($client['company'] ?? '') ?>">
    </div>
    <div class="field">
      <label class="form__label" for="e_email">Email</label>
      <input class="form__input" type="email" id="e_email" name="email" value="<?= e($client['email'] ?? '') ?>">
    </div>
    <div class="field">
      <label class="form__label" for="e_mobile">Mobile number</label>
      <input class="form__input" type="text" id="e_mobile" name="mobile" value="<?= e($client['mobile'] ?? '') ?>" pattern="\d{10}" maxlength="10">
    </div>
    <div class="field field--wide">
      <label class="form__label" for="e_billing_address">Billing address</label>
      <textarea class="form__input" id="e_billing_address" name="billing_address" rows="2"><?= e($client['billing_address'] ?? '') ?></textarea>
    </div>

    <p class="form-grid__section">Service</p>
    <div class="field">
      <label class="form__label" for="e_service_name">Service name</label>
      <input class="form__input" type="text" id="e_service_name" name="service_name" value="<?= e($client['service_name']) ?>" required>
    </div>
    <div class="field field--wide">
      <label class="form__label" for="e_service_description">Service description</label>
      <textarea class="form__input" id="e_service_description" name="service_description" rows="2"><?= e($client['service_description'] ?? '') ?></textarea>
    </div>

    <p class="form-grid__section">Billing</p>
    <div class="field">
      <label class="form__label" for="e_monthly_amount">Monthly amount (₹)</label>
      <input class="form__input js-amount" type="number" id="e_monthly_amount" name="monthly_amount" step="0.01" min="0"
             value="<?= e($num($client['monthly_amount'])) ?>" required>
      <span class="field__hint">Changing this only affects invoices raised from now on.</span>
    </div>
    <div class="field">
      <label class="form__label" for="e_billing_frequency">Billing frequency</label>
      <select class="form__input js-frequency" id="e_billing_frequency" name="billing_frequency">
        <?php foreach ($frequencies as $f): ?>
          <option value="<?= e($f) ?>"<?= $client['billing_frequency'] === $f ? ' selected' : '' ?>><?= e(MonthlyClient::frequencyLabel($f)) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label class="form__label" for="e_discount_type">Discount</label>
      <select class="form__input js-discount-type" id="e_discount_type" name="discount_type">
        <option value="none"<?= $client['discount_type'] === 'none' ? ' selected' : '' ?>>No discount</option>
        <option value="percent"<?= $client['discount_type'] === 'percent' ? ' selected' : '' ?>>Percentage of the invoice</option>
        <option value="amount"<?= $client['discount_type'] === 'amount' ? ' selected' : '' ?>>Flat amount off</option>
      </select>
    </div>
    <div class="field js-discount-value"<?= $client['discount_type'] === 'none' ? ' hidden' : '' ?>>
      <label class="form__label" for="e_discount_value">Discount value</label>
      <input class="form__input js-discount" type="number" id="e_discount_value" name="discount_value" step="0.01" min="0"
             value="<?= e($num($client['discount_value'])) ?>">
    </div>
    <div class="field">
      <label class="form__label" for="e_tax_percent">Tax (%)</label>
      <input class="form__input js-tax" type="number" id="e_tax_percent" name="tax_percent" step="0.01" min="0"
             value="<?= e($num($client['tax_percent'])) ?>">
    </div>
    <div class="field">
      <label class="form__label" for="e_payment_method">Payment method</label>
      <select class="form__input" id="e_payment_method" name="payment_method">
        <?php foreach ($methods as $m): ?>
          <option value="<?= e($m) ?>"<?= $client['payment_method'] === $m ? ' selected' : '' ?>><?= e(MonthlyClient::methodLabel($m)) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label class="form__label" for="e_payment_terms">Payment terms</label>
      <select class="form__input" id="e_payment_terms" name="payment_terms">
        <?php foreach ($terms as $t): ?>
          <option value="<?= e($t) ?>"<?= $client['payment_terms'] === $t ? ' selected' : '' ?>><?= e(MonthlyClient::termsLabel($t)) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field field--wide">
      <p class="form-intro form-intro--tight">Each invoice will come to <strong class="js-preview">—</strong>.</p>
    </div>

    <p class="form-grid__section">Contract</p>
    <div class="field">
      <label class="form__label" for="e_start_date">Contract start date</label>
      <input class="form__input" type="date" id="e_start_date" name="start_date" value="<?= e((string) $client['start_date']) ?>" required>
    </div>
    <div class="field">
      <label class="form__label" for="e_contract_end_date">Contract end date</label>
      <input class="form__input" type="date" id="e_contract_end_date" name="contract_end_date" value="<?= e((string) ($client['contract_end_date'] ?? '')) ?>">
    </div>
    <div class="field">
      <label class="form__label" for="e_renewal_date">Renewal date</label>
      <input class="form__input" type="date" id="e_renewal_date" name="renewal_date" value="<?= e((string) ($client['renewal_date'] ?? '')) ?>">
    </div>
    <div class="field field--wide">
      <label class="form__label" for="e_contract_notes">Contract notes</label>
      <textarea class="form__input" id="e_contract_notes" name="contract_notes" rows="2"><?= e($client['contract_notes'] ?? '') ?></textarea>
    </div>
    <div class="field field--wide">
      <label class="form__label" for="e_notes">Notes</label>
      <textarea class="form__input" id="e_notes" name="notes" rows="2"><?= e($client['notes'] ?? '') ?></textarea>
    </div>
    <div class="field field--action">
      <button type="submit" class="btn btn--primary">Save details</button>
      <button type="button" class="btn btn--ghost" data-toggle="details-form"
              data-label-open="Edit details" data-label-close="Cancel">Cancel</button>
    </div>
  </form>
</section>

<!-- ---------- Invoice history ---------- -->
<section class="panel" id="invoices">
  <div class="panel__head">
    <h2 class="panel__title">Invoices</h2>
    <span class="panel__count">
      <?= count($invoices) ?> raised<?= $paidInvoices > 0 ? ' · ' . $paidInvoices . ' settled' : '' ?>
    </span>
  </div>

  <?php if (empty($invoices)): ?>
    <p class="empty">
      No invoices yet.
      <?php if ($isActive): ?>
        Use <strong>Generate invoice</strong> above to raise the first billing cycle.
      <?php else: ?>
        Billing is <?= e($lifecycle) ?>, so no invoice can be raised right now.
      <?php endif; ?>
    </p>
  <?php else: ?>
    <table class="table table--monthly">
      <thead>
        <tr>
          <th>Invoice</th><th>Billing period</th><th>Invoice date</th><th>Due date</th>
          <th class="ta-right">Total</th><th class="ta-right">Paid</th><th class="ta-right">Balance</th>
          <th>Status</th><th class="ta-right">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($invoices as $i): ?>
          <tr class="row--<?= e($i['display_status']) ?>">
            <td><a href="<?= url('/monthly-clients/invoice') ?>?id=<?= (int) $i['id'] ?>"><strong><?= e($i['invoice_number']) ?></strong></a></td>
            <td class="muted"><?= e($i['period_label']) ?></td>
            <td><?= e($date($i['invoice_date'])) ?></td>
            <td>
              <?= e($date($i['due_date'])) ?>
              <?php if ($i['is_overdue']): ?>
                <span class="days-pill days-pill--expired"><?= (int) $i['days_overdue'] ?> day<?= (int) $i['days_overdue'] === 1 ? '' : 's' ?> overdue</span>
              <?php endif; ?>
            </td>
            <td class="ta-right"><?= e($money($i['total_amount'])) ?></td>
            <td class="ta-right amount-paid"><?= e($money($i['amount_paid'])) ?></td>
            <td class="ta-right<?= $i['balance_due'] > 0 ? ' amount-due' : '' ?>"><?= e($money($i['balance_due'])) ?></td>
            <td>
              <span class="badge badge--inv-<?= e($i['display_status']) ?>"><?= e($i['status_label']) ?></span>
              <?php if ($i['is_partial'] && $i['display_status'] === 'overdue'): ?>
                <span class="badge badge--inv-partially_paid">Part paid</span>
              <?php endif; ?>
            </td>
            <td class="ta-right">
              <div class="row-actions">
                <a class="btn btn--sm btn--ghost" href="<?= url('/monthly-clients/invoice') ?>?id=<?= (int) $i['id'] ?>">Open</a>
                <?php if ($i['status'] === 'draft'): ?>
                  <form method="post" action="<?= url('/monthly-clients/invoices/status') ?>" class="inline-form">
                    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                    <input type="hidden" name="id" value="<?= (int) $i['id'] ?>">
                    <input type="hidden" name="status" value="sent">
                    <button type="submit" class="btn btn--sm btn--primary">Mark sent</button>
                  </form>
                <?php endif; ?>
                <?php if ($i['display_status'] !== 'cancelled' && (float) $i['amount_paid'] <= 0): ?>
                  <form method="post" action="<?= url('/monthly-clients/invoices/status') ?>" class="inline-form"
                        onsubmit="return confirm('Cancel invoice <?= e($i['invoice_number']) ?>? It stays in the history, marked cancelled.')">
                    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                    <input type="hidden" name="id" value="<?= (int) $i['id'] ?>">
                    <input type="hidden" name="status" value="cancelled">
                    <button type="submit" class="btn btn--sm btn--danger">Cancel</button>
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

<!-- ---------- Payment history ---------- -->
<section class="panel" id="payments">
  <div class="panel__head">
    <h2 class="panel__title">Payments &amp; receipts</h2>
    <span class="panel__count"><?= count($payments) ?> payment<?= count($payments) === 1 ? '' : 's' ?> · <?= e($money($client['total_paid'])) ?></span>
  </div>

  <?php if (empty($payments)): ?>
    <p class="empty">No payments recorded yet. Each one you record produces its own receipt, kept here permanently.</p>
  <?php else: ?>
    <table class="table table--monthly">
      <thead>
        <tr>
          <th>Receipt</th><th>Invoice</th><th>Paid on</th><th>Method</th><th>Reference</th>
          <th class="ta-right">Amount</th><th class="ta-right">Balance after</th>
          <th>Notes</th><th>Recorded by</th><th class="ta-right">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($payments as $p): ?>
          <tr>
            <td><a href="<?= url('/monthly-clients/receipt') ?>?id=<?= (int) $p['id'] ?>"><strong><?= e($p['receipt_number']) ?></strong></a></td>
            <td class="muted"><?= e($p['invoice_number']) ?></td>
            <td><?= e($date($p['payment_date'])) ?></td>
            <td class="muted"><?= e(MonthlyPayment::methodLabel((string) $p['method'])) ?></td>
            <td class="muted"><?= e($p['reference'] ?: '—') ?></td>
            <td class="ta-right amount-paid"><?= e($money($p['amount'])) ?></td>
            <td class="ta-right<?= (float) $p['balance_after'] > 0 ? ' amount-due' : '' ?>"><?= e($money($p['balance_after'])) ?></td>
            <td class="muted"><?= e($p['notes'] ?: '—') ?></td>
            <td class="muted"><?= e($p['recorded_by'] ?: '—') ?></td>
            <td class="ta-right"><a class="btn btn--sm btn--ghost" href="<?= url('/monthly-clients/receipt') ?>?id=<?= (int) $p['id'] ?>">Receipt</a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</section>

<!-- ---------- Pause history ---------- -->
<?php if (!empty($pauses)): ?>
  <section class="panel">
    <div class="panel__head">
      <h2 class="panel__title">Pause history</h2>
      <span class="panel__count"><?= count($pauses) ?> pause<?= count($pauses) === 1 ? '' : 's' ?></span>
    </div>
    <table class="table">
      <thead>
        <tr><th>Paused on</th><th>Reason</th><th>Planned resume</th><th>Actually resumed</th><th>Notes</th><th>Recorded by</th></tr>
      </thead>
      <tbody>
        <?php foreach ($pauses as $p): ?>
          <tr>
            <td><strong><?= e($date($p['paused_on'])) ?></strong></td>
            <td><?= e($p['reason'] ?: '—') ?></td>
            <td class="muted"><?= e($date($p['resume_date'])) ?></td>
            <td class="muted"><?= $p['resumed_on'] ? e($date($p['resumed_on'])) : '<span class="badge badge--mc-paused">Still paused</span>' ?></td>
            <td class="muted"><?= e($p['notes'] ?: '—') ?></td>
            <td class="muted"><?= e($p['recorded_by'] ?: '—') ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </section>
<?php endif; ?>

<script src="<?= asset('js/monthly.js') ?>"></script>
