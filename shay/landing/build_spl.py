"""Build /shai-power-landing/ in Elementor: the live Shay header + the source landing page.

Source: https://shai-power-landing.vercel.app/ (static HTML/CSS). Its sections are kept in
order, each in its own Elementor HTML widget; its stylesheet is scoped under .spl so
nothing leaks to the rest of the site. The lead form is a real Elementor form using the
site's existing email connection (no source CRM, no checkout redirect, no tracking).

usage: python3 build_spl.py <home_page.json> <source_dir> <out.json>
"""
import copy, json, random, re, sys

home_json, src_dir, out_path = sys.argv[1:4]
U = "https://nivcreative.com/wp-content/uploads/2026/10/spl-"
ASSETS = {f"assets/{n}": f"{U}{n.rsplit('.', 1)[0]}.webp" for n in [
    "about-shai.png", "credit-aviel.png", "hero-shai.png", "logo-white.png", "s6-banner.png",
    "s6-collage.png", "s6-img1.jpg", "s6-speaking.jpg", "workshop-group.jpg"]}
rnd = random.Random(690)


def nid():
    return "%07x" % rnd.getrandbits(28)


# ---------------------------------------------------------------- scoped CSS
def scope_selector(sel):
    sel = sel.strip()
    if sel in (":root", "html", "body"):
        return ".spl"
    if sel == "*":
        return ".spl, .spl *"
    sel = re.sub(r"^(html|body)\b", ".spl", sel)
    return sel if sel.startswith(".spl") else ".spl " + sel


def rem_to_px(css):
    # the theme sets a different root font size; pin the source's rem values to its 16px base
    return re.sub(r"(?<![\w.-])(\d*\.?\d+)rem\b", lambda m: f"{float(m.group(1)) * 16:g}px", css)


def scope_css(css):
    css = re.sub(r"/\*.*?\*/", "", css, flags=re.S)
    out, i = [], 0
    while i < len(css):
        j = css.find("{", i)
        if j < 0:
            break
        head = css[i:j].strip()
        if head.startswith("@media") or head.startswith("@supports"):
            depth, k = 1, j + 1
            while depth:
                depth += {"{": 1, "}": -1}.get(css[k], 0)
                k += 1
            out.append(head + "{" + scope_css(css[j + 1:k - 1]) + "}")
            i = k
            continue
        k = css.find("}", j)
        body = css[j + 1:k]
        if head.startswith("@"):
            out.append(head + "{" + body + "}")
        else:
            out.append(", ".join(scope_selector(s) for s in head.split(",")) + "{" + body + "}")
        i = k + 1
    return "\n".join(out)


