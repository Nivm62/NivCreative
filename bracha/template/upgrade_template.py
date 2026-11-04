#!/usr/bin/env python3
"""Upgrade the supplied Elementor template (fixed copy) with the uploaded asset package:
SVG icons, native backgrounds (desktop + mobile), waves, logo, service thumbnails, animations,
a11y fixes. Output: bracha_template_upgraded.json. Structure/classes of the supplied template are kept."""
import copy, json, os, re
HERE = os.path.dirname(os.path.abspath(__file__))
d = json.load(open(os.path.join(HERE, 'bracha_elementor_template.fixed.json')))
root = d['content'][0]
U = 'https://nivcreative.com/wp-content/uploads/2026/11/'
M = {  # id, file
 'sky': (3671, '02_hero_sky_background.webp'), 'wave12': (3681, '12_pastel_wave_mint.webp'), 'bokeh': (3682, '13_soft_cream_bokeh.webp'),
 'wave14': (3683, '14_pastel_wave_gold.webp'), 'm7': (3685, 'bracha-bg-m7.webp'), 'm8': (3686, 'bracha-bg-m8.webp'), 'm9': (3687, 'bracha-bg-m9.webp'),
 'm10': (3688, 'bracha-bg-m10.webp'), 'm11': (3689, 'bracha-bg-m11.webp'), 'logo': (3690, 'bracha-logo-3.webp'), 'mark': (3650, '15_logo_symbol_without_text.webp'),
 'svc1': (3673, '04_child_writing.webp'), 'svc2': (3674, '05_blank_wooden_blocks.webp'), 'svc3': (3675, '06_open_educational_books.webp')}
ICON = {'fas fa-bullseye': (3664, 'icon_target.svg'), 'fas fa-book-open': (3658, 'icon_open_book.svg'), 'fas fa-seedling': (3662, 'icon_sprout.svg'),
        'far fa-heart': (3656, 'icon_heart.svg'), 'fas fa-graduation-cap': (3654, 'icon_graduation_cap.svg'), 'fas fa-trophy': (3666, 'icon_trophy.svg'),
        'far fa-calendar-alt': (3652, 'icon_calendar.svg'), 'fas fa-users': (3660, 'icon_people.svg')}
_n = [0]
def rid():
    _n[0] += 1; return 'c0%06x' % (0xa00000 + _n[0])
def url(k): return U + M[k][1]
def img(k, cls='', hidden=True, **kw):
    s = {"image": {"url": url(k), "id": M[k][0], "size": "", "alt": ""}, "image_size": "full", "_css_classes": cls}
    if hidden: s["_attributes"] = "aria-hidden|true"
    s.update(kw); return {"id": rid(), "elType": "widget", "widgetType": "image", "isInner": False, "settings": s, "elements": []}
def bg(k, mk=None, overlay=None):
    s = {"background_background": "classic"}
    if k: s.update({"background_image": {"url": url(k), "id": M[k][0], "size": ""}, "background_position": "center center", "background_repeat": "no-repeat", "background_size": "cover"})
    if mk: s.update({"background_image_mobile": {"url": url(mk), "id": M[mk][0], "size": ""}, "background_position_mobile": "center top", "background_size_mobile": "100% 100%", "background_repeat_mobile": "no-repeat"})
    if overlay: s.update({"background_overlay_background": "classic", "background_overlay_color": overlay})
    return s
def cls_of(e): return e['settings'].get('css_classes') or e['settings'].get('_css_classes') or ''
def walk(e, f, parent=None):
    f(e, parent)
    for c in list(e.get('elements', [])): walk(c, f, e)
def find(cls):
    out = []
    walk(root, lambda e, p: out.append(e) if cls in cls_of(e).split() else None); return out
def anim(e): e['settings'].update(animation='fadeInUp', animation_duration='slow')

# --- icons -> uploaded SVG image widgets, decorative
def swap_icons(e, p):
    for i, c in enumerate(e.get('elements', [])):
        if c['elType'] == 'widget' and c['widgetType'] == 'icon':
            v = c['settings']['selected_icon']['value']
            mid, f = ICON[v]
            e['elements'][i] = {"id": rid(), "elType": "widget", "widgetType": "image", "isInner": False, "elements": [],
              "settings": {"image": {"url": U + f, "id": mid, "size": "", "alt": ""}, "image_size": "full", "_css_classes": "b-ic", "_attributes": "aria-hidden|true"}}
