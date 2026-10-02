#!/usr/bin/env python3
"""Generates nivcreative-home-elementor.json (Elementor Pro, RTL, Flexbox Containers)
from the Stitch export (code.html + DESIGN.md "Obsidian Cyber Luxe")."""
import json
import random

random.seed(62)

# ---------- design tokens (DESIGN.md / tailwind config) ----------
C = dict(
    bg="#111318", lowest="#0c0e13", low="#1a1b21", cont="#1e1f25", high="#282a2f",
    highest="#33353a", text="#e2e2e9", muted="#bbc9cf", cyan="#00d2ff", primary="#a5e7ff",
    violet="#e0b6ff", tert="#e4d6ff", tert_c="#ccb5ff", fixed_dim="#47d6ff",
    on_primary="#003543", dark="#0c0e13",
)
FONT = "Plus Jakarta Sans"
MONO = "JetBrains Mono"
IMG = {
    "hero": "https://lh3.googleusercontent.com/aida-public/AB6AXuD1U5V5xhkPF_qRip6_B6yKCjHZAKgI_Mf_aMl39X6KS1Fkay_iIzi4HBQ0v0U0L-s0o54xlYpoCM6ICBXJLutPflMy6I833Q5HiDw1XKvcTVjfaXIlNgVvktH0BcXbKmgjg-tZoddaJmKSKFUb-Svt9sGcF3gUTFs3zL9ItKAUAOAicK2o8vL0sdl1B9_186NPSey153mEc6YeHhrXstrep36elO8CYeNoW37Il5Iaj1CgNorijj6PIQ",
    "cyber": "https://lh3.googleusercontent.com/aida-public/AB6AXuCmZNQOEVTrCBn28gp6jUoYSnYOnERir5ZdYQ67bDfG3gZZ-aainZL2fQZwQkCoiA55UPm3YxD-UiRsLWRCmLBd-yiQ30e90-Kz7F3he08PwSgJpQbVC_su_UhmxyvmE4N_zSDu4et5SJisa05K0z2lTb62WhN13rjD-ZnlwSjlyPWKmdCx-Ryc4tOP3ECzDPMZtRZ8l5utYNlW-3W2OXbUG7EsyUpWEB09Twbg5kC5DGgJAS4h2N72gw",
    "lumina": "https://lh3.googleusercontent.com/aida-public/AB6AXuCUEbl83DBIID92qM8lIAdiyuNlAnA2YbroBs1jdbpBtSLNfSzTxGH4BkT8m4xUPnSaxNzobV_9nfmAnxIwXNeGWJOsriF4Ol2W3hag0VGtC2iB4D4LMxLHemJZ6oQho0NqvTY5zC1y5zVfHlYXpXUQLNu0xk-rG-OXcaN-UuSanc1uRhUCoPD9Yja_F3j3olQXVXkwgz75Ft9_PbS3npW0miS6opxCtYHgJCtHvULDOj9JHuFf7NfICA",
    "aura": "https://lh3.googleusercontent.com/aida-public/AB6AXuDJ50a-4oMzjBTzAoZL67TOW8dH7yEfrF4JpsIwom6bSpt0fvCJJAZHXjyHQHCu-JxXAnROa1UXW6ZS5b3RuGJQzWoJ3r_LImiU2eMri-fK0eai4Fvc-FVpGOT9uzSsCy6WP1vrODbEdkN8UZpD3Omgw0gJRFr0x-uUjzeGk7cuFWgZY4u7BJwSwpg7tXzQk1ZrUtCF8KyzBCHxNuGlQi9YqAkU0D1yiabN0x3CL7mCnq5YFb5cNoqRLQ",
    "nexus": "https://lh3.googleusercontent.com/aida-public/AB6AXuC4noGujoMHpoPqmo0Bw2vgugWGTVoZrdeQ0f3478DvNA7kt4fxBm2Fm2zd84vG_DfGcYdmiLQKvVvftN2FWYxZ1kdc-qY7nBxjnchY9tVYs-wWu_NzHeW9zGzlzHHLACYwGIJ_Ab6TvhrXY_5bm53me0SBDa562VojmWudJrwbdxcQNzjTzg16JTGlZS1bAUqmOC-v7kKU-ljJsQd62JE0rKl0AdideT6tegmq-ILLaYcDqh3gUNnE-A",
    "avatar": "https://lh3.googleusercontent.com/aida/AEtjO1UngTfu_UE4QHDd8SB_LjIQ6Yz05zSPBDXAsdRZ6PBq8_oq5_reBk13O-8eVJURhyLz0rvJVxytasJMPZBiyGRB6PHvEMnnnQTLMh-442LnNV3-7KEBUyxx2rl92CDe-quC0UqtebkD57bpBpbLPlRy5dxVONq3DSpCwmv5apefSdAHXH5OQPC5s0M1I9xlwVJgZ_ayfCSiI7IXszf9uxhU4bbjnZbhAu0th5VI0nxbHpsyqYiiZaaObY8HVwvKw47JU61FyuRXi-o",
}


# ---------- helpers ----------
def uid():
    return "%07x" % random.getrandbits(28)


def dim(t, r=None, b=None, l=None, unit="px"):
    if r is None:
        r = b = l = t
    linked = t == r == b == l
    return {"unit": unit, "top": str(t), "right": str(r), "bottom": str(b), "left": str(l), "isLinked": linked}


def sz(n, unit="px"):
    return {"unit": unit, "size": n, "sizes": []}


def gap(n):
    return {"column": str(n), "row": str(n), "isLinked": True, "unit": "px"}


def typo(size, weight, family=FONT, lh=None, ls=0, msize=None):
    s = {
        "typography_typography": "custom",
        "typography_font_family": family,
        "typography_font_size": sz(size),
        "typography_font_weight": str(weight),
        "typography_letter_spacing": sz(ls),
    }
    if lh:
        s["typography_line_height"] = sz(lh)
    if msize:
        s["typography_font_size_mobile"] = sz(msize)
        if lh:
            s["typography_line_height_mobile"] = sz(round(lh * msize / size))
    return s


def el(widget, settings, cls=""):
    if cls:
        settings = dict(settings, css_classes=cls)
    return {"id": uid(), "elType": "widget", "widgetType": widget, "settings": settings, "elements": []}


