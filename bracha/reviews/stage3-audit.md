# Stage 3 audit – https://nivcreative.com/bracha/ (page 3646)

Date: 2026-11-04. Method: live Playwright checks at 390px and 1440px + raw-HTML checks + web-interface-guidelines, a11y, perf and prod-readiness lenses.

## Fixed in this pass
| Area | Finding | Fix |
|---|---|---|
| SEO / share | Server HTML had title "ברכה ברנס - nivcreative.com", no meta description, no og:image (SEO was injected by JS only; WhatsApp/Facebook don't run JS) | Set via SureRank post settings (this page only); removed JS injection |
| Landmarks | No `<main>`; skip link had no target | Sections wrapped in `<main id="main">` |
| Screen readers | Decorative images/icons announced with filename-derived alt ("icon open book", "12 pastel wave mint") | `aria-hidden` on decorative image/icon widgets |
| Contrast | Teal text 2.7:1 / 3.3:1; hero WhatsApp gradient top ~3:1 | Darker teal text (#00756d), darker button gradient, footer link darkened |
| Tap targets | Footer credit link 18px tall, nav links 43px | min-height/width 44px |
| Perf | Logo PNG 173 KB for a 94px image; extra font weights | Logo -> 336px WebP (40 KB); fewer font weights; preconnect to Google Fonts |
| Keyboard | Esc closed the menu but lost focus | Focus returns to the menu button |
| Misc | no `touch-action`, headings could leave widows | `touch-action: manipulation`, `text-wrap: balance` |
| Code | dead SVG constants / CSS, hard-coded /tmp path in publish.py | removed; tempfile used |

## Verified OK
One H1; heading order h1>h2>h3; no horizontal scroll (390/1440); CLS 0.09 mobile / 0.002 desktop; visible 3px focus ring on all controls; Tab order logical; reduced-motion shows all content and disables smooth scroll; every interactive element has an accessible name; images have width/height; below-fold images lazy.

## Open items (not changed)
1. **Indexing**: page is `index, follow` while it still uses an AI-generated "with children" photo and placeholder (hidden) testimonials. Recommend `noindex` until the client approves.
2. **Site-wide, not this page**: ~120 requests / 1.1 MB, 60+ plugin scripts and stylesheets (Astra, Spectra, accessibility plugin, etc.); LCP ~3.7-4 s in the test environment; JS error `Cannot access 'n' before initialization` also appears on the homepage.
3. `<html lang>` in raw HTML is `en-US` (site locale); JS switches it to `he-IL`. Fix in WP language settings.
4. Portrait / photo resolution is low (605px wide source).
5. `wa.link` could not be opened from the sandbox (proxy blocks it): verify manually.
6. No contact form: WhatsApp is the only conversion path.
