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
         "accessibility": P("accessibility-declaration/"), "quiz": P("find-your-path/"), "conference": P("business-conference/"), "conference-v2": P("business-conference-v2/"), "privacy": P("privacy-policy/")}
# key -> (slug, parent key, source key in /shaymentor/ snapshot)
PAGES = [("home", "mentorshay", None, "home"), ("about", "about", "home", "about"), ("programs", "programs", "home", "programs"), ("workshop", "workshop", "home", "workshop"),
         ("business", "business", "home", "business"), ("vsl", "vsl", "home", "vsl"), ("to-brand", "to-brand", "home", "to-brand"), ("course", "course", "home", "course"),
         ("personal-session", "personal-session", "home", "personal-session"), ("team", "team", "home", "team"), ("stories", "stories", "home", "stories"),
         ("partners", "partners", "home", "partners"), ("contact", "contact", "home", "contact"), ("thank-you", "thank-you", "home", "thank-you"),
         ("partners-thanks", "thank-you", "partners", "partners-thanks"), ("accessibility", "accessibility-declaration", "home", "accessibility"), ("privacy", "privacy-policy", "home", "accessibility"), ("home-v2", "home-v2", "home", "home"), ("quiz", "find-your-path", "home", "contact"), ("conference", "business-conference", "home", "home"), ("conference-v2", "business-conference-v2", "home", "home")]

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
def slider_html(order=None, start=3):
    T = json.load(open(os.path.join(HERE, "source", "testimonials.json"), encoding="utf-8"))
    if order:
        byi = {t["i"]: t for t in T}
        T = [dict(byi[i], i=k + 1) for k, i in enumerate(order)]
    def mmss(s): return f"{s//60}:{s%60:02d}"
    PLAY = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg>'
    vcards = "".join(f'''<article class="ms-vcard" data-src="{t['video']}" data-poster="{t['poster']}" data-label="עדות {t['i']}: סרטון של משתתפת או משתתף" aria-roledescription="שקופית" aria-label="עדות {t['i']} מתוך {len(T)}"><div class="ms-frame"><img src="{t['poster']}" alt="" loading="lazy" decoding="async" width="576" height="1024"><button class="ms-play" type="button" aria-label="נגן עדות {t['i']}, {mmss(t['dur'])} דקות">{PLAY}</button><span class="ms-dur" aria-hidden="true">{mmss(t['dur'])}</span></div></article>''' for t in T)
    dots = "".join(f'<button type="button" role="tab" aria-selected="{"true" if k == start else "false"}" aria-label="עדות {k+1}"></button>' for k in range(len(T)))
    return f'''<div class="ms-slider rv" style="--i:3" data-start="{start}" role="region" aria-roledescription="סליידר" aria-label="סרטוני עדויות"><div class="ms-track" tabindex="0" aria-label="גלילה בין העדויות">{vcards}</div>
 <div class="ms-ctrl"><button class="ms-prev" type="button" aria-label="העדות הקודמת"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 5l7 7-7 7"/></svg></button><div class="ms-meta"><div class="ms-prog" aria-hidden="true"><i></i></div><div class="ms-dots" role="tablist" aria-label="בחירת עדות">{dots}</div></div><button class="ms-next" type="button" aria-label="העדות הבאה"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 5l-7 7 7 7"/></svg></button></div><p class="ms-live" aria-live="polite" style="position:absolute;left:-9999px"></p></div>'''



MARQ_WORDS = ["בהירות", "ביטחון עצמי", "מיינדסט עסקי", "שיווק מדויק", "צמיחה עסקית", "שליחות", "עשייה", "השפעה"]
def marquee_html():
    """Full-width word marquee. Copy 1 is the real content; the other copies are decorative (aria-hidden)."""
    def copy(hidden):
        items = "".join(f'<li role="listitem">{w}</li>' for w in MARQ_WORDS)
        return f'<ul class="ms-marq-copy" role="list"{" aria-hidden=\"true\"" if hidden else ""}>{items}</ul>'
    return f'''<section class="ms-sec ms-dark ms-marq" style="--z:45" aria-label="ערכים שמלווים את הדרך"><div class="ms-marq-vp"><div class="ms-marq-track">{copy(False)}{copy(True)}{copy(True)}{copy(True)}</div></div></section>'''

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
 <div class="ms-cards">{card(UP+'shay-banner-stage.jpg','50% 40%','ליווי עסקי ומנטלי','בונים תכנון ברור לעסק, עם ליווי מותאם אישית.','business')}{card('https://nivcreative.com/wp-content/uploads/2026/11/ms-card-workshop.webp','40% 50%','סדנאות התפתחות אישית','למידה בקבוצה, חיבורים ותרגול, במסגרת תומכת ומעשית.','workshop')}{card('https://nivcreative.com/wp-content/uploads/2026/11/ms-card-course2.webp','50% 50%','קורסים דיגיטליים','ללמוד בקצב שלכם, מכל מקום ובזמן שמתאים לכם.','course')}{card(UP+'syx-card-1to1.webp','50% 50%','פגישת 1:1','שיחה מעמיקה על הצעד הבא בעסק שלכם.','personal-session')}</div></div></section>'''
    slider = slider_html()
    stories = f'''<section class="ms-sec ms-dark ms-center" id="stories-home"{z(10)}><div class="wrap"><span class="ms-eyebrow rv">עדויות</span><h2 class="ms-h2 rv" style="--i:1">אנשים. תהליכים. סיפורים אישיים.</h2><p class="ms-p rv" style="--i:2;margin-inline:auto">חוויות מהדרך, במילים שלהם.</p></div>
 {slider}
 <div class="wrap"><div class="ms-cta2 rv"><a class="btn btn-ghost" href="{PATHS['stories']}" data-magnet>לעוד סיפורי דרך {ARR}</a><a class="btn btn-gold" href="{PATHS['contact']}" data-magnet>בואו נדבר {ARR}</a></div></div></section>'''
    return hero + marquee_html() + story + about + products + stories

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


def quiz_sections():
    ARRW = ARR
    hub = f'''<section class="sp-sec" id="start" aria-labelledby="start-t"><div id="qz" class="qz"><div class="qz-hub"><span class="sp-kick">שאלוני התאמה</span><h2 class="sp-h2" id="start-t">בחרו שאלון והתחילו</h2><p class="sp-sub">כמה שאלות קצרות, ותקבלו המלצה על המסלול שנראה הכי קרוב למה שאתם מחפשים. אין תשובה נכונה או שגויה, ואין צורך להשאיר פרטים כדי לקבל תוצאה.</p>
