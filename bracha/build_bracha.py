#!/usr/bin/env python3
"""Builds bracha-elementor.json: Elementor containers + native widgets (heading / text-editor /
button / image) with page-scoped CSS (.bracha-landing). Publish with publish.py."""
import json, os, re
HERE = os.path.dirname(os.path.abspath(__file__))
css = open(os.path.join(HERE, 'bracha.css'), encoding='utf-8').read()
css = css.replace(".bracha-landing .elementor-widget{margin:0!important;width:auto;max-width:100%}",
                  ".bracha-landing .elementor-widget{margin:0!important}")
css += """
.bracha-landing .br-header-w{position:sticky!important;top:0;z-index:60;width:100%}
.bracha-landing .br-header{position:relative;top:auto}
@media(min-width:1024px){.bracha-landing .br-nav{position:absolute;left:50%;top:0;height:84px;transform:translateX(-50%);align-items:center;margin:0;padding:0}}
.bracha-landing .br-dash{display:block;width:34px;height:12px;background:repeating-linear-gradient(100deg,#EFB629 0 3px,transparent 3px 8px)}
.bracha-landing .br-head-deco::before,.bracha-landing .br-head-deco::after{display:none!important;content:none!important}
.bracha-landing .br-final-in{padding-bottom:120px!important}
@media(min-width:768px){.bracha-landing .br-final-in{padding-bottom:70px!important}}
.bracha-landing .br-deco{position:absolute!important;bottom:0;z-index:1;width:30%;max-width:150px;pointer-events:none}
.bracha-landing .br-deco.l{left:0}.bracha-landing .br-deco.r{right:0}
.bracha-landing .br-deco svg{position:static;width:100%;height:auto}
.bracha-landing .br-head-deco{justify-content:center!important;align-items:center!important;width:100%;gap:12px}
.bracha-landing .br-head-deco::before,.bracha-landing .br-head-deco::after{flex:none}
@media(min-width:1024px){.bracha-landing .br-hero-photo img{-webkit-mask-image:linear-gradient(to bottom,#000 78%,transparent 100%),linear-gradient(to right,transparent 0,#000 10%,#000 62%,transparent 98%)}}
.bracha-landing .elementor-heading-title{font-family:'Heebo',sans-serif;color:var(--navy);line-height:inherit;padding:0;margin:0}
.bracha-landing .br-sub .elementor-heading-title,.bracha-landing .br-about-sub .elementor-heading-title{color:var(--teal)}
.bracha-landing .br-brush-1 .elementor-heading-title,.bracha-landing .br-brush-2 .elementor-heading-title{font-family:'Gveret Levin','Heebo',cursive;font-weight:400;color:var(--navy)}
.bracha-landing .br-h1 .elementor-heading-title{font-weight:800}
@media(max-width:1023px){.bracha-landing .br-hero-photo{width:64%!important;max-width:none!important;position:absolute!important}}
@media(min-width:1024px){.bracha-landing .br-hero-photo{width:600px!important;max-width:none!important;position:absolute!important;left:max(0px,calc(50% - 640px))!important;top:0!important}.bracha-landing .br-hero{min-height:650px}}
.bracha-landing .br-hero-copy{display:flex!important}
.bracha-landing .br-wa .elementor-button{background:linear-gradient(180deg,#0aa391,#007f73)!important;white-space:nowrap}
.bracha-landing .br-ghost .elementor-button{background:rgba(255,255,255,.6)!important}
.bracha-landing .br-hero,.bracha-landing .br-final{background:inherit}

.bracha-landing .br-values-grid,.bracha-landing .br-ach-box,.bracha-landing .br-about-grid,.bracha-landing .br-svc-list,.bracha-landing .br-testi-grid{display:grid!important}
.bracha-landing .br-card,.bracha-landing .br-ach-item{display:flex!important;flex-direction:column!important}
.bracha-landing .br-svc{display:grid!important}
.bracha-landing .br-head-deco{display:flex!important;flex-direction:row!important}
.bracha-landing .br-title{display:flex!important;flex-direction:row!important}
.bracha-landing .br-hero-copy,.bracha-landing .br-final-in,.bracha-landing .br-brush{display:flex!important;flex-direction:column!important}
.bracha-landing .br-hero-copy .elementor-widget,.bracha-landing .br-final-in .elementor-widget{max-width:100%}
.bracha-landing .br-btn-wrap{display:block!important}
.bracha-landing .br-btn-wrap .elementor-widget,.bracha-landing .br-btn-wrap .elementor-button{width:100%}
.bracha-landing .br-ghost{width:auto}
"""
U = 'https://nivcreative.com/wp-content/uploads/2026/11/bracha-'
IMG = {'hero': (3649, U + 'hero-2.webp'), 'logo': (3644, U + 'logo.png'), 'kids': (3648, U + 'kids-2.webp')}
WA = 'https://wa.link/2qo7tw'
SEO_TITLE = 'ברכה ברנס | מנטורית לגיל הרך והכנה לכיתה א׳'
SEO_DESC = 'ברכה ברנס, אשת חינוך ומנטורית לגיל הרך, בעלת מעל 20 שנות ניסיון. הכנה מיטבית לכיתה א׳, פיתוח ביטחון עצמי, מסוגלות ורכישת קריאה בשיטה ייחודית.'

