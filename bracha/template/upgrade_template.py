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
 'nhero': (3700, 'bracha-n-hero2.webp'), 'nlogo': (3695, 'bracha-n-logo.webp'), 's_about': (3696, 'bracha-n-s_about.webp'), 's_services': (3697, 'bracha-n-s_services.webp'), 's_final': (3698, 'bracha-n-s_final.webp'),
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
def box(children, cls, gap=0, inner=True):
    z = {"unit": "px", "top": "0", "right": "0", "bottom": "0", "left": "0", "isLinked": True}
    return {"id": rid(), "elType": "container", "isInner": inner, "elements": children, "settings": {"content_width": "full", "flex_direction": "column", "padding": z,
            "flex_gap": {"unit": "px", "column": str(gap), "row": str(gap), "isLinked": True}, "css_classes": cls}}
def mbg(k, color):
    return {"background_background": "classic", "background_color": color, "background_image_mobile": {"url": url(k), "id": M[k][0], "size": ""},
            "background_position_mobile": "top center", "background_repeat_mobile": "no-repeat", "background_size_mobile": "initial",
            "background_bg_width_mobile": {"unit": "%", "size": 100, "sizes": []}}
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
   "settings": {"image": {"url": url('nlogo'), "id": M['nlogo'][0], "size": "", "alt": ""}, "image_size": "full", "_css_classes": "b-logo-img"}}]
menu = [c for c in find('b-header')[0]['elements'] if c.get('widgetType') == 'html'][0]
menu['settings']['html'] = menu['settings']['html'].replace('<a href="#reviews">המלצות</a>', '')

# --- hero
hero = find('b-hero')[0]; hero['settings'].update(bg('sky', 'nhero')); hero['settings']['_element_id'] = 'top'
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
rest = [w for w in copy['elements'] if w not in order]
lead = [w for w in rest if w.get('widgetType') == 'text-editor']; btns = [w for w in rest if w.get('widgetType') == 'button']
copy['elements'] = [box(order, 'b-hero-titles', 8), box(lead, 'b-hero-lead'), box(btns, 'b-hero-ctas', 12)]

# --- sections

ab = find('b-about')[0]; ab['settings'].update(bg('bokeh', None, None)); ab['settings'].update(mbg('s_about', '#fcf1eb'))
ab['settings'].update({"background_overlay_background": "classic", "background_overlay_color": "#FFFAF5", "background_overlay_opacity": {"unit": "px", "size": 0.62, "sizes": []}, "background_overlay_opacity_mobile": {"unit": "px", "size": 0, "sizes": []}})
ab['settings']['background_image_mobile'] = {"url": url('s_about'), "id": M['s_about'][0], "size": ""}
import copy as _cp
atext = find('b-about-text')[0]; aphoto = find('b-about-photo')[0]
def renew(e):
    e['id'] = rid()
    for c in e.get('elements', []): renew(c)
pm = _cp.deepcopy(aphoto); renew(pm); pm['settings']['css_classes'] = 'b-about-photo-m'
pm['settings'].update(hide_desktop='hidden-desktop', hide_tablet='hidden-tablet'); aphoto['settings'].update(hide_mobile='hidden-mobile')
atext['elements'].insert(2, pm)
svc = find('b-services')[0]; svc['settings'].update(mbg('s_services', '#fbf4ed'))
for card, k in zip(find('b-service-card'), ('svc1', 'svc2', 'svc3')):
    card['elements'] = [c for c in card['elements'] if c.get('widgetType') != 'image']
    tx = box([c for c in card['elements']], 'b-svc-tx', 4)
    ic = {'svc1': 'fas fa-book-open', 'svc2': 'fas fa-bullseye', 'svc3': 'fas fa-users'}[k]
    mid, f = ICON[ic]
    icw = {"id": rid(), "elType": "widget", "widgetType": "image", "isInner": False, "elements": [], "settings": {"image": {"url": U + f, "id": mid, "size": "", "alt": ""}, "image_size": "full", "_css_classes": "b-svc-ic", "_attributes": "aria-hidden|true"}}
    card['elements'] = [icw, img(k, 'b-svc-img'), tx]
    anim(card)