walk(root, swap_icons)

# --- header: logo image instead of text
hl = find('b-header-logo')[0]
hl['elements'] = [{"id": rid(), "elType": "widget", "widgetType": "image", "isInner": False, "elements": [],
   "settings": {"image": {"url": url('logo'), "id": M['logo'][0], "size": "", "alt": ""}, "image_size": "full", "_css_classes": "b-logo-img"}}]
menu = [c for c in find('b-header')[0]['elements'] if c.get('widgetType') == 'html'][0]
menu['settings']['html'] = menu['settings']['html'].replace('<a href="#reviews">המלצות</a>', '')

# --- hero
hero = find('b-hero')[0]; hero['settings'].update(bg('sky', 'm9')); hero['settings']['_element_id'] = 'top'
hero['elements'].append(img('wave12', 'b-hero-wave'))
for w in hero['elements'][0]['elements'][0]['elements']:  # hero copy widgets
    if w.get('widgetType') == 'button' and 'וואטסאפ' in w['settings']['text']:
        w['settings']['background_color'] = '#00766E'
    t = w['settings'].get('title')
    if t == 'קצת עליי': w['settings']['_css_classes'] = 'b-hero-kicker2'
    if t == 'מנטורית לגיל הרך': w['settings']['title_color'] = '#00756D'

copy = hero['elements'][0]['elements'][0]
byt = {w['settings'].get('title'): w for w in copy['elements'] if w.get('widgetType') == 'heading'}
order = [byt['ברכה ברנס'], byt['מנטורית לגיל הרך'], byt['נעים להכיר'], byt['קצת עליי']]
copy['elements'] = order + [w for w in copy['elements'] if w not in order]

# --- sections
find('b-values')[0]['settings'].update(bg(None, 'm11'))
find('b-achievements')[0]['settings'].update(bg(None, 'm10'))
find('b-about')[0]['settings'].update(bg('bokeh', None, 'rgba(255,250,245,0.62)'))
svc = find('b-services')[0]; svc['settings'].update(bg(None, 'm8'))
for card, k in zip(find('b-service-card'), ('svc1', 'svc2', 'svc3')):
    card['elements'] = [c for c in card['elements'] if c.get('widgetType') != 'image']
    card['elements'].insert(0, img(k, 'b-svc-img'))
    anim(card)
for c in find('b-value-card') + find('b-credential'): anim(c)
find('b-achievements-grid')[0]['settings'].update(animation='fadeInUp', animation_duration='slow')
# reviews: hidden until genuine testimonials exist
rv = find('b-reviews')[0]; rv['settings'].update(hide_desktop='hidden-desktop', hide_tablet='hidden-tablet', hide_mobile='hidden-mobile')
# contact + footer
ct = find('b-contact')[0]; ct['settings'].update(bg('wave14', 'm7'))
foot_w = ct['elements'].pop()  # © text
ct['elements'].insert(0, img('mark', 'b-mark'))
for w in ct['elements']:
    if w.get('widgetType') == 'button': w['settings']['background_color'] = '#00766E'
    if w.get('widgetType') == 'heading': w['settings']['title'] = 'רוצה לשמוע איך גם הילד/ה שלך יוכל/תוכל לפרוח ולהרגיש מוכן/ה לכיתה א׳?'
foot_w['settings']['editor'] = foot_w['settings']['editor'].replace('Designed by <a','<span dir="ltr">Designed by <a').replace('</a>','</a></span>',1)
footer = {"id": rid(), "elType": "container", "isInner": True, "settings": {"padding": {"unit": "px", "top": "0", "right": "0", "bottom": "0", "left": "0", "isLinked": True}, "flex_gap": {"unit": "px", "column": "0", "row": "0", "isLinked": True}, "content_width": "full", "flex_direction": "column", "html_tag": "footer", "css_classes": "b-footer"}, "elements": [foot_w]}
# main landmark wraps hero..contact
kids = root['elements']
idx = [i for i, e in enumerate(kids) if 'b-hero' in cls_of(e).split()][0]
main = {"id": rid(), "elType": "container", "isInner": True, "settings": {"padding": {"unit": "px", "top": "0", "right": "0", "bottom": "0", "left": "0", "isLinked": True}, "flex_gap": {"unit": "px", "column": "0", "row": "0", "isLinked": True}, "content_width": "full", "flex_direction": "column", "html_tag": "main", "css_classes": "b-main"}, "elements": kids[idx:]}
root['elements'] = kids[:idx] + [main, footer]

