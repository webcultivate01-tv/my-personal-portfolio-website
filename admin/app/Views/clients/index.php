<?php
/**
 * @var string $csrf @var array $clients
 * @var float $totalCost @var float $totalInvoiced @var float $totalPaid @var int $totalMeetings
 */
$money = static fn(float $n): string => '₹' . number_format($n, 2);
?>
<header class="page-head">
  <div>
    <h1 class="page-head__title">Client Management</h1>
    <p class="page-head__sub">Everything about a client in one place — their details, every meeting held, invoices raised and payments received.</p>
  </div>
  <button type="button" class="btn btn--primary" onclick="toggleClientForm(true)">+ Add new client</button>
</header>

<section class="stat-grid">
  <div class="stat"><span class="stat__label">Clients</span><span class="stat__value"><?= count($clients) ?></span></div>
  <div class="stat"><span class="stat__label">Meetings</span><span class="stat__value"><?= (int) $totalMeetings ?></span></div>
  <div class="stat"><span class="stat__label">Project value</span><span class="stat__value stat__value--money"><?= e($money($totalCost)) ?></span></div>
  <div class="stat"><span class="stat__label">Invoiced</span><span class="stat__value stat__value--money"><?= e($money($totalInvoiced)) ?></span></div>
  <div class="stat"><span class="stat__label">Received</span><span class="stat__value stat__value--money"><?= e($money($totalPaid)) ?></span></div>
  <div class="stat<?= ($totalInvoiced - $totalPaid) > 0 ? ' stat--alert' : '' ?>">
    <span class="stat__label">Outstanding</span>
    <span class="stat__value stat__value--money"><?= e($money($totalInvoiced - $totalPaid)) ?></span>
  </div>
</section>

<section class="panel panel--pad" id="client-form" hidden>
  <div class="panel__head panel__head--plain">
    <h2 class="panel__title">Add a new client</h2>
  </div>
  <form class="form-grid" method="post" action="<?= url('/clients/create') ?>">
    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">

    <p class="form-grid__section">Contact</p>
    <div class="field">
      <label class="form__label" for="name">Client name</label>
      <input class="form__input" type="text" id="name" name="name" placeholder="Jane Doe" required>
    </div>
    <div class="field">
      <label class="form__label" for="company">Company</label>
      <input class="form__input" type="text" id="company" name="company" placeholder="Acme Pvt Ltd">
    </div>
    <div class="field">
      <label class="form__label" for="email">Email</label>
      <input class="form__input" type="email" id="email" name="email" placeholder="jane@example.com">
    </div>
    <div class="field">
      <label class="form__label" for="phone">Phone</label>
      <input class="form__input" type="text" id="phone" name="phone" placeholder="10-digit mobile number" pattern="\d{10}" maxlength="10">
    </div>
    <div class="field field--wide">
      <label class="form__label" for="address">Address</label>
      <textarea class="form__input" id="address" name="address" rows="2" placeholder="Billing / office address"></textarea>
    </div>

    <p class="form-grid__section">Project</p>
    <div class="field">
      <label class="form__label" for="project_cost">Total project cost (₹)</label>
      <input class="form__input" type="number" id="project_cost" name="project_cost" step="0.01" min="0" placeholder="e.g. 50000.00">
      <span class="field__hint">The whole agreed value. Invoices are billed against it.</span>
    </div>
    <div class="field">
      <label class="form__label" for="services">Services they took</label>
      <input class="form__input" type="text" id="services" name="services" placeholder="e.g. Website design, SEO, Hosting">
    </div>
    <div class="field field--wide">
      <label class="form__label" for="notes">Notes</label>
      <textarea class="form__input" id="notes" name="notes" rows="2" placeholder="Anything worth remembering about this client"></textarea>
    </div>
    <div class="field field--action">
      <button type="submit" class="btn btn--primary">Add client</button>
      <button type="button" class="btn btn--ghost" onclick="toggleClientForm(false)">Cancel</button>
    </div>
  </form>
</section>

<section class="panel">
  <div class="panel__head">
    <h2 class="panel__title">All clients</h2>
    <span class="panel__count"><?= count($clients) ?> total</span>
  </div>

  <?php if (empty($clients)): ?>
    <p class="empty">No clients yet. Use <strong>+ Add new client</strong> above to create your first one — then you can log meetings, raise invoices and record payments against them.</p>
  <?php else: ?>
    <table class="table">
      <thead>
        <tr>
          <th>Client</th><th>Services</th><th class="ta-center">Meetings</th>
          <th class="ta-right">Project cost</th><th class="ta-right">Invoiced</th>
          <th class="ta-right">Received</th><th class="ta-right">Outstanding</th>
          <th class="ta-right">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($clients as $c): ?>
          <?php
            $viewUrl     = url('/clients/view') . '?id=' . (int) $c['id'];
            $outstanding = (float) $c['total_invoiced'] - (float) $c['total_paid'];
          ?>
          <tr>
            <td>
              <a class="enq-from" href="<?= e($viewUrl) ?>">
                <span class="enq-from__name"><?= e($c['name']) ?></span>
                <span class="enq-from__email">
                  <?= e($c['company'] ?: ($c['email'] ?: ($c['phone'] ?: ''))) ?>
                </span>
              </a>
            </td>
            <td><?= !empty($c['services']) ? e($c['services']) : '<span class="muted">—</span>' ?></td>
            <td class="ta-center"><span class="count-pill"><?= (int) $c['meeting_count'] ?></span></td>
            <td class="ta-right"><?= $c['project_cost'] !== null ? e($money((float) $c['project_cost'])) : '<span class="muted">—</span>' ?></td>
            <td class="ta-right"><?= e($money((float) $c['total_invoiced'])) ?></td>
            <td class="ta-right"><?= e($money((float) $c['total_paid'])) ?></td>
            <td class="ta-right<?= $outstanding > 0 ? ' amount-due' : '' ?>"><?= e($money($outstanding)) ?></td>
            <td class="ta-right">
              <div class="row-actions">
                <a class="btn btn--sm btn--ghost" href="<?= e($viewUrl) ?>">Manage</a>
                <form method="post" action="<?= url('/clients/delete') ?>" class="inline-form"
                      onsubmit="return confirm('Delete <?= e($c['name']) ?>? Their meetings, invoices and payments are deleted too. This cannot be undone.')">
                  <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                  <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
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
  function toggleClientForm(show) {
    var panel = document.getElementById('client-form');
    panel.hidden = !show;
    if (show) { panel.scrollIntoView({behavior: 'smooth'}); panel.querySelector('#name').focus(); }
  }
</script>