def box(children, cls="", direction="column", inner=True, **kw):
    s = {
        "content_width": "full",
        "flex_direction": direction,
        "flex_gap": gap(kw.pop("g", 0)),
    }
    if direction == "column":
        s["flex_direction_mobile"] = "column"
    if kw.pop("wrap", False):
        s["flex_wrap"] = "wrap"
    if "justify" in kw:
        s["flex_justify_content"] = kw.pop("justify")
    if "align" in kw:
        s["flex_align_items"] = kw.pop("align")
    if "bg" in kw:
        s["background_background"] = "classic"
        s["background_color"] = kw.pop("bg")
    if "img" in kw:
        s["background_background"] = "classic"
        s["background_image"] = {"url": kw.pop("img"), "id": "", "size": ""}
        s["background_position"] = "center center"
        s["background_size"] = "cover"
        s["background_repeat"] = "no-repeat"
    if "pad" in kw:
        s["padding"] = kw.pop("pad")
    if "pad_m" in kw:
        s["padding_mobile"] = kw.pop("pad_m")
    if "radius" in kw:
        s["border_radius"] = dim(kw.pop("radius"))
    if "minh" in kw:
        s["min_height"] = sz(kw.pop("minh"))
    if "width" in kw:
        s["width"] = {"unit": "%", "size": kw.pop("width")}
    if "eid" in kw:
        s["_element_id"] = kw.pop("eid")
    s.update(kw)
    if cls:
        s["css_classes"] = cls
    return {"id": uid(), "elType": "container", "settings": s, "elements": children, "isInner": inner}


def heading(text, size, weight, color, tag="h2", align=None, family=FONT, lh=None, msize=None, cls="", ls=0):
    s = {"title": text, "header_size": tag, "title_color": color}
    s.update(typo(size, weight, family, lh, ls, msize))
    if align:
        s["align"] = align
        s["align_mobile"] = align
    return el("heading", s, cls)


def text(html, size, color, weight=400, lh=None, align=None, family=FONT, cls="", msize=None):
    s = {"editor": html, "text_color": color}
    s.update(typo(size, weight, family, lh, 0, msize))
    if align:
        s["align"] = align
    return el("text-editor", s, cls)


def mono(html, color=C["primary"], size=12, cls="", align=None):
    return text(html, size, color, 500, 16, align, MONO, "niv-mono " + cls)


def html(code, cls=""):
    return el("html", {"html": code}, cls)


def icon(name, color, size, lib="fa-solid"):
    return el("icon", {
        "selected_icon": {"value": name, "library": lib},
        "primary_color": color, "size": sz(size), "view": "default", "align": "center",
    })


def button(label, url, grad=True, size=18, pad=(0, 40), height=56, icon_name=None, cls="", full=False):
    s = {
        "text": label, "link": {"url": url, "is_external": "", "nofollow": ""},
        "button_text_color": C["dark"] if grad else C["text"],
        "border_radius": dim(12), "text_padding": dim(0, pad[1], 0, pad[1]),
        "align_mobile": "center",
    }
    s.update(typo(size, 700 if grad else 500))
    if grad:
        s.update({
            "background_background": "gradient", "background_color": C["cyan"],
            "background_color_b": C["violet"], "background_gradient_type": "linear",
            "background_gradient_angle": {"unit": "deg", "size": 135, "sizes": []},
            "box_shadow_box_shadow_type": "yes",
            "box_shadow_box_shadow": {"horizontal": 0, "vertical": 0, "blur": 35, "spread": 0, "color": "rgba(0,210,255,0.45)"},
        })
    else:
        s.update({"background_background": "classic", "background_color": "rgba(51,53,58,0.6)"})
    if icon_name:
        s["selected_icon"] = {"value": icon_name, "library": "fa-solid"}
        s["icon_align"] = "right"
        s["icon_indent"] = sz(8)
    if full:
        s["align"] = "stretch"
    return el("button", s, "niv-btn " + cls)


def chips(items, color):
    return html("".join('<span class="niv-chip" style="color:%s">%s</span>' % (color, i) for i in items), "niv-chips")


