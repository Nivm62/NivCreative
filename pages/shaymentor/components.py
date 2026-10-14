# -*- coding: utf-8 -*-
"""Shared components for the /shaymentor/ site (header, footer, blocks, Elementor forms)."""
import os, random, json

HERE = os.path.dirname(os.path.abspath(__file__))
SITE = "https://nivcreative.com"
BASE = "/shaymentor/"
IMG = SITE + "/wp-content/uploads/2026/10/"
LOGO = IMG + "shay-logo-new.png"
SHOW_PENDING = True          # draft build shows structure of blocks that wait for real content; --publish removes them

PATHS = {
    "home": BASE, "about": BASE + "about/", "programs": BASE + "programs/", "workshop": BASE + "workshop/",
    "business": BASE + "business/", "vsl": BASE + "vsl/", "to-brand": BASE + "to-brand/", "course": BASE + "course/",
    "personal-session": BASE + "personal-session/", "team": BASE + "team/", "stories": BASE + "stories/",
    "partners": BASE + "partners/", "contact": BASE + "contact/", "thank-you": BASE + "thank-you/",
    "partners-thanks": BASE + "partners/thank-you/",
}
ARR = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 12H5M11 18l-6-6 6-6"/></svg>'

_i = {"n": 0}
def rv(extra=""):
    """reveal class + stagger index"""
    _i["n"] += 1
    return f'class="rv {extra}" style="--i:{_i["n"] % 6}"'.replace('class="rv "', 'class="rv"')

def btn(label, key_or_href, kind="gold", small=False, magnet=True):
    href = PATHS.get(key_or_href, key_or_href)
    cls = f"btn btn-{kind}" + (" btn-sm" if small else "")
    return f'<a class="{cls}" href="{href}"' + (' data-magnet' if magnet else '') + f'>{label} {ARR}</a>'

# ---------------------------------------------------------------- header / footer
NAV = [
    ("about", "אודות שי", None),
    ("programs", "תוכניות וליווי", [("workshop", "סדנת התפתחות אישית"), ("business", "ליווי עסקי ומנטלי"), ("personal-session", "פגישת 1:1 עם שי")]),
    ("course", "הקורס הדיגיטלי", None),
    ("vsl", "תוכן ללא עלות", [("vsl", "הדרכה עסקית"), ("to-brand", "פריצה ברשתות")]),
    ("team", "הצוות", None),
    ("contact", "יצירת קשר", None),
]
def header(active):
    items = []
    for key, label, subs in NAV:
        cur = ' aria-current="page"' if key == active else ""
        if subs:
            sub = "".join(f'<li><a href="{PATHS[k]}"' + (' aria-current="page"' if k == active else "") + f'>{l}</a></li>' for k, l in subs)
            items.append(f'<li class="has-sub"><a href="{PATHS[key]}"{cur}>{label}</a><button class="subtoggle" type="button" aria-expanded="false" aria-label="פתיחת תת-תפריט: {label}"></button><ul class="sub">{sub}</ul></li>')
        else:
            items.append(f'<li><a href="{PATHS[key]}"{cur}>{label}</a></li>')
    return f'''<div class="syx-progress" aria-hidden="true"><i></i></div>
<header class="syx-head" id="syxHead"><div class="pill">
 <a class="syx-logo" href="{PATHS['home']}" aria-label="שי יפרח – לעמוד הבית"><img src="{LOGO}" alt="שי יפרח" width="129" height="40"></a>
 <nav class="syx-nav" id="syxNav" aria-label="ניווט ראשי"><ul>{"".join(items)}</ul></nav>
 <div class="actions"><a class="btn btn-gold btn-sm" href="{PATHS['programs']}" data-magnet><span class="d">מצאו את המסלול שלכם</span><span class="m">מצאו מסלול</span> {ARR}</a>
 <button class="menu-btn" type="button" aria-expanded="false" aria-controls="syxNav" aria-label="פתיחת תפריט"><span></span></button></div>
</div></header>'''

