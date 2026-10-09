/* Admin: manage every panel login (administrators and client users). */
NC.page(function (view) {
  'use strict';
  var h = NC.h, t = NC.t, icon = NC.icon;
  var state = { search: '', role: '', status: '', page: 1, per_page: 25 };
  var listBox = h('div'), pagerBox = h('div'), clients = [], ctl = null;

  function load() {
    if (ctl) ctl.abort(); ctl = window.AbortController ? new AbortController() : null;
    NC.clear(listBox).appendChild(NC.skeleton(5));
    NC.get('/users', state, { signal: ctl ? ctl.signal : undefined }).then(function (r) {
      NC.clear(listBox); NC.clear(pagerBox);
      if (!r.items.length) { listBox.appendChild(NC.empty('search', t('users.none_found'), '', null)); return; }
      var cols = [
        { label: t('users.user'), cls: 'c-name', render: function (u) { return h('div', { class: 'cell-person' }, NC.avatar(u.name), h('div', null, h('b', null, u.name), h('small', { class: 'muted block ltr ellip' }, u.email))); } },
        { label: t('users.role'), render: function (u) { return h('div', null, NC.badge(u.role === 'admin' ? 'conn-ok' : 'conn-warn', (u.role === 'admin' ? t('users.role_admin') : t('users.role_client'))), u.client_name ? h('small', { class: 'muted block' }, u.client_name) : null); } },
        { label: t('client.status'), render: function (u) { return NC.badge(u.status === 'active' ? 'conn-ok' : 'conn-bad', t('account_status.' + u.status)); } },
        { label: t('users.last_login'), render: function (u) { return u.last_login_at ? h('div', null, NC.ago(u.last_login_at), h('small', { class: 'muted block' }, NC.dateTime(u.last_login_at))) : h('span', { class: 'muted' }, t('users.never')); } },
        { label: t('common.actions'), cls: 'c-actions', render: function (u) {
          var me = u.email === NC.boot.user.email;
          return h('div', { class: 'row-actions' },
            h('button', { type: 'button', class: 'icon-btn', 'aria-label': t('common.edit') + ' ' + u.name, title: t('common.edit'), onclick: function () { openForm(u); } }, icon('edit')),
            me ? null : NC.moreBtn(t('common.actions'), [
              u.status === 'active' ? { label: t('client.disable'), icon: 'pause', onClick: function () { setStatus(u, 'disabled'); } } : { label: t('client.enable'), icon: 'play', onClick: function () { setStatus(u, 'active'); } },
              { sep: true }, { label: t('common.delete'), icon: 'trash', danger: true, onClick: function () { del(u); } }]));
        } }
      ];
      listBox.appendChild(NC.table(cols, r.items, { caption: t('nav.users') }));
      pagerBox.appendChild(NC.pager(r.meta, function (pg) { state.page = pg; load(); }));
    }, function (e) { if (e && e.aborted) return; NC.clear(listBox).appendChild(NC.errorState(e.message, load)); });
  }
  function userBody(u, status) { return { name: u.name, email: u.email, role: u.role, client_id: u.client_id || '', locale: u.locale || 'he', status: status }; }
  function setStatus(u, status) {
    NC.confirm({ title: t(status === 'disabled' ? 'client.disable' : 'client.enable'), message: t(status === 'disabled' ? 'users.disable_confirm' : 'users.enable_confirm', { name: u.name }), danger: status === 'disabled', confirmText: t(status === 'disabled' ? 'client.disable' : 'client.enable') })
      .then(function (ok) { if (ok) NC.put('/users/' + u.id, userBody(u, status)).then(function (r) { NC.toast(r.message); load(); }, function (e) { NC.toast(e.message, 'err'); }); });
  }
  function del(u) {
    NC.confirm({ title: t('users.delete'), message: t('users.delete_confirm', { name: u.name }), note: t('users.delete_note'), danger: true, confirmText: t('common.delete') })
      .then(function (ok) { if (ok) NC.del('/users/' + u.id).then(function (r) { NC.toast(r.message); load(); }, function (e) { NC.toast(e.message, 'err'); }); });
  }

  function openForm(u) {
    var edit = !!u;
    var f = {
      name: NC.input('us-name', 'name', { value: u ? u.name : '', attrs: { maxlength: 190, autocomplete: 'off' } }),
      email: NC.input('us-email', 'email', { value: u ? u.email : '', attrs: { type: 'email', dir: 'ltr', autocomplete: 'off' } }),
      role: NC.select('us-role', 'role', [['admin', t('users.role_admin')], ['client', t('users.role_client')]], u ? u.role : 'admin'),
      client_id: NC.select('us-client', 'client_id', [['', '—']].concat(clients.map(function (c) { return [c.id, c.name]; })), u && u.client_id ? u.client_id : ''),
      status: NC.select('us-status', 'status', [['active', t('account_status.active')], ['disabled', t('account_status.disabled')]], u ? u.status : 'active'),
      locale: NC.select('us-locale', 'locale', [['he', 'עברית'], ['en', 'English']], u ? u.locale : 'he'),
      password: NC.input('us-pass', 'password', { value: '', attrs: { type: 'text', dir: 'ltr', autocomplete: 'new-password', placeholder: edit ? t('client.password_keep') : '' } })
    };
    var clientField = NC.field(t('nav.clients'), f.client_id, { req: true });
    function sync() { clientField.hidden = f.role.value !== 'client'; }
    f.role.addEventListener('change', sync);
    var msg = h('p', { class: 'err form-err', hidden: true, role: 'alert' });
    var save = h('button', { type: 'submit', form: 'user-form', class: 'btn btn-primary' }, edit ? t('common.save_changes') : t('users.add'));
    var form = h('form', { id: 'user-form', class: 'form', novalidate: true },
      h('div', { class: 'grid-2' }, NC.field(t('users.name'), f.name, { req: true }), NC.field(t('auth.email'), f.email, { req: true })),
      h('div', { class: 'grid-2' }, NC.field(t('users.role'), f.role), clientField),
      h('div', { class: 'grid-2' }, NC.field(t('client.password'), f.password, { req: !edit, hint: t('validation.password_rule') }), NC.field(t('client.status'), f.status)),
      NC.field(t('users.language'), f.locale), msg);
    sync();
    var d = NC.modal({ title: edit ? t('users.edit') : t('users.add'), body: form, footer: [save, h('button', { type: 'button', class: 'btn btn-ghost', onclick: function () { d.close(); } }, t('common.cancel'))], wide: true });
    form.addEventListener('submit', function (e) {
      e.preventDefault(); NC.setFieldErrors(form, null); msg.hidden = true; save.disabled = true;
      var body = {}; Object.keys(f).forEach(function (k) { body[k] = f[k].value; });
      (edit ? NC.put('/users/' + u.id, body) : NC.post('/users', body)).then(function (r) { d.close(); NC.toast(r.message); load(); },
        function (err) { save.disabled = false; if (err.fields) NC.setFieldErrors(form, err.fields); msg.hidden = false; msg.textContent = err.message; });
    });
  }

  var search = h('input', { type: 'search', class: 'input', placeholder: t('users.search_placeholder'), 'aria-label': t('common.search'), autocomplete: 'off' });
  search.addEventListener('input', NC.debounce(function () { state.search = search.value.trim(); state.page = 1; load(); }, 280));
  function mkSel(key, opts, label) { var s = h('select', { class: 'input', 'aria-label': label }, opts.map(function (o) { return h('option', { value: o[0] }, o[1]); })); s.addEventListener('change', function () { state[key] = s.value; state.page = 1; load(); }); return s; }
  var role = mkSel('role', [['', t('users.role') + ': ' + t('common.all')], ['admin', t('users.role_admin')], ['client', t('users.role_client')]], t('users.role'));
  var st = mkSel('status', [['', t('client.status') + ': ' + t('common.all')], ['active', t('account_status.active')], ['disabled', t('account_status.disabled')]], t('client.status'));
  NC.clear(view).append(
    h('div', { class: 'page-head' }, h('div', null, h('h1', null, t('users.title')), h('p', { class: 'sub' }, t('users.sub'))),
      h('div', { class: 'head-actions' }, h('button', { type: 'button', class: 'btn btn-primary', onclick: function () { openForm(null); } }, icon('plus'), t('users.add')))),
    NC.card(null, h('div', null, h('div', { class: 'toolbar' }, h('div', { class: 'search-field' }, icon('search'), search), role, st), listBox, pagerBox), { cls: 'card-flush' }));
  NC.get('/clients/options').then(function (r) { clients = r.items; });
  load();
});