# ---------- custom CSS (glass / glow / grids / header / RTL) ----------
CSS = """
@import url('https://fonts.googleapis.com/css2?family=Heebo:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');
.niv-root,.niv-root *{box-sizing:border-box}
.niv-root{direction:rtl;text-align:right;position:relative;overflow:hidden;font-family:"Plus Jakarta Sans","Heebo","Assistant",sans-serif;
  background-color:#111318;
  background-image:radial-gradient(600px 600px at 75% 0%,rgba(0,210,255,.10),transparent 70%),
  radial-gradient(500px 500px at 6% 14%,rgba(109,17,173,.20),transparent 70%),
  radial-gradient(550px 550px at 94% 42%,rgba(165,231,255,.10),transparent 70%)}
.niv-root h1,.niv-root h2,.niv-root h3,.niv-root h4,.niv-root p,.niv-root .elementor-widget-container{font-family:"Plus Jakarta Sans","Heebo","Assistant",sans-serif}
.niv-root .niv-mono,.niv-root .niv-mono *{font-family:"JetBrains Mono","Heebo",monospace!important;letter-spacing:.08em}
.niv-ltr,.niv-ltr *{direction:ltr!important;unicode-bidi:isolate}
.niv-root p{margin:0}
.niv-wrap{width:100%;max-width:1280px;margin-inline:auto;padding-inline:64px}
@media(max-width:767px){.niv-wrap{padding-inline:20px}}
/* header */
.niv-header{position:fixed!important;top:0;inset-inline:0;z-index:999;background:rgba(12,14,19,.8)!important;backdrop-filter:blur(24px);-webkit-backdrop-filter:blur(24px);box-shadow:0 1px 8px rgba(0,0,0,.4);min-height:80px;justify-content:center}
.niv-main{padding-top:80px}
.niv-nav{display:flex;gap:8px;background:rgba(26,27,33,.7);padding:4px 16px;border-radius:9999px;backdrop-filter:blur(12px)}
.niv-nav a{padding:4px 16px;border-radius:8px;color:#bbc9cf;font-size:14px;font-weight:600;text-decoration:none;transition:color .2s}
.niv-nav a:hover{color:#e2e2e9}
.niv-nav a.active{background:#282a2f;color:#a5e7ff;border-radius:12px}
@media(max-width:1199px){.niv-nav{display:none}}
.niv-lang{display:inline-flex;background:#282a2f;padding:4px;border-radius:9999px;gap:0}
.niv-lang span{padding:2px 8px;border-radius:9999px;font-size:12px;color:#bbc9cf}
.niv-lang span.on{background:#33353a;color:#a5e7ff}
@media(max-width:639px){.niv-lang{display:none}}
.niv-pulse{display:inline-block;width:6px;height:6px;border-radius:50%;background:#00d2ff;animation:nivpulse 2s infinite}
@keyframes nivpulse{0%,100%{opacity:1}50%{opacity:.4}}
/* gradient text / badge */
.niv-grad-text{background:linear-gradient(90deg,#00d2ff,#a5e7ff,#e0b6ff);-webkit-background-clip:text;background-clip:text;color:transparent;-webkit-text-fill-color:transparent;filter:drop-shadow(0 4px 24px rgba(0,210,255,.3))}
.niv-badge{display:inline-flex;align-items:center;gap:8px;padding:6px 16px;border-radius:9999px;background:rgba(40,42,47,.8);backdrop-filter:blur(20px);box-shadow:0 0 20px rgba(0,210,255,.2);color:#a5e7ff;font-size:12px}
.niv-dot{width:8px;height:8px;border-radius:50%;background:#00d2ff;box-shadow:0 0 8px #00d2ff;display:inline-block}
/* glass + cards */
.niv-glass{background:rgba(26,27,33,.7)!important;backdrop-filter:blur(24px);-webkit-backdrop-filter:blur(24px);box-shadow:0 8px 32px rgba(0,0,0,.45)}
.niv-card{transition:all .3s;box-shadow:0 10px 30px rgba(0,0,0,.35)}
.niv-card:hover{background:#1e1f25!important;box-shadow:0 0 30px rgba(0,210,255,.25)}
.niv-card.v:hover{box-shadow:0 0 30px rgba(224,182,255,.25)}
.niv-card.t:hover{box-shadow:0 0 30px rgba(204,181,255,.25)}
.niv-card.f:hover{box-shadow:0 0 30px rgba(71,214,255,.25)}
.niv-card:hover h3{color:#a5e7ff}
.niv-icon-box{width:56px;height:56px;border-radius:12px;background:#282a2f;display:flex;align-items:center;justify-content:center}
.niv-chips{display:flex;flex-wrap:wrap;gap:8px;padding-top:12px;margin-top:12px;border-top:1px solid #33353a}
.niv-chip{padding:4px 8px;border-radius:6px;background:#282a2f;font-family:"JetBrains Mono",monospace;font-size:12px;letter-spacing:.08em}
.niv-tag{background:rgba(12,14,19,.8);backdrop-filter:blur(12px);padding:4px 8px;border-radius:6px;font-family:"JetBrains Mono",monospace;font-size:12px;letter-spacing:.08em;display:inline-block}
.niv-pill{padding:4px 16px;border-radius:12px;font-weight:700;font-size:20px;display:inline-block;box-shadow:0 8px 20px rgba(0,0,0,.4)}
.niv-shot{position:relative;overflow:hidden}
.niv-shot::before{content:"";position:absolute;inset:0;background:linear-gradient(to top,#1a1b21,transparent 60%);opacity:.8;pointer-events:none}
.niv-shot>*{position:relative;z-index:1}
.niv-bar{height:8px;border-radius:9999px;background:#33353a;overflow:hidden}
.niv-bar i{display:block;height:100%;width:94%;border-radius:9999px;background:linear-gradient(90deg,#00d2ff,#a5e7ff)}
.niv-code{background:#0c0e13;border-radius:12px;padding:16px;font-family:"JetBrains Mono",monospace;font-size:12px;line-height:1.7;direction:ltr;text-align:left;color:#bbc9cf;box-shadow:inset 0 2px 8px rgba(0,0,0,.5)}
.niv-code b{font-weight:500}
.niv-glow-wrap{position:relative}
.niv-glow-wrap::before{content:"";position:absolute;inset:-4px;border-radius:28px;background:linear-gradient(90deg,rgba(0,210,255,.3),rgba(109,17,173,.2),rgba(165,231,255,.25));filter:blur(24px);opacity:.7;pointer-events:none}
.niv-glow-wrap>*{position:relative}
.niv-topline{position:absolute;top:0;inset-inline:0;height:4px;background:linear-gradient(90deg,#00d2ff,#e0b6ff,#00d2ff)}
.niv-num{width:48px;height:48px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-family:"JetBrains Mono",monospace;font-weight:700;font-size:12px}
.niv-stars{color:#00d2ff;letter-spacing:4px;font-size:20px}
.niv-avatar{width:48px;height:48px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700}
.niv-eyebrow{display:flex;align-items:center;gap:8px}
.niv-eyebrow::before{content:"";width:32px;height:2px;background:#00d2ff;display:inline-block}
.niv-center .niv-eyebrow::before{display:none}
/* grids */
.niv-grid-2,.niv-grid-3,.niv-grid-4{display:grid!important;gap:24px}
.niv-grid-2{grid-template-columns:repeat(2,1fr)}
.niv-grid-3{grid-template-columns:repeat(3,1fr)}
.niv-grid-4{grid-template-columns:repeat(4,1fr)}
.niv-grid-2.big{gap:40px}
@media(max-width:1023px){.niv-grid-4{grid-template-columns:repeat(2,1fr)}.niv-grid-3{grid-template-columns:1fr}}
@media(max-width:767px){.niv-grid-2,.niv-grid-3,.niv-grid-4{grid-template-columns:1fr}.niv-metrics.niv-grid-4{grid-template-columns:repeat(2,1fr)}}
.niv-split-8-4{display:grid!important;grid-template-columns:2fr 1fr;gap:24px;align-items:center}
.niv-split-5-7{display:grid!important;grid-template-columns:5fr 7fr;gap:64px;align-items:center}
.niv-split-7-5{display:grid!important;grid-template-columns:7fr 5fr;gap:64px;align-items:center}
@media(max-width:1023px){.niv-split-8-4,.niv-split-5-7,.niv-split-7-5{grid-template-columns:1fr;gap:32px}}
.niv-footer-grid{display:grid!important;grid-template-columns:4fr 2fr 3fr 3fr;gap:24px}
@media(max-width:1023px){.niv-footer-grid{grid-template-columns:1fr 1fr}}
@media(max-width:767px){.niv-footer-grid{grid-template-columns:1fr}}
/* forms (Elementor Pro form widget) */
.niv-form .elementor-field-label{color:#e2e2e9;font-size:14px;font-weight:500}
.niv-form .elementor-field-textual{background:#1e1f25!important;border:0!important;border-radius:12px!important;padding:12px 16px!important;color:#e2e2e9!important}
.niv-form .elementor-field-textual::placeholder{color:rgba(187,201,207,.4)}
.niv-form .elementor-field-textual:focus{box-shadow:0 0 0 2px #00d2ff!important;outline:none}
.niv-form .elementor-button{height:56px;border-radius:12px;background:linear-gradient(135deg,#00d2ff,#e0b6ff)!important;color:#0c0e13!important;font-weight:700;font-size:20px;box-shadow:0 0 25px rgba(0,210,255,.4);transition:all .3s}
.niv-form .elementor-button:hover{box-shadow:0 0 35px rgba(0,210,255,.6);transform:scale(1.01)}
.niv-form .elementor-message-success{color:#a5e7ff;background:rgba(0,210,255,.2);padding:12px;border-radius:12px;text-align:center}
/* buttons hover */
.niv-btn .elementor-button{transition:all .3s}
.niv-btn .elementor-button:hover{transform:scale(1.02);box-shadow:0 0 50px rgba(0,210,255,.7)}
.niv-social a{width:40px;height:40px;border-radius:12px;background:#1a1b21;display:inline-flex;align-items:center;justify-content:center;color:#bbc9cf;text-decoration:none;transition:all .2s}
.niv-social a:hover{background:#282a2f;color:#a5e7ff}
.niv-footer a.l{color:#bbc9cf;text-decoration:none;font-size:14px;display:block;margin-bottom:8px}
.niv-footer a.l:hover{color:#e2e2e9}
.niv-glow-1,.niv-glow-2{pointer-events:none}
"""

