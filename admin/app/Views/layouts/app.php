<?php
/**
 * Main authenticated layout: sidebar + content area.
 * @var string $content  @var string $title
 */
$active   = $active   ?? '';
$csrfTok  = $csrf ?? ($_SESSION['csrf'] ?? '');
$meName   = \App\Core\Auth::name();
$meRole   = \App\Core\Auth::role();
$isAdmin  = \App\Core\Auth::isAdmin();

// Unread enquiries drive the red dot next to "Enquiries" in the sidebar,
// visible from every page until someone opens them.
$unreadEnquiries = \App\Core\Auth::check() ? (new \App\Models\Lead())->countUnread() : 0;

// Hosting renewals that have expired or fall due within a week put a red dot
// next to "Hosting", so a lapsing renewal is visible from anywhere in the panel.
// Managers see Hosting read-only, so the dot is useful to them too.
$hostingAlerts = 0;
if (\App\Core\Auth::check()) {
    $counts        = (new \App\Models\HostingService())->alertCounts();
    $hostingAlerts = $counts['expired'] + $counts['urgent'];
}

// Pull and clear any one-shot flash messages.
$flashes = $_SESSION['flash'] ?? [];
unset($_SESSION['flash']);

/** Mark a nav link active. */
$is = static fn(string $key): string => $active === $key ? ' is-active' : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($title ?? 'Admin') ?> — <?= e(APP_NAME) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= asset('css/admin.css') ?>">
</head>
<body class="app">
  <div class="mobile-topbar">
    <button type="button" class="sidebar-toggle" id="sidebarToggle" aria-label="Open menu" aria-controls="sidebar" aria-expanded="false">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <span class="mobile-topbar__brand"><span class="sidebar__logo">TM</span> Admin panel</span>
  </div>
  <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

  <aside class="sidebar" id="sidebar">
    <div class="sidebar__top">
      <div class="sidebar__brand">
        <span class="sidebar__logo">TM</span>
        <span class="sidebar__brandtext">Tejas Mehar<small>Admin panel</small></span>
      </div>

      <nav class="sidebar__nav">
        <p class="nav-label">Overview</p>
        <a class="nav-item<?= $is('dashboard') ?>" href="<?= url('/') ?>">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
          Dashboard
        </a>
        <a class="nav-item<?= $is('enquiries') ?>" href="<?= url('/enquiries') ?>">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z"/><path d="M22 6l-10 7L2 6"/></svg>
          Enquiries
          <?php if ($unreadEnquiries > 0): ?>
            <span class="nav-dot" title="<?= (int) $unreadEnquiries ?> new enquir<?= $unreadEnquiries === 1 ? 'y' : 'ies' ?>"></span>
          <?php endif; ?>
        </a>

        <p class="nav-label">Management</p>
        <a class="nav-item<?= $is('projects') ?>" href="<?= url('/projects') ?>">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="9" y1="3" x2="9" y2="21"/><line x1="3" y1="9" x2="9" y2="9"/><line x1="3" y1="15" x2="9" y2="15"/></svg>
          Project Management
        </a>

        <?php if ($isAdmin): ?>
          <a class="nav-item<?= $is('clients') ?>" href="<?= url('/clients') ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21V7a2 2 0 0 1 2-2h6a2 2 0 0 1 2 2v14"/><path d="M13 10h6a2 2 0 0 1 2 2v9"/><path d="M1 21h22"/><path d="M6 9h2M6 13h2M6 17h2M17 14h2M17 18h2"/></svg>
            Client Management
          </a>
        <?php endif; ?>

        <?php /* Monthly Clients and Hosting are read-only for managers, so both stay in the sidebar. */ ?>
        <a class="nav-item<?= $is('monthly') ?>" href="<?= url('/monthly-clients') ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><path d="M8 14h.01M12 14h.01M16 14h.01M8 18h.01M12 18h.01"/></svg>
          Monthly Clients
        </a>

        <?php if ($isAdmin): ?>
          <a class="nav-item<?= $is('bills') ?>" href="<?= url('/bills') ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><line x1="8" y1="13" x2="16" y2="13"/><line x1="8" y1="17" x2="13" y2="17"/></svg>
            Billing
          </a>
        <?php endif; ?>

        <a class="nav-item<?= $is('hosting') ?>" href="<?= url('/hosting') ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
          Hosting
          <?php if ($hostingAlerts > 0): ?>
            <span class="nav-dot" title="<?= (int) $hostingAlerts ?> hosting renewal<?= $hostingAlerts === 1 ? '' : 's' ?> need attention"></span>
          <?php endif; ?>
        </a>

        <?php if ($isAdmin): ?>
          <a class="nav-item<?= $is('users') ?>" href="<?= url('/users') ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            Admin Management
          </a>
        <?php endif; ?>

        <a class="nav-item<?= $is('reports') ?>" href="<?= url('/reports') ?>">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><line x1="8" y1="18" x2="8" y2="14"/><line x1="12" y1="18" x2="12" y2="11"/><line x1="16" y1="18" x2="16" y2="15"/></svg>
          Reports
        </a>

        <p class="nav-label">Account</p>
        <a class="nav-item<?= $is('account') ?>" href="<?= url('/account') ?>">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
          My Account
        </a>
      </nav>
    </div>

    <div class="sidebar__user">
      <div class="user-card">
        <span class="avatar avatar--lg"><?= e(strtoupper(substr($meName, 0, 1))) ?></span>
        <div class="user-card__meta">
          <span class="user-card__name"><?= e($meName) ?></span>
          <span class="role-badge role-badge--<?= e($meRole) ?>"><?= e(ucfirst($meRole)) ?></span>
        </div>
      </div>
      <form method="post" action="<?= url('/logout') ?>">
        <input type="hidden" name="csrf" value="<?= e($csrfTok) ?>">
        <button type="submit" class="btn btn--ghost btn--block">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
          Sign out
        </button>
      </form>
    </div>
  </aside>

  <main class="main">
    <?php if (!empty($flashes)): ?>
      <div class="flash-stack">
        <?php foreach ($flashes as $f): ?>
          <div class="alert alert--<?= e($f['type']) ?>"><?= e($f['message']) ?></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?= $content ?>
  </main>

  <script src="<?= asset('js/sidebar.js') ?>"></script>
  <script src="<?= asset('js/password-toggle.js') ?>"></script>
</body>
</html>
