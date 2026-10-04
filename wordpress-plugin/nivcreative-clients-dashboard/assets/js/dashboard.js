/* NivCreative – לוח ניהול לקוחות. Vanilla JS, no build step. All data comes from the private REST API. */
(function () {
  'use strict';

  var CFG = window.NIVC_CONFIG;
  var root = document.getElementById('nivc-root');
  if (!CFG || !root) return;

  /* ------------------------------------------------------------------ utils */

  function h(tag, attrs) {
    var el = document.createElement(tag);
    if (attrs) {
      Object.keys(attrs).forEach(function (k) {
        var v = attrs[k];
        if (v === null || v === undefined || v === false) return;
        if (k === 'class') el.className = v;
        else if (k.slice(0, 2) === 'on' && typeof v === 'function') el.addEventListener(k.slice(2), v);
        else if (k === 'value' || k === 'checked' || k === 'disabled' || k === 'selected') el[k] = v;
        else el.setAttribute(k, v === true ? '' : v);
      });
    }
    for (var i = 2; i < arguments.length; i++) append(el, arguments[i]);
    return el;
  }
  function append(el, c) {
    if (c === null || c === undefined || c === false) return;
    if (Array.isArray(c)) { c.forEach(function (x) { append(el, x); }); return; }
    el.appendChild(c.nodeType ? c : document.createTextNode(String(c)));
  }
  function clear(el) { while (el.firstChild) el.removeChild(el.firstChild); return el; }

  var ICONS = {
    plus: '<path d="M12 5v14M5 12h14"/>',
    edit: '<path d="M4 20h4L19 9l-4-4L4 16v4z"/><path d="M13.5 6.5l4 4"/>',
    trash: '<path d="M4 7h16M10 11v6M14 11v6M6 7l1 12h10l1-12M9 7V4h6v3"/>',
    renew: '<path d="M20 11a8 8 0 0 0-14-4M4 5v4h4"/><path d="M4 13a8 8 0 0 0 14 4M20 19v-4h-4"/>',
    history: '<circle cx="12" cy="12" r="8"/><path d="M12 8v4l3 2"/>',
    whatsapp: '<path d="M4 20l1.3-4A8 8 0 1 1 8 18.7L4 20z"/><path d="M9 9.5c.5 2 2.500 4 5 5l1.200-1.200-2-1-.8.8c-.8-.4-1.600-1.200-2-2l.8-.8-1-2L9 9.500z"/>',
    external: '<path d="M14 4h6v6M20 4l-9 9M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5"/>',
    search: '<circle cx="11" cy="11" r="6"/><path d="M20 20l-4-4"/>',
    filter: '<path d="M4 6h16M7 12h10M10 18h4"/>',
    close: '<path d="M6 6l12 12M18 6L6 18"/>',
    check: '<path d="M5 12.500l4.500 4.500L19 7.500"/>',
    warn: '<path d="M12 4l9 16H3L12 4z"/><path d="M12 10v4M12 17.500v.01"/>',
    cross: '<circle cx="12" cy="12" r="8"/><path d="M9 9l6 6M15 9l-6 6"/>',
    users: '<circle cx="9" cy="8" r="3"/><path d="M3 20c0-3.500 2.700-6 6-6s6 2.500 6 6M16 5a3 3 0 0 1 0 6M18 14c1.800.8 3 2.800 3 6"/>',
    eye: '<path d="M2 12s3.500-6 10-6 10 6 10 6-3.500 6-10 6S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
    shekel: '<path d="M7 19V6h5a3 3 0 0 1 3 3v3M17 5v13H12a3 3 0 0 1-3-3v-3"/>',
    chart: '<path d="M4 20V10M10 20V4M16 20v-8M22 20H2"/>',
    arrowUp: '<path d="M12 19V5M6 11l6-6 6 6"/>',
    arrowDown: '<path d="M12 5v14M6 13l6 6 6-6"/>',
    info: '<circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8v.01"/>',
    gear: '<circle cx="12" cy="12" r="3"/><path d="M12 3v2.500M12 19v2.500M3 12h2.500M19 12h2.500M5.600 5.600l1.800 1.800M16.600 16.600l1.800 1.800M5.600 18.400l1.800-1.800M16.600 7.400l1.800-1.800"/>'
  };
  function icon(name, cls) {
    var s = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    s.setAttribute('viewBox', '0 0 24 24');
    s.setAttribute('class', 'nivc-ico' + (cls ? ' ' + cls : ''));
    s.setAttribute('aria-hidden', 'true');
    s.setAttribute('focusable', 'false');
    var p = new DOMParser().parseFromString('<svg xmlns="http://www.w3.org/2000/svg">' + ICONS[name] + '</svg>', 'image/svg+xml');
    Array.prototype.forEach.call(p.documentElement.childNodes, function (n) { s.appendChild(document.importNode(n, true)); });
    return s;
  }

  function pad(n) { return (n < 10 ? '0' : '') + n; }
  function fmtDate(s) {
    var m = /^(\d{4})-(\d{2})-(\d{2})/.exec(s || '');
    return m ? m[3] + '/' + m[2] + '/' + m[1] : '—';
  }
  function fmtDateTime(s) {
    var m = /^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/.exec(s || '');
    return m ? m[3] + '/' + m[2] + '/' + m[1] + ' ' + m[4] + ':' + m[5] : '—';
  }
  var nfInt = new Intl.NumberFormat('he-IL');
  var nfMoney = new Intl.NumberFormat('he-IL', { style: 'currency', currency: 'ILS', maximumFractionDigits: 2 });
  function fmtInt(n) { return nfInt.format(n); }
  function fmtMoney(n) { return nfMoney.format(n); }

  function plural(n, one, many) { return n === 1 ? one : n + ' ' + many; }

  function addYears(date, years) {
    var m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(date);
    if (!m) return '';
    var y = +m[1] + years, mo = +m[2], d = +m[3];
    var last = new Date(Date.UTC(y, mo, 0)).getUTCDate();
    return y + '-' + pad(mo) + '-' + pad(Math.min(d, last));
  }
  function validDate(s) {
    var m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(s || '');
    if (!m) return false;
    var d = new Date(Date.UTC(+m[1], +m[2] - 1, +m[3]));
    return d.getUTCFullYear() === +m[1] && d.getUTCMonth() === +m[2] - 1 && d.getUTCDate() === +m[3] && +m[1] >= 2000 && +m[1] <= 2100;
  }

  // Mirror of the server-side Israeli phone normalisation (the server is authoritative).
  function normalizePhone(raw) {
    var d = String(raw || '').replace(/\D+/g, '');
    if (!d) return null;
    var n;
    if (d.indexOf('00972') === 0) n = d.slice(5);
    else if (d.indexOf('972') === 0) n = d.slice(3);
    else if (d.charAt(0) === '0') n = d.slice(1);
    else n = d;
    if (n.charAt(0) === '0' && n !== d) n = n.slice(1);
    if (!/^(5\d{8}|7\d{8}|[23489]\d{7})$/.test(n)) return null;
    var local = '0' + n, cut = local.length === 10 ? 3 : 2;
    return { display: local.slice(0, cut) + '-' + local.slice(cut), intl: '972' + n };
  }

  function safeUrl(u) { return /^https?:\/\//i.test(u || '') ? u : ''; }
  function shortUrl(u) { return String(u || '').replace(/^https?:\/\/(www\.)?/i, '').replace(/\/$/, ''); }

  function debounce(fn, ms) {
    var t;
    return function () { var a = arguments, c = this; clearTimeout(t); t = setTimeout(function () { fn.apply(c, a); }, ms); };
  }

  /* -------------------------------------------------------------------- api */

  function ApiError(message, status, errors) { this.message = message; this.status = status; this.errors = errors || {}; }

  function api(method, path, opts) {
    opts = opts || {};
    var url = CFG.api + path;
    if (opts.params) {
      var q = [];
      Object.keys(opts.params).forEach(function (k) {
        var v = opts.params[k];
        if (v !== '' && v !== null && v !== undefined) q.push(encodeURIComponent(k) + '=' + encodeURIComponent(v));
      });
      if (q.length) url += (url.indexOf('?') > -1 ? '&' : '?') + q.join('&');
    }
    if (method === 'GET') url += (url.indexOf('?') > -1 ? '&' : '?') + '_=' + Date.now(); // defeat any host/CDN cache
    var headers = { 'X-WP-Nonce': CFG.restNonce, 'Accept': 'application/json' };
    if (method !== 'GET') { headers['X-NIVC-Nonce'] = CFG.writeNonce; headers['Content-Type'] = 'application/json'; }
    return fetch(url, {
      method: method, headers: headers, credentials: 'same-origin', cache: 'no-store', signal: opts.signal,
      body: opts.body ? JSON.stringify(opts.body) : undefined
    }).then(function (res) {
      return res.text().then(function (txt) {
        var json = null;
        try { json = txt ? JSON.parse(txt) : null; } catch (e) { /* non-JSON */ }
        if (!res.ok) {
          var msg = (json && json.message) || 'אירעה שגיאה. נסו שוב.';
          if (res.status === 401 || res.status === 403) msg = (json && json.message) || 'אין הרשאה או שפג תוקף ההתחברות. רעננו את הדף.';
          throw new ApiError(msg, res.status, json && json.data && json.data.errors);
        }
        return json;
      });
    }, function (err) {
      if (err && err.name === 'AbortError') throw err;
      throw new ApiError('אין חיבור לשרת. בדקו את החיבור לאינטרנט ונסו שוב.', 0);
    });
  }

  /* ------------------------------------------------------------------ state */

  var state = {
    f: { search: '', status: '', created_from: '', created_to: '', expires_from: '', expires_to: '', paid_min: '', paid_max: '', views_min: '', views_max: '', sort: 'expires', order: 'asc', page: 1, per_page: 25 },
    data: null, loading: true, error: null, filtersOpen: false, pages: null
  };
  var ctl = null;

  var SORT_LABELS = { name: 'שם לקוח', created: 'תאריך יצירה', expires: 'תאריך תפוגה', paid: 'סכום ששולם', views: 'צפיות', days_left: 'ימים שנותרו' };
  var STATUS = {
    active: { label: 'פעיל', icon: 'check' },
    soon: { label: 'מסתיים בקרוב', icon: 'warn' },
    expired: { label: 'פג תוקף', icon: 'cross' }
  };

  /* --------------------------------------------------------------- skeleton */

  var els = {};
  var host = null;
  function buildShell() {
    clear(root);
    var logo = CFG.logo ? h('img', { class: 'nivc-logo', src: CFG.logo, alt: CFG.siteName, width: '640', height: '140' }) : h('span', { class: 'nivc-wordmark' }, 'Niv', h('b', null, 'Creative'));

    els.live = h('div', { class: 'nivc-sr', role: 'status', 'aria-live': 'polite' });
    // Dialogs and toasts live in a host on <body> so Elementor containers (transform/overflow) cannot clip them.
    if (host && host.parentNode) host.parentNode.removeChild(host);
    host = h('div', { class: 'nivc-app nivc-host', dir: 'rtl', lang: 'he' });
    document.body.appendChild(host);
    els.toasts = h('div', { class: 'nivc-toasts', 'aria-live': 'polite' });
    host.appendChild(els.toasts);
    els.cards = h('section', { class: 'nivc-cards', 'aria-label': 'סיכום' });
    els.scope = h('p', { class: 'nivc-scope' });
    els.analytics = h('div', { class: 'nivc-analytics' });
    els.search = h('input', {
      type: 'search', id: 'nivc-search', class: 'nivc-input nivc-search-input', placeholder: 'חיפוש לפי שם, טלפון או כתובת דף…',
      'aria-label': 'חיפוש לקוחות', autocomplete: 'off',
      oninput: debounce(function () { state.f.search = els.search.value.trim(); state.f.page = 1; load(); }, 250)
    });
    els.filterBtn = h('button', { type: 'button', class: 'nivc-btn nivc-btn-ghost', 'aria-expanded': 'false', 'aria-controls': 'nivc-filters', onclick: toggleFilters }, icon('filter'), 'מסננים');
    els.filters = h('div', { class: 'nivc-filters', id: 'nivc-filters', hidden: true });
    els.tableWrap = h('div', { class: 'nivc-tablewrap', tabindex: '0', role: 'region', 'aria-label': 'טבלת לקוחות' });
    els.pager = h('nav', { class: 'nivc-pager', 'aria-label': 'דפדוף' });

    root.appendChild(h('div', { class: 'nivc-shell' },
      h('header', { class: 'nivc-head' },
        h('div', { class: 'nivc-brand' }, logo, h('span', { class: 'nivc-vr', 'aria-hidden': 'true' }), h('div', null, h('h1', { class: 'nivc-title' }, 'ניהול לקוחות'), h('p', { class: 'nivc-sub' }, 'דפי נחיתה, תשלומים וחידושי שירות'))),
        h('button', { type: 'button', class: 'nivc-btn nivc-btn-primary', onclick: function () { openClientPanel(null); } }, icon('plus'), 'הוספת לקוח')
      ),
      els.cards, els.scope, els.analytics,
      h('div', { class: 'nivc-toolbar' },
        h('div', { class: 'nivc-searchbox' }, icon('search'), els.search),
        els.filterBtn
      ),
      els.filters, els.tableWrap, els.pager, els.live
    ));
    buildFilters();
  }

  /* ---------------------------------------------------------------- filters */

  function labelled(label, input, cls) {
    return h('label', { class: 'nivc-field ' + (cls || '') }, h('span', { class: 'nivc-label' }, label), input);
  }
  function bindInput(key, attrs) {
    var el = h('input', Object.assign({ class: 'nivc-input', value: state.f[key] }, attrs));
    el.addEventListener('input', debounce(function () { state.f[key] = el.value; state.f.page = 1; load(); }, 300));
    return el;
  }
  function bindSelect(key, options) {
    var el = h('select', { class: 'nivc-input' }, options.map(function (o) { return h('option', { value: o[0], selected: String(state.f[key]) === String(o[0]) }, o[1]); }));
    el.addEventListener('change', function () { state.f[key] = el.value; state.f.page = 1; load(); });
    return el;
  }

  function buildFilters() {
    clear(els.filters);
    var sortSel = bindSelect('sort', Object.keys(SORT_LABELS).map(function (k) { return [k, SORT_LABELS[k]]; }));
    var orderBtn = h('button', { type: 'button', class: 'nivc-btn nivc-btn-ghost nivc-btn-sm', 'aria-label': 'כיוון מיון', onclick: function () { state.f.order = state.f.order === 'asc' ? 'desc' : 'asc'; render(); load(); } },
      icon(state.f.order === 'asc' ? 'arrowUp' : 'arrowDown'), state.f.order === 'asc' ? 'עולה' : 'יורד');
    els.viewsMin = bindInput('views_min', { type: 'number', min: '0', inputmode: 'numeric', placeholder: 'מ-' });
    els.viewsMax = bindInput('views_max', { type: 'number', min: '0', inputmode: 'numeric', placeholder: 'עד' });

    els.filters.appendChild(h('div', { class: 'nivc-filter-grid' },
      labelled('סטטוס שירות', bindSelect('status', [['', 'הכול'], ['active', 'פעיל'], ['soon', 'מסתיים בקרוב'], ['expired', 'פג תוקף']])),
      h('fieldset', { class: 'nivc-field nivc-range' }, h('legend', { class: 'nivc-label' }, 'תאריך יצירה'),
        h('div', { class: 'nivc-pair' }, bindInput('created_from', { type: 'date', 'aria-label': 'תאריך יצירה מ-' }), h('span', null, '–'), bindInput('created_to', { type: 'date', 'aria-label': 'תאריך יצירה עד' }))),
      h('fieldset', { class: 'nivc-field nivc-range' }, h('legend', { class: 'nivc-label' }, 'תאריך תפוגה'),
        h('div', { class: 'nivc-pair' }, bindInput('expires_from', { type: 'date', 'aria-label': 'תאריך תפוגה מ-' }), h('span', null, '–'), bindInput('expires_to', { type: 'date', 'aria-label': 'תאריך תפוגה עד' }))),
      h('fieldset', { class: 'nivc-field nivc-range' }, h('legend', { class: 'nivc-label' }, 'סכום ששולם (₪, סה״כ תשלומים)'),
        h('div', { class: 'nivc-pair' }, bindInput('paid_min', { type: 'number', min: '0', step: '0.01', inputmode: 'decimal', placeholder: 'מ-', 'aria-label': 'סכום מינימלי' }), h('span', null, '–'), bindInput('paid_max', { type: 'number', min: '0', step: '0.01', inputmode: 'decimal', placeholder: 'עד', 'aria-label': 'סכום מקסימלי' }))),
      h('fieldset', { class: 'nivc-field nivc-range', id: 'nivc-views-range' }, h('legend', { class: 'nivc-label' }, 'צפיות בדף'),
        h('div', { class: 'nivc-pair' }, els.viewsMin, h('span', null, '–'), els.viewsMax),
        h('small', { class: 'nivc-hint', id: 'nivc-views-hint' })),
      labelled('מיון לפי', sortSel),
      h('div', { class: 'nivc-field' }, h('span', { class: 'nivc-label' }, 'כיוון'), orderBtn),
      labelled('שורות בעמוד', bindSelect('per_page', [[10, '10'], [25, '25'], [50, '50'], [100, '100']])),
      h('div', { class: 'nivc-field nivc-field-end' }, h('button', { type: 'button', class: 'nivc-btn nivc-btn-ghost', onclick: clearFilters }, icon('close'), 'ניקוי מסננים'))
    ));
  }

  function toggleFilters() {
    state.filtersOpen = !state.filtersOpen;
    els.filters.hidden = !state.filtersOpen;
    els.filterBtn.setAttribute('aria-expanded', String(state.filtersOpen));
  }

  function activeFilterCount() {
    var f = state.f, n = 0;
    ['search', 'status', 'created_from', 'created_to', 'expires_from', 'expires_to', 'paid_min', 'paid_max', 'views_min', 'views_max'].forEach(function (k) { if (f[k] !== '') n++; });
    return n;
  }

  function clearFilters() {
    var keep = { sort: state.f.sort, order: state.f.order, per_page: state.f.per_page };
    state.f = Object.assign({ search: '', status: '', created_from: '', created_to: '', expires_from: '', expires_to: '', paid_min: '', paid_max: '', views_min: '', views_max: '', page: 1 }, keep);
    els.search.value = '';
    buildFilters();
    updateViewsFilter();
    load();
    announce('המסננים נוקו');
  }

  function updateViewsFilter() {
    var connected = !!(state.data && state.data.analytics && state.data.analytics.connected);
    var hint = document.getElementById('nivc-views-hint');
    if (!hint) return;
    els.viewsMin.disabled = !connected;
    els.viewsMax.disabled = !connected;
    hint.textContent = connected ? 'צפיות מהמעקב הפנימי בלבד.' : 'זמין לאחר חיבור נתוני צפיות.';
  }

  /* ------------------------------------------------------------------ load */

  function load() {
    if (ctl) ctl.abort();
    ctl = typeof AbortController !== 'undefined' ? new AbortController() : null;
    state.loading = true;
    state.error = null;
    render();
    api('GET', 'clients', { params: state.f, signal: ctl ? ctl.signal : undefined }).then(function (d) {
      state.data = d;
      state.f.page = d.page;
      state.loading = false;
      render();
      updateViewsFilter();
    }, function (err) {
      if (err && err.name === 'AbortError') return;
      state.loading = false;
      state.error = err.message;
      render();
    });
  }

  function announce(msg) { els.live.textContent = ''; setTimeout(function () { els.live.textContent = msg; }, 30); }

  function toast(msg, kind) {
    var t = h('div', { class: 'nivc-toast nivc-toast-' + (kind || 'ok'), role: kind === 'err' ? 'alert' : 'status' }, icon(kind === 'err' ? 'cross' : 'check'), h('span', null, msg));
    els.toasts.appendChild(t);
    setTimeout(function () { t.classList.add('out'); setTimeout(function () { if (t.parentNode) t.parentNode.removeChild(t); }, 300); }, kind === 'err' ? 7000 : 4000);
  }

  /* ----------------------------------------------------------------- render */

  function render() {
    renderCards();
    renderAnalytics();
    renderTable();
    renderPager();
  }

  // Purely decorative mini-graphics (fixed shapes, NOT data); hidden from assistive tech.
  function deco(kind) {
    var ns = 'http://www.w3.org/2000/svg';
    var svg = document.createElementNS(ns, 'svg');
    svg.setAttribute('class', 'nivc-deco');
    svg.setAttribute('viewBox', '0 0 90 36');
    svg.setAttribute('aria-hidden', 'true');
    svg.setAttribute('focusable', 'false');
    if (kind === 'bars') {
      [10, 18, 14, 24, 20, 30, 26, 34].forEach(function (hgt, i) {
        var r = document.createElementNS(ns, 'rect');
        r.setAttribute('x', String(i * 11)); r.setAttribute('y', String(36 - hgt)); r.setAttribute('width', '6'); r.setAttribute('height', String(hgt)); r.setAttribute('rx', '2');
        svg.appendChild(r);
      });
    } else {
      var path = document.createElementNS(ns, 'path');
      path.setAttribute('d', 'M2 28 C12 8 20 8 28 20 S44 30 52 16 S70 6 88 22');
      path.setAttribute('fill', 'none'); path.setAttribute('stroke-width', '2.500'); path.setAttribute('stroke-linecap', 'round');
      svg.appendChild(path);
    }
    return svg;
  }

  function card(iconName, label, value, note, tone, decoKind) {
    return h('article', { class: 'nivc-card nivc-tone-' + (tone || 'blue') },
      h('span', { class: 'nivc-card-ico' }, icon(iconName)),
      h('div', { class: 'nivc-card-main' }, h('p', { class: 'nivc-card-label' }, label), h('p', { class: 'nivc-card-value' }, value), note ? h('p', { class: 'nivc-card-note' }, note) : null),
      deco(decoKind || 'line'));
  }

  function renderCards() {
    clear(els.cards);
    var d = state.data;
    if (!d) {
      for (var i = 0; i < 6; i++) els.cards.appendChild(h('div', { class: 'nivc-card nivc-skel', 'aria-hidden': 'true' }));
      els.scope.textContent = '';
      return;
    }
    var s = d.summary, a = d.analytics;
    els.cards.appendChild(card('users', 'סה״כ לקוחות', fmtInt(s.total), null, 'violet', 'bars'));
    els.cards.appendChild(card('check', 'לקוחות פעילים', fmtInt(s.active), 'יותר מ-30 יום לתפוגה', 'green', 'line'));
    els.cards.appendChild(card('warn', 'מסתיימים בתוך 30 יום', fmtInt(s.soon), null, 'amber', 'line'));
    els.cards.appendChild(card('cross', 'שירות שפג תוקפו', fmtInt(s.expired), null, 'red', 'line'));
    els.cards.appendChild(card('shekel', 'סה״כ תשלומים שנרשמו', fmtMoney(s.paid), 'ראשוני + חידושים', 'teal', 'bars'));
    els.cards.appendChild(card('eye', 'סה״כ צפיות בדפים',
      s.views === null ? 'לא מחובר' : fmtInt(s.views),
      s.views === null ? 'ניתן לחבר בהגדרות הנתונים' : 'צפיות, לא מבקרים ייחודיים' + (a.since ? ' · מאז ' + fmtDate(a.since) : ''), 'blue', 'line'));
    els.scope.textContent = d.filtered
      ? 'הכרטיסים מציגים את הלקוחות לפי המסננים הפעילים (' + fmtInt(d.total) + ' מתוך ' + fmtInt(d.all_clients) + ').'
      : 'הכרטיסים מציגים את כל הלקוחות.';
    els.scope.className = 'nivc-scope' + (d.filtered ? ' is-filtered' : '');
  }

  function renderAnalytics() {
    clear(els.analytics);
    var a = state.data && state.data.analytics;
    if (!a) return;
    var txt;
    if (a.connected) {
      txt = 'מעקב צפיות פנימי פעיל' + (a.since ? ' מאז ' + fmtDateTime(a.since) : '') + ' · עדכון אחרון: ' + (a.last_update ? fmtDateTime(a.last_update) : 'עדיין לא נרשמו צפיות') + ' · ' + fmtInt(a.pages_tracked) + ' דפים במעקב';
    } else {
      txt = 'נתוני צפיות: לא מחובר. המעקב אינו פעיל, ולכן לא מוצגים נתונים.';
    }
    els.analytics.appendChild(h('span', { class: 'nivc-astatus' }, h('span', { class: 'nivc-dot ' + (a.connected ? 'on' : 'off'), 'aria-hidden': 'true' }), h('span', null, txt)));
    els.analytics.appendChild(h('button', { type: 'button', class: 'nivc-link', onclick: openAnalytics }, icon('gear', 'nivc-ico-sm'), 'הגדרות נתוני צפיות'));
  }

  function sortHeader(key, label) {
    var active = state.f.sort === key;
    var th = h('th', { scope: 'col', class: 'nivc-th-sort', 'aria-sort': active ? (state.f.order === 'asc' ? 'ascending' : 'descending') : 'none' },
      h('button', { type: 'button', class: 'nivc-sortbtn' + (active ? ' is-active' : ''), onclick: function () {
        if (state.f.sort === key) state.f.order = state.f.order === 'asc' ? 'desc' : 'asc';
        else { state.f.sort = key; state.f.order = 'asc'; }
        state.f.page = 1; buildFilters(); updateViewsFilter(); load();
      } }, label, active ? icon(state.f.order === 'asc' ? 'arrowUp' : 'arrowDown', 'nivc-ico-sm') : null));
    return th;
  }

  function renderTable() {
    clear(els.tableWrap);
    els.tableWrap.setAttribute('aria-busy', state.loading ? 'true' : 'false');
    if (state.error) {
      els.tableWrap.appendChild(h('div', { class: 'nivc-state nivc-state-err', role: 'alert' },
        icon('warn'), h('h2', null, 'לא הצלחנו לטעון את הלקוחות'), h('p', null, state.error),
        h('button', { type: 'button', class: 'nivc-btn nivc-btn-primary', onclick: load }, 'ניסיון חוזר')));
      return;
    }
    if (!state.data) {
      els.tableWrap.appendChild(h('div', { class: 'nivc-skeleton' }, [1, 2, 3, 4, 5].map(function () { return h('div', { class: 'nivc-skel nivc-skel-row' }); })));
      return;
    }
    var d = state.data;
    if (!d.items.length) {
      if (!d.all_clients) {
        els.tableWrap.appendChild(h('div', { class: 'nivc-empty' },
          CFG.art ? h('img', { class: 'nivc-empty-art', src: CFG.art, alt: '', width: '900', height: '886', loading: 'lazy' }) : null,
          h('div', { class: 'nivc-empty-text' }, icon('users'), h('h2', null, 'עדיין אין לקוחות'),
            h('p', null, 'הוסיפו את הלקוח הראשון כדי להתחיל לעקוב אחרי דפי נחיתה, תשלומים וחידושים.'),
            h('button', { type: 'button', class: 'nivc-btn nivc-btn-primary nivc-btn-lg', onclick: function () { openClientPanel(null); } }, 'הוספת לקוח', icon('plus')))));
      } else {
        els.tableWrap.appendChild(h('div', { class: 'nivc-state' }, icon('search'), h('h2', null, 'לא נמצאו לקוחות'),
          h('p', null, 'נסו לשנות את החיפוש או את המסננים.'),
          h('button', { type: 'button', class: 'nivc-btn nivc-btn-ghost', onclick: clearFilters }, 'ניקוי מסננים')));
      }
      return;
    }
    var table = h('table', { class: 'nivc-table' },
      h('caption', { class: 'nivc-sr' }, 'רשימת לקוחות'),
      h('thead', null, h('tr', null,
        sortHeader('name', 'לקוח / עסק'),
        h('th', { scope: 'col' }, 'דף נחיתה'),
        h('th', { scope: 'col' }, 'כתובת'),
        h('th', { scope: 'col' }, 'טלפון'),
        h('th', { scope: 'col' }, 'וואטסאפ'),
        sortHeader('views', 'צפיות'),
        sortHeader('paid', 'שולם (סה״כ)'),
        sortHeader('created', 'נוצר'),
        sortHeader('expires', 'תפוגה'),
        sortHeader('days_left', 'ימים שנותרו'),
        h('th', { scope: 'col' }, 'סטטוס'),
        h('th', { scope: 'col' }, 'פעולות'))),
      h('tbody', null, d.items.map(row)));
    els.tableWrap.appendChild(table);
  }

  function td(label, content, cls) { return h('td', { 'data-label': label, class: cls || '' }, content); }

  function statusBadge(c) {
    var st = STATUS[c.status];
    return h('span', { class: 'nivc-badge nivc-badge-' + c.status }, icon(st.icon), st.label);
  }

  function daysCell(c) {
    var main;
    if (c.status === 'expired') main = 'באיחור של ' + plural(c.overdue_days, 'יום אחד', 'ימים');
    else if (c.days_left === 0) main = 'מסתיים היום';
    else if (c.days_left === 1) main = 'נותר יום אחד';
    else main = 'נותרו ' + c.days_left + ' ימים';
    return h('div', { class: 'nivc-days' },
      h('span', { class: 'nivc-days-text' }, main),
      h('div', { class: 'nivc-progress nivc-progress-' + c.status, role: 'progressbar', 'aria-valuemin': '0', 'aria-valuemax': '100', 'aria-valuenow': String(c.progress), 'aria-label': 'חלק מתקופת השירות שחלף' },
        h('span', { style: 'width:' + c.progress + '%' })));
  }

  function viewsCell(c) {
    var a = state.data.analytics;
    if (!a.connected) return h('span', { class: 'nivc-muted', title: 'מעקב הצפיות אינו מחובר' }, 'לא מחובר');
    if (!c.measurable) return h('span', { class: 'nivc-muted', title: 'דף חיצוני – לא ניתן למדוד באתר זה' }, 'לא נמדד');
    return h('div', null, h('strong', null, fmtInt(c.views)), h('small', { class: 'nivc-muted nivc-block' }, 'ייחודיים: ' + fmtInt(c.uniques)));
  }

  function iconBtn(name, label, fn, cls) {
    return h('button', { type: 'button', class: 'nivc-iconbtn ' + (cls || ''), 'aria-label': label, title: label, onclick: fn }, icon(name));
  }

  function row(c) {
    var url = safeUrl(c.landing_url);
    return h('tr', null,
      td('לקוח / עסק', h('div', { class: 'nivc-name' }, h('strong', null, c.name), c.notes ? h('small', { class: 'nivc-muted nivc-notes', title: c.notes }, c.notes) : null), 'nivc-cell-name'),
      td('דף נחיתה', c.landing_name),
      td('כתובת', url ? h('a', { class: 'nivc-url nivc-ltr', href: url, target: '_blank', rel: 'noopener noreferrer', title: url }, shortUrl(url), h('span', { class: 'nivc-sr' }, ' (נפתח בלשונית חדשה)'), icon('external', 'nivc-ico-sm')) : h('span', { class: 'nivc-muted' }, '—')),
      td('טלפון', h('a', { class: 'nivc-ltr nivc-tel', href: 'tel:+' + c.phone_intl }, c.phone)),
      td('וואטסאפ', c.whatsapp_url ? h('a', { class: 'nivc-wa', href: c.whatsapp_url, target: '_blank', rel: 'noopener noreferrer', 'aria-label': 'פתיחת שיחת וואטסאפ עם ' + c.name + ' (נפתח בלשונית חדשה)' }, icon('whatsapp'), 'וואטסאפ') : h('span', { class: 'nivc-muted' }, '—')),
      td('צפיות', viewsCell(c)),
      td('שולם (סה״כ)', h('div', null, h('strong', null, fmtMoney(c.paid)), c.payments_count > 1 ? h('small', { class: 'nivc-muted nivc-block' }, c.payments_count + ' תשלומים') : null)),
      td('נוצר', h('div', null, fmtDate(c.landing_created), h('small', { class: 'nivc-muted nivc-block', title: 'מועד הוספת הרשומה לדשבורד' }, 'נוסף: ' + fmtDate(c.record_added_at)))),
      td('תפוגה', fmtDate(c.expires_on)),
      td('ימים שנותרו', daysCell(c)),
      td('סטטוס', statusBadge(c)),
      td('פעולות', h('div', { class: 'nivc-actions' },
        h('button', { type: 'button', class: 'nivc-btn nivc-btn-soft nivc-btn-sm', onclick: function () { openRenew(c); } }, icon('renew'), 'חידוש לשנה'),
        iconBtn('history', 'היסטוריית תשלומים וחידושים', function () { openHistory(c); }),
        iconBtn('edit', 'עריכת ' + c.name, function () { openClientPanel(c); }),
        iconBtn('trash', 'מחיקת ' + c.name, function () { openDelete(c); }, 'is-danger')), 'nivc-cell-actions')
    );
  }

  function renderPager() {
    clear(els.pager);
    var d = state.data;
    if (!d || !d.total) return;
    var from = (d.page - 1) * state.f.per_page + 1;
    var to = Math.min(d.total, d.page * state.f.per_page);
    els.pager.appendChild(h('p', { class: 'nivc-count' }, 'מציג ' + fmtInt(from) + '–' + fmtInt(to) + ' מתוך ' + fmtInt(d.total) + ' לקוחות' + (d.filtered ? ' (לפי מסננים)' : '')));
    if (d.pages > 1) {
      els.pager.appendChild(h('div', { class: 'nivc-pages' },
        h('button', { type: 'button', class: 'nivc-btn nivc-btn-ghost nivc-btn-sm', disabled: d.page <= 1, onclick: function () { state.f.page = d.page - 1; load(); } }, 'הקודם'),
        h('span', { class: 'nivc-pageno' }, 'עמוד ' + d.page + ' מתוך ' + d.pages),
        h('button', { type: 'button', class: 'nivc-btn nivc-btn-ghost nivc-btn-sm', disabled: d.page >= d.pages, onclick: function () { state.f.page = d.page + 1; load(); } }, 'הבא')));
    }
  }

  /* ----------------------------------------------------------------- dialogs */

  var openDlg = null;

  function dialog(opts) {
    // opts: title, kind ('panel'|'modal'), body (node), footer (node), onClose
    var prev = document.activeElement;
    var titleId = 'nivc-dlg-title-' + Date.now();
    var closeBtn = h('button', { type: 'button', class: 'nivc-iconbtn', 'aria-label': 'סגירה', onclick: close }, icon('close'));
    var box = h('div', { class: 'nivc-dialog nivc-dialog-' + (opts.kind || 'modal'), role: 'dialog', 'aria-modal': 'true', 'aria-labelledby': titleId },
      h('div', { class: 'nivc-dialog-head' }, h('h2', { id: titleId }, opts.title), closeBtn),
      h('div', { class: 'nivc-dialog-body' }, opts.body),
      opts.footer ? h('div', { class: 'nivc-dialog-foot' }, opts.footer) : null);
    var back = h('div', { class: 'nivc-backdrop', onmousedown: function (e) { if (e.target === back && !opts.sticky) close(); } }, box);
    host.appendChild(back);
    document.documentElement.classList.add('nivc-lock');
    requestAnimationFrame(function () { back.classList.add('in'); });

    function keydown(e) {
      if (e.key === 'Escape') { e.stopPropagation(); close(); return; }
      if (e.key !== 'Tab') return;
      var f = box.querySelectorAll('a[href],button:not([disabled]),input:not([disabled]):not([type=hidden]),select:not([disabled]),textarea:not([disabled])');
      if (!f.length) return;
      var first = f[0], last = f[f.length - 1];
      if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
      else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
    }
    document.addEventListener('keydown', keydown, true);
    function close() {
      document.removeEventListener('keydown', keydown, true);
      document.documentElement.classList.remove('nivc-lock');
      if (back.parentNode) back.parentNode.removeChild(back);
      openDlg = null;
      if (prev && prev.focus && document.contains(prev)) prev.focus();
      if (opts.onClose) opts.onClose();
    }
    setTimeout(function () {
      var first = opts.focus ? box.querySelector(opts.focus) : box.querySelector('input:not([type=hidden]),select,textarea');
      (first || closeBtn).focus();
    }, 60);
    openDlg = { close: close, box: box };
    return openDlg;
  }

  function fieldErr(id) { return h('p', { class: 'nivc-err', id: id + '-err', hidden: true, role: 'alert' }); }
  function setErrors(form, errors) {
    var first = null;
    form.querySelectorAll('[data-field]').forEach(function (inp) {
      var key = inp.getAttribute('data-field');
      var p = form.querySelector('#' + inp.id + '-err');
      var msg = errors && errors[key];
      if (p) { p.hidden = !msg; p.textContent = msg || ''; }
      if (msg) { inp.setAttribute('aria-invalid', 'true'); inp.setAttribute('aria-describedby', inp.id + '-err'); if (!first) first = inp; }
      else { inp.removeAttribute('aria-invalid'); }
    });
    if (first) first.focus();
  }
  function field(id, label, input, opts) {
    opts = opts || {};
    input.id = id;
    if (opts.key) {
      input.setAttribute('data-field', opts.key);
      // Clear a field's error as soon as the user edits it.
      input.addEventListener('input', function () {
        var p = document.getElementById(id + '-err');
        if (p && !p.hidden) { p.hidden = true; input.removeAttribute('aria-invalid'); }
      });
    }
    return h('div', { class: 'nivc-field ' + (opts.cls || '') },
      h('label', { class: 'nivc-label', for: id }, label, opts.req ? h('span', { class: 'nivc-req', 'aria-hidden': 'true' }, ' *') : null),
      input, opts.hint ? h('small', { class: 'nivc-hint' }, opts.hint) : null, opts.key ? fieldErr(id) : null);
  }

  /* ---------------------------------------------------------- client panel */

  function loadPages() {
    if (state.pages) return Promise.resolve(state.pages);
    return api('GET', 'pages').then(function (r) { state.pages = r.items; return state.pages; }, function () { state.pages = []; return []; });
  }

  function openClientPanel(c) {
    var isEdit = !!c;
    var today = (state.data && state.data.today) || new Date().toISOString().slice(0, 10);
    var v = c || { name: '', landing_name: '', landing_source: 'page', page_id: 0, landing_url: '', phone: '', paid: '', landing_created: today, expires_on: '', notes: '' };
    var expiresTouched = isEdit && c.expires_on !== addYears(c.landing_created, 1);
    var source = v.landing_source === 'url' && v.landing_url ? 'url' : 'page';
    if (!isEdit) source = 'page';
    var initialAmount = '';

    var name = h('input', { class: 'nivc-input', type: 'text', value: v.name, maxlength: '190', autocomplete: 'off' });
    var lname = h('input', { class: 'nivc-input', type: 'text', value: v.landing_name, maxlength: '190', autocomplete: 'off' });
    var pageFilter = h('input', { class: 'nivc-input', type: 'search', placeholder: 'סינון רשימת העמודים…', 'aria-label': 'סינון רשימת העמודים' });
    var pageSel = h('select', { class: 'nivc-input' }, h('option', { value: '' }, 'טוען עמודים…'));
    var urlIn = h('input', { class: 'nivc-input nivc-ltr', type: 'url', dir: 'ltr', value: v.landing_source === 'url' ? v.landing_url : '', placeholder: 'https://example.com/landing' });
    var phone = h('input', { class: 'nivc-input nivc-ltr', type: 'tel', dir: 'ltr', value: v.phone, inputmode: 'tel', placeholder: '050-1234567', autocomplete: 'off' });
    var phoneNote = h('small', { class: 'nivc-hint', 'aria-live': 'polite' });
    var amount = h('input', { class: 'nivc-input', type: 'number', min: '0', step: '0.01', inputmode: 'decimal', value: isEdit ? '' : '', placeholder: '0.00' });
    var created = h('input', { class: 'nivc-input', type: 'date', value: v.landing_created, min: '2000-01-01', max: '2100-12-31' });
    var expires = h('input', { class: 'nivc-input', type: 'date', value: v.expires_on || addYears(v.landing_created, 1) });
    var notes = h('textarea', { class: 'nivc-input', rows: '3', maxlength: '5000' }, v.notes || '');
    var formMsg = h('p', { class: 'nivc-err nivc-form-err', hidden: true, role: 'alert' });

    function updatePhone() {
      var n = normalizePhone(phone.value);
      phoneNote.textContent = !phone.value.trim() ? 'מספר ישראלי, למשל 050-1234567 או +972501234567.' : n ? 'ייפתח בוואטסאפ כ: +' + n.intl : 'המספר אינו נראה כמספר ישראלי תקין.';
    }
    phone.addEventListener('input', updatePhone); updatePhone();

    created.addEventListener('change', function () {
      if (!expiresTouched && validDate(created.value)) { expires.value = addYears(created.value, 1); updateExpHint(); }
    });
    var expHint = h('small', { class: 'nivc-hint' });
    function updateExpHint() {
      expHint.textContent = expiresTouched ? 'תאריך תפוגה ידני. ברירת המחדל היא שנה מיום היצירה (' + fmtDate(addYears(created.value, 1)) + ').' : 'מחושב אוטומטית: שנה קלנדרית מתאריך היצירה.';
    }
    expires.addEventListener('input', function () { expiresTouched = true; updateExpHint(); });
    updateExpHint();

    var rPage = h('input', { type: 'radio', name: 'nivc-src', value: 'page', checked: source === 'page', id: 'nivc-src-page' });
    var rUrl = h('input', { type: 'radio', name: 'nivc-src', value: 'url', checked: source === 'url', id: 'nivc-src-url' });
    pageSel.id = 'nivc-f-page'; pageSel.setAttribute('data-field', 'page_id');
    urlIn.id = 'nivc-f-url'; urlIn.setAttribute('data-field', 'landing_url');
    var pageBox = h('div', { class: 'nivc-src-box' }, pageFilter, pageSel, fieldErr('nivc-f-page'));
    var urlBox = h('div', { class: 'nivc-src-box' }, urlIn, fieldErr('nivc-f-url'));
    function applySource() { source = rUrl.checked ? 'url' : 'page'; pageBox.hidden = source !== 'page'; urlBox.hidden = source !== 'url'; }
    rPage.addEventListener('change', applySource); rUrl.addEventListener('change', applySource); applySource();

    var allPages = [];
    function fillPages() {
      var q = pageFilter.value.trim().toLowerCase();
      var cur = pageSel.value || String(v.page_id || '');
      clear(pageSel);
      pageSel.appendChild(h('option', { value: '' }, '— בחירת עמוד —'));
      allPages.filter(function (p) { return !q || p.title.toLowerCase().indexOf(q) > -1 || String(p.id) === cur; }).forEach(function (p) {
        pageSel.appendChild(h('option', { value: p.id, selected: String(p.id) === cur, 'data-title': p.title }, p.title + (p.status !== 'publish' ? ' (' + (p.status === 'draft' ? 'טיוטה' : p.status === 'private' ? 'פרטי' : p.status) + ')' : '')));
      });
    }
    pageFilter.addEventListener('input', fillPages);
    pageSel.addEventListener('change', function () {
      var o = pageSel.options[pageSel.selectedIndex];
      if (o && o.value && !lname.value.trim()) lname.value = o.getAttribute('data-title') || '';
    });
    loadPages().then(function (p) { allPages = p; fillPages(); });

    var saveBtn = h('button', { type: 'submit', class: 'nivc-btn nivc-btn-primary', form: 'nivc-client-form' }, isEdit ? 'שמירת שינויים' : 'הוספת לקוח');
    var form = h('form', { id: 'nivc-client-form', novalidate: true, class: 'nivc-form' },
      field('nivc-f-name', 'שם לקוח / עסק', name, { key: 'name', req: true }),
      field('nivc-f-lname', 'שם דף הנחיתה', lname, { key: 'landing_name', req: true }),
      h('fieldset', { class: 'nivc-field nivc-segment' },
        h('legend', { class: 'nivc-label' }, 'דף הנחיתה', h('span', { class: 'nivc-req', 'aria-hidden': 'true' }, ' *')),
        h('div', { class: 'nivc-radios' },
          h('label', { class: 'nivc-radio', for: 'nivc-src-page' }, rPage, h('span', null, 'עמוד קיים באתר')),
          h('label', { class: 'nivc-radio', for: 'nivc-src-url' }, rUrl, h('span', null, 'כתובת מותאמת'))),
        pageBox, urlBox),
      field('nivc-f-phone', 'טלפון', phone, { key: 'phone', req: true }),
      phoneNote,
      field('nivc-f-amount', isEdit ? 'סכום תשלום ראשוני (₪)' : 'סכום ששולם (₪)', amount, { key: 'amount', req: true, hint: isEdit ? 'עריכת התשלום הראשוני בלבד. חידושים נשמרים בנפרד ואינם משתנים.' : 'נרשם כתשלום הראשוני של הלקוח.' }),
      field('nivc-f-created', 'תאריך יצירת דף הנחיתה', created, { key: 'landing_created', req: true, hint: 'אפשר לבחור תאריך עבר עבור לקוחות קיימים. זה אינו מועד הוספת הרשומה לדשבורד.' }),
      h('div', { class: 'nivc-field' }, h('label', { class: 'nivc-label', for: 'nivc-f-expires' }, 'תאריך תפוגת השירות'), (function () { expires.id = 'nivc-f-expires'; expires.setAttribute('data-field', 'expires_on'); return expires; })(), expHint, fieldErr('nivc-f-expires')),
      field('nivc-f-notes', 'הערות פנימיות', notes, { key: 'notes' }),
      formMsg);

    // The page selector/URL inputs carry the error spans; hide duplicate visual labels.
    pageSel.setAttribute('aria-label', 'בחירת עמוד'); urlIn.setAttribute('aria-label', 'כתובת דף הנחיתה');

    if (isEdit) {
      // Initial payment amount comes from the payment history (the first/oldest 'initial' row).
      api('GET', 'clients/' + c.id + '/payments').then(function (r) {
        var ini = r.items.filter(function (p) { return p.kind === 'initial'; }).pop();
        initialAmount = ini ? ini.amount : 0;
        amount.value = initialAmount;
      }, function () { formMsg.hidden = false; formMsg.textContent = 'לא ניתן היה לטעון את סכום התשלום הראשוני.'; });
    }

    var dlg = dialog({ title: isEdit ? 'עריכת לקוח' : 'הוספת לקוח', kind: 'panel', body: form, footer: [saveBtn, h('button', { type: 'button', class: 'nivc-btn nivc-btn-ghost', onclick: function () { dlg.close(); } }, 'ביטול')], sticky: true });

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var errors = {};
      if (!name.value.trim()) errors.name = 'נא להזין שם לקוח או עסק.';
      if (!lname.value.trim()) errors.landing_name = 'נא להזין שם דף נחיתה.';
      if (source === 'page' && !pageSel.value) errors.page_id = 'נא לבחור עמוד קיים מהרשימה.';
      if (source === 'url') {
        if (!urlIn.value.trim()) errors.landing_url = 'נא להזין כתובת דף נחיתה.';
        else if (!/^https?:\/\/[^\s/$.?#].[^\s]*$/i.test(urlIn.value.trim())) errors.landing_url = 'כתובת האתר אינה תקינה (יש להתחיל ב-https://).';
      }
      if (!phone.value.trim()) errors.phone = 'נא להזין מספר טלפון.';
      else if (!normalizePhone(phone.value)) errors.phone = 'מספר הטלפון אינו תקין. לדוגמה: 050-1234567.';
      if (amount.value === '' || isNaN(+amount.value) || +amount.value < 0) errors.amount = 'נא להזין סכום תקין בשקלים.';
      if (!validDate(created.value)) errors.landing_created = 'נא לבחור תאריך יצירה תקין.';
      if (!validDate(expires.value)) errors.expires_on = 'נא לבחור תאריך תפוגה תקין.';
      else if (validDate(created.value) && expires.value <= created.value) errors.expires_on = 'תאריך התפוגה חייב להיות אחרי תאריך היצירה.';
      formMsg.hidden = true;
      setErrors(form, errors);
      if (Object.keys(errors).length) return;

      saveBtn.disabled = true; saveBtn.textContent = 'שומר…';
      var payload = {
        name: name.value, landing_name: lname.value, landing_source: source,
        page_id: source === 'page' ? +pageSel.value : 0, landing_url: source === 'url' ? urlIn.value.trim() : '',
        phone: phone.value, amount: amount.value, landing_created: created.value, expires_on: expires.value, notes: notes.value
      };
      api(isEdit ? 'POST' : 'POST', isEdit ? 'clients/' + c.id : 'clients', { body: payload }).then(function (r) {
        dlg.close();
        toast(r.message, 'ok');
        load();
      }, function (err) {
        saveBtn.disabled = false; saveBtn.textContent = isEdit ? 'שמירת שינויים' : 'הוספת לקוח';
        if (err.errors && Object.keys(err.errors).length) { setErrors(form, err.errors); }
        formMsg.hidden = false; formMsg.textContent = err.message;
      });
    });
  }

  /* ----------------------------------------------------------------- delete */

  function openDelete(c) {
    var btn = h('button', { type: 'button', class: 'nivc-btn nivc-btn-danger' }, icon('trash'), 'מחיקת הלקוח');
    var msg = h('p', { class: 'nivc-err', hidden: true, role: 'alert' });
    var dlg = dialog({
      title: 'מחיקת לקוח',
      body: h('div', null,
        h('p', null, 'למחוק את הלקוח ', h('strong', null, c.name), '?'),
        h('p', { class: 'nivc-callout' }, icon('info'), h('span', null, 'הפעולה מוחקת רק את רשומת הלקוח ואת היסטוריית התשלומים שלו בדשבורד. דף הנחיתה עצמו באתר לא יימחק ולא ישתנה.')),
        msg),
      footer: [btn, h('button', { type: 'button', class: 'nivc-btn nivc-btn-ghost', onclick: function () { dlg.close(); } }, 'ביטול')],
      focus: '.nivc-btn-ghost'
    });
    btn.addEventListener('click', function () {
      btn.disabled = true;
      api('DELETE', 'clients/' + c.id).then(function (r) { dlg.close(); toast(r.message, 'ok'); load(); },
        function (err) { btn.disabled = false; msg.hidden = false; msg.textContent = err.message; });
    });
  }

  /* ------------------------------------------------------------------ renew */

  function openRenew(c) {
    var today = state.data.today;
    var startDefault = c.days_left >= 0 ? c.expires_on : today;
    var start = h('input', { class: 'nivc-input', type: 'date', value: startDefault, min: '2000-01-01', max: '2100-12-31' });
    var amount = h('input', { class: 'nivc-input', type: 'number', min: '0', step: '0.01', inputmode: 'decimal', placeholder: '0.00' });
    var note = h('input', { class: 'nivc-input', type: 'text', maxlength: '255', placeholder: 'אופציונלי' });
    var preview = h('p', { class: 'nivc-callout' }, icon('info'), h('span'));
    var msg = h('p', { class: 'nivc-err', hidden: true, role: 'alert' });
    function upd() { preview.lastChild.textContent = validDate(start.value) ? 'השירות יחודש עד ' + fmtDate(addYears(start.value, 1)) + ' (שנה מתאריך ההתחלה).' : 'בחרו תאריך התחלה תקין.'; }
    start.addEventListener('input', upd); upd();
    var save = h('button', { type: 'submit', class: 'nivc-btn nivc-btn-primary', form: 'nivc-renew-form' }, icon('renew'), 'אישור חידוש');
    var form = h('form', { id: 'nivc-renew-form', novalidate: true, class: 'nivc-form' },
      h('p', null, 'חידוש עבור ', h('strong', null, c.name), '. תפוגה נוכחית: ', fmtDate(c.expires_on), '.'),
      field('nivc-r-start', 'תאריך התחלת התקופה החדשה', start, { key: 'start_date', req: true, hint: 'ברירת מחדל: מיום התפוגה הנוכחית (או היום, אם השירות כבר פג).' }),
      field('nivc-r-amount', 'סכום ששולם עבור החידוש (₪)', amount, { key: 'amount', req: true }),
      field('nivc-r-note', 'הערה', note, { key: 'note' }),
      preview, msg);
    var dlg = dialog({ title: 'חידוש לשנה', body: form, footer: [save, h('button', { type: 'button', class: 'nivc-btn nivc-btn-ghost', onclick: function () { dlg.close(); } }, 'ביטול')], focus: '#nivc-r-amount' });
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var errors = {};
      if (!validDate(start.value)) errors.start_date = 'נא לבחור תאריך התחלה תקין.';
      if (amount.value === '' || isNaN(+amount.value) || +amount.value < 0) errors.amount = 'נא להזין סכום תקין בשקלים.';
      msg.hidden = true; setErrors(form, errors);
      if (Object.keys(errors).length) return;
      save.disabled = true;
      api('POST', 'clients/' + c.id + '/renew', { body: { start_date: start.value, amount: amount.value, note: note.value } }).then(function (r) {
        dlg.close(); toast(r.message + ' תוקף חדש: ' + fmtDate(r.client.expires_on), 'ok'); load();
      }, function (err) {
        save.disabled = false;
        if (err.errors && Object.keys(err.errors).length) setErrors(form, err.errors);
        msg.hidden = false; msg.textContent = err.message;
      });
    });
  }

  /* ---------------------------------------------------------------- history */

  function openHistory(c) {
    var body = h('div', null, h('p', { class: 'nivc-muted' }, 'טוען…'));
    dialog({ title: 'תשלומים וחידושים · ' + c.name, body: body, kind: 'modal wide' });
    api('GET', 'clients/' + c.id + '/payments').then(function (r) {
      clear(body);
      if (!r.items.length) { body.appendChild(h('p', null, 'לא נרשמו תשלומים.')); return; }
      var total = r.items.reduce(function (s, p) { return s + p.amount; }, 0);
      body.appendChild(h('div', { class: 'nivc-tablewrap nivc-tablewrap-plain' }, h('table', { class: 'nivc-table nivc-table-simple' },
        h('thead', null, h('tr', null, ['סוג', 'סכום', 'תאריך תשלום', 'תקופת שירות', 'הערה'].map(function (t) { return h('th', { scope: 'col' }, t); }))),
        h('tbody', null, r.items.map(function (p) {
          return h('tr', null,
            td('סוג', p.kind === 'renewal' ? 'חידוש' : 'תשלום ראשוני'),
            td('סכום', fmtMoney(p.amount)),
            td('תאריך תשלום', fmtDate(p.paid_on)),
            td('תקופת שירות', fmtDate(p.period_start) + ' – ' + fmtDate(p.period_end)),
            td('הערה', p.note || '—'));
        })))));
      body.appendChild(h('p', { class: 'nivc-total' }, 'סה״כ תשלומים שנרשמו: ', h('strong', null, fmtMoney(total))));
    }, function (err) { clear(body); body.appendChild(h('p', { class: 'nivc-err', role: 'alert' }, err.message)); });
  }

  /* -------------------------------------------------------------- analytics */

  function openAnalytics() {
    var body = h('div', { class: 'nivc-form' });
    var dlg = dialog({ title: 'נתוני צפיות בדפי נחיתה', body: body, kind: 'modal wide' });
    function draw(a) {
      clear(body);
      body.appendChild(h('p', { class: 'nivc-callout' }, icon('info'), h('span', null,
        a.connected ? 'המעקב הפנימי פעיל. הנתונים נאספים רק מרגע ההפעלה ואינם כוללים צפיות עבר.' : 'לא מחובר. לא נמצא מקור נתוני צפיות מחובר, ולכן לא מוצגים נתונים (ולא אפסים).')));
      var dl = h('dl', { class: 'nivc-dl' },
        h('dt', null, 'מקור'), h('dd', null, a.connected ? 'מעקב פנימי של התוסף (ללא עוגיות)' : 'לא מחובר'),
        h('dt', null, 'תקופת מדידה'), h('dd', null, a.since ? 'מ-' + fmtDateTime(a.since) + ' ועד עכשיו' : '—'),
        h('dt', null, 'עדכון אחרון'), h('dd', null, a.last_update ? fmtDateTime(a.last_update) : '—'),
        h('dt', null, 'דפים במעקב'), h('dd', null, a.connected ? fmtInt(a.pages_tracked) : '—'));
      body.appendChild(dl);
      body.appendChild(h('ul', { class: 'nivc-bullets' },
        h('li', null, h('strong', null, 'צפיות'), ' = כל טעינת דף. ', h('strong', null, 'מבקרים ייחודיים'), ' = מבקרים אנונימיים שונים ביום (נספרים מחדש בכל יום).'),
        h('li', null, 'נמדדים רק דפי נחיתה שנמצאים באתר הזה (עמוד וורדפרס או כתובת מאותו דומיין). כתובות חיצוניות מסומנות "לא נמדד".'),
        h('li', null, 'ביקורי מנהלים מחוברים ורובוטים מוחרגים. המעקב פועל גם עם מטמון עמודים, אבל עמוד שנשמר במטמון לפני שהלקוח נוסף דורש ניקוי מטמון כדי שיתחיל להימדד.'),
        a.detected && a.detected.length ? h('li', null, 'זוהו באתר: ' + a.detected.join(', ') + '. נתוני התוספים האלה אינם נקראים על ידי הדשבורד.') : null));
      var btn = h('button', { type: 'button', class: 'nivc-btn ' + (a.connected ? 'nivc-btn-ghost' : 'nivc-btn-primary') }, a.connected ? 'כיבוי המעקב' : 'הפעלת מעקב פנימי');
      btn.addEventListener('click', function () {
        btn.disabled = true;
        api('POST', 'analytics', { body: { enabled: !a.connected } }).then(function (r) { toast(r.message, 'ok'); draw(r); load(); }, function (err) { btn.disabled = false; toast(err.message, 'err'); });
      });
      body.appendChild(btn);
    }
    api('GET', 'analytics').then(draw, function (err) { clear(body); body.appendChild(h('p', { class: 'nivc-err', role: 'alert' }, err.message)); });
    return dlg;
  }

  /* ------------------------------------------------------------------- boot */

  buildShell();
  load();
})();
