/* NivCreative page-view beacon: one tiny request per page load; no cookies, no personal data stored. */
(function () {
  'use strict';
  var cfg = window.nivcTrack;
  if (!cfg || !cfg.url) return;
  if (navigator.doNotTrack === '1' || window.doNotTrack === '1') return;
  if (document.body && document.body.classList.contains('logged-in') && document.body.classList.contains('admin-bar')) return;
  try {
    var body = JSON.stringify({ p: location.pathname });
    fetch(cfg.url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: body,
      keepalive: true,
      credentials: 'same-origin'
    }).catch(function () {});
  } catch (e) { /* never break the landing page */ }
})();
