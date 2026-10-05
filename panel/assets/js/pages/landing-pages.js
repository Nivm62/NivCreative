/* Landing pages (admin: manage all; client: read-only stats for own pages). */
NC.page(function (view) {
  'use strict';
  var h = NC.h, t = NC.t, icon = NC.icon, admin = NC.isAdmin;
  var state = { search: '', status: '', website_id: '', sort: 'created', dir: 'desc', page: 1, per_page: 25 };
  var listBox = h('div'), pagerBox = h('div'), ctl = null, sites = [];

  function statusBadge(s) { return NC.badge('page-' + s, t('page_status.' + s)); }

  function load() {
    if (ctl) ctl.abort(); ctl = window.AbortController ? new AbortController() : null;
    NC.clear(listBox).appendChild(NC.skeleton(5));
    NC.get('/landing-pages', state, { signal: ctl ? ctl.signal : undefined }).then(function (r) {
      NC.clear(listBox); NC.clear(pagerBox);
      if (!r.items.length) { listBox.appendChild(NC.empty('file', t('page.empty_title'), t(admin ? 'page.empty_admin' : 'page.empty_client'), admin ? h('button', { type: 'button', class: 'btn btn-primary', onclick: function () { openForm(null); } }, icon('plus'), t('page.add')) : null)); return; }
      var cols = [
        admin ? { label: t('nav.clients'), render: function (p) { return p.client_name; } } : null,
        admin ? { label: t('lead.website'), render: function (p) { return h('div', null, p.website_name, h('small', { class: 'muted block ltr' }, p.website_domain)); } } : null,
        { label: t('page.name'), sort: 'name', render: function (p) { return h('b', null, p.name); } },
        { label: t('page.url'), render: function (p) { var u = NC.safeHref(p.url); return u ? h('a', { class: 'ext-link ltr', href: u, target: '_blank', rel: 'noopener noreferrer' }, u.replace(/^https?:\/\/(www\.)?/, '').replace(/\/$/, ''), icon('external', 'i-sm')) : '—'; } },
        { label: t('kpi.views'), sort: 'views', render: function (p) { return NC.int(p.views); } },
        { label: t('kpi.leads'), sort: 'leads', render: function (p) { return NC.int(p.leads); } },
        { label: t('kpi.conversion'), sort: 'conversion', render: function (p) { return NC.pct(p.conversion); } },
        { label: t('page.status'), render: function (p) { return statusBadge(p.status); } },
        { label: t('page.created'), sort: 'created', render: function (p) { return NC.date(p.created_at); } },
        { label: t('common.actions'), cls: 'c-actions', render: function (p) {
          return h('div', { class: 'row-actions' },
            h('a', { class: 'btn btn-soft btn-sm', href: NC.url((admin ? '/admin/analytics' : '/analytics') + '?landing_page_id=' + p.id) }, t('page.analytics')),
            admin ? h('button', { type: 'button', class: 'icon-btn', 'aria-label': t('common.edit') + ' ' + p.name, title: t('common.edit'), onclick: function () { openForm(p); } }, icon('edit')) : null,
            admin ? NC.moreBtn(t('common.actions'), [
              { label: t('page.open'), icon: 'external', href: NC.safeHref(p.url), external: true },
              p.status === 'active' ? { label: t('page.disconnect'), icon: 'pause', onClick: function () { setStatus(p, 'paused'); } } : { label: t('page.connect'), icon: 'play', onClick: function () { setStatus(p, 'active'); } },
              { label: t('page.duplicate'), icon: 'copy', onClick: function () { NC.post('/landing-pages/' + p.id + '/duplicate').then(function (x) { NC.toast(x.message); load(); }, function (e) { NC.toast(e.message, 'err'); }); } },
              { label: t('page.sync'), icon: 'refresh', onClick: function () { NC.toast(t('page.sync_soon')); } },
              { sep: true },
              { label: t('common.delete'), icon: 'trash', danger: true, onClick: function () { del(p); } }
            ]) : h('a', { class: 'icon-btn', href: NC.safeHref(p.url), target: '_blank', rel: 'noopener noreferrer', 'aria-label': t('page.open'), title: t('page.open') }, icon('external')));
        } }
      ].filter(Boolean);
      listBox.appendChild(NC.table(cols, r.items, { sort: state.sort, dir: state.dir, caption: t('nav.landing_pages'), onSort: function (k) { if (state.sort === k) state.dir = state.dir === 'asc' ? 'desc' : 'asc'; else { state.sort = k; state.dir = 'desc'; } load(); } }));
      pagerBox.appendChild(NC.pager(r.meta, function (pg) { state.page = pg; load(); }));
    }, function (e) { if (e.name !== 'AbortError') NC.clear(listBox).appendChild(NC.errorState(e.message, load)); });
  }
  function setStatus(p, s) { NC.post('/landing-pages/' + p.id + '/status', { status: s }).then(function () { NC.toast(t('page.updated')); load(); }, function (e) { NC.toast(e.message, 'err'); }); }
  function del(p) { NC.confirm({ title: t('page.delete'), message: t('page.delete_confirm', { name: p.name }), note: t('page.delete_note'), danger: true, confirmText: t('common.delete') }).then(function (ok) { if (ok) NC.del('/landing-pages/' + p.id).then(function (r) { NC.toast(r.message); load(); }, function (e) { NC.toast(e.message, 'err'); }); }); }

  function openForm(p) {
    var edit = !!p;
    var f = {
      website_id: NC.select('lp-site', 'website_id', [['', '—']].concat(sites.map(function (w) { return [w.id, w.name]; })), p ? p.website_id : ''),
      name: NC.input('lp-name', 'name', { value: p ? p.name : '', attrs: { maxlength: 190 } }),
      url: NC.input('lp-url', 'url', { value: p ? p.url : '', attrs: { type: 'url', dir: 'ltr', placeholder: 'https://client-site.co.il/landing' } }),
      status: NC.select('lp-status', 'status', [['active', t('page_status.active')], ['paused', t('page_status.paused')], ['archived', t('page_status.archived')]], p ? p.status : 'active')
    };
    var msg = h('p', { class: 'err form-err', hidden: true, role: 'alert' });
    var save = h('button', { type: 'submit', form: 'lp-form', class: 'btn btn-primary' }, edit ? t('common.save_changes') : t('page.add'));
    var form = h('form', { id: 'lp-form', class: 'form', novalidate: true },
      NC.field(t('lead.website'), f.website_id, { req: true, hint: t('page.website_hint') }), NC.field(t('page.name'), f.name, { req: true }), NC.field(t('page.url'), f.url, { req: true, hint: t('page.url_hint') }), NC.field(t('page.status'), f.status), msg);
    var d = NC.modal({ title: edit ? t('page.edit') : t('page.add'), body: form, footer: [save, h('button', { type: 'button', class: 'btn btn-ghost', onclick: function () { d.close(); } }, t('common.cancel'))] });
    form.addEventListener('submit', function (e) {
      e.preventDefault(); NC.setFieldErrors(form, null); msg.hidden = true; save.disabled = true;
      var body = {}; Object.keys(f).forEach(function (k) { body[k] = f[k].value; });
      (edit ? NC.put('/landing-pages/' + p.id, body) : NC.post('/landing-pages', body)).then(function (r) { d.close(); NC.toast(r.message); load(); }, function (err) { save.disabled = false; if (err.fields) NC.setFieldErrors(form, err.fields); msg.hidden = false; msg.textContent = err.message; });
    });
  }

  var search = h('input', { type: 'search', class: 'input', placeholder: t('page.search_placeholder'), 'aria-label': t('common.search'), autocomplete: 'off' });
  search.addEventListener('input', NC.debounce(function () { state.search = search.value.trim(); state.page = 1; load(); }, 280));
  var st = h('select', { class: 'input', 'aria-label': t('page.status') }, [['', t('page.status') + ': ' + t('common.all')], ['active', t('page_status.active')], ['paused', t('page_status.paused')], ['archived', t('page_status.archived')]].map(function (o) { return h('option', { value: o[0] }, o[1]); }));
  st.addEventListener('change', function () { state.status = st.value; state.page = 1; load(); });

  NC.clear(view).append(
    h('div', { class: 'page-head' }, h('div', null, h('h1', null, t(admin ? 'nav.landing_pages' : 'nav.landing_page')), h('p', { class: 'sub' }, t(admin ? 'page.sub_admin' : 'page.sub_client'))),
      admin ? h('div', { class: 'head-actions' }, h('button', { type: 'button', class: 'btn btn-primary', onclick: function () { openForm(null); } }, icon('plus'), t('page.add'))) : null),
    NC.card(null, h('div', null, h('div', { class: 'toolbar' }, h('div', { class: 'search-field' }, icon('search'), search), st), listBox, pagerBox), { cls: 'card-flush' }));
  if (admin) NC.get('/filters').then(function (r) { sites = r.websites; });
  load();
});
