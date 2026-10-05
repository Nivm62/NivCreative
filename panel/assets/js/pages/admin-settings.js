/* Admin: system settings + integration info. */
NC.page(function (view) {
  'use strict';
  var h = NC.h, t = NC.t, icon = NC.icon;
  var body = h('div', { class: 'dash' });
  function load() {
    NC.clear(body).appendChild(NC.skeleton(5));
    NC.get('/settings').then(function (r) {
      NC.clear(body);
      var s = r.settings, sys = r.system;
      var f = {
        company_name: NC.input('st-co', 'company_name', { value: s.company_name, attrs: { maxlength: 80 } }),
        support_email: NC.input('st-mail', 'support_email', { value: s.support_email || '', attrs: { type: 'email', dir: 'ltr' } }),
        support_phone: NC.input('st-phone', 'support_phone', { value: s.support_phone || '', attrs: { type: 'tel', dir: 'ltr' } }),
        support_whatsapp: NC.input('st-wa', 'support_whatsapp', { value: s.support_whatsapp || '', attrs: { type: 'tel', dir: 'ltr', placeholder: '050-1234567' } })
      };
      var msg = h('p', { class: 'err form-err', hidden: true, role: 'alert' });
      var save = h('button', { type: 'submit', class: 'btn btn-primary' }, t('common.save_changes'));
      var form = h('form', { class: 'form', novalidate: true }, NC.field(t('settings.company'), f.company_name, { req: true }),
        h('div', { class: 'grid-2' }, NC.field(t('settings.support_email'), f.support_email), NC.field(t('settings.support_phone'), f.support_phone)),
        NC.field(t('settings.support_whatsapp'), f.support_whatsapp, { hint: t('settings.support_wa_hint') }), msg, h('div', null, save));
      form.addEventListener('submit', function (e) {
        e.preventDefault(); NC.setFieldErrors(form, null); save.disabled = true;
        var b = {}; Object.keys(f).forEach(function (k) { b[k] = f[k].value; });
        NC.put('/settings', b).then(function (x) { save.disabled = false; NC.toast(x.message); }, function (err) { save.disabled = false; if (err.fields) NC.setFieldErrors(form, err.fields); msg.hidden = false; msg.textContent = err.message; });
      });
      function row(label, value, copy) { return h('div', { class: 'info-row' }, h('dt', null, label), h('dd', null, h('span', { class: 'ltr wrap' }, value), copy ? h('button', { type: 'button', class: 'icon-btn icon-btn-sm', 'aria-label': t('common.copy'), onclick: function () { NC.copy(value); } }, icon('copy')) : null)); }
      body.append(
        NC.card(t('settings.general'), form, { cls: 'card-pad' }),
        NC.card(t('settings.integration'), h('div', null, h('p', { class: 'muted' }, t('settings.integration_text')),
          h('dl', { class: 'info-grid info-grid-1' }, row(t('settings.api_leads'), sys.api_leads, true), row(t('settings.api_track'), sys.api_track, true), row(t('settings.api_ping'), sys.api_ping, true), row(t('settings.tracker_script'), sys.tracker, true)),
          h('p', { class: 'muted' }, t('settings.integration_auth'))), { cls: 'card-pad' }),
        NC.card(t('settings.system'), h('dl', { class: 'info-grid' }, row(t('settings.version'), sys.version), row(t('settings.timezone'), sys.timezone),
          row(t('settings.last_maintenance'), sys.last_maintenance ? NC.dateTime(new Date(sys.last_maintenance * 1000).toISOString().replace('T', ' ').slice(0, 19)) : '—'),
          h('div', { class: 'info-row info-wide' }, h('dt', null, t('settings.cron')), h('dd', null, h('code', { class: 'ltr wrap' }, '*/10 * * * * php /path/to/panel/bin/cron.php')))), { cls: 'card-pad' }));
    }, function (e) { NC.clear(body).appendChild(NC.errorState(e.message, load)); });
  }
  NC.clear(view).append(h('div', { class: 'page-head' }, h('div', null, h('h1', null, t('nav.settings')), h('p', { class: 'sub' }, t('settings.sub')))), body);
  load();
});
