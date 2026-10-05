/* Admin: connected websites (external WordPress installs) – credentials never leave the server except one-time tokens. */
NC.page(function (view) {
  'use strict';
  var h = NC.h, t = NC.t, icon = NC.icon;
  var listBox = h('div'), clients = [];
  function connMsg(m) {
    var k = 'connection_msg.' + m;
    if (t(k) !== k) return t(k);
    var mm = /^http_(\d+)$/.exec(m);
    return mm ? t('connection_msg.http_other', { code: mm[1] }) : m;
  }
  var connKind = { connected: 'conn-ok', disconnected: 'conn-bad', error: 'conn-err', auth_required: 'conn-warn' };

  function load() {
    NC.clear(listBox).appendChild(NC.skeleton(4));
    NC.get('/websites').then(function (r) {
      NC.clear(listBox);
      if (!r.items.length) { listBox.appendChild(NC.empty('globe', t('website.empty_title'), t('website.empty_text'), h('button', { type: 'button', class: 'btn btn-primary', onclick: function () { openForm(null); } }, icon('plus'), t('website.add')))); return; }
      var cols = [
        { label: t('website.name'), cls: 'c-name', render: function (w) { return h('div', null, h('b', null, w.name), h('small', { class: 'muted block' }, w.client_name)); } },
        { label: t('website.domain'), render: function (w) { return h('div', null, h('a', { class: 'ext-link ltr', href: NC.safeHref(w.url), target: '_blank', rel: 'noopener noreferrer' }, w.domain, icon('external', 'i-sm')), h('small', { class: 'muted block' }, t('website.wordpress') + (w.wp_version ? ' ' + w.wp_version : ''))); } },
        { label: t('website.connection'), render: function (w) { return h('div', null, NC.badge(connKind[w.connection_status] || 'conn-bad', t('connection.' + w.connection_status)), w.connection_message ? h('small', { class: 'muted block' }, connMsg(w.connection_message)) : null); } },
        { label: t('website.last_comm'), render: function (w) { return w.last_seen_at ? h('div', null, NC.ago(w.last_seen_at), h('small', { class: 'muted block' }, NC.dateTime(w.last_seen_at))) : '—'; } },
        { label: t('nav.landing_pages'), render: function (w) { return h('div', null, NC.int(w.pages_count), w.strict_pages ? h('small', { class: 'muted block' }, t('website.strict_badge')) : null); } },
        { label: t('website.leads_received'), render: function (w) { return NC.int(w.leads_count); } },
        { label: t('kpi.views'), render: function (w) { return NC.int(w.views); } },
        { label: t('common.actions'), cls: 'c-actions', render: function (w) {
          return h('div', { class: 'row-actions' },
            h('button', { type: 'button', class: 'btn btn-soft btn-sm', onclick: function (e) { testConn(w, e.currentTarget); } }, icon('refresh'), t('website.test')),
            h('button', { type: 'button', class: 'icon-btn', 'aria-label': t('common.edit') + ' ' + w.name, title: t('common.edit'), onclick: function () { openForm(w); } }, icon('edit')),
            NC.moreBtn(t('common.actions'), [
              { label: t('website.install_info'), icon: 'key', onClick: function () { NC.showCredentials({ site_key: w.site_key, token: null }, w.url); } },
              { label: t('website.rotate'), icon: 'refresh', onClick: function () { rotate(w); } },
              { label: t('nav.landing_pages'), icon: 'file', href: NC.url('/admin/landing-pages') },
              { sep: true }, { label: t('common.delete'), icon: 'trash', danger: true, onClick: function () { del(w); } }]));
        } }
      ];
      listBox.appendChild(NC.table(cols, r.items, { caption: t('nav.websites') }));
    }, function (e) { NC.clear(listBox).appendChild(NC.errorState(e.message, load)); });
  }
  function testConn(w, btn) {
    btn.disabled = true;
    NC.post('/websites/' + w.id + '/test').then(function (r) { NC.toast(t('connection.' + r.result.connection_status), r.result.connection_status === 'connected' ? 'ok' : 'err'); load(); }, function (e) { btn.disabled = false; NC.toast(e.message, 'err'); });
  }
  function rotate(w) {
    NC.confirm({ title: t('website.rotate'), message: t('website.rotate_confirm', { name: w.name }), note: t('website.rotate_note'), danger: true, confirmText: t('website.rotate') })
      .then(function (ok) { if (ok) NC.post('/websites/' + w.id + '/rotate-token').then(function (r) { NC.toast(r.message); NC.showCredentials(r.result, w.url); load(); }, function (e) { NC.toast(e.message, 'err'); }); });
  }
  function del(w) { NC.confirm({ title: t('website.delete'), message: t('website.delete_confirm', { name: w.name }), note: t('website.delete_note'), danger: true, confirmText: t('common.delete') }).then(function (ok) { if (ok) NC.del('/websites/' + w.id).then(function (r) { NC.toast(r.message); load(); }, function (e) { NC.toast(e.message, 'err'); }); }); }

  function openForm(w) {
    var edit = !!w;
    var f = {
      client_id: NC.select('ws-client', 'client_id', [['', '—']].concat(clients.map(function (c) { return [c.id, c.name]; })), w ? w.client_id : ''),
      name: NC.input('ws-name', 'name', { value: w ? w.name : '', attrs: { maxlength: 190 } }),
      url: NC.input('ws-url', 'url', { value: w ? w.url : '', attrs: { type: 'url', dir: 'ltr', placeholder: 'https://client-site.co.il' } }),
      strict_pages: NC.select('ws-strict', 'strict_pages', [['1', t('website.strict_on')], ['0', t('website.strict_off')]], w ? (w.strict_pages ? '1' : '0') : '1'),
      wp_api_user: NC.input('ws-wpu', 'wp_api_user', { value: w ? (w.wp_api_user || '') : '', attrs: { dir: 'ltr', autocomplete: 'off' } }),
      wp_api_secret: NC.input('ws-wps', 'wp_api_secret', { value: '', attrs: { type: 'password', dir: 'ltr', autocomplete: 'new-password', placeholder: w && w.has_wp_credentials ? '••••••••' : '' } })
    };
    var msg = h('p', { class: 'err form-err', hidden: true, role: 'alert' });
    var save = h('button', { type: 'submit', form: 'ws-form', class: 'btn btn-primary' }, edit ? t('common.save_changes') : t('website.add'));
    var form = h('form', { id: 'ws-form', class: 'form', novalidate: true },
      NC.field(t('nav.clients'), f.client_id, { req: true }), NC.field(t('website.name'), f.name, { req: true }), NC.field(t('website.url'), f.url, { req: true }),
      NC.field(t('website.strict'), f.strict_pages, { hint: t('website.strict_hint') }),
      h('h3', { class: 'form-h' }, t('website.wp_credentials')), h('p', { class: 'muted' }, t('website.wp_credentials_hint')),
      h('div', { class: 'grid-2' }, NC.field(t('website.wp_user'), f.wp_api_user), NC.field(t('website.wp_secret'), f.wp_api_secret, { hint: t('website.wp_secret_hint') })), msg);
    var d = NC.modal({ title: edit ? t('website.edit') : t('website.add'), body: form, footer: [save, h('button', { type: 'button', class: 'btn btn-ghost', onclick: function () { d.close(); } }, t('common.cancel'))], wide: true });
    form.addEventListener('submit', function (e) {
      e.preventDefault(); NC.setFieldErrors(form, null); msg.hidden = true; save.disabled = true;
      var body = {}; Object.keys(f).forEach(function (k) { body[k] = f[k].value; });
      (edit ? NC.put('/websites/' + w.id, body) : NC.post('/websites', body)).then(function (r) {
        d.close(); NC.toast(r.message); load(); if (!edit) NC.showCredentials(r.result, body.url);
      }, function (err) { save.disabled = false; if (err.fields) NC.setFieldErrors(form, err.fields); msg.hidden = false; msg.textContent = err.message; });
    });
  }

  NC.clear(view).append(h('div', { class: 'page-head' }, h('div', null, h('h1', null, t('website.title')), h('p', { class: 'sub' }, t('website.sub'))),
    h('div', { class: 'head-actions' }, h('button', { type: 'button', class: 'btn btn-primary', onclick: function () { openForm(null); } }, icon('plus'), t('website.add')))),
    NC.card(null, listBox, { cls: 'card-flush' }), h('p', { class: 'callout' }, icon('alert'), h('span', null, t('website.status_note'))));
  NC.get('/clients/options').then(function (r) { clients = r.items; });
  load();
});
