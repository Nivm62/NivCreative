#!/usr/bin/env python3
"""Builds elementor/bereshit-elementor.json from ../index.html + ../style.css.
Everything is an HTML widget except the contact form (Elementor Pro Form widget -> email)."""
import json, re, os, sys
HERE = os.path.dirname(os.path.abspath(__file__))
html = open(os.path.join(HERE, '../index.html'), encoding='utf-8').read()
css = open(os.path.join(HERE, '../style.css'), encoding='utf-8').read()
BASE = 'https://nivcreative.com/wp-content/uploads/2026/10/bereshit-'
for f in ('hero.webp', 'duo.webp', 'arch.webp', 'olive.webp', 'logo.png', 'favicon.png'):
    html = html.replace('assets/' + f, BASE + f)

def rid(n=[0]):
    n[0] += 1
    return '%08x' % (0xbe5a0000 + n[0])

html = html.replace(' loading="lazy"', '')
body = html.split('<body>')[1].split('<script>')[0]
sprite = re.search(r'<svg width="0".*?</svg>', body, re.S).group(0)
header = re.search(r'<header.*?</header>', body, re.S).group(0)
main = re.search(r'<main id="main">(.*?)</main>', body, re.S).group(1)
footer = re.search(r'<footer.*?</footer>', body, re.S).group(0)
cta = re.search(r'<!-- CTA / FORM -->\s*<section class="cta" id="contact">.*?</section>', main, re.S).group(0)
before = main.replace(cta, '')
cta_head = re.search(r'<h2>.*?</p>', cta, re.S).group(0)           # h2 + sub
wa = re.search(r'<a class="wa".*?</a>', cta, re.S).group(0)
cta_img = re.search(r'<div class="cta-img">.*?</div>', cta, re.S).group(0)

css = css.replace('html,body{overflow-x:clip}\n', '')
css += """
/* --- Elementor integration --- */
.bsh-root,.bsh-root .bsh,.bsh-cta,.bsh-cta-form{direction:rtl;text-align:right}
.bsh-root{font-family:'Heebo',system-ui,sans-serif;font-weight:300;color:var(--ink);line-height:1.7}
.bsh h1,.bsh h2,.bsh h3,.bsh-cta-form h2{font-family:'Heebo',sans-serif!important;font-weight:300!important;color:var(--ink)!important;text-align:inherit;letter-spacing:0;line-height:1.15;margin:0}
.bsh .steps h2,.bsh .steps h3,.bsh .num{color:#f3ecdc!important}
.bsh .num{color:var(--gold)!important}
.bsh .card h3,.bsh .steps-grid h3{font-weight:400!important}
.bsh p,.bsh li,.bsh summary{text-align:inherit;font-family:'Heebo',sans-serif}
.bsh p{margin:0}
.bsh a{color:inherit;text-decoration:none}
.bsh .btn,.bsh .btn:hover{color:#fff}
.bsh .hero-copy,.bsh .change,.bsh .steps,.bsh .faq,.bsh .card{text-align:center}
.bsh .steps-grid,.bsh .faq-list{text-align:right}
.bsh .steps-grid li,.bsh .steps-grid p{text-align:right}
.bsh img{max-width:none}
.bsh .brand img{height:46px;width:auto}
.bsh .brand-foot img{height:54px;width:auto}
@media(max-width:520px){.bsh .brand img{height:38px}}
.bsh .hero-img img,.bsh .about-img img,.bsh .training-img>img,.bsh .inset img,.bsh .cta-img img{height:100%;width:100%}
.bsh ol,.bsh ul{margin:0;padding:0;list-style:none}
.bsh details p{padding:0 22px 20px}
body.elementor-page{background:var(--cream)}
.bsh .elementor-widget-container{margin:0}
.bsh-cta{display:grid!important;grid-template-columns:1fr 1fr;background:linear-gradient(90deg,#eadcc4,#f0e3cd);border-radius:0 80px 0 0;overflow:hidden;--gap:0px;padding:0!important}
.bsh-cta-form{padding:clamp(40px,6vw,80px) clamp(24px,6vw,90px)!important;max-width:640px;margin-inline-start:auto;width:100%;display:flex;flex-direction:column;gap:14px}
.bsh-cta-form .elementor-field-textual{font:inherit;padding:15px 20px;border:1px solid var(--line);border-radius:10px;background:#fffdf8;color:var(--ink);font-size:1rem}
.bsh-cta-form .elementor-field-group{margin-bottom:0}
.bsh-cta-form .elementor-field-label{display:none}
.bsh-cta-form .elementor-button{border:0;box-shadow:none}
.bsh-cta-form .elementor-form-fields-wrapper{gap:12px}
.bsh-cta-form .elementor-button{background:var(--olive)!important;color:#fff;border-radius:999px;padding:16px 34px;font:400 1rem 'Heebo',sans-serif;width:100%}
.bsh-cta-form .elementor-button:hover{background:#2c3024!important}
.bsh-cta-form .elementor-message-success{color:#3c6b37;text-align:center}
.bsh-cta-form .elementor-message-danger{color:#a3402d;text-align:center}
.bsh-cta .cta-img{min-height:380px;height:100%}
.bsh-cta .cta-img img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover}
.bsh-cta .cta-img{position:relative}
.bsh-cta-form h2,.bsh-cta-form .sub{margin:0}
@media(max-width:900px){.bsh-cta{grid-template-columns:1fr;border-radius:0 48px 0 0}.bsh-cta .cta-img{min-height:240px;order:2}}
"""
FONTS = '<link href="https://fonts.googleapis.com/css2?family=Heebo:wght@200;300;400;500&family=Gveret+Levin&display=swap" rel="stylesheet">'