_n = [0]
def rid():
    _n[0] += 1
    return '%08x' % (0xb4ac0000 + _n[0])

def W(wt, cls='', **s):
    st = dict(s)
    if cls: st['_css_classes'] = cls
    return {"id": rid(), "elType": "widget", "widgetType": wt, "settings": st, "elements": []}

def C(children, cls='', eid=None, inner=True, **s):
    st = {"content_width": "full", "flex_direction": "column", "css_classes": cls,
          "padding": {"unit": "px", "top": "0", "right": "0", "bottom": "0", "left": "0", "isLinked": True},
          "flex_gap": {"unit": "px", "column": "0", "row": "0", "isLinked": True}}
    if eid: st["_element_id"] = eid
    st.update(s)
    return {"id": rid(), "elType": "container", "isInner": inner, "settings": st, "elements": children}

def H(text, tag, cls): return W('heading', cls, title=text, header_size=tag)
def T(html, cls=''): return W('text-editor', cls, editor=html)
def HTML(code, cls=''): return W('html', cls, html=code)
def IMGW(key, cls, size='full'):
    i, url = IMG[key]
    return W('image', cls, image={"url": url, "id": i, "size": "", "alt": ""}, image_size=size)
def BTN(text, cls, wa=True):
    s = dict(text=text, link={"url": WA, "is_external": "on", "nofollow": "on", "custom_attributes": ""})
    if wa: s['selected_icon'] = {"value": "fab fa-whatsapp", "library": "fa-brands"}; s['icon_align'] = 'row-reverse'
    return W('button', cls, **s)

# ---------- SVG icons ----------
def svg(body, vb='0 0 48 48', cls='br-ic'):
    return '<svg class="%s" viewBox="%s" fill="none" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">%s</svg>' % (cls, vb, body)
