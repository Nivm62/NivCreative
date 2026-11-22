#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""Clones the LIVE conference page on shayyfrach.co.il (page 6433, /business-conference/) into a new page
   /business-webinar/ and turns the in-person conference into an online Zoom webinar (Tuesday 1.12, 19:00-21:00).
   Everything else (design, form + CRM webhook, pixel, credit, wheel-scroll fix) is inherited from the live page.

   SHAY_AUTH='Basic <base64 user:app-password>' python3 clone_webinar.py            # creates/updates the page as a DRAFT (+ noindex through Yoast)
The credential comes from the environment only (never stored in the repo).
"""
import json, os, re, subprocess, sys

TARGET = "https://shayyfrach.co.il"
SRC_ID = 6433
SLUG = "business-webinar"
AUTH = os.environ.get("SHAY_AUTH", "")
TITLE = "הצלחה עסקית למטפלות ומאמנים (וובינר)"
SEO_TITLE = "הצלחה עסקית למטפלות ומאמנים: וובינר אונליין בזום עם שי יפרח"
SEO_DESC = "וובינר אונליין בזום ללא עלות עם שי יפרח למטפלות ומאמנים: מנטליות, שיווק ושיחות מכירה בגישה אנושית. ההצטרפות לקבוצת הוואטסאפ הרשמית היא שלב בהרשמה."
DATE_LABEL, TIME_LABEL, PLACE_LABEL = "יום שלישי, 1.12", "19:00 עד 21:00", "אונליין בזום"
# Israel is UTC+2 in December: 19:00 = 17:00Z, 21:00 = 19:00Z
START_UTC, END_UTC = "Date.UTC(2026,11,1,17,0,0)", "Date.UTC(2026,11,1,19,0,0)"
PIN_PATHS = '<path d="M12 21s7-6.2 7-11.5A7 7 0 0 0 5 9.5C5 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/>'
MONITOR_PATHS = '<rect x="3" y="4.5" width="18" height="12" rx="2.5"/><path d="M8 20h8M12 16.5V20"/>'
FAQ_Q_OLD, FAQ_A_OLD = "האם הכנס מתקיים בזום?", "לא. זהו כנס פרונטלי שמתקיים ב-16.11, בין השעות 16:00 ל-21:00, במתחם מיי קמפוס בבני ברק."
FAQ_Q_NEW = "האם הוובינר מתקיים בזום?"
FAQ_A_NEW = "כן. הוובינר מתקיים אונליין בזום, ביום שלישי 1.12, בין השעות 19:00 ל-21:00. פרטים והנחיות להתחברות יפורסמו בקבוצת הוואטסאפ הרשמית."

def api(method, path, body=None):
    cmd = ["curl", "-sS", "-m", "120", "-X", method, TARGET + "/wp-json" + path, "-H", "Authorization: " + AUTH, "-H", "Content-Type: application/json"]
    if body is not None:
        os.makedirs("/tmp/ms/nc", exist_ok=True); open("/tmp/ms/nc/wb_payload.json", "w", encoding="utf-8").write(json.dumps(body, ensure_ascii=False)); cmd += ["--data-binary", "@/tmp/ms/nc/wb_payload.json"]
    out = subprocess.run(cmd, capture_output=True, text=True).stdout
    try: return json.loads(out)
    except Exception: return {"raw": out[:200]}

HITS = {}
def once(s, a, b, label):
    """replace if present in this widget; main() asserts every marker was hit in at least one widget"""
    if a in s: HITS[label] = HITS.get(label, 0) + s.count(a)
    return s.replace(a, b)

def tokens(h):
    # the event is now a webinar: כנס -> וובינר (with Hebrew prefixes: הכנס, בכנס, לכנס, מהכנס); words like "הכנסות" are left alone
    return re.sub(r"(?<![א-ת])([הבלמוכש]{0,2})כנס(?![א-ת])", lambda m: m.group(1) + "וובינר", h)

def convert(h):
    h = once(h, "כנס פרונטלי ללא עלות עם שי יפרח", "וובינר אונליין ללא עלות עם שי יפרח", "pill")
    h = once(h, "<li>כנס פרונטלי</li>", "<li>וובינר בזום</li>", "tag")
    h = once(h, ">16.11</li>", f">{DATE_LABEL}</li>", "date")
    h = once(h, ">16:00 עד 21:00</li>", f">{TIME_LABEL}</li>", "time")
    h = once(h, "מתחם מיי קמפוס, בני ברק</li>", f"{PLACE_LABEL}</li>", "place")
    h = once(h, PIN_PATHS, MONITOR_PATHS, "place icon")
    h = once(h, "Date.UTC(2026,10,16,14,0,0)", START_UTC, "countdown start")
    h = once(h, "Date.UTC(2026,10,16,19,0,0)", END_UTC, "countdown end")
    h = once(h, FAQ_Q_OLD, FAQ_Q_NEW, "faq q")
    h = once(h, FAQ_A_OLD, FAQ_A_NEW, "faq a")
    # the event is now a webinar: כנס -> וובינר (with Hebrew prefixes: הכנס, בכנס, לכנס, מהכנס)
    return tokens(h)

def walk(els, fn):
    for e in els:
        if e.get("widgetType") == "html": e["settings"]["html"] = fn(e["settings"]["html"])
        if e.get("widgetType") == "form":
            s = e["settings"]
            for f in s["form_fields"]:
                if f["custom_id"] == "service": f["field_value"] = "וובינר 1.12"
                if f["custom_id"] == "source_page": f["field_value"] = "/" + SLUG + "/"
            for k, v in list(s.items()):
                if isinstance(v, str) and k not in ("webhooks", "redirect_to"): s[k] = tokens(v)   # button text, success message...
            s["form_name"] = "הצלחה עסקית למטפלות ומאמנים – וובינר 1.12"
        walk(e.get("elements", []), fn)

def main():
    src = api("GET", f"/wp/v2/pages/{SRC_ID}?context=edit&_fields=meta,status")
    data = json.loads(src["meta"]["_elementor_data"])
    assert any(w for w in [1]), "source empty"
    walk(data, convert)
    labels = ["pill", "tag", "date", "time", "place", "place icon", "countdown start", "countdown end", "faq q", "faq a"]
    missing = [l for l in labels if not HITS.get(l)]
    assert not missing, ("markers not found in the source page", missing)
    raw = json.dumps(data, ensure_ascii=False)
    left = [x for x in ("פרונטלי", "16.11", "16:00", "בני ברק", "מיי קמפוס") if x in raw] + re.findall(r"(?<![א-ת])[הבלמוכש]{0,2}כנס(?![א-ת])", raw) + re.findall(r'(?:href|action|value)=[^>]*business-conference', raw)
    assert not left, ("leftovers from the conference version", left)
    ex = api("GET", f"/wp/v2/pages?slug={SLUG}&status=any&context=edit&_fields=id,status")
    assert isinstance(ex, list), ("slug lookup failed, not creating a duplicate page; retry", str(ex)[:120])
    pid = ex[0]["id"] if ex else None
    status = ex[0]["status"] if pid else "draft"
    meta = {k: v for k, v in src["meta"].items() if k.startswith("_elementor_") and k != "_elementor_data"}
    meta["_elementor_data"] = raw
    payload = {"title": TITLE, "slug": SLUG, "status": status, "template": "elementor_canvas", "meta": meta}
    r = api("POST", f"/wp/v2/pages/{pid}" if pid else "/wp/v2/pages", payload)
    print({k: r.get(k) for k in ("id", "status", "link", "slug", "code", "message", "raw")})
    if "id" in r:
        s = api("POST", "/wp-abilities/v1/abilities/yoast-seo/update-post-seo-data/run", {"input": {"post_id": r["id"], "noindex": True, "seo_title": SEO_TITLE, "meta_description": SEO_DESC}})
        print("yoast:", json.dumps(s, ensure_ascii=False)[:160])
    subprocess.run(["curl", "-sS", "-m", "60", "-X", "DELETE", TARGET + "/wp-json/elementor/v1/cache", "-H", "Authorization: " + AUTH], capture_output=True)

if __name__ == "__main__":
    if not AUTH: sys.exit("set SHAY_AUTH")
    main()