src_css = open(f"{src_dir}/css/style.css", encoding="utf-8").read()
EXTRA_CSS = r"""
/* ---- integration with the WordPress page (page-scoped) ---- */
html:has(.spl-page){scroll-behavior:smooth}
body:has(.spl-page){background:#080808}
.spl-page{background:#080808;overflow-x:clip}
.spl-page .spl :is(h1,h2,h3,h4,p,a,li,span,button,input,label,summary){font-family:inherit;text-transform:none;letter-spacing:inherit}
.spl-page .spl :is(h1,h2,h3,h4){color:inherit}
.spl-page .spl a,.spl-page .spl a:hover{color:inherit}
.spl-page .spl .gold,.spl-page .spl .gold2{color:transparent}
.spl-page .spl .footer-links a{color:rgba(255,255,255,.55)}.spl-page .spl .footer-links a:hover{color:var(--gold)}
.spl-page .spl :focus-visible{outline:2px solid #ffd768;outline-offset:3px}
/* the floating site header sits over the top of the page */
.spl-page .syx{position:relative;z-index:1001}
.spl-page .spl .hero{padding-block-start:calc(clamp(3rem,7vw,5.5rem) + 92px)}
.spl-page #form{scroll-margin-top:90px}
/* Elementor containers inside the form section behave like the source blocks */
.spl-page .e-con.spl,.spl-page .e-con.spl>.e-con-inner{padding:0;gap:0}
.spl-page .e-con.section{--padding-top:0;--padding-bottom:0;--padding-left:0;--padding-right:0;display:block}
.spl-page .e-con.form-card{--padding-top:0;--padding-bottom:0;--padding-left:0;--padding-right:0;display:block;padding:clamp(2rem,4vw,3rem) clamp(1.4rem,4vw,3rem)}
.spl-page .elementor-widget:not(:last-child){margin-block-end:0}
/* the Elementor form, dressed as the source form */
.spl .spl-form .elementor-form-fields-wrapper{display:grid;grid-template-columns:1fr 1fr;gap:clamp(1rem,3vw,2rem);max-width:520px;margin:0 auto 1.75rem!important}
.spl .spl-form .elementor-field-group{width:100%!important;max-width:none!important;padding:0!important;margin:0!important;flex:none}
.spl .spl-form .elementor-field-type-hidden{display:none}
.spl .spl-form .elementor-field-type-submit{grid-column:1/-1;display:flex;justify-content:center;margin-top:1.75rem!important}
.spl .spl-form input.elementor-field{width:100%;min-height:0;background:transparent!important;border:none!important;border-bottom:1px solid #fff!important;border-radius:0!important;color:#fff!important;font-family:var(--font-ui)!important;font-weight:400;font-size:1rem!important;text-align:right;direction:rtl;padding:0 0 .5rem!important;box-shadow:none!important;transition:border-color .2s ease}
.spl .spl-form input.elementor-field::placeholder{color:rgba(255,255,255,.85);opacity:1}
.spl .spl-form input.elementor-field:focus{border-bottom-color:var(--gold)!important;outline:none}
.spl .spl-form input.elementor-field:focus-visible{outline:2px solid #ffd768;outline-offset:4px}
.spl .spl-form .elementor-button{width:100%;max-width:520px;border:none;border-radius:20px!important;cursor:pointer;color:#0c0900!important;background:var(--grad-gold)!important;font-family:var(--font-display)!important;font-weight:900!important;font-size:clamp(1rem,1.7vw,1.2rem)!important;line-height:1.3;padding:.85rem 1.5rem!important;transition:transform .2s ease,box-shadow .2s ease}
.spl .spl-form .elementor-button:hover{transform:translateY(-2px);box-shadow:0 10px 40px rgba(255,215,104,.25)}
.spl .spl-form .elementor-message{font-family:var(--font-ui);font-size:.95rem;margin-top:.9rem;text-align:center}
.spl .spl-form .elementor-message-success{color:#1bd96a}
.spl .spl-form .elementor-message-danger,.spl .spl-form .elementor-error .elementor-message{color:var(--red)}
.spl .spl-form .elementor-message::before{display:none}
.spl .spl-form .elementor-field-group .elementor-message{display:none}
.spl .spl-err{min-height:1.4em;margin-block-start:.9rem;color:var(--red);font-family:var(--font-ui);font-size:.95rem;text-align:center}
@media (max-width:560px){.spl .spl-form .elementor-form-fields-wrapper{grid-template-columns:1fr;gap:1.5rem}}
"""