def footer(sticky_label="בואו נדבר", sticky_href="#contact"):
    def a(k, l): return f'<a href="{PATHS[k]}">{l}</a>'
    return f'''<footer class="syx-foot"><div class="wrap">
 <a href="{PATHS['home']}" aria-label="שי יפרח – לעמוד הבית"><img src="{LOGO}" alt="שי יפרח" width="141" height="44" loading="lazy"></a>
 <div class="cols">
  <div class="col"><h4>אודות והצוות</h4>{a('about','אודות שי')}{a('team','הצוות')}{a('stories','סיפורי דרך')}</div>
  <div class="col"><h4>תוכניות</h4>{a('workshop','סדנת התפתחות אישית')}{a('business','ליווי עסקי ומנטלי')}{a('course','הקורס הדיגיטלי')}{a('personal-session','פגישת 1:1')}</div>
  <div class="col"><h4>תוכן ללא עלות</h4>{a('vsl','הדרכה עסקית')}{a('to-brand','פריצה ברשתות')}</div>
  <div class="col"><h4>קשר ושותפים</h4>{a('contact','יצירת קשר')}{a('partners','תוכנית השותפים')}</div>
 </div>
 <nav class="legal" aria-label="מידע משפטי"><a href="/accessibility-declaration/">הצהרת נגישות</a></nav>
</div></footer>
<a class="btn btn-gold syx-sticky" id="syxSticky" href="{sticky_href}">{sticky_label}</a>'''

# ---------------------------------------------------------------- blocks
def crumbs(trail):
    """trail: list of (label, key or None) ; last is current"""
    out = [f'<li><a href="{PATHS["home"]}">בית</a></li>']
    for i, (label, key) in enumerate(trail):
        if i == len(trail) - 1:
            out.append(f'<li aria-current="page">{label}</li>')
        elif key is None:
            out.append(f'<li>{label}</li>')
        else:
            out.append(f'<li><a href="{PATHS[key]}">{label}</a></li>')
    return f'<ol class="crumbs" aria-label="פירורי לחם" style="--i:0">{"".join(out)}</ol>'

def ihero(trail, h1, lead, ctas="", img=None, pos="center"):
    pic = f'<div class="pic" aria-hidden="true"><div class="im" style="background-image:url({img});background-position:{pos}"></div></div>' if img else ""
    solo = "" if img else " solo"
    return f'''<section class="ihero{solo}"><div class="wrap"><div class="copy">{crumbs(trail)}<h1 style="--i:1">{h1}</h1><p class="lead" style="--i:2">{lead}</p><div class="cta-row" style="--i:3">{ctas}</div></div>{pic}</div></section>'''

def section(kind, inner, id_=""):
    idat = f' id="{id_}"' if id_ else ""
    return f'<section class="{kind}"{idat}><div class="wrap">{inner}</div></section>'

def sech(h2, p=""):
    return f'<div class="sec-h"><h2 {rv()}>{h2}</h2>' + (f'<p {rv()}>{p}</p>' if p else "") + '</div>'

def prose(*paras, cls=""):
    return f'<div class="prose {cls}">' + "".join(f'<p {rv()}>{p}</p>' for p in paras) + '</div>'

def ticks(items):
    return '<ul class="ticks">' + "".join(f'<li {rv()}>{t}</li>' for t in items) + '</ul>'

def icards(items, numbered=False):
    out = []
    for n, (t, d, *link) in enumerate(items, 1):
        lk = f'<div>{link[0]}</div>' if link else ""
        num = f'<span class="n">{n:02d}</span>' if numbered else ""
        out.append(f'<div class="icard {"" }" {rv()}>{num}<h3>{t}</h3><p>{d}</p>{lk}</div>'.replace('class="icard "', 'class="icard"'))
    return '<div class="cardgrid">' + "".join(out) + '</div>'

def srows(items):
    return '<div class="stepsrow">' + "".join(f'<div class="srow" {rv()}><b>{n:02d}</b><h3>{t}</h3><p>{d}</p></div>' for n, (t, d) in enumerate(items, 1)) + '</div>'

def twocol(a_title, a_items, b_title, b_items):
    return f'''<div class="twocol"><div class="panel" {rv()}><h3>{a_title}</h3>{ticks(a_items)}</div><div class="panel no" {rv()}><h3>{b_title}</h3>{ticks(b_items)}</div></div>'''

def related(*links):
    return '<div class="related">' + "".join(links) + '</div>'

def faq(items, title="שאלות ותשובות"):
    det = "".join(f'<details {rv()}><summary>{q}<i aria-hidden="true"></i></summary><div class="ans">{a}</div></details>' for q, a in items)
    return section("cream syx-faq", f'<h2 class="t rv">{title}</h2><div class="list">{det}</div>')

def ctaband(h, p, buttons):
    return f'<section class="ctaband"><div class="wrap"><h2 {rv()}>{h}</h2><p {rv()}>{p}</p><div {rv()}>{buttons}</div></div></section>'

def pending(html):
    return f'<div class="pending">{html}</div>' if SHOW_PENDING else ""

def contact_band(h, sub):
    return f'<section class="syx-contact" id="contact"><div class="wrap"><h2 class="t rv">{h}</h2><p class="sub rv" style="--i:1">{sub}</p></div></section>'