<div class="qz-choose"><div class="qz-pick"><span class="n">01</span><h3>איזה מסלול מתאים לי?</h3><p>שישה שאלות לכל מי שרוצה להתקדם בחיים ובעסק.</p><span class="qz-time">כדקה וחצי</span><button class="btn btn-gold" type="button" data-quiz="general">להתחלת השאלון {ARRW}</button></div>
<div class="qz-pick dark"><span class="n">02</span><h3>שאלון לבעלות ובעלי עסקים, מטפלות ומאמנים</h3><p>חמש שאלות על שלב העסק, כדי להבין מאיפה כדאי להתחיל.</p><span class="qz-time">כדקה</span><button class="btn btn-ghost" type="button" data-quiz="business">להתחלת השאלון {ARRW}</button></div></div>
<noscript><p class="qz-note">השאלונים דורשים JavaScript. אפשר להשוות בין המסלולים <a href="{PATHS['programs']}">בעמוד התוכניות והליווי</a> או <a href="{PATHS['contact']}">להשאיר פרטים</a>.</p></noscript></div>
<div class="qz-stage" hidden></div><p class="qz-live" aria-live="polite" style="position:absolute;left:-9999px"></p></div></section>'''
    after = f'''<section class="sp-sec" id="paths" aria-labelledby="paths-t"><span class="sp-kick">המסלולים</span><h2 class="sp-h2" id="paths-t">רוצים להשוות בעצמכם?</h2><div class="sp-rel"><a href="{PATHS['workshop']}"><b>סדנת התפתחות אישית</b><span>בסיס אישי חזק, בקבוצה.</span><i>לעמוד הסדנה ←</i></a><a href="{PATHS['business']}"><b>ליווי עסקי ומנטלי</b><span>בידול, תכנון, שיווק ומכירות.</span><i>לעמוד הליווי ←</i></a><a href="{PATHS['course']}"><b>הקורס הדיגיטלי</b><span>21 פרקים בקצב שלכם.</span><i>לעמוד הקורס ←</i></a><a href="{PATHS['personal-session']}"><b>פגישת 1:1 עם שי</b><span>מיקוד באתגר או בהחלטה אחת.</span><i>לפרטי הפגישה ←</i></a><a href="{PATHS['vsl']}"><b>הדרכה ללא עלות</b><span>נקודת פתיחה בלי התחייבות.</span><i>לצפייה בהדרכה ←</i></a><a href="{PATHS['programs']}"><b>השוואה מלאה</b><span>טבלת השוואה בין כל המסלולים.</span><i>לעמוד התוכניות ←</i></a></div></section>'''
    return hub + after

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


# ------------------------------------------------------------------ business conference landing page
CONF_V2 = False
CONF_JOIN = "https://chat.whatsapp.com/EajOYmFxufa8PsbtSNLgXd?s=cl&p=a&mlu=4&ilr=4&iam=2"   # official group invite: shown AFTER the lead form is submitted (redirect) and as a direct option
CONF_NAME = "הצלחה עסקית למאמנות ומטפלים"
def cta_label():
    return "לקבוצת הכנס ולהמשך ההרשמה" if CONF_V2 else "שי, תשריין לי כרטיס לכנס!"

def conf_btn(label, extra=""):
    return f'<a class="btn btn-gold cf-join" href="#register" data-magnet{extra}>{label} {ARR}</a>'
CF_TICK = '<svg viewBox="0 0 28 28" aria-hidden="true"><circle cx="14" cy="14" r="11"/><path d="M9 14.5l3.6 3.6L19 10.5"/></svg>'

def _ic(inner, vb="0 0 48 48"):
    return f'<svg viewBox="{vb}" aria-hidden="true">{inner}</svg>'
IC = {
 "target": _ic('<circle cx="24" cy="24" r="16"/><circle cx="24" cy="24" r="9"/><circle cx="24" cy="24" r="2.5"/><path d="M24 24l13-13M33 9l6-1-1 6"/>'),
 "people": _ic('<circle cx="24" cy="17" r="6"/><path d="M12 38c0-7 5-11 12-11s12 4 12 11"/><circle cx="10" cy="21" r="4"/><circle cx="38" cy="21" r="4"/><path d="M2 36c0-5 3-8 8-8M46 36c0-5-3-8-8-8"/>'),
 "mega": _ic('<path d="M8 20v8l22 9V11z"/><path d="M30 18c5 1 8 3 8 6s-3 5-8 6M14 28l2 9h5l-2-7"/>'),
 "chat": _ic('<path d="M8 12h32v20H22l-9 7v-7H8z"/><path d="M16 20h16M16 26h10"/>'),
 "cal": _ic('<rect x="8" y="11" width="32" height="29" rx="4"/><path d="M8 20h32M16 7v8M32 7v8M17 30l5 5 9-10"/>'),
 "bulb": ICON_BULB,
 "sprout": ICON_SPROUT,
 "chart": _ic('<path d="M8 40h32M12 34V26M21 34V20M30 34V14M39 34V8"/><path d="M10 22l10-8 8 4 11-10M33 8h6v6"/>'),
}

def header_logo_only():
    return f'''<header class="cf-logobar" id="syxHead"><a class="syx-logo v2-logo" href="{PATHS['home']}" aria-label="שי יפרח – לעמוד הבית"><span class="v2-mark" role="img" aria-label="שי יפרח"><img src="https://nivcreative.com/wp-content/uploads/2026/11/ms-logo-crown.webp" alt="" width="553" height="337"><img src="https://nivcreative.com/wp-content/uploads/2026/11/ms-logo-text.webp" alt="" width="1242" height="337"></span><small>העצמה<i>|</i>הגשמה<i>|</i>תוצאות</small></a></header>