TEAL, GOLD, PINK, NAVY, PUR = '#079B96', '#EFB629', '#E4476E', '#102B51', '#3B2A6B'
ICON = {
 'target': svg('<g stroke="%s" stroke-width="2.6"><circle cx="22" cy="26" r="15"/><circle cx="22" cy="26" r="9"/></g><circle cx="22" cy="26" r="3" fill="%s"/><path d="M22 26L40 8M34 6v8h8" stroke="%s" stroke-width="2.6"/>' % (TEAL, TEAL, TEAL)),
 'book': svg('<g stroke="%s" stroke-width="2.6"><path d="M24 12C19 8 11 8 5 10v26c6-2 14-2 19 2 5-4 13-4 19-2V10c-6-2-14-2-19 2z"/><path d="M24 12v26"/></g><path d="M12 16c3-.8 6-.6 8 .4M12 22c3-.8 6-.6 8 .4" stroke="%s" stroke-width="2"/>' % (GOLD, GOLD)),
 'sprout': svg('<g stroke="%s" stroke-width="2.6"><path d="M24 42V24"/><path d="M24 26C24 15 17 10 7 10c0 10 6 16 17 16z" fill="#dff3e4"/><path d="M24 30c0-8 6-12 16-12 0 8-6 12-16 12z" fill="#dff3e4"/></g>' % TEAL),
 'heart': svg('<path d="M24 41C10 31 6 23 6 17 6 11 10 8 15 8c4 0 7 2 9 6 2-4 5-6 9-6 5 0 9 3 9 9 0 6-4 14-18 24z" stroke="%s" stroke-width="2.6" fill="#fff0f4"/>' % PINK),
 'books': svg('<g stroke-width="2.4"><rect x="6" y="30" width="34" height="8" rx="2" fill="#fff" stroke="%s"/><rect x="9" y="22" width="30" height="8" rx="2" fill="#dff3ee" stroke="%s"/><rect x="7" y="14" width="32" height="8" rx="2" fill="#fde1e8" stroke="%s"/></g><path d="M24 12C20 8 24 4 24 6c0-2 4 2 0 6z" stroke="%s" stroke-width="2.2" fill="#fff"/>' % (NAVY, TEAL, PINK, GOLD)),
 'people': svg('<g stroke="%s" stroke-width="2.6"><circle cx="17" cy="16" r="6"/><circle cx="32" cy="18" r="5"/><path d="M5 40c0-8 5-13 12-13s12 5 12 13M30 28c7-1 13 3 13 11"/></g>' % PINK),
 'cap': svg('<g stroke="%s" stroke-width="2.4"><path d="M24 8L3 18l21 10 21-10z"/><path d="M11 23v10c4 4 8 5 13 5s9-1 13-5V23"/><path d="M45 18v12"/></g>' % PUR, cls=''),
 'trophy': svg('<g stroke="%s" stroke-width="2.4"><path d="M14 6h20v10c0 7-5 12-10 12S14 23 14 16z"/><path d="M14 10H7c0 7 3 11 8 12M34 10h7c0 7-3 11-8 12M24 28v8M16 42h16M18 36h12v6H18z"/></g>' % PUR, cls=''),
 'cal': svg('<g stroke="%s" stroke-width="2.4"><rect x="6" y="9" width="36" height="32" rx="5"/><path d="M6 19h36M15 5v8M33 5v8"/></g><g fill="%s"><circle cx="15" cy="27" r="1.8"/><circle cx="24" cy="27" r="1.8"/><circle cx="33" cy="27" r="1.8"/><circle cx="15" cy="34" r="1.8"/><circle cx="24" cy="34" r="1.8"/></g>' % (PUR, PUR), cls=''),
 'group': svg('<g stroke="%s" stroke-width="2.4"><circle cx="24" cy="14" r="5"/><circle cx="10" cy="18" r="4.2"/><circle cx="38" cy="18" r="4.2"/><path d="M14 40c0-7 4-12 10-12s10 5 10 12M2 38c0-6 3-10 8-10M46 38c0-6-3-10-8-10"/></g>' % PUR, cls=''),
 'chev': svg('<path d="M14 6l-8 8 8 8" stroke="%s" stroke-width="2.6"/>' % NAVY, vb='0 0 20 28', cls='br-svc-arrow'),
 'heart-gold': svg('<path d="M24 40C10 30 6 22 6 16.5 6 11 10 8 14.5 8c4 0 7 2 9.500 6 2.500-4 5.500-6 9.500-6C38 8 42 11 42 16.5 42 22 38 30 24 40z" stroke="%s" stroke-width="3" fill="none"/><path d="M38 4l2 5M43 9l-4 3M32 3l.5 4" stroke="%s" stroke-width="2.4"/>' % (GOLD, GOLD), cls='br-heart'),
}
WAVE = ('<svg viewBox="0 0 390 90" preserveAspectRatio="none" aria-hidden="true" focusable="false">'
        '<path d="M0 40C60 8 120 8 190 34s130 22 200-6v62H0z" fill="#ffe3ea"/>'
        '<path d="M0 58C70 30 130 38 200 56s130 10 190-14v48H0z" fill="#d9f2ec"/>'
        '<path d="M0 72C80 52 140 58 210 70s120 6 180-10v30H0z" fill="#fffaf5"/>'
        '<path d="M0 52C70 26 130 34 200 50s130 8 190-14" stroke="#EFB629" stroke-width="2.2" fill="none" opacity=".8"/></svg>')
