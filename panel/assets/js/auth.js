/* Login/forgot pages: language switch + password reveal. */
(function () {
  'use strict';
  var csrf = (document.querySelector('meta[name=csrf]') || {}).content || '';
  var base = (location.pathname.match(/^(.*?)\/(login|forgot|reset|install)/) || [])[1] || '';
  document.querySelectorAll('[data-set-lang]').forEach(function (b) {
    b.addEventListener('click', function () {
      fetch(base + '/set-language', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf }, body: JSON.stringify({ locale: b.getAttribute('data-set-lang') }) })
        .then(function () { location.reload(); }, function () { location.reload(); });
    });
  });
  document.querySelectorAll('[data-toggle-pw]').forEach(function (b) {
    b.addEventListener('click', function () {
      var i = document.getElementById(b.getAttribute('data-toggle-pw')); if (!i) return;
      i.type = i.type === 'password' ? 'text' : 'password';
      var u = b.querySelector('use'); if (u) u.setAttribute('href', i.type === 'password' ? '#i-eye' : '#i-eye-off');
    });
  });
})();