<span class="sp-skip-target" id="nv-main-content" tabindex="-1" style="display:block;position:relative;top:-90px"></span>'''

def header_conf():
    items = [("challenge", "על הכנס"), ("learn", "מה נלמד"), ("shay", "על שי"), ("faq", "שאלות ותשובות")]
    lis = "".join(f'<li><a href="#{i}">{l}</a></li>' for i, l in items)
    return f'''<header class="syx-head" id="syxHead"><div class="pill">
 <a class="syx-logo v2-logo" href="{PATHS['home']}" aria-label="שי יפרח – לעמוד הבית"><span class="v2-mark" role="img" aria-label="שי יפרח"><img src="https://nivcreative.com/wp-content/uploads/2026/11/ms-logo-crown.webp" alt="" width="553" height="337"><img src="https://nivcreative.com/wp-content/uploads/2026/11/ms-logo-text.webp" alt="" width="1242" height="337"></span><small>העצמה<i>|</i>הגשמה<i>|</i>תוצאות</small></a>
 <nav class="syx-nav" id="syxNav" aria-label="ניווט בעמוד"><ul>{lis}</ul></nav>
 <div class="actions"><a class="btn btn-gold btn-sm cf-join" href="#register" data-magnet><span class="d">להמשך ההרשמה</span><span class="m">להרשמה</span> {ARR}</a>
 <button class="menu-btn" type="button" aria-expanded="false" aria-controls="syxNav" aria-label="פתיחת תפריט"><span></span></button></div>
