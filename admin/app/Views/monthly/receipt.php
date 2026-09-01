<?php
/**
 * The receipt for one payment, rendered as a standalone, print-ready page.
 *
 * Every figure here is the payment's own frozen record — including the balance
 * that was left when it was taken — so a receipt reprinted a year later still
 * says exactly what the client was handed on the day.
 *
 * @var array $payment
 */

use App\Models\MonthlyPayment;

$money = static fn($n): string => '₹' . number_format((float) $n, 2);
$date  = static fn(?string $d): string => $d ? date('d M Y', strtotime($d)) : '—';

$balance = (float) $payment['balance_after'];
$period  = date('j M Y', strtotime((string) $payment['period_start']))
         . ' – ' . date('j M Y', strtotime((string) $payment['period_end']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?= e($payment['receipt_number']) ?> — <?= e(APP_NAME) ?></title>
<style>
  * { box-sizing: border-box; }
  body { font-family: Arial, Helvetica, sans-serif; color: #1e293b; margin: 0; padding: 28px; font-size: 13px; background: #f1f5f9; }
  .sheet { max-width: 680px; margin: 0 auto; background: #fff; padding: 40px; border-radius: 10px; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
  .toolbar { max-width: 680px; margin: 0 auto 16px; }
  .toolbar button { font-family: inherit; font-size: 13px; padding: 9px 16px; border-radius: 8px; border: 1px solid #2563eb;
    background: #2563eb; color: #fff; cursor: pointer; }
  .toolbar a { font-family: inherit; font-size: 13px; padding: 9px 16px; border-radius: 8px; border: 1px solid #cbd5e1;
    color: #1e293b; text-decoration: none; margin-left: 8px; }

  .rc-head { display: flex; justify-content: space-between; align-items: flex-start; padding-bottom: 20px;
    border-bottom: 2px solid #1e293b; margin-bottom: 24px; }
  .rc-head h1 { font-size: 22px; margin: 0 0 8px; letter-spacing: .02em; }
  .rc-head .rc-no { color: #475569; font-size: 12.5px; line-height: 1.8; }
  .rc-head .rc-no strong { color: #1e293b; }
  .biz__name { font-size: 15px; font-weight: 700; margin-bottom: 8px; }
  .biz__line { font-size: 12px; color: #475569; line-height: 2; white-space: nowrap; }
  .biz__line strong { display: inline-block; min-width: 50px; color: #1e293b; font-weight: 600; }

  .stamp { display: inline-block; padding: 4px 12px; border-radius: 999px; font-size: 11.5px; font-weight: 700;
    text-transform: uppercase; letter-spacing: .05em; margin-top: 8px; }
  .stamp--full { background: #ecfdf5; color: #047857; }
  .stamp--partial { background: #fffbeb; color: #b45309; }

  .party { margin-bottom: 24px; }
  .party h2 { font-size: 10.5px; text-transform: uppercase; letter-spacing: .06em; color: #94a3b8; margin: 0 0 6px; }
  .party .name { font-size: 14.5px; font-weight: 700; margin-bottom: 2px; }
  .party .line { font-size: 12.5px; color: #475569; line-height: 1.6; }

  table.detail { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
  table.detail th { text-align: left; width: 46%; font-weight: 600; color: #475569; font-size: 12.5px;
    padding: 9px 0; border-bottom: 1px solid #e2e8f0; }
  table.detail td { text-align: right; padding: 9px 0; border-bottom: 1px solid #e2e8f0; font-size: 13px; color: #1e293b; }

  .amount-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 18px 20px; margin-bottom: 22px; }
  .amount-box .row { display: flex; justify-content: space-between; padding: 6px 0; font-size: 13px; color: #475569; }
  .amount-box .row.big { font-size: 17px; font-weight: 700; color: #047857; border-top: 1px solid #e2e8f0;
    margin-top: 6px; padding-top: 12px; }
  .amount-box .row.due { color: #b91c1c; font-weight: 700; }
  .amount-box .row.settled { color: #047857; font-weight: 700; }

  .notes { margin-top: 4px; padding-top: 16px; border-top: 1px dashed #cbd5e1; font-size: 12.5px; color: #64748b; }
  .footer { margin-top: 30px; text-align: center; font-size: 11.5px; color: #94a3b8; }

  @media print {
    @page { margin: 0; }
    body { background: #fff; padding: 15mm 12mm; }
    .toolbar { display: none; }
    .sheet { box-shadow: none; border-radius: 0; max-width: none; padding: 0; }
  }
</style>
</head>
<body>

  <div class="toolbar">
    <button type="button" onclick="window.print()">Print / Download receipt</button>
    <a href="<?= url('/monthly-clients/view') ?>?id=<?= (int) $payment['monthly_client_id'] ?>">&larr; Back to client</a>
    <a href="<?= url('/monthly-clients/invoice') ?>?id=<?= (int) $payment['invoice_id'] ?>">View invoice</a>
  </div>

  <div class="sheet">
    <div class="rc-head">
      <div>
        <h1>Payment Receipt</h1>
        <div class="rc-no">
          <div><strong>Receipt No:</strong> <?= e($payment['receipt_number']) ?></div>
          <div><strong>Payment Date:</strong> <?= e($date($payment['payment_date'])) ?></div>
        </div>
        <span class="stamp stamp--<?= $balance > 0.004 ? 'partial' : 'full' ?>">
          <?= $balance > 0.004 ? 'Partial payment' : 'Paid in full' ?>
        </span>
      </div>
      <div class="biz">
        <div class="biz__name">Tejas Mehar</div>
        <div class="biz__line"><strong>Mobile:</strong> +91 7821096438</div>
        <div class="biz__line"><strong>Email:</strong> scalewithtejas@gmail.com</div>
        <div class="biz__line"><strong>Website:</strong> www.tejasmehar.in</div>
      </div>
    </div>

    <div class="party">
      <h2>Received from</h2>
      <div class="name"><?= e($payment['client_name']) ?></div>
      <?php if (!empty($payment['company'])): ?><div class="line"><?= e($payment['company']) ?></div><?php endif; ?>
      <?php if (!empty($payment['billing_address'])): ?><div class="line"><?= nl2br(e($payment['billing_address'])) ?></div><?php endif; ?>
      <?php if (!empty($payment['email'])): ?><div class="line"><?= e($payment['email']) ?></div><?php endif; ?>
      <?php if (!empty($payment['mobile'])): ?><div class="line"><?= e($payment['mobile']) ?></div><?php endif; ?>
    </div>

    <table class="detail">
      <tr><th>Invoice number</th><td><?= e($payment['invoice_number']) ?></td></tr>
      <tr><th>Invoice date</th><td><?= e($date($payment['invoice_date'])) ?></td></tr>
      <tr><th>Due date</th><td><?= e($date($payment['due_date'])) ?></td></tr>
      <tr><th>Service</th><td><?= e($payment['service_name']) ?></td></tr>
      <tr><th>Billing period</th><td><?= e($period) ?></td></tr>
      <tr><th>Payment method</th><td><?= e(MonthlyPayment::methodLabel((string) $payment['method'])) ?></td></tr>
      <tr><th>Transaction / reference number</th><td><?= e($payment['reference'] ?: '—') ?></td></tr>
    </table>

    <div class="amount-box">
      <div class="row"><span>Invoice total</span><span><?= e($money($payment['total_amount'])) ?></span></div>
      <div class="row big"><span>Amount paid</span><span><?= e($money($payment['amount'])) ?></span></div>
      <div class="row <?= $balance > 0.004 ? 'due' : 'settled' ?>">
        <span>Remaining balance</span>
        <span><?= e($money($balance)) ?></span>
      </div>
    </div>

    <?php if (!empty($payment['notes'])): ?>
      <div class="notes"><strong>Notes:</strong> <?= nl2br(e($payment['notes'])) ?></div>
    <?php endif; ?>

    <div class="footer">
      <?= $balance > 0.004
            ? 'This receipt confirms a part payment. ' . e($money($balance)) . ' remains outstanding on ' . e($payment['invoice_number']) . '.'
            : 'This receipt confirms invoice ' . e($payment['invoice_number']) . ' has been paid in full.' ?><br>
      This is a computer-generated receipt and does not require a signature.
    </div>
  </div>

</body>
</html>
