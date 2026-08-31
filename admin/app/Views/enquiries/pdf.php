<?php
/** @var string $title @var string $filter @var string $dateFrom @var string $dateTo @var string $subject
 *  @var array $enquiries @var string $generatedAt
 */
$rangeLabel = 'All time';
if ($dateFrom !== '' && $dateTo !== '') {
    $rangeLabel = date('M j, Y', strtotime($dateFrom)) . ' – ' . date('M j, Y', strtotime($dateTo));
} elseif ($dateFrom !== '') {
    $rangeLabel = 'From ' . date('M j, Y', strtotime($dateFrom));
} elseif ($dateTo !== '') {
    $rangeLabel = 'Up to ' . date('M j, Y', strtotime($dateTo));
}
$filterLabel = ucfirst($filter);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?= e($title) ?> — <?= e(APP_NAME) ?></title>
<style>
  * { box-sizing: border-box; }
  body { font-family: Arial, Helvetica, sans-serif; color: #1e293b; margin: 0; padding: 28px; font-size: 13px; }
  .toolbar { margin-bottom: 18px; }
  .toolbar button { font-family: inherit; font-size: 13px; padding: 9px 16px; border-radius: 8px; border: 1px solid #2563eb;
    background: #2563eb; color: #fff; cursor: pointer; }
  .toolbar a { font-family: inherit; font-size: 13px; padding: 9px 16px; border-radius: 8px; border: 1px solid #cbd5e1;
    color: #1e293b; text-decoration: none; margin-left: 8px; }
  h1 { font-size: 20px; margin: 0 0 4px; }
  .meta { color: #64748b; margin: 0 0 20px; font-size: 12.5px; }
  .meta strong { color: #1e293b; }
  table { width: 100%; border-collapse: collapse; }
  th, td { text-align: left; padding: 8px 10px; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
  th { background: #f1f5f9; font-size: 11.5px; text-transform: uppercase; letter-spacing: .03em; color: #475569; }
  td { font-size: 12.5px; }
  .status { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 11px; font-weight: 600; text-transform: capitalize; }
  .status-new { background:#eff6ff; color:#1d4ed8; }
  .status-contacted { background:#fffbeb; color:#b45309; }
  .status-quoted { background:#eff6ff; color:#1d4ed8; }
  .status-won { background:#ecfdf5; color:#047857; }
  .status-lost { background:#fef2f2; color:#b91c1c; }
  .status-spam { background:#f1f5f9; color:#64748b; }
  .flags { font-size: 11px; color: #64748b; }
  .empty { padding: 30px 0; text-align: center; color: #64748b; }
  @media print {
    .toolbar { display: none; }
    body { padding: 0; }
  }
</style>
</head>
<body>

  <div class="toolbar">
    <button type="button" onclick="window.print()">Print / Save as PDF</button>
    <a href="javascript:window.close()">Close</a>
  </div>

  <h1>Enquiries Export</h1>
  <p class="meta">
    Status filter: <strong><?= e($filterLabel) ?></strong>
    &nbsp;·&nbsp; Date range: <strong><?= e($rangeLabel) ?></strong>
    <?php if ($subject !== ''): ?>
      &nbsp;·&nbsp; Subject: <strong><?= e($subject) ?></strong>
    <?php endif; ?>
    &nbsp;·&nbsp; <?= count($enquiries) ?> record<?= count($enquiries) === 1 ? '' : 's' ?>
    &nbsp;·&nbsp; Generated <?= e($generatedAt) ?>
  </p>

  <?php if (empty($enquiries)): ?>
    <p class="empty">No enquiries match this filter and date range.</p>
  <?php else: ?>
    <table>
      <thead>
        <tr>
          <th>Date</th>
          <th>From</th>
          <th>Subject</th>
          <th>Status</th>
          <th>Flags</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($enquiries as $enq): ?>
          <tr>
            <td><?= e(date('M j, Y', strtotime($enq['created_at']))) ?></td>
            <td>
              <?= e($enq['name']) ?><br>
              <span class="flags"><?= e($enq['email']) ?><?= !empty($enq['phone']) ? ' · ' . e($enq['phone']) : '' ?></span>
            </td>
            <td><?= e($enq['subject'] !== '' && $enq['subject'] !== null ? $enq['subject'] : '(no subject)') ?></td>
            <td><span class="status status-<?= e($enq['status']) ?>"><?= e(ucfirst($enq['status'])) ?></span></td>
            <td class="flags">
              <?= (int) $enq['is_important'] === 1 ? '★ Important' : '' ?>
              <?= (int) $enq['is_client'] === 1 ? ((int) $enq['is_important'] === 1 ? ' · ' : '') . 'Client' : '' ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

</body>
</html>
