/* NivCreative lightweight SVG charts (no dependencies): line/area, bars, donut, funnel, sparkline. */
(function () {
  'use strict';
  var NS = 'http://www.w3.org/2000/svg';
  var COLORS = ['#6d4cf5', '#3b82f6', '#22c1a1', '#f5a524', '#ef6a8a', '#8b95b8', '#a78bfa'];

  function el(tag, attrs, parent) {
    var n = document.createElementNS(NS, tag);
    if (tag === 'svg') n.style.direction = 'ltr';
    if (attrs) Object.keys(attrs).forEach(function (k) { n.setAttribute(k, attrs[k]); });
    if (parent) parent.appendChild(n);
    return n;
  }
  function niceMax(v) {
    if (v <= 0) return 10;
    var p = Math.pow(10, Math.floor(Math.log10(v)));
    var f = v / p;
    var n = f <= 1 ? 1 : f <= 2 ? 2 : f <= 2.5 ? 2.5 : f <= 5 ? 5 : 10;
    return n * p;
  }
  function fmt(n) { return new Intl.NumberFormat(document.documentElement.lang === 'he' ? 'he-IL' : 'en-US', { maximumFractionDigits: 1 }).format(n); }

  var tip;
  function tooltip() {
    if (!tip) { tip = document.createElement('div'); tip.className = 'chart-tip'; tip.hidden = true; document.body.appendChild(tip); }
    return tip;
  }
  function showTip(evt, lines) {
    var t = tooltip();
    t.textContent = '';
    lines.forEach(function (l, i) {
      var d = document.createElement('div');
      if (i === 0) d.className = 'tip-title';
      if (l.color) { var s = document.createElement('i'); s.style.background = l.color; d.appendChild(s); }
      d.appendChild(document.createTextNode(l.text || l));
      t.appendChild(d);
    });
    t.hidden = false;
    var w = t.offsetWidth, h = t.offsetHeight;
    var x = Math.min(window.innerWidth - w - 8, Math.max(8, evt.clientX - w / 2));
    var y = evt.clientY - h - 14; if (y < 8) y = evt.clientY + 18;
    t.style.left = x + 'px'; t.style.top = y + 'px';
  }
  function hideTip() { if (tip) tip.hidden = true; }

  /** Renders `draw(width)` now and on every resize. */
  function responsive(host, draw) {
    host.textContent = '';
    var last = 0;
    var run = function () {
      var w = Math.floor(host.clientWidth);
      if (w < 40 || w === last) return;
      last = w; host.textContent = ''; draw(w);
    };
    run();
    if (window.ResizeObserver) { var ro = new ResizeObserver(run); ro.observe(host); host._ro = ro; }
  }

  function empty(host, msg) {
    host.textContent = '';
    var d = document.createElement('div'); d.className = 'chart-empty'; d.textContent = msg; host.appendChild(d);
  }

  /**
   * opts: { labels:[...], series:[{name,color,data:[..],area:bool}], height, format(v), emptyText, tickEvery }
   */
  function line(host, opts) {
    var series = opts.series, n = opts.labels.length;
    var any = series.some(function (s) { return s.data.some(function (v) { return v > 0; }); });
    if (!n || !any) return empty(host, opts.emptyText || '—');
    var H = opts.height || 220;
    responsive(host, function (W) {
      var max = niceMax(Math.max.apply(null, series.map(function (s) { return Math.max.apply(null, s.data.map(function (v) { return v || 0; })); })));
      var pad = { l: Math.max(40, fmt(max).length * 7 + 16), r: 18, t: 12, b: 26 }, iw = W - pad.l - pad.r, ih = H - pad.t - pad.b;
      var svg = el('svg', { viewBox: '0 0 ' + W + ' ' + H, width: W, height: H, role: 'img', 'aria-label': opts.label || '' }, host);
      var defs = el('defs', null, svg);
      var X = function (i) { return pad.l + (n === 1 ? iw / 2 : i * iw / (n - 1)); };
      var Y = function (v) { return pad.t + ih - (v / max) * ih; };
      for (var g = 0; g <= 4; g++) {
        var gy = pad.t + ih * g / 4;
        el('line', { x1: pad.l, x2: W - pad.r, y1: gy, y2: gy, class: 'c-grid' }, svg);
        var tx = el('text', { x: pad.l - 8, y: gy + 4, class: 'c-axis', 'text-anchor': 'end' }, svg); tx.textContent = fmt(max * (4 - g) / 4);
      }
      var every = opts.tickEvery || Math.max(1, Math.ceil(n / Math.max(2, Math.floor(iw / 64))));
      var lastTick = Math.floor((n - 1) / every) * every;
      opts.labels.forEach(function (lb, i) {
        var isTick = i % every === 0;
        var isLast = i === n - 1 && i !== lastTick && ((n - 1 - lastTick) * iw / Math.max(1, n - 1)) >= 70;
        if (!isTick && !isLast) return;
        var t = el('text', { x: X(i), y: H - 6, class: 'c-axis', 'text-anchor': i === 0 ? 'start' : (i === n - 1 ? 'end' : 'middle') }, svg); t.textContent = lb;
      });
      series.forEach(function (s, si) {
        var color = s.color || COLORS[si % COLORS.length];
        var pts = s.data.map(function (v, i) { return [X(i), Y(v || 0)]; });
        var d = smooth(pts);
        if (s.area !== false && si === 0) {
          var gid = 'ga' + Math.random().toString(36).slice(2, 8);
          var lg = el('linearGradient', { id: gid, x1: 0, y1: 0, x2: 0, y2: 1 }, defs);
          el('stop', { offset: '0', 'stop-color': color, 'stop-opacity': '.28' }, lg); el('stop', { offset: '1', 'stop-color': color, 'stop-opacity': '0' }, lg);
          el('path', { d: d + ' L' + pts[pts.length - 1][0] + ',' + (pad.t + ih) + ' L' + pts[0][0] + ',' + (pad.t + ih) + ' Z', fill: 'url(#' + gid + ')' }, svg);
        }
        el('path', { d: d, fill: 'none', stroke: color, 'stroke-width': 2.5, 'stroke-linecap': 'round', 'stroke-linejoin': 'round' }, svg);
        if (n <= 40) pts.forEach(function (p) { el('circle', { cx: p[0], cy: p[1], r: 3, fill: '#fff', stroke: color, 'stroke-width': 2 }, svg); });
      });
      var cross = el('line', { y1: pad.t, y2: pad.t + ih, class: 'c-cross', visibility: 'hidden' }, svg);
      var hit = el('rect', { x: pad.l, y: pad.t, width: iw, height: ih, fill: 'transparent' }, svg);
      hit.addEventListener('pointermove', function (e) {
        var r = svg.getBoundingClientRect();
        var i = n === 1 ? 0 : Math.round((e.clientX - r.left - pad.l) / iw * (n - 1));
        i = Math.max(0, Math.min(n - 1, i));
        cross.setAttribute('x1', X(i)); cross.setAttribute('x2', X(i)); cross.setAttribute('visibility', 'visible');
        showTip(e, [{ text: opts.fullLabels ? opts.fullLabels[i] : opts.labels[i] }].concat(series.map(function (s, si) {
          return { color: s.color || COLORS[si % COLORS.length], text: s.name + ': ' + (opts.format ? opts.format(s.data[i], s) : fmt(s.data[i] || 0)) };
        })));
      });
      hit.addEventListener('pointerleave', function () { cross.setAttribute('visibility', 'hidden'); hideTip(); });
    });
  }

  function smooth(p) {
    if (p.length < 3) return 'M' + p.map(function (q) { return q[0] + ',' + q[1]; }).join(' L');
    var d = 'M' + p[0][0] + ',' + p[0][1];
    for (var i = 0; i < p.length - 1; i++) {
      var p0 = p[i - 1] || p[i], p1 = p[i], p2 = p[i + 1], p3 = p[i + 2] || p2, t = 0.18;
      var c1 = [p1[0] + (p2[0] - p0[0]) * t, p1[1] + (p2[1] - p0[1]) * t], c2 = [p2[0] - (p3[0] - p1[0]) * t, p2[1] - (p3[1] - p1[1]) * t];
      d += ' C' + c1[0] + ',' + c1[1] + ' ' + c2[0] + ',' + c2[1] + ' ' + p2[0] + ',' + p2[1];
    }
    return d;
  }

  /** Grouped vertical bars. Same opts as line(). */
  function bars(host, opts) {
    var series = opts.series, n = opts.labels.length;
    var any = series.some(function (s) { return s.data.some(function (v) { return v > 0; }); });
    if (!n || !any) return empty(host, opts.emptyText || '—');
    var H = opts.height || 220;
    responsive(host, function (W) {
      var max = niceMax(Math.max.apply(null, series.map(function (s) { return Math.max.apply(null, s.data.map(function (v) { return v || 0; })); })));
      var pad = { l: Math.max(40, fmt(max).length * 7 + 16), r: 8, t: 12, b: 26 }, iw = W - pad.l - pad.r, ih = H - pad.t - pad.b;
      var svg = el('svg', { viewBox: '0 0 ' + W + ' ' + H, width: W, height: H, role: 'img', 'aria-label': opts.label || '' }, host);
      for (var g = 0; g <= 4; g++) {
        var gy = pad.t + ih * g / 4;
        el('line', { x1: pad.l, x2: W - pad.r, y1: gy, y2: gy, class: 'c-grid' }, svg);
        var tx = el('text', { x: pad.l - 8, y: gy + 4, class: 'c-axis', 'text-anchor': 'end' }, svg); tx.textContent = fmt(max * (4 - g) / 4);
      }
      var slot = iw / n, bw = Math.max(3, Math.min(26, slot * 0.7 / series.length)), gap = 2;
      var every = Math.max(1, Math.ceil(n / Math.max(2, Math.floor(iw / 56))));
      opts.labels.forEach(function (lb, i) {
        var cx = pad.l + slot * (i + 0.5);
        series.forEach(function (s, si) {
          var v = s.data[i] || 0, h = v / max * ih, x = cx - (series.length * bw + (series.length - 1) * gap) / 2 + si * (bw + gap);
          if (h > 0) el('rect', { x: x, y: pad.t + ih - h, width: bw, height: h, rx: Math.min(4, bw / 2), fill: s.color || COLORS[si % COLORS.length] }, svg);
        });
        if (i % every === 0) { var t = el('text', { x: cx, y: H - 6, class: 'c-axis', 'text-anchor': 'middle' }, svg); t.textContent = lb; }
        var hit = el('rect', { x: pad.l + slot * i, y: pad.t, width: slot, height: ih, fill: 'transparent' }, svg);
        hit.addEventListener('pointermove', function (e) {
          showTip(e, [{ text: opts.fullLabels ? opts.fullLabels[i] : lb }].concat(series.map(function (s, si) {
            return { color: s.color || COLORS[si % COLORS.length], text: s.name + ': ' + (opts.format ? opts.format(s.data[i], s) : fmt(s.data[i] || 0)) };
          })));
        });
        hit.addEventListener('pointerleave', hideTip);
      });
    });
  }

  /** Donut. opts: { items:[{label,value,color}], center:{value,label}, size } */
  function donut(host, opts) {
    var items = opts.items.filter(function (i) { return i.value > 0; });
    var total = items.reduce(function (s, i) { return s + i.value; }, 0);
    host.textContent = '';
    var wrap = document.createElement('div'); wrap.className = 'donut-wrap'; host.appendChild(wrap);
    if (!total) return empty(host, opts.emptyText || '—');
    var S = opts.size || 168, R = S / 2 - 12, r = R - 22;
    var svg = el('svg', { viewBox: '0 0 ' + S + ' ' + S, width: S, height: S, role: 'img', 'aria-label': opts.label || '' }, wrap);
    var a0 = -Math.PI / 2;
    items.forEach(function (it) {
      var frac = it.value / total, a1 = a0 + frac * Math.PI * 2 - (items.length > 1 ? 0.025 : 0);
      var large = a1 - a0 > Math.PI ? 1 : 0, c = S / 2;
      var p = function (rad, a) { return (c + rad * Math.cos(a)) + ',' + (c + rad * Math.sin(a)); };
      var path = el('path', {
        d: items.length === 1 ? 'M' + p(R, a0) + ' A' + R + ',' + R + ' 0 1 1 ' + p(R, a0 + Math.PI * 1.999) + ' L' + p(r, a0 + Math.PI * 1.999) + ' A' + r + ',' + r + ' 0 1 0 ' + p(r, a0) + ' Z'
          : 'M' + p(R, a0) + ' A' + R + ',' + R + ' 0 ' + large + ' 1 ' + p(R, a1) + ' L' + p(r, a1) + ' A' + r + ',' + r + ' 0 ' + large + ' 0 ' + p(r, a0) + ' Z',
        fill: it.color, class: 'c-slice'
      }, svg);
      path.addEventListener('pointermove', function (e) { showTip(e, [{ text: it.label }, { color: it.color, text: fmt(it.value) + ' (' + Math.round(frac * 100) + '%)' }]); });
      path.addEventListener('pointerleave', hideTip);
      a0 += frac * Math.PI * 2;
    });
    if (opts.center) {
      var c1 = el('text', { x: S / 2, y: S / 2 + 2, class: 'c-center-v', 'text-anchor': 'middle' }, svg); c1.textContent = opts.center.value;
      var c2 = el('text', { x: S / 2, y: S / 2 + 20, class: 'c-center-l', 'text-anchor': 'middle' }, svg); c2.textContent = opts.center.label;
    }
    var legend = document.createElement('ul'); legend.className = 'legend'; wrap.appendChild(legend);
    items.forEach(function (it) {
      var li = document.createElement('li');
      var dot = document.createElement('i'); dot.style.background = it.color; li.appendChild(dot);
      var nm = document.createElement('span'); nm.textContent = it.label; li.appendChild(nm);
      var pc = document.createElement('b'); pc.textContent = Math.round(it.value / total * 100) + '%'; li.appendChild(pc);
      legend.appendChild(li);
    });
  }

  /** Funnel. opts: { steps:[{label,value}], colors } */
  function funnel(host, opts) {
    host.textContent = '';
    var steps = opts.steps, top = steps[0].value;
    if (!top) { return empty(host, opts.emptyText || '—'); }
    var box = document.createElement('div'); box.className = 'funnel'; host.appendChild(box);
    steps.forEach(function (s, i) {
      var row = document.createElement('div'); row.className = 'funnel-row';
      var bar = document.createElement('div'); bar.className = 'funnel-bar'; bar.style.width = Math.max(14, s.value / top * 100) + '%';
      bar.style.opacity = String(1 - i * 0.13);
      bar.setAttribute('role', 'img'); bar.setAttribute('aria-label', s.label + ': ' + fmt(s.value));
      var lab = document.createElement('div'); lab.className = 'funnel-label';
      var a = document.createElement('b'); a.textContent = fmt(s.value); var b = document.createElement('span'); b.textContent = s.label;
      var c = document.createElement('em'); c.textContent = i === 0 ? '100%' : (steps[i - 1].value ? Math.round(s.value / steps[i - 1].value * 100) + '%' : '—');
      lab.appendChild(a); lab.appendChild(b); lab.appendChild(c);
      var barWrap = document.createElement('div'); barWrap.className = 'funnel-barwrap'; barWrap.appendChild(bar);
      row.appendChild(lab); row.appendChild(barWrap); box.appendChild(row);
    });
  }

  /** Tiny inline sparkline for stat cards. */
  function spark(host, data, color) {
    host.textContent = '';
    if (!data || data.length < 2 || !data.some(function (v) { return v > 0; })) return;
    var W = 90, H = 30, max = Math.max.apply(null, data) || 1;
    var svg = el('svg', { viewBox: '0 0 ' + W + ' ' + H, width: W, height: H, 'aria-hidden': 'true' }, host);
    var pts = data.map(function (v, i) { return [i * W / (data.length - 1), H - 3 - v / max * (H - 6)]; });
    el('path', { d: smooth(pts), fill: 'none', stroke: color || COLORS[0], 'stroke-width': 2, 'stroke-linecap': 'round' }, svg);
  }

  window.NCCharts = { line: line, bars: bars, donut: donut, funnel: funnel, spark: spark, COLORS: COLORS };
})();
