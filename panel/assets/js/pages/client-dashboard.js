/* Client dashboard (also used by admins to view one client's dashboard: /admin/clients/{id}). */
NC.page(function (view) {
  'use strict';
  var h = NC.h, t = NC.t, icon = NC.icon, admin = NC.isAdmin;
  var P = NC.params();
  var clientId = admin ? (NC.boot.extra && NC.boot.extra.client_id) : '';
  var range = { preset: P.preset || 'month', from: P.from || '', to: P.to || '' };
  var body = h('div', { class: 'dash' });
  var titleBox = h('div');
  var picker = NC.rangePicker(range.from ? range : Object.assign(range, NC.rangeFromPreset(range.preset)), function () { NC.setParams(range.preset === 'custom' ? { from: range.from, to: range.to } : { preset: range.preset === 'month' ? '' : range.preset }); load(); });
  NC.clear(view).append(h('div', { class: 'page-head' }, titleBox, h('div', { class: 'head-actions' }, picker)), body);

  function labels(series, group) {
    var loc = NC.boot.locale === 'he' ? 'he-IL' : 'en-GB';
    return series.map(function (s) { var d = NC.parseDate(s.date); return group === 'month' ? d.toLocaleDateString(loc, { month: 'short', year: '2-digit' }) : d.toLocaleDateString(loc, { day: 'numeric', month: 'short' }); });
  }
  function chartCard(title, build, cls) { var host = h('div', { class: 'chart-host' }); var c = NC.card(title, host, { cls: cls || '' }); setTimeout(function () { build(host); }, 0); return c; }

  function render(d) {
    NC.clear(body); NC.clear(titleBox);
    if (admin) {
      titleBox.append(h('a', { class: 'back-link', href: NC.url('/admin/clients') }, icon(NC.boot.dir === 'rtl' ? 'arrow-right' : 'arrow-left'), t('nav.clients')),
        h('h1', null, d.client.business), h('p', { class: 'sub' }, t('dash.admin_viewing', { name: d.client.name })));
    } else {
      titleBox.append(h('h1', null, t('dash.welcome_client', { name: d.client.name }), ' ', h('span', { 'aria-hidden': 'true' }, '👋')), h('p', { class: 'sub' }, t('dash.sub_client')));
    }
    var k = d.kpis;
    var sub = d.subscription;
    if (sub && !admin && (sub.state === 'expiring' || sub.state === 'expired')) {
      body.appendChild(h('div', { class: 'banner ' + (sub.state === 'expired' ? 'banner-danger' : 'banner-warn') }, icon('clock'), h('span', null, sub.state === 'expired' ? t('billing.banner_expired', { days: Math.abs(sub.days_left) }) : t('billing.banner_expiring', { days: sub.days_left }))));
    }
    if (k.waiting.value > 0) {
      body.appendChild(h('a', { class: 'banner banner-warn', href: NC.url((admin ? '/admin/leads?client_id=' + clientId + '&' : '/leads?') + 'aging=waiting_2h') }, icon('alert'), h('span', null, t('dash.waiting_banner', { count: k.waiting.value })), icon(NC.boot.dir === 'rtl' ? 'arrow-left' : 'arrow-right')));
    }
    body.appendChild(h('div', { class: 'kpi-grid kpi-4' },
      NC.kpi({ icon: 'target', tone: 'violet', label: t('kpi.leads_period'), value: NC.int(k.leads.value), delta: k.leads.delta }),
      NC.kpi({ icon: 'users', tone: 'blue', label: t('kpi.new_leads'), value: NC.int(k.new_leads.value) }),
      NC.kpi({ icon: 'eye', tone: 'blue', label: t('kpi.views'), value: NC.int(k.views.value), delta: k.views.delta }),
      NC.kpi({ icon: 'trend', tone: 'green', label: t('kpi.conversion'), value: NC.pct(k.conversion.value), delta: k.conversion.delta })));
    body.appendChild(h('div', { class: 'kpi-grid kpi-4' },
      NC.kpi({ icon: 'clock', tone: 'red', label: t('kpi.waiting'), value: NC.int(k.waiting.value) }),
      NC.kpi({ icon: 'check', tone: 'green', label: t('kpi.closed'), value: NC.int(k.closed.value), delta: k.closed.delta }),
      NC.kpi({ icon: 'shekel', tone: 'amber', label: t('kpi.revenue'), value: NC.money(k.revenue.value), delta: k.revenue.delta }),
      NC.kpi({ icon: 'chart', tone: 'violet', label: t('kpi.avg_deal'), value: NC.money(k.avg_deal.value) })));

    var lab = labels(d.series, d.range.group), full = d.series.map(function (s) { return NC.date(s.date); });
    var items = d.sources.map(function (s) { return { label: t('source.' + s.source), value: s.leads, color: NC.sourceColors[s.source] }; });
    var total = d.sources.reduce(function (a, s) { return a + s.leads; }, 0);
    body.appendChild(h('div', { class: 'grid-chart' },
      chartCard(t('chart.leads_over_time'), function (host) { NCCharts.line(host, { labels: lab, fullLabels: full, series: [{ name: t('kpi.leads'), data: d.series.map(function (s) { return s.leads; }) }], emptyText: t('chart.no_data') }); }, 'span-2'),
      chartCard(t('chart.leads_by_source'), function (host) { NCCharts.donut(host, { items: items, center: { value: NC.int(total), label: t('chart.total_leads') }, emptyText: t('chart.no_data') }); })));
    body.appendChild(h('div', { class: 'grid-chart' },
      chartCard(t('chart.views_vs_leads'), function (host) { NCCharts.bars(host, { labels: lab, fullLabels: full, series: [{ name: t('kpi.views'), data: d.series.map(function (s) { return s.views; }), color: '#c7bdfc' }, { name: t('kpi.leads'), data: d.series.map(function (s) { return s.leads; }), color: '#6d4cf5' }], emptyText: t('chart.no_data') }); }, 'span-2'),
      chartCard(t('chart.funnel'), function (host) { NCCharts.funnel(host, { steps: d.funnel.map(function (f) { return { label: t('funnel.' + f.key), value: f.value }; }), emptyText: t('chart.no_data') }); })));

    var cols = [
      { label: t('lead.name'), cls: 'c-name', render: function (l) { return h('div', { class: 'cell-person' }, NC.avatar(l.name || l.phone), h('div', null, h('b', null, l.name || t('lead.no_name')), NC.agingBadge(l.aging))); } },
      { label: t('lead.phone'), render: function (l) { return l.phone ? h('span', { class: 'ltr' }, l.phone) : '—'; } },
      { label: t('lead.source'), render: function (l) { return NC.sourceTag(l.source); } },
      { label: t('lead.date'), render: function (l) { return NC.ago(l.created_at); } },
      { label: t('lead.status'), render: function (l) { return NC.statusBadge(l.status); } },
      { label: t('common.actions'), cls: 'c-actions', render: function (l) { return NC.waLink(l.whatsapp_url, l.name); } }
    ];
    body.appendChild(NC.card(t('dash.recent_leads'), d.recent_leads.length ? NC.table(cols, d.recent_leads, { onRow: function (l) { NC.openLead(l.id, load); } }) : NC.empty('target', t('lead.empty_title'), t('lead.empty_client')),
      { right: h('a', { class: 'link', href: NC.url(admin ? '/admin/leads?client_id=' + clientId : '/leads') }, t('common.view_all')), cls: 'card-flush' }));
  }

  function load() {
    NC.clear(body).appendChild(NC.skeleton(8));
    var p = range.preset === 'custom' ? { from: range.from, to: range.to } : { preset: range.preset };
    if (admin) p.client_id = clientId;
    NC.get('/dashboard/client', p).then(render, function (e) { NC.clear(body).appendChild(NC.errorState(e.message, load)); });
  }
  load();
});
