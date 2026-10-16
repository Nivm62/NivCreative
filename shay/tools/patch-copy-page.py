# Adds the scroll-scrubbed video to the hero of the COPY page (3541) only.
import json, sys
U = "https://nivcreative.com/wp-content/uploads/2026/10/"
src = json.load(open(sys.argv[1]))  # current page 3541 (context=edit)
ed = json.loads(src["meta"]["_elementor_data"])
w = ed[0]["elements"][0]
assert w["id"] == "73ab487" and w["widgetType"] == "html"
h = w["settings"]["html"]
assert "syxVScroll" not in h, "already patched"
open_tag = '<section class="syx-hero" id="syxHero">'
assert h.count(open_tag) == 1
start = h.index(open_tag)
close = ' </div></div></section>'
end = h.index(close, start) + len(close)
video = ('<video class="syx-vid" muted playsinline preload="auto" aria-hidden="true" tabindex="-1" disablepictureinpicture '
         f'data-src-d="{U}shay-scroll-hero-desktop.mp4" data-src-m="{U}shay-scroll-hero-mobile-v2.mp4" '
         f'data-poster-d="{U}shay-scroll-poster-desktop.jpg" data-poster-m="{U}shay-scroll-poster-mobile-v2.jpg"></video>')
cue = '<a class="syx-cue" href="#programs" aria-label="גללו להמשך"><i aria-hidden="true"></i><span>גללו למטה</span></a>'
hero = h[start:end].replace(open_tag, '<section class="syx-hero syx-hero--video" id="syxHero">\n ' + video, 1)
hero = hero[: -len('</section>')] + cue + '</section>'
h = h[:start] + '<div class="syx-vscroll" id="syxVScroll">' + hero + '</div>' + h[end:]
h += "\n" + open(sys.argv[2]).read()
w["settings"]["html"] = h
json.dump({"meta": {"_elementor_data": json.dumps(ed, ensure_ascii=False)}}, open(sys.argv[3], "w"), ensure_ascii=False)
open(sys.argv[4], "w").write(h)
print("ok", len(h))
