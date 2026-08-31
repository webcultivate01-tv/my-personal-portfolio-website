/* ============================================================
   Tejas Mehar — Portfolio scripts (shared by every page)
   Mobile menu · sticky navbar · active page link · scroll reveal ·
   animated skill bars · back-to-top · contact form
   ============================================================ */

document.addEventListener('DOMContentLoaded', () => {

  /* ---------- Current year in footer ---------- */
  const yearEl = document.getElementById('year');
  if (yearEl) yearEl.textContent = new Date().getFullYear();

  /* ---------- Highlight the current page in the nav ---------- */
  let page = location.pathname.split('/').pop();
  if (!page) page = 'index.html';               // served at "/"
  document.querySelectorAll('.nav-link, .mobile-link').forEach(link => {
    if (link.getAttribute('href') === page) link.classList.add('active');
  });

  /* ---------- Mobile sidebar (slides in from the right) ---------- */
  const menuBtn     = document.getElementById('menu-btn');
  const mobileMenu  = document.getElementById('mobile-menu');
  const overlay     = document.getElementById('mobile-overlay');
  const closeBtn    = document.getElementById('menu-close-btn');
  const iconOpen    = document.getElementById('menu-open');
  const iconClose   = document.getElementById('menu-close');

  if (menuBtn && mobileMenu && overlay) {
    const openMenu = () => {
      mobileMenu.classList.remove('translate-x-full');
      overlay.classList.remove('opacity-0', 'pointer-events-none');
      iconOpen.classList.add('hidden');
      iconClose.classList.remove('hidden');
      document.body.classList.add('overflow-hidden');
    };
    const closeMenu = () => {
      mobileMenu.classList.add('translate-x-full');
      overlay.classList.add('opacity-0', 'pointer-events-none');
      iconOpen.classList.remove('hidden');
      iconClose.classList.add('hidden');
      document.body.classList.remove('overflow-hidden');
    };

    menuBtn.addEventListener('click', () => {
      const isOpen = !mobileMenu.classList.contains('translate-x-full');
      isOpen ? closeMenu() : openMenu();
    });
    if (closeBtn) closeBtn.addEventListener('click', closeMenu);
    overlay.addEventListener('click', closeMenu);
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') closeMenu();
    });
    mobileMenu.querySelectorAll('a').forEach(link =>
      link.addEventListener('click', closeMenu)
    );
  }

  /* ---------- Sticky navbar styling on scroll ---------- */
  const navbar = document.getElementById('navbar');
  if (navbar) {
    const onScrollNavbar = () => {
      navbar.classList.toggle('bg-white/90', window.scrollY > 20);
      navbar.classList.toggle('backdrop-blur', window.scrollY > 20);
      navbar.classList.toggle('shadow-sm', window.scrollY > 20);
    };
    onScrollNavbar();
    window.addEventListener('scroll', onScrollNavbar, { passive: true });
  }

  /* ---------- Scroll reveal + animated skill bars ---------- */
  const revealEls = document.querySelectorAll('[data-reveal]');

  const fillBars = (root) => {
    root.querySelectorAll('.skill-bar').forEach(bar => {
      const target = bar.parentElement.previousElementSibling
        ?.querySelector('span:last-child')?.textContent || '0%';
      bar.style.width = target;
    });
  };

  if ('IntersectionObserver' in window) {
    const observer = new IntersectionObserver((entries, obs) => {
      entries.forEach(entry => {
        if (!entry.isIntersecting) return;
        entry.target.classList.add('revealed');
        fillBars(entry.target);
        obs.unobserve(entry.target);
      });
    }, { threshold: 0.15, rootMargin: '0px 0px -50px 0px' });

    revealEls.forEach(el => observer.observe(el));
  } else {
    revealEls.forEach(el => { el.classList.add('revealed'); fillBars(el); });
  }

  /* ---------- Back to top button ---------- */
  const toTop = document.getElementById('to-top');
  if (toTop) {
    const onScrollToTop = () => {
      const show = window.scrollY > 500;
      toTop.classList.toggle('opacity-0', !show);
      toTop.classList.toggle('pointer-events-none', !show);
    };
    onScrollToTop();
    window.addEventListener('scroll', onScrollToTop, { passive: true });
    toTop.addEventListener('click', () =>
      window.scrollTo({ top: 0, behavior: 'smooth' })
    );
  }

  /* ---------- Contact form (client-side only) ---------- */
  const form   = document.getElementById('contact-form');
  const status = document.getElementById('form-status');

  if (form) {
    const submitBtn = form.querySelector('button[type="submit"]');

    const showStatus = (msg, ok) => {
      status.textContent = msg;
      status.classList.remove('hidden', 'text-green-600', 'text-red-600');
      status.classList.add(ok ? 'text-green-600' : 'text-red-600');
    };

    // Fallback used when the backend isn't reachable (e.g. previewed as a
    // static file with no PHP): open the visitor's mail client instead.
    const mailtoFallback = (name, email, phone, subject, message) => {
      const s = encodeURIComponent(subject || `Message from ${name}`);
      const b = encodeURIComponent(`${message}\n\n— ${name} (${email}, ${phone})`);
      window.location.href = `mailto:scalewithtejas@gmail.com?subject=${s}&body=${b}`;
      showStatus('Opening your email client so you can send the message…', true);
    };

    form.addEventListener('submit', async (e) => {
      e.preventDefault();

      const name    = form.name.value.trim();
      const email   = form.email.value.trim();
      const phone   = form.phone.value.trim();
      const subject = form.subject.value.trim();
      const message = form.message.value.trim();
      const emailOk = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
      const phoneOk = /^[0-9+\-\s()]{7,20}$/.test(phone);

      if (!name || !email || !phone || !message) {
        showStatus('Please fill in your name, email, mobile number, and message.', false);
        return;
      }
      if (!emailOk) {
        showStatus('Please enter a valid email address.', false);
        return;
      }
      if (!phoneOk) {
        showStatus('Please enter a valid mobile number.', false);
        return;
      }

      if (submitBtn) { submitBtn.disabled = true; submitBtn.classList.add('opacity-70'); }
      showStatus('Sending…', true);

      try {
        const res  = await fetch('contact.php', { method: 'POST', body: new FormData(form) });
        const data = await res.json().catch(() => ({}));

        if (res.ok && data.ok) {
          showStatus("Thanks! Your message has been sent — I'll get back to you soon.", true);
          form.reset();
        } else {
          showStatus(data.error || 'Sorry, something went wrong. Please try again.', false);
        }
      } catch (err) {
        // Network / no-PHP: fall back to the mail client so the message isn't lost.
        mailtoFallback(name, email, phone, subject, message);
      } finally {
        if (submitBtn) { submitBtn.disabled = false; submitBtn.classList.remove('opacity-70'); }
      }
    });
  }

});