PAGE_JS = r"""
<script>
(function(){
  'use strict';
  /* scroll reveal (same behaviour as the source page) */
  function reveal(){
    var els=document.querySelectorAll('.spl .reveal');
    if(!('IntersectionObserver' in window)){els.forEach(function(el){el.classList.add('visible')});return;}
    var io=new IntersectionObserver(function(es){es.forEach(function(e){if(e.isIntersecting)e.target.classList.add('visible');});},{threshold:0.12,rootMargin:'0px 0px -40px 0px'});
    els.forEach(function(el){io.observe(el)});
  }
  /* lead form: the source's validation rules and messages, in front of the Elementor form */
  function form(){
    var f=document.querySelector('.spl .spl-form form'); if(!f) return;
    f.setAttribute('novalidate','');  /* show the source's own messages instead of the browser bubbles */
    var name=f.querySelector('[name="form_fields[name]"]'), phone=f.querySelector('[name="form_fields[phone]"]');
    var err=document.querySelector('.spl .spl-err');
    if(phone){phone.setAttribute('inputmode','numeric');phone.setAttribute('maxlength','10');
      phone.addEventListener('input',function(){this.value=this.value.replace(/\D/g,'').slice(0,10);});}
    document.addEventListener('submit',function(e){
      if(e.target!==f) return;
      var msg='';
      if(!name||name.value.trim().length<2) msg='נא להזין שם מלא';
      else if(!/^05\d{8}$/.test((phone&&phone.value||'').trim())) msg='נא להזין מספר טלפון נייד תקין (05XXXXXXXX)';
      if(err) err.textContent=msg;
      if(msg){e.preventDefault();e.stopPropagation();e.stopImmediatePropagation();(msg.indexOf('שם')>-1?name:phone).focus();}
    },true);
  }
  /* CTA buttons -> #form. Lazy images above the form load during the smooth scroll and push it down
     (the source page stops short for the same reason), so re-aim until the form is in place. */
  document.addEventListener('click',function(e){
    var a=e.target.closest&&e.target.closest('.spl a[href="#form"]'); if(!a) return;
    var t=document.getElementById('form'); if(!t) return; e.preventDefault();
    var off=90, tries=0;
    function aim(){ window.scrollTo({top:t.getBoundingClientRect().top+window.pageYOffset-off,behavior:'smooth'}); }
    aim(); if(history.replaceState) history.replaceState(null,'','#form');
    var iv=setInterval(function(){ tries++; var d=t.getBoundingClientRect().top-off;
      if(Math.abs(d)>30) aim(); if(tries>12||(Math.abs(d)<=30&&tries>3)){ clearInterval(iv); var f=t.querySelector('input'); if(f) f.focus({preventScroll:true}); } },400);
  });
  /* the site-wide accessibility panel links to the general statement; point it to Shay's */
  (function fixA11y(n){var l=document.querySelector('#nv-a11y-foot a[href="/accessibility-declaration/"]');
    if(l){l.setAttribute('href','/shaymentor/accessibility-declaration/');}else if(n<40){setTimeout(function(){fixA11y(n+1)},250);}})(0);
  if(document.readyState!=='loading'){reveal();form();}else document.addEventListener('DOMContentLoaded',function(){reveal();form();});
})();
</script>
"""

# ---------------------------------------------------------------- source sections
html = open(f"{src_dir}/index.html", encoding="utf-8").read()
for a, b in ASSETS.items():
    html = html.replace(f'src="{a}"', f'src="{b}"')
html = html.replace('href="/accessibility"', 'href="/shaymentor/accessibility-declaration/"')
body = html[html.index("<body>") + 6: html.index('<script src="js/main.js">')]
blocks = re.findall(r"(<(header|section|footer)\b.*?</\2>)", body, re.S)
blocks = [b[0] for b in blocks]
assert len(blocks) == 13, len(blocks)  # hero, 10 sections (one is the form), footer, credit footer
form_idx = next(i for i, b in enumerate(blocks) if 'id="form"' in b)
form_block = blocks[form_idx]

# ---------------------------------------------------------------- shared header from the live home page
home = json.load(open(home_json))
hdata = json.loads(home["meta"]["_elementor_data"])
w1 = hdata[0]["elements"][0]["settings"]["html"]
w2 = hdata[0]["elements"][2]["settings"]["html"]
head = w1[: w1.index("</header>") + len("</header>")] + "</div>"  # styles + <div class="syx"> + progress + header
head_js = re.findall(r"<script>(.*?)</script>", w2, re.S)[1]       # header/menu behaviour (no footer markup)
home_form = hdata[0]["elements"][1]["elements"][0]["elements"][0]

fonts = ('<link rel="preconnect" href="https://fonts.googleapis.com"/><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>'
         '<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Hebrew:wght@300;400;500;600;700;800;900&family=Heebo:wght@500;800;900&family=IBM+Plex+Sans+Hebrew:wght@400;500&display=swap" rel="stylesheet"/>')
RESET = ".spl-page .spl *{margin:0;padding:0;box-sizing:border-box}\n"  # beats the Elementor kit, loses to the source rules that follow
first_widget = fonts + "<style>" + RESET + rem_to_px(scope_css(src_css)) + rem_to_px(EXTRA_CSS) + "</style>" + head + "<script>" + head_js + "</script>" + PAGE_JS


def html_widget(markup, title=""):
    return {"id": nid(), "elType": "widget", "widgetType": "html", "settings": {"html": markup, "_title": title}, "elements": []}


