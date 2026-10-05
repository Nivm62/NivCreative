/* Notifications centre (both roles; scoped server-side). */
NC.page(function (view) {
  'use strict';
  var h = NC.h, t = NC.t, icon = NC.icon;
  var box = h('div', { class: 'alert-list' });
  function load() {
    NC.clear(box).appendChild(NC.skeleton(5));
    NC.get('/notifications').then(function (r) {
      NC.setUnread(r.unread); NC.clear(box);
      if (!r.items.length) { box.appendChild(NC.empty('bell', t('notif.empty'), t('notif.empty_hint'))); return; }
      r.items.forEach(function (n) { box.appendChild(NC.notifItem(n)); });
    }, function (e) { NC.clear(box).appendChild(NC.errorState(e.message, load)); });
  }
  NC.clear(view).append(h('div', { class: 'page-head' }, h('div', null, h('h1', null, t('nav.notifications')), h('p', { class: 'sub' }, t('notif.sub'))),
    h('div', { class: 'head-actions' }, h('button', { type: 'button', class: 'btn btn-ghost', onclick: function () { NC.post('/notifications/read', {}).then(function (x) { NC.setUnread(x.unread); load(); }); } }, icon('check'), t('notif.mark_all')))),
    NC.card(null, box, { cls: 'card-flush' }));
  load();
});
