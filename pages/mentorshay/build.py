#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""Builds the /mentorshay/ site (new design) from the live /shaymentor/ pages (source/ snapshot) + the new sketch.
   python3 build.py preview            render local HTML to /tmp/ms/site
   python3 build.py push [keys...]     create/update WordPress pages as DRAFTS (+SEO, noindex, Elementor cache)
"""
import json, os, re, subprocess, sys, copy
HERE = os.path.dirname(os.path.abspath(__file__)); sys.path.insert(0, HERE)
import theme

SITE = "https://nivcreative.com"; OLD = "/shaymentor/"; BASE = "/mentorshay/"
UP = SITE + "/wp-content/uploads/2026/10/"
LOGO = UP + "shay-logo-new.png"
SHOW_PENDING = False
WA = "https://wa.me/972539457696"
ARR = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 12H5M11 18l-6-6 6-6"/></svg>'

P = lambda s: BASE + s
PATHS = {"home": BASE, "about": P("about/"), "programs": P("programs/"), "workshop": P("workshop/"), "business": P("business/"), "vsl": P("vsl/"),
         "to-brand": P("to-brand/"), "course": P("course/"), "personal-session": P("personal-session/"), "team": P("team/"), "stories": P("stories/"),
         "partners": P("partners/"), "contact": P("contact/"), "thank-you": P("thank-you/"), "partners-thanks": P("partners/thank-you/"),
         "accessibility": P("accessibility-declaration/"), "privacy": P("privacy-policy/")}
# key -> (slug, parent key, source key in /shaymentor/ snapshot)
PAGES = [("home", "mentorshay", None, "home"), ("about", "about", "home", "about"), ("programs", "programs", "home", "programs"), ("workshop", "workshop", "home", "workshop"),
         ("business", "business", "home", "business"), ("vsl", "vsl", "home", "vsl"), ("to-brand", "to-brand", "home", "to-brand"), ("course", "course", "home", "course"),
         ("personal-session", "personal-session", "home", "personal-session"), ("team", "team", "home", "team"), ("stories", "stories", "home", "stories"),
         ("partners", "partners", "home", "partners"), ("contact", "contact", "home", "contact"), ("thank-you", "thank-you", "home", "thank-you"),
         ("partners-thanks", "thank-you", "partners", "partners-thanks"), ("accessibility", "accessibility-declaration", "home", "accessibility"), ("privacy", "privacy-policy", "home", "accessibility"), ("home-v2", "home-v2", "home", "home")]

def rd(n): return open(os.path.join(HERE, n), encoding="utf-8").read()
CSS = None
def css():
    global CSS
    if CSS is None:
        base = json.load(open(os.path.join(HERE, "source", "live_business.json"), encoding="utf-8"))
        styles = []
        def walk(n):
            for e in n:
                if e.get("widgetType") == "html":
                    styles.extend(re.findall(r"<style[^>]*>(.*?)</style>", e["settings"]["html"], re.S))
                walk(e.get("elements", []))
        walk(base["elementor_data"])
        # [font fix, base css, inner-page css]
        raw = "\n".join(styles[:3])
        raw = raw.replace("/shaymentor/", BASE)
        raw = re.sub(r"url\(https://nivcreative\.com/wp-content/uploads/2026/10/(syx-bg-(glow|stage|band)|shay-inner-hero|shay-hero-stage)\.webp\)", 'url("data:image/gif;base64,R0lGODlhAQABAAAAACw=")', raw)
        CSS = theme.recolor(raw) + "\n" + rd("ms.css")
    return CSS

JS = None
def js():
    global JS
    if JS is None:
        src = json.load(open(os.path.join(HERE, "source", "live_home.json"), encoding="utf-8"))
        ws = []
        def walk(n):
            for e in n:
                if e.get("widgetType") == "html": ws.append(e["settings"]["html"])
                walk(e.get("elements", []))
        walk(src["elementor_data"])
        JS = re.search(r"<script>\n\(function\(\)\{.*?\}\)\(\);\n</script>", ws[-1], re.S).group(0)
        # safety net: reveal anything already in view even if the IntersectionObserver was skipped (fast scroll / anchor jump)
        anchor = "  tick=false;\n }\n w.addEventListener('scroll'"
        assert anchor in JS, "scroll handler anchor not found"
        JS = JS.replace(anchor, "  $$('.rv:not(.in)').forEach(function(el){var r=el.getBoundingClientRect();if(r.top<w.innerHeight*.95&&r.bottom>0)el.classList.add('in')});\n"+anchor, 1)
    return JS

FONTS = '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Heebo:wght@300;400;500;700;800;900&display=swap" rel="stylesheet">'
JSFLAG = '<script>document.currentScript.parentNode.classList.add("syx-js")</script>'

NAV = [("about", "אודות שי", None), ("programs", "תוכניות וליווי", [("workshop", "סדנת התפתחות אישית"), ("business", "ליווי עסקי ומנטלי"), ("personal-session", "פגישת 1:1 עם שי")]),
       ("course", "הקורס הדיגיטלי", None), ("vsl", "תוכן ללא עלות", [("vsl", "הדרכה עסקית"), ("to-brand", "פריצה ברשתות")]), ("team", "הצוות", None), ("contact", "יצירת קשר", None)]

def header(active):
    items = []
    for key, label, subs in NAV:
        cur = ' aria-current="page"' if key == active else ""
        if subs:
            sub = "".join(f'<li><a href="{PATHS[k]}"' + (' aria-current="page"' if k == active else "") + f'>{l}</a></li>' for k, l in subs)
            items.append(f'<li class="has-sub"><a href="{PATHS[key]}"{cur}>{label}</a><button class="subtoggle" type="button" aria-expanded="false" aria-label="פתיחת תת-תפריט: {label}"></button><ul class="sub">{sub}</ul></li>')
        else:
            items.append(f'<li><a href="{PATHS[key]}"{cur}>{label}</a></li>')
    return f'''<header class="syx-head" id="syxHead"><div class="pill">
 <a class="syx-logo v2-logo" href="{PATHS['home']}" aria-label="שי יפרח – לעמוד הבית"><span class="v2-mark" role="img" aria-label="שי יפרח"><img src="https://nivcreative.com/wp-content/uploads/2026/11/ms-logo-crown.webp" alt="" width="553" height="337"><img src="https://nivcreative.com/wp-content/uploads/2026/11/ms-logo-text.webp" alt="" width="1242" height="337"></span><small>העצמה<i>|</i>הגשמה<i>|</i>תוצאות</small></a>
 <nav class="syx-nav" id="syxNav" aria-label="ניווט ראשי"><ul>{"".join(items)}</ul></nav>
 <div class="actions"><a class="btn btn-gold btn-sm" href="{PATHS['contact']}" data-magnet><span class="d">בואו נדבר</span><span class="m">בואו נדבר</span> {ARR}</a>
 <button class="menu-btn" type="button" aria-expanded="false" aria-controls="syxNav" aria-label="פתיחת תפריט"><span></span></button></div>
</div></header>
<script>(function fixA11y(n){{var l=document.querySelector('#nv-a11y-foot a[href="/accessibility-declaration/"]');if(l){{l.setAttribute('href','{PATHS["accessibility"]}');}}else if(n<40){{setTimeout(function(){{fixA11y(n+1)}},250);}}}})(0);</script><span class="sp-skip-target" id="nv-main-content" tabindex="-1" style="display:block;position:relative;top:-90px"></span>'''

WA_SVG = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2a10 10 0 0 0-8.6 15L2 22l5.1-1.3A10 10 0 1 0 12 2zm5.8 14.2c-.2.7-1.3 1.3-1.8 1.4-.5.1-1 .2-3.3-.7-2.8-1.2-4.6-4-4.7-4.2-.1-.2-1.1-1.5-1.1-2.8s.7-2 .9-2.3c.2-.3.5-.3.7-.3h.5c.2 0 .4 0 .6.5.2.6.8 2 .8 2.1.1.1.1.3 0 .5-.1.2-.1.3-.3.5l-.4.5c-.1.2-.3.3-.1.6.2.3.7 1.2 1.5 1.9 1 .9 1.9 1.2 2.2 1.3.3.1.4.1.6-.1.2-.2.7-.8.9-1.1.2-.3.4-.2.6-.1.3.1 1.6.8 1.9.9.3.2.5.2.5.3.1.2.1.7-.1 1.4z"/></svg>'
def footer(sticky_label="בואו נדבר", sticky_href=None):
    sticky_href = sticky_href or PATHS["contact"]
    def a(k, l): return f'<a href="{PATHS[k]}">{l}</a>'
    return f'''<footer class="ms-foot"><div class="wrap">
 <a class="syx-logo v2-logo" href="{PATHS['home']}" aria-label="שי יפרח – לעמוד הבית"><span class="v2-mark" role="img" aria-label="שי יפרח"><img src="https://nivcreative.com/wp-content/uploads/2026/11/ms-logo-crown.webp" alt="" width="553" height="337" loading="lazy"><img src="https://nivcreative.com/wp-content/uploads/2026/11/ms-logo-text.webp" alt="" width="1242" height="337" loading="lazy"></span><small>העצמה<i>|</i>הגשמה<i>|</i>תוצאות</small></a>
 <nav aria-label="קישורי תחתית">{a('contact','יצירת קשר')}{a('privacy','מדיניות פרטיות')}{a('accessibility','הצהרת נגישות')}</nav>
 <div class="reach"><a href="{WA}" target="_blank" rel="noopener" aria-label="שליחת הודעה בוואטסאפ">{WA_SVG}<span>וואטסאפ</span></a></div>
 <div class="sub2">{a('about','אודות שי')}{a('programs','תוכניות וליווי')}{a('course','הקורס הדיגיטלי')}{a('vsl','תוכן ללא עלות')}{a('team','הצוות')}{a('stories','סיפורי דרך')}{a('partners','תוכנית השותפים')}</div>
</div></footer>
<a class="ms-wa-float" href="{WA}?text=%D7%A9%D7%9C%D7%95%D7%9D%2C%20%D7%90%D7%A9%D7%9E%D7%97%20%D7%9C%D7%A9%D7%9E%D7%95%D7%A2%20%D7%A2%D7%95%D7%93%20%D7%A2%D7%9C%20%D7%94%D7%9E%D7%A1%D7%9C%D7%95%D7%9C%D7%99%D7%9D" target="_blank" rel="noopener" aria-label="שליחת הודעה בוואטסאפ (נפתח בחלון חדש)">{WA_SVG}<span class="ms-wa-label">דברו איתנו בוואטסאפ</span></a>'''

# ------------------------------------------------------------------ home (5 blocks per the sketch)
ICON_BULB = '<svg viewBox="0 0 48 48" aria-hidden="true"><path d="M24 6a12 12 0 0 0-7 21.8V33h14v-5.2A12 12 0 0 0 24 6z" pathLength="1"/><path d="M18 38h12M20 43h8" pathLength="1" style="--i:1"/></svg>'
ICON_SHIELD = '<svg viewBox="0 0 48 48" aria-hidden="true"><path d="M24 6l14 5v11c0 9-6 15-14 20-8-5-14-11-14-20V11z" pathLength="1"/><path d="M17.5 24l5 5 9-10" pathLength="1" style="--i:1"/></svg>'
ICON_SPROUT = '<svg viewBox="0 0 48 48" aria-hidden="true"><path d="M24 42V24" pathLength="1"/><path d="M24 26c-9 0-14-5-14-12 8 0 14 4 14 12z" pathLength="1" style="--i:1"/><path d="M24 22c0-7 5-11 13-11 0 7-5 11-13 11z" pathLength="1" style="--i:2"/></svg>'
def slider_html():
    T = json.load(open(os.path.join(HERE, "source", "testimonials.json"), encoding="utf-8"))
    def mmss(s): return f"{s//60}:{s%60:02d}"
    PLAY = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg>'
    vcards = "".join(f'''<article class="ms-vcard" data-src="{t['video']}" data-poster="{t['poster']}" data-label="עדות {t['i']}: סרטון של משתתפת או משתתף" aria-roledescription="שקופית" aria-label="עדות {t['i']} מתוך {len(T)}"><div class="ms-frame"><img src="{t['poster']}" alt="" loading="lazy" decoding="async" width="576" height="1024"><button class="ms-play" type="button" aria-label="נגן עדות {t['i']}, {mmss(t['dur'])} דקות">{PLAY}</button><span class="ms-dur" aria-hidden="true">{mmss(t['dur'])}</span></div></article>''' for t in T)
    dots = "".join(f'<button type="button" role="tab" aria-selected="{"true" if k == 3 else "false"}" aria-label="עדות {k+1}"></button>' for k in range(len(T)))
    return f'''<div class="ms-slider rv" style="--i:3" data-start="3" role="region" aria-roledescription="סליידר" aria-label="סרטוני עדויות"><div class="ms-track" tabindex="0" aria-label="גלילה בין העדויות">{vcards}</div>
 <div class="ms-ctrl"><button class="ms-prev" type="button" aria-label="העדות הקודמת"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 5l7 7-7 7"/></svg></button><div class="ms-meta"><div class="ms-prog" aria-hidden="true"><i></i></div><div class="ms-dots" role="tablist" aria-label="בחירת עדות">{dots}</div></div><button class="ms-next" type="button" aria-label="העדות הבאה"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 5l-7 7 7 7"/></svg></button></div><p class="ms-live" aria-live="polite" style="position:absolute;left:-9999px"></p></div>'''


def story2_html(sec_class, z_attr=""):
    return f'''<section class="ms-sec ms-ivory {sec_class} ms-story2" id="story"{z_attr}><div class="wrap">
 <span class="ms-eyebrow ms-eyebrow-c rv">הסיפור שלנו</span>
 <h2 class="ms-h2 s2-h rv" style="--i:1"><span>עסק עם משמעות</span><span class="s2-u">מתחיל באנשים.</span></h2>
 <p class="s2-gold rv" style="--i:2">מחברים בין שליחות, אנשים ודרך עסקית.</p>
 <p class="s2-p rv" style="--i:3">אקדמיית העסקים של שי יפרח מחברת בין התפתחות אישית, חשיבה עסקית וכלים מעשיים.<br> מרחב למטפלות ומאמנים שרוצים לחדד את הכיוון, לחזק את הביטחון ולהפוך את הידע והשליחות שלהם לעסק עם משמעות.</p>
 <div class="s2-video rv" style="--i:3" data-src="{UP}shay-stories-reel.mp4" data-poster="{UP}shay-stories-cover.jpg"><img src="{UP}shay-stories-cover.jpg" alt="" loading="lazy" decoding="async" width="1280" height="720"><button class="s2-play" type="button" aria-label="נגן סרטון מתוך הסדנאות של שי יפרח, 1:47 דקות"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg></button><span class="s2-dur" aria-hidden="true">1:47</span></div>
 <p class="s2-vcap rv" style="--i:3">מתוך סדנאות וכנסים של שי יפרח. הסרטון כולל כתוביות בעברית.</p>
 <div class="ms-feats s2-feats"><div class="ms-feat rv" style="--i:2">{ICON_BULB}<h3>בהירות</h3><p>להגדיר למי אתם עוזרים, מה מייחד אתכם ומה הצעד הבא של העסק.</p></div><div class="ms-feat rv" style="--i:3">{ICON_SHIELD}<h3>ביטחון</h3><p>לחזק את האמונה ביכולות שלכם ולהציג את השירות והערך שלכם בביטחון.</p></div><div class="ms-feat rv" style="--i:4">{ICON_SPROUT}<h3>עשייה</h3><p>לתרגם מטרות לתוכנית מעשית, להתמיד ולבחון את ההתקדמות לאורך הדרך.</p></div></div>
 <div class="s2-cta rv" style="--i:4"><a class="btn btn-gold" href="{PATHS['about']}" data-magnet>להכיר את הסיפור שלנו {ARR}</a></div>
</div></section>'''

def home_body():
    z = lambda n: f' style="--z:{n}"'
    hero = f'''<section class="syx-hero ms-hero ms-sec ms-dark" id="syxHero"{z(50)}>
 <div class="bg" role="img" aria-label="שי יפרח מחייך על הבמה מול קהל"></div><div class="sheen" aria-hidden="true"></div><div class="spot" aria-hidden="true"></div><canvas id="syxDust" aria-hidden="true"></canvas>
 <div class="wrap"><div class="copy">
  <h1 aria-label="אקדמיית העסקים למטפלות ומאמנים"><span class="ln"><span class="w"><span style="--i:0">אקדמיית</span></span> <span class="w"><span style="--i:1" class="gold">העסקים</span></span></span><span class="ln"><span class="w"><span style="--i:2">למטפלות</span></span> <span class="w"><span style="--i:3">ומאמנים</span></span></span></h1>
  <p class="lead hero-in" style="--i:1">מחברים בין התפתחות אישית לכלים מעשיים לבניית עסק.</p>
  <div class="cta-row hero-in" style="--i:2"><a class="btn btn-gold" href="{PATHS['programs']}" data-magnet>בואו נמצא את הדרך שלכם {ARR}</a><a class="btn-link" href="{PATHS['about']}" data-magnet>הכירו את שי {ARR}</a></div>
  <img class="ms-sign hero-sign" src="https://nivcreative.com/wp-content/uploads/2026/11/ms-signature.webp" alt="החתימה של שי יפרח" width="900" height="251">
 </div></div></section>'''
    def feat(n, icon, title, text):
        return f'<div class="ms-feat rv" style="--i:{n}">{icon}<h3>{title}</h3><p>{text}</p></div>'
    story = story2_html('ms-story', z(40))
    about = f'''<section class="ms-sec ms-dark ms-midnight ms-about" id="meet"{z(30)}><div class="wrap ms-grid">
 <div><span class="ms-eyebrow rv">על שי</span><h2 class="ms-h2 rv" style="--i:1">נעים להכיר, שי יפרח.</h2>
 <p class="ms-p rv" style="--i:2">שי משלב ליווי מנטלי ועסקי, הרצאות וסדנאות, מתוך גישה שמחברת בין האדם לבין העסק שהוא בונה.</p>
 <p class="ms-emph rv" style="--i:3">קודם בונים את האדם. משם מקדמים את העסק.</p>
 <div class="rv" style="--i:4"><a class="btn btn-ghost" href="{PATHS['about']}" data-magnet>להכיר את שי {ARR}</a></div></div>
 <div class="ms-photo rv" style="--i:2" role="img" aria-label="שי יפרח מחייך ומרצה"><div class="im" style="background:url({UP}spl-s6-speaking.webp) 70% 30%/cover;width:100%;height:100%"></div></div></div></section>'''
    def card(img, pos, h, p, key):
        return f'<article class="ms-card rv" data-tilt><div class="im" role="img" aria-label="{h}" style="background-image:url({img});background-position:{pos}"></div><h3>{h}</h3><p>{p}</p><a class="btn btn-ink" href="{PATHS[key]}">לפרטים נוספים {ARR}</a></article>'
    products = f'''<section class="ms-sec ms-ivory ms-center" id="services"{z(20)}><div class="wrap"><span class="ms-eyebrow rv">מוצרים ושירותים</span><h2 class="ms-h2 rv" style="--i:1">הדרך שמתאימה לשלב שלכם.</h2>
 <div class="ms-cards">{card(UP+'shay-banner-stage.jpg','50% 40%','ליווי עסקי ומנטלי','בונים תכנון ברור לעסק, עם ליווי מותאם אישית.','business')}{card(UP+'shay-stories-cover.jpg','50% 50%','סדנאות התפתחות אישית','למידה בקבוצה, חיבורים ותרגול, במסגרת תומכת ומעשית.','workshop')}{card(UP+'ms-card-course.webp','60% 50%','קורסים דיגיטליים','ללמוד בקצב שלכם, מכל מקום ובזמן שמתאים לכם.','course')}{card(UP+'syx-card-1to1.webp','50% 50%','פגישת 1:1','שיחה מעמיקה על הצעד הבא בעסק שלכם.','personal-session')}</div></div></section>'''
    slider = slider_html()
    stories = f'''<section class="ms-sec ms-dark ms-center" id="stories-home"{z(10)}><div class="wrap"><span class="ms-eyebrow rv">עדויות</span><h2 class="ms-h2 rv" style="--i:1">אנשים. תהליכים. סיפורים אישיים.</h2><p class="ms-p rv" style="--i:2;margin-inline:auto">חוויות מהדרך, במילים שלהם.</p></div>
 {slider}
 <div class="wrap"><div class="ms-cta2 rv"><a class="btn btn-ghost" href="{PATHS['stories']}" data-magnet>לעוד סיפורי דרך {ARR}</a><a class="btn btn-gold" href="{PATHS['contact']}" data-magnet>בואו נדבר {ARR}</a></div></div></section>'''
    return hero + story + about + products + stories

# ------------------------------------------------------------------ transforms of the live inner pages
def widgets(data):
    acc = []
    def walk(n):
        for e in n:
            if e.get("widgetType") in ("html", "form"): acc.append(e)
            walk(e.get("elements", []))
    walk(data); return acc

def fix_inline_styles(h):
    return re.sub(r'style="([^"]*)"', lambda m: 'style="%s"' % theme.recolor(m.group(1)), h)

def retarget(s):
    return s.replace(OLD, BASE).replace("ShayMentor", "MentorShay")

def inner_widget0(html, active):
    h = re.sub(r"<style[^>]*>.*?</style>", "", html, flags=re.S)
    h = re.sub(r"<header class=\"syx-head\".*?</header>.*?<span class=\"sp-skip-target\"[^>]*></span>", "@@HEADER@@", h, flags=re.S)
    assert "@@HEADER@@" in h, "header not found"
    h = h.replace("@@HEADER@@", header(active))
    h = fix_inline_styles(retarget(h))
    h = h.replace("<link", "<link", 1)
    return h

def with_style(h):
    # insert the single style block right after the fonts <link>s
    i = h.rfind("<link")
    j = h.find(">", i) + 1
    return h[:j] + "<style>" + css() + "</style>" + h[j:]

def form_widget(src, key):
    f = copy.deepcopy(src)
    s = retarget(json.dumps(f, ensure_ascii=False))
    s = s.replace("#0B1730", "#151515").replace("rgba(235,198,108,.4)", "rgba(233,204,134,.45)")
    return json.loads(s)

def tail_widget(sticky_label, sticky_href):
    return {"id": "ms" + os.urandom(3).hex(), "elType": "widget", "settings": {"html": f'<div class="syx">{JSFLAG}{footer(sticky_label, sticky_href)}</div>' + js()}, "elements": [], "widgetType": "html"}

def contact_extra():
    return f'''<section class="sp-sec" id="reach" aria-labelledby="reach-t"><span class="sp-kick">דרכים נוספות</span><h2 class="sp-h2" id="reach-t">מעדיפים לדבר?</h2><div class="sp-prose"><p>אפשר גם לשלוח לנו הודעה בוואטסאפ.</p></div><div class="sp-ctas" style="margin-top:18px"><a class="btn btn-ink" style="width:auto;padding:0 28px" href="{WA}" target="_blank" rel="noopener">וואטסאפ</a></div></section>'''

# ---------------- legal pages ----------------
def sp_page(trail, h1, h1_gold, lead, sections, tabs=None):
    crumbs = "".join(f'<li><a href="{PATHS[k]}">{l}</a></li>' for l, k in trail[:-1]) + f'<li><span aria-current="page">{trail[-1][0]}</span></li>'
    tabs_html = ('<nav class="sp-tabs" aria-label="ניווט בעמוד"><ul>' + "".join(f'<li><a href="#{i}"' + (' class="is-on"' if n == 0 else "") + f'>{t}</a></li>' for n, (i, t) in enumerate(tabs)) + '</ul></nav>') if tabs else ""
    return f'''<section class="sp-hero" aria-labelledby="sp-h1"><div class="wrap"><div class="sp-hero-in"><nav class="sp-crumbs" aria-label="פירורי לחם"><ol><li><a href="{PATHS['home']}">עמוד הבית</a></li>{crumbs}</ol></nav><span class="sp-tag">שי יפרח | מנטור מנטלי ועסקי</span><h1 id="sp-h1">{h1} <span class="sp-gold">{h1_gold}</span></h1><p class="sp-lead">{lead}</p></div></div></section>{tabs_html}<div class="sp-main"><div class="wrap">{sections}</div></div>'''

def privacy_sections():
    def sec(id_, kick, title, body):
        return f'<section class="sp-sec" id="{id_}" aria-labelledby="{id_}-t"><span class="sp-kick">{kick}</span><h2 class="sp-h2" id="{id_}-t">{title}</h2><div class="sp-prose">{body}</div></section>'
    return "".join([
        sec("operator", "סעיף 1", "פרטי העוסק", f'<p>האתר מופעל על ידי: <strong>שי יפרח – העצמה, הגשמה, תוצאות בע״מ</strong> (להלן: ״העוסק״).</p><p>ח.פ: 516945938<br>כתובת: המסגר 35 תל אביב / מצדה 7<br>יצירת קשר: <a href="{PATHS["contact"]}">טופס יצירת הקשר באתר</a></p>'),
        sec("services", "סעיף 2", "מהות השירותים", '<p>האתר משמש כפלטפורמה לשיווק שירותים בתחום ההתפתחות האישית והעסקית, לרבות קורסים דיגיטליים, תכניות ליווי, סדנאות ומפגשים פרונטליים.</p><p>המידע המוצג באתר מהווה מידע כללי בלבד ואינו מהווה התחייבות לתוצאה כלשהי.</p>'),
        sec("contracting", "סעיף 3", "ביצוע התקשרות", '<p>התקשרות עם העוסק מתבצעת באמצעות אמצעי תקשורת מרחוק, לרבות שיחה טלפונית, טופס מקוון או הודעות דיגיטליות.</p><p>עם ביצוע ההתקשרות נמסר לצרכן מסמך גילוי או הסכם התקשרות הכולל את מלוא פרטי העסקה, בהתאם להוראות חוק הגנת הצרכן.</p>'),
        sec("cancel", "סעיף 4", "מדיניות ביטולים: מכר מרחוק", '<p>בהתאם לחוק הגנת הצרכן, התשמ״א–1981:</p><ul class="sp-list"><li>צרכן רשאי לבטל עסקה לרכישת שירות בתוך 14 ימים ממועד קבלת מסמך הגילוי או ממועד קבלת השירות, לפי המאוחר מביניהם.</li><li>במקרה של ביטול כדין, יוחזר לצרכן הסכום ששולם, בניכוי דמי ביטול בשיעור של 5% ממחיר העסקה או 100 ₪, לפי הנמוך מביניהם.</li><li>ככל שנמסרה לצרכן גישה לתכנים דיגיטליים הניתנים להעתקה, שמירה או שימוש חוזר, לא תחול זכות ביטול ביחס לרכיב הדיגיטלי, וזאת בהתאם לדין.</li><li>ביטול ייעשה בהודעה בכתב בלבד, לכתובת הדוא״ל שתימסר בעת ההתקשרות.</li></ul>'),
        sec("ip", "סעיף 5", "קניין רוחני", '<p>כל התכנים באתר, לרבות סרטונים, מסמכים, מערכי לימוד, חומרים כתובים או מוקלטים, הינם רכושו הבלעדי של העוסק ואסור להעתיקם, להפיצם או לעשות בהם שימוש ללא אישור מראש ובכתב.</p>'),
        sec("liability", "סעיף 6", "הגבלת אחריות", '<p>העוסק אינו מתחייב לתוצאה כלשהי בעקבות שימוש בשירותים. האחריות ליישום התכנים חלה על המשתמש בלבד.</p>'),
        sec("law", "סעיף 7", "סמכות שיפוט", '<p>על מדיניות זו יחולו דיני מדינת ישראל בלבד. סמכות השיפוט הבלעדית נתונה לבתי המשפט במחוז תל אביב.</p>')])


# ------------------------------------------------------------------ home v2 (second sketch)
def header2():
    items = []
    for key, label, subs in NAV:
        if subs:
            sub = "".join(f'<li><a href="{PATHS[k]}">{l}</a></li>' for k, l in subs)
            items.append(f'<li class="has-sub"><a href="{PATHS[key]}">{label}</a><button class="subtoggle" type="button" aria-expanded="false" aria-label="פתיחת תת-תפריט: {label}"></button><ul class="sub">{sub}</ul></li>')
        else:
            items.append(f'<li><a href="{PATHS[key]}">{label}</a></li>')
    return f'''<header class="syx-head" id="syxHead"><div class="pill">
 <a class="syx-logo v2-logo" href="{PATHS['home']}" aria-label="שי יפרח – לעמוד הבית"><span class="v2-mark" role="img" aria-label="שי יפרח"><img src="https://nivcreative.com/wp-content/uploads/2026/11/ms-logo-crown.webp" alt="" width="553" height="337"><img src="https://nivcreative.com/wp-content/uploads/2026/11/ms-logo-text.webp" alt="" width="1242" height="337"></span><small>העצמה<i>|</i>הגשמה<i>|</i>תוצאות</small></a>
 <nav class="syx-nav" id="syxNav" aria-label="ניווט ראשי"><ul>{"".join(items)}</ul></nav>
 <div class="actions"><a class="btn btn-gold btn-sm" href="#contact" data-magnet><span class="d">בואו נדבר</span><span class="m">בואו נדבר</span> {ARR}</a>
 <button class="menu-btn" type="button" aria-expanded="false" aria-controls="syxNav" aria-label="פתיחת תפריט"><span></span></button></div>
</div></header>
<span class="sp-skip-target" id="nv-main-content" tabindex="-1" style="display:block;position:relative;top:-90px"></span>'''

def footer2():
    def a(k, l): return f'<a href="{PATHS[k]}">{l}</a>'
    return f'''<footer class="v2-foot"><div class="wrap"><a class="syx-logo v2-logo" href="{PATHS['home']}" aria-label="שי יפרח – לעמוד הבית"><span class="v2-mark" role="img" aria-label="שי יפרח"><img src="https://nivcreative.com/wp-content/uploads/2026/11/ms-logo-crown.webp" alt="" width="553" height="337" loading="lazy"><img src="https://nivcreative.com/wp-content/uploads/2026/11/ms-logo-text.webp" alt="" width="1242" height="337" loading="lazy"></span><small>העצמה<i>|</i>הגשמה<i>|</i>תוצאות</small></a>
 <nav aria-label="קישורי תחתית">{a('accessibility','נגישות')}{a('privacy','פרטיות')}{a('contact','יצירת קשר')}</nav></div></footer>
<a class="ms-wa-float" href="{WA}?text=%D7%A9%D7%9C%D7%95%D7%9D%2C%20%D7%90%D7%A9%D7%9E%D7%97%20%D7%9C%D7%A9%D7%9E%D7%95%D7%A2%20%D7%A2%D7%95%D7%93%20%D7%A2%D7%9C%20%D7%94%D7%9E%D7%A1%D7%9C%D7%95%D7%9C%D7%99%D7%9D" target="_blank" rel="noopener" aria-label="שליחת הודעה בוואטסאפ (נפתח בחלון חדש)">{WA_SVG}<span class="ms-wa-label">דברו איתנו בוואטסאפ</span></a>'''

def home2_parts():
    z = lambda n: f' style="--z:{n}"'
    mouse = '<div class="v2-mouse" aria-hidden="true"><i></i><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg></div>'
    hero = f'''<section class="syx-hero ms-hero v2-hero ms-sec ms-dark" id="syxHero"{z(50)}>
 <div class="bg" role="img" aria-label="שי יפרח מחייך על הבמה מול קהל"></div><div class="sheen" aria-hidden="true"></div><div class="spot" aria-hidden="true"></div><canvas id="syxDust" aria-hidden="true"></canvas>
 <div class="wrap"><div class="copy">
  <span class="v2-eyebrow hero-in" style="--i:0">האקדמיה של שי יפרח</span>
  <h1 aria-label="אקדמיית העסקים למטפלות ומאמנים"><span class="ln"><span class="w"><span style="--i:0">אקדמיית</span></span> <span class="w"><span style="--i:1">העסקים</span></span></span><span class="ln"><span class="w"><span style="--i:2" class="gold">למטפלות</span></span> <span class="w"><span style="--i:3" class="gold">ומאמנים</span></span></span></h1>
  <p class="lead hero-in" style="--i:1"><b>המקצועיות שלכם. השליחות שלכם. העסק שאתם רוצים לבנות.</b><span>כלים עסקיים והתפתחות אישית, כדי להתקדם עם כיוון וביטחון.</span></p>
  <div class="cta-row hero-in" style="--i:2"><a class="btn btn-gold" href="#contact" data-magnet>בואו נמצא את הדרך שלכם {ARR}</a><a class="btn-link" href="{PATHS['about']}" data-magnet>להכיר את האקדמיה {ARR}</a></div>
  <img class="ms-sign hero-sign" src="https://nivcreative.com/wp-content/uploads/2026/11/ms-signature.webp" alt="החתימה של שי יפרח" width="900" height="251">
 </div></div>
 <div class="v2-feats hero-in" style="--i:3"><div class="wrap">{mouse}</div></div></section>'''
    story = story2_html('v2-story2', z(40))
    meet = f'''<section class="ms-sec ms-dark v2-meet" id="meet"{z(30)}><div class="wrap"><div class="ms-grid"><div class="v2-photo rv" style="--i:2" role="img" aria-label="שי יפרח מחייך"><div class="im"></div></div><div><span class="ms-eyebrow rv">מי עומד מאחורי האקדמיה</span><h2 class="ms-h2 rv" style="--i:1">נעים להכיר, שי יפרח.</h2><span class="ms-sub rv" style="--i:2">מנטור מנטלי ועסקי</span>
 <p class="ms-p rv" style="--i:2">הגישה שלנו מתחילה באדם שמוביל את העסק. כשמחברים בין עבודה מנטלית, חשיבה ממוקדת וצעדים מעשיים, אפשר לבנות דרך שמתאימה למי שאתם ולמקום שאליו אתם רוצים להגיע.</p>
 <div class="rv" style="--i:3"><a class="btn-link" href="{PATHS['about']}">עוד על שי {ARR}</a></div></div></div>
 <div class="v2-tag rv">להתפתח כאנשים. להתקדם בעסקים.</div></div></section>'''
    def row(n, title, desc, img, key, extra=""):
        return f'''<a class="v2-row rv" style="--i:{n}" href="{PATHS[key]}"><div class="tx"><span class="num">{n:02d}</span><h3>{title}</h3><p>{desc}</p>{extra}</div><span class="v2-circ" aria-hidden="true">{ARR.replace('M19 12H5M11 18l-6-6 6-6','M5 12h14M13 6l6 6-6 6')}</span><div class="th" role="img" aria-label="{title}" style="background-image:url({img})"></div></a>'''
    serv = f'''<section class="ms-sec ms-ivory v2-serv" id="services"{z(20)}><div class="wrap"><div class="head"><span class="ms-eyebrow rv">תוכניות, מוצרים ושירותים</span><h2 class="ms-h2 rv" style="--i:1">בצעד הבא שלכם, יש דרך.</h2><p class="rv" style="--i:2">בחרו את המסגרת שמתאימה לשלב שבו אתם נמצאים.</p></div>
 <div class="v2-grid"><a class="v2-feat rv" style="--i:2" href="{PATHS['business']}"><div class="im" aria-hidden="true"></div><div class="in"><h3>ליווי עסקי</h3><p>כיוון, חשיבה וכלים לבניית העסק שלכם.</p><span class="v2-more">לפרטים על התהליך <span class="v2-circ">{ARR.replace('M19 12H5M11 18l-6-6 6-6','M5 12h14M13 6l6 6-6 6')}</span></span></div></a>
 <div class="v2-list">{row(1,'סדנאות והתפתחות אישית','מרחב ללמידה, לתרגול ולהתפתחות.',UP+'shay-card-workshop-2.jpg','workshop')}{row(2,'קורסים דיגיטליים','ידע וכלים שאפשר ללמוד בקצב שלכם.',UP+'ms-card-course.webp','course')}{row(3,'פגישת 1:1 עם שי','זמן ממוקד לשאלות ולאתגרים שלכם.',UP+'syx-card-1to1.webp','personal-session','<span class="price">1,000 ₪ + מע״מ</span>')}</div></div></div></section>'''
    stories = f'''<section class="ms-sec ms-dark ms-center v2-stories" id="stories-home"{z(10)}><div class="wrap"><span class="ms-eyebrow rv">עדויות</span><h2 class="ms-h2 rv" style="--i:1">הדרך שלהם. ההשראה שלכם.</h2></div>
 {slider_html()}
 <div class="wrap"><div class="ms-cta2 rv"><a class="btn btn-ghost" href="{PATHS['stories']}" data-magnet>לעוד סיפורי דרך {ARR}</a></div></div></section>'''
    contact = '<section class="v2-contact" id="contact"><div class="wrap"><h2 class="rv">בואו נדבר על העסק שלכם.</h2><p class="rv" style="--i:1">השאירו פרטים ונבדוק יחד מה יכול להתאים לכם.</p></div></section>'
    return hero + story + meet + serv + stories + contact

def home2_form():
    src = json.load(open(os.path.join(HERE, "source", "live_contact.json"), encoding="utf-8"))
    f = None
    for w in widgets(src["elementor_data"]):
        if w["widgetType"] == "form": f = copy.deepcopy(w); break
    s = f["settings"]
    keep = [x for x in s["form_fields"] if x["custom_id"] in ("name", "phone", "service", "source_page")]
    for x in keep:
        if x["custom_id"] in ("name", "phone"): x["width"] = "40"; x["width_mobile"] = "100"
        if x["custom_id"] == "service": x["field_value"] = "דף הבית (גרסה 2)"
        if x["custom_id"] == "source_page": x["field_value"] = P("home-v2/")
    s["form_fields"] = keep
    s.update({"form_name": "MentorShay – דף הבית גרסה 2", "button_text": "דברו איתי", "button_width": "20", "button_width_mobile": "100", "button_align": "stretch",
              "email_subject": "ליד חדש: דף הבית (גרסה 2)", "redirect_to": SITE + PATHS["thank-you"],
              "field_background_color": "#FFFFFF", "field_text_color": "#000000", "field_border_color": "rgba(185,138,62,.45)",
              "button_background_color": "#E3B665", "button_text_color": "#14110A", "_css_classes": "shay-form"})
    return retarget_obj(f)

def retarget_obj(o): return json.loads(retarget(json.dumps(o, ensure_ascii=False)))

def build_home2():
    head = FONTS + "<style>" + css() + rd("ms2.css") + "</style>" + f'<div class="syx" id="top">{JSFLAG}<div class="syx-progress" aria-hidden="true"><i></i></div>' + header2()
    a = head + home2_parts() + "</div>"
    bt = f'<div class="syx">{JSFLAG}{footer2()}</div>' + js() + rd("ms_slider.js") + "<script>(function(){var h=document.getElementById('syxHead');document.querySelectorAll('#syxNav a').forEach(function(l){l.addEventListener('click',function(){h.classList.remove('open')})})})();</script>"
    wid = lambda code, i: {"id": "v2h%d" % i, "elType": "widget", "settings": {"html": code}, "elements": [], "widgetType": "html"}
    form = home2_form()
    cont = {"id": "v2fc", "elType": "container", "settings": {"flex_direction": "column", "content_width": "full", "flex_gap": {"column": "0", "row": "0", "isLinked": True, "unit": "px"}, "padding": {"unit": "px", "top": "0", "right": "0", "bottom": "0", "left": "0", "isLinked": True}, "css_classes": "syx syx-wrapper v2-formcont"}, "elements": [{"id": "v2fw", "elType": "container", "settings": {"flex_direction": "column", "content_width": "full", "flex_gap": {"column": "0", "row": "0", "isLinked": True, "unit": "px"}, "css_classes": "v2-formwrap"}, "elements": [form], "isInner": True}], "isInner": False}
    return [{"id": "v2root", "elType": "container", "settings": {"flex_direction": "column", "content_width": "full", "flex_gap": {"column": "0", "row": "0", "isLinked": True, "unit": "px"}, "padding": {"unit": "px", "top": "0", "right": "0", "bottom": "0", "left": "0", "isLinked": True}, "css_classes": "syx-root"}, "elements": [wid(a, 0), cont, wid(bt, 1)], "isInner": False}]

def build_page(key, src_cache):
    if key == "home-v2": return build_home2()
    slug, parent, srckey = [(s, p, sk) for k, s, p, sk in PAGES if k == key][0]
    if key == "home":
        body = home_body()
        html = FONTS + "<style>" + css() + "</style>" + f'<div class="syx" id="top">{JSFLAG}<div class="syx-progress" aria-hidden="true"><i></i></div>' + header("home") + body + footer() + "</div>" + js() + rd("ms_slider.js")
        return [{"id": "msroot0", "elType": "container", "settings": {"flex_direction": "column", "content_width": "full", "padding": {"unit": "px", "top": "0", "right": "0", "bottom": "0", "left": "0", "isLinked": True}, "css_classes": "syx-root"}, "elements": [{"id": "mshome0", "elType": "widget", "settings": {"html": html}, "elements": [], "widgetType": "html"}], "isInner": False}]
    src = src_cache[srckey]
    data = copy.deepcopy(src["elementor_data"])
    ws = widgets(data)
    html_ws = [w for w in ws if w["widgetType"] == "html"]
    w0, wtail = html_ws[0], html_ws[-1]
    h0 = inner_widget0(w0["settings"]["html"], key if key in ("about", "programs", "workshop", "business", "vsl", "to-brand", "course", "personal-session", "team", "contact") else "")
    if key == "contact":
        h0 = re.sub(r"(</section>)(</div></div>)(<section class=\"syx-contact\")", lambda m: m.group(1) + contact_extra() + m.group(2) + m.group(3), h0, count=1)
    if key == "stories":
        panel = f'''<section class="sp-sec" id="testimonials" aria-labelledby="testimonials-t"><div class="ms-panel"><div class="ms-panel-h"><span class="ms-eyebrow">עדויות בווידאו</span><h2 class="ms-h2" id="testimonials-t">אנשים. תהליכים. סיפורים אישיים.</h2><p class="ms-p">חוויות מהדרך, במילים שלהם.</p></div>{slider_html()}</div></section>'''
        h0 = h0.replace('<div class="sp-main"><div class="wrap">', '<div class="sp-main"><div class="wrap">' + panel, 1)
        h0 = h0.replace('<li><a href="#video" class="is-on">', '<li><a href="#testimonials" class="is-on">עדויות בווידאו</a></li><li><a href="#video">', 1)
    if key == "privacy":
        a = h0.index('<section class="sp-hero"'); b = h0.index('<section class="syx-contact"')
        page = sp_page([("תקנון ומדיניות פרטיות", None)], "תקנון ומדיניות", "פרטיות.", "תנאי השימוש בשירותים, מדיניות הביטולים ופרטי העוסק.", privacy_sections(),
                       [("operator", "פרטי העוסק"), ("services", "השירותים"), ("cancel", "ביטולים"), ("ip", "קניין רוחני"), ("law", "סמכות שיפוט")])
        h0 = h0[:a] + fix_inline_styles(page) + h0[b:]
        h0 = h0.replace("הצהרת נגישות", "מדיניות פרטיות")
    w0["settings"]["html"] = with_style(h0)
    # footer / js widget
    wtail["settings"]["html"] = f'<div class="syx">{JSFLAG}{footer()}</div>' + js() + (rd("ms_slider.js") if key == "stories" else "")
    for w in ws:
        if w["widgetType"] == "form":
            w["settings"] = form_widget(w["settings"], key)
            if key == "privacy":
                w["settings"] = json.loads(json.dumps(w["settings"], ensure_ascii=False).replace("הצהרת נגישות", "מדיניות פרטיות").replace("נגישות", "מדיניות פרטיות"))
    return json.loads(retarget(json.dumps(data, ensure_ascii=False)))

def load_sources():
    out = {}
    for f in os.listdir(os.path.join(HERE, "source")):
        if f.startswith("live_"): out[f[5:-5]] = json.load(open(os.path.join(HERE, "source", f), encoding="utf-8"))
    return out

def preview():
    src = load_sources(); os.makedirs("/tmp/ms/site", exist_ok=True)
    STUB = '<form onsubmit="return false" style="display:flex;flex-wrap:wrap;gap:14px;justify-content:center;max-width:900px;margin:0 auto;padding:20px"><input class="elementor-field-textual" placeholder="שם" style="flex:1 1 240px;min-height:52px;border-radius:10px;background:#151515;color:#fff;border:1px solid rgba(233,204,134,.45);padding:0 14px"><input class="elementor-field-textual" placeholder="טלפון" style="flex:1 1 240px;min-height:52px;border-radius:10px;background:#151515;color:#fff;border:1px solid rgba(233,204,134,.45);padding:0 14px"><button class="elementor-button" style="min-width:260px;min-height:54px;border-radius:40px;background:#E3B665;border:0;font-weight:800">שליחה</button></form>'
    for k, *_ in PAGES:
        d = build_page(k, src); parts = []
        for w in widgets(d): parts.append(w["settings"]["html"] if w["widgetType"] == "html" else STUB)
        # form container shown between widgets, like Elementor does
        open(f"/tmp/ms/site/{k}.html", "w", encoding="utf-8").write('<!doctype html><html lang="he" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>%s</title></head><body style="margin:0;background:#0b0b0c">%s</body></html>' % (k, "".join(parts)))
    print("rendered", len(PAGES))

# ------------------------------------------------------------------ WordPress (drafts)
SEO = {  # key: (wp title suffix handled below, seo title, seo description)
    "home": ("אקדמיית העסקים למטפלות ומאמנים | שי יפרח", "אקדמיית העסקים של שי יפרח למטפלות ומאמנים: מחברים בין התפתחות אישית לכלים מעשיים לבניית עסק. ליווי עסקי ומנטלי, סדנאות, קורסים דיגיטליים ופגישות 1:1."),
    "home-v2": ("אקדמיית העסקים למטפלות ומאמנים | גרסה 2", "סקיצה נוספת לעמוד הבית של אקדמיית העסקים של שי יפרח."),
    "privacy": ("תקנון ומדיניות פרטיות | שי יפרח", "תקנון ומדיניות פרטיות של שי יפרח: פרטי העוסק, מדיניות ביטולים, קניין רוחני והגבלת אחריות."),
}
MAPF = os.path.join(HERE, "pages-map.json")
def call(method, path, body=None):
    cmd = ["curl", "-sS", "-m", "120", "-X", method, SITE + "/wp-json" + path, "-H", "Content-Type: application/json"]
    if body is not None:
        os.makedirs("/tmp/ms", exist_ok=True); open("/tmp/ms/payload.json", "w", encoding="utf-8").write(json.dumps(body, ensure_ascii=False)); cmd += ["--data-binary", "@/tmp/ms/payload.json"]
    out = subprocess.run(cmd, capture_output=True, text=True).stdout
    try: return json.loads(out)
    except Exception: return {"raw": out[:300]}

def push(only=None):
    src = load_sources(); ids = json.load(open(MAPF)) if os.path.exists(MAPF) else {}
    for key, slug, parent, srckey in PAGES:
        if only and key not in only: continue
        data = build_page(key, src)
        s = src[srckey]
        title = SEO.get(key, (None,))[0]
        seo_title, seo_desc = SEO.get(key, (None, None))
        if key not in SEO:
            lm = json.load(open(os.path.join(HERE, "source", "seo.json"), encoding="utf-8")).get(srckey, {})
            seo_title = retarget(lm.get("page_title") or s["title"]); seo_desc = retarget(lm.get("page_description") or "")
        wp_title = (s["title"] if key not in SEO else SEO[key][0]) + " (MentorShay)"
        cur = call("GET", f"/wp/v2/pages/{ids[key]}?context=edit&_fields=status") if ids.get(key) else {}
        keep = "publish" if cur.get("status") == "publish" else "draft"
        payload = {"title": wp_title, "slug": slug, "status": keep, "template": "elementor_canvas", "parent": ids.get(parent, 0) if parent else 0,
                   "excerpt": seo_desc or "", "menu_order": [k for k, *_ in PAGES].index(key),
                   "meta": {"_elementor_edit_mode": "builder", "_elementor_template_type": "wp-page", "_elementor_version": "3.25.0", "_elementor_data": json.dumps(data, ensure_ascii=False),
                            "_elementor_page_settings": {"hide_title": "yes", "background_background": "classic", "background_color": "#0B0B0C"}}}
        pid = ids.get(key)
        r = call("POST", f"/wp/v2/pages/{pid}" if pid else "/wp/v2/pages", payload)
        if "id" not in r: print("FAILED", key, r); sys.exit(1)
        ids[key] = r["id"]; json.dump(ids, open(MAPF, "w"), indent=1)
        sr = call("POST", "/surerank/v1/post/settings", {"post_id": r["id"], "metaData": {"page_title": seo_title, "page_description": seo_desc, "post_no_index": ("yes" if (key in ("thank-you", "partners-thanks") or keep != "publish") else ""), "post_no_follow": ""}})
        print(f'{key:17s} id={r["id"]} status={r["status"]} parent={r.get("parent")} slug={r["slug"]} seo={sr.get("success", sr)}')
    subprocess.run(["curl", "-sS", "-m", "60", "-X", "DELETE", SITE + "/wp-json/elementor/v1/cache"], capture_output=True)

if __name__ == "__main__":
    if len(sys.argv) > 1 and sys.argv[1] == "preview": preview()
    elif len(sys.argv) > 1 and sys.argv[1] == "push": push(sys.argv[2:] or None)