# --- override CSS (scoped, appended as a second style widget in the style holder)
CSS = r'''
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Heebo:wght@400;500;700;800&family=Assistant:wght@400;600&family=Gveret+Levin&display=swap" rel="stylesheet">
<style>
html{scroll-behavior:smooth;scroll-padding-top:70px}
.bracha-landing{--teal-text:#00756d}
.bracha-landing .b-main,.bracha-landing .b-header,.bracha-landing .b-footer{display:block}
.bracha-landing .b-header{position:sticky;top:0;z-index:60;display:flex!important}
.bracha-landing .b-main,.bracha-landing .b-footer{--padding-top:0px;--padding-bottom:0px;--padding-left:0px;--padding-right:0px;--gap:0px;--row-gap:0px;--column-gap:0px}
.bracha-landing .b-header{flex-wrap:nowrap!important;padding-block:6px!important}
.bracha-landing .b-header-logo{flex:0 0 auto;width:auto!important}
.bracha-landing .b-logo-img,.bracha-landing .b-logo-img .elementor-widget-container{width:auto!important}
.bracha-landing .b-logo-img img{height:56px;width:auto;max-width:none}
.bracha-landing .b-ic img{width:44px;height:44px;object-fit:contain;margin:0 auto}
.bracha-landing .b-credential .b-ic img{width:32px;height:32px}
.bracha-landing .b-svc-img img{width:100%;height:150px;object-fit:cover;border-radius:16px}
.bracha-landing .b-mark img{width:78px;margin:0 auto}
.bracha-landing .b-value-card,.bracha-landing .b-service-card{transition:transform .25s,box-shadow .25s}
.bracha-landing .b-value-card:hover,.bracha-landing .b-service-card:hover{transform:translateY(-4px)}
.bracha-landing .b-hero{position:relative;overflow:hidden}
.bracha-landing .b-hero::after{display:none}
.bracha-landing .b-hero-wave{position:absolute!important;left:0;right:0;bottom:0;width:100%!important;height:clamp(110px,16vw,220px);z-index:1;pointer-events:none;-webkit-mask-image:linear-gradient(to bottom,transparent 0,#000 38%);mask-image:linear-gradient(to bottom,transparent 0,#000 38%)}
.bracha-landing .b-hero-wave .elementor-widget-container{height:100%}
.bracha-landing .b-hero-wave img{width:100%;height:100%;object-fit:fill}
.bracha-landing .b-hero-copy,.bracha-landing .b-hero-image{position:relative;z-index:3}
.bracha-landing .b-hero-kicker,.bracha-landing .b-hero-kicker2{width:fit-content;align-self:flex-start;margin-top:6px;font-family:'Gveret Levin','Heebo',cursive!important;font-weight:400!important}
.bracha-landing .b-hero-kicker{background:linear-gradient(100deg,transparent 0,#f7bfd0 8%,#f9cfdb 92%,transparent 100%);border-radius:12px 40px 12px 40px;padding:2px 22px 0}
.bracha-landing .b-hero-kicker2{display:inline-block;background:linear-gradient(100deg,transparent 0,#8fd8cc 8%,#a7e3d8 92%,transparent 100%);border-radius:40px 12px 40px 12px;padding:2px 24px 0;transform:rotate(-1.5deg);align-self:flex-start}
.bracha-landing .b-hero-kicker2 .elementor-heading-title{font-family:'Gveret Levin','Heebo',cursive!important;font-weight:400!important}
.bracha-landing .b-hero-kicker .elementor-heading-title{font-family:'Gveret Levin','Heebo',cursive!important;font-weight:400!important}
.bracha-landing .b-hero .elementor-button{background:linear-gradient(180deg,#00817a,#00665e)!important}
.bracha-landing .b-hero .elementor-button[href^="#"]{background:rgba(255,255,255,.6)!important;border:2px solid #102B51;color:#102B51!important}
.bracha-landing .b-contact .elementor-button{background:linear-gradient(180deg,#00817a,#00665e)!important}
.bracha-landing .b-about-text .elementor-heading-title[class],.bracha-landing .b-about-text h3.elementor-heading-title{color:var(--teal-text)!important}
.bracha-landing .b-footer{background:#fff;text-align:center;padding:14px}
.bracha-landing .b-footer a{display:inline-flex;align-items:center;min-height:44px;padding:0 6px;color:#00726a}
.bracha-landing a,.bracha-landing button,.bracha-landing summary{touch-action:manipulation}
.bracha-landing .b-menu summary:focus-visible,.bracha-landing a:focus-visible{outline:3px solid #079B96;outline-offset:3px}
.bracha-landing h1,.bracha-landing h2,.bracha-landing h3{text-wrap:balance}
@media(min-width:768px){
 .bracha-landing .b-hero-image{position:absolute!important;left:max(0px,calc(50% - 640px));top:0;bottom:0;width:380px}
 .bracha-landing .b-hero-image img{width:100%;max-height:none;height:100%;object-fit:cover;object-position:50% 5%;-webkit-mask-image:linear-gradient(to right,transparent 0,#000 12%,#000 70%,transparent 100%),linear-gradient(to bottom,#000 80%,transparent);-webkit-mask-composite:source-in;mask-image:linear-gradient(to right,transparent 0,#000 12%,#000 70%,transparent 100%),linear-gradient(to bottom,#000 80%,transparent);mask-composite:intersect}
 .bracha-landing .b-hero-copy{width:48%;margin-inline-start:0;margin-inline-end:auto}
 .bracha-landing .b-hero{min-height:650px}
}
@media(max-width:767px){
 .bracha-landing .b-hero{padding:0 0 34px!important;min-height:0}
 .bracha-landing .b-hero-wave{display:none}
 .bracha-landing .b-hero-content{display:block!important;position:static}
 .bracha-landing .b-hero-image{position:absolute!important;left:3.2%;top:21.5%;width:44.5%!important;height:46.5%;min-height:0;border-radius:26px;overflow:hidden;z-index:2}
 .bracha-landing .b-hero{min-height:calc(100vw * 1.111)!important}
 .bracha-landing .b-hero-image .elementor-widget-image,.bracha-landing .b-hero-image .elementor-widget-container{height:100%;width:100%}
 .bracha-landing .b-hero-copy h1.elementor-heading-title{font-size:38px!important}
 .bracha-landing .b-hero-copy .elementor-widget-text-editor{font-size:14.5px!important;text-align:center}
 .bracha-landing .b-hero-copy{align-items:center;text-align:center}
 .bracha-landing .b-hero-copy .elementor-widget-heading .elementor-heading-title{text-align:center}
 .bracha-landing .b-hero-kicker,.bracha-landing .b-hero-kicker2{align-self:center}
 .bracha-landing .b-hero-image .elementor-widget-image img{width:100%!important;max-width:none!important;height:100%!important;max-height:none!important;object-fit:cover!important;object-position:50% 6%!important}
 .bracha-landing .b-hero-copy{width:53%!important;margin-inline-start:0;margin-inline-end:auto;padding:6px 10px 0 8px!important}
 .bracha-landing .b-values{padding:72px 16px 70px!important}
 .bracha-landing .b-achievements{padding:36px 16px 40px!important}
 .bracha-landing .b-achievements-grid{background:transparent!important;border:0!important;box-shadow:none!important}
 .bracha-landing .b-services{padding:80px 16px 90px!important}
 .bracha-landing .b-svc-img img{height:130px}
 .bracha-landing .b-contact{padding-bottom:120px!important}
}
@media (prefers-reduced-motion:reduce){html{scroll-behavior:auto}.bracha-landing .elementor-invisible{visibility:visible!important}.bracha-landing .animated{animation:none!important}.bracha-landing .b-value-card,.bracha-landing .b-service-card{transition:none}}
</style>'''
holder = find('b-style-holder')[0]
holder['elements'].append({"id": rid(), "elType": "widget", "widgetType": "html", "isInner": False, "settings": {"html": CSS}, "elements": []})
d['content'] = [root]
json.dump(d, open(os.path.join(HERE, 'bracha_template_upgraded.json'), 'w', encoding='utf-8'), ensure_ascii=False)
print('ok', os.path.getsize(os.path.join(HERE, 'bracha_template_upgraded.json')))
