#!/usr/bin/env python3
"""Creates/updates the /shaymentor/ project pages in WordPress as DRAFTS (idempotent).
Usage: python3 push.py            (draft build, shows pending blocks)
       python3 push.py --only about contact
Auth to nivcreative.com is injected by the agent proxy."""
import sys, os, json, subprocess, urllib.parse
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import components
from content import PAGES
from components import assemble, PATHS, SITE

HERE = os.path.dirname(os.path.abspath(__file__))
MAPF = os.path.join(HERE, "pages-map.json")
API = SITE + "/wp-json"
ids = json.load(open(MAPF)) if os.path.exists(MAPF) else {"home": 3502}
only = sys.argv[sys.argv.index("--only") + 1:] if "--only" in sys.argv else None

def call(method, path, body=None):
    cmd = ["curl", "-sS", "-m", "120", "-X", method, API + path, "-H", "Content-Type: application/json"]
    if body is not None:
        open("/tmp/a/payload.json", "w", encoding="utf-8").write(json.dumps(body, ensure_ascii=False)); cmd += ["--data-binary", "@/tmp/a/payload.json"]
    out = subprocess.run(cmd, capture_output=True, text=True).stdout
    try: return json.loads(out)
    except Exception: return {"raw": out[:300]}

for p in PAGES:
    if only and p["key"] not in only: continue
    page = dict(p, body=p["body"]())
    data = json.dumps(assemble(page), ensure_ascii=False)
    payload = {"title": p["title"], "slug": p["slug"], "status": "draft", "template": "elementor_canvas",
               "parent": ids.get(p["parent"], 0) if p["parent"] else 0, "excerpt": p["seo_desc"],
               "menu_order": [x["key"] for x in PAGES].index(p["key"]),
               "meta": {"_elementor_edit_mode": "builder", "_elementor_template_type": "wp-page", "_elementor_version": "3.25.0",
                        "_elementor_data": data, "_elementor_page_settings": {"hide_title": "yes", "background_background": "classic", "background_color": "#0B0B0C"}}}
    pid = ids.get(p["key"])
    r = call("POST", f"/wp/v2/pages/{pid}" if pid else "/wp/v2/pages", payload)
    if "id" not in r: print("FAILED", p["key"], r); sys.exit(1)
    ids[p["key"]] = r["id"]
    json.dump(ids, open(MAPF, "w"), indent=1)
    # SEO (SureRank): title, description, keep out of the index while in draft
    s = call("POST", "/surerank/v1/post/settings", {"post_id": r["id"], "metaData": {"page_title": p["seo_title"], "page_description": p["seo_desc"], "post_no_index": "1", "post_no_follow": "1"}})
    print(f'{p["key"]:17s} id={r["id"]} status={r["status"]} parent={r.get("parent")} slug={r["slug"]} seo={s.get("success", s)}')
subprocess.run(["curl", "-sS", "-m", "60", "-X", "DELETE", API + "/elementor/v1/cache"], capture_output=True)