# ---------- sections ----------
def header():
    logo = box([
        el("image", {"image": {"url": IMG["avatar"], "id": ""}, "image_size": "full",
                      "width": sz(32), "height": sz(32), "object_fit": "cover",
                      "image_border_radius": dim(50, unit="%")}),
        box([
            box([
                heading("NivCreative", 20, 700, C["text"], "div", lh=28),
                html('<span class="niv-pulse"></span>'),
            ], direction="row", g=4, align="center"),
            mono("DIGITAL CRAFT &amp; IDENTITY", C["primary"], 12, "niv-ltr"),
        ], g=0),
    ], direction="row", g=16, align="center")
    nav = html('''<nav class="niv-nav"><a class="active" href="#top">דף הבית</a><a href="#services">שירותים</a><a href="#portfolio-section">תיק עבודות</a><a href="#process">השיטה שלנו</a><a href="#testimonials">לקוחות ממליצים</a><a href="#about">אודות</a></nav>''')
    right = box([
        html('<div class="niv-lang niv-mono"><span class="on">עב</span><span>EN</span></div>'),
        button("בואו נדבר תכל'ס", "#contact-flow", size=20, pad=(0, 24), height=48, cls="niv-btn"),
    ], direction="row", g=8, align="center")
    inner = box([logo, nav, right], "niv-wrap", direction="row", justify="space-between", align="center", g=24)
    return box([inner], "niv-header", inner=False, eid="top")


def hero():
    badge = html('<div class="niv-badge niv-mono"><span class="niv-dot"></span><span>✦ העידן הבא של נוכחות דיגיטלית לעסקים</span></div>')
    h1 = heading('אנחנו לא רק בונים אתרים.<br>'
                 '<span class="niv-grad-text">אנחנו מעצבים את העתיד</span> של המותג שלך.',
                 72, 800, C["text"], "h1", "center", lh=80, msize=40, cls="niv-h1")
    sub = text("סטודיו בוטיק מתקדם לעיצוב אתרי פרימיום, דפי נחיתה ממירים במיוחד והקמת מותגים בלתי נשכחים באינטרנט. "
               "שילוב מדויק בין אסתטיקה עתידנית, ארכיטקטורת נתונים ויחסי המרה מקסימליים.",
               18, C["muted"], 400, 29, "center", msize=16)
    sub["settings"]["_element_width"] = "initial"
    sub["settings"]["_element_custom_width"] = sz(768)
    ctas = box([
        button("בואו נבנה משהו יוצא דופן", "#contact-flow", icon_name="fas fa-arrow-left"),
        button("צפו בפרויקטים נבחרים", "#portfolio-section", grad=False, size=20, pad=(0, 32), icon_name="fas fa-eye"),
    ], direction="row", g=16, justify="center", align="center", wrap=True)

    def metric(num, label, color):
        return box([
            heading(num, 48, 800, color, "div", "center", lh=56, msize=32, cls="niv-ltr"),
            text(label, 14, C["muted"], 600, 20, "center"),
        ], g=4, align="center", pad=dim(8))

    metrics = box([
        metric("99.4%", "שביעות רצון לקוחות", C["cyan"]),
        metric("+120", "פרויקטים חיים ברשת", C["violet"]),
        metric("x3.4", "עלייה ממוצעת בהמרות", C["primary"]),
        metric("99+", "מהירות טעינה PageSpeed", C["tert_c"]),
    ], "niv-grid-4 niv-glass niv-metrics", pad=dim(24), radius=16)

    # showcase
    shot = box([
        html('<span class="niv-tag" style="color:#a5e7ff;align-self:flex-start;direction:ltr">● RENDER: 60FPS WEBLIGHT</span>'),
        html('<div style="display:flex;align-items:center;gap:16px;background:rgba(51,53,58,.9);backdrop-filter:blur(20px);padding:16px;border-radius:12px;width:fit-content;box-shadow:0 20px 40px rgba(0,0,0,.5)">'
             '<div style="width:48px;height:48px;border-radius:12px;background:rgba(0,210,255,.2);display:flex;align-items:center;justify-content:center;color:#00d2ff"><i class="fas fa-arrow-trend-up"></i></div>'
             '<div><div class="niv-mono" style="color:#a5e7ff;font-size:12px">צמיחה אורגנית מדודה</div>'
             '<div class="niv-ltr" style="color:#e2e2e9;font-weight:700;font-size:20px">+340% Conversions</div></div></div>'),
    ], "niv-shot", justify="space-between", img=IMG["hero"], minh=384, radius=12, pad=dim(16))
    side = box([
        html('<div style="background:rgba(40,42,47,.6);padding:16px;border-radius:12px"><div style="display:flex;justify-content:space-between;margin-bottom:8px">'
             '<span style="color:#e2e2e9;font-weight:600;font-size:14px">זמן תגובה ממוצע ללקוח</span><span class="niv-mono niv-ltr" style="color:#a5e7ff">&lt; 0.28s</span></div>'
             '<div class="niv-bar"><i></i></div></div>'),
        html('<div class="niv-code"><div style="color:#e0b6ff;opacity:.75;font-weight:600">// Arch Engine: NextGen</div>'
             '<div style="color:#e2e2e9"><span style="color:#00d2ff">const</span> studio = <span style="color:#e4d6ff">createIdentity</span>({</div>'
             '<div style="padding-left:16px">speed: <span style="color:#a5e7ff">"HyperLight"</span>,</div>'
             '<div style="padding-left:16px">conversionOptimized: <span style="color:#00d2ff">true</span>,</div>'
             '<div style="padding-left:16px">aestheticRating: <span style="color:#e0b6ff">99.9</span></div>'
             '<div style="color:#e2e2e9">});</div>'
             '<div style="color:#a5e7ff;margin-top:4px">✓ Ready for hyper-scale deployment</div></div>'),
        html('<div style="background:rgba(40,42,47,.6);padding:16px;border-radius:12px;display:flex;align-items:center;gap:16px">'
             '<div style="width:40px;height:40px;border-radius:50%;background:rgba(109,17,173,.4);display:flex;align-items:center;justify-content:center;color:#e0b6ff"><i class="fas fa-certificate"></i></div>'
             '<div><div style="color:#e2e2e9;font-weight:700;font-size:14px">אחריות תוצאה מלאה</div>'
             '<div style="color:#bbc9cf;font-size:14px">מתודולוגיית עיצוב שנבחנה על מיליוני כניסות</div></div></div>'),
    ], g=24)
    topbar = html('<div style="height:48px;background:#1e1f25;padding:0 16px;display:flex;align-items:center;justify-content:space-between">'
                  '<div style="display:flex;align-items:center;gap:8px"><i style="width:12px;height:12px;border-radius:50%;background:rgba(255,180,171,.8)"></i>'
                  '<i style="width:12px;height:12px;border-radius:50%;background:rgba(224,182,255,.8)"></i><i style="width:12px;height:12px;border-radius:50%;background:rgba(0,210,255,.8)"></i>'
                  '<span class="niv-mono niv-ltr" style="color:#bbc9cf;margin-right:12px">NivCreative_OS :: v4.9.2-ultra</span></div>'
                  '<div style="display:flex;gap:16px;align-items:center"><span class="niv-mono" style="color:#a5e7ff;background:#282a2f;padding:2px 8px;border-radius:9999px">● CORE_ACTIVE</span>'
                  '<span class="niv-mono niv-ltr" style="color:#bbc9cf">https://matrix.nivcreative.com</span></div></div>')
    shell = box([topbar, box([shot, side], "niv-split-8-4", direction="row", pad=dim(40), pad_m=dim(16))],
                bg="rgba(12,14,19,.9)", radius=16)
    shell["settings"]["css_classes"] = ""
    showcase = box([shell], "niv-glow-wrap")

    return box([badge, h1, sub, ctas, metrics, showcase], "niv-wrap", g=24, align="center",
               pad=dim(48, 64, 128, 64), pad_m=dim(48, 20, 96, 20))


