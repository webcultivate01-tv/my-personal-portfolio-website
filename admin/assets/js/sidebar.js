/* ============================================================
   Mobile sidebar — hamburger toggles the sidebar in as an
   off-canvas drawer over a dimmed backdrop (CSS handles the
   slide via .sidebar.is-open; this just flips the classes).
   ============================================================ */
(function () {
  'use strict';

  var toggle   = document.getElementById('sidebarToggle');
  var sidebar  = document.getElementById('sidebar');
  var backdrop = document.getElementById('sidebarBackdrop');
  if (!toggle || !sidebar || !backdrop) return;

  function open() {
    sidebar.classList.add('is-open');
    backdrop.classList.add('is-open');
    toggle.setAttribute('aria-expanded', 'true');
    document.body.classList.add('no-scroll');
  }

  function close() {
    sidebar.classList.remove('is-open');
    backdrop.classList.remove('is-open');
    toggle.setAttribute('aria-expanded', 'false');
    document.body.classList.remove('no-scroll');
  }

  toggle.addEventListener('click', function () {
    if (sidebar.classList.contains('is-open')) close(); else open();
  });
  backdrop.addEventListener('click', close);
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') close();
  });

  // Tapping a nav link or the sign-out button should close the drawer
  // rather than leave it open behind the page that just loaded.
  var closers = sidebar.querySelectorAll('.nav-item, .sidebar__user button');
  for (var i = 0; i < closers.length; i++) {
    closers[i].addEventListener('click', close);
  }

  // Growing past the mobile breakpoint (e.g. rotating a tablet) should
  // drop any open/hidden state so the desktop layout isn't stuck mid-animation.
  window.addEventListener('resize', function () {
    if (window.innerWidth > 720) close();
  });
}());
