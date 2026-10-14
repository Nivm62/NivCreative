#!/usr/bin/env python3
"""Builds the Elementor data for the Shay Yafrach landing page and creates/updates it
as a DRAFT WordPress page on nivcreative.com (auth is injected by the agent proxy)."""
import json, os, random, subprocess, sys

HERE = os.path.dirname(os.path.abspath(__file__))
random.seed(7)
uid = lambda: "%07x" % random.getrandbits(28)
read = lambda n: open(os.path.join(HERE, n), encoding="utf-8").read()
ZERO = {"unit": "px", "top": "0", "right": "0", "bottom": "0", "left": "0", "isLinked": True}
GAP0 = {"column": "0", "row": "0", "isLinked": True, "unit": "px"}

def html(code):
    return {"id": uid(), "elType": "widget", "settings": {"html": code}, "elements": [], "widgetType": "html"}

def box(children, css="", **extra):
    s = {"flex_direction": "column", "content_width": "full", "padding": ZERO, "margin": ZERO, "flex_gap": GAP0, "css_classes": css}
    s.update(extra)
    return {"id": uid(), "elType": "container", "settings": s, "elements": children, "isInner": False}

FIELD = lambda **k: dict({"required": "", "width": "33", "width_mobile": "100", "_id": uid()}, **k)
form = {"id": uid(), "elType": "widget", "widgetType": "form", "elements": [], "settings": {
    "form_name": "שי יפרח - דף נחיתה", "show_labels": "", "button_text": "שליחת פנייה", "button_size": "lg", "button_align": "center",
    "form_fields": [
        FIELD(custom_id="name", field_label="שם מלא", placeholder="שם מלא", required="true"),
        FIELD(custom_id="phone", field_type="tel", field_label="טלפון", placeholder="טלפון", required="true"),
        FIELD(custom_id="focus", field_type="select", field_label="במה תרצו להתמקד?",
              field_options="במה תרצו להתמקד?|\nהתפתחות אישית\nליווי עסקי\nקורס דיגיטלי\nפגישת 1:1\nתוכנית השותפים")],
    "submit_actions": ["email"], "email_subject": "ליד חדש - שי יפרח (דף נחיתה)", "email_content": "[all-fields]",
    "email_to": "nivmozes@gmail.com", "email_from": "email@nivcreative.com", "email_from_name": "nivcreative.com",
    "success_message": "תודה! נחזור אליכם בקרוב.",
    "column_gap": {"unit": "px", "size": 14, "sizes": []}, "row_gap": {"unit": "px", "size": 14, "sizes": []},
    "field_text_color": "#FFFFFF", "field_background_color": "#0B1730", "field_border_color": "rgba(232,181,74,.4)",
    "field_border_width": {"unit": "px", "top": "1", "right": "1", "bottom": "1", "left": "1", "isLinked": True},
    "field_border_radius": {"unit": "px", "top": "10", "right": "10", "bottom": "10", "left": "10", "isLinked": True},
    "button_text_color": "#1A1405", "button_background_color": "#E8B54A",
    "button_border_radius": {"unit": "px", "top": "40", "right": "40", "bottom": "40", "left": "40", "isLinked": True},
    "button_hover_background_color": "#F6D88A", "button_hover_color": "#1A1405",
    "field_typography_typography": "custom", "field_typography_font_family": "Heebo",
    "field_typography_font_size": {"unit": "px", "size": 16, "sizes": []},
    "button_typography_typography": "custom", "button_typography_font_family": "Heebo",
    "button_typography_font_size": {"unit": "px", "size": 18, "sizes": []}, "button_typography_font_weight": "800",
    "_css_classes": "shay-form"}}

data = [box([html(read("part1.html")),
             box([box([form], css="syx-formwrap")], css="syx syx-formcont"),
             html(read("part2.html"))], css="syx-root")]
data_s = json.dumps(data, ensure_ascii=False)
open(os.path.join(HERE, "elementor-data.json"), "w", encoding="utf-8").write(data_s)

payload = {
    "title": "שי יפרח – מנטור מנטלי ועסקי | דף נחיתה חדשני (טיוטה)",
    "slug": "shay-yafrach-landing", "status": "draft", "template": "elementor_canvas",
    "meta": {"_elementor_edit_mode": "builder", "_elementor_template_type": "wp-page", "_elementor_version": "3.25.0",
             "_elementor_data": data_s,
             "_elementor_page_settings": {"hide_title": "yes", "background_background": "classic", "background_color": "#0B0B0C"}}}
pid = sys.argv[1] if len(sys.argv) > 1 else None
url = "https://nivcreative.com/wp-json/wp/v2/pages" + (f"/{pid}" if pid else "")
open("/tmp/a/payload.json", "w", encoding="utf-8").write(json.dumps(payload, ensure_ascii=False))
out = subprocess.run(["curl", "-sS", "-m", "90", "-X", "POST", url, "-H", "Content-Type: application/json",
                      "--data-binary", "@/tmp/a/payload.json"], capture_output=True, text=True).stdout
r = json.loads(out)
print({k: r.get(k) for k in ("id", "status", "slug", "link", "template", "code", "message")})
# Elementor caches the rendered output; a plain REST update does not invalidate it.
subprocess.run(["curl", "-sS", "-m", "60", "-X", "DELETE", "https://nivcreative.com/wp-json/elementor/v1/cache"], capture_output=True)