def html_w(code):
    return {"id": rid(), "elType": "widget", "widgetType": "html", "settings": {"html": code}, "elements": []}

def cont(children, cls='', **s):
    st = {"flex_direction": "column", "content_width": "full", "padding": {"unit": "px", "top": "0", "right": "0", "bottom": "0", "left": "0", "isLinked": True},
          "flex_gap": {"unit": "px", "column": "0", "row": "0", "isLinked": True}, "css_classes": cls}
    st.update(s)
    return {"id": rid(), "elType": "container", "isInner": bool(cls and cls != "bsh-root"), "settings": st, "elements": children}

form = {"id": rid(), "elType": "widget", "widgetType": "form", "settings": {
    "form_name": "בראשית – פגישת התאמה", "show_labels": "", "mark_required": "",
    "form_fields": [
        {"_id": "name", "custom_id": "name", "field_type": "text", "field_label": "שם מלא", "placeholder": "שם מלא", "required": "true", "width": "100"},
        {"_id": "phone", "custom_id": "phone", "field_type": "tel", "field_label": "טלפון", "placeholder": "טלפון", "required": "true", "width": "100"}],
    "button_text": "אשמח לשמוע עוד", "button_width": "100", "submit_actions": ["email"],
    "email_to": "nivmozes@gmail.com", "email_subject": "ליד חדש – בראשית", "email_content": "[all-fields]",
    "email_from_name": "אתר בראשית", "success_message": "תודה! אחזור אלייך בקרוב.", "error_message": "משהו השתבש. אפשר לפנות בווטסאפ."},
    "elements": []}

top = html_w('<div class="bsh" dir="rtl"><link rel="preconnect" href="https://fonts.googleapis.com">%s<style>%s</style>%s%s</div>' % (FONTS, css, sprite, header))
sections = html_w('<div class="bsh" dir="rtl">%s</div>' % before)
cta_box = cont([
    cont([html_w('<div class="bsh" dir="rtl">%s</div>' % cta_head), form, html_w('<div class="bsh" dir="rtl">%s</div>' % wa)], 'bsh-cta-form'),
    html_w('<div class="bsh" dir="rtl">%s</div>' % cta_img)], 'bsh bsh-cta', flex_direction="row")
cta_box["settings"]["container_type"] = "grid" if False else "flex"
foot = html_w('<div class="bsh" dir="rtl">%s</div>' % footer)

doc = {"version": "0.4", "title": "בראשית – מליס הדר", "type": "page",
       "content": [cont([top, sections, cta_box, foot], 'bsh-root')], "page_settings": {"hide_title": "yes"}}
out = os.path.join(HERE, 'bereshit-elementor.json')
json.dump(doc, open(out, 'w', encoding='utf-8'), ensure_ascii=False)
print('wrote', out, os.path.getsize(out))
