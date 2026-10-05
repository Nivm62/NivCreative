/* Admin: subscriptions & payments overview. No payment processing; rows can later be created by a gateway integration. */
NC.page(function (view) {
  'use strict';
  var h = NC.h, t = NC.t, icon = NC.icon;
  var state = { payment_status: '', client_id: '', page: 1, per_page: 25 };
  var summaryBox = h('div', { class: 'kpi-grid kpi-4' }), listBox = h('div'), pagerBox = h('div'), clients = [];
  var payKind = { paid: 'pay-paid', pending: 'pay-pending', overdue: 'pay-overdue', free: 'pay-free' };

  function countdown(s) {
    if (!s.is_current) return h('small', { class: 'muted block' }, t('billing.past_period'));
    var txt = s.days_left < 0 ? t('client.expired_days', { days: Math.abs(s.days_left) }) : (s.days_left === 0 ? t('client.expires_today') : (s.days_left <= 30 ? t('client.expires_in', { days: s.days_left }) : t('client.days_left', { days: s.days_left })));
    return h('small', { class: 'block ' + (s.days_left < 0 ? 'txt-red' : (s.days_left <= 30 ? 'txt-amber' : 'muted')) }, txt);
  }

  function load() {
    NC.clear(listBox).appendChild(NC.skeleton(5));
    NC.get('/billing', state).then(function (r) {
      NC.clear(summaryBox).append(
        NC.kpi({ icon: 'shekel', tone: 'green', label: t('billing.revenue_month'), value: NC.money(r.summary.revenue_month) }),
        NC.kpi({ icon: 'card', tone: 'violet', label: t('billing.total_paid'), value: NC.money(r.summary.total_paid) }),
        NC.kpi({ icon: 'alert', tone: 'amber', label: t('billing.pending'), value: NC.int(r.summary.pending) }),
        NC.kpi({ icon: 'clock', tone: 'red', label: t('kpi.expiring_soon'), value: NC.int(r.summary.expiring), note: t('kpi.within_30') }));
      NC.clear(listBox); NC.clear(pagerBox);
      if (!r.items.length) { listBox.appendChild(NC.empty('card', t('billing.empty_title'), t('billing.empty_text'))); return; }
      var cols = [
        { label: t('nav.clients'), render: function (s) { return h('div', { class: 'cell-person' }, NC.avatar(s.client_name), h('div', null, h('a', { class: 'link-strong', href: NC.url('/admin/clients/' + s.client_id) }, s.client_name), h('small', { class: 'muted block' }, s.contact_name))); } },
        { label: t('client.plan'), render: function (s) { return s.plan ? (t('plan.' + s.plan) === 'plan.' + s.plan ? s.plan : t('plan.' + s.plan)) : '—'; } },
        { label: t('billing.amount'), render: function (s) { return NC.money(s.amount, true); } },
        { label: t('client.start_date'), render: function (s) { return NC.date(s.start_date); } },
        { label: t('client.end_date'), render: function (s) { return h('div', null, NC.date(s.end_date), countdown(s)); } },
        { label: t('client.payment_status'), render: function (s) { return NC.badge(payKind[s.payment_status], t('payment.' + s.payment_status)); } },
        { label: t('common.actions'), cls: 'c-actions', render: function (s) {
          return h('div', { class: 'row-actions' }, NC.moreBtn(t('common.actions'), [
            s.payment_status !== 'paid' ? { label: t('billing.mark_paid'), icon: 'check', onClick: function () { setStatus(s, 'paid'); } } : null,
            s.payment_status !== 'pending' ? { label: t('billing.mark_pending'), icon: 'clock', onClick: function () { setStatus(s, 'pending'); } } : null,
            s.payment_status !== 'overdue' ? { label: t('billing.mark_overdue'), icon: 'alert', onClick: function () { setStatus(s, 'overdue'); } } : null,
            { label: t('common.edit'), icon: 'edit', onClick: function () { openEdit(s); } }]));
        } }
      ];
      listBox.appendChild(NC.table(cols, r.items, { caption: t('nav.billing') }));
      pagerBox.appendChild(NC.pager(r.meta, function (pg) { state.page = pg; load(); }));
    }, function (e) { NC.clear(listBox).appendChild(NC.errorState(e.message, load)); });
  }
  function setStatus(s, st) { NC.put('/billing/' + s.id, { payment_status: st }).then(function (r) { NC.toast(r.message); load(); }, function (e) { NC.toast(e.message, 'err'); }); }
  function openEdit(s) {
    var f = {
      plan: NC.select('bl-plan', 'plan', [['', '—']].concat(NC.boot.plans.map(function (p) { return [p, t('plan.' + p)]; })), s.plan),
      amount: NC.input('bl-amount', 'amount', { value: s.amount, attrs: { type: 'number', min: '0', step: '0.01', inputmode: 'decimal' } }),
      start_date: NC.input('bl-start', 'start_date', { value: s.start_date, attrs: { type: 'date' } }),
      end_date: NC.input('bl-end', 'end_date', { value: s.end_date, attrs: { type: 'date' } }),
      payment_status: NC.select('bl-pay', 'payment_status', NC.boot.payment.map(function (p) { return [p, t('payment.' + p)]; }), s.payment_status),
      note: NC.input('bl-note', 'note', { value: s.note, attrs: { maxlength: 255 } })
    };
    var msg = h('p', { class: 'err form-err', hidden: true, role: 'alert' });
    var save = h('button', { type: 'submit', form: 'bl-form', class: 'btn btn-primary' }, t('common.save_changes'));
    var form = h('form', { id: 'bl-form', class: 'form', novalidate: true }, h('p', null, s.client_name),
      h('div', { class: 'grid-2' }, NC.field(t('client.plan'), f.plan), NC.field(t('billing.amount') + ' (₪)', f.amount, { req: true })),
      h('div', { class: 'grid-2' }, NC.field(t('client.start_date'), f.start_date, { req: true }), NC.field(t('client.end_date'), f.end_date, { req: true })),
      h('div', { class: 'grid-2' }, NC.field(t('client.payment_status'), f.payment_status), NC.field(t('client.note'), f.note)), msg);
    var d = NC.modal({ title: t('billing.edit'), body: form, footer: [save, h('button', { type: 'button', class: 'btn btn-ghost', onclick: function () { d.close(); } }, t('common.cancel'))] });
    form.addEventListener('submit', function (e) {
      e.preventDefault(); NC.setFieldErrors(form, null); save.disabled = true;
      var body = {}; Object.keys(f).forEach(function (k) { body[k] = f[k].value; });
      NC.put('/billing/' + s.id, body).then(function (r) { d.close(); NC.toast(r.message); load(); }, function (err) { save.disabled = false; if (err.fields) NC.setFieldErrors(form, err.fields); msg.hidden = false; msg.textContent = err.message; });
    });
  }

  var ps = h('select', { class: 'input', 'aria-label': t('client.payment_status') }, [['', t('client.payment_status') + ': ' + t('common.all')]].concat(NC.boot.payment.map(function (p) { return [p, t('payment.' + p)]; })).map(function (o) { return h('option', { value: o[0] }, o[1]); }));
  ps.addEventListener('change', function () { state.payment_status = ps.value; state.page = 1; load(); });
  var cs = h('select', { class: 'input', 'aria-label': t('filters.client') }, h('option', { value: '' }, t('filters.all_clients')));
  cs.addEventListener('change', function () { state.client_id = cs.value; state.page = 1; load(); });
  NC.get('/clients/options').then(function (r) { r.items.forEach(function (c) { cs.appendChild(h('option', { value: c.id }, c.name)); }); });

  NC.clear(view).append(h('div', { class: 'page-head' }, h('div', null, h('h1', null, t('nav.billing')), h('p', { class: 'sub' }, t('billing.sub')))),
    summaryBox, NC.card(null, h('div', null, h('div', { class: 'toolbar' }, cs, ps), listBox, pagerBox), { cls: 'card-flush' }),
    h('p', { class: 'callout' }, icon('alert'), h('span', null, t('billing.no_payments_note'))));
  load();
});
