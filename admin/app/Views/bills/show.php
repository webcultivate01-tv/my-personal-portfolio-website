<?php
/**
 * A single bill, rendered as a standalone, print-ready page — "Print / Save
 * as PDF" uses the browser's own print-to-PDF, the same pattern already used
 * for the enquiries export, so no PDF library is needed.
 *
 * @var array $bill @var array $items
 */
$money = static fn(?float $n): string => $n === null ? '—' : '₹' . number_format($n, 2);
$sub   = array_sum(array_column($items, 'amount'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?= e($bill['bill_number']) ?> — <?= e(APP_NAME) ?></title>
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
  .bill-head h1 { font-size: 22px; margin: 0 0 4px; letter-spacing: .02em; }
  .bill-head .bill-no { color: #64748b; font-size: 12.5px; }
  .biz { text-align: right; }
  .biz__name { font-size: 15px; font-weight: 700; margin-bottom: 3px; }
  .biz__line { font-size: 12px; color: #475569; line-height: 1.6; }

  .parties { display: flex; justify-content: space-between; gap: 24px; margin-bottom: 26px; }
  .party h2 { font-size: 10.5px; text-transform: uppercase; letter-spacing: .06em; color: #94a3b8; margin: 0 0 6px; }
  .party .name { font-size: 14.5px; font-weight: 700; margin-bottom: 2px; }
  .party .line { font-size: 12.5px; color: #475569; line-height: 1.6; }
  .meta { text-align: right; }
  .meta .line { font-size: 12.5px; color: #475569; line-height: 1.9; }
  .meta .line strong { color: #1e293b; }

  table.items { width: 100%; border-collapse: collapse; margin-bottom: 22px; }
  table.items th { text-align: left; background: #f1f5f9; font-size: 11px; text-transform: uppercase; letter-spacing: .04em;
    color: #475569; padding: 9px 12px; }
  table.items th.ta-right, table.items td.ta-right { text-align: right; }
  table.items td { padding: 10px 12px; border-bottom: 1px solid #e2e8f0; font-size: 13px; }

  .totals { margin-left: auto; width: 300px; }
  .totals .row { display: flex; justify-content: space-between; padding: 7px 0; font-size: 13px; color: #475569; }
  .totals .row.grand { border-top: 2px solid #1e293b; margin-top: 4px; padding-top: 12px; font-size: 15px; font-weight: 700; color: #1e293b; }
  .totals .row.due { color: #b91c1c; font-weight: 700; }
  .totals .row.settled { color: #047857; font-weight: 700; }

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
    <a href="<?= url('/bills') ?>">&larr; Back to billing</a>
  </div>

  <div class="sheet">
    <div class="bill-head">
      <div>
        <h1>Bill / Receipt</h1>
        <div class="bill-no">
          <?= e($bill['bill_number']) ?> &nbsp;·&nbsp; <?= e(date('F j, Y', strtotime($bill['bill_date']))) ?>
        </div>
      </div>
      <div class="biz">
        <div class="biz__name">Tejas Mehar</div>
        <div class="biz__line">Mobile: +91 7821096438</div>
        <div class="biz__line">Email: scalewithtejas@gmail.com</div>
        <div class="biz__line">Website: www.tejasmehar.in</div>
      </div>
    </div>

    <div class="parties">
      <div class="party">
        <h2>Billed to</h2>
        <div class="name"><?= e($bill['client_name']) ?></div>
        <?php if (!empty($bill['client_company'])): ?><div class="line"><?= e($bill['client_company']) ?></div><?php endif; ?>
        <?php if (!empty($bill['client_address'])): ?><div class="line"><?= nl2br(e($bill['client_address'])) ?></div><?php endif; ?>
        <?php if (!empty($bill['client_email'])): ?><div class="line"><?= e($bill['client_email']) ?></div><?php endif; ?>
        <?php if (!empty($bill['client_phone'])): ?><div class="line"><?= e($bill['client_phone']) ?></div><?php endif; ?>
      </div>
      <div class="meta">
        <?php if (!empty($bill['project_name'])): ?>
          <div class="line"><strong>Project:</strong> <?= e($bill['project_name']) ?></div>
        <?php endif; ?>
        <div class="line"><strong>Payment method:</strong> <?= e(ucwords(str_replace('_', ' ', $bill['payment_method']))) ?></div>
      </div>
    </div>

    <table class="items">
      <thead>
        <tr><th>Service</th><th class="ta-right">Amount</th></tr>
      </thead>
      <tbody>
        <?php if (empty($items)): ?>
          <tr><td colspan="2" style="color:#94a3b8;">No line items recorded.</td></tr>
        <?php else: ?>
          <?php foreach ($items as $item): ?>
            <tr>
              <td><?= e($item['description'] ?? '') ?></td>
              <td class="ta-right"><?= isset($item['amount']) && $item['amount'] > 0 ? e($money((float) $item['amount'])) : '—' ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>

    <div class="totals">
      <?php if ($sub > 0): ?>
        <div class="row"><span>Services subtotal</span><span><?= e($money($sub)) ?></span></div>
      <?php endif; ?>
      <?php if ($bill['project_cost'] !== null): ?>
        <div class="row"><span>Total project cost</span><span><?= e($money((float) $bill['project_cost'])) ?></span></div>
      <?php endif; ?>
      <div class="row grand"><span>Amount paid (this bill)</span><span><?= e($money((float) $bill['amount_paid'])) ?></span></div>
      <div class="row"><span>Total paid to date</span><span><?= e($money((float) $bill['total_paid'])) ?></span></div>
      <?php if ($bill['balance_due'] !== null): ?>
        <?php $due = (float) $bill['balance_due']; ?>
        <div class="row <?= $due > 0 ? 'due' : 'settled' ?>">
          <span><?= $due > 0 ? 'Balance due' : ($due < 0 ? 'Advance paid' : 'Balance due') ?></span>
          <span><?= e($money(abs($due))) ?></span>
        </div>
      <?php endif; ?>
    </div>

    <?php if (!empty($bill['notes'])): ?>
      <div class="notes"><strong>Notes:</strong> <?= nl2br(e($bill['notes'])) ?></div>
    <?php endif; ?>

    <div class="footer">Thank you for your business. This is a computer-generated receipt.</div>
  </div>

</body>
</html>
