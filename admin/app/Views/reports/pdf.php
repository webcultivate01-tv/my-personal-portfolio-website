<?php
/**
 * Printable report output — no admin chrome, styled for paper.
 *
 * Driven entirely by the column spec the controller hands over, so adding a
 * report means describing its columns there, not editing this file.
 * Each column is ['label', 'key', 'format'?, 'align'?, 'sub'?, 'text_key'?]:
 *   format   text (default) | date | money | status | label
 *   sub      a second, muted line under the main value
 *   text_key column to print instead of 'key' (used when the raw value is a
 *            status slug but a friendlier label already sits on the row)
 *
 * @var string $reportLabel @var array $columns @var array $rows
 * @var array $summary @var array $appliedFilters
 * @var string $generatedAt @var string $generatedBy @var bool $autoPrint
 */

/** Print one cell's main value in the format its column asks for. */
$cell = static function (array $col, array $row): string {
    $key   = $col['key'];
    $value = $row[$key] ?? null;

    switch ($col['format'] ?? 'text') {
        case 'date':
            return ($value === null || $value === '' || $value === '0000-00-00')
                ? '<span class="muted">—</span>'
                : e(date('M j, Y', strtotime((string) $value)));

        case 'money':
            return $value === null || $value === ''
                ? '<span class="muted">—</span>'
                : e('₹' . number_format((float) $value, 2));

        case 'status':
            $text = (string) ($row[$col['text_key'] ?? $key] ?? $value);
            return '<span class="status status-' . e((string) $value) . '">'
                 . e(ucwords(str_replace('_', ' ', $text))) . '</span>';

        case 'label':
            return e(ucwords(str_replace('_', ' ', (string) $value)));

        default:
            return ($value === null || $value === '')
                ? '<span class="muted">—</span>'
                : e((string) $value);
    }
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?= e($reportLabel) ?> — <?= e(APP_NAME) ?></title>
<style>
  * { box-sizing: border-box; }
  body { font-family: Arial, Helvetica, sans-serif; color: #1e293b; margin: 0; padding: 28px; font-size: 13px; }

  .toolbar { display: flex; gap: 8px; margin-bottom: 20px; }
  .toolbar button, .toolbar a { font-family: inherit; font-size: 13px; padding: 9px 16px; border-radius: 8px;
    cursor: pointer; text-decoration: none; }
  .toolbar button { border: 1px solid #2563eb; background: #2563eb; color: #fff; }
  .toolbar a { border: 1px solid #cbd5e1; color: #1e293b; }

  .head { border-bottom: 2px solid #1e293b; padding-bottom: 12px; margin-bottom: 16px; }
  .head__brand { font-size: 12px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: #64748b; }
  h1 { font-size: 21px; margin: 4px 0 0; }
  .head__meta { color: #64748b; font-size: 12px; margin: 6px 0 0; }

  .filters { margin: 0 0 16px; font-size: 12px; color: #475569; line-height: 1.7; }
  .filters strong { color: #0f172a; }
  .filters .chip { display: inline-block; background: #f1f5f9; border-radius: 6px; padding: 2px 8px; margin-right: 6px; }

  .summary { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 18px; }
  .summary__item { border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px 14px; min-width: 130px; }
  .summary__label { display: block; font-size: 10.5px; text-transform: uppercase; letter-spacing: .05em; color: #64748b; font-weight: 700; }
  .summary__value { display: block; font-size: 17px; font-weight: 700; color: #0f172a; margin-top: 3px; }

  table { width: 100%; border-collapse: collapse; }
  th, td { text-align: left; padding: 8px 10px; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
  th { background: #f1f5f9; font-size: 11px; text-transform: uppercase; letter-spacing: .03em; color: #475569; }
  td { font-size: 12.5px; }
  .ta-right { text-align: right; }
  .sub { display: block; font-size: 11px; color: #64748b; margin-top: 2px; }
  .muted { color: #94a3b8; }

  .status { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 10.5px; font-weight: 700; white-space: nowrap; }
  .status-new, .status-quoted, .status-planning, .status-active { background:#eff6ff; color:#1d4ed8; }
  .status-contacted, .status-in_progress, .status-renewing_soon { background:#fffbeb; color:#b45309; }
  .status-won, .status-completed { background:#ecfdf5; color:#047857; }
  .status-lost, .status-cancelled, .status-expired { background:#fef2f2; color:#b91c1c; }
  .status-spam, .status-on_hold { background:#f1f5f9; color:#64748b; }
  .status-due { background:#fff7ed; color:#c2410c; }

  .empty { padding: 34px 0; text-align: center; color: #64748b; }
  .foot { margin-top: 18px; padding-top: 10px; border-top: 1px solid #e2e8f0; font-size: 11px; color: #94a3b8; }

  @media print {
    .toolbar { display: none; }
    body { padding: 0; font-size: 11.5px; }
    /* Repeat the header on every printed page and never split a row. */
    thead { display: table-header-group; }
    tr { page-break-inside: avoid; }
    .summary__item { border-color: #cbd5e1; }
  }
  @page { margin: 14mm; }
</style>
</head>
<body>

  <div class="toolbar">
    <button type="button" onclick="window.print()">⬇ Download / Print PDF</button>
    <a href="<?= url('/reports') ?>">Back to reports</a>
  </div>

  <div class="head">
    <p class="head__brand"><?= e(APP_NAME) ?></p>
    <h1><?= e($reportLabel) ?></h1>
    <p class="head__meta">
      Generated <?= e($generatedAt) ?> by <?= e($generatedBy) ?>
      &nbsp;·&nbsp; <?= count($rows) ?> record<?= count($rows) === 1 ? '' : 's' ?>
    </p>
  </div>

  <p class="filters">
    <strong>Filters:</strong>
    <?php if (empty($appliedFilters)): ?>
      <span class="chip">None — every record</span>
    <?php else: ?>
      <?php foreach ($appliedFilters as $label => $value): ?>
        <span class="chip"><?= e($label) ?>: <strong><?= e((string) $value) ?></strong></span>
      <?php endforeach; ?>
    <?php endif; ?>
  </p>

  <?php if (!empty($summary)): ?>
    <div class="summary">
      <?php foreach ($summary as $label => $value): ?>
        <div class="summary__item">
          <span class="summary__label"><?= e($label) ?></span>
          <span class="summary__value"><?= e((string) $value) ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if (empty($rows)): ?>
    <p class="empty">No records match these filters.</p>
  <?php else: ?>
    <table>
      <thead>
        <tr>
          <?php foreach ($columns as $col): ?>
            <th<?= ($col['align'] ?? '') === 'right' ? ' class="ta-right"' : '' ?>><?= e($col['label']) ?></th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $row): ?>
          <tr>
            <?php foreach ($columns as $col): ?>
              <td<?= ($col['align'] ?? '') === 'right' ? ' class="ta-right"' : '' ?>>
                <?= $cell($col, $row) ?>
                <?php
                $sub = isset($col['sub']) ? (string) ($row[$col['sub']] ?? '') : '';
                if ($sub !== ''): ?>
                  <span class="sub"><?= e($sub) ?></span>
                <?php endif; ?>
              </td>
            <?php endforeach; ?>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

  <p class="foot"><?= e(APP_NAME) ?> — <?= e($reportLabel) ?> · <?= e($generatedAt) ?></p>

<?php if ($autoPrint): ?>
  <script>
    // Let the table paint before the print dialog steals focus.
    window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 350); });
  </script>
<?php endif; ?>
</body>
</html>