for c in find('b-value-card') + find('b-credential'): anim(c)
find('b-achievements-grid')[0]['settings'].update(animation='fadeInUp', animation_duration='slow')
# reviews: hidden until genuine testimonials exist
rv = find('b-reviews')[0]; rv['settings'].update(hide_desktop='hidden-desktop', hide_tablet='hidden-tablet', hide_mobile='hidden-mobile')
# contact + footer
ct = find('b-contact')[0]; ct['settings'].update(bg('wave14', None)); ct['settings'].update({"background_image_mobile": {"url": url('s_final'), "id": M['s_final'][0], "size": ""},
    "background_position_mobile": "bottom center", "background_repeat_mobile": "no-repeat", "background_size_mobile": "initial", "background_bg_width_mobile": {"unit": "%", "size": 100, "sizes": []}, "background_color": "#fdf4ed"})
foot_w = ct['elements'].pop()  # © text
ct['elements'].insert(0, img('mark', 'b-mark'))
for w in ct['elements']:
    if w.get('widgetType') == 'button': w['settings']['background_color'] = '#00766E'; w['settings']['_css_classes'] = 'b-final-btn'
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
@media(max-width:1023px){.bracha-landing .b-svc-ic{display:none}}
@media(min-width:1024px){.bracha-landing .b-svc-ic{display:none}}
@media(max-width:767px){
 .bracha-landing .b-header{position:fixed!important;top:0;left:0;right:0;width:100%;height:92px;align-items:flex-start!important;background:url(https://nivcreative.com/wp-content/uploads/2026/11/bracha-header-wave.webp) no-repeat center top/100% 100%!important;border:0!important;box-shadow:none!important;padding:8px 14px 0!important;z-index:70;filter:drop-shadow(0 3px 5px rgba(190,140,60,.2));transition:height .25s}
 .bracha-landing .b-header.scrolled{height:68px;padding-top:5px!important}
 .bracha-landing .b-header-logo{margin-right:40px!important;margin-inline-end:0}
 .bracha-landing .b-logo-img img{height:62px;width:auto;transition:height .25s}
 .bracha-landing .b-header.scrolled .b-logo-img img{height:44px}
 .bracha-landing .b-menu summary{width:48px;height:48px;border:0;border-radius:50%;font-size:0;background:linear-gradient(#102B51,#102B51) 50% 16px/22px 3px no-repeat,linear-gradient(#102B51,#102B51) 50% 22.5px/22px 3px no-repeat,linear-gradient(#102B51,#102B51) 50% 29px/22px 3px no-repeat,rgba(236,226,216,.92);box-shadow:0 2px 8px rgba(16,43,81,.1)}
 /* hero: cropped composition, everything in vw so it scales with the background */
 .bracha-landing .b-hero{position:relative;min-height:calc(100vw * 1.57)!important;height:calc(100vw * 1.57);padding:0!important;background-size:100% auto!important;background-position:center top!important}
 .bracha-landing .b-hero-wave,.bracha-landing .b-hero-image{display:none!important}
 .bracha-landing .b-hero-content,.bracha-landing .b-hero-copy{position:static!important;display:block!important;width:auto!important;padding:0!important}
 .bracha-landing .b-hero-titles{position:absolute;top:26vw;right:4%;width:40%;align-items:flex-start;text-align:right}
 .bracha-landing .b-hero-titles h1.elementor-heading-title{font-size:31px!important;line-height:1.05!important;white-space:nowrap;text-align:right}
 .bracha-landing .b-hero-titles h2.elementor-heading-title{font-size:16px!important;text-align:right}
 .bracha-landing .b-hero-kicker,.bracha-landing .b-hero-kicker2{align-self:flex-start;margin-top:4px}
 .bracha-landing .b-hero-kicker .elementor-heading-title,.bracha-landing .b-hero-kicker2 .elementor-heading-title{font-size:20px!important}
 .bracha-landing .b-hero-lead{position:absolute;top:114vw;right:8%;width:84%}
 .bracha-landing .b-hero-lead .elementor-widget-text-editor{font-size:13.5px!important;line-height:1.5;text-align:right;color:#102B51;text-shadow:0 0 8px #fff,0 0 14px #fff,0 0 18px #fff}
 .bracha-landing .b-hero-ctas{position:absolute;right:14%;left:14%;bottom:4vw;width:auto!important;align-items:center;gap:8px}
 .bracha-landing .b-hero-ctas .elementor-widget-button{width:100%}
 .bracha-landing .b-hero-ctas .elementor-button{width:100%;justify-content:center;font-size:13.5px;padding:12px 8px;white-space:nowrap}
 .bracha-landing .b-hero-ctas .elementor-widget-button:last-child{width:auto}
 .bracha-landing .b-hero-ctas .elementor-widget-button:last-child .elementor-button{width:auto;min-height:44px;padding:8px 24px;margin:0 auto}
 /* values: compact 2x2 */
 .bracha-landing .b-values{padding:22px 16px 14px!important;background:linear-gradient(180deg,#fde9ec 0,#fff6f1 45%,#fffaf5 100%)!important}
 .bracha-landing .b-value-grid{gap:10px!important}
 .bracha-landing .b-value-card{padding:10px 6px!important;min-height:78px;border-radius:18px}
 .bracha-landing .b-value-card .b-ic img{width:30px;height:30px}
 .bracha-landing .b-value-card .elementor-heading-title{font-size:13px!important;line-height:1.25!important}
 .bracha-landing .b-value-card{gap:4px!important}
 /* achievements: 4 in one row */
 .bracha-landing .b-achievements{padding:8px 12px 18px!important;background:#fffaf5!important}
 .bracha-landing .b-achievements-grid{display:grid!important;grid-template-columns:repeat(4,minmax(0,1fr))!important;gap:0!important;background:#fff!important;border:1px solid #eadcd3!important;border-radius:20px!important;box-shadow:0 6px 18px rgba(43,29,22,.05)!important;padding:10px 2px!important}
 .bracha-landing .b-credential{padding:4px 3px!important;border:0!important;gap:6px!important}
 .bracha-landing .b-credential+.b-credential{border-inline-start:1px solid #e3d6cf!important}
 .bracha-landing .b-credential .b-ic img{width:28px;height:28px}
 .bracha-landing .b-credential .elementor-heading-title{font-size:11px!important;line-height:1.25!important;font-weight:600!important}
 .bracha-landing .b-about-text,.bracha-landing .b-about-text *:not(img):not(svg){direction:rtl!important;text-align:right!important}
 .bracha-landing .b-about-text .elementor-widget-heading,.bracha-landing .b-about-text .elementor-widget-text-editor,.bracha-landing .b-about-text .elementor-heading-title{text-align:right!important;justify-content:flex-start}
 /* about: photo floats beside text */
 .bracha-landing .b-about{padding:14px 18px 22px!important}
 .bracha-landing .b-about-inner{display:block!important}
 .bracha-landing .b-about-text{display:block!important;padding:0!important}
 .bracha-landing .b-about-text .elementor-widget-heading{padding:0}
 .bracha-landing .b-about-text .elementor-widget-heading:first-child{padding-right:26%!important}
 .bracha-landing .b-about-text .elementor-widget-text-editor{font-size:14.5px!important;line-height:1.55;margin-bottom:8px}
 .bracha-landing .b-about-text h2.elementor-heading-title{font-size:30px!important}
 .bracha-landing .b-about-text h3.elementor-heading-title{font-size:17px!important}
 .bracha-landing .b-about-photo-m{float:left;width:45%!important;margin:4px 12px 4px 0;padding:0!important}
 .bracha-landing .b-about-photo-m img{border:4px solid #fff;border-radius:22px;width:100%;height:auto}
 .bracha-landing .b-about-text .elementor-widget-text-editor:last-child::after{content:"";display:block;clear:both}
 /* services: slim pills with icon, tap to expand */
 .bracha-landing .b-service-card,.bracha-landing .b-service-card *:not(img):not(svg){direction:rtl!important;text-align:right!important}
 .bracha-landing .b-service-card .b-svc-tx{align-items:stretch}
 .bracha-landing .b-services{padding:0 16px 26px!important;gap:12px!important}
 .bracha-landing .b-services .b-section-title .elementor-heading-title{font-size:24px!important;line-height:28px}
 .bracha-landing .b-service-list{flex-direction:column!important;gap:10px!important}
 .bracha-landing .b-service-card{flex-direction:row!important;flex-wrap:nowrap;align-items:center;gap:12px!important;padding:10px 16px!important;border-radius:26px;cursor:pointer;min-height:48px}
 .bracha-landing .b-svc-img{display:none!important}
 .bracha-landing .b-svc-ic{display:block!important;flex:0 0 34px;width:34px!important}
 .bracha-landing .b-svc-ic img{width:34px;height:34px;object-fit:contain}
 .bracha-landing .b-svc-tx{flex:1;min-width:0}
 .bracha-landing .b-svc-tx .elementor-heading-title{font-size:16px!important;line-height:1.25!important}
 .bracha-landing .b-svc-tx .elementor-widget-text-editor{display:none;font-size:13px!important;line-height:1.45;margin-top:4px}
 .bracha-landing .b-service-card.open .b-svc-tx .elementor-widget-text-editor{display:block}
 .bracha-landing .b-service-card::after{content:"\2039";font-size:26px;line-height:1;color:#102B51;margin-inline-start:auto;transition:transform .25s}
 .bracha-landing .b-service-card.open::after{transform:rotate(-90deg)}
 /* final */
 .bracha-landing .b-contact{position:relative;padding:26px 20px 175px!important}
 .bracha-landing .b-contact::after{display:none}
 .bracha-landing .b-mark img{width:56px}
 .bracha-landing .b-contact h2.elementor-heading-title{font-size:22px!important;line-height:1.35!important;text-align:right}
 .bracha-landing .b-contact .elementor-widget-text-editor{text-align:right;font-size:14.5px}
 .bracha-landing .b-contact{align-items:stretch!important}
 .bracha-landing .b-mark{align-self:flex-start}
 .bracha-landing .b-final-btn{position:absolute!important;left:23%;right:23%;bottom:70px;width:auto!important;z-index:3}
 .bracha-landing .b-final-btn .elementor-button{width:100%;justify-content:center;font-size:13.5px;padding:14px 6px;min-height:50px;white-space:nowrap}
}
@media (prefers-reduced-motion:reduce){html{scroll-behavior:auto}.bracha-landing .elementor-invisible{visibility:visible!important}.bracha-landing .animated{animation:none!important}.bracha-landing .b-value-card,.bracha-landing .b-service-card{transition:none}}
</style>'''
JS = '''<script>window.addEventListener('DOMContentLoaded',function(){var hd=document.querySelector('.bracha-landing .b-header');if(hd){var f=function(){hd.classList.toggle('scrolled',window.scrollY>30)};f();window.addEventListener('scroll',f,{passive:true});}
document.querySelectorAll('.bracha-landing .b-service-card').forEach(function(c){
var t=c.querySelector('.elementor-heading-title');c.setAttribute('tabindex','0');c.setAttribute('role','button');c.setAttribute('aria-expanded','false');
function tg(){var o=c.classList.toggle('open');c.setAttribute('aria-expanded',o);}
c.addEventListener('click',function(e){if(window.innerWidth<768)tg();});
c.addEventListener('keydown',function(e){if(window.innerWidth<768&&(e.key==='Enter'||e.key===' ')){e.preventDefault();tg();}});});});</script>'''
holder = find('b-style-holder')[0]
holder['elements'].append({"id": rid(), "elType": "widget", "widgetType": "html", "isInner": False, "settings": {"html": JS}, "elements": []})
holder['elements'].append({"id": rid(), "elType": "widget", "widgetType": "html", "isInner": False, "settings": {"html": CSS}, "elements": []})
d['content'] = [root]
json.dump(d, open(os.path.join(HERE, 'bracha_template_upgraded.json'), 'w', encoding='utf-8'), ensure_ascii=False)
print('ok', os.path.getsize(os.path.join(HERE, 'bracha_template_upgraded.json')))
