# renders every page to /tmp/a/site/*.html (form widgets replaced by a visual stub) for local browser checks
import sys, os, json
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from content import PAGES
from components import assemble
os.makedirs("/tmp/a/site", exist_ok=True)
STUB = '<form onsubmit="return false" style="display:flex;flex-wrap:wrap;gap:14px;justify-content:center"><input class="elementor-field-textual" placeholder="שם" style="flex:1 1 240px;min-height:52px;border-radius:10px;background:#0b1730;color:#fff;border:1px solid rgba(235,198,108,.4);padding:0 14px"><input class="elementor-field-textual" placeholder="טלפון" style="flex:1 1 240px;min-height:52px;border-radius:10px;background:#0b1730;color:#fff;border:1px solid rgba(235,198,108,.4);padding:0 14px"><button class="elementor-button" style="min-width:260px;min-height:54px;border-radius:40px;background:#E3B665;border:0;font-weight:800">שליחה</button></form>'
for p in PAGES:
    data = assemble(dict(p, body=p["body"]()))
    parts = []
    def walk(n):
        for e in n:
            if e.get("widgetType") == "html": parts.append(e["settings"]["html"])
            elif e.get("widgetType") == "form": parts.append(STUB)
            walk(e.get("elements", []))
    walk(data)
    open(f"/tmp/a/site/{p['key']}.html", "w", encoding="utf-8").write('<!doctype html><html lang="he" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>%s</title></head><body style="margin:0;background:#060b16">%s</body></html>' % (p["title"], "".join(parts)))
print("rendered", len(PAGES))
