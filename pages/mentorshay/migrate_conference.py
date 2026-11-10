#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""Copies the /mentorshay/business-conference/ landing page to another WordPress+Elementor site as a NEW DRAFT page.

   SHAY_AUTH='Basic <base64 user:app-password>' python3 migrate_conference.py media   # download + upload every referenced file, writes /tmp/ms/mig_map.json
   SHAY_AUTH=... python3 migrate_conference.py page [--publish]                      # builds the page with rewritten URLs and creates/updates it

The credential comes from the environment only (never stored in the repo).
"""
import json, os, re, subprocess, sys, mimetypes
HERE = os.path.dirname(os.path.abspath(__file__)); sys.path.insert(0, HERE)
import build

TARGET = "https://shayyfrach.co.il"
SLUG = "business-conference"
MAP = "/tmp/ms/mig_map.json"
AUTH = os.environ.get("SHAY_AUTH", "")
# The destination host's nginx rejects uploads over ~1-2 MB (HTTP 413), so the 6 testimonial videos (4-14 MB) cannot be uploaded through the REST API.
# They stay hosted on nivcreative.com until someone uploads them (hosting file manager / SFTP) and runs `page` again with VIDEO_MAP.
VIDEO_MAP = "/tmp/ms/mig_videos.json"   # optional {old mp4 url: new url}
OLD_UP = re.compile(r'https://nivcreative\.com/wp-content/uploads/[^"\'\)\s\\<>]+')

# footer targets that exist on the destination site (pages of the old site that match the mentorshay footer)
FOOT_LINKS = [("/workshop/", "סדנת התפתחות אישית"), ("/business/", "ליווי עסקי ומנטלי"), ("/course/", "הקורס הדיגיטלי"), ("/vsl/", "תוכן ללא עלות")]
FOOT_NAV = [("/מדיניות-פרטיות/", "מדיניות פרטיות"), ("/accessibility-statement/", "הצהרת נגישות")]

def api(method, path, body=None, raw=None, headers=()):
    cmd = ["curl", "-sS", "-m", "300", "-X", method, TARGET + "/wp-json" + path, "-H", "Authorization: " + AUTH]
    for h in headers: cmd += ["-H", h]
    if body is not None:
        open("/tmp/ms/mig_payload.json", "w", encoding="utf-8").write(json.dumps(body, ensure_ascii=False))
        cmd += ["-H", "Content-Type: application/json", "--data-binary", "@/tmp/ms/mig_payload.json"]
    if raw: cmd += ["--data-binary", "@" + raw]
    out = subprocess.run(cmd, capture_output=True, text=True).stdout
    try: return json.loads(out)
    except Exception: return {"raw": out[:300]}

def page_urls():
    s = json.dumps(build.build_conference(False), ensure_ascii=False)
    return sorted(u for u in set(OLD_UP.findall(s)) if not u.endswith(".mp4"))   # videos: see KEEP_VIDEOS

def media():
    os.makedirs("/tmp/ms/mig", exist_ok=True)
    m = json.load(open(MAP)) if os.path.exists(MAP) else {}
    for u in page_urls():
        if u in m: print("skip", u.rsplit("/", 1)[1]); continue
        fn = u.rsplit("/", 1)[1]; local = "/tmp/ms/mig/" + fn
        if not os.path.exists(local):
            subprocess.run(["curl", "-sS", "-m", "300", "-o", local, u], check=True)
        ct = mimetypes.guess_type(fn)[0] or ("image/webp" if fn.endswith(".webp") else "application/octet-stream")
        r = api("POST", "/wp/v2/media", raw=local, headers=[f'Content-Disposition: attachment; filename="{fn}"', "Content-Type: " + ct])
        if "source_url" not in r: print("FAILED", fn, r); continue
        m[u] = r["source_url"]; json.dump(m, open(MAP, "w"), indent=1)
        print("uploaded", fn, "->", r["source_url"])
    print(len(m), "of", len(page_urls()), "mapped")

def fix_footer(s):
    nav = "".join(f'<a href="{h}">{l}</a>' for h, l in FOOT_NAV)
    sub = "".join(f'<a href="{h}">{l}</a>' for h, l in FOOT_LINKS)
    s, n1 = re.subn(r'(<nav aria-label=\\"קישורי תחתית\\">).*?(</nav>)', lambda m: m.group(1) + nav.replace('"', '\\"') + m.group(2), s, count=1)
    s, n2 = re.subn(r'(<div class=\\"sub2\\">).*?(</div>)', lambda m: m.group(1) + sub.replace('"', '\\"') + m.group(2), s, count=1)
    assert n1 == 1 and n2 == 1, (n1, n2)
    return s

def page(publish=False):
    m = json.load(open(MAP))
    data = build.build_conference(False)
    s = json.dumps(data, ensure_ascii=False)
    missing = [u for u in page_urls() if u not in m]
    assert not missing, ("media not uploaded yet", missing)
    vm = json.load(open(VIDEO_MAP)) if os.path.exists(VIDEO_MAP) else {}
    s = OLD_UP.sub(lambda x: m.get(x.group(0)) or vm.get(x.group(0)) or x.group(0), s)
    s = fix_footer(s)
    # internal links of the mentorshay site -> destination site
    s = s.replace(build.PATHS["privacy"], "/מדיניות-פרטיות/").replace(build.PATHS["conference"], "/" + SLUG + "/")
    s = s.replace('href=\\"' + build.PATHS["home"] + '\\"', 'href=\\"/\\"')
    s = s.replace("MentorShay – דף הכנס", "הצלחה עסקית למאמנות ומטפלים – דף נחיתה")
    s = s.replace("email@nivcreative.com", "wordpress@shayyfrach.co.il").replace('"nivcreative.com"', '"shayyfrach.co.il"')
    leftovers = [x for x in re.findall(r'(?:nivcreative|/mentorshay/)[^"\\ ]*', s) if not x.endswith(".mp4")]
    assert not leftovers, leftovers[:10]
    seo_title, seo_desc = build.SEO["conference"]
    existing = api("GET", f"/wp/v2/pages?slug={SLUG}&status=any&context=edit&_fields=id,status")
    pid = existing[0]["id"] if isinstance(existing, list) and existing else None
    status = (existing[0]["status"] if pid else "draft")
    if publish: status = "publish"
    payload = {"title": "הצלחה עסקית למאמנות ומטפלים (כנס)", "slug": SLUG, "status": status, "template": "elementor_canvas", "excerpt": seo_desc,
               "meta": {"_elementor_edit_mode": "builder", "_elementor_template_type": "wp-page", "_elementor_version": "4.4.0", "_elementor_data": s,
                        "_elementor_page_settings": {"hide_title": "yes", "background_background": "classic", "background_color": "#0B0B0C"},
                        "_yoast_wpseo_title": seo_title, "_yoast_wpseo_metadesc": seo_desc}}
    r = api("POST", f"/wp/v2/pages/{pid}" if pid else "/wp/v2/pages", payload)
    print({k: r.get(k) for k in ("id", "status", "link", "slug", "code", "message")})
    api("DELETE", "/elementor/v1/cache")

if __name__ == "__main__":
    if not AUTH: sys.exit("set SHAY_AUTH")
    if sys.argv[1] == "media": media()
    elif sys.argv[1] == "page": page("--publish" in sys.argv)
