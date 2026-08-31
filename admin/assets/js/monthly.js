/* ============================================================
   Monthly Clients module — form behaviour.

   Every job here is a convenience: the server works all of it out
   again on submit (and refuses anything invalid), so the module
   still behaves correctly with JavaScript switched off.

     1. Reveal / hide the "+ Add", "Edit" and action forms — and
        open the one a link points at, e.g. #payment-form.
     2. Show the discount value field only when a discount is set,
        and preview what one invoice will come to.
     3. Invoice date + payment terms -> due date, and a live total
        as the amounts on the invoice form are edited.
     4. Picking an invoice fills in what is still outstanding, and
        caps the payment at it.
   ============================================================ */
(function () {
  'use strict';

  /** Months covered by each billing frequency. Mirrors MonthlyClient::FREQUENCY_MONTHS. */
  var FREQUENCY_MONTHS = {
    monthly: 1,
    quarterly: 3,
    half_yearly: 6,
    yearly: 12
  };

  function money(n) {
    return '₹' + (Math.round(n * 100) / 100).toLocaleString('en-IN', {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2
    });
  }

  function num(el) {
    return el && el.value !== '' ? parseFloat(el.value) || 0 : 0;
  }

  /** Add days to a yyyy-mm-dd date. Mirrors MonthlyClient::addDays(). */
  function addDays(iso, days) {
    var parts = /^(\d{4})-(\d{2})-(\d{2})$/.exec(iso);
    if (!parts) return '';
    var d = new Date(Date.UTC(+parts[1], +parts[2] - 1, +parts[3]));
    d.setUTCDate(d.getUTCDate() + days);
    return d.toISOString().slice(0, 10);
  }

  /* ---- 1. Reveal / hide a form, flipping the button's label ---- */
  function toggleForm(id, open) {
    var form = document.getElementById(id);
    if (!form) return;

    form.hidden = !open;

    // Every button pointing at this form shows the same label.
    document.querySelectorAll('[data-toggle="' + id + '"]').forEach(function (b) {
      if (b.dataset.labelOpen) b.textContent = open ? b.dataset.labelClose : b.dataset.labelOpen;
    });

    // A read-only summary marked as this form's twin swaps out while editing.
    var view = document.getElementById(id + '-view');
    if (view) view.hidden = open;

    if (open) {
      form.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      var first = form.querySelector('input:not([type=hidden]):not([readonly]), select, textarea');
      if (first) first.focus();
    }
  }

  document.addEventListener('click', function (ev) {
    var btn = ev.target.closest('[data-toggle]');
    if (!btn) return;
    var form = document.getElementById(btn.dataset.toggle);
    if (form) toggleForm(btn.dataset.toggle, form.hidden);
  });

  // A link like ...#payment-form should open that form, not just jump to a
  // hidden panel — the dashboard's "Record payment" buttons rely on this.
  function openFromHash() {
    var id = (location.hash || '').replace('#', '');
    if (id && document.getElementById(id) && document.querySelector('[data-toggle="' + id + '"]')) {
      toggleForm(id, true);
    }
  }
  window.addEventListener('hashchange', openFromHash);

  /* ---- 2. Discount field + "each invoice will come to" preview ---- */
  function refreshClientForm(form) {
    var type = form.querySelector('.js-discount-type');
    var wrap = form.querySelector('.js-discount-value');
    if (type && wrap) wrap.hidden = type.value === 'none';

    var preview = form.querySelector('.js-preview');
    if (!preview) return;

    var monthly = num(form.querySelector('.js-amount'));
    var freq = form.querySelector('.js-frequency');
    var months = freq ? (FREQUENCY_MONTHS[freq.value] || 1) : 1;
    if (!monthly) {
      preview.textContent = '—';
      return;
    }

    var recurring = monthly * months;
    var discount = 0;
    if (type && type.value === 'percent') {
      discount = recurring * (num(form.querySelector('.js-discount')) / 100);
    } else if (type && type.value === 'amount') {
      discount = num(form.querySelector('.js-discount'));
    }
    discount = Math.max(0, Math.min(discount, recurring));

    var taxable = recurring - discount;
    var total = taxable + taxable * (num(form.querySelector('.js-tax')) / 100);

    preview.textContent = money(total)
      + (months > 1 ? ' every ' + months + ' months' : ' every month');
  }

  /* ---- 3. Invoice form: due date and running total ---- */
  function refreshInvoiceForm(form) {
    var invoiceDate = form.querySelector('.js-invoice-date');
    var dueDate = form.querySelector('.js-due-date');
    var termDays = form.querySelector('.js-term-days');

    // Don't overwrite a due date the admin typed in themselves.
    if (invoiceDate && dueDate && termDays && invoiceDate.value
        && (!dueDate.value || dueDate.dataset.auto === dueDate.value)) {
      var next = addDays(invoiceDate.value, parseInt(termDays.value, 10) || 0);
      if (next) {
        dueDate.value = next;
        dueDate.dataset.auto = next;
      }
    }

    var out = form.querySelector('.js-invoice-total');
    if (!out) return;

    var recurring = num(form.querySelector('.js-recurring'));
    var discount = Math.max(0, Math.min(num(form.querySelector('.js-discount-amount')), recurring));
    var taxable = recurring - discount;
    out.textContent = money(taxable + taxable * (num(form.querySelector('.js-tax-percent')) / 100));
  }

  /* ---- 4. Payment form: fill and cap the amount from the invoice ---- */
  function refreshPaymentForm(form, resetAmount) {
    var select = form.querySelector('.js-invoice-select');
    var amount = form.querySelector('.js-pay-amount');
    var hint = form.querySelector('.js-pay-hint');
    if (!select || !amount) return;

    var opt = select.options[select.selectedIndex];
    var balance = opt ? parseFloat(opt.dataset.balance) || 0 : 0;

    amount.max = balance;
    if (resetAmount || !amount.value || parseFloat(amount.value) > balance) {
      amount.value = balance.toFixed(2);
    }
    if (hint) {
      hint.textContent = money(balance) + ' outstanding — pay it in full, or enter less for a part payment.';
    }
  }

  /* ---- Wiring ---- */
  function onChange(ev) {
    var form = ev.target.form;
    if (!form) return;

    if (form.classList.contains('js-monthly-form')) refreshClientForm(form);
    if (form.classList.contains('js-invoice-form')) refreshInvoiceForm(form);
    if (form.classList.contains('js-payment-form')) {
      refreshPaymentForm(form, ev.target.classList.contains('js-invoice-select'));
    }
  }

  document.addEventListener('input', onChange);
  document.addEventListener('change', onChange);

  // Fill everything in once on load so a form is never blank or stale.
  document.querySelectorAll('.js-monthly-form').forEach(refreshClientForm);
  document.querySelectorAll('.js-invoice-form').forEach(refreshInvoiceForm);
  document.querySelectorAll('.js-payment-form').forEach(function (f) { refreshPaymentForm(f, true); });
  openFromHash();
}());