def container(classes, children, element_id="", title="", inner=True):
    s = {"flex_direction": "column", "content_width": "full", "flex_gap": {"column": "0", "row": "0", "isLinked": True, "unit": "px"},
         "padding": {"unit": "px", "top": "0", "right": "0", "bottom": "0", "left": "0", "isLinked": True},
         "margin": {"unit": "px", "top": "0", "right": "0", "bottom": "0", "left": "0", "isLinked": True},
         # e-no-lazyload: Elementor's background lazy-load would otherwise strip the gold gradient text
         "css_classes": (classes + " e-no-lazyload").strip(), "_title": title}
    if element_id:
        s["_element_id"] = element_id
    return {"id": nid(), "elType": "container", "isInner": inner, "settings": s, "elements": children}


# form section: [glows + heading] then .form-card [title, Elementor form, error line]
fs_open = re.match(r'<section class="section section--soft" id="form">(.*?)<div class="form-card reveal">', form_block, re.S).group(1)
card_title = re.search(r'<h3 class="form-card__title gold">.*?</h3>', form_block, re.S).group(0)

form = copy.deepcopy(home_form)
form["id"] = nid()
fs = form["settings"]
fs.update({
    "form_name": "סדנת הדגל עוצמה ואמונה – shai-power-landing",
    "email_subject": "ליד חדש: סדנת הדגל ״עוצמה ואמונה״",
    "button_text": "שי, אני רוצה יותר עוצמה ואמונה",
    "submit_actions": ["email"],
    "success_message": "תודה! קיבלנו את הפרטים ונחזור אליכם בהקדם.",
    "required_field_message": "שדה חובה",
    "show_labels": "",
    "column_gap": {"unit": "px", "size": 0, "sizes": []}, "row_gap": {"unit": "px", "size": 0, "sizes": []},
    "_css_classes": "spl-form",
})
fs.pop("redirect_to", None)
fs["form_fields"] = [
    {"_id": nid(), "custom_id": "name", "field_type": "text", "field_label": "שם מלא", "placeholder": "שם מלא", "required": "true", "width": "50", "width_mobile": "100"},
    {"_id": nid(), "custom_id": "phone", "field_type": "tel", "field_label": "טלפון", "placeholder": "טלפון", "required": "true", "width": "50", "width_mobile": "100"},
    {"_id": nid(), "custom_id": "service", "field_type": "hidden", "field_label": "שירות מבוקש", "field_value": "סדנת הדגל ״עוצמה ואמונה״", "width": "100"},
    {"_id": nid(), "custom_id": "source_page", "field_type": "hidden", "field_label": "עמוד מקור", "field_value": "/shai-power-landing/", "width": "100"},
]

form_section = container("spl", [
    container("section section--soft", [
        html_widget(fs_open, "טופס: רקע וכותרת"),
        container("form-card reveal", [
            html_widget(card_title, "טופס: כותרת הכרטיס"),
            form,
            html_widget('<div class="spl-err" role="status" aria-live="polite"></div>', "טופס: הודעת ולידציה"),
        ], title="כרטיס הטופס"),
    ], element_id="form", title="פרטים להרשמה"),
], title="סקשן טופס", inner=False)

TITLES = ["הירו", "הגישה + 4 השלבים", "הכאבים", "קצת עליי", "מהתחתית לפסגה", "איך תצאו בסוף התהליך", "ארבעת השלבים",
          "ההשקעה הכלכלית", "מי שמגיע עד לפה", "פרטים להרשמה", "שאלות ותשובות", "פוטר", "קרדיט הבונה"]
children = [html_widget(first_widget, "כותרת האתר + סגנונות העמוד")]
for i, b in enumerate(blocks):
    if i == form_idx:
        children.append(form_section)
    else:
        children.append(html_widget('<div class="spl">' + b + "</div>", TITLES[i] if i < len(TITLES) else f"בלוק {i+1}"))

root = container("syx-root spl-page", children, title="shai-power-landing", inner=False)
root["isInner"] = False
json.dump({"elementor_data": json.dumps([root], ensure_ascii=False)}, open(out_path, "w"), ensure_ascii=False)
print("blocks:", len(blocks), "form index:", form_idx, "widgets:", len(children))