DOODLE = ('<svg class="br-heart-doodle" style="top:150px;inset-inline-end:44%;width:54px" viewBox="0 0 60 60" fill="none" stroke="#EFB629" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
          '<path d="M30 52C14 41 8 31 8 23 8 15 13 11 19 11c5 0 8 3 11 8 3-5 6-8 11-8 6 0 11 4 11 12 0 8-6 18-22 29z"/><path d="M12 6l3 7M20 2l1 6M4 14l6 3"/></svg>')
DECO_L = ('<svg class="br-final-deco l" viewBox="0 0 150 140" aria-hidden="true" focusable="false"><rect x="8" y="108" width="120" height="22" rx="5" fill="#8fd8cc"/><rect x="16" y="86" width="108" height="22" rx="5" fill="#f9cfdb"/><rect x="10" y="66" width="116" height="20" rx="5" fill="#fbe3a8"/>'
          '<path d="M70 56C48 44 70 20 70 38c0-18 22 6 0 18z" fill="#fff" stroke="#EFB629" stroke-width="3"/></svg>')
DECO_R = ('<svg class="br-final-deco r" viewBox="0 0 150 150" aria-hidden="true" focusable="false"><path d="M60 128V96M60 100C60 80 78 66 104 70M60 100C60 76 40 62 22 66" stroke="#3a9b6a" stroke-width="5" fill="none" stroke-linecap="round"/>'
          '<path d="M104 70c-6 14-20 20-36 16 4-14 20-22 36-16z" fill="#6fc28d"/><path d="M22 66c8 10 24 14 36 8-6-12-22-16-36-8z" fill="#8fd1a5"/>'
          '<rect x="86" y="98" width="46" height="40" rx="8" fill="#f6d8b8"/><g stroke-width="7" stroke-linecap="round"><path d="M98 98V72" stroke="#EFB629"/><path d="M110 98V64" stroke="#E4476E"/><path d="M121 98V72" stroke="#079B96"/></g></svg>')

# ---------- header ----------
header = HTML('''<header class="br-header" id="br-header">
<div class="br-header-bar">
<a class="br-logo" href="#top" aria-label="ברכה ברנס – מנטורית לגיל הרך"><img src="%s" alt="ברכה ברנס – מנטורית לגיל הרך" width="558" height="369"></a>
<button class="br-burger" type="button" aria-expanded="false" aria-controls="br-nav" aria-label="פתיחת תפריט"><span></span><span></span><span></span></button>
</div>
<nav class="br-nav" id="br-nav" aria-label="תפריט ראשי"><a href="#top">ראשי</a><a href="#about">מי אני</a><a href="#specialties">תחומי התמחות</a><a href="#testimonials">המלצות</a><a href="#contact">יצירת קשר</a></nav>
</header>''' % IMG['logo'][1], 'br-header-w')

# ---------- hero ----------
hero = C([
    IMGW('hero', 'br-hero-photo'),
    C([
        H('ברכה ברנס', 'h1', 'br-h1'),
        H('מנטורית לגיל הרך', 'h2', 'br-sub'),
        C([H('נעים להכיר', 'p', 'br-brush-1'), H('קצת עליי', 'p', 'br-brush-2')], 'br-brush'),
        T('<p>הכנה רב־ממדית לכיתה א׳ – תהליך לבניית חוסן רגשי, עצמאות ומסוגלות.</p>', 'br-lead'),
        C([BTN('שלחי לי הודעה בוואטסאפ', 'br-wa')], 'br-btn-wrap'),
        W('button', 'br-ghost', text='להיכרות', link={"url": "#about", "is_external": "", "nofollow": ""}),
    ], 'br-hero-copy'),
    HTML('<div class="br-hero-waves">%s</div>' % WAVE),
], 'br-hero', eid='top')

