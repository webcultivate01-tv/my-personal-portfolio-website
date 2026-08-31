/* ============================================================
   Hosting & Domain module — form behaviour.

   Three small jobs, all of them conveniences: the server re-does
   the same work on submit, so the module still behaves correctly
   with JavaScript switched off.

     1. Reveal / hide the "+ Add" and "Edit" forms.
     2. Fill the client name + company when a client is picked.
     3. Work out the renewal date from purchase date + billing cycle.
   ============================================================ */
(function () {
  'use strict';

  /** Months covered by each billing cycle ('custom' reads the months field). */
  var CYCLE_MONTHS = {
    monthly: 1,
    quarterly: 3,
    half_yearly: 6,
    yearly: 12,
    custom: null
  };

  /**
   * Add whole months to a yyyy-mm-dd date, clamping to the end of the target
   * month so 31 Jan + 1 month lands on 28 Feb rather than spilling into March.
   * Mirrors HostingService::addCycle() on the server.
   */
  function addMonths(iso, months) {
    var parts = /^(\d{4})-(\d{2})-(\d{2})$/.exec(iso);
    if (!parts || !months) return '';

    var year = +parts[1], month = +parts[2] - 1, day = +parts[3];
    var target = new Date(Date.UTC(year, month + months, 1));
    var lastDay = new Date(Date.UTC(target.getUTCFullYear(), target.getUTCMonth() + 1, 0)).getUTCDate();

    target.setUTCDate(Math.min(day, lastDay));
    return target.toISOString().slice(0, 10);
  }

  /** The cycle length selected inside one form. */
  function cycleMonths(form) {
    var cycle = form.querySelector('.js-cycle');
    if (!cycle) return null;
    if (cycle.value === 'custom') {
      var custom = form.querySelector('[name="custom_cycle_months"]');
      return custom && +custom.value > 0 ? +custom.value : null;
    }
    return CYCLE_MONTHS[cycle.value] || null;
  }

  /* ---- 1. Reveal / hide a form, flipping the button's label ---- */
  document.addEventListener('click', function (ev) {
    var btn = ev.target.closest('[data-toggle]');
    if (!btn) return;

    var form = document.getElementById(btn.dataset.toggle);
    if (!form) return;

    var opening = form.hidden;
    form.hidden = !opening;

    // Every button pointing at this form shows the same label.
    document.querySelectorAll('[data-toggle="' + btn.dataset.toggle + '"]').forEach(function (b) {
      if (b.dataset.labelOpen) b.textContent = opening ? b.dataset.labelClose : b.dataset.labelOpen;
    });

    // A read-only summary marked as this form's twin swaps out while editing.
    var view = document.getElementById(btn.dataset.toggle + '-view');
    if (view) view.hidden = opening;

    if (opening) {
      form.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      var first = form.querySelector('input:not([type=hidden]):not([readonly]), select, textarea');
      if (first) first.focus();
    }
  });

  /* ---- 2. Picking a client fills its name and company ---- */
  document.addEventListener('change', function (ev) {
    var select = ev.target.closest('.js-client-select');
    if (!select) return;

    var form = select.form;
    var opt = select.options[select.selectedIndex];
    var name = form.querySelector('.js-client-name');
    var company = form.querySelector('.js-client-company');

    if (!opt || !opt.dataset.name) return;
    if (name) name.value = opt.dataset.name;
    if (company) company.value = opt.dataset.company || '';
  });

  /* ---- 3. Purchase date + billing cycle -> renewal date ---- */
  function recalcRenewal(form) {
    var purchase = form.querySelector('.js-purchase');
    var renewal = form.querySelector('.js-renewal');
    if (!purchase || !renewal || !purchase.value) return;

    // Don't overwrite a date the admin typed in themselves.
    if (renewal.value && renewal.dataset.auto !== renewal.value) return;

    var next = addMonths(purchase.value, cycleMonths(form));
    if (!next) return;

    renewal.value = next;
    renewal.dataset.auto = next;
  }

  /** The "Mark as renewed" form counts forward from the *current* expiry. */
  function recalcNewExpiry(form) {
    var from = form.querySelector('.js-expiry-from');
    var next = form.querySelector('.js-new-expiry');
    if (!from || !next) return;
    if (next.value && next.dataset.auto !== next.value) return;

    var value = addMonths(from.value || from.dataset.current || '', cycleMonths(form));
    if (!value) return;

    next.value = value;
    next.dataset.auto = value;
  }

  document.addEventListener('input', onFormChange);
  document.addEventListener('change', onFormChange);

  function onFormChange(ev) {
    var field = ev.target;
    if (!field.form) return;

    if (field.matches('.js-purchase, .js-cycle, [name="custom_cycle_months"]')) {
      recalcRenewal(field.form);
      recalcNewExpiry(field.form);
    }

    // "Custom" is the only cycle that needs a length in months.
    if (field.matches('.js-cycle')) {
      var custom = field.form.querySelector('.js-custom-months');
      if (custom) custom.hidden = field.value !== 'custom';
    }
  }

  // Fill the renewal date on first load so a brand-new form is never blank.
  document.querySelectorAll('.js-hosting-form').forEach(recalcRenewal);
  document.querySelectorAll('.js-renew-form').forEach(recalcNewExpiry);
})();
