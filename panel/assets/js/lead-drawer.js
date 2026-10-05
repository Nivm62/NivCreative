/* Shared lead details drawer + helpers (leads page and dashboards). */
(function () {
  'use strict';
  var NC = window.NC, h = NC.h, t = NC.t, icon = NC.icon;

  NC.agingBadge = function (a) {
    if (!a || a === 'ok') return null;
    var cls = a === 'waiting_24h' ? 'b-late' : (a === 'stale' ? 'b-stale' : 'b-warn');
    return h('span', { class: 'badge ' + cls, title: t('aging.' + a + '_hint') }, icon('clock', 'i-sm'), t('aging.' + a));
  };

  function activityText(a) {
    var m = a.meta || {};
    switch (a.type) {
      case 'status_changed': return t('activity.status_changed', { from: t('status.' + m.from), to: t('status.' + m.to) });
      case 'deal_value_set': return t('activity.deal_value_set', { value: NC.money(m.value, true) });
      case 'created': return t('activity.created', { source: t('source.' + (m.source || 'other')) });
      default: return t('activity.' + a.type);
    }
  }

  function infoRow(label, value) {
    if (value === '' || value === null || value === undefined) return null;
    return h('div', { class: 'info-row' }, h('dt', null, label), h('dd', null, value));
  }

  /** Opens the lead drawer. onChange(lead) is called after every successful update. */
  NC.openLead = function (id, onChange) {
    var body = h('div', { class: 'lead-body' }, NC.loading());
    var d = NC.drawer({ title: t('lead.details'), body: body, wide: true });
    var lead = null;

    function render() {
      NC.clear(body);
      var l = lead;
      var status = h('select', { class: 'input', id: 'ld-status', 'aria-label': t('lead.status') }, NC.boot.statuses.map(function (s) { return h('option', { value: s, selected: s === l.status }, t('status.' + s)); }));
      status.addEventListener('change', function () { save({ status: status.value }); });
      var deal = h('input', { class: 'input', id: 'ld-deal', type: 'number', min: '0', step: '0.01', inputmode: 'decimal', value: l.deal_value === null ? '' : l.deal_value, placeholder: '0' });
      var dealBtn = h('button', { type: 'button', class: 'btn btn-soft', onclick: function () { save({ deal_value: deal.value === '' ? null : deal.value }); } }, t('common.save'));
      var noteBox = h('textarea', { class: 'input', id: 'ld-note', rows: '3', maxlength: '5000', placeholder: t('lead.note_placeholder') });
      var noteBtn = h('button', { type: 'button', class: 'btn btn-primary btn-sm', onclick: function () {
        if (!noteBox.value.trim()) { noteBox.focus(); return; }
        noteBtn.disabled = true;
        NC.post('/leads/' + l.id + '/notes', { body: noteBox.value }).then(function (r) { lead = r.lead; NC.toast(r.message); render(); if (onChange) onChange(lead); },
          function (e) { noteBtn.disabled = false; NC.toast(e.message, 'err'); });
      } }, icon('plus'), t('lead.add_note'));
      var contact = function (channel) { return function () { NC.post('/leads/' + l.id + '/contact', { channel: channel }).catch(function () { return null; }); }; };

      body.appendChild(h('div', { class: 'lead-head' },
        NC.avatar(l.name || l.phone, 'lg'),
        h('div', { class: 'lead-title' }, h('h3', null, l.name || t('lead.no_name')), h('div', { class: 'lead-badges' }, NC.statusBadge(l.status), NC.agingBadge(l.aging), l.client_name && NC.isAdmin ? h('span', { class: 'muted' }, l.client_name) : null))));

      body.appendChild(h('div', { class: 'lead-actions' },
        l.whatsapp_url ? h('a', { class: 'btn btn-wa', href: l.whatsapp_url, target: '_blank', rel: 'noopener noreferrer', onclick: contact('whatsapp') }, icon('whatsapp'), t('lead.whatsapp')) : null,
        l.phone ? h('a', { class: 'btn btn-ghost', href: 'tel:' + l.phone.replace(/[^0-9+]/g, ''), onclick: contact('call') }, icon('phone'), t('lead.call')) : null,
        l.email ? h('a', { class: 'btn btn-ghost', href: 'mailto:' + l.email, onclick: contact('email') }, icon('mail'), t('lead.send_email')) : null));

      body.appendChild(h('div', { class: 'grid-2' },
        h('div', { class: 'field' }, h('label', { class: 'label', for: 'ld-status' }, t('lead.status')), status),
        h('div', { class: 'field' }, h('label', { class: 'label', for: 'ld-deal' }, t('lead.deal_value') + ' (₪)'), h('div', { class: 'input-row' }, deal, dealBtn))));

      body.appendChild(h('dl', { class: 'info-grid' },
        infoRow(t('lead.phone'), l.phone ? h('span', { class: 'ltr' }, l.phone) : ''), infoRow(t('lead.email'), l.email ? h('span', { class: 'ltr' }, l.email) : ''),
        infoRow(t('lead.date'), NC.dateTime(l.created_at)), infoRow(t('lead.source'), t('source.' + l.source)),
        infoRow(t('lead.campaign'), l.campaign), infoRow(t('lead.landing_page'), l.landing_name),
        infoRow(t('lead.website'), l.website_name), infoRow(t('lead.medium'), l.utm_medium),
        infoRow(t('lead.content'), l.utm_content), infoRow(t('lead.term'), l.utm_term),
        infoRow(t('lead.referrer'), l.referrer ? h('span', { class: 'ltr wrap' }, l.referrer) : ''), infoRow(t('lead.device'), t('device.' + l.device))));

      if (l.message) body.appendChild(h('div', { class: 'msg-box' }, h('h4', null, t('lead.message')), h('p', null, l.message)));

      body.appendChild(h('div', { class: 'notes' }, h('h4', null, t('lead.notes')), noteBox, h('div', { class: 'notes-actions' }, noteBtn),
        l.notes.length ? h('ul', { class: 'note-list' }, l.notes.map(function (n) { return h('li', null, h('p', null, n.body), h('small', null, (n.author ? n.author + ' · ' : '') + NC.dateTime(n.created_at))); })) : null));

      body.appendChild(h('div', { class: 'timeline-box' }, h('h4', null, t('lead.activity')),
        h('ol', { class: 'timeline' }, l.activity.map(function (a) { return h('li', null, h('i', { class: 'tl-dot' }), h('div', null, h('p', null, activityText(a)), h('small', null, (a.author ? a.author + ' · ' : '') + NC.dateTime(a.created_at)))); }))));
    }

    function save(patch) {
      NC.put('/leads/' + id, patch).then(function (r) { lead = r.lead; NC.toast(r.message); render(); if (onChange) onChange(lead); },
        function (e) { NC.toast(e.fields && Object.keys(e.fields).length ? Object.values(e.fields)[0] : e.message, 'err'); render(); });
    }

    NC.get('/leads/' + id).then(function (r) { lead = r.lead; render(); }, function (e) { NC.clear(body).appendChild(NC.errorState(e.message)); });
    return d;
  };
})();
