/* Administrator dashboard. */
NC.page(function (view) {
  'use strict';
  var h = NC.h, t = NC.t, icon = NC.icon;
  var P = NC.params();
  var range = { preset: P.preset || 'month', from: P.from || '', to: P.to || '' };
  var body = h('div', { class: 'dash' });
  var ctl = null;

  function setRangeParams() { NC.setParams(range.preset === 'custom' ? { from: range.from, to: range.to } : { preset: range.preset === 'month' ? '' : range.preset }); }

  var picker = NC.rangePicker(range.from ? range : Object.assign(range, NC.rangeFromPreset(range.preset)), function () { setRangeParams(); load(); });

  NC.clear(view).append(
    h('div', { class: 'page-head' }, h('div', null, h('h1', null, t('dash.welcome_admin', { name: NC.boot.user.name }), ' ', h('span', { 'aria-hidden': 'true' }, '👋')), h('p', { class: 'sub' }, t('dash.sub_admin'))), h('div', { class: 'head-actions' }, picker)),
    body);

  function chartCard(title, build, cls, right) {
    var host = h('div', { class: 'chart-host' });
    var c = NC.card(title, host, { cls: cls || '', right: right });
    setTimeout(function () { build(host); }, 0);
    return c;
  }

  function labels(series, group) {
    var loc = NC.boot.locale === 'he' ? 'he-IL' : 'en-GB';
    return series.map(function (s) {
      var d = NC.parseDate(s.date);
      return group === 'month' ? d.toLocaleDateString(loc, { month: 'short', year: '2-digit' }) : d.toLocaleDateString(loc, { day: 'numeric', month: 'short' });
    });
  }

  function render(d) {
    NC.clear(body);
    var k = d.kpis, g = d.range.group;
    var row1 = h('div', { class: 'kpi-grid kpi-4' },
      NC.kpi({ icon: 'users', tone: 'violet', label: t('kpi.active_clients'), value: NC.int(k.active_clients.value) }),
      NC.kpi({ icon: 'file', tone: 'blue', label: t('kpi.active_pages'), value: NC.int(k.active_pages.value) }),
      NC.kpi({ icon: 'zap', tone: 'amber', label: t('kpi.leads_today'), value: NC.int(k.leads_today.value) }),
      NC.kpi({ icon: 'target', tone: 'green', label: t('kpi.leads_month'), value: NC.int(k.leads_month.value) }));
    var row2 = h('div', { class: 'kpi-grid kpi-5' },
      NC.kpi({ icon: 'chart', tone: 'violet', label: t('kpi.total_leads'), value: NC.int(k.total_leads.value) }),
      NC.kpi({ icon: 'eye', tone: 'blue', label: t('kpi.total_views'), value: NC.int(k.total_views.value), delta: k.total_views.delta }),
      NC.kpi({ icon: 'trend', tone: 'green', label: t('kpi.avg_conversion'), value: NC.pct(k.avg_conversion.value), delta: k.avg_conversion.delta }),
      NC.kpi({ icon: 'shekel', tone: 'amber', label: t('kpi.monthly_revenue'), value: NC.money(k.monthly_revenue.value), delta: k.monthly_revenue.delta }),
      NC.kpi({ icon: 'clock', tone: 'red', label: t('kpi.expiring_soon'), value: NC.int(k.expiring_soon.value), note: t('kpi.within_30') }));
    body.appendChild(row1); body.appendChild(row2);

    if (d.waiting > 0) {
      body.appendChild(h('a', { class: 'banner banner-warn', href: NC.url('/admin/leads?aging=waiting_2h') }, icon('alert'), h('span', null, t('dash.waiting_banner', { count: d.waiting })), icon(NC.boot.dir === 'rtl' ? 'arrow-left' : 'arrow-right')));
    }

    var lab = labels(d.series, g);
    var full = d.series.map(function (s) { return NC.date(s.date); });
    var srcItems = d.sources.map(function (s) { return { label: t('source.' + s.source), value: s.leads, color: NC.sourceColors[s.source] }; });
    var totalLeads = d.sources.reduce(function (a, s) { return a + s.leads; }, 0);

    body.appendChild(h('div', { class: 'grid-chart' },
      chartCard(t('chart.leads_over_time'), function (host) { NCCharts.line(host, { labels: lab, fullLabels: full, series: [{ name: t('kpi.leads'), data: d.series.map(function (s) { return s.leads; }) }], emptyText: t('chart.no_data'), label: t('chart.leads_over_time') }); }, 'span-2'),
      chartCard(t('chart.leads_by_source'), function (host) { NCCharts.donut(host, { items: srcItems, center: { value: NC.int(totalLeads), label: t('chart.total_leads') }, emptyText: t('chart.no_data'), label: t('chart.leads_by_source') }); })));

    body.appendChild(h('div', { class: 'grid-chart' },
      chartCard(t('chart.views_over_time'), function (host) { NCCharts.bars(host, { labels: lab, fullLabels: full, series: [{ name: t('kpi.views'), data: d.series.map(function (s) { return s.views; }), color: '#3b82f6' }, { name: t('kpi.leads'), data: d.series.map(function (s) { return s.leads; }), color: '#6d4cf5' }], emptyText: t('chart.no_data') }); }, 'span-2'),
      chartCard(t('chart.conversion'), function (host) { NCCharts.line(host, { labels: lab, fullLabels: full, series: [{ name: t('kpi.conversion'), data: d.series.map(function (s) { return s.conversion || 0; }), color: '#16a37f' }], format: function (v) { return NC.pct(v); }, emptyText: t('chart.no_data') }); })));

    body.appendChild(h('div', { class: 'grid-chart' },
      chartCard(t('chart.leads_by_campaign'), function (host) {
        if (!d.campaigns.length) { host.appendChild(h('div', { class: 'chart-empty' }, t('chart.no_campaigns'))); return; }
        var max = d.campaigns[0].leads || 1;
        host.appendChild(h('ul', { class: 'hbars' }, d.campaigns.map(function (c) { var bar = h('span', { class: 'hbar-fill' }); bar.style.width = Math.max(4, c.leads / max * 100) + '%'; return h('li', null, h('span', { class: 'hbar-label', title: c.campaign }, c.campaign), h('span', { class: 'hbar-track' }, bar), h('b', null, NC.int(c.leads))); })));
      }),
      chartCard(t('chart.revenue_over_time'), function (host) { NCCharts.bars(host, { labels: d.revenue_series.map(function (s) { return NC.parseDate(s.date).toLocaleDateString(NC.boot.locale === 'he' ? 'he-IL' : 'en-GB', { month: 'short' }); }), fullLabels: d.revenue_series.map(function (s) { return NC.parseDate(s.date).toLocaleDateString(NC.boot.locale === 'he' ? 'he-IL' : 'en-GB', { month: 'long', year: 'numeric' }); }), series: [{ name: t('kpi.revenue'), data: d.revenue_series.map(function (s) { return s.revenue; }), color: '#f5a524' }], format: function (v) { return NC.money(v); }, emptyText: t('chart.no_data') }); }),
      NC.card(t('dash.top_clients'), h('ul', { class: 'top-list' }, d.top_clients.length ? d.top_clients.map(function (c, i) {
        return h('li', null, h('a', { href: NC.url('/admin/clients/' + c.id) }, NC.avatar(c.name), h('span', null, h('b', null, c.name), h('small', null, c.contact)), h('em', null, NC.int(c.leads))));
      }) : [h('li', { class: 'muted' }, t('chart.no_data'))]))));

    // Recent leads + alerts
    var cols = [
      { label: t('lead.name'), cls: 'c-name', render: function (l) { return h('div', { class: 'cell-person' }, NC.avatar(l.name || l.phone), h('div', null, h('b', null, l.name || t('lead.no_name')), h('small', { class: 'muted block' }, l.client_name))); } },
      { label: t('lead.phone'), render: function (l) { return l.phone ? h('span', { class: 'ltr' }, l.phone) : '—'; } },
      { label: t('lead.source'), render: function (l) { return NC.sourceTag(l.source); } },
      { label: t('lead.date'), render: function (l) { return NC.ago(l.created_at); } },
      { label: t('lead.status'), render: function (l) { return NC.statusBadge(l.status); } }
    ];
    var recent = d.recent_leads.length ? NC.table(cols, d.recent_leads, { onRow: function (l) { NC.openLead(l.id, load); } }) : h('p', { class: 'muted pad' }, t('lead.empty_admin'));
    var alerts = h('div', { class: 'alert-list' }, d.notifications.length ? d.notifications.map(function (n) { return NC.notifItem(n); }) : [h('p', { class: 'muted pad' }, t('notif.empty'))]);
    body.appendChild(h('div', { class: 'grid-2-1' },
      NC.card(t('dash.recent_leads'), recent, { right: h('a', { class: 'link', href: NC.url('/admin/leads') }, t('common.view_all')), cls: 'card-flush' }),
      NC.card(t('dash.important_alerts'), alerts, { right: h('a', { class: 'link', href: NC.url('/admin/notifications') }, t('common.view_all')), cls: 'card-flush' })));
  }

  function load() {
    if (ctl) ctl.abort(); ctl = window.AbortController ? new AbortController() : null;
    NC.clear(body).appendChild(NC.skeleton(8));
    NC.get('/dashboard/admin', range.preset === 'custom' ? { from: range.from, to: range.to } : { preset: range.preset }, { signal: ctl ? ctl.signal : undefined })
      .then(render, function (e) { if (e.name !== 'AbortError') NC.clear(body).appendChild(NC.errorState(e.message, load)); });
  }
  load();
});
