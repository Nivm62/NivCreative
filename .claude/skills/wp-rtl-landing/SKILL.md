---
name: wp-rtl-landing
description: Workflow for building WordPress + Elementor / custom-HTML landing pages in Hebrew (RTL) on nivcreative.com via the REST API. Use when asked to build, redesign, update or audit a landing page, or when the user says "build the page", "עמוד נחיתה", "אלמנטור", "RTL".
---

# WordPress + Elementor RTL landing-page workflow

Work is in Hebrew, RTL. Before starting, check which skills are available (names may carry the `audit-suite:` or `anthropic-skills:` prefix). If a skill below is missing, skip it and tell the user.

## Stage 1 – plan and design (before any code)
- `artifact-design`: design principles, typography, spacing, colour, layout.
- `audit-suite:emil-design-engineering`: polished UI details, forms, mobile and touch.
- `audit-suite:make-interfaces-feel-better`: shadows, radii, micro-interactions, small details.
- `audit-suite:web-animation-design`: animation, easing, marquee, scroll reveal, `prefers-reduced-motion`.
- `artifact-diagramming`, `dataviz`: only when diagrams, charts or dashboards are needed.

## Stage 2 – build
- `run`: launch the page/site and confirm the change works in practice.
- `built-in-browser` or `chrome-browser` if available for browser checks; otherwise Playwright screenshots at 390px (mobile) and 1440px (desktop).

## Stage 3 – review and tests (after the build)
- `audit-suite:web-design-guidelines`: web interface guidelines.
- `audit-suite:audit-as-a11y-eng`: contrast, keyboard, ARIA, screen readers.
- `audit-suite:audit-as-design-eng`: design consistency.
- `audit-suite:audit-as-ux-eng`: user flows and error states.
- `audit-suite:audit-as-frontend-eng`: HTML / CSS / JS quality.
- `audit-suite:audit-as-perf-eng`: performance, image/video weight, loading.
- `audit-suite:audit-as-prod-readiness`: production readiness (cache, SEO, forms, noindex).
- `audit-suite:full-audit`: only at the very end and only if the user asks.
- `code-review`, `simplify`, `security-review`: on the code before commit.

## Working rules
- Work directly through the site REST API (`https://nivcreative.com/wp-json`); authentication is injected by the proxy. Never hard-code secrets.
- Do NOT change existing sites or pages without explicit approval.
- New pages are created as **drafts**. Before publishing, check the real render (temporary publish with a password), then return the page to draft.
- After every update clear the Elementor cache: `DELETE /wp-json/elementor/v1/cache`.
- After every change check mobile and desktop: no horizontal scroll, exactly one H1, no new console errors.
- CSS uses a unique scope class (e.g. `.bracha-landing`); Hebrew content gets `dir="rtl"`.
- Elementor gotchas learned here: container classes use the `css_classes` setting (widgets use `_css_classes`); only the root container may be `isInner: false`, otherwise Elementor's lazy-load rule hides descendant `background-image`; Elementor-generated CSS overrides plain CSS, so use `!important` or higher specificity; absolutely positioned elements should be given the class on the widget wrapper, not on markup inside an HTML widget; `::before`/`::after` on containers are used by Elementor, so use child widgets for decoration; native entrance animations use `animation` (containers) / `_animation` (widgets); mobile backgrounds use the `_mobile` suffix settings.
- Show the user before/after screenshots (send the files).
- At the end commit and push to the session's branch. Do not open a PR unless asked.
