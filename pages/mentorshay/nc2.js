/* /new-customer-v2/ interaction + motion layer.
   Layer 1 (no library): click-to-play videos, reflection cards, glowing stars.
   Layer 2 (GSAP 3.15 core + ScrollTrigger + SplitText, loaded once from jsDelivr, only on this page): cinematic hero, text reveals, scroll storytelling.
   If the library cannot load, the CSS fail-safe + this file's catch show everything statically. All motion respects prefers-reduced-motion. */
(function () {
  'use strict';
  var d = document, html = d.documentElement, win = window;
  var reduce = win.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var CDN = 'https://cdn.jsdelivr.net/npm/gsap@3.15.1/dist/';
  var $ = function (s, r) { return (r || d).querySelector(s); };
  var $$ = function (s, r) { return [].slice.call((r || d).querySelectorAll(s)); };
  var lite = !!(navigator.hardwareConcurrency && navigator.hardwareConcurrency <= 4) || !!(navigator.connection && navigator.connection.saveData);
  if (lite) html.classList.add('nc2-lite');

  /* ---------- analytics: reuse the Meta Pixel / dataLayer that already exist on the site (no new tracker) ---------- */
  function ev(name, id, extra) {
    try { if (win.fbq) { var p = { video: id, variant: 'v2' }; for (var k in extra) p[k] = extra[k]; win.fbq('trackCustom', name, p); } } catch (e) {}
    try { if (win.dataLayer) win.dataLayer.push({ event: name, video: id, variant: 'v2' }); } catch (e) {}
  }

  /* ---------- layer 1a: click-to-play videos (never autoplay) ---------- */
  var vids = $$('.nc2-video');
  vids.forEach(function (box) {
    var btn = $('.nc2-play', box), id = box.getAttribute('data-video'), sent = {};
    btn.addEventListener('click', function () {
      var src = box.getAttribute('data-src'); if (!src) return;
      var v = $('video', box);
      if (!v) {
        v = d.createElement('video'); v.src = src; v.controls = true; v.playsInline = true; v.setAttribute('playsinline', ''); v.preload = 'metadata'; v.setAttribute('aria-label', box.getAttribute('aria-label'));
        if (box.getAttribute('data-vtt')) { var t = d.createElement('track'); t.kind = 'captions'; t.srclang = 'he'; t.label = 'עברית'; t.src = box.getAttribute('data-vtt'); t['default'] = true; v.appendChild(t); }
        box.appendChild(v);
        v.addEventListener('play', function () { vids.forEach(function (o) { var ov = $('video', o); if (ov && ov !== v) ov.pause(); }); if (!sent.p) { sent.p = 1; ev('NewCustomerVideoPlay', id); } });
        v.addEventListener('timeupdate', function () { if (!v.duration) return; var pc = Math.floor(v.currentTime / v.duration * 4) * 25; if (pc >= 25 && !sent[pc]) { sent[pc] = 1; ev('NewCustomerVideoProgress', id, { percent: pc }); } });
        v.addEventListener('ended', function () { if (!sent[100]) { sent[100] = 1; ev('NewCustomerVideoProgress', id, { percent: 100 }); } });
      }
      btn.style.display = 'none'; $('.nc2-vlabel', box).style.display = 'none';
      var pr = v.play(); if (pr && pr['catch']) pr['catch'](function () {});
    });
    if ('IntersectionObserver' in win) new IntersectionObserver(function (es) { if (!es[0].isIntersecting) { var v = $('video', box); if (v) v.pause(); } }, { threshold: 0 }).observe(box);
  });

  /* ---------- layer 1b: reflection cards ---------- */
  var qs = $$('.nc2-q'), ready = $('.nc2-ready'), opened = {};
  qs.forEach(function (q, i) {
    q.addEventListener('click', function () {
      var open = q.getAttribute('aria-expanded') === 'true';
      q.setAttribute('aria-expanded', open ? 'false' : 'true');
      if (!open) { opened[i] = 1; if (Object.keys(opened).length === qs.length && ready) { ready.classList.add('on'); } }
    });
  });

  /* ---------- layer 1c: glowing stars (cheap sprites; paused off-screen / hidden tab; static when reduced) ---------- */
  function sprite(spark) {
    var s = 64, c = d.createElement('canvas'); c.width = c.height = s; var g = c.getContext('2d'), r = g.createRadialGradient(32, 32, 0, 32, 32, 32);
    r.addColorStop(0, 'rgba(255,250,225,1)'); r.addColorStop(.18, 'rgba(255,226,150,.85)'); r.addColorStop(.5, 'rgba(233,196,110,.22)'); r.addColorStop(1, 'rgba(233,196,110,0)'); g.fillStyle = r; g.fillRect(0, 0, s, s);
    if (spark) { g.globalCompositeOperation = 'lighter'; var l = g.createLinearGradient(0, 32, 64, 32); l.addColorStop(0, 'rgba(255,240,190,0)'); l.addColorStop(.5, 'rgba(255,248,215,.95)'); l.addColorStop(1, 'rgba(255,240,190,0)'); g.fillStyle = l; g.fillRect(2, 31, 60, 2);
      var v = g.createLinearGradient(32, 0, 32, 64); v.addColorStop(0, 'rgba(255,240,190,0)'); v.addColorStop(.5, 'rgba(255,248,215,.95)'); v.addColorStop(1, 'rgba(255,240,190,0)'); g.fillStyle = v; g.fillRect(31, 2, 2, 60); }
    return c;
  }
  var SP = [sprite(false), sprite(true)];
  function stars(cv, host, n) {
    if (!cv || !host || !cv.getContext) return null;
    var ctx = cv.getContext('2d'), dpr = Math.min(2, win.devicePixelRatio || 1), W = 0, H = 0, P = [], run = false;
    function mk(init) { var sp = Math.random() < .16; return { x: Math.random() * W, y: init ? Math.random() * H : H + 20, r: sp ? 9 + Math.random() * 9 : 2.5 + Math.random() * 5.5, v: .12 + Math.random() * .34, a: .45 + Math.random() * .55, tw: .6 + Math.random() * 1.8, ph: Math.random() * 6.28, dx: .15 + Math.random() * .5, sp: sp ? 1 : 0 }; }
    function size() { var r = host.getBoundingClientRect(); W = r.width; H = r.height; cv.width = Math.round(W * dpr); cv.height = Math.round(H * dpr); ctx.setTransform(dpr, 0, 0, dpr, 0, 0); }
    function draw(t, step) { ctx.clearRect(0, 0, W, H); for (var i = 0; i < P.length; i++) { var p = P[i]; if (step) { p.y -= p.v; p.x += Math.sin(t / 1700 * p.dx + p.ph) * .22; if (p.y < -24) { P[i] = p = mk(false); } }
      var al = p.a * (.5 + .5 * Math.sin(t / 1000 * p.tw + p.ph)); if (al < .03) continue; ctx.globalAlpha = al; var sz = p.r * 2; ctx.drawImage(SP[p.sp], p.x - p.r, p.y - p.r, sz, sz); } ctx.globalAlpha = 1; }
    function frame(t) { if (!run) return; draw(t, true); requestAnimationFrame(frame); }
    function start() { if (run || reduce) return; run = true; requestAnimationFrame(frame); }
    size(); for (var i = 0; i < n; i++) P.push(mk(true)); if (reduce) draw(0, false);
    win.addEventListener('resize', function () { size(); if (reduce) draw(0, false); });
    if ('IntersectionObserver' in win) new IntersectionObserver(function (es) { if (es[0].isIntersecting) start(); else run = false; }, { threshold: 0 }).observe(host); else start();
    d.addEventListener('visibilitychange', function () { if (d.hidden) run = false; else start(); });
    return { burst: function (k) { for (var i = 0; i < k; i++) { var p = mk(true); p.v *= 2.2; P.push(p); } } };
  }
  var small = win.innerWidth < 700;
  var heroStars = stars($('.nc2-hero .nc2-stars'), $('.nc2-hero'), small ? (lite ? 20 : 40) : (lite ? 36 : 80));
  var endStars = stars($('.nc2-finale .nc2-stars'), $('.nc2-finale'), small ? 22 : 46);

  /* ---------- layer 2: GSAP ---------- */
  if (reduce) { html.classList.remove('nc2-js'); return; }
  function load(src) { return new Promise(function (res, rej) { var s = d.createElement('script'); s.src = src; s.async = true; s.onload = res; s.onerror = rej; d.head.appendChild(s); }); }
  var need = [];
  var base = win.gsap ? Promise.resolve() : load(CDN + 'gsap.min.js');
  base.then(function () {
    var ps = [];
    if (!win.ScrollTrigger) ps.push(load(CDN + 'ScrollTrigger.min.js'));
    if (!win.SplitText) ps.push(load(CDN + 'SplitText.min.js'));
    return Promise.all(ps);
  }).then(function () { try { motion(); } catch (e) { console.warn('nc2 motion failed', e); fallback(); } })['catch'](function () { fallback(); });
  function fallback() { html.classList.remove('nc2-js'); }

  function motion() {
    var gsap = win.gsap, ScrollTrigger = win.ScrollTrigger, SplitText = win.SplitText;
    gsap.registerPlugin(ScrollTrigger, SplitText);
    ScrollTrigger.config({ ignoreMobileResize: true });
    $$('.nc2-card svg *, .nc2-node svg *, .nc2-pill svg *, .nc2-pathsvg path, .nc2-sign svg path').forEach(function (e) { e.setAttribute('pathLength', '1'); });
    var mm = gsap.matchMedia();
    mm.add({ all: '(min-width: 1px)', desk: '(min-width: 1024px)', fine: '(hover: hover) and (pointer: fine)' }, function (ctx) {
      var desk = ctx.conditions.desk, fine = ctx.conditions.fine, cleanup = [];

      /* overall progress + chapter rail */
      gsap.to('.nc2-bar i', { scaleX: 1, ease: 'none', scrollTrigger: { trigger: d.body, start: 0, end: 'max', scrub: .3 } });
      var dots = $$('.nc2-rail i');
      $$('[data-ch]').forEach(function (sec) {
        ScrollTrigger.create({ trigger: sec, start: 'top 55%', end: 'bottom 55%', onToggle: function (self) { if (!self.isActive) return; dots.forEach(function (x) { x.classList.toggle('on', x.getAttribute('data-for') === sec.getAttribute('data-ch')); }); } });
      });

      /* hero: cinematic entrance */
      gsap.set('.nc2-hero [data-split]', { autoAlpha: 1 });
      var tl = gsap.timeline({ defaults: { ease: 'power4.out' } });
      tl.from('.nc2-hero-img', { scale: 1.18, autoAlpha: 0, duration: 2.2, ease: 'power2.out' }, 0)
        .from('.nc2-logo', { y: -26, autoAlpha: 0, duration: 1.2 }, .4)
        .from('.nc2-rings circle', { scale: .55, autoAlpha: 0, transformOrigin: '50% 50%', duration: 2.4, stagger: .22 }, .3)
        .from('.nc2-hero .nc2-eyebrow', { x: 40, autoAlpha: 0, duration: 1 }, .7);
      $$('.nc2-hero h1 [data-split]').forEach(function (el, i) {
        SplitText.create(el, { type: 'lines', mask: 'lines', autoSplit: true,
          onSplit: function (self) { return gsap.from(self.lines, { yPercent: 118, duration: 1.3, stagger: .12, delay: .75 + i * .35, ease: 'power4.out' }); } });
      });
      tl.from('.nc2-hero .nc2-lead', { y: 30, autoAlpha: 0, duration: 1.2 }, 1.9)
        .from('.nc2-hero .nc2-chips span', { y: 20, autoAlpha: 0, duration: .9, stagger: .1 }, 2.15)
        .from('.nc2-hero .nc2-cue', { autoAlpha: 0, y: 20, duration: 1 }, 2.4);
      /* hero: slow halo drift + parallax */
      var halo = $('.nc2-halo');
      if (halo) {
        var W = function () { return win.innerWidth; }, H = function () { return $('.nc2-hero').offsetHeight; };
        gsap.set(halo, { x: function () { return W() * .08; }, y: function () { return H() * .02; } });
        gsap.timeline({ repeat: -1, yoyo: true, defaults: { duration: 9, ease: 'sine.inOut' } })
          .to(halo, { x: function () { return W() * .62; }, y: function () { return H() * .12; }, scale: 1.12 })
          .to(halo, { x: function () { return W() * .3; }, y: function () { return H() * .48; }, scale: .94 })
          .to(halo, { x: function () { return W() * .78; }, y: function () { return H() * .4; }, scale: 1.1 });
      }
      gsap.to('.nc2-hero-img', { yPercent: 10, ease: 'none', scrollTrigger: { trigger: '.nc2-hero', start: 'top top', end: 'bottom top', scrub: true } });
      gsap.to('.nc2-hero-copy', { yPercent: -14, autoAlpha: .2, ease: 'none', scrollTrigger: { trigger: '.nc2-hero', start: '55% top', end: 'bottom top', scrub: true } });

      /* desktop pointer: golden glow follows the cursor, hero photo drifts a few pixels */
      if (fine && !lite) {
        var glow = $('.nc2-glow'), gx = gsap.quickTo(glow, 'x', { duration: .7, ease: 'power3' }), gy = gsap.quickTo(glow, 'y', { duration: .7, ease: 'power3' }), shown = false;
        var ix = gsap.quickTo('.nc2-hero-img', 'x', { duration: 1.4, ease: 'power3' }), iy = gsap.quickTo('.nc2-hero-img', 'y', { duration: 1.4, ease: 'power3' });
        var mv = function (e) { if (!shown) { shown = true; gsap.to(glow, { opacity: 1, duration: .8 }); } gx(e.clientX); gy(e.clientY); ix((e.clientX / win.innerWidth - .5) * -26); iy((e.clientY / win.innerHeight - .5) * -18); };
        win.addEventListener('pointermove', mv, { passive: true });
        d.addEventListener('pointerleave', function () { gsap.to(glow, { opacity: 0, duration: .6 }); shown = false; });
        /* cards: spotlight + gentle 3D tilt */
        $$('.nc2-card, .nc2-q').forEach(function (c) {
          var rx = gsap.quickTo(c, 'rotationX', { duration: .6, ease: 'power3' }), ry = gsap.quickTo(c, 'rotationY', { duration: .6, ease: 'power3' });
          gsap.set(c, { transformPerspective: 900 });
          c.addEventListener('pointermove', function (e) { var r = c.getBoundingClientRect(), px = (e.clientX - r.left) / r.width, py = (e.clientY - r.top) / r.height; c.style.setProperty('--mx', (px * 100) + '%'); c.style.setProperty('--my', (py * 100) + '%'); ry((px - .5) * 9); rx((py - .5) * -9); });
          c.addEventListener('pointerleave', function () { rx(0); ry(0); });
        });
        cleanup.push(function () { win.removeEventListener('pointermove', mv); });
      }

      /* headings: mask line reveals + gold underline */
      $$('[data-split]').forEach(function (el) {
        if (el.closest('.nc2-hero')) return;
        SplitText.create(el, { type: 'lines', mask: 'lines', autoSplit: true,
          onSplit: function (self) { gsap.set(el, { autoAlpha: 1 }); return gsap.from(self.lines, { yPercent: 112, duration: 1.15, stagger: .11, ease: 'power4.out', scrollTrigger: { trigger: el, start: 'top 87%', once: true } }); } });
      });
      /* generic rise-in, batched so groups stagger */
      ScrollTrigger.batch('[data-rv]:not(.nc2-video)', { start: 'top 90%', once: true,
        onEnter: function (els) { gsap.fromTo(els, { y: 46, autoAlpha: 0 }, { y: 0, autoAlpha: 1, duration: 1.15, stagger: .13, ease: 'power3.out', overwrite: true }); } });

      /* icons draw themselves */
      $$('.nc2-card, .nc2-pill, .nc2-step').forEach(function (c) {
        var parts = $$('svg *', c); if (!parts.length) return;
        gsap.to(parts, { strokeDashoffset: 0, duration: 1.7, stagger: .12, ease: 'power2.inOut', scrollTrigger: { trigger: c, start: 'top 88%', once: true } });
      });

      /* manifesto: words light up as you scroll */
      $$('[data-manifest]').forEach(function (el) {
        var sp = SplitText.create(el, { type: 'words', wordsClass: 'w' });
        gsap.set(el, { autoAlpha: 1 });
        gsap.fromTo(sp.words, { opacity: .13 }, { opacity: 1, stagger: .1, ease: 'none', scrollTrigger: { trigger: el, start: 'top 78%', end: 'bottom 52%', scrub: true } });
      });

      /* videos: frame opens up, slight parallax on desktop */
      $$('.nc2-video').forEach(function (v) {
        gsap.fromTo(v, { clipPath: 'inset(14% 9% round 44px)', scale: .94, autoAlpha: 0 }, { clipPath: 'inset(0% 0% round 38px)', scale: 1, autoAlpha: 1, duration: 1.5, ease: 'power4.out', scrollTrigger: { trigger: v, start: 'top 88%', once: true } });
        if (desk) gsap.fromTo(v.parentNode, { yPercent: 5 }, { yPercent: -5, ease: 'none', scrollTrigger: { trigger: v.parentNode, start: 'top bottom', end: 'bottom top', scrub: true } });
      });

      /* pillars: pinned scroll story on desktop, stacked focus on small screens */
      var pills = $$('.nc2-pill'), bigs = $$('.nc2-pbig .it'), cur = -1;
      function setOn(i) { if (i === cur) return; cur = i; pills.forEach(function (p, k) { p.classList.toggle('on', k === i); }); }
      if (desk && pills.length === 4 && bigs.length === 4) {
        setOn(0); gsap.set(bigs, { autoAlpha: 0, yPercent: 14 }); gsap.set(bigs[0], { autoAlpha: 1, yPercent: 0 });
        var pt = gsap.timeline({ scrollTrigger: { trigger: '.nc2-pstage', start: 'top top', end: '+=300%', pin: true, scrub: .7, anticipatePin: 1,
          onUpdate: function (self) { setOn(Math.min(3, Math.floor(self.progress * 4))); } } });
        for (var i = 1; i < 4; i++) { pt.to(bigs[i - 1], { autoAlpha: 0, yPercent: -14, duration: .45, ease: 'power2.in' }, i - .3).to(bigs[i], { autoAlpha: 1, yPercent: 0, duration: .55, ease: 'power2.out' }, i - .05); }
        pt.to({}, { duration: .35 });
      } else {
        pills.forEach(function (p, k) { ScrollTrigger.create({ trigger: p, start: 'top 62%', end: 'bottom 38%', toggleClass: { targets: p, className: 'on' } }); });
      }

      /* meeting: the path draws itself between the three steps (desktop) */
      if (desk) { gsap.fromTo('.nc2-pathsvg path', { strokeDashoffset: 1 }, { strokeDashoffset: 0, ease: 'none', scrollTrigger: { trigger: '.nc2-steps', start: 'top 72%', end: 'bottom 62%', scrub: true } }); }
      gsap.from('.nc2-node', { scale: .45, autoAlpha: 0, duration: 1.1, stagger: .35, ease: 'back.out(1.8)', scrollTrigger: { trigger: '.nc2-steps', start: 'top 80%', once: true } });

      /* finale */
      var ft = gsap.timeline({ scrollTrigger: { trigger: '.nc2-finale', start: 'top 60%', once: true } });
      ft.from('.nc2-sign', { autoAlpha: 0, scale: .9, duration: 1.2, ease: 'power4.out' }, .5)
        .fromTo('.nc2-sign svg path', { strokeDashoffset: 1 }, { strokeDashoffset: 0, duration: 1.9, ease: 'power2.inOut' }, .9)
        .call(function () { if (endStars) endStars.burst(small ? 14 : 30); }, null, 1.1);
      gsap.to('.nc2-finale-img', { yPercent: 8, scale: 1.06, ease: 'none', scrollTrigger: { trigger: '.nc2-finale', start: 'top bottom', end: 'bottom top', scrub: true } });
      return function () { cleanup.forEach(function (f) { f(); }); };
    });

    html.classList.add('nc2-live');
    win.addEventListener('load', function () { ScrollTrigger.refresh(); });
    if (d.fonts && d.fonts.ready) d.fonts.ready.then(function () { ScrollTrigger.refresh(); });
    win.addEventListener('pagehide', function () { mm.revert(); });
  }
})();
