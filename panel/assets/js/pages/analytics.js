/* Analytics (admin: any client; client: own data only – enforced server-side). */
NC.page(function (view) {
  'use strict';
  var h = NC.h, t = NC.t, icon = NC.icon, admin = NC.isAdmin;
  var P = NC.params();
  var range = { preset: P.preset || '30d', from: P.from || '', to: P.to || '' };
  var f = { client_id: P.client_id || '', website_id: '', landing_page_id: '', group: '' };
  var data = { clients: [], websites: [], landing_pages: [] };
  var body = h('div', { class: 'dash' }), filtersBox = h('div', { class: 'toolbar toolbar-wrap' });

  var picker = NC.rangePicker(range.from ? range : Object.assign(range, NC.rangeFromPreset(range.preset)), function () { load(); });

  function sel(key, options, label) {
    var s = h('select', { class: 'input', 'aria-label': label }, options.map(function (o) { return h('option', { value: o[0], selected: String(o[0]) === String(f[key]) }, o[1]); }));
    s.addEventListener('change', function () { f[key] = s.value; if (key === 'client_id') { f.website_id = ''; f.landing_page_id = ''; refreshFilters(); } if (key === 'website_id') { f.landing_page_id = ''; drawFilters(); } load(); });
    return s;
  }
  function drawFilters() {
    NC.clear(filtersBox);
    var sites = data.websites.filter(function (w) { return !f.client_id || String(w.client_id) === String(f.client_id); });
    var pages = data.landing_pages.filter(function (p) { return (!f.website_id || String(p.website_id) === String(f.website_id)) && (!f.client_id || String(p.client_id) === String(f.client_id)); });
    filtersBox.append(
      admin ? sel('client_id', [['', t('filters.all_clients')]].concat((data.clients || []).map(function (c) { return [c.id, c.name]; })), t('filters.client')) : null,
      sel('website_id', [['', t('filters.all_websites')]].concat(sites.map(function (w) { return [w.id, w.name]; })), t('lead.website')),
      sel('landing_page_id', [['', t('filters.all_pages')]].concat(pages.map(function (p) { return [p.id, p.name]; })), t('lead.landing_page')),
      sel('group', [['', t('analytics.group_auto')], ['day', t('analytics.by_day')], ['week', t('analytics.by_week')], ['month', t('analytics.by_month')]], t('analytics.group')),
      picker);
  }
  function refreshFilters() { NC.get('/filters', { client_id: f.client_id }).then(function (r) { data = r; drawFilters(); }); }

  function labelsOf(series, group) {
    var loc = NC.boot.locale === 'he' ? 'he-IL' : 'en-GB';
    return series.map(function (s) { var d = NC.parseDate(s.date); return group === 'month' ? d.toLocaleDateString(loc, { month: 'short', year: '2-digit' }) : d.toLocaleDateString(loc, { day: 'numeric', month: 'short' }); });
  }
  function chartCard(title, build, cls) { var host = h('div', { class: 'chart-host' }); var c = NC.card(title, host, { cls: cls || '' }); setTimeout(function () { build(host); }, 0); return c; }
  function delta(cur, prev) { if (cur === null || prev === null || !prev) return null; return Math.round((cur - prev) * 1000 / prev) / 10; }

  function render(d) {
    NC.clear(body);
    var T = d.totals, Pv = d.previous, g = d.range.group;
    body.appendChild(h('div', { class: 'kpi-grid kpi-6' },
      NC.kpi({ icon: 'eye', tone: 'blue', label: t('kpi.views'), value: NC.int(T.views), delta: delta(T.views, Pv.views), note: t('analytics.views_note') }),
      NC.kpi({ icon: 'users', tone: 'violet', label: t('kpi.visitors'), value: NC.int(T.visitors), delta: delta(T.visitors, Pv.visitors), note: t('analytics.visitors_note') }),
      NC.kpi({ icon: 'target', tone: 'green', label: t('kpi.leads'), value: NC.int(T.leads), delta: delta(T.leads, Pv.leads) }),
      NC.kpi({ icon: 'trend', tone: 'amber', label: t('kpi.conversion'), value: NC.pct(T.conversion), delta: delta(T.conversion, Pv.conversion), note: t('analytics.conv_note') }),
      NC.kpi({ icon: 'check', tone: 'green', label: t('kpi.closed'), value: NC.int(T.closed), delta: delta(T.closed, Pv.closed) }),
      NC.kpi({ icon: 'shekel', tone: 'amber', label: t('kpi.revenue'), value: NC.money(T.revenue), delta: delta(T.revenue, Pv.revenue) })));
    var lab = labelsOf(d.series, g), full = d.series.map(function (s) { return NC.date(s.date); });
    var items = d.sources.map(function (s) { return { label: t('source.' + s.source), value: s.leads, color: NC.sourceColors[s.source] }; });
    var total = d.sources.reduce(function (a, s) { return a + s.leads; }, 0);

    body.appendChild(h('div', { class: 'grid-chart' },
      chartCard(t('chart.views_vs_leads'), function (host) { NCCharts.bars(host, { labels: lab, fullLabels: full, series: [{ name: t('kpi.views'), data: d.series.map(function (s) { return s.views; }), color: '#c7bdfc' }, { name: t('kpi.leads'), data: d.series.map(function (s) { return s.leads; }), color: '#6d4cf5' }], emptyText: t('chart.no_data') }); }, 'span-2'),
      chartCard(t('chart.funnel'), function (host) { NCCharts.funnel(host, { steps: d.funnel.map(function (x) { return { label: t('funnel.' + x.key), value: x.value }; }), emptyText: t('chart.no_data') }); })));
    body.appendChild(h('div', { class: 'grid-chart' },
      chartCard(t('chart.leads_over_time'), function (host) { NCCharts.line(host, { labels: lab, fullLabels: full, series: [{ name: t('kpi.leads'), data: d.series.map(function (s) { return s.leads; }) }], emptyText: t('chart.no_data') }); }, 'span-2'),
      chartCard(t('chart.leads_by_source'), function (host) { NCCharts.donut(host, { items: items, center: { value: NC.int(total), label: t('chart.total_leads') }, emptyText: t('chart.no_data') }); })));
    body.appendChild(h('div', { class: 'grid-chart' },
      chartCard(t('chart.conversion'), function (host) { NCCharts.line(host, { labels: lab, fullLabels: full, series: [{ name: t('kpi.conversion'), data: d.series.map(function (s) { return s.conversion || 0; }), color: '#16a37f' }], format: function (v) { return NC.pct(v); }, emptyText: t('chart.no_data') }); }),
      chartCard(t('chart.closed_deals'), function (host) { NCCharts.bars(host, { labels: lab, fullLabels: full, series: [{ name: t('kpi.closed'), data: d.series.map(function (s) { return s.closed; }), color: '#16a37f' }], emptyText: t('chart.no_data') }); }),
      chartCard(t('chart.revenue'), function (host) { NCCharts.bars(host, { labels: lab, fullLabels: full, series: [{ name: t('kpi.revenue'), data: d.series.map(function (s) { return s.revenue; }), color: '#f5a524' }], format: function (v) { return NC.money(v); }, emptyText: t('chart.no_data') }); })));

    // Source table
    var srcRows = d.sources.filter(function (s) { return s.leads || s.views; });
    var srcCols = [
      { label: t('lead.source'), render: function (s) { return NC.sourceTag(s.source); } },
      { label: t('kpi.views'), render: function (s) { return NC.int(s.views); } },
      { label: t('kpi.leads'), render: function (s) { return NC.int(s.leads); } },
      { label: t('kpi.conversion'), render: function (s) { return s.views ? NC.pct(Math.round(s.leads * 1000 / s.views) / 10) : '—'; } }
    ];
    var campCols = [
      { label: t('lead.campaign'), render: function (c) { return h('b', null, c.campaign); } },
      { label: t('kpi.views'), render: function (c) { return NC.int(c.views); } },
      { label: t('kpi.leads'), render: function (c) { return NC.int(c.leads); } },
      { label: t('kpi.conversion'), render: function (c) { return NC.pct(c.conversion); } },
      { label: t('kpi.closed'), render: function (c) { return NC.int(c.closed); } },
      { label: t('kpi.revenue'), render: function (c) { return NC.money(c.revenue); } }
    ];
    body.appendChild(h('div', { class: 'grid-2' },
      NC.card(t('chart.traffic_sources'), srcRows.length ? NC.table(srcCols, srcRows) : h('p', { class: 'muted pad' }, t('chart.no_data')), { cls: 'card-flush' }),
      NC.card(t('chart.campaign_performance'), d.campaigns.length ? NC.table(campCols, d.campaigns) : h('p', { class: 'muted pad' }, t('chart.no_campaigns')), { cls: 'card-flush' })));
    if (T.views === 0) body.appendChild(h('p', { class: 'callout' }, icon('alert'), h('span', null, t('analytics.no_views_hint'))));
  }

  var ctl = null;
  function load() {
    NC.setParams(range.preset === 'custom' ? { from: range.from, to: range.to } : { preset: range.preset === '30d' ? '' : range.preset });
    if (ctl) ctl.abort(); ctl = window.AbortController ? new AbortController() : null;
    NC.clear(body).appendChild(NC.skeleton(8));
    var p = Object.assign({}, f, range.preset === 'custom' ? { from: range.from, to: range.to } : { preset: range.preset });
    NC.get('/analytics', p, { signal: ctl ? ctl.signal : undefined }).then(render, function (e) { if (e.name !== 'AbortError') NC.clear(body).appendChild(NC.errorState(e.message, load)); });
  }

  NC.clear(view).append(h('div', { class: 'page-head' }, h('div', null, h('h1', null, t('nav.analytics')), h('p', { class: 'sub' }, t(admin ? 'analytics.sub_admin' : 'analytics.sub_client')))), NC.card(null, filtersBox, { cls: 'card-pad' }), body);
  refreshFilters(); load();
});
