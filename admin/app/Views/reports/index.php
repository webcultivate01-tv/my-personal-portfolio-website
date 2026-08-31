<?php
/**
 * Reports — pick a report on the left, set its filters, then generate.
 * The form is a plain GET to /reports/generate opened in a new tab, so a
 * generated report has a shareable URL and the back button never re-runs it.
 *
 * @var string $type @var array $reports @var array $filters
 * @var array $statuses @var array $subjects @var array $flags
 * @var array $projectStatuses @var array $projectPriorities @var array $balances
 * @var array $methods @var array $hostingTypes @var array $hostingStatuses
 * @var array $hostingLabels @var array $cycles
 * @var array $clients @var array $projects @var array $providers
 */
$f = $filters;

/** Value of a filter, '' when it isn't set for this report. */
$val = static fn(string $key): string => (string) ($f[$key] ?? '');

/** 'selected' when $key currently holds $value. */
$sel = static fn(string $key, string $value): string => $val($key) === $value ? ' selected' : '';

/** 'in_progress' -> 'In Progress' */
$label = static fn(string $v): string => ucwords(str_replace('_', ' ', $v));

/** The date range label changes per report — that is what the range filters. */
$dateLabels = [
    'enquiries' => 'Received between',
    'projects'  => 'Created between',
    'clients'   => 'Added between',
    'bills'     => 'Bill date between',
    'hosting'   => 'Renewal date between',
];
$searchPlaceholders = [
    'enquiries' => 'Search name, email, phone, subject or message…',
    'projects'  => 'Search project, description or client…',
    'clients'   => 'Search name, company, email or phone…',
    'bills'     => 'Search bill number, client or project…',
    'hosting'   => 'Search client, website, domain or provider…',
];
?>
<header class="page-head">
  <div>
    <h1 class="page-head__title">Reports</h1>
    <p class="page-head__sub">Pick a report, narrow it down with the filters, and download it as a PDF. Every report opens in a new tab, print-ready.</p>
  </div>
</header>

<section class="report-cards">
  <?php foreach ($reports as $key => $r): ?>
    <a class="report-card<?= $type === $key ? ' is-active' : '' ?>" href="<?= url('/reports') ?>?type=<?= e($key) ?>">
      <span class="report-card__title"><?= e($r['label']) ?></span>
      <span class="report-card__blurb"><?= e($r['blurb']) ?></span>
    </a>
  <?php endforeach; ?>
</section>

