/* ============================================================
   Password show/hide — wraps every password field on the page in
   a .password-field container with an eye button that flips the
   input between type="password" and type="text". Works anywhere a
   password input exists (login, reset, change password) with no
   per-page markup needed.
   ============================================================ */
(function () {
  'use strict';

  var EYE = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/></svg>';
  var EYE_OFF = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.94 10.94 0 0 1 12 19c-7 0-11-7-11-7a18.6 18.6 0 0 1 5.06-5.94M9.9 4.24A10.94 10.94 0 0 1 12 5c7 0 11 7 11 7a18.6 18.6 0 0 1-2.16 3.19M14.12 14.12a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>';

  var inputs = document.querySelectorAll('input[type="password"]');

  for (var i = 0; i < inputs.length; i++) {
    (function (input) {
      var wrap = document.createElement('div');
      wrap.className = 'password-field';
      input.parentNode.insertBefore(wrap, input);
      wrap.appendChild(input);

      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'password-toggle';
      btn.setAttribute('aria-label', 'Show password');
      btn.innerHTML = EYE;
      wrap.appendChild(btn);

      btn.addEventListener('click', function () {
        var showing = input.type === 'password';
        input.type = showing ? 'text' : 'password';
        btn.innerHTML = showing ? EYE_OFF : EYE;
        btn.setAttribute('aria-label', showing ? 'Hide password' : 'Show password');
      });
    }(inputs[i]));
  }
}());