def section_head(eyebrow, title, desc=None, center=True, eyebrow_color=C["primary"]):
    kids = [mono(eyebrow, eyebrow_color, 12, "niv-eyebrow" if not center else "", "center" if center else None),
            heading(title, 48, 700, C["text"], "h2", "center" if center else None, lh=56, msize=32)]
    if desc:
        kids.append(text(desc, 16, C["muted"], 400, 24, "center" if center else None))
    return box(kids, "niv-center" if center else "", g=8, align="center" if center else "stretch")


def services():
    head = box([
        box([
            mono("שירותי הליבה שלנו // Core Matrix", C["primary"], 12, "niv-eyebrow"),
            heading('פתרונות דיגיטליים שנבנו במיוחד כדי <span style="color:#00d2ff">לשבור שיאי מכירה</span>',
                    48, 700, C["text"], "h2", lh=56, msize=32),
        ], g=8, width=60),
        box([text("אנחנו לא מאמינים בתבניות גנריות. כל מערכת, דף נחיתה ומותג נבנים במלאכת מחשבת מדעית ומדויקת להשגת תשואה גבוהה.",
                  16, C["muted"], 400, 24)], width=35),
    ], direction="row", justify="space-between", align="end", g=24, wrap=True)

    def card(ic, color, hover, num, title, desc, tags):
        top = box([
            html('<div class="niv-icon-box" style="color:%s"><i class="%s" style="font-size:24px"></i></div>' % (color, ic)),
            mono(num, "rgba(187,201,207,.6)", 12, "niv-ltr"),
        ], direction="row", justify="space-between", align="start")
        return box([top, heading(title, 24, 700, C["text"], "h3", lh=32), text(desc, 16, C["muted"], 400, 26),
                    chips(tags, color)],
                   "niv-card " + hover, g=16, bg=C["low"], radius=16, pad=dim(40), pad_m=dim(24))

    grid = box([
        card("fas fa-laptop-code", C["cyan"], "", "01 // IDENTITY WEB", "עיצוב ובניית אתרי תדמית &amp; Web Apps",
             "אתרי בוטיק מותאמים אישית מאפס, אנימציות חלקות ברמת WebGL, חוויית משתמש UI/UX פורצת דרך שמשדרת יוקרה וביטחון בלתי מעורער בכל גלילה.",
             ["React / Next.js", "Micro-Interactions", "Bespoke Design"]),
        card("fas fa-bolt", C["violet"], "v", "02 // FUNNELS &amp; CRO", "דפי נחיתה עתירי המרה (High-Converting)",
             "מבנה פסיכולוגי מוכח להנעה מהירה לפעולה, כתיבת מיקרו-קופי חכם, אופטימיזציית מובייל קפדנית, ובדיקות מהירות קיצוניות שהופכות קליקים ללקוחות משלמים.",
             ["A/B Testing", "Sales Psychology", "99 Score Tech"]),
        card("fas fa-wand-magic-sparkles", C["tert_c"], "t", "03 // BRAND COUTURE", "מיתוג דיגיטלי ואסטרטגיית מותג יוקרתי",
             "גיבוש זהות חזותית שלמה, לוגוטייפים חדשניים, פלטות צבעים עתידניות, ספרי מותג דיגיטליים ומערכות עיצוב (Design Systems) לסקייל בינלאומי.",
             ["Brand Books", "Vector Systems", "Omnichannel"]),
        card("fas fa-chart-line", C["fixed_dim"], "f", "04 // REVAMP &amp; OPTIMIZATION", "שדרוג ושיפור יחסי המרה (CRO &amp; Web Revamp)",
             "הפיכת אתרים קיימים ומיושנים למכונות מכירה מלוטשות ומהירות כברק. ניתוח מפות חום, זיהוי צווארי בקבוק ונטישה, וביצוע קפיצה משמעותית ברווחיות.",
             ["Heatmap Analytics", "Speed Turbo", "UX Overhaul"]),
    ], "niv-grid-2")
    return box([head, grid], "niv-wrap", g=64, eid="services", pad=dim(96, 64), pad_m=dim(64, 20))