</div></header>
<span class="sp-skip-target" id="nv-main-content" tabindex="-1" style="display:block;position:relative;top:-90px"></span>'''

CONF_COUNT = '''<div class="cf-count" id="cfCount" role="timer" aria-label="הזמן שנותר עד תחילת הכנס"><p class="cf-count-t">הכנס מתחיל בעוד</p><div class="cf-count-g"><div><b data-u="d">00</b><span>ימים</span></div><div><b data-u="h">00</b><span>שעות</span></div><div><b data-u="m">00</b><span>דקות</span></div><div><b data-u="s">00</b><span>שניות</span></div></div></div>'''
CONF_COUNT_JS = '''<script>(function(){var box=document.getElementById('cfCount');if(!box)return;var T=Date.UTC(2026,10,16,14,0,0),E=Date.UTC(2026,10,16,19,0,0),el={};['d','h','m','s'].forEach(function(u){el[u]=box.querySelector('[data-u="'+u+'"]')});
function p(n){return n<10?'0'+n:''+n}
function tick(){var n=Date.now(),d=T-n;if(d<=0){if(n<E){box.innerHTML='<p class="cf-count-t">הכנס מתקיים עכשיו</p>'}else{box.style.display='none'}return false}
var s=Math.floor(d/1000);el.d.textContent=p(Math.floor(s/86400));el.h.textContent=p(Math.floor(s%86400/3600));el.m.textContent=p(Math.floor(s%3600/60));el.s.textContent=p(s%60);return true}
if(tick()){var iv=setInterval(function(){if(!tick())clearInterval(iv)},1000)}})();</script>'''

def conf_body():
    z = lambda n: f' style="--z:{n}"'
    words = lambda t, i0, cls="": " ".join(f'<span class="w"><span style="--i:{i0+k}"{cls}>{w}</span></span>' for k, w in enumerate(t.split()))
    hero = f'''<section class="syx-hero ms-hero cf-hero{" cf-hero2" if CONF_V2 else ""} ms-sec ms-dark" id="syxHero"{z(60)}>
 <div class="bg" role="img" aria-label="{'שי יפרח מרצה מול קהל באולם' if CONF_V2 else 'שי יפרח על הבמה מול קהל'}"></div><div class="sheen" aria-hidden="true"></div><div class="spot" aria-hidden="true"></div><canvas id="syxDust" aria-hidden="true"></canvas>
 <div class="wrap"><div class="copy">
  <p class="cf-pill hero-in">כנס פרונטלי ללא עלות עם שי יפרח</p>
  <h1 aria-label="{CONF_NAME}"><span class="ln">{words("הצלחה עסקית", 0, ' class="gold"')}</span><span class="ln">{words("למאמנות ומטפלים", 2, ' class="gold"')}</span></h1>
  <p class="cf-sublead hero-in" style="--i:1">{"יש לך את היכולת לעזור לאנשים.<br>עכשיו הזמן לבנות את העסק שסביבה." if CONF_V2 else "בואו נייצר מהכישרון והניסיון שלכם עסק מנצח!<br>מתחילים לדייק את הדרך בכנס של שי יפרח."}</p>
  <p class="cf-tagline hero-in" style="--i:2">מנטליות, שיווק וכלים מעשיים לצעד הבא בעסק שלך</p>
  <div class="cf-box hero-in" style="--i:3"><ul class="cf-tags"><li>כנס פרונטלי</li><li>ללא עלות</li><li>בהרשמה מראש</li></ul>
   <ul class="cf-meta"><li><svg class="cf-mi" viewBox="0 0 24 24" aria-hidden="true"><rect x="3.5" y="5" width="17" height="15.5" rx="3"/><path d="M3.5 10h17M8 3v4M16 3v4"/></svg><span class="sr-only">תאריך: </span>16.11</li><li><svg class="cf-mi" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/></svg><span class="sr-only">שעה: </span>16:00 עד 21:00</li><li><svg class="cf-mi" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21s7-6.2 7-11.5A7 7 0 0 0 5 9.5C5 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/></svg><span class="sr-only">מיקום: </span>מתחם מיי קמפוס, בני ברק</li></ul>
   {CONF_COUNT}
   {conf_btn(cta_label())}
   <p class="cf-note">פרטי הכנס והנחיות ההרשמה בקבוצת הוואטסאפ</p></div>
 </div></div></section>'''
    cols = ["להגיע ללקוחות הנכונים", "לשווק בביטחון", "לעבוד עם כיוון ברור"]
    pain = f'''<section class="ms-sec ms-dark ms-center cf-sec" id="challenge"{z(50)}><div class="wrap">
 <h2 class="ms-h2 cf-big rv">למדת לעזור לאחרים.<br><span class="cf-gold">אבל מי לימד אותך לבנות עסק?</span></h2>
 <p class="cf-lead rv" style="--i:1">השקעת בלימודים, רכשת כלים וצברת ניסיון. ובכל זאת, בין העבודה עם אנשים לבין שיווק, מכירות וניהול העסק, לפעמים חסרה דרך ברורה.</p>
 <ul class="cf-cols">{"".join(f'<li class="rv" style="--i:{k}">{c}</li>' for k, c in enumerate(cols))}</ul></div></section>'''
    topics = [("target", "קהל יעד ומסר ברור", "איך לזהות למי השירות שלך מתאים, להבין את הצורך שלו ולהסביר את הערך שלך בשפה שמייצרת חיבור."),
              ("people", "שירותים ותהליך לקוח", "איך לבנות הצעה ברורה ולתכנן את הדרך שבה אדם עובר מהיכרות ראשונה איתך להתעניינות ולעבודה משותפת."),
              ("mega", "שיווק שמבוסס על אמון", "איך לגשת ליצירת תוכן שמציג את הידע, הגישה והייחוד שלך, ומעודד פניות מהאנשים המתאימים."),
              ("chat", "שיחות מכירה בגישה אנושית", "איך לנהל שיחה מתוך הקשבה, לשאול שאלות מדויקות ולבדוק התאמה בלי לחץ מיותר."),
              ("cal", "סדר והתנהלות עסקית", "עקרונות לניהול פניות, לקוחות והכנסות, כדי לעבוד בצורה מסודרת ולקבל החלטות מתוך תמונה ברורה יותר."),
              ("bulb", "מנטליות שמובילה לפעולה", "איך לחבר בין חזון, אחריות אישית והרגלי פעולה, ולפתח חשיבה של מי שמוביל את העסק שלו.")]
    learn = f'''<section class="ms-sec ms-ivory ms-center cf-sec cf-ivory" id="learn"{z(40)}><div class="wrap">
 <h2 class="ms-h2 rv">{"מהשליחות שלך, לדרך עסקית ברורה" if CONF_V2 else "מה נלמד בכנס?"}</h2>
 <p class="cf-lead rv" style="--i:1">נכיר שישה תחומים מרכזיים שעוזרים לחבר בין היכולת המקצועית שלך לבין עסק שמתנהל בצורה מכוונת ומסודרת.</p>
 <ol class="cf-topics">{"".join(f'<li class="cf-topic rv" style="--i:{k % 3}"><span class="cf-ic">{IC[ic]}</span><h3>{h}</h3><p>{p}</p></li>' for k, (ic, h, p) in enumerate(topics))}</ol>
 <div class="cf-cta-row rv">{conf_btn("אני רוצה לקבל פרטים ולהמשיך להרשמה")}</div></div></section>'''
    stages = [("sprout", "בתחילת הדרך", "יש לך הכשרה בתחום האימון או הטיפול, ורצון להבין איך להפוך אותה להצעת שירות ברורה ולצעדים ראשונים בעסק."), ("people", "כבר עובדים עם לקוחות", "העסק פעיל, אבל יש רצון לשפר את השיווק, לנהל שיחות מכירה בביטחון ולהכניס יותר סדר להתנהלות."), ("chart", "רוצים להתפתח לשלב הבא", "יש בסיס קיים, והגיע הזמן לעצור, לדייק כיוון ולבחון איך לבנות תהליכים שתומכים בהמשך הצמיחה.")]
    fit = f'''<section class="ms-sec ms-dark ms-center cf-sec" id="fit"{z(30)}><div class="wrap">
 <h2 class="ms-h2 cf-gold-h rv">{"באיזה שלב העסק שלך נמצא היום?" if CONF_V2 else "למי הכנס מתאים?"}</h2>
 <div class="cf-stages{"" if CONF_V2 else " cf-compact"}">{"".join(f'<article class="cf-stage rv" style="--i:{k}"><span class="cf-ic">{IC[ic]}</span><h3>{h}</h3><p>{p}</p></article>' for k, (ic, h, p) in enumerate(stages))}</div>
 <p class="cf-closing rv">הכנס מתאים למי שרוצה ללמוד, לבחון את דרך הפעולה שלו ולתרגם רעיונות לצעדים מעשיים.</p></div></section>'''
    about = f'''<section class="ms-sec ms-dark ms-midnight cf-sec" id="shay"{z(20)}><div class="wrap cf-about">
 <div><h2 class="ms-h2 rv">נעים להכיר, שי יפרח</h2><p class="cf-gold-sub rv" style="--i:1">מחברים בין התפתחות אישית לעשייה עסקית</p>
 <p class="rv" style="--i:2">שי יפרח הוא מרצה להעצמה אישית ולפריצה עסקית, ובעלים של המרכז להכשרת קואוצ׳רים מנטליים ושל אקדמיית העסקים למאמנים, מטפלים ואנשי השפעה.</p>
 <p class="rv" style="--i:2">הגישה שלו מחברת בין התפתחות אישית לעשייה עסקית: להבין מה מניע אותנו, לחדד את הכיוון ולפתח כלים שמסייעים לפעול בצורה מכוונת.</p>
 <p class="rv" style="--i:3">מתוך הדרך האישית והעסקית שעבר, שי מביא לבמה ניסיון, תובנות וכלים מעשיים עבור אנשים שרוצים להפוך את היכולת שלהם לעזור לאחרים לעסק עם משמעות.</p></div>
 <div class="ms-photo rv" style="--i:2" role="img" aria-label="שי יפרח מרצה מול קהל"><div class="im" style="background:url({UP}spl-s6-speaking.webp) 70% 30%/cover;width:100%;height:100%"></div></div></div></section>'''
    stories = f'''<section class="ms-sec ms-dark ms-center cf-sec" id="stories-home"{z(15)}><div class="wrap"><h2 class="ms-h2 cf-gold-h rv">לשמוע ממי שכבר עברו דרך עם שי</h2><p class="cf-lead rv" style="--i:1">משתתפים משתפים בחוויה שלהם, בתובנות שלקחו ובצעדים שבחרו לעשות בהמשך. העדויות הן מתהליכים וסדנאות קודמים של שי, ולא מהכנס הנוכחי.</p></div>
 {slider_html() if CONF_V2 else slider_html([2, 6, 4, 1, 3, 5], 1)}</section>'''
    faq_items = [("האם ההשתתפות בכנס בתשלום?", "לא. לפי מתכונת האירוע שנמסרה, ההשתתפות ללא עלות ובהרשמה מראש."),
                 ("למי הכנס מיועד?", "למאמנות, מאמנים, מטפלות ומטפלים שרוצים לפתח את הצד העסקי של העשייה שלהם, בתחילת הדרך או כשהעסק כבר פעיל."),
                 ("איך נרשמים לכנס?", "מצטרפים לקבוצת הוואטסאפ הרשמית דרך הכפתור בעמוד, ומשלימים את השלבים בהתאם להנחיות בקבוצה."),
                 ("האם לחיצה על הכפתור מבטיחה מקום?", "לא. הלחיצה פותחת את קישור ההצטרפות לקבוצה. יש להשלים את תהליך ההרשמה לפי ההנחיות שיימסרו בה."),
                 ("האם הכנס מתאים גם למי שעדיין אין לו עסק פעיל?", "כן. הכנס מתאים גם לבעלי הכשרה בתחום שרוצים להבין איך להתחיל לבנות את הפעילות העסקית שלהם."),
                 ("האם הכנס מתקיים בזום?", "לא. זהו כנס פרונטלי שמתקיים ב-16.11, בין השעות 16:00 ל-21:00, במתחם מיי קמפוס בבני ברק."),
                 ("האם נדרש ניסיון בשיווק או במכירות?", "התוכן מיועד למאמנים ומטפלים בשלבים שונים של הדרך העסקית, כולל בתחילת הדרך. אין צורך להציג את עצמך כמומחה בשיווק כדי להתעניין ולהצטרף.")]
    faq = f'''<section class="ms-sec ms-dark ms-center cf-sec{"" if CONF_V2 else " cf-final"}" id="faq"{z(10 if CONF_V2 else 2)}><div class="wrap"><h2 class="ms-h2 rv">שאלות שעולות לפני שמצטרפים</h2>
 <div class="cf-faq">{"".join(f'<details class="rv" style="--i:{k % 3}"><summary>{q}</summary><div class="a">{a}</div></details>' for k, (q, a) in enumerate(faq_items))}</div></div></section>'''
    steps = [("people", "מצטרפים לקבוצה"), ("chat", "מקבלים הנחיות"), ("cal", "משלימים הרשמה")]
    final = f'''<section class="ms-sec ms-dark ms-center cf-sec cf-final" id="join"{z(2)}><div class="wrap"><div class="cf-frame rv">
 <h2 class="ms-h2 cf-big">יש לך מה לתת לעולם.<br><span class="cf-gold">בואו לבנות את הדרך קדימה.</span></h2>
 <p class="cf-lead">הכנס „{CONF_NAME}” הוא הזדמנות לעצור, לדייק את הכיוון ולקבל כלים לחשיבה ולעשייה עסקית, לצד ההתפתחות האישית שלך.</p>
 <ol class="cf-flow">{'<li class="cf-arrow" aria-hidden="true">←</li>'.join(f'<li><span class="cf-ic">{IC[ic]}</span>{t}</li>' for ic, t in steps)}</ol>
 <div class="cf-cta-row">{conf_btn("לקבוצת הכנס ולהמשך ההרשמה")}</div>
 <p class="cf-sub">ההצטרפות לקבוצה היא שלב בהליך ההרשמה ואינה מהווה אישור הרשמה או הבטחת מקום.</p></div></div></section>'''
    if CONF_V2: return hero + pain + learn + fit + about + stories + faq, final
    return hero + pain + learn + fit + about + stories, faq

CONF_FLOAT_JS = '''<script>(function(){var f=document.getElementById('cfFloat'),h=document.getElementById('syxHero');if(!f||!h||!('IntersectionObserver' in window)){if(f)f.classList.add('on');return}
new IntersectionObserver(function(e){f.classList.toggle('on',!e[0].isIntersecting)},{threshold:0}).observe(h)})();</script>'''

def conf_form(v2):
    src = json.load(open(os.path.join(HERE, "source", "live_contact.json"), encoding="utf-8"))
    f = None
    for w in widgets(src["elementor_data"]):
        if w["widgetType"] == "form": f = copy.deepcopy(w); break
    s = f["settings"]
    keep = [x for x in s["form_fields"] if x["custom_id"] in ("name", "phone", "service", "source_page")]
    for x in keep:
        if x["custom_id"] in ("name", "phone"): x["width"] = "40"; x["width_mobile"] = "100"
        if x["custom_id"] == "service": x["field_value"] = "כנס 16.11: הצלחה עסקית למאמנות ומטפלים"
        if x["custom_id"] == "source_page": x["field_value"] = PATHS["conference-v2" if v2 else "conference"]
    s["form_fields"] = keep
    nm = "דף הכנס (גרסה 2)" if v2 else "דף הכנס"
    s.update({"form_name": "MentorShay – " + nm, "button_text": ("להמשך ההרשמה לקבוצה" if v2 else "לשריין כרטיס לכנס"), "button_width": "30", "button_width_mobile": "100", "button_align": "stretch",
              "email_subject": "ליד חדש: " + nm, "redirect_to": CONF_JOIN,
              "success_message": "תודה! מעבירים אתכם לקבוצת הוואטסאפ של הכנס.",
              "field_background_color": "#FFFFFF", "field_text_color": "#000000", "field_border_color": "rgba(185,138,62,.45)",
              "button_background_color": "#E3B665", "button_text_color": "#14110A", "_css_classes": "shay-form"})
    return retarget_obj(f)

def conf_reg_head(v2=True):
    return f'''<section class="cf-reg{"" if v2 else " cf-reg-long"}" id="register" aria-labelledby="reg-t"><div class="wrap"><span class="ms-eyebrow rv">הרשמה לכנס</span><h2 class="ms-h2 rv" id="reg-t" style="--i:1">{"השאירו פרטים והמשיכו לקבוצת הכנס" if v2 else "רק לאחר הכניסה לקבוצה תוכלו לשריין כרטיס לכנס ושם תקבלו את כל העדכונים"}</h2>
 <p class="cf-lead rv" style="--i:2">אחרי שליחת הפרטים תועברו להזמנה לקבוצת הוואטסאפ הרשמית של הכנס, שבה מקבלים את הנחיות ההרשמה, פרטים ועדכונים.</p>
 <p class="cf-small rv" style="--i:2">הצטרפות לקבוצה אינה אישור הרשמה או הבטחת מקום. מעדיפים בלי להשאיר פרטים? <a href="{CONF_JOIN}" target="_blank" rel="noopener">אפשר להצטרף ישירות לקבוצה</a>.</p></div></section>'''

def build_conference(v2=False):
    global CONF_V2
    CONF_V2 = v2
    before, final = conf_body()
    float_btn = f'<div class="cf-float" id="cfFloat">{conf_btn(cta_label())}</div>'
    consent = f'<p class="cf-consent">בשליחת הפרטים אתם מאשרים יצירת קשר בנוגע לכנס ולפעילות של שי יפרח, בהתאם ל<a href="{PATHS["privacy"]}">מדיניות הפרטיות</a>.</p>'
    head = FONTS + "<style>" + css() + "\n" + rd("conf.css") + (rd("conf2.css") if v2 else "") + "</style>" + f'<div class="syx" id="top">{JSFLAG}<div class="syx-progress" aria-hidden="true"><i></i></div>' + (header_logo_only() if v2 else header_conf()) + before + conf_reg_head(v2) + "</div>"
    tail = f'<div class="syx">{JSFLAG}<div class="cf-consent-wrap">{consent}</div>{final}{footer()}{float_btn}</div>' + js() + rd("ms_slider.js") + CONF_FLOAT_JS + CONF_COUNT_JS
    wid = lambda code, i: {"id": "cfh%d" % i, "elType": "widget", "settings": {"html": code}, "elements": [], "widgetType": "html"}
    zero = {"column": "0", "row": "0", "isLinked": True, "unit": "px"}
    cont = {"id": "cffc", "elType": "container", "settings": {"flex_direction": "column", "content_width": "full", "flex_gap": zero, "padding": {"unit": "px", "top": "0", "right": "0", "bottom": "0", "left": "0", "isLinked": True}, "css_classes": "syx syx-wrapper cf-formcont"}, "elements": [{"id": "cffw", "elType": "container", "settings": {"flex_direction": "column", "content_width": "full", "flex_gap": zero, "css_classes": "cf-formwrap"}, "elements": [conf_form(v2)], "isInner": True}], "isInner": False}
    return [{"id": "msroot0", "elType": "container", "settings": {"flex_direction": "column", "content_width": "full", "flex_gap": zero, "padding": {"unit": "px", "top": "0", "right": "0", "bottom": "0", "left": "0", "isLinked": True}, "css_classes": "syx-root"}, "elements": [wid(head, 0), cont, wid(tail, 1)], "isInner": False}]

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
    return hero + marquee_html() + story + meet + serv + stories + contact

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


# ------------------------------------------------------------------ content improvements (source: shayyfrach.co.il)
COURSE_DURS = {"הקדמה והסיפור שלי": "7:18", "מהו אושר אמיתי והדרך אליו": "6:19", "ערך עצמי": "6:59", "ביטחון עצמי": "1:58", "דימוי עצמי": "4:23", "אפירמציות": "9:02", "אמונה עצמית": "9:43",
               "מציאת ייעוד": "19:35", "לקיחת עצות": "6:00", "סביבה מנצחת": "8:11", "עמוד שדרה": "11:12", "בניית תוכנית וחזון": "9:06", "קשיים": "4:35", "לקיחת סיכונים ופחדים": "10:58",
               "בניית שגרת יום מברזל": "11:28", "משמעת עצמית": "13:57", "שליטה במחשבות": "8:50", "אמונה": "10:03", "מכירות": "6:23", "כושר ותזונה": "8:41", "סיכום וכלים ליישום": "3:33"}

def patch_content(key, h):
    def rep(a, b2):
        nonlocal h
        assert a in h, (key, a[:60])
        h = h.replace(a, b2, 1)
    if key == "course":
        for t, d in COURSE_DURS.items():
            rep(f"<li>{t}</li>", f'<li>{t} <span class="dur">{d}</span></li>')
        rep("הקורס נשאר אצלכם לכל החיים.", "21 פרקים בווידאו, בסך הכול כשלוש שעות של תוכן (2:58), כך שאפשר להתקדם פרק אחרי פרק בקצב שלכם. הקורס נשאר אצלכם לכל החיים.")
    if key == "business":
        rep("ליווי פרקטי שמחבר בין העבודה הפנימית, ביטחון, מחשבות והחלטות, לבין הצעדים המעשיים של בניית עסק: בידול, תכנון, שיווק, כספים ומכירות.",
            "ליווי פרקטי למטפלות, למאמנים ולבעלי עסקים בתחום נותני השירותים. הוא מחבר בין העבודה הפנימית, ביטחון, מחשבות והחלטות, לבין הצעדים המעשיים של בניית עסק: בידול, תכנון, שיווק, כספים ומכירות.")
        rep("<li>בעלי עסקים, או מי שרוצה להקים עסק, בעיקר בתחום נותני השירותים</li>", "<li>מטפלות ומאמנים, בעלי עסקים או מי שרוצה להקים עסק, בעיקר בתחום נותני השירותים</li>")
        rep("שיווק אורגני וממומן, מיתוג, ופריצה ברשתות החברתיות.", "שיווק אורגני וממומן, מיתוג, יצירת תוכן ועמידה בביטחון מול מצלמה, ופריצה ברשתות החברתיות.")
    if key == "about":
        rep("הקים כמה עסקים בתחום נותני השירותים", "הקים כארבעה עסקים בתחום נותני השירותים")
    return h

def build_page(key, src_cache):
    if key == "home-v2": return build_home2()
    if key == "conference": return build_conference()
    if key == "conference-v2": return build_conference(v2=True)
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
    if key == "quiz":
        a = h0.index('<section class="sp-hero"'); c = h0.index('<section class="syx-contact"')
        page = sp_page([("שאלון התאמה", None)], "איזה מסלול", "מתאים לכם?", "שני שאלוני התאמה קצרים שעוזרים להבין איזה מסלול נראה הכי קרוב למה שאתם מחפשים: סדנה, ליווי עסקי, קורס דיגיטלי, פגישת 1:1 או תוכן ללא עלות.", quiz_sections())
        h0 = h0[:a] + fix_inline_styles(page) + h0[c:]
    if key == "privacy":
        a = h0.index('<section class="sp-hero"'); b = h0.index('<section class="syx-contact"')
        page = sp_page([("תקנון ומדיניות פרטיות", None)], "תקנון ומדיניות", "פרטיות.", "תנאי השימוש בשירותים, מדיניות הביטולים ופרטי העוסק.", privacy_sections(),
                       [("operator", "פרטי העוסק"), ("services", "השירותים"), ("cancel", "ביטולים"), ("ip", "קניין רוחני"), ("law", "סמכות שיפוט")])
        h0 = h0[:a] + fix_inline_styles(page) + h0[b:]
        h0 = h0.replace("הצהרת נגישות", "מדיניות פרטיות")
    h0 = patch_content(key, h0)
    w0["settings"]["html"] = with_style(h0)
    # footer / js widget
    wtail["settings"]["html"] = f'<div class="syx">{JSFLAG}{footer()}</div>' + js() + (rd("ms_slider.js") if key == "stories" else "") + (rd("quiz.js") if key == "quiz" else "")
    for w in ws:
        if w["widgetType"] == "form":
            w["settings"] = form_widget(w["settings"], key)
            if key == "quiz":
                fs = w["settings"]["form_fields"]
                for f in fs:
                    if f["custom_id"] == "service": f["field_value"] = "שאלון התאמה"
                    if f["custom_id"] == "source_page": f["field_value"] = PATHS["quiz"]
                fs.insert(-2, {"custom_id": "quiz_result", "field_type": "hidden", "field_label": "תוצאת שאלון", "field_value": "לא מולא שאלון", "width": "100", "_id": "qzres01"})
                w["settings"]["form_name"] = "MentorShay – שאלון התאמה"
                w["settings"]["email_subject"] = "ליד חדש: שאלון התאמה"
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
    "quiz": ("שאלון התאמה: איזה מסלול מתאים לכם? | שי יפרח", "שני שאלוני התאמה קצרים שמכוונים אתכם למסלול המתאים: סדנה, ליווי עסקי, קורס דיגיטלי, פגישת 1:1 או תוכן ללא עלות."),
    "conference": ("הצלחה עסקית למאמנות ומטפלים: כנס פרונטלי עם שי יפרח", "כנס פרונטלי ללא עלות עם שי יפרח למאמנות, מאמנים, מטפלות ומטפלים: מנטליות, שיווק ושיחות מכירה בגישה אנושית. ההצטרפות לקבוצת הוואטסאפ הרשמית היא שלב בהרשמה."),
    "conference-v2": ("הצלחה עסקית למאמנות ומטפלים: כנס פרונטלי עם שי יפרח | גרסה 2", "סקיצה נוספת (v2) לדף הנחיתה של הכנס, עם תמונת רקע חדשה לבלוק הראשי."),
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
        sr = call("POST", "/surerank/v1/post/settings", {"post_id": r["id"], "metaData": {"page_title": seo_title, "page_description": seo_desc, "post_no_index": ("yes" if (key in ("thank-you", "partners-thanks", "conference-v2") or keep != "publish") else "no"), "post_no_follow": ""}})
        print(f'{key:17s} id={r["id"]} status={r["status"]} parent={r.get("parent")} slug={r["slug"]} seo={sr.get("success", sr)}')
    subprocess.run(["curl", "-sS", "-m", "60", "-X", "DELETE", SITE + "/wp-json/elementor/v1/cache"], capture_output=True)

if __name__ == "__main__":
    if len(sys.argv) > 1 and sys.argv[1] == "preview": preview()
    elif len(sys.argv) > 1 and sys.argv[1] == "push": push(sys.argv[2:] or None)
