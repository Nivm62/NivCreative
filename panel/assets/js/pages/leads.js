/* Leads list (admin: all clients; client: own leads only – enforced server-side). */
NC.page(function (view) {
  'use strict';
  var h = NC.h, t = NC.t, icon = NC.icon, admin = NC.isAdmin;
  var P = NC.params();
  var state = { search: P.search || '', status: P.status || '', source: P.source || '', campaign: '', client_id: P.client_id || '', website_id: '', landing_page_id: '',
    from: P.from || '', to: P.to || '', aging: P.aging || '', sub_status: '', sort: 'created', dir: 'desc', page: 1, per_page: 25 };
  var filterData = { websites: [], landing_pages: [], campaigns: [], clients: [] };
  var listBox = h('div', { class: 'list-box' }), pagerBox = h('div'), advanced = h('div', { class: 'filters-adv', hidden: true });
  var ctl = null, advOpen = false;

  function activeCount() { return ['status', 'source', 'campaign', 'client_id', 'website_id', 'landing_page_id', 'from', 'to', 'aging', 'sub_status'].filter(function (k) { return state[k] !== ''; }).length; }

  function sel(key, options, label) {
    var s = h('select', { class: 'input', id: 'f-' + key, 'aria-label': label }, options.map(function (o) { return h('option', { value: o[0], selected: String(o[0]) === String(state[key]) }, o[1]); }));
    s.addEventListener('change', function () { state[key] = s.value; if (key === 'client_id') { reloadFilters(); } state.page = 1; load(); });
    return h('label', { class: 'field' }, h('span', { class: 'label' }, label), s);
  }
  function dateField(key, label) {
    var i = h('input', { type: 'date', class: 'input', value: state[key], 'aria-label': label });
    i.addEventListener('change', function () { state[key] = i.value; state.page = 1; load(); });
    return h('label', { class: 'field' }, h('span', { class: 'label' }, label), i);
  }

  function buildAdvanced() {
    NC.clear(advanced);
    var sites = filterData.websites.filter(function (w) { return !state.client_id || String(w.client_id) === String(state.client_id); });
    var pages = filterData.landing_pages.filter(function (p) { return (!state.website_id || String(p.website_id) === String(state.website_id)) && (!state.client_id || String(p.client_id) === String(state.client_id)); });
    var agingOpts = [['', t('common.all')], ['waiting_2h', t('aging.waiting_2h')], ['waiting_24h', t('aging.waiting_24h')], ['stale', t('aging.stale')]];
    advanced.appendChild(h('div', { class: 'filter-grid' },
      admin ? sel('client_id', [['', t('common.all')]].concat(filterData.clients.map(function (c) { return [c.id, c.name]; })), t('filters.client')) : null,
      sel('website_id', [['', t('common.all')]].concat(sites.map(function (w) { return [w.id, w.name]; })), t('lead.website')),
      sel('landing_page_id', [['', t('common.all')]].concat(pages.map(function (p) { return [p.id, p.name]; })), t('lead.landing_page')),
      sel('source', NC.sourceOptions(), t('lead.source')),
      sel('campaign', [['', t('common.all')]].concat(filterData.campaigns.map(function (c) { return [c, c]; })), t('lead.campaign')),
      sel('aging', agingOpts, t('filters.aging')),
      admin ? sel('sub_status', [['', t('common.all')], ['active', t('account_status.active')], ['expiring', t('account_status.expiring')], ['expired', t('account_status.expired')], ['disabled', t('account_status.disabled')]], t('filters.subscription')) : null,
      dateField('from', t('range.from')), dateField('to', t('range.to'))));
  }

  function reloadFilters() {
    NC.get('/filters', { client_id: state.client_id }).then(function (r) { filterData = r; buildAdvanced(); });
  }

  function clearAll() {
    ['search', 'status', 'source', 'campaign', 'client_id', 'website_id', 'landing_page_id', 'from', 'to', 'aging', 'sub_status'].forEach(function (k) { state[k] = ''; });
    state.page = 1; searchInput.value = ''; statusSel.value = ''; reloadFilters(); updateBtn(); load();
  }

  var searchInput = h('input', { type: 'search', class: 'input', id: 'lead-search', value: state.search, placeholder: t('lead.search_placeholder'), 'aria-label': t('common.search'), autocomplete: 'off' });
  searchInput.addEventListener('input', NC.debounce(function () { state.search = searchInput.value.trim(); state.page = 1; load(); }, 280));
  var statusSel = h('select', { class: 'input', 'aria-label': t('lead.status') }, NC.statusOptions().map(function (o) { return h('option', { value: o[0], selected: o[0] === state.status }, o[1]); }));
  statusSel.addEventListener('change', function () { state.status = statusSel.value; state.page = 1; load(); });
  var advBtn = h('button', { type: 'button', class: 'btn btn-ghost', 'aria-expanded': 'false', 'aria-controls': 'filters-adv', onclick: function () { advOpen = !advOpen; advanced.hidden = !advOpen; advBtn.setAttribute('aria-expanded', String(advOpen)); } });
  advanced.id = 'filters-adv';
  function updateBtn() { NC.clear(advBtn).append(icon('filter'), t('common.filters'), activeCount() ? h('em', { class: 'count-pill' }, activeCount()) : ''); }

  function params() { var p = Object.assign({}, state); return p; }

  function load() {
    updateBtn();
    if (ctl) ctl.abort(); ctl = window.AbortController ? new AbortController() : null;
    NC.clear(listBox).appendChild(NC.skeleton(6));
    NC.get('/leads', params(), { signal: ctl ? ctl.signal : undefined }).then(function (r) {
      NC.clear(listBox); NC.clear(pagerBox);
      if (!r.items.length) {
        listBox.appendChild(activeCount() || state.search ? NC.empty('search', t('lead.none_found'), t('lead.none_found_hint'), h('button', { type: 'button', class: 'btn btn-ghost', onclick: clearAll }, t('common.clear_filters')))
          : NC.empty('target', t('lead.empty_title'), t(admin ? 'lead.empty_admin' : 'lead.empty_client')));
        return;
      }
      var cols = [
        { label: t('lead.name'), sort: 'name', cls: 'c-name', render: function (l) { return h('div', { class: 'cell-person' }, NC.avatar(l.name || l.phone), h('div', null, h('b', null, l.name || t('lead.no_name')), admin && l.client_name ? h('small', { class: 'muted block' }, l.client_name) : null, NC.agingBadge(l.aging))); } },
        { label: t('lead.phone') + ' / ' + t('lead.email'), render: function (l) { return h('div', null, l.phone ? h('span', { class: 'ltr nowrap' }, l.phone) : '—', l.email ? h('small', { class: 'muted block ltr ellip' }, l.email) : null); } },
        { label: t('lead.date'), sort: 'created', render: function (l) { return h('div', null, h('span', { class: 'nowrap' }, NC.date(l.created_at)), h('small', { class: 'muted block' }, NC.ago(l.created_at))); } },
        { label: t('lead.source') + ' / ' + t('lead.campaign'), render: function (l) { return h('div', null, NC.sourceTag(l.source), l.campaign ? h('small', { class: 'muted block ellip' }, l.campaign) : null); } },
        { label: t('lead.landing_page'), render: function (l) { return l.landing_name || '—'; } },
        { label: t('lead.status'), sort: 'status', render: function (l) { return NC.statusBadge(l.status); } },
        { label: t('lead.deal_value'), sort: 'value', render: function (l) { return l.deal_value === null ? '—' : NC.money(l.deal_value, true); } },
        { label: t('common.actions'), cls: 'c-actions', render: function (l) {
          return h('div', { class: 'row-actions' }, NC.waLink(l.whatsapp_url, l.name, function () { NC.post('/leads/' + l.id + '/contact', { channel: 'whatsapp' }).catch(function () { return null; }); }),
            l.phone ? h('a', { class: 'icon-btn', href: 'tel:' + l.phone.replace(/[^0-9+]/g, ''), 'aria-label': t('lead.call') + ' ' + (l.name || ''), title: t('lead.call') }, icon('phone')) : null,
            h('button', { type: 'button', class: 'icon-btn', 'aria-label': t('lead.open'), title: t('lead.open'), onclick: function () { open(l.id); } }, icon('eye')),
            NC.moreBtn(t('common.actions'), function () {
              return NC.boot.statuses.filter(function (s) { return s !== l.status; }).map(function (s) {
                return { label: t('lead.mark_as', { status: t('status.' + s) }), icon: s === 'closed' ? 'check' : 'chev-right', onClick: function () { NC.put('/leads/' + l.id, { status: s }).then(function (x) { NC.toast(x.message); load(); }, function (e) { NC.toast(e.message, 'err'); }); } };
              });
            }));
        } }
      ];
      listBox.appendChild(NC.table(cols, r.items, { sort: state.sort, dir: state.dir, caption: t('nav.leads'),
        onSort: function (k) { if (state.sort === k) state.dir = state.dir === 'asc' ? 'desc' : 'asc'; else { state.sort = k; state.dir = 'desc'; } load(); },
        onRow: function (l) { open(l.id); }, rowClass: function (l) { return l.aging === 'waiting_24h' ? 'row-late' : (l.aging === 'waiting_2h' ? 'row-warn' : ''); } }));
      pagerBox.appendChild(NC.pager(r.meta, function (pg) { state.page = pg; load(); window.scrollTo({ top: 0, behavior: 'smooth' }); }));
    }, function (e) { if (e.name === 'AbortError') return; NC.clear(listBox).appendChild(NC.errorState(e.message, load)); });
  }

  function open(id) { NC.openLead(id, function () { load(); }); }

  NC.clear(view).append(
    h('div', { class: 'page-head' }, h('div', null, h('h1', null, t('nav.leads')), h('p', { class: 'sub' }, t(admin ? 'lead.sub_admin' : 'lead.sub_client'))),
      h('div', { class: 'head-actions' }, h('button', { type: 'button', class: 'btn btn-ghost', onclick: function () { NC.download('/leads/export', state); } }, icon('download'), t('lead.export')))),
    NC.card(null, h('div', null,
      h('div', { class: 'toolbar' }, h('div', { class: 'search-field' }, icon('search'), searchInput), statusSel, advBtn,
        h('button', { type: 'button', class: 'btn btn-text', onclick: clearAll }, t('common.clear_filters'))),
      advanced, listBox, pagerBox), { cls: 'card-flush' }));

  reloadFilters();
  load();
  if (P.open && /^\d+$/.test(P.open)) open(+P.open);
});
