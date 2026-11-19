(function () {
  'use strict';

  /* ------------------------------------------------------------------
     CONTACT CONFIGURATION
     whatsapp: international number, digits only, no "+" or spaces
               (e.g. "9725XXXXXXXX"). Leave empty until a real number
               is supplied. No number was provided, so none is invented.
     message:  optional pre-filled WhatsApp text.
     When whatsapp is set, the final "בואי נדבר" button opens WhatsApp
     and the WhatsApp icon is shown. When empty, it anchors to #contact.
  ------------------------------------------------------------------ */
  var CONFIG = {
    whatsapp: '972504488768',
    message: 'שלום תמר, אשמח לשמוע עוד על הליווי'
  };

  /* ---------- contact button ---------- */
  var contactBtn = document.getElementById('tm-contact-btn');
  var number = String(CONFIG.whatsapp || '').replace(/\D/g, '');
  if (contactBtn && number.length >= 9) {
    contactBtn.href = 'https://wa.me/' + number + (CONFIG.message ? '?text=' + encodeURIComponent(CONFIG.message) : '');
    contactBtn.target = '_blank';
    contactBtn.rel = 'noopener';
    var wa = contactBtn.querySelector('.tm-btn__wa');
    if (wa) wa.removeAttribute('hidden');
  }

  /* ---------- hamburger menu ---------- */
  var burger = document.querySelector('.tm-burger');
  var menu = document.getElementById('tm-menu');
  var header = document.querySelector('.tm-header');

  function setMenu(open, returnFocus) {
    if (!burger || !menu) return;
    burger.setAttribute('aria-expanded', open ? 'true' : 'false');
    burger.setAttribute('aria-label', open ? 'סגירת תפריט' : 'פתיחת תפריט');
    menu.hidden = !open;
    if (!open && returnFocus) burger.focus();
  }

  if (burger && menu) {
    burger.addEventListener('click', function () {
      setMenu(menu.hidden);
      if (!menu.hidden) { var first = menu.querySelector('a'); if (first) first.focus(); }
    });
    menu.addEventListener('click', function (e) {
      if (e.target.closest('a')) setMenu(false);
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && !menu.hidden) setMenu(false, true);
    });
    document.addEventListener('click', function (e) {
      if (!menu.hidden && header && !header.contains(e.target)) setMenu(false);
    });
    // keep focus inside the open menu + burger while tabbing
    menu.addEventListener('keydown', function (e) {
      if (e.key !== 'Tab') return;
      var links = menu.querySelectorAll('a');
      var last = links[links.length - 1];
      if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); burger.focus(); }
    });
  }

  /* ---------- reveal on scroll (subtle, skipped for reduced motion) ---------- */
  var items = document.querySelectorAll('.tm-reveal');
  var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  if (reduce || !('IntersectionObserver' in window)) {
    items.forEach(function (el) { el.classList.add('is-in'); });
  } else {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (en.isIntersecting) { en.target.classList.add('is-in'); io.unobserve(en.target); }
      });
    }, { rootMargin: '0px 0px -8% 0px', threshold: 0.12 });
    items.forEach(function (el, i) {
      el.style.transitionDelay = (i % 3) * 70 + 'ms';
      io.observe(el);
    });
  }
})();
