#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""Builds /new-customer-v2/ on shayyfrach.co.il: a premium, GSAP-driven redesign of /new-customer/ (the original is never touched).
   Content = the original page's content (+ the academy copy that already exists on the mentorshay home page). No form, no sales CTA.
   The page is made of separate Elementor HTML widgets (one per section) so sections can be re-ordered in Elementor.

   SHAY_AUTH='Basic ...' python3 newcustomer_v2.py preview   # /tmp/ms/nc2/preview.html (+ a media-free structure check)
   SHAY_AUTH='Basic ...' python3 newcustomer_v2.py page      # creates/updates the V2 page as a DRAFT (+ noindex via Yoast); verifies the original is unchanged
The credential comes from the environment only (never stored in the repo).
"""
import hashlib, json, os, re, subprocess, sys, time
HERE = os.path.dirname(os.path.abspath(__file__)); sys.path.insert(0, HERE)
import newcustomer as n1   # constants + api helper (reads SHAY_AUTH)
import build

SLUG, ORIG_ID = "new-customer-v2", 6438
UP = n1.UP
IC = build.IC
FONTS = '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Heebo:wght@300;400;500;700;800;900&family=Frank+Ruhl+Libre:wght@500;700;900&display=swap" rel="stylesheet">'
PLAY = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg>'
UPARROW = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 19V5M5 12l7-7 7 7"/></svg>'
PIN = '<svg viewBox="0 0 48 48" aria-hidden="true"><path d="M24 43s13-10.5 13-21a13 13 0 0 0-26 0c0 10.5 13 21 13 21z"/><circle cx="24" cy="22" r="4.5"/></svg>'
LOCK = '<svg viewBox="0 0 48 48" aria-hidden="true"><rect x="11" y="22" width="26" height="19" rx="4"/><path d="M17 22v-6a7 7 0 0 1 14 0v6M24 29v6"/></svg>'
def svg64(inner): return f'<svg viewBox="0 0 64 64" aria-hidden="true">{inner}</svg>'
PILLAR_ICONS = [
    svg64('<circle cx="32" cy="32" r="20"/><circle cx="32" cy="32" r="8"/><path d="M32 6v8M32 50v8M6 32h8M50 32h8"/>'),
    svg64('<path d="M32 8a17 17 0 0 0-10 30.8V46h20v-7.2A17 17 0 0 0 32 8z"/><path d="M25 52h14M28 58h8"/>'),
    svg64('<path d="M10 52C20 22 44 46 54 14"/><circle cx="10" cy="52" r="4"/><circle cx="54" cy="14" r="4"/><circle cx="31" cy="34" r="3"/>'),
    svg64('<path d="M8 54h14V40h14V26h14V12"/><path d="M40 12h10v10"/>'),
]
CHAPTERS = [("hero", "פתיחה"), ("challenge", "מכירים את זה"), ("meet", "הכירו את שי"), ("manifesto", "הגישה"), ("action", "שי בפעולה"), ("pillars", "ארבעה עמודים"), ("voices", "קולות מהדרך"), ("meeting", "הפגישה"), ("reflect", "התבוננות"), ("finale", "סיום")]

def logo(cls="mk", lazy=False):
    l = ' loading="lazy"' if lazy else ""
    return (f'<span class="{cls}" role="img" aria-label="שי יפרח"><img src="{UP}ms-logo-crown.webp" alt="" width="553" height="337"{l}><img src="{UP}ms-logo-text.webp" alt="" width="1242" height="337"{l}></span>')

def video(vid, label, sub, poster, src):
    return f'''<div class="nc2-vwrap"><div class="nc2-video" id="{vid}" data-video="{vid}" data-src="{src}" data-vtt="" data-rv style="background-image:url({poster})" role="group" aria-label="{label}">
 <button class="nc2-play" type="button" aria-label="נגן סרטון: {label}">{PLAY}</button>
 <div class="nc2-vlabel"><span>{label}</span><small>{sub}</small></div></div></div>'''

def w_setup():
    rail = "".join(f'<i data-for="{k}"><b>{t}</b></i>' for k, t in CHAPTERS)
    return (FONTS + "<style>" + open(os.path.join(HERE, "nc2.css"), encoding="utf-8").read() + "</style>"
            "<script>(function(){var h=document.documentElement;if(!matchMedia('(prefers-reduced-motion: reduce)').matches)h.classList.add('nc2-js')})()</script>"
            f'<div class="nc2"><div class="nc2-bar" aria-hidden="true"><i></i></div><div class="nc2-rail" aria-hidden="true">{rail}</div><div class="nc2-glow" aria-hidden="true"></div></div>'
            '<span id="nv-main-content" tabindex="-1" style="display:block;position:relative"></span>')

def w_hero():
    return f'''<div class="nc2"><section class="nc2-sec nc2-hero" id="nc2-top" data-ch="hero" aria-labelledby="nc2-h1">
 <img class="nc2-hero-img" src="{UP}ms-hero-stage.webp" alt="שי יפרח מחייך על הבמה" width="1672" height="941" fetchpriority="high" decoding="async">
 <svg class="nc2-rings" viewBox="0 0 400 400" aria-hidden="true"><circle cx="200" cy="200" r="190"/><circle cx="200" cy="200" r="140"/><circle cx="200" cy="200" r="90"/></svg>
 <div class="nc2-halo" aria-hidden="true"></div><canvas class="nc2-stars" aria-hidden="true"></canvas>
 <div class="nc2-logo"><a href="/" aria-label="שי יפרח – לאתר הראשי">{logo()}<small>העצמה<i>|</i>הגשמה<i>|</i>תוצאות</small></a></div>
 <div class="nc2-wrap"><div class="nc2-hero-copy">
  <p class="nc2-eyebrow">שי יפרח | פריצה עסקית</p>
  <h1 id="nc2-h1" aria-label="הפגישה שלך כבר נקבעה. עכשיו מתחילה ההיכרות שלנו"><span class="l1" data-split>הפגישה שלך כבר נקבעה.</span><span class="l2" data-split data-gold>עכשיו מתחילה ההיכרות שלנו</span></h1>
  <p class="nc2-lead">לפני שניפגש, הכנו לך כמה דקות של תוכן שיעזרו לך להבין מי אנחנו, איך אנחנו חושבים על הצלחה עסקית ומה מחכה לך בפגישה.</p>
  <div class="nc2-chips" aria-label="מה בדרך"><span>הכרות עם שי</span><span>הבנת הדרך</span><span>הכנה לפגישה</span></div>
  <a class="nc2-cue" href="#nc2-s-challenge"><span class="ln" aria-hidden="true"></span>גוללים להמשך</a>
 </div></div></section></div>'''

def w_challenge():
    cards = [("chart", "הלקוחות לא מגיעים בצורה עקבית, וההכנסה משתנה מחודש לחודש."), ("mega", "יש רצון לשווק ולמכור, אבל משהו בפנים עוצר."), ("sprout", "יש תחושה של פוטנציאל גדול, בלי דרך מספיק ברורה לממש אותו.")]
    cs = "".join(f'<li class="nc2-card" data-rv><span class="no">0{k+1}</span>{IC[ic].replace("viewBox=\"0 0 48 48\"", "viewBox=\"0 0 48 48\"")}<p>{t}</p></li>' for k, (ic, t) in enumerate(cards))
    return f'''<div class="nc2"><section class="nc2-sec nc2-challenge" id="nc2-s-challenge" data-ch="challenge" aria-labelledby="nc2-h-ch"><span class="nc2-bgnum" aria-hidden="true">01</span><div class="nc2-wrap">
 <p class="nc2-eyebrow" data-rv><b>01</b> מכירים את זה?</p>
 <h2 class="nc2-h2 wide" id="nc2-h-ch" data-split>יש לך מקצוע. יש לך יכולת. אבל האם יש לך את העסק שרצית?</h2>
 <p class="nc2-lead" data-rv>לא מעט מאמנים ומטפלים משקיעים שנים בלימודים, בהתפתחות אישית ובשיפור היכולת המקצועית שלהם.<br>ועדיין, כשהם מסתכלים על העסק שלהם, הם פוגשים מציאות אחרת.</p>
 <ul class="nc2-cards">{cs}</ul>
 <p class="nc2-quote nc2-serif" data-rv>אם משהו כאן מוכר לך, <em>זו בדיוק הסיבה שאנחנו נפגשים</em></p>
</div></section></div>'''

def w_meet():
    return f'''<div class="nc2"><section class="nc2-sec nc2-meet" id="nc2-s-meet" data-ch="meet" aria-labelledby="nc2-h-meet"><span class="nc2-bgnum" aria-hidden="true">02</span><div class="nc2-wrap"><div class="nc2-split">
 <div class="nc2-copy"><p class="nc2-eyebrow" data-rv><b>02</b> ההיכרות</p>
  <h2 class="nc2-h2" id="nc2-h-meet" data-split>נעים להכיר, שי יפרח</h2>
  <p class="nc2-lead" data-rv>לפני שמדברים על פתרונות, חשוב לנו שתכירו את האדם ואת דרך החשיבה שמאחורי התהליך.</p></div>
 {video("nc2-v-intro", "היכרות עם שי", "4:27 דקות", UP + "shay-intro-poster.webp", n1.INTRO_VIDEO)}
</div></div></section></div>'''

def w_manifesto():
    return '''<div class="nc2"><section class="nc2-sec nc2-manifesto" id="nc2-s-manifesto" data-ch="manifesto" aria-label="הגישה של אקדמיית העסקים"><div class="nc2-wrap">
 <p class="nc2-eyebrow" data-rv><b>03</b> הגישה</p>
 <p class="big" data-manifest style="margin-top:22px">אקדמיית העסקים של שי יפרח מחברת בין התפתחות אישית, חשיבה עסקית וכלים מעשיים.</p>
 <p class="big" data-manifest><span class="nc2-gold">קודם בונים את האדם. משם מקדמים את העסק.</span></p>
 <p class="sub" data-rv>מרחב למטפלות ומאמנים שרוצים לחדד את הכיוון, לחזק את הביטחון ולהפוך את הידע והשליחות שלהם לעסק עם משמעות.</p>
</div></section></div>'''

def w_action():
    return f'''<div class="nc2"><section class="nc2-sec nc2-action" id="nc2-s-action" data-ch="action" aria-labelledby="nc2-h-act"><span class="nc2-bgnum" aria-hidden="true">04</span><div class="nc2-wrap"><div class="nc2-split">
 {video("nc2-v-action", "שי בפעולה", "1:24 דקות", UP + "shay-in-action-poster.webp", n1.ACTION_VIDEO)}
 <div class="nc2-copy"><p class="nc2-eyebrow" data-rv><b>04</b> שי בפעולה</p>
  <h2 class="nc2-h2" id="nc2-h-act" data-split>איך שי מסתכל על עסק שרוצה לפרוץ קדימה?</h2>
  <p class="nc2-lead" data-rv>לפעמים שינוי משמעותי מתחיל דווקא משאלה אחת נכונה.<br>הנה הצצה לדרך שבה שי מסתכל על אתגרים עסקיים, מזהה חסמים ועוזר לבעלי עסקים לחשוב אחרת.</p></div>
</div></div></section></div>'''

def w_pillars():
    P = [("בהירות", "CLARITY", "להגדיר למי אתם עוזרים, מה מייחד אתכם ומה הצעד הבא של העסק."),
         ("מיינדסט", "MINDSET", "לחזק את האמונה ביכולות שלכם ולהציג את השירות והערך שלכם בביטחון."),
         ("אסטרטגיה", "STRATEGY", "לבנות הצעה ברורה ולתכנן את הדרך מהיכרות ראשונה ועד עבודה משותפת, בשיווק מדויק ובשיחות מכירה בגישה אנושית."),
         ("יישום", "IMPLEMENTATION", "לתרגם מטרות לתוכנית מעשית, להתמיד ולבחון את ההתקדמות לאורך הדרך.")]
    big = "".join(f'<div class="it"><span class="e">{e}</span><span class="n">0{k+1}</span><span class="t nc2-gold">{he}</span></div>' for k, (he, e, _) in enumerate(P))
    lst = "".join(f'<li class="nc2-pill">{PILLAR_ICONS[k]}<div><h3>{he}</h3><p>{tx}</p></div></li>' for k, (he, e, tx) in enumerate(P))
    return f'''<div class="nc2"><section class="nc2-sec nc2-pillars" id="nc2-s-pillars" data-ch="pillars" aria-labelledby="nc2-h-pil"><div class="nc2-pstage"><div class="nc2-wrap">
 <div class="nc2-phead"><p class="nc2-eyebrow" data-rv><b>05</b> מהי הצלחה עסקית באמת?</p>
  <h2 class="nc2-h2 wide" id="nc2-h-pil" data-split>ארבעה עמודי תווך שמלווים את הדרך</h2></div>
 <div class="nc2-pgrid"><div class="nc2-pbig" aria-hidden="true">{big}</div><ol class="nc2-plist">{lst}</ol></div>
</div></div></section></div>'''

def w_voices():
    return f'''<div class="nc2"><section class="nc2-sec nc2-voices" id="nc2-s-voices" data-ch="voices" aria-labelledby="nc2-h-voc"><span class="nc2-bgnum" aria-hidden="true">06</span><div class="nc2-wrap"><div class="nc2-split">
 <div class="nc2-copy"><p class="nc2-eyebrow" data-rv><b>06</b> קולות מהדרך</p>
  <h2 class="nc2-h2 wide" id="nc2-h-voc" data-split>גם הם התחילו עם שאלות וחששות</h2>
  <p class="nc2-lead" data-rv>זה טבעי לתהות האם אפשר לעשות שינוי אמיתי בעסק והאם התהליך מתאים לך.<br>במקום להבטיח הבטחות, אנחנו מעדיפים לתת לאנשים שעבדו איתנו לספר על הדרך שלהם.</p>
  <p class="nc2-note" data-rv>כל עדות משקפת חוויה אישית של המשתתף. היא אינה הבטחה לתוצאה דומה.</p></div>
 {video("nc2-v-voices", "עדויות", "2:34 דקות", UP + "shay-testimonials-poster.webp", n1.VOICES_VIDEO)}
</div></div></section></div>'''

def w_meeting():
    steps = [(PIN, "איפה הדברים עומדים היום"), (IC["target"], "לאן היית רוצה להגיע"), (LOCK, "מה כרגע מונע ממך להתקדם")]
    st = "".join(f'<li class="nc2-step" data-rv><span class="nc2-node">{ic}</span><span class="k">0{k+1}</span><h3>{t}</h3></li>' for k, (ic, t) in enumerate(steps))
    return f'''<div class="nc2"><section class="nc2-sec nc2-meeting nc2-center" id="nc2-s-meeting" data-ch="meeting" aria-labelledby="nc2-h-meet2"><span class="nc2-bgnum" aria-hidden="true">07</span><div class="nc2-wrap">
 <p class="nc2-eyebrow" data-rv><b>07</b> לקראת הפגישה</p>
 <h2 class="nc2-h2 wide" id="nc2-h-meet2" data-split>בפגישה הקרובה, העסק שלך במרכז</h2>
 <p class="nc2-lead" data-rv>אנחנו הולכים להכיר אותך ואת העסק שלך, ולהבין:</p>
 <div class="nc2-steps"><svg class="nc2-pathsvg" viewBox="0 0 1000 90" preserveAspectRatio="none" aria-hidden="true"><path d="M0 45 C120 5 220 85 340 45 S560 5 680 45 S900 85 1000 45"/></svg><ol style="display:contents">{st}</ol></div>
 <p class="nc2-aim nc2-serif" data-rv>המטרה היא לייצר בהירות ולבדוק התאמה לשני הצדדים.</p>
</div></section></div>'''

def w_reflect():
    Q = [("איפה הדברים עומדים היום?", "חשבו על ההכנסות, הלקוחות והשגרה: מה עובד, ומה פחות?"),
         ("לאן הייתי רוצה להגיע?", "דמיינו את העסק שלכם בעוד שנה: איך הוא נראה, ואיך אתם מרגישים בו?"),
         ("מה כרגע מונע ממני להתקדם?", "מה אתם אומרים לעצמכם כשאתם נעצרים? איזו החלטה כבר זמן רב נדחית?")]
    qs = "".join(f'''<li data-rv><button class="nc2-q" type="button" aria-expanded="false" aria-controls="nc2-q{k}"><span class="no">0{k+1}</span><h3>{q}</h3><span class="more" id="nc2-q{k}"><span style="overflow:hidden;display:block"><p>{a}</p></span></span><span class="tg">לחצו כדי להתבונן</span></button></li>''' for k, (q, a) in enumerate(Q))
    return f'''<div class="nc2"><section class="nc2-sec nc2-reflect nc2-center" id="nc2-s-reflect" data-ch="reflect" aria-labelledby="nc2-h-ref"><span class="nc2-bgnum" aria-hidden="true">08</span><div class="nc2-wrap">
 <p class="nc2-eyebrow" data-rv><b>08</b> התבוננות קצרה</p>
 <h2 class="nc2-h2 wide" id="nc2-h-ref" data-split>שלוש שאלות לקחת איתכם לפגישה</h2>
 <p class="nc2-lead" data-rv>קחו כמה דקות להתבונן בהן. אין תשובות נכונות או לא נכונות.</p>
 <ul class="nc2-qs">{qs}</ul>
 <p class="nc2-ready" role="status" aria-live="polite">מעולה. עכשיו אתם מוכנים לפגישה.</p>
</div></section></div>'''

def w_finale():
    return f'''<div class="nc2"><section class="nc2-sec nc2-finale" id="nc2-s-finale" data-ch="finale" aria-labelledby="nc2-h-fin">
 <img class="nc2-finale-img" src="{UP}ms-story-wide.webp" alt="" width="1672" height="941" loading="lazy" decoding="async"><canvas class="nc2-stars" aria-hidden="true"></canvas>
 <div class="nc2-wrap">
  <h2 id="nc2-h-fin" data-split>מחכים להכיר אותך ולשמוע את הסיפור שלך</h2>
  <p class="nc2-sign nc2-gold-wrap"><span>נתראה בקרוב!</span> <span class="nc2-heart">❤️</span><svg viewBox="0 0 400 130" preserveAspectRatio="none" aria-hidden="true"><path d="M30 70 C 22 20 210 4 330 22 C 400 36 396 100 280 114 C 160 128 20 112 22 66 C 24 34 120 10 200 8"/></svg></p>
  <p><a class="nc2-top" href="#nc2-top">{UPARROW}לחזור להתחלה</a></p>
 </div></section></div>'''

def w_foot():
    js = open(os.path.join(HERE, "nc2.js"), encoding="utf-8").read()
    return f'''<div class="nc2"><footer class="nc2-foot"><div class="nc2-wrap"><a href="/" aria-label="שי יפרח – לאתר הראשי">{logo("mk", True)}</a>
 <nav aria-label="קישורי תחתית"><a href="{n1.PRIVACY}">מדיניות פרטיות</a><a href="{n1.ACCESS}">הצהרת נגישות</a></nav></div></footer></div><script>{js}</script>'''

def widgets():
    fns = [w_setup, w_hero, w_challenge, w_meet, w_manifesto, w_action, w_pillars, w_voices, w_meeting, w_reflect, w_finale, w_foot]
    return [{"id": "nc2w%02d" % i, "elType": "widget", "settings": {"html": f()}, "elements": [], "widgetType": "html"} for i, f in enumerate(fns)]

def elementor():
    z = {"column": "0", "row": "0", "isLinked": True, "unit": "px"}
    return [{"id": "nc2root0", "elType": "container", "settings": {"flex_direction": "column", "content_width": "full", "flex_gap": z, "padding": {"unit": "px", "top": "0", "right": "0", "bottom": "0", "left": "0", "isLinked": True}, "css_classes": "nc2-root"}, "elements": widgets(), "isInner": False}]

def sha(s): return hashlib.sha256(s.encode("utf-8")).hexdigest()[:16]

def original_state():
    r = n1.api("GET", f"/wp/v2/pages/{ORIG_ID}?context=edit&_fields=modified,status,meta")
    assert isinstance(r.get("meta"), dict), ("could not read the original page (anti-bot?)", str(r)[:100])
    return {"modified": r["modified"], "status": r["status"], "data": sha(r["meta"]["_elementor_data"]), "settings": r["meta"].get("_elementor_page_settings")}

def page():
    before = None
    for _ in range(6):
        try: before = original_state(); break
        except AssertionError: time.sleep(8)
    assert before, "original page unreadable; retry later"
    print("original before:", before["modified"], before["status"], before["data"])
    ex = None
    for _ in range(40):   # the host sometimes shows an anti-bot interstitial for a few minutes: wait it out instead of failing
        ex = n1.api("GET", f"/wp/v2/pages?slug={SLUG}&status=any&context=edit&_fields=id,status")
        if isinstance(ex, list): break
        time.sleep(20)
    assert isinstance(ex, list), ("slug lookup failed, not creating a duplicate page; retry", str(ex)[:120])
    pid = ex[0]["id"] if ex else None
    settings = before["settings"] or {"hide_title": "yes", "background_background": "classic", "background_color": "#0B0B0C"}
    payload = {"title": "הכנה לפגישת פריצה עסקית | שי יפרח (V2)", "slug": SLUG, "status": ex[0]["status"] if pid else "draft", "template": "elementor_canvas",
               "meta": {"_elementor_edit_mode": "builder", "_elementor_template_type": "wp-page", "_elementor_version": "4.4.0", "_elementor_data": json.dumps(elementor(), ensure_ascii=False), "_elementor_page_settings": settings}}
    r = n1.api("POST", f"/wp/v2/pages/{pid}" if pid else "/wp/v2/pages", payload)
    print({k: r.get(k) for k in ("id", "status", "link", "slug", "code", "message")})
    if "id" in r:
        s = n1.api("POST", "/wp-abilities/v1/abilities/yoast-seo/update-post-seo-data/run", {"input": {"post_id": r["id"], "noindex": True, "seo_title": "הכנה לפגישת פריצה עסקית | שי יפרח", "meta_description": "הכנה קצרה לקראת פגישת פריצה עסקית עם שי יפרח."}})
        print("yoast noindex:", "post_id" in s)
    subprocess.run(["curl", "-sS", "-m", "60", "-X", "DELETE", n1.TARGET + "/wp-json/elementor/v1/cache", "-H", "Authorization: " + n1.AUTH], capture_output=True)
    after = original_state()
    print("original after: ", after["modified"], after["status"], after["data"])
    assert after == before, "THE ORIGINAL PAGE CHANGED"
    print("original page verified unchanged")

if __name__ == "__main__":
    if sys.argv[1] == "preview":
        os.makedirs("/tmp/ms/nc2", exist_ok=True)
        open("/tmp/ms/nc2/preview.html", "w", encoding="utf-8").write('<!doctype html><html lang="he" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>new-customer-v2</title></head><body style="margin:0;background:#08080a">' + "".join(w["settings"]["html"] for w in widgets()) + "</body></html>")
        print("preview written", sum(len(w["settings"]["html"]) for w in widgets()) // 1024, "KB")
    elif sys.argv[1] == "page": page()
