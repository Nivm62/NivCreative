/* Admin: clients list + add/edit drawer + extend subscription. */
NC.page(function (view) {
  'use strict';
  var h = NC.h, t = NC.t, icon = NC.icon;
  var P = NC.params();
  var state = { search: P.search || '', status: P.status || '', payment_status: '', connection: '', sort: 'created', dir: 'desc', page: 1, per_page: 25 };
  var listBox = h('div'), pagerBox = h('div'), ctl = null;

  function addYear(d) { var m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(d); if (!m) return ''; var y = +m[1] + 1, mo = +m[2], day = +m[3], last = new Date(y, mo, 0).getDate(); return y + '-' + String(mo).padStart(2, '0') + '-' + String(Math.min(day, last)).padStart(2, '0'); }
  function countdown(c) {
    if (c.days_left === null) return h('small', { class: 'muted block' }, t('client.no_subscription'));
    var txt = c.days_left < 0 ? t('client.expired_days', { days: Math.abs(c.days_left) }) : (c.days_left === 0 ? t('client.expires_today') : (c.days_left <= 30 ? t('client.expires_in', { days: c.days_left }) : t('client.days_left', { days: c.days_left })));
    return h('small', { class: 'block ' + (c.days_left < 0 ? 'txt-red' : (c.days_left <= 30 ? 'txt-amber' : 'muted')) }, txt);
  }
  function connBadge(c) {
    if (c.connection === 'none') return h('span', { class: 'muted' }, '—');
    return NC.badge(c.connection === 'connected' ? 'conn-ok' : 'conn-bad', t('connection.' + c.connection));
  }

  function load() {
    if (ctl) ctl.abort(); ctl = window.AbortController ? new AbortController() : null;
    NC.clear(listBox).appendChild(NC.skeleton(6));
    NC.get('/clients', state, { signal: ctl ? ctl.signal : undefined }).then(function (r) {
      NC.clear(listBox); NC.clear(pagerBox);
      if (!r.items.length) {
        listBox.appendChild(state.search || state.status || state.payment_status || state.connection
          ? NC.empty('search', t('client.none_found'), t('client.none_found_hint'), h('button', { type: 'button', class: 'btn btn-ghost', onclick: clearAll }, t('common.clear_filters')))
          : NC.empty('users', t('client.empty_title'), t('client.empty_text'), h('button', { type: 'button', class: 'btn btn-primary', onclick: function () { openForm(null); } }, icon('plus'), t('client.add'))));
        return;
      }
      var cols = [
        { label: t('client.client'), sort: 'name', cls: 'c-name', render: function (c) { return h('div', { class: 'cell-person' }, NC.avatar(c.business_name), h('div', null, h('b', null, c.business_name), h('small', { class: 'muted block' }, c.contact_name), h('small', { class: 'muted block ltr ellip' }, c.email))); } },
        { label: t('client.phone'), render: function (c) { return h('div', { class: 'cell-inline' }, h('span', { class: 'ltr nowrap' }, c.phone), NC.waLink(c.whatsapp_url, c.contact_name)); } },
        { label: t('client.website'), render: function (c) { var u = NC.safeHref(c.website_url); return h('div', null, u ? h('a', { class: 'ext-link ltr', href: u, target: '_blank', rel: 'noopener noreferrer' }, u.replace(/^https?:\/\/(www\.)?/, '').replace(/\/$/, ''), icon('external', 'i-sm')) : '—', h('small', { class: 'muted block' }, t('client.pages_count', { count: c.pages_count }))); } },
        { label: t('client.performance'), sort: 'leads', render: function (c) { return h('div', { class: 'perf' }, h('b', null, t('client.leads_n', { n: NC.int(c.leads_count) })), h('small', { class: 'muted block' }, t('client.views_n', { n: NC.int(c.views) }) + ' · ' + t('client.conv_n', { n: NC.pct(c.conversion) }))); } },
        { label: t('client.plan') + ' / ' + t('client.amount_paid'), sort: 'paid', render: function (c) { return h('div', null, h('b', null, NC.money(c.amount_paid)), h('small', { class: 'muted block' }, (c.plan ? (t('plan.' + c.plan) === 'plan.' + c.plan ? c.plan : t('plan.' + c.plan)) : '—') + (c.payment_status && c.payment_status !== 'paid' ? ' · ' + t('payment.' + c.payment_status) : ''))); } },
        { label: t('client.subscription'), sort: 'expires', render: function (c) { return h('div', null, h('span', { class: 'ltr nowrap' }, NC.date(c.start_date) + ' – ' + NC.date(c.end_date)), countdown(c)); } },
        { label: t('client.status'), render: function (c) { return h('div', { class: 'stack' }, NC.accountBadge(c.account_status), connBadge(c)); } },
        { label: t('common.actions'), cls: 'c-actions', render: function (c) {
          return h('div', { class: 'row-actions' },
            h('a', { class: 'btn btn-soft btn-sm', href: NC.url('/admin/clients/' + c.id) }, t('client.open_dashboard')),
            h('button', { type: 'button', class: 'icon-btn', 'aria-label': t('common.edit') + ' ' + c.business_name, title: t('common.edit'), onclick: function () { openForm(c); } }, icon('edit')),
            NC.moreBtn(t('common.actions'), function () {
              var u = NC.safeHref(c.website_url);
              return [
                { label: t('client.view_leads'), icon: 'target', href: NC.url('/admin/leads?client_id=' + c.id) },
                { label: t('client.extend'), icon: 'refresh', onClick: function () { openExtend(c); } },
                u ? { label: t('client.open_website'), icon: 'external', href: u, external: true } : null,
                c.whatsapp_url ? { label: t('lead.whatsapp'), icon: 'whatsapp', href: c.whatsapp_url, external: true } : null,
                { sep: true },
                c.status === 'active' ? { label: t('client.disable'), icon: 'pause', onClick: function () { toggleStatus(c, 'disabled'); } } : { label: t('client.enable'), icon: 'play', onClick: function () { toggleStatus(c, 'active'); } },
                { label: t('common.delete'), icon: 'trash', danger: true, onClick: function () { del(c); } }
              ];
            }));
        } }
      ];
      listBox.appendChild(NC.table(cols, r.items, { sort: state.sort, dir: state.dir, caption: t('nav.clients'), onSort: function (k) { if (state.sort === k) state.dir = state.dir === 'asc' ? 'desc' : 'asc'; else { state.sort = k; state.dir = 'desc'; } load(); } }));
      pagerBox.appendChild(NC.pager(r.meta, function (pg) { state.page = pg; load(); }));
    }, function (e) { if (e.name !== 'AbortError') NC.clear(listBox).appendChild(NC.errorState(e.message, load)); });
  }

  function toggleStatus(c, status) {
    NC.confirm({ title: t(status === 'disabled' ? 'client.disable' : 'client.enable'), message: t(status === 'disabled' ? 'client.disable_confirm' : 'client.enable_confirm', { name: c.business_name }), danger: status === 'disabled', confirmText: t(status === 'disabled' ? 'client.disable' : 'client.enable') })
      .then(function (ok) { if (!ok) return; NC.post('/clients/' + c.id + '/status', { status: status }).then(function () { NC.toast(t('client.updated')); load(); }, function (e) { NC.toast(e.message, 'err'); }); });
  }
  function del(c) {
    NC.confirm({ title: t('client.delete'), message: t('client.delete_confirm', { name: c.business_name }), note: t('client.delete_note'), danger: true, confirmText: t('client.delete') })
      .then(function (ok) { if (!ok) return; NC.del('/clients/' + c.id).then(function (r) { NC.toast(r.message); load(); }, function (e) { NC.toast(e.message, 'err'); }); });
  }

  /* ------------------------------------------------------- add / edit form */
  function openForm(c) {
    var edit = !!c, today = NC.boot.today;
    var f = {};
    f.contact_name = NC.input('cf-contact', 'contact_name', { value: c ? c.contact_name : '', attrs: { maxlength: 190, autocomplete: 'off' } });
    f.business_name = NC.input('cf-business', 'business_name', { value: c ? c.business_name : '', attrs: { maxlength: 190, autocomplete: 'off' } });
    f.phone = NC.input('cf-phone', 'phone', { value: c ? c.phone : '', attrs: { type: 'tel', dir: 'ltr', inputmode: 'tel', placeholder: '050-1234567' } });
    f.whatsapp = NC.input('cf-wa', 'whatsapp', { value: '', attrs: { type: 'tel', dir: 'ltr', inputmode: 'tel', placeholder: t('client.whatsapp_same') } });
    f.email = NC.input('cf-email', 'email', { value: c ? c.email : '', attrs: { type: 'email', dir: 'ltr', autocomplete: 'off' } });
    f.password = NC.input('cf-pass', 'password', { value: '', attrs: { type: 'text', dir: 'ltr', autocomplete: 'new-password', placeholder: edit ? t('client.password_keep') : '' } });
    f.create_login = NC.select('cf-login', 'create_login', [['1', t('client.login_yes')], ['0', t('client.login_no')]], '1');
    f.create_login.addEventListener('change', function () { f.password.disabled = f.create_login.value === '0'; if (f.password.disabled) f.password.value = ''; });
    f.website_url = NC.input('cf-site', 'website_url', { value: c ? c.website_url : '', attrs: { type: 'url', dir: 'ltr', placeholder: 'https://client-site.co.il' } });
    f.landing_url = NC.input('cf-landing', 'landing_url', { value: '', attrs: { type: 'url', dir: 'ltr', placeholder: 'https://client-site.co.il/landing' } });
    f.plan = NC.select('cf-plan', 'plan', [['', '—']].concat(NC.boot.plans.map(function (p) { return [p, t('plan.' + p)]; })), c ? c.plan : 'basic');
    f.amount = NC.input('cf-amount', 'amount', { value: c ? c.amount_paid : '', attrs: { type: 'number', min: '0', step: '0.01', inputmode: 'decimal', placeholder: '0' } });
    f.start_date = NC.input('cf-start', 'start_date', { value: c && c.start_date ? c.start_date : today, attrs: { type: 'date' } });
    f.end_date = NC.input('cf-end', 'end_date', { value: c && c.end_date ? c.end_date : addYear(today), attrs: { type: 'date' } });
    f.payment_status = NC.select('cf-pay', 'payment_status', NC.boot.payment.map(function (p) { return [p, t('payment.' + p)]; }), c && c.payment_status ? c.payment_status : 'paid');
    f.status = NC.select('cf-status', 'status', [['active', t('account_status.active')], ['disabled', t('account_status.disabled')]], c ? c.status : 'active');
    f.notes = NC.input('cf-notes', 'notes', { tag: 'textarea', value: c ? c.notes : '', attrs: { rows: 3, maxlength: 5000 } });
    var endTouched = edit;
    f.start_date.addEventListener('change', function () { if (!endTouched) f.end_date.value = addYear(f.start_date.value); });
    f.end_date.addEventListener('input', function () { endTouched = true; });
    var msg = h('p', { class: 'err form-err', hidden: true, role: 'alert' });
    var save = h('button', { type: 'submit', form: 'client-form', class: 'btn btn-primary' }, edit ? t('common.save_changes') : t('client.add'));
    var form = h('form', { id: 'client-form', class: 'form', novalidate: true },
      h('h3', { class: 'form-h' }, t('client.section_contact')),
      h('div', { class: 'grid-2' }, NC.field(t('client.contact_name'), f.contact_name, { req: true }), NC.field(t('client.business_name'), f.business_name, { req: true })),
      h('div', { class: 'grid-2' }, NC.field(t('client.phone'), f.phone, { req: true }), NC.field(t('client.whatsapp'), f.whatsapp)),
      h('div', { class: 'grid-2' }, NC.field(t('auth.email'), f.email, { req: true }), NC.field(t('client.password'), f.password, { req: !edit, hint: t('validation.password_rule') })),
      edit ? null : NC.field(t('client.login'), f.create_login, { hint: t('client.login_hint') }),
      h('h3', { class: 'form-h' }, t('client.section_site')),
      NC.field(t('client.website_url'), f.website_url, { hint: edit ? t('client.website_edit_hint') : t('client.website_hint') }),
      edit ? null : NC.field(t('client.landing_url'), f.landing_url),
      h('h3', { class: 'form-h' }, t('client.section_service')),
      h('div', { class: 'grid-2' }, NC.field(t('client.plan'), f.plan), NC.field(t('client.amount_paid') + ' (₪)', f.amount, { req: !edit || true, hint: edit ? t('client.amount_hint_edit') : '' })),
      h('div', { class: 'grid-2' }, NC.field(t('client.start_date'), f.start_date, { req: !edit }), NC.field(t('client.end_date'), f.end_date, { req: !edit })),
      h('div', { class: 'grid-2' }, NC.field(t('client.payment_status'), f.payment_status), NC.field(t('client.status'), f.status)),
      NC.field(t('client.notes'), f.notes), msg);
    if (edit) {
      // Edit shows the subscription's total of paid amounts read-only? Keep it simple: amount edits the current subscription amount.
      NC.get('/clients/' + c.id).then(function (r) { if (r.client.subscription_id === null) { f.amount.value = ''; } });
    }
    var d = NC.drawer({ title: edit ? t('client.edit') : t('client.add'), body: form, footer: [save, h('button', { type: 'button', class: 'btn btn-ghost', onclick: function () { d.close(); } }, t('common.cancel'))], sticky: true });
    form.addEventListener('submit', function (e) {
      e.preventDefault(); msg.hidden = true; NC.setFieldErrors(form, null);
      var body = {}; Object.keys(f).forEach(function (k) { body[k] = f[k].value; });
      save.disabled = true;
      (edit ? NC.put('/clients/' + c.id, body) : NC.post('/clients', body)).then(function (r) {
        d.close(); NC.toast(r.message); load();
        if (!edit && r.result.website) showCredentials(r.result.website, body.website_url);
      }, function (err) {
        save.disabled = false;
        if (err.fields && Object.keys(err.fields).length) NC.setFieldErrors(form, err.fields);
        msg.hidden = false; msg.textContent = err.message;
      });
    });
  }

  /** One-time display of the website API credentials. */
  function showCredentials(w, url) {
    window.NC.showCredentials(w, url);
  }

  /* --------------------------------------------------------------- extend */
  function openExtend(c) {
    var today = NC.boot.today;
    var startDefault = c.end_date && c.end_date >= today ? c.end_date : today;
    var f = {
      start_date: NC.input('ex-start', 'start_date', { value: startDefault, attrs: { type: 'date' } }),
      months: NC.select('ex-months', 'months', [[1, t('client.months', { n: 1 })], [3, t('client.months', { n: 3 })], [6, t('client.months', { n: 6 })], [12, t('client.months', { n: 12 })], [24, t('client.months', { n: 24 })]], 12),
      amount: NC.input('ex-amount', 'amount', { value: '', attrs: { type: 'number', min: '0', step: '0.01', inputmode: 'decimal', placeholder: '0' } }),
      plan: NC.select('ex-plan', 'plan', NC.boot.plans.map(function (p) { return [p, t('plan.' + p)]; }), c.plan || 'basic'),
      payment_status: NC.select('ex-pay', 'payment_status', NC.boot.payment.map(function (p) { return [p, t('payment.' + p)]; }), 'paid'),
      note: NC.input('ex-note', 'note', { value: '', attrs: { maxlength: 255 } })
    };
    var preview = h('p', { class: 'callout' }, icon('calendar'), h('span'));
    function upd() { var s = f.start_date.value; if (!/^\d{4}-\d{2}-\d{2}$/.test(s)) { preview.lastChild.textContent = ''; return; } var m = +f.months.value, d = NC.parseDate(s); d.setMonth(d.getMonth() + m); var iso = m % 12 === 0 ? addYear(s) : NC.iso(d); if (m === 24) iso = addYear(addYear(s)); preview.lastChild.textContent = t('client.extend_preview', { date: NC.date(iso) }); }
    f.start_date.addEventListener('input', upd); f.months.addEventListener('change', upd); upd();
    var msg = h('p', { class: 'err form-err', hidden: true, role: 'alert' });
    var save = h('button', { type: 'submit', form: 'extend-form', class: 'btn btn-primary' }, icon('refresh'), t('client.extend_confirm'));
    var form = h('form', { id: 'extend-form', class: 'form', novalidate: true },
      h('p', null, t('client.extend_intro', { name: c.business_name, date: NC.date(c.end_date) })),
      h('div', { class: 'grid-2' }, NC.field(t('client.new_start'), f.start_date, { req: true }), NC.field(t('client.duration'), f.months)),
      h('div', { class: 'grid-2' }, NC.field(t('client.amount_paid') + ' (₪)', f.amount, { req: true }), NC.field(t('client.plan'), f.plan)),
      h('div', { class: 'grid-2' }, NC.field(t('client.payment_status'), f.payment_status), NC.field(t('client.note'), f.note)), preview, msg);
    var d = NC.modal({ title: t('client.extend'), body: form, footer: [save, h('button', { type: 'button', class: 'btn btn-ghost', onclick: function () { d.close(); } }, t('common.cancel'))] });
    form.addEventListener('submit', function (e) {
      e.preventDefault(); NC.setFieldErrors(form, null); msg.hidden = true; save.disabled = true;
      var body = {}; Object.keys(f).forEach(function (k) { body[k] = f[k].value; });
      NC.post('/clients/' + c.id + '/extend', body).then(function (r) { d.close(); NC.toast(r.message); load(); }, function (err) { save.disabled = false; if (err.fields) NC.setFieldErrors(form, err.fields); msg.hidden = false; msg.textContent = err.message; });
    });
  }

  /* -------------------------------------------------------------- page shell */
  function clearAll() { state.search = ''; state.status = ''; state.payment_status = ''; state.connection = ''; state.page = 1; search.value = ''; st.value = ''; ps.value = ''; cn.value = ''; load(); }
  var search = h('input', { type: 'search', class: 'input', value: state.search, placeholder: t('client.search_placeholder'), 'aria-label': t('common.search'), autocomplete: 'off' });
  search.addEventListener('input', NC.debounce(function () { state.search = search.value.trim(); state.page = 1; load(); }, 280));
  function mkSel(key, opts, label) { var s = h('select', { class: 'input', 'aria-label': label }, opts.map(function (o) { return h('option', { value: o[0], selected: o[0] === state[key] }, o[1]); })); s.addEventListener('change', function () { state[key] = s.value; state.page = 1; load(); }); return s; }
  var st = mkSel('status', [['', t('filters.account_status') + ': ' + t('common.all')], ['active', t('account_status.active')], ['expiring', t('account_status.expiring')], ['expired', t('account_status.expired')], ['disabled', t('account_status.disabled')]], t('filters.account_status'));
  var ps = mkSel('payment_status', [['', t('client.payment_status') + ': ' + t('common.all')]].concat(NC.boot.payment.map(function (p) { return [p, t('payment.' + p)]; })), t('client.payment_status'));
  var cn = mkSel('connection', [['', t('client.connection') + ': ' + t('common.all')], ['connected', t('connection.connected')], ['disconnected', t('connection.disconnected')]], t('client.connection'));

  var SORTS = [['created', t('client.sort_created')], ['name', t('client.client')], ['leads', t('kpi.total_leads')], ['views', t('kpi.views')], ['conversion', t('kpi.conversion')], ['paid', t('client.amount_paid')], ['start', t('client.start_date')], ['expires', t('client.end_date')]];
  var sortSel = h('select', { class: 'input', 'aria-label': t('common.sort_by') }, SORTS.map(function (o) { return h('option', { value: o[0], selected: o[0] === state.sort }, t('common.sort_by') + ': ' + o[1]); }));
  sortSel.addEventListener('change', function () { state.sort = sortSel.value; state.page = 1; load(); });
  NC.clear(view).append(
    h('div', { class: 'page-head' }, h('div', null, h('h1', null, t('nav.clients')), h('p', { class: 'sub' }, t('client.sub'))),
      h('div', { class: 'head-actions' }, h('button', { type: 'button', class: 'btn btn-primary', onclick: function () { openForm(null); } }, icon('plus'), t('client.add')))),
    NC.card(null, h('div', null, h('div', { class: 'toolbar' }, h('div', { class: 'search-field' }, icon('search'), search), st, ps, cn, sortSel, h('button', { type: 'button', class: 'btn btn-text', onclick: clearAll }, t('common.clear_filters'))), listBox, pagerBox), { cls: 'card-flush' }));

  window.NC_addClient = function () { openForm(null); };
  load();
  if (P.add === '1') openForm(null);
});
