/* Client account settings: profile, password, subscription (read-only). */
NC.page(function (view) {
  'use strict';
  var h = NC.h, t = NC.t, icon = NC.icon;
  var body = h('div', { class: 'dash' });
  function load() {
    NC.clear(body).appendChild(NC.skeleton(4));
    NC.get('/account').then(function (r) {
      NC.clear(body);
      var f = {
        name: NC.input('ac-name', 'name', { value: r.user.name, attrs: { maxlength: 190, autocomplete: 'name' } }),
        email: NC.input('ac-email', 'email', { value: r.user.email, attrs: { type: 'email', dir: 'ltr', autocomplete: 'email' } }),
        current_password: NC.input('ac-cur', 'current_password', { value: '', attrs: { type: 'password', dir: 'ltr', autocomplete: 'current-password' } }),
        new_password: NC.input('ac-new', 'new_password', { value: '', attrs: { type: 'password', dir: 'ltr', autocomplete: 'new-password' } })
      };
      var msg = h('p', { class: 'err form-err', hidden: true, role: 'alert' });
      var save = h('button', { type: 'submit', class: 'btn btn-primary' }, t('common.save_changes'));
      var langSel = h('select', { class: 'input', id: 'ac-lang', 'aria-label': t('account.language') }, [['he', 'עברית'], ['en', 'English']].map(function (o) { return h('option', { value: o[0], selected: o[0] === NC.boot.locale }, o[1]); }));
      langSel.addEventListener('change', function () {
        fetch(NC.boot.base + '/set-language', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': NC.boot.csrf }, body: JSON.stringify({ locale: langSel.value }) }).then(function () { location.reload(); });
      });
      var form = h('form', { class: 'form', novalidate: true },
        h('div', { class: 'grid-2' }, NC.field(t('client.contact_name'), f.name, { req: true }), NC.field(t('auth.email'), f.email, { req: true })),
        h('h3', { class: 'form-h' }, t('account.change_password')), h('p', { class: 'muted' }, t('account.password_hint')),
        h('div', { class: 'grid-2' }, NC.field(t('account.current_password'), f.current_password), NC.field(t('auth.new_password'), f.new_password, { hint: t('validation.password_rule') })),
        msg, h('div', null, save));
      form.addEventListener('submit', function (e) {
        e.preventDefault(); NC.setFieldErrors(form, null); msg.hidden = true; save.disabled = true;
        var b = {}; Object.keys(f).forEach(function (k) { b[k] = f[k].value; });
        NC.put('/account', b).then(function (x) { NC.toast(x.message); load(); }, function (err) { save.disabled = false; if (err.fields) NC.setFieldErrors(form, err.fields); msg.hidden = false; msg.textContent = err.message; });
      });
      body.appendChild(NC.card(t('account.profile'), form, { cls: 'card-pad' }));
      body.appendChild(NC.card(t('account.preferences'), h('div', { class: 'field' }, h('label', { class: 'label', for: 'ac-lang' }, t('account.language')), langSel), { cls: 'card-pad' }));
      if (r.client) {
        var sub = r.subscription;
        function row(l, v) { return h('div', { class: 'info-row' }, h('dt', null, l), h('dd', null, v)); }
        body.appendChild(NC.card(t('account.subscription'), sub ? h('dl', { class: 'info-grid' },
          row(t('client.plan'), sub.plan ? (t('plan.' + sub.plan) === 'plan.' + sub.plan ? sub.plan : t('plan.' + sub.plan)) : '—'), row(t('client.status'), NC.accountBadge(sub.state === 'none' ? 'active' : sub.state)),
          row(t('client.start_date'), NC.date(sub.start_date)), row(t('client.end_date'), NC.date(sub.end_date)),
          row(t('account.days_left'), sub.days_left < 0 ? t('client.expired_days', { days: Math.abs(sub.days_left) }) : t('client.days_left', { days: sub.days_left })),
          row(t('client.payment_status'), t('payment.' + sub.payment_status))) : h('p', { class: 'muted' }, t('client.no_subscription')), { cls: 'card-pad' }));
        body.appendChild(NC.card(t('account.business'), h('dl', { class: 'info-grid' }, row(t('client.business_name'), r.client.business_name), row(t('client.phone'), h('span', { class: 'ltr' }, r.client.phone)),
          row(t('client.website'), r.client.website_url ? h('a', { class: 'ext-link ltr', href: NC.safeHref(r.client.website_url), target: '_blank', rel: 'noopener noreferrer' }, r.client.website_url) : '—'), row(t('account.client_id'), h('code', { class: 'ltr' }, r.client.public_id))), { cls: 'card-pad' }));
      }
    }, function (e) { NC.clear(body).appendChild(NC.errorState(e.message, load)); });
  }
  NC.clear(view).append(h('div', { class: 'page-head' }, h('div', null, h('h1', null, t('nav.account')), h('p', { class: 'sub' }, t('account.sub')))), body);
  load();
});