# ---------------------------------------------------------------- Elementor widgets / forms
random.seed(11)
def uid(): return "%07x" % random.getrandbits(28)
ZERO = {"unit": "px", "top": "0", "right": "0", "bottom": "0", "left": "0", "isLinked": True}
GAP0 = {"column": "0", "row": "0", "isLinked": True, "unit": "px"}

def html_widget(code):
    return {"id": uid(), "elType": "widget", "settings": {"html": code}, "elements": [], "widgetType": "html"}
def box(children, css="", **extra):
    s = {"flex_direction": "column", "content_width": "full", "padding": ZERO, "margin": ZERO, "flex_gap": GAP0, "css_classes": css}
    s.update(extra)
    return {"id": uid(), "elType": "container", "settings": s, "elements": children, "isInner": False}

def F(id_, label, typ="text", req=False, width="33", opts=None, value=None, ph=None, rows=None):
    f = {"custom_id": id_, "field_label": label, "placeholder": ph or label, "required": "true" if req else "", "width": width, "width_mobile": "100", "_id": uid()}
    if typ != "text": f["field_type"] = typ
    if opts: f["field_options"] = "\n".join(opts)
    if value is not None: f["field_value"] = value
    if rows: f["rows"] = rows
    return f

TRACKS = ["מסלול עניין|", "התפתחות אישית (סדנה)", "ליווי עסקי ומנטלי", "הקורס הדיגיטלי", "פגישת 1:1", "תוכן ללא עלות", "תוכנית השותפים", "עדיין לא יודע/ת"]

FORMS = {
    "general": dict(name="פנייה כללית", service="פנייה כללית", button="שליחת פנייה", thanks="thank-you", fields=lambda: [
        F("name", "שם", req=True), F("phone", "טלפון", "tel", req=True), F("track", "מסלול עניין", "select", opts=TRACKS),
        F("message", "הודעה (אופציונלי)", "textarea", width="100", rows="3")]),
    "business": dict(name="בדיקת התאמה לליווי עסקי", service="בדיקת התאמה לליווי", button="לבדיקת התאמה", thanks="thank-you", fields=lambda: [
        F("name", "שם", req=True), F("phone", "טלפון", "tel", req=True), F("stage", "שלב העסק", "select", opts=["שלב העסק|", "עדיין לא התחלתי", "בתחילת הדרך", "פעיל ורוצה להתקדם", "פעיל ותקוע"]),
        F("message", "במה תרצו להתקדם? (אופציונלי)", "textarea", width="100", rows="3")]),
    "workshop": dict(name="התעניינות בסדנה", service="התעניינות בסדנת התפתחות אישית", button="עדכנו אותי על הסדנה", thanks="thank-you", fields=lambda: [
        F("name", "שם", req=True, width="50"), F("phone", "טלפון", "tel", req=True, width="50"),
        F("message", "הודעה (אופציונלי)", "textarea", width="100", rows="3")]),
    "course": dict(name="התעניינות בקורס הדיגיטלי", service="התעניינות בקורס הדיגיטלי", button="אני מעוניין/ת בקורס", thanks="thank-you", fields=lambda: [
        F("name", "שם", req=True), F("phone", "טלפון", "tel", req=True), F("email", "אימייל", "email", req=True),
        F("message", "הודעה (אופציונלי)", "textarea", width="100", rows="3")]),
    "session": dict(name="בקשת זמינות לפגישת 1:1", service="בקשת זמינות לפגישה אישית", button="לבדיקת זמינות לפגישה", thanks="thank-you", fields=lambda: [
        F("name", "שם", req=True), F("phone", "טלפון", "tel", req=True), F("topic", "נושא הפגישה", "select", opts=["נושא הפגישה|", "אתגר אישי", "החלטה עסקית", "כיוון אישי ועסקי", "אחר"]),
        F("message", "ספרו בקצרה מה תרצו לבחון (אופציונלי)", "textarea", width="100", rows="3")]),
    "partners": dict(name="מועמדות לתוכנית השותפים", service="מועמדות לתוכנית השותפים", button="להגשת מועמדות לתוכנית", thanks="partners-thanks", fields=lambda: [
        F("name", "שם", req=True), F("phone", "טלפון", "tel", req=True), F("email", "אימייל", "email", req=True),
        F("message", "ספרו בקצרה למי תרצו להפנות ואיך אתם מכירים את שי", "textarea", width="100", rows="3", req=True)]),
    "vsl": dict(name="השארת פרטים אחרי ההדרכה", service="הדרכה עסקית ללא עלות", button="השארת פרטים", thanks="thank-you", fields=lambda: [
        F("name", "שם", req=True, width="50"), F("phone", "טלפון", "tel", req=True, width="50"),
        F("message", "הודעה (אופציונלי)", "textarea", width="100", rows="3")]),
    "to-brand": dict(name="עדכון על מועד ההרצאה הבא", service="פריצה ברשתות: עדכון על מועד הבא", button="עדכנו אותי במועד הבא", thanks="thank-you", fields=lambda: [
        F("name", "שם", req=True, width="50"), F("phone", "טלפון", "tel", req=True, width="50")]),
}

