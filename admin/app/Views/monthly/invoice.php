<?php
/**
 * A monthly client's invoice for one billing cycle, rendered as a standalone,
 * print-ready page — "Print / Save as PDF" uses the browser's own print-to-PDF,
 * the same pattern the Billing module already uses, so no PDF library is needed.
 *
 * @var array $invoice @var array $payments
 */

use App\Models\MonthlyClient;
use App\Models\MonthlyPayment;

$money = static fn($n): string => '₹' . number_format((float) $n, 2);
$date  = static fn(?string $d): string => $d ? date('d M Y', strtotime($d)) : '—';

$balance = (float) $invoice['balance_due'];
$paid    = (float) $invoice['amount_paid'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?= e($invoice['invoice_number']) ?> — <?= e(APP_NAME) ?></title>
<style>
  * { box-sizing: border-box; }
  body { font-family: Arial, Helvetica, sans-serif; color: #1e293b; margin: 0; padding: 28px; font-size: 13px; background: #f1f5f9; }
  .sheet { max-width: 780px; margin: 0 auto; background: #fff; padding: 40px; border-radius: 10px; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
  .toolbar { max-width: 780px; margin: 0 auto 16px; }
  .toolbar button { font-family: inherit; font-size: 13px; padding: 9px 16px; border-radius: 8px; border: 1px solid #2563eb;
    background: #2563eb; color: #fff; cursor: pointer; }
  .toolbar a { font-family: inherit; font-size: 13px; padding: 9px 16px; border-radius: 8px; border: 1px solid #cbd5e1;
    color: #1e293b; text-decoration: none; margin-left: 8px; }

  .bill-head { display: flex; justify-content: space-between; align-items: flex-start; padding-bottom: 20px; border-bottom: 2px solid #1e293b; margin-bottom: 24px; }
  .bill-head h1 { font-size: 22px; margin: 0 0 8px; letter-spacing: .02em; }
  .bill-head .bill-no { color: #475569; font-size: 12.5px; line-height: 1.8; }
  .bill-head .bill-no strong { color: #1e293b; }
  .biz__name { font-size: 15px; font-weight: 700; margin-bottom: 8px; }
  .biz__line { font-size: 12px; color: #475569; line-height: 2; white-space: nowrap; }
  .biz__line strong { display: inline-block; min-width: 50px; color: #1e293b; font-weight: 600; }

  .status-stamp { display: inline-block; padding: 4px 12px; border-radius: 999px; font-size: 11.5px; font-weight: 700;
    text-transform: uppercase; letter-spacing: .05em; margin-top: 8px; }
  .status-stamp--paid { background: #ecfdf5; color: #047857; }
  .status-stamp--overdue { background: #fef2f2; color: #b91c1c; }
  .status-stamp--partially_paid { background: #fffbeb; color: #b45309; }
  .status-stamp--cancelled { background: #f1f5f9; color: #64748b; }
  .status-stamp--draft { background: #f1f5f9; color: #64748b; }
  .status-stamp--sent { background: #eff6ff; color: #1d4ed8; }

  .parties { display: flex; justify-content: space-between; gap: 24px; margin-bottom: 26px; }
  .party h2 { font-size: 10.5px; text-transform: uppercase; letter-spacing: .06em; color: #94a3b8; margin: 0 0 6px; }
  .party .name { font-size: 14.5px; font-weight: 700; margin-bottom: 2px; }
  .party .line { font-size: 12.5px; color: #475569; line-height: 1.6; }
  .meta .line { font-size: 12.5px; color: #475569; line-height: 2; white-space: nowrap; }
  .meta .line strong { display: inline-block; min-width: 118px; color: #1e293b; }

  table.items { width: 100%; border-collapse: collapse; margin-bottom: 22px; }
  table.items th { text-align: left; background: #f1f5f9; font-size: 11px; text-transform: uppercase; letter-spacing: .04em;
    color: #475569; padding: 9px 12px; }
  table.items th.ta-right, table.items td.ta-right { text-align: right; }
  table.items td { padding: 10px 12px; border-bottom: 1px solid #e2e8f0; font-size: 13px; }
  table.items .desc { color: #64748b; font-size: 12px; margin-top: 3px; }

  .totals { margin-left: auto; width: 320px; }
  .totals .row { display: flex; justify-content: space-between; padding: 7px 0; font-size: 13px; color: #475569; }
  .totals .row.grand { border-top: 2px solid #1e293b; margin-top: 4px; padding-top: 12px; font-size: 15px; font-weight: 700; color: #1e293b; }
  .totals .row.due { color: #b91c1c; font-weight: 700; }
  .totals .row.settled { color: #047857; font-weight: 700; }

  table.paylog { width: 100%; border-collapse: collapse; margin-top: 30px; }
  table.paylog caption { text-align: left; font-size: 11px; text-transform: uppercase; letter-spacing: .06em;
    color: #94a3b8; padding-bottom: 8px; }
  table.paylog th { text-align: left; font-size: 11px; text-transform: uppercase; letter-spacing: .04em; color: #475569;
    background: #f8fafc; padding: 8px 10px; }
  table.paylog td { padding: 8px 10px; border-bottom: 1px solid #e2e8f0; font-size: 12.5px; }
  table.paylog .ta-right { text-align: right; }

  .notes { margin-top: 26px; padding-top: 16px; border-top: 1px dashed #cbd5e1; font-size: 12.5px; color: #64748b; }
  .footer { margin-top: 34px; text-align: center; font-size: 11.5px; color: #94a3b8; }

  @media print {
    body { background: #fff; padding: 0; }
    .toolbar { display: none; }
    .sheet { box-shadow: none; border-radius: 0; max-width: none; padding: 0; }
  }
</style>
</head>
<body>

  <div class="toolbar">
    <button type="button" onclick="window.print()">Print / Save as PDF</button>
    <a href="<?= url('/monthly-clients/view') ?>?id=<?= (int) $invoice['monthly_client_id'] ?>">&larr; Back to client</a>
    <a href="<?= url('/monthly-clients') ?>">All monthly clients</a>
  </div>

  <div class="sheet">
    <div class="bill-head">
      <div>
        <h1>Invoice</h1>
        <div class="bill-no">
          <div><strong>Invoice No:</strong> <?= e($invoice['invoice_number']) ?></div>
          <div><strong>Invoice Date:</strong> <?= e($date($invoice['invoice_date'])) ?></div>
          <div><strong>Due Date:</strong> <?= e($date($invoice['due_date'])) ?></div>
        </div>
        <span class="status-stamp status-stamp--<?= e($invoice['display_status']) ?>"><?= e($invoice['status_label']) ?></span>
      </div>
      <div class="biz">
        <div class="biz__name">Tejas Mehar</div>
        <div class="biz__line"><strong>Mobile:</strong> +91 7821096438</div>
        <div class="biz__line"><strong>Email:</strong> scalewithtejas@gmail.com</div>
        <div class="biz__line"><strong>Website:</strong> www.tejasmehar.in</div>
      </div>
    </div>

    <div class="parties">
      <div class="party">
        <h2>Billed to</h2>
        <div class="name"><?= e($invoice['client_name']) ?></div>
        <?php if (!empty($invoice['company'])): ?><div class="line"><?= e($invoice['company']) ?></div><?php endif; ?>
        <?php if (!empty($invoice['billing_address'])): ?><div class="line"><?= nl2br(e($invoice['billing_address'])) ?></div><?php endif; ?>
        <?php if (!empty($invoice['email'])): ?><div class="line"><?= e($invoice['email']) ?></div><?php endif; ?>
        <?php if (!empty($invoice['mobile'])): ?><div class="line"><?= e($invoice['mobile']) ?></div><?php endif; ?>
      </div>
      <div class="meta">
        <div class="line"><strong>Billing period:</strong> <?= e($invoice['period_label']) ?></div>
        <div class="line"><strong>Billing frequency:</strong> <?= e(MonthlyClient::frequencyLabel((string) $invoice['billing_frequency'])) ?></div>
        <div class="line"><strong>Payment method:</strong> <?= e(MonthlyClient::methodLabel((string) $invoice['payment_method'])) ?></div>
        <div class="line"><strong>Payment terms:</strong> <?= e(MonthlyClient::termsLabel((string) $invoice['payment_terms'])) ?></div>
      </div>
    </div>

    <table class="items">
      <thead>
        <tr><th>Service</th><th>Billing period</th><th class="ta-right">Amount</th></tr>
      </thead>
      <tbody>
        <tr>
          <td>
            <?= e($invoice['service_name']) ?>
            <?php if (!empty($invoice['service_description'])): ?>
              <div class="desc"><?= nl2br(e($invoice['service_description'])) ?></div>
            <?php endif; ?>
          </td>
          <td><?= e($invoice['period_label']) ?></td>
          <td class="ta-right"><?= e($money($invoice['recurring_amount'])) ?></td>
        </tr>
      </tbody>
    </table>

    <div class="totals">
      <div class="row"><span>Recurring amount</span><span><?= e($money($invoice['recurring_amount'])) ?></span></div>
      <?php if ((float) $invoice['discount_amount'] > 0): ?>
        <div class="row"><span>Discount</span><span>− <?= e($money($invoice['discount_amount'])) ?></span></div>
      <?php endif; ?>
      <?php if ((float) $invoice['tax_amount'] > 0): ?>
        <div class="row"><span>Tax (<?= e(rtrim(rtrim(number_format((float) $invoice['tax_percent'], 2, '.', ''), '0'), '.')) ?>%)</span><span><?= e($money($invoice['tax_amount'])) ?></span></div>
      <?php endif; ?>
      <div class="row grand"><span>Total amount</span><span><?= e($money($invoice['total_amount'])) ?></span></div>
      <div class="row"><span>Amount paid</span><span><?= e($money($paid)) ?></span></div>
      <div class="row <?= $balance > 0 ? 'due' : 'settled' ?>">
        <span><?= $balance > 0 ? 'Balance due' : 'Fully paid' ?></span>
        <span><?= e($money($balance)) ?></span>
      </div>
    </div>

    <?php if (!empty($payments)): ?>
      <table class="paylog">
        <caption>Payments received against this invoice</caption>
        <thead>
          <tr><th>Receipt</th><th>Date</th><th>Method</th><th>Reference</th>
              <th class="ta-right">Amount</th><th class="ta-right">Balance after</th></tr>
        </thead>
        <tbody>
          <?php foreach ($payments as $p): ?>
            <tr>
              <td><?= e($p['receipt_number']) ?></td>
              <td><?= e($date($p['payment_date'])) ?></td>
              <td><?= e(MonthlyPayment::methodLabel((string) $p['method'])) ?></td>
              <td><?= e($p['reference'] ?: '—') ?></td>
              <td class="ta-right"><?= e($money($p['amount'])) ?></td>
              <td class="ta-right"><?= e($money($p['balance_after'])) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>

    <?php if (!empty($invoice['notes'])): ?>
      <div class="notes"><strong>Notes:</strong> <?= nl2br(e($invoice['notes'])) ?></div>
    <?php endif; ?>

    <div class="footer">
      Thank you for choosing Tejas Mehar.<br>
      This is a computer-generated invoice and does not require a signature.
    </div>
  </div>

</body>
</html>