def portfolio():
    def proj(img, tag, tag_color, pill, pill_bg, pill_fg, title, hover_color, desc, stack):
        shot = box([
            html('<span class="niv-tag" style="color:%s;align-self:flex-start">%s</span>' % (tag_color, tag)),
            html('<span class="niv-pill niv-ltr" style="background:%s;color:%s">%s</span>' % (pill_bg, pill_fg, pill)),
        ], "niv-shot", direction="column", justify="space-between", img=img, minh=320, pad=dim(16),
            **{"flex_align_items": "flex-start"})
        info = box([
            html('<div style="display:flex;justify-content:space-between;align-items:center"><h3 style="margin:0;font-size:24px;font-weight:700;color:#e2e2e9">%s</h3>'
                 '<i class="fas fa-arrow-left" style="color:#bbc9cf"></i></div>' % title),
            text(desc, 14, C["muted"], 400, 23),
            mono(" • ".join(stack), C["muted"], 12, "niv-ltr"),
        ], g=8, pad=dim(24))
        c = box([shot, info], "niv-card", bg=C["low"], radius=16)
        c["settings"]["overflow"] = "hidden"
        return c

    head = section_head("// תיק עבודות נבחר / CASE STUDIES", "פרויקטים שוברי שוק שמייצרים תוצאות מוחשיות",
                        "מבט מקרוב על מותגים ופלטפורמות שזכו לקפיצת מדרגה דיגיטלית דרך הסטודיו שלנו.", True, C["violet"])
    grid = box([
        proj(IMG["cyber"], "AI &amp; CLOUD SECURITY", C["primary"], "+280% Conversions", C["cyan"], "#00566a", "CyberPulse AI", C["primary"],
             "ארכיטקטורת מותג ופלטפורמת בינה מלאכותית מורכבת. פישוט המסרים, הטמעת אנימציות 3D אינטראקטיביות והקפצת ההמרות מגרסאות ניסיון למנויים שנתיים.",
             ["Next.js", "Tailwind v4", "WebGL Canvas"]),
        proj(IMG["lumina"], "FINTECH &amp; WEALTH", C["violet"], "$14.2M Volume", C["violet"], "#4c007d", "Lumina FinTech", C["violet"],
             "אתר תדמית ואפליקציית השקעות יוקרתית למשקיעי כשירים. שילוב בין אבטחה חסרת פשרות, תחושת עושר ויזואלי ומערכת onboarding חלקה ואינטואיטיבית.",
             ["Fintech UX", "React Native", "Custom Charts"]),
        proj(IMG["aura"], "LUXURY &amp; ARCHITECTURE", C["tert"], "Sold Out in 48h", C["tert_c"], "#583f91", "Aura Living Luxury", C["tert"],
             "דף נחיתה ומערכת הזמנות סגורה לפרויקט נדל״ן אדריכלי וסדנאות יוקרה. פסיכולוגיית מחסור ויוקרה שהביאה לסגירת כל המקומות תוך יומיים בלבד.",
             ["High-End E-Commerce", "Speed Engine", "Editorial"]),
        proj(IMG["nexus"], "ENTERPRISE SAAS", C["primary"], "100/100 PageSpeed", "#b6ebff", "#001f28", "NEXUS Cloud Infrastructure", C["primary"],
             "חידוש מלא של מותג תשתיות ענן בינלאומי. יצירת שפה ויזואלית חדה, תיעוד מפתחים אינטראקטיבי וקצב טעינה מקסימלי בכל נקודה בעולם.",
             ["SaaS Architecture", "TypeScript", "Tailored CRO"]),
    ], "niv-grid-2 big")
    return box([head, grid], "niv-wrap", g=64, eid="portfolio-section", pad=dim(96, 64), pad_m=dim(64, 20))


def process():
    def step(n, color, bg, title, en, desc):
        return box([
            html('<div class="niv-num" style="background:%s;color:%s">%s</div>' % (bg, color, n)),
            heading(title, 20, 700, C["text"], "h3", lh=28),
            mono(en, color, 12, "niv-ltr"),
            text(desc, 14, C["muted"], 400, 23),
        ], "niv-card", g=8, bg=C["low"], radius=16, pad=dim(24))

    head = section_head("מתודולוגיית העבודה // The Blueprint", "השיטה שלנו: איפה שמדע פוגש יצירתיות עתידנית",
                        "תהליך מובנה ומדויק בארבעה שלבים, המבטיח תוצאה יוצאת דופן ללא ניחושים או עיכובים מיותרים.")
    grid = box([
        step("01", C["cyan"], "rgba(0,210,255,.2)", "מחקר עומק ופיצוח אסטרטגי", "Data &amp; Discovery",
             "מיפוי קהל היעד, ניתוח מתחרים מעמיק, הבנת חסמי הרכישה והגדרת ה-USP הברור שיוביל את כל השפה הדיגיטלית."),
        step("02", C["violet"], "rgba(224,182,255,.2)", "ארכיטקטורת UX ואבטיפוס", "Architecture &amp; Wireframes",
             "תכנון מבנה מסכים אופטימלי להמרות, מסעות לקוח מדויקים (Funnels), וסקיצות אינטראקטיביות לבדיקת זרימת המשתמש."),
        step("03", C["tert"], "rgba(228,214,255,.2)", "עיצוב עתידני וקוד נקי", "Futuristic UI &amp; Clean Code",
             "הפיכת האבטיפוס ליצירת אמנות חזותית מוארת, פיתוח בקוד עדכני, רספונסיביות מלאה לכל מסך והטמעת מיקרו-אינטראקציות."),
        step("04", C["primary"], "rgba(182,235,255,.2)", "בדיקות המרה והשקה", "Launch &amp; CRO Scale",
             "אופטימיזציית מהירות אחרונה (100 PageSpeed), חיבור כלי מדידה מתקדמים, עלייה מבוקרת לאוויר והאצה של יחסי ההמרה."),
    ], "niv-grid-4")
    panel = box([html("", "niv-topline"), head, grid], "niv-glass", g=64, radius=24, pad=dim(64), pad_m=dim(24),
                bg="rgba(12,14,19,.8)")
    panel["settings"]["position"] = "relative"
    return box([panel], "niv-wrap", eid="process", pad=dim(96, 64), pad_m=dim(64, 20))