def form_widget(key, page_path):
    cfg = FORMS[key]
    fields = cfg["fields"]() + [
        F("service", "שירות מבוקש", "hidden", width="100", value=cfg["service"]),
        F("source_page", "עמוד מקור", "hidden", width="100", value=page_path)]
    return {"id": uid(), "elType": "widget", "widgetType": "form", "elements": [], "settings": {
        "form_name": "ShayMentor – " + cfg["name"], "show_labels": "", "mark_required": "",
        "button_text": cfg["button"], "button_size": "lg", "button_align": "center",
        "form_fields": fields, "form_metadata": ["page_url", "date", "time"],
        "submit_actions": ["email", "redirect"], "redirect_to": SITE + PATHS[cfg["thanks"]],
        "email_subject": "ליד חדש: " + cfg["service"], "email_content": "[all-fields]",
        "email_to": "nivmozes@gmail.com", "email_from": "email@nivcreative.com", "email_from_name": "nivcreative.com",
        "success_message": "תודה! הפנייה התקבלה ונחזור אליכם בהקדם.",
        "error_message": "הפנייה לא נשלחה. אפשר לנסות שוב בעוד רגע.",
        "server_message": "אירעה תקלה בשרת. נסו שוב מאוחר יותר.",
        "invalid_message": "חלק מהשדות אינם תקינים. בדקו ונסו שוב.",
        "required_field_message": "שדה חובה",
        "column_gap": {"unit": "px", "size": 14, "sizes": []}, "row_gap": {"unit": "px", "size": 14, "sizes": []},
        "field_text_color": "#FFFFFF", "field_background_color": "#0B1730", "field_border_color": "rgba(235,198,108,.4)",
        "field_border_width": {"unit": "px", "top": "1", "right": "1", "bottom": "1", "left": "1", "isLinked": True},
        "field_border_radius": {"unit": "px", "top": "10", "right": "10", "bottom": "10", "left": "10", "isLinked": True},
        "button_text_color": "#1A1405", "button_background_color": "#E3B665",
        "button_border_radius": {"unit": "px", "top": "40", "right": "40", "bottom": "40", "left": "40", "isLinked": True},
        "button_hover_background_color": "#F4D275", "button_hover_color": "#1A1405",
        "field_typography_typography": "custom", "field_typography_font_family": "Heebo",
        "field_typography_font_size": {"unit": "px", "size": 16, "sizes": []},
        "button_typography_typography": "custom", "button_typography_font_family": "Heebo",
        "button_typography_font_size": {"unit": "px", "size": 18, "sizes": []}, "button_typography_font_weight": "800",
        "_css_classes": "shay-form"}}

# ---------------------------------------------------------------- page assembly
def read(n): return open(os.path.join(HERE, n), encoding="utf-8").read()
FONTS = '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Heebo:wght@300;400;500;700;800;900&display=swap" rel="stylesheet">'
JSFLAG = '<script>document.currentScript.parentNode.classList.add("syx-js")</script>'

def assemble(page):
    """returns Elementor data for a page dict {key, active, body, form, sticky, thanks_only}"""
    css = "<style>" + read("site.css") + read("extra.css") + "</style>"
    head = FONTS + css + f'<div class="syx" id="top">{JSFLAG}' + header(page["active"])
    js = read("site.js")
    if page.get("form"):
        a = head + page["body"] + "</div>"
        b = f'<div class="syx">{JSFLAG}{footer(page.get("sticky", "בואו נדבר"), page.get("sticky_href", "#contact"))}</div>' + js
        fw = form_widget(page["form"], PATHS[page["key"]])
        return [box([html_widget(a), box([box([fw], css="syx-formwrap")], css="syx syx-formcont"), html_widget(b)], css="syx-root")]
    one = head + page["body"] + footer(page.get("sticky", "בואו נדבר"), page.get("sticky_href", "#contact")) + "</div>" + js
    return [box([html_widget(one)], css="syx-root")]
