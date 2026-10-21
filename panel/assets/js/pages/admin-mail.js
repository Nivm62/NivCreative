/* Admin: personal mailbox (IMAP/SMTP). Messages are shown as plain text only; nothing from a mail is ever executed or rendered as HTML. */
NC.page(function (view) {
  'use strict';
  var h = NC.h, t = NC.t, icon = NC.icon;
  var P = NC.params();
  var S = { folder: 'INBOX', page: 1, q: '', unseen: false, uid: null }, folders = [], account = null, presets = {}, curMsg = null;
  var root = h('div', { class: 'mail' });
  var URLRE = /(https?:\/\/[^\s<>"')]+)/g;

  function linkify(text) {
    var frag = document.createDocumentFragment();
    String(text).split(URLRE).forEach(function (part, i) {
      if (i % 2 === 1 && NC.safeHref(part)) frag.appendChild(h('a', { href: part, target: '_blank', rel: 'noopener noreferrer nofollow' }, part));
      else frag.appendChild(document.createTextNode(part));
    });
    return frag;
  }
  function who(a) { return a && (a.name || a.email) ? (a.name || a.email) : '—'; }
  function dateShort(s) {
    if (!s) return '';
    var today = NC.boot.today || '';
    return s.slice(0, 10) === today ? s.slice(11, 16) : NC.dateTime(s).slice(0, 5);
  }
  function sizeText(n) { return n > 1048576 ? (n / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(n / 1024)) + ' KB'; }

  /* ----------------------------------------------------------- settings */
  function settingsView() {
    var a = account || {};
    var f = {
      email: NC.input('mb-email', 'email', { value: a.email || '', attrs: { type: 'email', dir: 'ltr', autocomplete: 'off', placeholder: 'name@nivcreative.com' } }),
      display_name: NC.input('mb-name', 'display_name', { value: a.display_name || '', attrs: { maxlength: 190 } }),
      username: NC.input('mb-user', 'username', { value: a.username && a.username !== a.email ? a.username : '', attrs: { dir: 'ltr', autocomplete: 'off', placeholder: t('mail.username_hint') } }),
      password: NC.input('mb-pass', 'password', { value: '', attrs: { type: 'password', dir: 'ltr', autocomplete: 'new-password', placeholder: a.has_password ? '••••••••' : '' } }),
      imap_host: NC.input('mb-ih', 'imap_host', { value: a.imap_host || presets.hostinger.imap_host, attrs: { dir: 'ltr' } }),
      imap_port: NC.input('mb-ip', 'imap_port', { value: a.imap_port || presets.hostinger.imap_port, attrs: { type: 'number', dir: 'ltr' } }),
      imap_secure: NC.select('mb-is', 'imap_secure', [['ssl', 'SSL/TLS'], ['tls', 'STARTTLS']], a.imap_secure || 'ssl'),
      smtp_host: NC.input('mb-sh', 'smtp_host', { value: a.smtp_host || presets.hostinger.smtp_host, attrs: { dir: 'ltr' } }),
      smtp_port: NC.input('mb-sp', 'smtp_port', { value: a.smtp_port || presets.hostinger.smtp_port, attrs: { type: 'number', dir: 'ltr' } }),
      smtp_secure: NC.select('mb-ss', 'smtp_secure', [['ssl', 'SSL/TLS'], ['tls', 'STARTTLS']], a.smtp_secure || 'ssl'),
      signature: NC.input('mb-sig', 'signature', { tag: 'textarea', value: a.signature || '', attrs: { rows: 3, maxlength: 2000 } })
    };
    var msg = h('p', { class: 'err form-err', hidden: true, role: 'alert' });
    var testBox = h('div');
    var save = h('button', { type: 'submit', class: 'btn btn-primary' }, a.email ? t('common.save_changes') : t('mail.connect'));
    var form = h('form', { class: 'form', novalidate: true },
      h('p', { class: 'callout' }, icon('mail'), h('span', null, t('mail.intro'))),
      h('div', { class: 'grid-2' }, NC.field(t('auth.email'), f.email, { req: true }), NC.field(t('mail.display_name'), f.display_name)),
      h('div', { class: 'grid-2' }, NC.field(t('mail.username'), f.username), NC.field(t('client.password'), f.password, { req: !a.has_password, hint: t('mail.password_hint') })),
      h('h3', { class: 'form-h' }, t('mail.incoming')),
      h('div', { class: 'grid-3' }, NC.field('IMAP', f.imap_host), NC.field(t('mail.port'), f.imap_port), NC.field(t('mail.security'), f.imap_secure)),
      h('h3', { class: 'form-h' }, t('mail.outgoing')),
      h('div', { class: 'grid-3' }, NC.field('SMTP', f.smtp_host), NC.field(t('mail.port'), f.smtp_port), NC.field(t('mail.security'), f.smtp_secure)),
      NC.field(t('mail.signature'), f.signature), msg,
      h('div', { class: 'form-actions' }, save,
        a.email ? h('button', { type: 'button', class: 'btn btn-soft', onclick: runTest }, icon('refresh'), t('mail.test')) : null,
        a.email ? h('button', { type: 'button', class: 'btn btn-ghost', onclick: function () { S.settings = false; boot(); } }, t('common.cancel')) : null,
        a.email ? h('button', { type: 'button', class: 'btn btn-text txt-red', onclick: disconnect }, t('mail.disconnect')) : null),
      testBox);
    function runTest(e) {
      var btn = e.currentTarget; btn.disabled = true; NC.clear(testBox);
      NC.post('/mail/account/test').then(function (r) {
        btn.disabled = false;
        ['imap', 'smtp'].forEach(function (k) {
          var x = r.result[k];
          testBox.appendChild(h('p', { class: 'callout ' + (x.ok ? '' : 'callout-warn') }, icon(x.ok ? 'check' : 'alert'), h('span', null, k.toUpperCase() + ': ' + (x.ok ? t('mail.test_ok') : x.error))));
        });
      }, function (er) { btn.disabled = false; NC.toast(er.message, 'err'); });
    }
    function disconnect() {
      NC.confirm({ title: t('mail.disconnect'), message: t('mail.disconnect_confirm'), danger: true, confirmText: t('mail.disconnect') }).then(function (ok) {
        if (ok) NC.del('/mail/account').then(function (r) { NC.toast(r.message); account = null; S.settings = false; boot(); }, function (e) { NC.toast(e.message, 'err'); });
      });
    }
    form.addEventListener('submit', function (e) {
      e.preventDefault(); NC.setFieldErrors(form, null); msg.hidden = true; save.disabled = true;
      var body = {}; Object.keys(f).forEach(function (k) { body[k] = f[k].value; });
      NC.put('/mail/account', body).then(function (r) { NC.toast(r.message); account = r.account; S.settings = false; boot(); },
        function (err) { save.disabled = false; if (err.fields) NC.setFieldErrors(form, err.fields); msg.hidden = false; msg.textContent = err.message; });
    });
    return NC.card(a.email ? t('mail.settings') : t('mail.connect_title'), form);
  }

  /* ----------------------------------------------------------- main layout */
  var foldersBox = h('nav', { class: 'mail-folders', 'aria-label': t('mail.folders') });
  var listBox = h('div', { class: 'mail-list' }), pagerBox = h('div'), readBox = h('section', { class: 'mail-read', 'aria-live': 'polite' });
  function folderIcon(role) { return { inbox: 'mail', sent: 'send', trash: 'trash' }[role] || 'file'; }
  function roleLabel(f) { var L = { inbox: t('mail.folder_inbox'), sent: t('mail.folder_sent'), drafts: t('mail.folder_drafts'), junk: t('mail.folder_junk'), trash: t('mail.folder_trash'), archive: t('mail.folder_archive') }; return L[f.role] || f.label; }

  function renderFolders() {
    NC.clear(foldersBox);
    folders.forEach(function (f) {
      foldersBox.appendChild(h('button', { type: 'button', class: 'mail-folder' + (f.name === S.folder ? ' on' : ''), onclick: function () { S.folder = f.name; S.page = 1; S.uid = null; S.unseen = false; closeReader(); renderFolders(); loadList(); } }, icon(folderIcon(f.role)), h('span', null, roleLabel(f))));
    });
  }

  function loadList() {
    NC.clear(listBox).appendChild(NC.skeleton(6)); NC.clear(pagerBox);
    NC.get('/mail/messages', { folder: S.folder, page: S.page, q: S.q, unseen: S.unseen ? '1' : '' }).then(function (r) {
      NC.clear(listBox);
      if (!r.items.length) { listBox.appendChild(NC.empty('mail', t('mail.empty'), '', null)); return; }
      var sent = (folders.filter(function (f) { return f.name === S.folder; })[0] || {}).role === 'sent';
      r.items.forEach(function (m) {
        var row = h('button', { type: 'button', class: 'mail-row' + (m.seen ? '' : ' unread') + (S.uid === m.uid ? ' on' : ''), onclick: function () { openMessage(m.uid, row); } },
          h('div', { class: 'mail-row-top' }, h('b', { class: 'ellip' }, sent ? (m.to[0] || '—') : who(m.from)), h('small', { class: 'muted' }, dateShort(m.date))),
          h('div', { class: 'ellip' }, m.subject || t('mail.no_subject')),
          m.lead_id ? h('small', { class: 'mail-lead' }, icon('target', 'i-sm'), t('mail.is_lead')) : null);
        row.__uid = m.uid; listBox.appendChild(row);
      });
      pagerBox.appendChild(NC.pager(r.meta, function (pg) { S.page = pg; loadList(); }));
    }, function (e) { NC.clear(listBox).appendChild(NC.errorState(e.message, loadList)); });
  }

  function closeReader() { root.classList.remove('reading'); curMsg = null; NC.clear(readBox).appendChild(h('div', { class: 'mail-placeholder muted' }, icon('mail'), t('mail.select'))); }

  function openMessage(uid) {
    S.uid = uid; root.classList.add('reading');
    NC.clear(readBox).appendChild(NC.skeleton(5));
    NC.get('/mail/message', { folder: S.folder, uid: uid }).then(function (r) {
      var m = curMsg = r.message;
      Array.prototype.forEach.call(listBox.querySelectorAll('.mail-row'), function (el) { el.classList.toggle('on', el.__uid === uid); });
      NC.clear(readBox).append.apply(readBox, [
        h('div', { class: 'mail-read-head' },
          h('button', { type: 'button', class: 'btn btn-ghost btn-sm mail-back', onclick: closeReader }, icon('arrow-right'), t('common.back')),
          h('h2', null, m.subject || t('mail.no_subject')),
          h('div', { class: 'mail-meta' },
            h('div', null, h('b', null, who(m.from)), ' ', h('span', { class: 'muted ltr' }, '<' + m.from.email + '>')),
            h('small', { class: 'muted' }, t('mail.to') + ': ' + m.to.map(function (x) { return x.email; }).join(', ') + (m.cc.length ? ' · Cc: ' + m.cc.map(function (x) { return x.email; }).join(', ') : '') + ' · ' + NC.dateTime(m.date))),
          h('div', { class: 'row-actions' },
            h('button', { type: 'button', class: 'btn btn-primary btn-sm', onclick: function () { compose({ to: m.reply_to || m.from.email, subject: /^re:/i.test(m.subject) ? m.subject : 'Re: ' + m.subject, quote: m, lead: m.leads[0] }); } }, icon('send'), t('mail.reply')),
            h('button', { type: 'button', class: 'btn btn-ghost btn-sm', onclick: function () { markUnread(m); } }, t('mail.mark_unread')),
            h('button', { type: 'button', class: 'btn btn-ghost btn-sm txt-red', onclick: function () { remove(m); } }, icon('trash'), t('common.delete'))),
          m.leads.length ? h('p', { class: 'callout' }, icon('target'), h('span', null, t('mail.lead_match') + ' '), m.leads.map(function (l) { return h('a', { href: NC.url('/admin/leads?open=' + l.id) }, l.name + ' (' + l.client + ')'); })) : null),
        h('div', { class: 'mail-body', dir: 'auto' }, linkify(m.text || t('mail.empty_body'))),
        m.attachments.length ? h('div', { class: 'mail-att' }, h('b', null, t('mail.attachments')), m.attachments.map(function (x) {
          return h('a', { class: 'btn btn-soft btn-sm', href: NC.url('/api/mail/attachment?folder=' + encodeURIComponent(S.folder) + '&uid=' + m.uid + '&part=' + encodeURIComponent(x.part)) }, icon('file'), x.name + ' · ' + sizeText(x.size));
        })) : null
      ].filter(Boolean));
      loadListQuiet();
    }, function (e) { NC.clear(readBox).appendChild(NC.errorState(e.message, function () { openMessage(uid); })); });
  }
  function loadListQuiet() { // refresh unread styling after opening a message
    NC.get('/mail/messages', { folder: S.folder, page: S.page, q: S.q, unseen: S.unseen ? '1' : '' }).then(function (r) {
      var rows = listBox.querySelectorAll('.mail-row');
      r.items.forEach(function (m, i) { if (rows[i]) { rows[i].classList.toggle('unread', !m.seen); rows[i].classList.toggle('on', S.uid === m.uid); } });
    }, function () { });
  }
  function markUnread(m) { NC.post('/mail/flag', { folder: S.folder, uids: [m.uid], seen: false }).then(function () { NC.toast(t('mail.marked')); loadList(); }, function (e) { NC.toast(e.message, 'err'); }); }
  function remove(m) {
    NC.confirm({ title: t('common.delete'), message: t('mail.delete_confirm'), danger: true, confirmText: t('common.delete') }).then(function (ok) {
      if (ok) NC.post('/mail/delete', { folder: S.folder, uid: m.uid }).then(function (r) { NC.toast(r.message); closeReader(); loadList(); }, function (e) { NC.toast(e.message, 'err'); });
    });
  }

  /* ----------------------------------------------------------- compose */
  function compose(o) {
    o = o || {};
    var body = '\n\n' + (account.signature ? '-- \n' + account.signature + '\n' : '');
    if (o.quote) {
      var q = String(o.quote.text || '').split('\n').slice(0, 40).map(function (l) { return '> ' + l; }).join('\n');
      body = '\n\n' + t('mail.quote_head', { date: NC.dateTime(o.quote.date), who: who(o.quote.from) }) + '\n' + q + '\n\n' + (account.signature ? '-- \n' + account.signature + '\n' : '');
    }
    var f = {
      to: NC.input('mc-to', 'to', { value: o.to || '', attrs: { dir: 'ltr', autocomplete: 'off', placeholder: 'name@example.com' } }),
      cc: NC.input('mc-cc', 'cc', { value: '', attrs: { dir: 'ltr', autocomplete: 'off' } }),
      subject: NC.input('mc-sub', 'subject', { value: o.subject || '', attrs: { maxlength: 255, dir: 'auto' } }),
      body: NC.input('mc-body', 'body', { tag: 'textarea', value: o.body || body, attrs: { rows: 12, dir: 'auto' } })
    };
    var msg = h('p', { class: 'err form-err', hidden: true, role: 'alert' });
    var send = h('button', { type: 'submit', form: 'mail-form', class: 'btn btn-primary' }, icon('send'), t('mail.send'));
    var form = h('form', { id: 'mail-form', class: 'form', novalidate: true }, NC.field(t('mail.to'), f.to, { req: true }), NC.field('Cc', f.cc), NC.field(t('mail.subject'), f.subject), NC.field(t('mail.message'), f.body, { req: true }), msg);
    var d = NC.modal({ title: t('mail.compose'), body: form, footer: [send, h('button', { type: 'button', class: 'btn btn-ghost', onclick: function () { d.close(); } }, t('common.cancel'))], wide: true });
    form.addEventListener('submit', function (e) {
      e.preventDefault(); NC.setFieldErrors(form, null); msg.hidden = true; send.disabled = true;
      var payload = { to: f.to.value, cc: f.cc.value, subject: f.subject.value, body: f.body.value };
      if (o.quote) { payload.in_reply_to = o.quote.message_id; payload.references = (o.quote.references ? o.quote.references + ' ' : '') + o.quote.message_id; }
      if (o.lead) payload.lead_id = o.lead.id || o.lead;
      NC.post('/mail/send', payload).then(function (r) { d.close(); NC.toast(r.message); if (S.folder && folders.length) loadList(); },
        function (err) { send.disabled = false; if (err.fields) NC.setFieldErrors(form, err.fields); msg.hidden = false; msg.textContent = err.message; });
    });
  }

  /* ----------------------------------------------------------- boot */
  function mainView() {
    var search = h('input', { type: 'search', class: 'input', placeholder: t('mail.search'), 'aria-label': t('common.search'), autocomplete: 'off', value: S.q });
    search.addEventListener('input', NC.debounce(function () { S.q = search.value.trim(); S.page = 1; loadList(); }, 350));
    var unread = h('button', { type: 'button', class: 'btn btn-ghost btn-sm', onclick: function (e) { S.unseen = !S.unseen; S.page = 1; e.currentTarget.classList.toggle('on', S.unseen); loadList(); } }, t('mail.unread_only'));
    NC.clear(root);
    root.append(
      h('div', { class: 'mail-folders-col' }, h('button', { type: 'button', class: 'btn btn-primary btn-block', onclick: function () { compose({}); } }, icon('plus'), t('mail.compose')), foldersBox),
      h('div', { class: 'mail-list-col' }, h('div', { class: 'mail-tools' }, h('div', { class: 'search-field' }, icon('search'), search), unread, h('button', { type: 'button', class: 'icon-btn', title: t('common.refresh'), 'aria-label': t('common.refresh'), onclick: loadList }, icon('refresh'))), listBox, pagerBox),
      readBox);
    closeReader();
    NC.get('/mail/folders').then(function (r) {
      folders = r.items; if (!folders.some(function (f) { return f.name === S.folder; })) S.folder = (folders[0] || {}).name || 'INBOX';
      renderFolders(); loadList();
    }, function (e) { NC.clear(listBox).appendChild(NC.errorState(e.message, boot)); });
    return root;
  }

  var page = h('div');
  function boot() {
    NC.clear(page).appendChild(NC.skeleton(4));
    NC.get('/mail/account').then(function (r) {
      account = r.account; presets = r.presets;
      NC.clear(page);
      if (!account || S.settings) { page.appendChild(settingsView()); return; }
      page.appendChild(mainView());
      if (P.compose === '1') { compose({ to: P.to || '', subject: P.subject || '', lead: P.lead ? { id: +P.lead } : null }); P.compose = ''; }
    }, function (e) { NC.clear(page).appendChild(NC.errorState(e.message, boot)); });
  }
  NC.clear(view).append(h('div', { class: 'page-head' }, h('div', null, h('h1', null, t('mail.title')), h('p', { class: 'sub' }, t('mail.sub'))),
    h('div', { class: 'head-actions' }, h('button', { type: 'button', class: 'btn btn-ghost', onclick: function () { S.settings = true; boot(); } }, icon('settings'), t('mail.settings')))), page);
  boot();
});