def why():
    left = box([
        mono("הערך המוסף שלנו // The Edge", C["cyan"], 12),
        heading("למה עסקים מובילים בוחרים ב-NivCreative?", 48, 800, C["text"], "h2", lh=56, msize=32),
        text("בעולם שבו כולם משתמשים באותן תבניות וורדפרס משעממות, אנחנו מעניקים לעסק שלך יתרון תחרותי לא הוגן – "
             "שילוב של יוקרה, מהירות שיא ויכולת מוכחת להמיר גולשים ללקוחות.", 16, C["muted"], 400, 26),
        html('<div style="background:rgba(40,42,47,.4);padding:24px;border-radius:16px;display:flex;align-items:center;gap:16px">'
             '<div style="width:48px;height:48px;border-radius:50%;background:rgba(0,210,255,.2);display:flex;align-items:center;justify-content:center;color:#00d2ff;flex-shrink:0"><i class="fas fa-brain"></i></div>'
             '<div><div style="color:#e2e2e9;font-weight:700;font-size:20px">חשיבה מוכוונת ROI</div>'
             '<div style="color:#bbc9cf;font-size:14px">אנחנו מודדים הצלחה במספרים, מכירות ולידים איכותיים.</div></div></div>'),
    ], g=16)

    def pillar(ic, color, title, desc):
        return box([
            html('<i class="%s" style="font-size:32px;color:%s"></i>' % (ic, color)),
            heading(title, 20, 700, C["text"], "h4", lh=28),
            text(desc, 14, C["muted"], 400, 22),
        ], "niv-card", g=4, bg=C["low"], radius=16, pad=dim(24))

    pillars = box([
        pillar("fas fa-terminal", C["cyan"], "טכנולוגיית קצה ללא פשרות", "פיתוח נקי ומודרני ללא תוספים מיותרים שמכבידים על האתר. מהירויות טעינה מיידיות שגוגל והלקוחות מעריצים."),
        pillar("fas fa-pen-nib", C["violet"], "עיצוב מקורי 100% מאפס", "אפס תבניות מוכנות מראש. כל פיקסל נתפר ומעוצב במיוחד עבור המותג שלך כדי לבדל אותך באופן מוחלט מהמתחרים."),
        pillar("fas fa-route", C["tert"], "התמקדות בתוצאות עסקיות", "יופי זה לא מספיק. אנחנו בונים מערכות שמניעות לפעולה באופן אלגנטי, מדויק ומשכנע בכל נקודת מגע."),
        pillar("fas fa-user-check", C["primary"], "ליווי אישי 1-על-1 ישיר", "עבודה צמודה מול מייסד הסטודיו. ללא אנשי מכירות באמצע, עם שקיפות מלאה, מהירות תגובה ומחויבות לתוצאה."),
    ], "niv-grid-2")
    return box([box([left, pillars], "niv-split-5-7", direction="row")], "niv-wrap", eid="about", pad=dim(96, 64), pad_m=dim(64, 20))


def testimonials():
    head = section_head("המלצות מהשטח // Client Stories", "מה אומרים השותפים לדרך?",
                        "יזמים, מנהלי שיווק ומנכ״לים שחוו את ההבדל המשמעותי של עבודה עם NivCreative.", True, C["cyan"])

    def review(color, bg, ini, quote, name, role):
        return box([
            box([html('<div class="niv-stars" style="color:%s">★★★★★</div>' % color),
                 text(quote, 16, C["text"], 500, 26)], g=16),
            html('<div style="display:flex;align-items:center;gap:16px;margin-top:24px;padding-top:16px;border-top:1px solid #33353a">'
                 '<div class="niv-avatar" style="background:%s;color:%s">%s</div>'
                 '<div><div style="color:#e2e2e9;font-weight:700;font-size:20px">%s</div>'
                 '<div style="color:#bbc9cf;font-size:14px;font-weight:600">%s</div></div></div>' % (bg, color, ini, name, role)),
        ], "niv-card", justify="space-between", bg="rgba(26,27,33,.9)", radius=16, pad=dim(40), pad_m=dim(24))

    grid = box([
        review(C["cyan"], "rgba(0,210,255,.2)", "אל", "״ניב לקח את דף הנחיתה המנומנם שלנו והפך אותו למפלצת מכירות אמיתית. ראינו עלייה של 310% בכמות הלידים הסגורים תוך פחות מחודש מההשקה!״", "אלון לוי", "סמנכ״ל צמיחה, CyberTech IL"),
        review(C["violet"], "rgba(109,17,173,.3)", "דש", "״הרמה האסתטית והעתידנית של האתר משכה אלינו לקוחות פרימיום בינלאומיים שקודם אפילו לא הסתכלו לכיווננו. השקעה שהחזירה את עצמה פי 10.״", "דנה שפירא", "מייסדת, Aura Living Design"),
        review(C["primary"], "rgba(204,181,255,.3)", "יו", "״המהירות, הדיוק וההבנה של הצרכים העסקיים פשוט לא קיימים בסוכנויות הרגילות. כל הערה קיבלה מענה מיידי, והתוצר הסופי פשוט מהפנט!״", "יונתן וקסלר", "סמנכ״ל מוצר, Lumina App"),
    ], "niv-grid-3")
    return box([head, grid], "niv-wrap", g=64, eid="testimonials", pad=dim(96, 64), pad_m=dim(64, 20))


def cta():
    left = box([
        html('<div class="niv-badge niv-mono" style="width:fit-content"><span class="niv-pulse"></span><span>פנויים לפרויקטים חדשים ברבעון הקרוב</span></div>'),
        heading('מוכנים להוביל את הענף שלכם?<br><span class="niv-grad-text">בואו נתאם שיחת אפיון ללא עלות.</span>',
                48, 800, C["text"], "h2", lh=56, msize=32),
        text("בשיחה קצרה בת 20 דקות ננתח את האתר או המותג הקיים שלך, נזהה הזדמנויות צמיחה בלתי מנוצלות ונתווה מפת דרכים ברורה לבניית נוכחות מנצחת.",
             16, C["muted"], 400, 26),
        html('<div style="display:flex;flex-wrap:wrap;gap:24px;margin-top:8px">'
             + "".join('<span style="color:#e2e2e9;font-size:14px;font-weight:600"><i class="fas fa-circle-check" style="color:#00d2ff;margin-inline-end:8px"></i>%s</span>' % t
                       for t in ["ללא התחייבות מראש", "התאמה אישית מלאה", "תשובה עד 24 שעות"]) + "</div>"),
    ], g=16)

    fields = [
        {"_id": "f1name", "custom_id": "name", "field_type": "text", "field_label": "שם מלא *", "placeholder": "למשל: יובל כהן", "required": "true", "width": "100"},
        {"_id": "f2phone", "custom_id": "phone", "field_type": "tel", "field_label": "טלפון נייד / וואטסאפ *", "placeholder": "050-000-0000", "required": "true", "width": "100"},
        {"_id": "f3goal", "custom_id": "goal", "field_type": "text", "field_label": "תחום העסק או מטרת הפרויקט", "placeholder": "למשל: סטארטאפ SaaS, דף נחיתה למכירות, מיתוג מחדש...", "required": "", "width": "100"},
    ]
    form = el("form", {
        "form_name": "NivCreative - שיחת אפיון", "form_fields": fields, "show_labels": "true", "mark_required": "",
        "button_text": "תיאום שיחת אפיון עכשיו", "button_width": "100", "button_size": "lg",
        "selected_button_icon": {"value": "fas fa-rocket", "library": "fa-solid"}, "button_icon_align": "row-reverse",
        "submit_actions": ["email"], "email_to": "hello@nivcreative.com",
        "email_subject": "ליד חדש מהאתר - NivCreative", "email_from_name": "NivCreative",
        "email_content": "[all-fields]", "success_message": "✓ הפרטים התקבלו בהצלחה! ניצור איתך קשר בהקדם לקביעת השיחה.",
        "label_color": C["text"], "field_text_color": C["text"], "field_background_color": C["cont"],
        "field_border_width": dim(0), "field_border_radius": dim(12),
        "button_background_color": C["cyan"], "button_text_color": C["dark"], "button_border_radius": dim(12),
        "row_gap": sz(16), "column_gap": sz(16),
    }, "niv-form")
    note = mono("🔒 הפרטים שלך שמורים ולא יועברו לאף צד שלישי לעולם.", "rgba(187,201,207,.6)", 12, "", "center")
    right = box([form, note], "niv-glass", g=16, bg="rgba(12,14,19,.8)", radius=16, pad=dim(40), pad_m=dim(24))
    card = box([box([left, right], "niv-split-7-5", direction="row")], g=0, bg="rgba(26,27,33,.95)", radius=24,
               pad=dim(64), pad_m=dim(24))
    card["settings"]["overflow"] = "hidden"
    return box([card], "niv-wrap", eid="contact-flow", pad=dim(96, 64, 40, 64), pad_m=dim(64, 20, 24, 20))