<section class="panel panel--pad">
  <div class="panel__head panel__head--plain">
    <h2 class="panel__title"><?= e($reports[$type]['label']) ?> — filters</h2>
    <span class="panel__count">All filters optional</span>
  </div>

  <form class="form-grid" method="get" action="<?= url('/reports/generate') ?>" target="_blank" rel="noopener">
    <input type="hidden" name="type" value="<?= e($type) ?>">

    <?php if ($type === 'enquiries'): ?>
      <div class="field">
        <label class="form__label" for="status">Status</label>
        <select class="form__input" id="status" name="status">
          <option value="">Any status</option>
          <?php foreach ($statuses as $s): ?>
            <option value="<?= e($s) ?>"<?= $sel('status', $s) ?>><?= e($label($s)) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label class="form__label" for="flag">Flag</label>
        <select class="form__input" id="flag" name="flag">
          <option value="">Any</option>
          <?php foreach ($flags as $fl): ?>
            <option value="<?= e($fl) ?>"<?= $sel('flag', $fl) ?>><?= e($label($fl)) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label class="form__label" for="subject">Subject</label>
        <select class="form__input" id="subject" name="subject">
          <option value="">Any subject</option>
          <?php foreach ($subjects as $s): ?>
            <option value="<?= e($s) ?>"<?= $sel('subject', $s) ?>><?= e($s) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

    <?php elseif ($type === 'projects'): ?>
      <div class="field">
        <label class="form__label" for="status">Status</label>
        <select class="form__input" id="status" name="status">
          <option value="">Any status</option>
          <?php foreach ($projectStatuses as $s): ?>
            <option value="<?= e($s) ?>"<?= $sel('status', $s) ?>><?= e($label($s)) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label class="form__label" for="priority">Priority</label>
        <select class="form__input" id="priority" name="priority">
          <option value="">Any priority</option>
          <?php foreach ($projectPriorities as $p): ?>
            <option value="<?= e($p) ?>"<?= $sel('priority', $p) ?>><?= e($label($p)) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php if (!empty($clients)): ?>
        <div class="field">
          <label class="form__label" for="client_id">Client</label>
          <select class="form__input" id="client_id" name="client_id">
            <option value="">All clients</option>
            <?php foreach ($clients as $c): ?>
              <option value="<?= (int) $c['id'] ?>"<?= (int) ($f['client_id'] ?? 0) === (int) $c['id'] ? ' selected' : '' ?>>
                <?= e($c['name']) ?><?= $c['company'] ? ' — ' . e($c['company']) : '' ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      <?php endif; ?>

    <?php elseif ($type === 'clients'): ?>
      <div class="field">
        <label class="form__label" for="balance">Balance</label>
        <select class="form__input" id="balance" name="balance">
          <option value="">Every client</option>
          <?php foreach ($balances as $b): ?>
            <option value="<?= e($b) ?>"<?= $sel('balance', $b) ?>>
              <?= $b === 'outstanding' ? 'Still owes money' : 'Fully settled' ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

    <?php elseif ($type === 'bills'): ?>
      <div class="field">
        <label class="form__label" for="client_id">Client</label>
        <select class="form__input" id="client_id" name="client_id">
          <option value="">All clients</option>
          <?php foreach ($clients as $c): ?>
            <option value="<?= (int) $c['id'] ?>"<?= (int) ($f['client_id'] ?? 0) === (int) $c['id'] ? ' selected' : '' ?>><?= e($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label class="form__label" for="project_id">Project</label>
        <select class="form__input" id="project_id" name="project_id">
          <option value="">All projects</option>
          <?php foreach ($projects as $p): ?>
            <option value="<?= (int) $p['id'] ?>"<?= (int) ($f['project_id'] ?? 0) === (int) $p['id'] ? ' selected' : '' ?>><?= e($p['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label class="form__label" for="payment_method">Payment method</label>
        <select class="form__input" id="payment_method" name="payment_method">
          <option value="">All methods</option>
          <?php foreach ($methods as $m): ?>
            <option value="<?= e($m) ?>"<?= $sel('payment_method', $m) ?>><?= e($label($m)) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

    <?php elseif ($type === 'hosting'): ?>
      <div class="field">
        <label class="form__label" for="service_type">Type</label>
        <select class="form__input" id="service_type" name="service_type">
          <option value="">Hosting &amp; domains</option>
          <?php foreach ($hostingTypes as $t): ?>
            <option value="<?= e($t) ?>"<?= $sel('type', $t) ?>><?= e($label($t)) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label class="form__label" for="status">Renewal status</label>
        <select class="form__input" id="status" name="status">
          <option value="">Any status</option>
          <?php foreach ($hostingStatuses as $s): ?>
            <option value="<?= e($s) ?>"<?= $sel('status', $s) ?>><?= e($hostingLabels[$s]) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label class="form__label" for="provider">Provider</label>
        <select class="form__input" id="provider" name="provider">
          <option value="">All providers</option>
          <?php foreach ($providers as $p): ?>
            <option value="<?= e($p) ?>"<?= $sel('provider', $p) ?>><?= e($p) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label class="form__label" for="cycle">Billing cycle</label>
        <select class="form__input" id="cycle" name="cycle">
          <option value="">Any cycle</option>
          <?php foreach ($cycles as $c): ?>
            <option value="<?= e($c) ?>"<?= $sel('cycle', $c) ?>><?= e($label($c)) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    <?php endif; ?>

    <div class="field">
      <label class="form__label" for="from"><?= e($dateLabels[$type]) ?></label>
      <input class="form__input" type="date" id="from" name="from" value="<?= e($val('from')) ?>">
    </div>
    <div class="field">
      <label class="form__label" for="to">and</label>
      <input class="form__input" type="date" id="to" name="to" value="<?= e($val('to')) ?>">
    </div>

    <div class="field field--wide">
      <label class="form__label" for="q">Search</label>
      <input class="form__input" type="search" id="q" name="q" value="<?= e($val('q')) ?>"
             placeholder="<?= e($searchPlaceholders[$type]) ?>">
    </div>

    <div class="field field--action">
      <button type="submit" class="btn btn--primary" name="print" value="1">⬇ Generate &amp; download PDF</button>
      <button type="submit" class="btn btn--ghost">Preview in new tab</button>
      <a class="btn btn--ghost" href="<?= url('/reports') ?>?type=<?= e($type) ?>">Reset filters</a>
    </div>
  </form>

  <p class="form-intro">
    “Download PDF” opens the report and goes straight to your browser’s print dialog — choose
    <strong>Save as PDF</strong> as the destination to keep the file.
  </p>
</section>