# ---------- value cards ----------
def card(icon, text, color): return C([HTML(ICON[icon]), H(text, 'h3', '')], 'br-card br-reveal ' + color)
values = C([C([C([
    card('book', 'מומחית ברכישת קריאה', 'br-c-yellow'),
    card('target', 'מותאמת אישית לילדים שמתקשים', 'br-c-mint'),
    card('heart', 'חיזוק ביטחון, מסוגלות ועצמאות', 'br-c-pink'),
    card('sprout', 'ליווי מקצועי, חם ותומך', 'br-c-yellow'),
], 'br-values-grid')], 'br-in')], 'br-values')

# ---------- achievements ----------
def ach(icon, text): return C([HTML('<div class="br-ach-ic">%s</div>' % ICON[icon]), T('<p>%s</p>' % text)], 'br-ach-item')
achievements = C([C([C([
    ach('cap', 'בעלת תואר ראשון ושני בחינוך'),
    ach('trophy', 'זוכת תואר “מורת המאה” מטעם משרד החינוך'),
    ach('cal', 'מעל 20 שנות ניסיון בניהול גן חובה'),
    ach('group', 'ליווי מאות ילדים במעבר מהגן לכיתה א׳'),
], 'br-ach-box br-reveal')], 'br-in')], 'br-ach')

# ---------- about ----------
about = C([C([C([
    C([
        C([HTML(ICON['heart-gold']), H('מי אני?', 'h2', '')], 'br-title'),
        H('נעים להכיר – ברכה ברנס', 'h3', 'br-about-sub'),
        T('<p><strong>אני ברכה ברנס, אשת חינוך ומנטורית לגיל הרך.</strong></p>'
          '<p>בעלת תואר ראשון ושני בחינוך, זוכת תואר “מורת המאה” מטעם משרד החינוך, ומנהלת גן חובה מעל 20 שנה.</p>'
          '<p>לאורך השנים ליוויתי מאות ילדים במעבר מהגן לכיתה א׳, מתוך אמונה שכל ילד וילדה יכולים לצמוח ולממש את היכולות שלהם.</p>'
          '<p>אני מתמחה בהכנה מיטבית לכיתה א׳, תוך פיתוח מסוגלות, ביטחון עצמי ומיומנויות רגשיות, חברתיות ולימודיות.</p>'
          '<p>בנוסף, אני מתמחה בשיטת לימוד ייחודית לרכישת הקריאה, המיועדת לילדים שטרם רכשו את מיומנות הקריאה – בכל גיל.</p>'),
    ], 'br-about-text br-reveal'),
    C([IMGW('kids', '')], 'br-about-photo br-reveal'),
], 'br-about-grid')], 'br-in')], 'br-about', eid='about')

# ---------- services ----------
def svc(icon, title, desc, color):
    return C([HTML(ICON[icon]), C([H(title, 'h3', ''), T('<p>%s</p>' % desc)], 'br-svc-tx'), HTML(ICON['chev'])], 'br-svc br-reveal ' + color)
services = C([C([
    C([HTML('<span class="br-dash"></span>'), H('מה תמצאו אצלי?', 'h2', ''), HTML('<span class="br-dash"></span>')], 'br-head-deco'),
    C([
        svc('books', 'תהליך הכנה מקיף לכיתה א׳', 'הכנה מותאמת לקראת המעבר לכיתה א׳, המשלבת חיזוק מיומנויות רגשיות, חברתיות ולימודיות.', 'br-c-yellow'),
        svc('target', 'ליווי אישי מותאם לילד/ה', 'תהליך אישי המותאם לצרכים, ליכולות ולקצב ההתפתחות של כל ילד וילדה.', 'br-c-mint'),
        svc('people', 'הדרכות והשתלמויות לצוותי חינוך והורים', 'הענקת כלים מקצועיים ופרקטיים לחיזוק הביטחון, המסוגלות וההתפתחות של ילדים.', 'br-c-pink'),
    ], 'br-svc-list'),
], 'br-in')], 'br-services', eid='specialties')

