/* Support page: contact details come from admin settings. */
NC.page(function (view) {
  'use strict';
  var h = NC.h, t = NC.t, icon = NC.icon, s = NC.boot.support || {};
  var wa = (s.whatsapp || '').replace(/\D+/g, '');
  if (wa.charAt(0) === '0') wa = '972' + wa.slice(1);
  function item(ic, label, value, href, external) { return h('a', { class: 'support-item', href: href, target: external ? '_blank' : null, rel: external ? 'noopener noreferrer' : null }, h('span', { class: 'kpi-ico tone-violet' }, icon(ic)), h('span', null, h('b', null, label), h('small', { class: 'ltr' }, value))); }
  var items = [];
  if (wa) items.push(item('whatsapp', t('support.whatsapp'), s.whatsapp, 'https://wa.me/' + wa, true));
  if (s.phone) items.push(item('phone', t('support.phone'), s.phone, 'tel:' + s.phone.replace(/[^0-9+]/g, '')));
  if (s.email) items.push(item('mail', t('support.email'), s.email, 'mailto:' + s.email));
  NC.clear(view).append(h('div', { class: 'page-head' }, h('div', null, h('h1', null, t('nav.support')), h('p', { class: 'sub' }, t('support.sub')))),
    NC.card(t('support.contact_us'), items.length ? h('div', { class: 'support-list' }, items) : h('p', { class: 'muted' }, t('support.none')), { cls: 'card-pad' }));
});
