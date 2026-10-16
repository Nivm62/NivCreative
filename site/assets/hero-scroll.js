// Scroll-driven hero: maps scroll progress through #hero-scroll to the video's currentTime,
// fades the headline out and the kicker line in. Eased with rAF so scrubbing feels smooth.
(function () {
  var scene = document.getElementById('hero-scroll');
  if (!scene) return;
  var video = scene.querySelector('.hero-video');
  var content = scene.querySelector('.hero-content');
  var kicker = scene.querySelector('.hero-kicker');
  var cue = scene.querySelector('.hero-cue');
  var bar = scene.querySelector('.hero-progress span');
  var reduce = window.matchMedia('(prefers-reduced-motion: reduce)');

  function clamp(x) { return x < 0 ? 0 : x > 1 ? 1 : x; }
  function range(a, b, x) { return clamp((x - a) / (b - a)); }

  // iOS Safari won't decode frames for seeking until the video has played once.
  function prime() {
    var p = video.play();
    if (p && p.then) p.then(function () { video.pause(); }).catch(function () {});
    else video.pause();
  }
  prime();
  window.addEventListener('touchstart', prime, { once: true, passive: true });

  if (reduce.matches) return;

  var target = 0, shown = 0, seeking = false, ticking = false;
  video.addEventListener('seeked', function () { seeking = false; });

  function progress() {
    var r = scene.getBoundingClientRect();
    var span = scene.offsetHeight - window.innerHeight;
    return span > 0 ? clamp(-r.top / span) : 0;
  }

  function render() {
    ticking = false;
    shown += (target - shown) * 0.14;
    if (Math.abs(target - shown) < 0.0005) shown = target;
    var p = shown;

    var d = video.duration;
    if (d && !seeking) {
      var t = p * (d - 0.05);
      if (Math.abs(video.currentTime - t) > 0.02) {
        seeking = true;
        if (video.fastSeek && Math.abs(video.currentTime - t) > 1) video.fastSeek(t); else video.currentTime = t;
      }
    }

    var out = range(0.08, 0.42, p);
    content.style.opacity = 1 - out;
    content.style.transform = 'translateY(' + (-70 * out) + 'px) scale(' + (1 - 0.08 * out) + ')';
    content.style.pointerEvents = out > 0.6 ? 'none' : '';

    // The kicker stays on screen at the end so the scene hands off to the page without an empty frame.
    var kin = range(0.45, 0.62, p), kend = range(0.62, 1, p);
    kicker.style.opacity = kin;
    kicker.style.transform = 'translateY(' + (40 * (1 - kin)) + 'px) scale(' + (0.94 + 0.06 * kin + 0.05 * kend) + ')';

    cue.style.opacity = 1 - range(0.02, 0.12, p);
    cue.style.pointerEvents = p > 0.1 ? 'none' : '';
    bar.style.transform = 'scaleX(' + p + ')';

    if (shown !== target) request();
  }

  function request() { if (!ticking) { ticking = true; requestAnimationFrame(render); } }
  function onScroll() { target = progress(); request(); }

  window.addEventListener('scroll', onScroll, { passive: true });
  window.addEventListener('resize', onScroll);
  video.addEventListener('loadedmetadata', onScroll);
  onScroll();
})();