# ---------- testimonials (hidden until real reviews are supplied) ----------
def quote(): return C([T('<p>הוסיפו כאן המלצה אמיתית של הורה, באישורו (עורכים בווידג׳ט זה).</p>')], 'br-quote')
testi = C([C([
    C([HTML('<span class="br-dash"></span>'), H('מה הורים מספרים?', 'h2', ''), HTML('<span class="br-dash"></span>')], 'br-head-deco'),
    C([quote(), quote()], 'br-testi-grid'),
], 'br-in')], 'br-testi', eid='testimonials', hide_desktop='hidden-desktop', hide_tablet='hidden-tablet', hide_mobile='hidden-mobile')

# ---------- final CTA + footer ----------
final = C([
    HTML(DECO_L, 'br-deco l'), HTML(DECO_R, 'br-deco r'),
    C([
        H('רוצה לשמוע איך גם הילד/ה שלך יוכל/תוכל לפרוח ולהרגיש מוכן/ה לכיתה א׳?', 'h2', ''),
        T('<p>אשמח להכיר, לשמוע על הצרכים של ילדכם ולמצוא יחד את הדרך המתאימה עבורו.</p>', 'br-fsub'),
        C([BTN('שלחי לי הודעה בוואטסאפ', 'br-wa')], 'br-btn-wrap'),
    ], 'br-final-in br-reveal'),
], 'br-final', eid='contact')

footer = HTML('<footer class="br-footer"><p>© ברכה ברנס | כל הזכויות שמורות</p><p><a href="https://nivcreative.com" target="_blank" rel="noopener">Designed by NivCreative</a></p></footer>')

JS = '''(function(){
var h=document.getElementById('br-header');if(!h)return;
var b=h.querySelector('.br-burger');
function set(o){h.classList.toggle('open',o);b.setAttribute('aria-expanded',o);b.setAttribute('aria-label',o?'סגירת תפריט':'פתיחת תפריט');}
b.addEventListener('click',function(){set(!h.classList.contains('open'));});
h.querySelectorAll('.br-nav a').forEach(function(a){
  var t=document.querySelector(a.getAttribute('href'));
  if(t&&t.offsetParent===null&&a.getAttribute('href')!=='#top'){a.hidden=true;}
  a.addEventListener('click',function(){set(false);});});
document.addEventListener('keydown',function(e){if(e.key==='Escape')set(false);});
var els=document.querySelectorAll('.br-reveal');
if('IntersectionObserver' in window&&!matchMedia('(prefers-reduced-motion: reduce)').matches){
  var io=new IntersectionObserver(function(es){es.forEach(function(x){if(x.isIntersecting){x.target.classList.add('in');io.unobserve(x.target);}});},{threshold:.12});
  els.forEach(function(e){io.observe(e);});
}else{els.forEach(function(e){e.classList.add('in');});}
document.title=%s;
function m(a,n,c){var e=document.querySelector('meta['+a+'="'+n+'"]');if(!e){e=document.createElement('meta');e.setAttribute(a,n);document.head.appendChild(e);}e.setAttribute('content',c);}
m('name','description',%s);m('property','og:title',%s);m('property','og:description',%s);m('property','og:type','website');m('property','og:locale','he_IL');m('property','og:image',%s);
})();''' % tuple(json.dumps(x, ensure_ascii=False) for x in (SEO_TITLE, SEO_DESC, SEO_TITLE, SEO_DESC, IMG['hero'][1]))

style = HTML('<link href="https://fonts.googleapis.com/css2?family=Heebo:wght@400;500;600;700;800&family=Assistant:wght@400;600;700&family=Gveret+Levin&display=swap" rel="stylesheet"><style>%s</style>' % css)
script = HTML('<script>%s</script>' % JS)

root = C([style, header, hero, values, achievements, about, services, testi, final, footer, script],
         'bracha-landing e-no-lazyload', inner=False,
         html_tag='main' if False else 'div')
doc = {"version": "0.4", "title": "ברכה ברנס", "type": "page", "content": [root], "page_settings": {"hide_title": "yes"}}
json.dump(doc, open(os.path.join(HERE, 'bracha-elementor.json'), 'w', encoding='utf-8'), ensure_ascii=False)
print('built', os.path.getsize(os.path.join(HERE, 'bracha-elementor.json')))