def footer():
    soc = "".join('<a href="%s" aria-label="%s"><i class="%s"></i></a>' % (h, a, i) for h, a, i in [
        ("https://wa.me/972500000000", "WhatsApp", "fab fa-whatsapp"), ("mailto:hello@nivcreative.com", "Email", "fas fa-at"),
        ("tel:+972500000000", "Phone", "fas fa-phone"), ("#", "Studio Location", "fas fa-map-marker-alt")])
    col1 = box([
        html('<div style="display:flex;align-items:center;gap:8px"><span style="color:#e2e2e9;font-size:24px;font-weight:700">NivCreative</span>'
             '<span class="niv-mono" style="color:#a5e7ff;background:#282a2f;padding:4px 8px;border-radius:6px">STUDIO V2.4</span></div>'),
        text("סטודיו בוטיק לתכנון, עיצוב ופיתוח חוויות דיגיטליות בעלות אפקט המרה גבוה. ממתגים סטארטאפים, חברות הייטק ויוזמות פורצות דרך בעולם הדיגיטל המודרני.",
             16, C["muted"], 400, 26),
        html('<div class="niv-social" style="display:flex;gap:8px">%s</div>' % soc),
    ], g=16)
    col2 = html('<div class="niv-footer"><div style="color:#e2e2e9;font-weight:600;font-size:20px;margin-bottom:12px">ניווט מהיר</div>'
                '<a class="l" href="#top">דף הבית</a><a class="l" href="#services">שירותי סטודיו</a><a class="l" href="#portfolio-section">תיק עבודות נבחר</a>'
                '<a class="l" href="#process">מתודולוגיית עבודה</a><a class="l" href="#testimonials">סיפורי לקוחות</a><a class="l" href="#about">אודות NivCreative</a></div>')
    row = lambda ic, t, ltr=False: ('<div style="display:flex;align-items:center;gap:8px;margin-bottom:8px"><i class="%s" style="color:#a5e7ff;width:20px"></i>'
                                    '<span %s style="color:#e2e2e9;font-size:%s">%s</span></div>') % (
        ic, 'class="niv-mono niv-ltr"' if ltr else "", "12px" if ltr else "14px", t)
    col3 = html('<div><div style="color:#e2e2e9;font-weight:600;font-size:20px;margin-bottom:12px">יצירת קשר וישירות</div>'
                + row("fas fa-comments", "+972 50 000 0000", True) + row("fas fa-envelope", "hello@nivcreative.com", True)
                + row("fas fa-clock", "א'-ה': 09:00 - 18:30 IST") + row("fas fa-map-pin", "תל אביב / גלובלי") + "</div>")
    col4 = box([
        heading("שיחת ייעוץ אסטרטגית", 20, 600, C["text"], "h4", lh=28),
        text("רוצים להפוך את האתר שלכם למכונת המרות? קבעו שיחת היכרות בלתי מחייבת.", 14, C["muted"], 400, 20),
        html('<div style="display:flex;flex-direction:column;gap:8px"><input type="email" dir="ltr" placeholder="your@email.com" style="width:100%;padding:8px 16px;border-radius:12px;border:0;background:#282a2f;color:#e2e2e9;font-size:14px">'
             '<a href="#contact-flow" style="text-align:center;padding:8px 16px;border-radius:12px;background:#a5e7ff;color:#003543;font-weight:600;font-size:14px;text-decoration:none">קביעת שיחה מהירה</a></div>'),
    ], g=8, bg=C["low"], radius=16, pad=dim(24))
    grid = box([col1, col2, col3, col4], "niv-footer-grid", direction="row")
    bottom = box([
        mono("© 2025 NivCreative Studio Ltd. כל הזכויות שמורות.", C["muted"], 12),
        html('<div style="display:flex;gap:24px;font-size:14px;font-weight:600"><a href="/privacy-policy" style="color:#bbc9cf;text-decoration:none">מדיניות פרטיות</a>'
             '<a href="/terms-of-service" style="color:#bbc9cf;text-decoration:none">תנאי שימוש</a><a href="/accessibility" style="color:#bbc9cf;text-decoration:none">הצהרת נגישות</a></div>'),
    ], direction="row", justify="space-between", align="center", g=16, wrap=True)
    return box([box([grid, bottom], "niv-wrap", g=64, pad=dim(0, 64), pad_m=dim(0, 20))],
               bg=C["lowest"], pad=dim(96, 0), pad_m=dim(64, 0), inner=False)


def build():
    sections = [services(), portfolio(), process(), why(), testimonials(), cta()]
    main = box([hero()] + sections, "niv-main", inner=False, g=0)
    root = {
        "id": uid(), "elType": "container", "isInner": False,
        "settings": {
            "content_width": "full", "flex_direction": "column", "flex_gap": gap(0),
            "background_background": "classic", "background_color": C["bg"],
            "css_classes": "niv-root", "padding": dim(0), "_element_id": "niv-page",
        },
        "elements": [header(), main, footer()],
    }
    return {
        "version": "0.4",
        "title": "NivCreative - דף הבית",
        "type": "page",
        "content": [root],
        "page_settings": {
            "hide_title": "yes",
            "template": "elementor_canvas",
            "background_background": "classic",
            "background_color": C["bg"],
            "custom_css": CSS,
            "padding": dim(0),
        },
    }


if __name__ == "__main__":
    out = "nivcreative-home-elementor.json"
    with open(out, "w", encoding="utf-8") as f:
        json.dump(build(), f, ensure_ascii=False, indent=1)
    print("wrote", out)
