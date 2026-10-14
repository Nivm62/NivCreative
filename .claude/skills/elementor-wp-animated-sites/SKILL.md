---
name: elementor-wp-animated-sites
description: Build WordPress + Elementor Pro (RTL/Hebrew) websites with live animations and visual effects. Use for any request to build, edit or animate an Elementor page, generate Elementor JSON templates, add scroll/hover/entrance effects, GSAP/ScrollTrigger, parallax, glow/glass effects, or prepare the site for WordPress/Hostinger.
---

# Elementor Pro + WordPress: animated sites

Combine with other installed skills: `frontend-design` (aesthetic direction), `emil-design-eng` (animation timing, easing, polish), `ui-ux-pro-max` (palettes, font pairings, GSAP presets). Load them before designing; this skill covers the Elementor/WordPress implementation.

## Project workflow
- Pages are generated as Elementor template JSON by `build_elementor.py` (output: `nivcreative-home-elementor.json`). Edit the generator, not the JSON, then re-run it.
- Design tokens (colors, fonts) live at the top of the generator. Reuse them; never hardcode new colors inline.
- Use **Flexbox Containers** (not legacy sections/columns). Keep nesting shallow.
- Site is RTL Hebrew: use logical properties (`margin-inline-start`, `padding-inline`), set `direction: rtl`, and mirror directional animations (slide-in from the right is the "start" side). Use Hebrew-capable fonts (Heebo, Assistant, Rubik, Noto Sans Hebrew), with `font-display: swap`.
- Verify the JSON parses and re-imports (Templates > Saved Templates > Import). Keep `version`, `type`, `content`, `page_settings` keys intact.

## Animation toolbox (choose the lightest that works)
1. **Elementor native**: Entrance Animation, Motion Effects (scroll/mouse/tilt/sticky), hover transitions. Zero JS. Prefer for simple reveals.
2. **Custom CSS** (container > Advanced > Custom CSS, or global CSS): keyframes, `@property` gradients, `backdrop-filter` glass, glow shadows, `mask`, `clip-path`, `animation-timeline: view()` with a fallback.
3. **GSAP + ScrollTrigger** for timelines, pinned sections, scrubbed motion, split-text, counters, marquee. Load it properly, not by pasting CDN tags in many widgets:
   - Preferred: a small mu-plugin / child-theme `functions.php` with `wp_enqueue_script` (footer, defer, versioned, local file or one pinned CDN version), plus one `assets/anim.js`.
   - Quick path: one HTML widget or Elementor Custom Code snippet (footer) loaded once site-wide.
   - Target elements with CSS classes set in Elementor (Advanced > CSS Classes), e.g. `.anim-reveal`, `.anim-stagger`, `.anim-parallax`, `.anim-counter`. JS selects by class.
4. **Canvas/WebGL/Lottie** only for a single hero moment; lazy-load and give a static fallback.

## Rules for animation quality
- Animate `transform` and `opacity` only. No layout properties (width, top, margin).
- Entrances 300-600ms, ease-out (e.g. `cubic-bezier(.22,1,.36,1)`); hover 150-250ms. Stagger 60-100ms. Nothing over ~1s except ambient loops.
- Always honour `prefers-reduced-motion: reduce` (disable or simplify motion).
- Hidden-until-revealed elements must be visible without JS (use a `.js-anim` class added by the script, hide only under it) to avoid blank pages and layout shift.
- Re-run `ScrollTrigger.refresh()` after fonts/images load; kill triggers on Elementor editor mode (`document.body.classList.contains('elementor-editor-active')`) so the editor stays usable.
- Pause offscreen loops (IntersectionObserver). Keep ambient effects (glow, particles, gradients) cheap and few per viewport.
- Mobile: reduce or remove parallax/pin; check touch targets (min 44px).

## Effects recipes (dark "cyber luxe" style)
- Glass card: translucent bg, `backdrop-filter: blur(16px)`, 1px border at low alpha, hover glow via box-shadow in the accent color.
- Gradient text/border: `background-clip: text` or masked pseudo-element; animate with `background-position`.
- Spotlight/cursor glow: CSS vars `--x/--y` updated on `pointermove` (throttled with rAF).
- Marquee: duplicated track + CSS `translateX` loop, pause on hover.
- Counters and split-text reveals: GSAP, triggered once at ~80% viewport.

## Performance and delivery
- Images: WebP/AVIF, explicit sizes, lazy-load below the fold, hero `fetchpriority=high`.
- Self-host fonts where possible; limit to 2 families.
- Test with Lighthouse mobile; keep CLS near 0. Don't ship unminified debug code.
- Deploy target is Hostinger (see `site/` for the static version, `send.php` for the form handler).
- Never commit credentials or `wp-config` values. Back up before importing templates on a live site.

## Done checklist
- Renders correctly RTL at mobile/tablet/desktop, works with JS disabled, respects reduced motion, no console errors, editor still usable.
