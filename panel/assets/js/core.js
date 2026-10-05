/* NivCreative Panel – core UI library (vanilla JS). All data comes from the authorised JSON API. */
(function () {
  'use strict';

  var bootEl = document.getElementById('boot');
  var BOOT = bootEl ? JSON.parse(bootEl.textContent) : {};
  var NC = window.NC = { boot: BOOT, isAdmin: BOOT.area === 'admin' };
  var RTL = BOOT.dir === 'rtl';
  var LOCALE = BOOT.locale === 'he' ? 'he-IL' : 'en-GB';

  /* ------------------------------------------------------------------ i18n */
  NC.t = function (key, params) {
    var s = (BOOT.i18n && BOOT.i18n[key]) || key;
    if (params) Object.keys(params).forEach(function (k) { s = s.split('{' + k + '}').join(params[k]); });
    return s;
  };
  var t = NC.t;

  /* ------------------------------------------------------------------- DOM */
  function h(tag, attrs) {
    var node = document.createElement(tag);
    if (attrs) {
      Object.keys(attrs).forEach(function (k) {
        var v = attrs[k];
        if (v === null || v === undefined || v === false) return;
        if (k === 'class') node.className = v;
        else if (k.slice(0, 2) === 'on' && typeof v === 'function') node.addEventListener(k.slice(2), v);
        else if (k === 'value' || k === 'checked' || k === 'disabled' || k === 'selected' || k === 'readOnly') node[k] = v;
        else node.setAttribute(k, v === true ? '' : v);
      });
    }
    for (var i = 2; i < arguments.length; i++) append(node, arguments[i]);
    return node;
  }
  function append(node, c) {
    if (c === null || c === undefined || c === false) return;
    if (Array.isArray(c)) { c.forEach(function (x) { append(node, x); }); return; }
    node.appendChild(c.nodeType ? c : document.createTextNode(String(c)));
  }
  function clear(node) { while (node.firstChild) node.removeChild(node.firstChild); return node; }
  function icon(name, cls) {
    var s = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    s.setAttribute('class', 'i' + (cls ? ' ' + cls : ''));
    s.setAttribute('aria-hidden', 'true');
    var u = document.createElementNS('http://www.w3.org/2000/svg', 'use');
    u.setAttribute('href', '#i-' + name);
    s.appendChild(u);
    return s;
  }
  NC.h = h; NC.clear = clear; NC.icon = icon;

  /* --------------------------------------------------------------- helpers */
  NC.debounce = function (fn, ms) { var t0; return function () { var a = arguments, c = this; clearTimeout(t0); t0 = setTimeout(function () { fn.apply(c, a); }, ms); }; };
  NC.url = function (path) { return BOOT.base + path; };
  NC.params = function () { var o = {}; new URLSearchParams(location.search).forEach(function (v, k) { o[k] = v; }); return o; };
  NC.setParams = function (obj) {
    var q = new URLSearchParams();
    Object.keys(obj).forEach(function (k) { if (obj[k] !== '' && obj[k] !== null && obj[k] !== undefined) q.set(k, obj[k]); });
    var s = q.toString();
    history.replaceState(null, '', location.pathname + (s ? '?' + s : ''));
  };

  var nfInt = new Intl.NumberFormat(LOCALE);
  var nfDec = new Intl.NumberFormat(LOCALE, { maximumFractionDigits: 1 });
  var nfMoney = new Intl.NumberFormat(LOCALE, { style: 'currency', currency: 'ILS', maximumFractionDigits: 0 });
  var nfMoney2 = new Intl.NumberFormat(LOCALE, { style: 'currency', currency: 'ILS', maximumFractionDigits: 2 });
  NC.int = function (n) { return n === null || n === undefined ? '—' : nfInt.format(n); };
  NC.dec = function (n) { return n === null || n === undefined ? '—' : nfDec.format(n); };
  NC.money = function (n, exact) { return n === null || n === undefined ? '—' : (exact ? nfMoney2 : nfMoney).format(n); };
  NC.pct = function (n) { return n === null || n === undefined ? '—' : nfDec.format(n) + '%'; };
  function pad(n) { return (n < 10 ? '0' : '') + n; }
  NC.parseDate = function (s) { var m = /^(\d{4})-(\d{2})-(\d{2})/.exec(s || ''); return m ? new Date(+m[1], +m[2] - 1, +m[3]) : null; };
  NC.date = function (s) { var m = /^(\d{4})-(\d{2})-(\d{2})/.exec(s || ''); return m ? m[3] + '/' + m[2] + '/' + m[1] : '—'; };
  NC.dateTime = function (s) { var m = /^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/.exec(s || ''); return m ? m[3] + '/' + m[2] + '/' + m[1] + ' ' + m[4] + ':' + m[5] : '—'; };
  NC.iso = function (d) { return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()); };
  var rtf = window.Intl && Intl.RelativeTimeFormat ? new Intl.RelativeTimeFormat(LOCALE, { numeric: 'auto' }) : null;
  NC.ago = function (s) {
    var m = /^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2}):?(\d{2})?/.exec(s || ''); if (!m) return '—';
    var then = new Date(+m[1], +m[2] - 1, +m[3], +m[4], +m[5], +(m[6] || 0)).getTime();
    var diff = Math.round((then - Date.now()) / 1000), a = Math.abs(diff);
    if (!rtf) return NC.dateTime(s);
    if (a < 60) return rtf.format(0, 'second');
    if (a < 3600) return rtf.format(Math.round(diff / 60), 'minute');
    if (a < 86400) return rtf.format(Math.round(diff / 3600), 'hour');
    if (a < 86400 * 30) return rtf.format(Math.round(diff / 86400), 'day');
    return NC.date(s);
  };
  NC.initial = function (name) { return (String(name || '?').trim().charAt(0) || '?').toUpperCase(); };
  var HUES = ['#6d4cf5', '#3b82f6', '#16a37f', '#e8a317', '#e5484d', '#0ea5c6', '#c026d3'];
  NC.avatar = function (name, size) {
    var sum = 0; String(name || '').split('').forEach(function (c) { sum += c.charCodeAt(0); });
    var a = h('span', { class: 'avatar avatar-sm' + (size === 'lg' ? ' avatar-lg' : ''), 'aria-hidden': 'true' }, NC.initial(name));
    a.style.background = HUES[sum % HUES.length];
    return a;
  };
  NC.safeHref = function (u) { return /^https?:\/\//i.test(u || '') ? u : ''; };

  /* ------------------------------------------------------------------- API */
  function ApiError(message, status, fields, code) { this.message = message; this.status = status; this.fields = fields || {}; this.code = code; }
  NC.ApiError = ApiError;
  NC.api = function (method, path, opts) {
    opts = opts || {};
    var url = BOOT.base + '/api' + path;
    if (opts.params) {
      var q = new URLSearchParams();
      Object.keys(opts.params).forEach(function (k) { var v = opts.params[k]; if (v !== '' && v !== null && v !== undefined) q.set(k, v); });
      var qs = q.toString(); if (qs) url += '?' + qs;
    }
    var headers = { 'Accept': 'application/json' };
    if (method !== 'GET') { headers['X-CSRF-Token'] = BOOT.csrf; if (opts.body !== undefined) headers['Content-Type'] = 'application/json'; }
    return fetch(url, { method: method, headers: headers, credentials: 'same-origin', cache: 'no-store', signal: opts.signal, body: opts.body !== undefined ? JSON.stringify(opts.body) : undefined })
      .then(function (res) {
        return res.text().then(function (txt) {
          var json = null; try { json = txt ? JSON.parse(txt) : null; } catch (e) { /* not json */ }
          if (!res.ok) {
            if (res.status === 401) { location.href = NC.url('/login'); }
            var err = json && json.error ? json.error : {};
            throw new ApiError(err.message || t('error.generic'), res.status, err.fields, err.code);
          }
          return json;
        });
      }, function (e) {
        if (e && e.name === 'AbortError') throw e;
        throw new ApiError(t('error.network'), 0);
      });
  };
  NC.get = function (p, params, o) { return NC.api('GET', p, Object.assign({ params: params }, o || {})); };
  NC.post = function (p, body) { return NC.api('POST', p, { body: body || {} }); };
  NC.put = function (p, body) { return NC.api('PUT', p, { body: body || {} }); };
  NC.del = function (p) { return NC.api('DELETE', p); };

  /* ----------------------------------------------------------------- toast */
  var toastBox;
  NC.toast = function (msg, kind) {
    if (!toastBox) { toastBox = h('div', { class: 'toasts', 'aria-live': 'polite' }); document.getElementById('overlay-root').appendChild(toastBox); }
    var n = h('div', { class: 'toast toast-' + (kind || 'ok'), role: kind === 'err' ? 'alert' : 'status' }, icon(kind === 'err' ? 'alert' : 'check'), h('span', null, msg));
    toastBox.appendChild(n);
    setTimeout(function () { n.classList.add('out'); setTimeout(function () { n.remove(); }, 300); }, kind === 'err' ? 7000 : 3800);
  };

  /* --------------------------------------------------------- dialogs/drawer */
  var FOCUSABLE = 'a[href],button:not([disabled]),input:not([disabled]):not([type=hidden]),select:not([disabled]),textarea:not([disabled]),[tabindex]:not([tabindex="-1"])';
  function overlay(kind, o) {
    var prev = document.activeElement, titleId = 'dlg-' + Math.random().toString(36).slice(2, 8);
    var closeBtn = h('button', { type: 'button', class: 'icon-btn', 'aria-label': t('common.close'), onclick: function () { close(); } }, icon('x'));
    var box = h('div', { class: 'dialog dialog-' + kind + (o.wide ? ' dialog-wide' : ''), role: 'dialog', 'aria-modal': 'true', 'aria-labelledby': titleId },
      h('div', { class: 'dialog-head' }, h('h2', { id: titleId }, o.title), closeBtn),
      h('div', { class: 'dialog-body' }, o.body),
      o.footer ? h('div', { class: 'dialog-foot' }, o.footer) : null);
    var back = h('div', { class: 'backdrop backdrop-' + kind, onmousedown: function (e) { if (e.target === back && !o.sticky) close(); } }, box);
    document.getElementById('overlay-root').appendChild(back);
    document.documentElement.classList.add('lock');
    requestAnimationFrame(function () { back.classList.add('in'); });
    function key(e) {
      if (e.key === 'Escape') { e.stopPropagation(); close(); return; }
      if (e.key !== 'Tab') return;
      var f = box.querySelectorAll(FOCUSABLE); if (!f.length) return;
      var first = f[0], last = f[f.length - 1];
      if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
      else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
    }
    document.addEventListener('keydown', key, true);
    var closed = false;
    function close() {
      if (closed) return; closed = true;
      document.removeEventListener('keydown', key, true);
      back.classList.remove('in');
      setTimeout(function () { back.remove(); }, 180);
      if (!document.querySelector('.backdrop')) document.documentElement.classList.remove('lock');
      if (prev && prev.focus && document.contains(prev)) prev.focus();
      if (o.onClose) o.onClose();
    }
    setTimeout(function () { var f = o.focus ? box.querySelector(o.focus) : box.querySelector('input:not([type=hidden]):not([disabled]),select,textarea'); (f || closeBtn).focus(); }, 60);
    return { close: close, box: box, body: box.querySelector('.dialog-body'), foot: box.querySelector('.dialog-foot') };
  }
  NC.drawer = function (o) { return overlay('drawer', o); };
  NC.modal = function (o) { return overlay('modal', o); };
  NC.confirm = function (o) {
    return new Promise(function (resolve) {
      var done = false;
      var ok = h('button', { type: 'button', class: 'btn ' + (o.danger ? 'btn-danger' : 'btn-primary'), onclick: function () { done = true; d.close(); resolve(true); } }, o.confirmText || t('common.confirm'));
      var cancel = h('button', { type: 'button', class: 'btn btn-ghost', onclick: function () { d.close(); } }, t('common.cancel'));
      var d = NC.modal({ title: o.title, body: h('div', null, h('p', null, o.message), o.note ? h('p', { class: 'callout' }, icon('alert'), h('span', null, o.note)) : null), footer: [ok, cancel], focus: '.btn-ghost', onClose: function () { if (!done) resolve(false); } });
    });
  };

  /* ------------------------------------------------------------ dropdown menu */
  NC.menu = function (anchor, items) {
    closeMenus();
    var rect = anchor.getBoundingClientRect();
    var m = h('div', { class: 'menu', role: 'menu' }, items.filter(Boolean).map(function (it) {
      if (it.sep) return h('div', { class: 'menu-sep' });
      var tag = it.href ? 'a' : 'button';
      return h(tag, { class: 'menu-item' + (it.danger ? ' is-danger' : ''), role: 'menuitem', href: it.href, target: it.href && it.external ? '_blank' : null, rel: it.external ? 'noopener noreferrer' : null, type: it.href ? null : 'button',
        onclick: function () { closeMenus(); if (it.onClick) it.onClick(); } }, it.icon ? icon(it.icon) : null, h('span', null, it.label));
    }));
    document.getElementById('overlay-root').appendChild(m);
    var w = m.offsetWidth, hgt = m.offsetHeight;
    var left = RTL ? rect.left : rect.right - w; left = Math.max(8, Math.min(window.innerWidth - w - 8, left));
    var top = rect.bottom + 6; if (top + hgt > window.innerHeight - 8) top = Math.max(8, rect.top - hgt - 6);
    m.style.left = left + 'px'; m.style.top = top + 'px';
    anchor.setAttribute('aria-expanded', 'true');
    setTimeout(function () { var f = m.querySelector('.menu-item'); if (f) f.focus(); }, 0);
    m.addEventListener('keydown', function (e) {
      var its = Array.prototype.slice.call(m.querySelectorAll('.menu-item')), i = its.indexOf(document.activeElement);
      if (e.key === 'ArrowDown') { e.preventDefault(); its[(i + 1) % its.length].focus(); }
      else if (e.key === 'ArrowUp') { e.preventDefault(); its[(i - 1 + its.length) % its.length].focus(); }
      else if (e.key === 'Escape') { closeMenus(); anchor.focus(); }
    });
    setTimeout(function () { document.addEventListener('click', closeMenus, { once: true }); }, 0);
    function closeMenus() { document.querySelectorAll('.menu').forEach(function (x) { x.remove(); }); document.querySelectorAll('[aria-expanded="true"]').forEach(function (a) { if (a.id !== 'bell') a.setAttribute('aria-expanded', 'false'); }); }
  };
  function closeMenus() { document.querySelectorAll('.menu').forEach(function (x) { x.remove(); }); }

  /* -------------------------------------------------------- shared components */
  NC.badge = function (kind, text) { return h('span', { class: 'badge b-' + kind }, h('i', { class: 'dot' }), text); };
  NC.statusBadge = function (s) { return NC.badge('lead-' + s, t('status.' + s)); };
  NC.accountBadge = function (s) { return NC.badge('acc-' + s, t('account_status.' + s)); };
  NC.loading = function () { return h('div', { class: 'loading-block', role: 'status' }, h('span', { class: 'spinner' }), t('common.loading')); };
  NC.skeleton = function (rows) { var w = h('div', { class: 'skeleton' }); for (var i = 0; i < (rows || 5); i++) w.appendChild(h('div', { class: 'skel skel-row' })); return w; };
  NC.empty = function (iconName, title, text, action) {
    return h('div', { class: 'state' }, h('div', { class: 'state-ico' }, icon(iconName)), h('h3', null, title), text ? h('p', null, text) : null, action || null);
  };
  NC.errorState = function (msg, retry) {
    return h('div', { class: 'state state-err', role: 'alert' }, h('div', { class: 'state-ico' }, icon('alert')), h('h3', null, t('error.load_failed')), h('p', null, msg),
      retry ? h('button', { type: 'button', class: 'btn btn-primary', onclick: retry }, icon('refresh'), t('common.retry')) : null);
  };
  NC.card = function (title, body, opts) {
    opts = opts || {};
    return h('section', { class: 'card ' + (opts.cls || '') },
      title || opts.right ? h('header', { class: 'card-head' }, h('h2', null, title), opts.right || null) : null, body);
  };
  NC.delta = function (d) {
    if (d === null || d === undefined) return null;
    var up = d >= 0;
    return h('span', { class: 'delta ' + (up ? 'up' : 'down') }, up ? '+' : '', NC.dec(d) + '%');
  };
  NC.kpi = function (o) {
    return h('article', { class: 'kpi' },
      h('div', { class: 'kpi-top' }, h('span', { class: 'kpi-ico tone-' + (o.tone || 'violet') }, icon(o.icon)), h('p', { class: 'kpi-label' }, o.label)),
      h('p', { class: 'kpi-value' }, o.value), h('div', { class: 'kpi-foot' }, NC.delta(o.delta), o.note ? h('small', null, o.note) : null));
  };
  NC.pager = function (meta, onPage) {
    if (!meta || !meta.total) return h('div');
    var from = (meta.page - 1) * meta.per_page + 1, to = Math.min(meta.total, meta.page * meta.per_page);
    var nav = h('nav', { class: 'pager', 'aria-label': t('common.pagination') }, h('p', { class: 'muted' }, t('common.showing', { from: NC.int(from), to: NC.int(to), total: NC.int(meta.total) })));
    if (meta.pages > 1) {
      nav.appendChild(h('div', { class: 'pager-btns' },
        h('button', { type: 'button', class: 'btn btn-ghost btn-sm', disabled: meta.page <= 1, onclick: function () { onPage(meta.page - 1); } }, icon(RTL ? 'chev-right' : 'chev-left'), t('common.prev')),
        h('span', { class: 'muted' }, t('common.page_of', { page: meta.page, pages: meta.pages })),
        h('button', { type: 'button', class: 'btn btn-ghost btn-sm', disabled: meta.page >= meta.pages, onclick: function () { onPage(meta.page + 1); } }, t('common.next'), icon(RTL ? 'chev-left' : 'chev-right'))));
    }
    return nav;
  };
  NC.field = function (label, input, o) {
    o = o || {};
    return h('div', { class: 'field ' + (o.cls || '') }, h('label', { class: 'label', for: input.id }, label, o.req ? h('span', { class: 'req', 'aria-hidden': 'true' }, ' *') : null),
      input, o.hint ? h('small', { class: 'hint' }, o.hint) : null, h('small', { class: 'err', id: input.id + '-err', hidden: true, role: 'alert' }));
  };
  NC.setFieldErrors = function (root, fields) {
    var first = null;
    root.querySelectorAll('[data-f]').forEach(function (inp) {
      var msg = fields && fields[inp.getAttribute('data-f')], p = root.querySelector('#' + inp.id + '-err');
      if (p) { p.hidden = !msg; p.textContent = msg || ''; }
      if (msg) { inp.setAttribute('aria-invalid', 'true'); inp.setAttribute('aria-describedby', inp.id + '-err'); if (!first) first = inp; } else { inp.removeAttribute('aria-invalid'); }
    });
    if (first) first.focus();
  };
  NC.input = function (id, f, o) {
    o = o || {};
    var el = h(o.tag || 'input', Object.assign({ id: id, 'data-f': f, class: 'input' }, o.attrs || {}));
    if (o.tag === 'textarea') el.value = o.value || ''; else if (o.value !== undefined && o.value !== null) el.value = o.value;
    el.addEventListener('input', function () { var p = document.getElementById(id + '-err'); if (p && !p.hidden) { p.hidden = true; el.removeAttribute('aria-invalid'); } });
    return el;
  };
  NC.select = function (id, f, options, value) {
    var el = h('select', { id: id, 'data-f': f, class: 'input' }, options.map(function (o) { return h('option', { value: o[0], selected: String(o[0]) === String(value) }, o[1]); }));
    return el;
  };
  NC.download = function (path, params) {
    var q = new URLSearchParams();
    Object.keys(params || {}).forEach(function (k) { if (params[k] !== '' && params[k] != null) q.set(k, params[k]); });
    location.href = BOOT.base + '/api' + path + (q.toString() ? '?' + q : '');
  };
  NC.waLink = function (url, name, onClick) {
    if (!url) return h('span', { class: 'muted' }, '—');
    return h('a', { class: 'wa-btn', href: url, target: '_blank', rel: 'noopener noreferrer', 'aria-label': t('lead.open_whatsapp', { name: name || '' }), title: t('lead.open_whatsapp', { name: name || '' }), onclick: onClick }, icon('whatsapp'));
  };
  NC.copy = function (text) {
    var done = function () { NC.toast(t('common.copied'), 'ok'); };
    if (navigator.clipboard && window.isSecureContext) navigator.clipboard.writeText(text).then(done); else {
      var ta = h('textarea', { value: text, style: 'position:fixed;opacity:0' }); document.body.appendChild(ta); ta.select(); try { document.execCommand('copy'); done(); } catch (e) { /* ignore */ } ta.remove();
    }
  };

  /** One-time display of website API credentials + install snippets. w = {site_key, token}. */
  NC.showCredentials = function (w, siteUrl) {
    var origin = BOOT.origin || (location.origin + BOOT.base);
    function block(label, text) {
      return h('div', { class: 'code-block' }, h('div', { class: 'code-head' }, h('b', null, label), h('button', { type: 'button', class: 'btn btn-ghost btn-sm', onclick: function () { NC.copy(text); } }, icon('copy'), t('common.copy'))), h('pre', { class: 'ltr', tabindex: '0' }, text));
    }
    var snippet = '<script async src="' + (BOOT.assets || (origin + '/assets')) + '/js/tracker.js" data-site="' + w.site_key + '" data-endpoint="' + origin + '/api/v1/track"></script>';
    var body = h('div', { class: 'cred' },
      h('p', { class: 'callout callout-warn' }, icon('alert'), h('span', null, w.token ? t('website.token_once') : t('website.secret_hint'))),
      block(t('website.site_key'), w.site_key),
      w.token ? block(t('website.api_token'), w.token) : null,
      block(t('website.tracker_snippet'), snippet),
      block(t('website.endpoint_leads'), origin + '/api/v1/leads'),
      h('p', { class: 'muted' }, t('website.connector_note')));
    return NC.modal({ title: t('website.credentials'), body: body, wide: true, sticky: true });
  };

  /* -------------------------------------------------------------- range picker */
  NC.rangeFromPreset = function (preset) {
    var today = BOOT.today, t0 = NC.parseDate(today), d = function (n) { var x = new Date(t0); x.setDate(x.getDate() + n); return NC.iso(x); };
    switch (preset) {
      case 'today': return { from: today, to: today };
      case '7d': return { from: d(-6), to: today };
      case '30d': return { from: d(-29), to: today };
      case 'last_month': return { from: NC.iso(new Date(t0.getFullYear(), t0.getMonth() - 1, 1)), to: NC.iso(new Date(t0.getFullYear(), t0.getMonth(), 0)) };
      case 'year': return { from: t0.getFullYear() + '-01-01', to: today };
      default: return { from: NC.iso(new Date(t0.getFullYear(), t0.getMonth(), 1)), to: today };
    }
  };
  /** state: {preset,from,to}; calls onChange(state). */
  NC.rangePicker = function (state, onChange) {
    var presets = ['today', '7d', '30d', 'month', 'last_month', 'year'];
    var label = h('span', { class: 'range-label' });
    var btn = h('button', { type: 'button', class: 'select-btn', 'aria-haspopup': 'true', 'aria-expanded': 'false' }, icon('calendar'), label, icon('chev-down', 'i-sm'));
    var wrap = h('div', { class: 'range' }, btn);
    function paint() {
      var r = state.from && state.to ? state : NC.rangeFromPreset(state.preset || 'month');
      clear(label);
      var dates = h('span', { class: 'ltr' }, NC.date(r.from) + ' – ' + NC.date(r.to));
      if (state.preset && state.preset !== 'custom') { label.appendChild(document.createTextNode(t('range.' + state.preset) + ' · ')); }
      label.appendChild(dates);
    }
    paint();
    btn.addEventListener('click', function (e) {
      e.stopPropagation();
      var from = h('input', { type: 'date', class: 'input', value: state.from || '', 'aria-label': t('range.from') });
      var to = h('input', { type: 'date', class: 'input', value: state.to || '', 'aria-label': t('range.to') });
      var pop = h('div', { class: 'range-pop', role: 'dialog' },
        h('div', { class: 'range-presets' }, presets.map(function (p) {
          return h('button', { type: 'button', class: 'chip' + (state.preset === p ? ' is-on' : ''), onclick: function () { var r = NC.rangeFromPreset(p); state.preset = p; state.from = r.from; state.to = r.to; pop.remove(); paint(); onChange(state); } }, t('range.' + p));
        })),
        h('div', { class: 'range-custom' }, h('label', null, t('range.from'), from), h('label', null, t('range.to'), to),
          h('button', { type: 'button', class: 'btn btn-primary btn-sm', onclick: function () {
            if (!from.value || !to.value || from.value > to.value) { NC.toast(t('range.invalid'), 'err'); return; }
            state.preset = 'custom'; state.from = from.value; state.to = to.value; pop.remove(); paint(); onChange(state);
          } }, t('common.apply'))));
      var old = wrap.querySelector('.range-pop'); if (old) { old.remove(); return; }
      wrap.appendChild(pop);
      setTimeout(function () { document.addEventListener('click', function f(ev) { if (!pop.contains(ev.target)) { pop.remove(); document.removeEventListener('click', f); } }); }, 0);
    });
    return wrap;
  };

  /* ------------------------------------------------------------------ layout */
  NC.view = document.getElementById('view');
  NC.page = function (fn) { if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', function () { fn(NC.view); }); else fn(NC.view); };

  function initLayout() {
    var path = location.pathname.replace(/\/$/, '') || '/';
    var best = null;
    document.querySelectorAll('.nav-item').forEach(function (a) {
      var p = (BOOT.base + a.getAttribute('data-nav')).replace(/\/$/, '');
      if (path === p || (p !== BOOT.base + '/admin' && path.indexOf(p + '/') === 0)) { if (!best || p.length > best.p.length) best = { a: a, p: p }; }
    });
    if (path.indexOf('/admin/clients/') >= 0) { best = { a: document.querySelector('[data-nav="/admin/clients"]') }; }
    if (best && best.a) { best.a.classList.add('is-active'); best.a.setAttribute('aria-current', 'page'); }

    var side = document.getElementById('sidebar');
    document.querySelectorAll('[data-open-sidebar]').forEach(function (b) { b.addEventListener('click', function () { document.body.classList.add('sidebar-open'); }); });
    document.querySelectorAll('[data-close-sidebar]').forEach(function (b) { b.addEventListener('click', function () { document.body.classList.remove('sidebar-open'); }); });
    side.querySelectorAll('a').forEach(function (a) { a.addEventListener('click', function () { document.body.classList.remove('sidebar-open'); }); });

    document.querySelectorAll('[data-set-lang]').forEach(function (b) {
      b.addEventListener('click', function () {
        fetch(BOOT.base + '/set-language', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': BOOT.csrf }, body: JSON.stringify({ locale: b.getAttribute('data-set-lang') }) })
          .then(function () { location.reload(); }, function () { location.reload(); });
      });
    });
    document.querySelectorAll('[data-action="add-client"]').forEach(function (b) {
      b.addEventListener('click', function () { document.body.classList.remove('sidebar-open'); if (window.NC_addClient) window.NC_addClient(); else location.href = NC.url('/admin/clients?add=1'); });
    });
    initBell();
    initGlobalSearch();
  }

  function setUnread(n) { document.querySelectorAll('[data-unread]').forEach(function (e) { e.textContent = n > 99 ? '99+' : n; e.hidden = !n; }); }
  NC.setUnread = setUnread;

  function initBell() {
    var bell = document.getElementById('bell'), pop = document.getElementById('notif-pop'); if (!bell) return;
    function close() { pop.hidden = true; bell.setAttribute('aria-expanded', 'false'); }
    bell.addEventListener('click', function (e) {
      e.stopPropagation();
      if (!pop.hidden) return close();
      pop.hidden = false; bell.setAttribute('aria-expanded', 'true');
      clear(pop).appendChild(NC.loading());
      NC.get('/notifications').then(function (r) {
        setUnread(r.unread); clear(pop);
        pop.appendChild(h('div', { class: 'pop-head' }, h('b', null, t('nav.notifications')),
          r.unread ? h('button', { type: 'button', class: 'link', onclick: function () { NC.post('/notifications/read', {}).then(function (x) { setUnread(x.unread); close(); }); } }, t('notif.mark_all')) : null));
        if (!r.items.length) pop.appendChild(h('p', { class: 'pop-empty' }, t('notif.empty')));
        r.items.slice(0, 8).forEach(function (n) { pop.appendChild(NC.notifItem(n, close)); });
        pop.appendChild(h('a', { class: 'pop-foot', href: NC.url(NC.isAdmin ? '/admin/notifications' : '/notifications') }, t('notif.view_all')));
      }, function (err) { clear(pop).appendChild(h('p', { class: 'pop-empty' }, err.message)); });
    });
    document.addEventListener('click', function (e) { if (!pop.hidden && !pop.contains(e.target)) close(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !pop.hidden) { close(); bell.focus(); } });
  }
  NC.notifItem = function (n, after) {
    var tone = { success: 'green', warning: 'amber', danger: 'red', info: 'violet' }[n.severity] || 'violet';
    var ico = { success: 'check', warning: 'clock', danger: 'alert', info: 'bell' }[n.severity] || 'bell';
    var a = h('a', { class: 'notif-item' + (n.read ? '' : ' unread'), href: n.link ? NC.url(n.link) : '#',
      onclick: function () { if (!n.read) NC.post('/notifications/read', { ids: [n.id] }).then(function (x) { setUnread(x.unread); }); if (after) after(); } },
      h('span', { class: 'kpi-ico tone-' + tone }, icon(ico)),
      h('span', { class: 'notif-text' }, h('b', null, n.title), h('small', null, n.body), h('em', null, NC.ago(n.created_at))));
    return a;
  };

  function initGlobalSearch() {
    var input = document.getElementById('gsearch'), box = document.getElementById('gsearch-results'); if (!input) return;
    var seq = 0;
    function hide() { box.hidden = true; clear(box); }
    input.addEventListener('input', NC.debounce(function () {
      var q = input.value.trim(); if (q.length < 2) return hide();
      var my = ++seq;
      var reqs = [NC.get('/leads', { search: q, per_page: 5 })];
      if (NC.isAdmin) reqs.push(NC.get('/clients', { search: q, per_page: 5 }));
      Promise.all(reqs).then(function (res) {
        if (my !== seq) return;
        clear(box); var any = false;
        if (NC.isAdmin && res[1].items.length) {
          any = true; box.appendChild(h('div', { class: 'gs-group' }, t('nav.clients')));
          res[1].items.forEach(function (c) { box.appendChild(h('a', { class: 'gs-item', href: NC.url('/admin/clients/' + c.id) }, NC.avatar(c.business_name), h('span', null, h('b', null, c.business_name), h('small', null, c.contact_name)))); });
        }
        if (res[0].items.length) {
          any = true; box.appendChild(h('div', { class: 'gs-group' }, t('nav.leads')));
          res[0].items.forEach(function (l) { box.appendChild(h('a', { class: 'gs-item', href: NC.url((NC.isAdmin ? '/admin/leads' : '/leads') + '?open=' + l.id) }, NC.avatar(l.name || l.phone), h('span', null, h('b', null, l.name || l.phone), h('small', { dir: 'auto' }, l.phone || l.email)))); });
        }
        if (!any) box.appendChild(h('p', { class: 'pop-empty' }, t('common.no_results')));
        box.hidden = false;
      }, hide);
    }, 250));
    input.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' && input.value.trim()) { location.href = NC.url((NC.isAdmin ? '/admin/leads' : '/leads') + '?search=' + encodeURIComponent(input.value.trim())); }
      if (e.key === 'Escape') { hide(); input.blur(); }
    });
    document.addEventListener('click', function (e) { if (!box.contains(e.target) && e.target !== input) hide(); });
  }

  /** Pre-built status/source option lists for filters. */
  NC.statusOptions = function (allLabel) { return [['', allLabel || t('common.all')]].concat(BOOT.statuses.map(function (s) { return [s, t('status.' + s)]; })); };
  NC.sourceOptions = function () { return [['', t('common.all')]].concat(BOOT.sources.map(function (s) { return [s, t('source.' + s)]; })); };
  NC.sourceColors = { instagram: '#e1306c', facebook: '#3b82f6', google: '#22c1a1', organic: '#f5a524', direct: '#8b95b8', whatsapp: '#16a34a', other: '#a78bfa' };

  /**
   * Responsive data table. columns: [{label, render(row)->node|string, cls, sort:'key'}], rows: [...]
   * opts: {sort, dir, onSort(key), onRow(row), rowClass(row), caption}
   */
  NC.table = function (columns, rows, opts) {
    opts = opts || {};
    var head = h('tr', null, columns.map(function (c) {
      if (!c.sort || !opts.onSort) return h('th', { scope: 'col', class: c.cls || '' }, c.label);
      var on = opts.sort === c.sort;
      return h('th', { scope: 'col', class: (c.cls || '') + ' sortable', 'aria-sort': on ? (opts.dir === 'asc' ? 'ascending' : 'descending') : 'none' },
        h('button', { type: 'button', class: 'sort-btn' + (on ? ' is-on' : ''), onclick: function () { opts.onSort(c.sort); } }, c.label, on ? icon(opts.dir === 'asc' ? 'chev-down' : 'chev-down', 'i-sm sort-ico ' + (opts.dir === 'asc' ? 'asc' : 'desc')) : null));
    }));
    var body = h('tbody', null, rows.map(function (r) {
      var tr = h('tr', { class: (opts.onRow ? 'clickable ' : '') + (opts.rowClass ? opts.rowClass(r) : ''), tabindex: opts.onRow ? '0' : null },
        columns.map(function (c) { return h('td', { 'data-label': c.label, class: c.cls || '' }, c.render(r)); }));
      if (opts.onRow) {
        tr.addEventListener('click', function (e) { if (e.target.closest('a,button,input,select,label')) return; opts.onRow(r); });
        tr.addEventListener('keydown', function (e) { if ((e.key === 'Enter' || e.key === ' ') && e.target === tr) { e.preventDefault(); opts.onRow(r); } });
      }
      return tr;
    }));
    return h('div', { class: 'table-wrap' }, h('table', { class: 'table' }, opts.caption ? h('caption', { class: 'sr' }, opts.caption) : null, h('thead', null, head), body));
  };
  /** Source chip with coloured dot. */
  NC.sourceTag = function (s) { var d = h('i', { class: 'dot' }); d.style.background = NC.sourceColors[s] || '#8b95b8'; return h('span', { class: 'src' }, d, t('source.' + s)); };
  /** Row action menu button (three dots). */
  NC.moreBtn = function (label, items) {
    var b = h('button', { type: 'button', class: 'icon-btn', 'aria-label': label, 'aria-haspopup': 'true', 'aria-expanded': 'false', title: label }, icon('more'));
    b.addEventListener('click', function (e) { e.stopPropagation(); NC.menu(b, typeof items === 'function' ? items() : items); });
    return b;
  };

  initLayout();
})();
