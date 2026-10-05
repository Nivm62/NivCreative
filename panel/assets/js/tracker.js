/*! NivCreative tracker – cookie-less page-view beacon + campaign attribution for landing pages.
 *  <script async src="https://nivcreative.com/panel/assets/js/tracker.js" data-site="ws_xxxxxxxxxxxxxxxxxxxxx"></script>
 *  - Sends ONE tiny beacon per visit (a refresh within 30 minutes is not a new visit).
 *  - Stores UTM parameters + referrer in a first-party cookie (30 days) so the form submission can attach them.
 *  - Does not block rendering (async) and never throws into the host page. Honors Do-Not-Track. */
(function () {
  'use strict';
  try {
    var s = document.currentScript;
    if (!s) return;
    var site = s.getAttribute('data-site');
    if (!site || navigator.doNotTrack === '1' || window.doNotTrack === '1') return;
    var endpoint = s.getAttribute('data-endpoint') || s.src.replace(/\/assets\/js\/tracker\.js.*$/, '/api/v1/track');
    var KEYS = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term'];

    function lsGet(k) { try { return localStorage.getItem(k); } catch (e) { return null; } }
    function lsSet(k, v) { try { localStorage.setItem(k, v); } catch (e) { /* private mode */ } }
    function ssGet(k) { try { return sessionStorage.getItem(k); } catch (e) { return null; } }
    function ssSet(k, v) { try { sessionStorage.setItem(k, v); } catch (e) { /* ignore */ } }

    function visitorId() {
      var id = lsGet('nc_vid');
      if (!id) {
        var a = new Uint8Array(12);
        (window.crypto || window.msCrypto).getRandomValues(a);
        id = Array.prototype.map.call(a, function (b) { return ('0' + b.toString(16)).slice(-2); }).join('');
        lsSet('nc_vid', id);
      }
      return id;
    }

    function readCookie(name) {
      var m = document.cookie.match(new RegExp('(?:^|; )' + name + '=([^;]*)'));
      return m ? decodeURIComponent(m[1]) : '';
    }
    function writeCookie(name, value, days) {
      var d = new Date(); d.setTime(d.getTime() + days * 86400000);
      document.cookie = name + '=' + encodeURIComponent(value) + '; expires=' + d.toUTCString() + '; path=/; SameSite=Lax' + (location.protocol === 'https:' ? '; Secure' : '');
    }

    // Attribution: UTM in the URL always wins; otherwise keep the first-touch value already stored.
    var q = new URLSearchParams(location.search), attr = {}, hasUtm = false;
    KEYS.forEach(function (k) { var v = q.get(k); if (v) { attr[k] = v.slice(0, 190); hasUtm = true; } });
    var stored = {};
    try { stored = JSON.parse(readCookie('nc_attr') || '{}') || {}; } catch (e) { stored = {}; }
    var ref = document.referrer && document.referrer.indexOf(location.host) === -1 ? document.referrer.slice(0, 500) : '';
    if (hasUtm) { attr.referrer = ref || stored.referrer || ''; stored = attr; writeCookie('nc_attr', JSON.stringify(stored), 30); }
    else if (!stored.utm_source && !stored.referrer && ref) { stored = { referrer: ref }; writeCookie('nc_attr', JSON.stringify(stored), 30); }

    window.NivTrack = {
      visitorId: visitorId,
      attribution: function () { var o = {}; KEYS.concat(['referrer']).forEach(function (k) { if (stored[k]) o[k] = stored[k]; }); o.landing_url = location.origin + location.pathname; return o; }
    };

    // One beacon per visit window (30 min) per page.
    var key = 'nc_seen_' + location.pathname, last = parseInt(ssGet(key) || '0', 10), now = Date.now();
    if (last && now - last < 30 * 60 * 1000) return;
    ssSet(key, String(now));

    var body = JSON.stringify({
      site: site, path: location.pathname, vid: visitorId(),
      utm_source: stored.utm_source || '', utm_medium: stored.utm_medium || '', utm_campaign: stored.utm_campaign || '', referrer: stored.referrer || ref
    });
    // text/plain avoids a CORS preflight; the server parses the JSON itself.
    if (navigator.sendBeacon && navigator.sendBeacon(endpoint, new Blob([body], { type: 'text/plain' }))) return;
    fetch(endpoint, { method: 'POST', body: body, headers: { 'Content-Type': 'text/plain' }, keepalive: true, credentials: 'omit' }).catch(function () { });
  } catch (e) { /* never break the landing page */ }
})();
