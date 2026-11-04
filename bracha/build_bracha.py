#!/usr/bin/env python3
"""Builds bracha-elementor.json: Elementor containers + native widgets (heading / text-editor /
button / image) with page-scoped CSS (.bracha-landing). Publish with publish.py."""
import json, os, re
HERE = os.path.dirname(os.path.abspath(__file__))
css = open(os.path.join(HERE, 'bracha.css'), encoding='utf-8').read()
css = css.replace(".bracha-landing .elementor-widget{margin:0!important;width:auto;max-width:100%}",
                  ".bracha-landing .elementor-widget{margin:0!important}")
css += """
.bracha-landing{--teal-text:#00756d}
html body .bracha-landing .br-sub .elementor-heading-title,html body .bracha-landing .br-about-sub .elementor-heading-title{color:#00756d!important}
html body .bracha-landing .br-wa .elementor-button{background:linear-gradient(180deg,#00817a,#00665e)!important}
.bracha-landing .br-footer a{color:#00726a;display:inline-flex;align-items:center;min-height:44px;padding:0 6px}
.bracha-landing .br-nav a{min-height:44px;min-width:44px;justify-content:center;display:inline-flex;align-items:center}
.bracha-landing a,.bracha-landing button,.bracha-landing summary{touch-action:manipulation;-webkit-tap-highlight-color:rgba(0,138,128,.18)}
.bracha-landing h1,.bracha-landing h2,.bracha-landing h3{text-wrap:balance}
.bracha-landing .br-main{display:block!important}
@media(max-width:767px){.bracha-landing .br-hero .br-wa .elementor-button{font-size:.88rem!important}}
@media(max-width:767px){
 .bracha-landing .br-hero{padding:0 0 34px!important}
 .bracha-landing .br-hero-waves-w{display:none!important}
 .bracha-landing .br-hero .br-hero-photo{left:3.2%!important;right:auto!important;top:21.5%!important;width:44.5%!important;max-width:none!important;height:46.5%!important;border-radius:26px;overflow:hidden;z-index:2!important}
 .bracha-landing .br-hero .br-hero-copy{width:53%!important;padding:6px 10px 0 8px!important}
 .bracha-landing .br-hero .br-wa .elementor-button{font-size:.84rem!important;padding:12px 8px!important}
 .bracha-landing .br-hero .br-hero-photo .elementor-widget-container{height:100%}
 .bracha-landing .br-hero .br-hero-photo img{width:100%!important;height:100%!important;object-fit:cover;object-position:50% 6%;-webkit-mask-image:none!important;mask-image:none!important}
 .bracha-landing .br-values{padding:72px 0 70px!important;background-color:#fff6f0}
 .bracha-landing .br-ach{padding:36px 0 40px!important}
 .bracha-landing .br-ach-box{background:transparent!important;border:0!important;box-shadow:none!important}
 .bracha-landing .br-services{padding:80px 0 90px!important}
 .bracha-landing .br-final{background-color:#fff3ee}
}
.bracha-landing .br-ic img{width:44px;height:44px;object-fit:contain;margin:0 auto}
.bracha-landing .br-ach-ic .br-ic img{width:30px;height:30px}
.bracha-landing .br-svc-img img{width:74px;height:74px;object-fit:cover;border-radius:18px;box-shadow:0 6px 14px -8px rgba(16,43,81,.5)}
.bracha-landing .br-mark img{width:78px;height:auto;mix-blend-mode:multiply;margin:0 auto}
.bracha-landing .br-svc-arrow .elementor-icon{font-size:20px;line-height:1}
.bracha-landing .br-heart .elementor-icon{font-size:28px;line-height:1}
.bracha-landing .br-svc-img,.bracha-landing .br-ic{width:auto}
.bracha-landing .br-hero-copy{background:none}
.bracha-landing .br-hero{background-color:#cfe9f1}
@media(min-width:1024px){.bracha-landing .br-svc-img img{width:100%;height:150px}.bracha-landing .br-svc-img{width:100%!important}.bracha-landing .br-ic img{width:54px;height:54px}.bracha-landing .br-ach-ic .br-ic img{width:36px;height:36px}}
@media (prefers-reduced-motion:reduce){.bracha-landing .elementor-invisible{visibility:visible!important}.bracha-landing .animated{animation:none!important}}
.bracha-landing .br-hero::before{display:none}
.bracha-landing .br-hero-waves-w{position:absolute!important;left:0;right:0;bottom:0;width:100%!important;height:clamp(120px,17vw,230px);z-index:1;pointer-events:none;-webkit-mask-image:linear-gradient(to bottom,transparent 0,#000 38%);mask-image:linear-gradient(to bottom,transparent 0,#000 38%)}
.bracha-landing .br-hero-waves-w .elementor-widget-container{height:100%}
.bracha-landing .br-hero-waves-w img{width:100%;height:100%;object-fit:fill}
.bracha-landing .br-hero{padding-bottom:clamp(70px,9vw,120px)}
.bracha-landing .br-hero-photo{z-index:2!important}
.bracha-landing .br-hero-photo img{-webkit-mask-image:linear-gradient(to bottom,#000 80%,transparent 100%),linear-gradient(to right,transparent 0,#000 12%,#000 70%,transparent 100%);-webkit-mask-composite:source-in;mask-image:linear-gradient(to bottom,#000 80%,transparent 100%),linear-gradient(to right,transparent 0,#000 12%,#000 70%,transparent 100%);mask-composite:intersect}
.bracha-landing .br-mark{width:78px;height:auto;margin:0 auto 10px;mix-blend-mode:multiply}
.bracha-landing .br-svc-img{width:74px;height:74px;object-fit:cover;border-radius:18px;box-shadow:0 6px 14px -8px rgba(16,43,81,.5)}
.bracha-landing .br-ic{object-fit:contain}
.bracha-landing .br-ach-ic .br-ic{width:30px;height:30px}
@media(min-width:1024px){.bracha-landing .br-svc-img{width:100%;height:150px;border-radius:18px}.bracha-landing .br-svc{grid-template-columns:1fr auto!important}}

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
@media(min-width:1024px){.bracha-landing .br-hero-photo{width:390px!important;max-width:none!important;position:absolute!important;left:max(0px,calc(50% - 640px))!important;top:0!important}.bracha-landing .br-hero{min-height:650px}}
.bracha-landing .br-hero-copy{display:flex!important}
.bracha-landing .br-wa .elementor-button{background:linear-gradient(180deg,#0aa391,#007f73)!important;white-space:nowrap}
.bracha-landing .br-ghost .elementor-button{background:rgba(255,255,255,.6)!important}
.bracha-landing .br-hero,
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
IMG = {'hero': (3649, U + 'hero-2.webp'), 'logo': (3690, U + 'logo-3.webp'), 'kids': (3648, U + 'kids-2.webp')}
M = lambda i, f: (i, 'https://nivcreative.com/wp-content/uploads/2026/11/' + f)
IMG.update({'hero': M(3669, '01_bracha_portrait_from_original.webp'), 'kids': M(3672, '03_bracha_with_children_illustrative.webp'),
            'sky': M(3671, '02_hero_sky_background.webp'), 'wave12': M(3681, '12_pastel_wave_mint.webp'), 'bokeh': M(3682, '13_soft_cream_bokeh.webp'),
            'wave14': M(3683, '14_pastel_wave_gold.webp'), 'mark': M(3650, '15_logo_symbol_without_text.webp'),
            'svc1': M(3673, '04_child_writing.webp'), 'svc2': M(3674, '05_blank_wooden_blocks.webp'), 'svc3': M(3675, '06_open_educational_books.webp')})
ICONS = {'book': 'icon_open_book.svg', 'target': 'icon_target.svg', 'sprout': 'icon_sprout.svg', 'heart': 'icon_heart.svg', 'people': 'icon_people.svg',
         'cap': 'icon_graduation_cap.svg', 'trophy': 'icon_trophy.svg', 'cal': 'icon_calendar.svg', 'group': 'icon_people.svg'}
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

def BG(key, overlay=None):
    i, url = IMG[key]
    d = {"background_background": "classic", "background_image": {"url": url, "id": i, "size": ""}, "background_position": "center center",
         "background_repeat": "no-repeat", "background_size": "cover"}
    if overlay: d.update({"background_overlay_background": "classic", "background_overlay_color": overlay})
    return d
def BGM(i, f, pos='center top', size='cover', **extra):
    url = 'https://nivcreative.com/wp-content/uploads/2026/11/' + f
    d = {"background_background": "classic", "background_image_mobile": {"url": url, "id": i, "size": ""}, "background_position_mobile": pos,
         "background_size_mobile": size, "background_repeat_mobile": "no-repeat"}
    d.update(extra); return d
def H(text, tag, cls): return W('heading', cls, title=text, header_size=tag)
def T(html, cls=''): return W('text-editor', cls, editor=html)
def HTML(code, cls=''): return W('html', cls, html=code)
def IMGW(key, cls, size='full'):
    i, url = IMG[key]
    return W('image', cls, image={"url": url, "id": i, "size": "", "alt": ""}, image_size=size)
def PIC(key, cls='', deco=False, **kw):
    i, url = IMG[key]
    if deco: kw['_attributes'] = 'aria-hidden|true'
    return W('image', cls, image={"url": url, "id": i, "size": "", "alt": ""}, image_size='full', **kw)
ICON_IDS = {'cal': 3652, 'cap': 3654, 'heart': 3656, 'book': 3658, 'people': 3660, 'group': 3660, 'sprout': 3662, 'target': 3664, 'trophy': 3666}
def ICONW(name):
    f = ICONS[name]
    return W('image', 'br-ic', image={"url": 'https://nivcreative.com/wp-content/uploads/2026/11/' + f, "id": ICON_IDS[name], "size": "", "alt": ""}, image_size='full', _attributes='aria-hidden|true')
def FAI(cls, value, lib='fa-solid', color='#102B51'):
    return W('icon', cls, selected_icon={"value": value, "library": lib}, primary_color=color, view='default', _attributes='aria-hidden|true')
def ANIM(**kw): return dict(animation='fadeInUp', animation_duration='slow', **kw)
def BTN(text, cls, wa=True):
    s = dict(text=text, link={"url": WA, "is_external": "on", "nofollow": "on", "custom_attributes": ""})
    if wa: s['selected_icon'] = {"value": "fab fa-whatsapp", "library": "fa-brands"}; s['icon_align'] = 'row-reverse'
    return W('button', cls, **s)

# ---------- header ----------
header = HTML('''<header class="br-header" id="br-header">
<div class="br-header-bar">
<a class="br-logo" href="#top" aria-label="ברכה ברנס – מנטורית לגיל הרך"><img src="%s" alt="ברכה ברנס – מנטורית לגיל הרך" width="336" height="222"></a>
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
    PIC('wave12', 'br-hero-waves-w', deco=True),
], 'br-hero', eid='top', **BG('sky'), **{k: v for k, v in BGM(3687, 'bracha-bg-m9.webp', 'center top', '100% 100%').items() if k != 'background_background'})

# ---------- value cards ----------
def card(icon, text, color): return C([ICONW(icon), H(text, 'h3', '')], 'br-card ' + color, **ANIM())
values = C([C([C([
    card('book', 'מומחית ברכישת קריאה', 'br-c-yellow'),
    card('target', 'מותאמת אישית לילדים שמתקשים', 'br-c-mint'),
    card('heart', 'חיזוק ביטחון, מסוגלות ועצמאות', 'br-c-pink'),
    card('sprout', 'ליווי מקצועי, חם ותומך', 'br-c-yellow'),
], 'br-values-grid')], 'br-in')], 'br-values', **BGM(3689, 'bracha-bg-m11.webp', 'center center', '100% 100%'))

# ---------- achievements ----------
def ach(icon, text): return C([C([ICONW(icon)], 'br-ach-ic'), T('<p>%s</p>' % text)], 'br-ach-item')
achievements = C([C([C([
    ach('cap', 'בעלת תואר ראשון ושני בחינוך'),
    ach('trophy', 'זוכת תואר “מורת המאה” מטעם משרד החינוך'),
    ach('cal', 'מעל 20 שנות ניסיון בניהול גן חובה'),
    ach('group', 'ליווי מאות ילדים במעבר מהגן לכיתה א׳'),
], 'br-ach-box', **ANIM())], 'br-in')], 'br-ach', **BGM(3688, 'bracha-bg-m10.webp', 'center center', '100% 100%'))

# ---------- about ----------
about = C([C([C([
    C([
        C([FAI('br-heart', 'far fa-heart', 'fa-regular', '#EFB629'), H('מי אני?', 'h2', '')], 'br-title'),
        H('נעים להכיר – ברכה ברנס', 'h3', 'br-about-sub'),
        T('<p><strong>אני ברכה ברנס, אשת חינוך ומנטורית לגיל הרך.</strong></p>'
          '<p>בעלת תואר ראשון ושני בחינוך, זוכת תואר “מורת המאה” מטעם משרד החינוך, ומנהלת גן חובה מעל 20 שנה.</p>'
          '<p>לאורך השנים ליוויתי מאות ילדים במעבר מהגן לכיתה א׳, מתוך אמונה שכל ילד וילדה יכולים לצמוח ולממש את היכולות שלהם.</p>'
          '<p>אני מתמחה בהכנה מיטבית לכיתה א׳, תוך פיתוח מסוגלות, ביטחון עצמי ומיומנויות רגשיות, חברתיות ולימודיות.</p>'
          '<p>בנוסף, אני מתמחה בשיטת לימוד ייחודית לרכישת הקריאה, המיועדת לילדים שטרם רכשו את מיומנות הקריאה – בכל גיל.</p>'),
    ], 'br-about-text', **ANIM()),
    C([IMGW('kids', '')], 'br-about-photo', **ANIM()),
], 'br-about-grid')], 'br-in')], 'br-about', eid='about', **BG('bokeh', overlay='rgba(255,250,245,0.62)'))

# ---------- services ----------
def svc(img, title, desc, color):
    return C([PIC(img, 'br-svc-img', deco=True), C([H(title, 'h3', ''), T('<p>%s</p>' % desc)], 'br-svc-tx'), FAI('br-svc-arrow', 'fas fa-chevron-left')], 'br-svc ' + color, **ANIM())
services = C([C([
    C([HTML('<span class="br-dash"></span>'), H('מה תמצאו אצלי?', 'h2', ''), HTML('<span class="br-dash"></span>')], 'br-head-deco'),
    C([
        svc('svc1', 'תהליך הכנה מקיף לכיתה א׳', 'הכנה מותאמת לקראת המעבר לכיתה א׳, המשלבת חיזוק מיומנויות רגשיות, חברתיות ולימודיות.', 'br-c-yellow'),
        svc('svc2', 'ליווי אישי מותאם לילד/ה', 'תהליך אישי המותאם לצרכים, ליכולות ולקצב ההתפתחות של כל ילד וילדה.', 'br-c-mint'),
        svc('svc3', 'הדרכות והשתלמויות לצוותי חינוך והורים', 'הענקת כלים מקצועיים ופרקטיים לחיזוק הביטחון, המסוגלות וההתפתחות של ילדים.', 'br-c-pink'),
    ], 'br-svc-list'),
], 'br-in')], 'br-services', eid='specialties', **BGM(3686, 'bracha-bg-m8.webp', 'center top', 'cover'))

# ---------- testimonials (hidden until real reviews are supplied) ----------
def quote(): return C([T('<p>הוסיפו כאן המלצה אמיתית של הורה, באישורו (עורכים בווידג׳ט זה).</p>')], 'br-quote')
testi = C([C([
    C([HTML('<span class="br-dash"></span>'), H('מה הורים מספרים?', 'h2', ''), HTML('<span class="br-dash"></span>')], 'br-head-deco'),
    C([quote(), quote()], 'br-testi-grid'),
], 'br-in')], 'br-testi', eid='testimonials', hide_desktop='hidden-desktop', hide_tablet='hidden-tablet', hide_mobile='hidden-mobile')

# ---------- final CTA + footer ----------
final = C([
    C([
        PIC('mark', 'br-mark', deco=True),
        H('רוצה לשמוע איך גם הילד/ה שלך יוכל/תוכל לפרוח ולהרגיש מוכן/ה לכיתה א׳?', 'h2', ''),
        T('<p>אשמח להכיר, לשמוע על הצרכים של ילדכם ולמצוא יחד את הדרך המתאימה עבורו.</p>', 'br-fsub'),
        C([BTN('שלחי לי הודעה בוואטסאפ', 'br-wa')], 'br-btn-wrap'),
    ], 'br-final-in', **ANIM()),
], 'br-final', eid='contact', **BG('wave14'), **{k: v for k, v in BGM(3685, 'bracha-bg-m7.webp', 'center bottom', 'cover').items() if k != 'background_background'})

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
document.addEventListener('keydown',function(e){if(e.key==='Escape'&&h.classList.contains('open')){set(false);b.focus();}});
document.documentElement.lang='he-IL';
})();'''

style = HTML('<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Heebo:wght@400;500;700;800&family=Assistant:wght@400;600&family=Gveret+Levin&display=swap" rel="stylesheet"><style>%s</style>' % css)
script = HTML('<script>%s</script>' % JS)

main = C([hero, values, achievements, about, services, testi, final], 'br-main', eid='main', html_tag='main')
root = C([style, header, main, footer, script],
         'bracha-landing e-no-lazyload', inner=False,
         html_tag='main' if False else 'div')
doc = {"version": "0.4", "title": "ברכה ברנס", "type": "page", "content": [root], "page_settings": {"hide_title": "yes"}}
json.dump(doc, open(os.path.join(HERE, 'bracha-elementor.json'), 'w', encoding='utf-8'), ensure_ascii=False)
print('built', os.path.getsize(os.path.join(HERE, 'bracha-elementor.json')))
