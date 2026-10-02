(function () {
  'use strict';
  var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var $ = function (s, c) { return (c || document).querySelector(s); };
  var $$ = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };

  /* Scroll reveal */
  var io = 'IntersectionObserver' in window ? new IntersectionObserver(function (es) {
    es.forEach(function (e) { if (e.isIntersecting) { e.target.classList.add('in'); io.unobserve(e.target); } });
  }, { threshold: 0.12, rootMargin: '0px 0px -6% 0px' }) : null;
  $$('.rv').forEach(function (el) { io ? io.observe(el) : el.classList.add('in'); });

  /* Count-up numbers */
  function countUp(el) {
    var end = parseInt(el.getAttribute('data-count'), 10), dur = 1400, t0 = null;
    if (reduce) return;
    function step(t) {
      if (!t0) t0 = t;
      var p = Math.min((t - t0) / dur, 1), v = Math.round(end * (1 - Math.pow(1 - p, 3)));
      el.textContent = v; if (p < 1) requestAnimationFrame(step);
    }
    el.textContent = '0'; requestAnimationFrame(step);
  }
  if (io) {
    var cio = new IntersectionObserver(function (es) {
      es.forEach(function (e) { if (e.isIntersecting) { countUp(e.target); cio.unobserve(e.target); } });
    }, { threshold: 0.6 });
    $$('[data-count]').forEach(function (el) { cio.observe(el); });
  }

  /* Header, progress bar, parallax */
  var hdr = $('#hdr'), bar = $('#progress'), bg = $('#bgimg'), ticking = false;
  function onScroll() {
    var y = window.scrollY, h = document.documentElement.scrollHeight - window.innerHeight;
    hdr.classList.toggle('scrolled', y > 20);
    bar.style.transform = 'scaleX(' + (h > 0 ? y / h : 0) + ')';
    if (!reduce) bg.style.transform = 'translate3d(0,' + (y * -0.12) + 'px,0)';
    ticking = false;
  }
  window.addEventListener('scroll', function () { if (!ticking) { ticking = true; requestAnimationFrame(onScroll); } }, { passive: true });
  onScroll();

  /* Scroll-spy for nav links */
  var links = $$('[data-nav]'), dlinks = $$('#deskNav a');
  var ids = ['workshop', 'method', 'pricing', 'faq'];
  if ('IntersectionObserver' in window) {
    var spy = new IntersectionObserver(function (es) {
      es.forEach(function (e) {
        if (!e.isIntersecting) return;
        var id = e.target.id;
        links.forEach(function (a) {
          var on = a.getAttribute('data-nav') === id;
          a.classList.toggle('text-primary', on); a.classList.toggle('font-bold', on);
          a.classList.toggle('text-on-surface-variant', !on);
        });
        dlinks.forEach(function (a) { a.classList.toggle('text-primary', a.getAttribute('href') === '#' + id); });
      });
    }, { rootMargin: '-45% 0px -50% 0px' });
    ids.forEach(function (id) { var el = document.getElementById(id); if (el) spy.observe(el); });
  }

  /* Hero particles */
  var pc = $('#particles');
  if (pc && !reduce) {
    for (var i = 0; i < 18; i++) {
      var s = document.createElement('span'); s.className = 'particle';
      s.style.left = (Math.random() * 100) + '%';
      s.style.animationDelay = (Math.random() * 9) + 's';
      s.style.animationDuration = (7 + Math.random() * 6) + 's';
      s.style.transform = 'scale(' + (0.5 + Math.random() * 1.2) + ')';
      pc.appendChild(s);
    }
  }

  /* Countdown to the live workshop: 24/11 11:00 Israel time */
  var cd = $('#countdown');
  if (cd) {
    var now = new Date(), yr = now.getFullYear();
    var target = new Date(yr + '-11-24T11:00:00+02:00');
    if (target < now) target = new Date((yr + 1) + '-11-24T11:00:00+02:00');
    var parts = { d: $('[data-u=d]', cd), h: $('[data-u=h]', cd), m: $('[data-u=m]', cd), s: $('[data-u=s]', cd) };
    var pad = function (n) { return n < 10 ? '0' + n : '' + n; };
    var tick = function () {
      var diff = Math.max(0, target - new Date()), sec = Math.floor(diff / 1000);
      parts.d.textContent = pad(Math.floor(sec / 86400));
      parts.h.textContent = pad(Math.floor(sec % 86400 / 3600));
      parts.m.textContent = pad(Math.floor(sec % 3600 / 60));
      parts.s.textContent = pad(sec % 60);
    };
    tick(); setInterval(tick, 1000);
  }

  /* FAQ accordion */
  $$('.faq-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var item = btn.parentElement, open = item.classList.contains('open');
      $$('.faq-item.open').forEach(function (i) { i.classList.remove('open'); $('.faq-btn', i).setAttribute('aria-expanded', 'false'); });
      if (!open) { item.classList.add('open'); btn.setAttribute('aria-expanded', 'true'); }
    });
  });

  /* Lead form */
  var form = $('#leadForm'), msg = $('#formMsg'), sbtn = $('#submitBtn');
  if (form) form.addEventListener('submit', function (ev) {
    ev.preventDefault();
    var d = new FormData(form), name = (d.get('name') || '').trim(), phone = (d.get('phone') || '').trim(), email = (d.get('email') || '').trim();
    if (name.length < 2) return err('נא למלא שם מלא');
    if (!/^[0-9+\-\s()]{7,20}$/.test(phone)) return err('נא להזין מספר טלפון תקין');
    if (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(email)) return err('נא להזין כתובת מייל תקינה');
    msg.textContent = ''; sbtn.disabled = true;
    fetch('send.php', { method: 'POST', body: d })
      .then(function (r) { if (!r.ok) throw 0; return r.json(); })
      .then(function () { form.classList.add('hidden'); $('#formOk').classList.remove('hidden'); })
      .catch(function () { err('אירעה שגיאה בשליחה. אפשר לפנות אלינו בוואטסאפ.'); sbtn.disabled = false; });
    function err(t) { msg.textContent = t; }
  });
})();
