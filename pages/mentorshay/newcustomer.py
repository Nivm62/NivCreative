#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""Builds /new-customer/ on shayyfrach.co.il: a preparation page for people who already booked a "business breakthrough" meeting.
   Not a sales page and not a lead page: no form, no purchase/registration/lead CTA.

   SHAY_AUTH='Basic <base64 user:app-password>' python3 newcustomer.py preview   # writes /tmp/ms/nc/preview.html (images already mapped to the destination site)
   SHAY_AUTH=... python3 newcustomer.py page                                       # creates/updates the page as a DRAFT (+ noindex through Yoast)
The credential comes from the environment only (never stored in the repo).
"""
import json, os, re, subprocess, sys, time
HERE = os.path.dirname(os.path.abspath(__file__)); sys.path.insert(0, HERE)
import build

TARGET = "https://shayyfrach.co.il"
SLUG = "new-customer"
AUTH = os.environ.get("SHAY_AUTH", "")
UP = TARGET + "/wp-content/uploads/2026/11/"
OLD_UP = re.compile(r'https://nivcreative\.com/wp-content/uploads/[^"\'\)\s\\<>]+')
# The destination host rejects uploads over ~2 MB (nginx 413), so this 9.4 MB video is hosted on nivcreative.com until the limit is raised;
# then upload it to the destination media library and set this constant to the new URL.
ACTION_VIDEO = "https://nivcreative.com/wp-content/uploads/2026/11/shay-in-action.mp4"
INTRO_VIDEO = "https://nivcreative.com/wp-content/uploads/2026/11/shay-intro.mp4"
VOICES_VIDEO = "https://nivcreative.com/wp-content/uploads/2026/11/shay-testimonials.mp4"
VOICES_VIDEO_2 = "https://nivcreative.com/wp-content/uploads/2026/11/shay-testimonials-2.mp4"
VOICES_VIDEO_3 = "https://nivcreative.com/wp-content/uploads/2026/10/WhatsApp-Video-2026-10-28-at-21.19.35.mp4"
KNOWN_MEDIA = ["ms-bg-ivory.webp", "ms-bg-midnight.webp", "ms-hero-stage.webp", "syx-card-1to1.webp", "ms-bg-ivory-glow.webp", "ms-logo-crown.webp", "ms-logo-text.webp", "ms-story-wide.webp",
               "spl-s6-speaking.webp", "ms-conf-hero-stage.webp", "shay-in-action-poster.webp", "shay-intro-poster.webp", "shay-testimonials-poster.webp", "shay-testimonials-2-poster.webp", "ms-testimonial-6.webp"]
PRIVACY, ACCESS = "/מדיניות-פרטיות/", "/accessibility-statement/"
IC, ARR = build.IC, build.ARR
PLAY = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg>'
DOWN = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 4v15M5 12l7 7 7-7"/></svg>'

def words(t, i0, cls=""):
    return " ".join(f'<span class="w"><span style="--i:{i0 + k}"{cls}>{w}</span></span>' for k, w in enumerate(t.split()))

def video(vid, label, sub, poster, big=False, src="", vtt="", note="", portrait=False):
    """Click-to-play video block. With no `src` it renders an inert, clearly marked placeholder (replace data-src / data-vtt later)."""
    ready = bool(src)
    state = "" if ready else ' aria-disabled="true"'
    aria = f'נגן סרטון: {label}' if ready else f'{label}: הסרטון יתווסף בקרוב'
    ph = "" if ready else f'<p class="nc-ph-note">{note}</p>'
    return f'''<div class="nc-video{" is-big" if big else ""}{" is-portrait" if portrait else ""} rv" id="{vid}" data-video="{vid}" data-src="{src}" data-vtt="{vtt}" style="background-image:url({poster})" role="group" aria-label="{label}">
 <button class="nc-play" type="button"{state} aria-label="{aria}">{PLAY}</button>
 <div class="nc-vlabel"><span>{label}</span><small>{sub}</small></div></div>{ph}'''

def logobar():
    return f'''<header class="nc-logobar" id="syxHead"><a class="syx-logo v2-logo" href="/" aria-label="שי יפרח – לאתר הראשי"><span class="v2-mark" role="img" aria-label="שי יפרח"><img src="https://nivcreative.com/wp-content/uploads/2026/11/ms-logo-crown.webp" alt="" width="553" height="337"><img src="https://nivcreative.com/wp-content/uploads/2026/11/ms-logo-text.webp" alt="" width="1242" height="337"></span><small>העצמה<i>|</i>הגשמה<i>|</i>תוצאות</small></a></header>
<span class="sp-skip-target" id="nv-main-content" tabindex="-1" style="display:block;position:relative;top:-90px"></span>'''

def foot():
    return f'''<footer class="nc-foot" id="ncFoot"><div class="nc-fhalo" aria-hidden="true"></div><canvas class="nc-fstars" id="ncStars2" aria-hidden="true"></canvas><div class="wrap"><a class="syx-logo v2-logo" href="/" aria-label="שי יפרח – לאתר הראשי"><span class="v2-mark" role="img" aria-label="שי יפרח"><img src="https://nivcreative.com/wp-content/uploads/2026/11/ms-logo-crown.webp" alt="" width="553" height="337" loading="lazy"><img src="https://nivcreative.com/wp-content/uploads/2026/11/ms-logo-text.webp" alt="" width="1242" height="337" loading="lazy"></span><small>העצמה<i>|</i>הגשמה<i>|</i>תוצאות</small></a>
 <nav aria-label="קישורי תחתית"><a href="{PRIVACY}">מדיניות פרטיות</a><a href="{ACCESS}">הצהרת נגישות</a></nav></div></footer>'''

def voices_slider():
    """3 testimonial videos in the site's video slider (click-to-play, centered start on the middle card)."""
    cards = [(VOICES_VIDEO, UP + "shay-testimonials-poster.webp", "2:34", 154), (VOICES_VIDEO_2, UP + "shay-testimonials-2-poster.webp", "1:33", 93), (VOICES_VIDEO_3, UP + "ms-testimonial-6.webp", "0:49", 49)]
    vc = "".join(f'''<article class="ms-vcard" data-src="{src}" data-poster="{po}" data-label="עדות {k+1}: סרטון של משתתפת או משתתף" aria-roledescription="שקופית" aria-label="עדות {k+1} מתוך {len(cards)}"><div class="ms-frame"><img src="{po}" alt="" loading="lazy" decoding="async" width="576" height="1024"><button class="ms-play" type="button" aria-label="נגן עדות {k+1}, {d} דקות">{PLAY}</button><span class="ms-dur" aria-hidden="true">{d}</span></div></article>''' for k, (src, po, d, sec) in enumerate(cards))
    dots = "".join(f'<button type="button" role="tab" aria-selected="{"true" if k == 1 else "false"}" aria-label="עדות {k+1}"></button>' for k in range(len(cards)))
    A = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="%s"/></svg>'
    return f'''<div class="ms-slider nc-voices rv" data-start="1" role="region" aria-roledescription="סליידר" aria-label="סרטוני עדויות"><div class="ms-track" tabindex="0" aria-label="גלילה בין העדויות">{vc}</div>
 <div class="ms-ctrl"><button class="ms-prev" type="button" aria-label="העדות הקודמת">{A % "M9 5l7 7-7 7"}</button><div class="ms-meta"><div class="ms-prog" aria-hidden="true"><i></i></div><div class="ms-dots" role="tablist" aria-label="בחירת עדות">{dots}</div></div><button class="ms-next" type="button" aria-label="העדות הבאה">{A % "M15 5l-7 7 7 7"}</button></div><p class="ms-live" aria-live="polite" style="position:absolute;left:-9999px"></p></div>'''

def body():
    z = lambda n: f' style="--z:{n}"'
    hero = f'''<section class="syx-hero ms-hero nc-hero ms-sec ms-dark" id="syxHero" aria-labelledby="nc-h1"{z(60)}>
 <div class="bg" role="img" aria-label="שי יפרח מחייך על הבמה"></div><div class="sheen" aria-hidden="true"></div><div class="spot" aria-hidden="true"></div><div class="nc-halo" aria-hidden="true"></div><canvas class="nc-stars" id="ncStars" aria-hidden="true"></canvas>
 <div class="wrap"><div class="copy">
  <p class="nc-kicker hero-in">שי יפרח | פריצה עסקית</p>
  <h1 id="nc-h1" aria-label="הפגישה שלך כבר נקבעה. עכשיו מתחילה ההיכרות שלנו"><span class="ln">{words("הפגישה שלך כבר נקבעה.", 0)}</span><span class="ln">{words("עכשיו מתחילה ההיכרות שלנו", 4, ' class="nc-gold"')}</span></h1>
  <p class="lead hero-in" style="--i:1">לפני שניפגש, הכנו לך כמה דקות של תוכן שיעזרו לך להבין מי אנחנו, איך אנחנו חושבים על הצלחה עסקית ומה מחכה לך בפגישה.</p>
  <a class="nc-cue hero-in" style="--i:2" href="#nc-s2">גוללים להמשך{DOWN}</a>
 </div></div></section>'''
    cards = [("chart", "הלקוחות לא מגיעים בצורה עקבית, וההכנסה משתנה מחודש לחודש."),
             ("mega", "יש רצון לשווק ולמכור, אבל משהו בפנים עוצר."),
             ("sprout", "יש תחושה של פוטנציאל גדול, בלי דרך מספיק ברורה לממש אותו.")]
    s2 = f'''<section class="ms-sec ms-ivory ms-center nc-sec" id="nc-s2" aria-labelledby="nc-h-s2"{z(50)}><div class="wrap">
 <span class="nc-step rv"><b>01</b> מכירים את זה?</span>
 <h2 class="ms-h2 rv" id="nc-h-s2" style="--i:1">יש לך מקצוע. יש לך יכולת.<br>אבל האם יש לך את העסק שרצית?</h2>
 <p class="nc-lead rv" style="--i:2">לא מעט מאמנים ומטפלים משקיעים שנים בלימודים, בהתפתחות אישית ובשיפור היכולת המקצועית שלהם.<br>ועדיין, כשהם מסתכלים על העסק שלהם, הם פוגשים מציאות אחרת.</p>
 <ul class="nc-cards">{"".join(f'<li class="nc-card rv" style="--i:{k}"><span class="nc-ic">{IC[ic]}</span>{t}</li>' for k, (ic, t) in enumerate(cards))}</ul>
 <p class="nc-after rv">אם משהו כאן מוכר לך, זו בדיוק הסיבה שאנחנו נפגשים.</p></div></section>'''
    s3 = f'''<section class="ms-sec ms-dark ms-midnight ms-center nc-sec" id="nc-s3" aria-labelledby="nc-h-s3"{z(40)}><div class="wrap">
 <span class="nc-step rv"><b>02</b> ההיכרות</span>
 <h2 class="ms-h2 rv" id="nc-h-s3" style="--i:1">נעים להכיר, שי יפרח</h2>
 <p class="nc-lead rv" style="--i:2">לפני שמדברים על פתרונות, חשוב לנו שתכירו את האדם ואת דרך החשיבה שמאחורי התהליך.</p>
 <div class="nc-meet" style="margin-top:34px">{video("nc-v-intro", "היכרות עם שי", "4:27 דקות", UP + "shay-intro-poster.webp", portrait=True, src=INTRO_VIDEO)}</div></div></section>'''
    s4 = f'''<section class="ms-sec ms-ivory ms-center nc-sec" id="nc-s4" aria-labelledby="nc-h-s4"{z(30)}><div class="wrap">
 <span class="nc-step rv"><b>03</b> שי בפעולה</span>
 <h2 class="ms-h2 rv" id="nc-h-s4" style="--i:1">איך שי מסתכל על עסק שרוצה לפרוץ קדימה?</h2>
 <p class="nc-lead rv" style="--i:2">לפעמים שינוי משמעותי מתחיל דווקא משאלה אחת נכונה.<br>הנה הצצה לדרך שבה שי מסתכל על אתגרים עסקיים, מזהה חסמים ועוזר לבעלי עסקים לחשוב אחרת.</p>
 <div style="margin-top:34px">{video("nc-v-action", "שי בפעולה", "1:24 דקות", UP + "shay-in-action-poster.webp", big=True, portrait=True, src=ACTION_VIDEO)}</div></div></section>'''
    s5 = f'''<section class="ms-sec ms-dark ms-center nc-sec" id="nc-s5" aria-labelledby="nc-h-s5"{z(20)}><div class="wrap">
 <span class="nc-step rv"><b>04</b> קולות מהדרך</span>
 <h2 class="ms-h2 rv" id="nc-h-s5" style="--i:1">גם הם התחילו עם שאלות וחששות</h2>
 <p class="nc-lead rv" style="--i:2">זה טבעי לתהות האם אפשר לעשות שינוי אמיתי בעסק והאם התהליך מתאים לך.<br>במקום להבטיח הבטחות, אנחנו מעדיפים לתת לאנשים שעבדו איתנו לספר על הדרך שלהם.</p>
 <div style="margin-top:34px">{voices_slider()}</div>
 <p class="nc-note rv">כל עדות משקפת חוויה אישית של המשתתף. היא אינה הבטחה לתוצאה דומה.</p></div></section>'''
    PIN = '<svg viewBox="0 0 48 48" aria-hidden="true"><path d="M24 43s13-10.5 13-21a13 13 0 0 0-26 0c0 10.5 13 21 13 21z"/><circle cx="24" cy="22" r="4.5"/></svg>'
    LOCK = '<svg viewBox="0 0 48 48" aria-hidden="true"><rect x="11" y="22" width="26" height="19" rx="4"/><path d="M17 22v-6a7 7 0 0 1 14 0v6M24 29v6"/></svg>'
    chips = [(PIN, "איפה הדברים עומדים היום"), (IC["target"], "לאן היית רוצה להגיע"), (LOCK, "מה כרגע מונע ממך להתקדם")]
    s6 = f'''<section class="ms-sec ms-ivory nc-sec" id="nc-s6" aria-labelledby="nc-h-s6"{z(10)}><div class="wrap">
 <div class="ms-center"><span class="nc-step rv"><b>05</b> לקראת הפגישה</span>
 <h2 class="ms-h2 rv" id="nc-h-s6" style="--i:1">בפגישה הקרובה, העסק שלך במרכז</h2></div>
 <div class="nc-final" style="margin-top:34px"><div class="ms-photo rv" style="--i:1"><img src="{UP}ms-story-wide.webp" alt="שי יפרח מצלם סלפי עם קהל משתתפים מחייך באולם" width="1672" height="941" loading="lazy" decoding="async"></div>
 <div><p class="rv">אנחנו הולכים להכיר אותך ואת העסק שלך, ולהבין:</p>
 <ol class="nc-journey rv" style="--i:1">{"".join(f'<li style="--k:{k}"><span class="nc-node">{ic}</span><span class="nc-jt">{t}</span></li>' for k, (ic, t) in enumerate(chips))}</ol>
 <p class="rv" style="--i:2">המטרה היא לייצר בהירות ולבדוק התאמה לשני הצדדים.</p>
 <p class="nc-sign rv" style="--i:3">מחכים להכיר אותך ולשמוע את הסיפור שלך.<br><span class="nc-soon"><span class="t">נתראה בקרוב!</span> <span class="nc-heart">❤️</span><svg viewBox="0 0 400 130" preserveAspectRatio="none" aria-hidden="true"><path pathLength="1" d="M30 70 C 22 20 210 4 330 22 C 400 36 396 100 280 114 C 160 128 20 112 22 66 C 24 34 120 10 200 8"/></svg></span></p></div></div></div></section>'''
    return hero + s2 + s3 + s4 + s5 + s6

# click-to-play (no autoplay); real video + captions are attached only when data-src / data-vtt are set. Reports to the Meta Pixel that already exists on the site (PixelYourSite) if fbq is present.
NC_JS = r'''<script>(function(){var d=document,reduce=matchMedia('(prefers-reduced-motion: reduce)').matches;
function ev(name,id,extra){try{if(window.fbq){var p={video:id};for(var k in extra)p[k]=extra[k];fbq('trackCustom',name,p)}}catch(e){}try{if(window.dataLayer)dataLayer.push({event:name,video:id})}catch(e){}}
[].slice.call(d.querySelectorAll('.nc-ic svg *,.nc-node svg *')).forEach(function(e){e.setAttribute('pathLength','1')});
var dts=[].slice.call(d.querySelectorAll('.nc-dots i'));
if('IntersectionObserver' in window&&dts.length&&!reduce){var io=new IntersectionObserver(function(es){es.forEach(function(e){if(e.isIntersecting){dts.forEach(function(x){x.classList.toggle('on',x.getAttribute('data-for')===e.target.id)})}})},{rootMargin:'-45% 0px -45% 0px'});dts.forEach(function(x){var t=d.getElementById(x.getAttribute('data-for'));if(t)io.observe(t)})}

/* glowing stars over the hero (cheap sprites, no shadowBlur); paused off-screen / in background tabs; static for reduced motion */
function ncStars(cvId,hostId,nd,nm){var cv=d.getElementById(cvId),hero=d.getElementById(hostId);if(!cv||!hero||!cv.getContext)return;
var ctx=cv.getContext('2d'),dpr=Math.min(2,window.devicePixelRatio||1),W=0,H=0,P=[],run=false,lite=!!d.querySelector('.nc-lite'),N=innerWidth<700?(lite?Math.round(nm/2):nm):(lite?Math.round(nd/2):nd);
function sprite(spark){var s=64,c=d.createElement('canvas');c.width=c.height=s;var g=c.getContext('2d'),r=g.createRadialGradient(32,32,0,32,32,32);
 r.addColorStop(0,'rgba(255,250,225,1)');r.addColorStop(.18,'rgba(255,226,150,.85)');r.addColorStop(.5,'rgba(233,196,110,.22)');r.addColorStop(1,'rgba(233,196,110,0)');g.fillStyle=r;g.fillRect(0,0,s,s);
 if(spark){g.globalCompositeOperation='lighter';var l=g.createLinearGradient(0,32,64,32);l.addColorStop(0,'rgba(255,240,190,0)');l.addColorStop(.5,'rgba(255,248,215,.95)');l.addColorStop(1,'rgba(255,240,190,0)');g.fillStyle=l;g.fillRect(2,31,60,2);
  var v=g.createLinearGradient(32,0,32,64);v.addColorStop(0,'rgba(255,240,190,0)');v.addColorStop(.5,'rgba(255,248,215,.95)');v.addColorStop(1,'rgba(255,240,190,0)');g.fillStyle=v;g.fillRect(31,2,2,60)}return c}
var S=[sprite(false),sprite(true)];
function mk(init){var sp=Math.random()<.16;return{x:Math.random()*W,y:init?Math.random()*H:H+20,r:sp?9+Math.random()*9:2.5+Math.random()*5.5,v:.12+Math.random()*.34,a:.45+Math.random()*.55,tw:.6+Math.random()*1.8,ph:Math.random()*6.28,dx:.15+Math.random()*.5,sp:sp?1:0}}
function size(){var r=hero.getBoundingClientRect();W=r.width;H=r.height;cv.width=Math.round(W*dpr);cv.height=Math.round(H*dpr);ctx.setTransform(dpr,0,0,dpr,0,0)}
function draw(t,step){ctx.clearRect(0,0,W,H);for(var i=0;i<P.length;i++){var p=P[i];if(step){p.y-=p.v;p.x+=Math.sin(t/1700*p.dx+p.ph)*.22;if(p.y<-24){P[i]=p=mk(false)}}
  var al=p.a*(.5+.5*Math.sin(t/1000*p.tw+p.ph));if(al<.03)continue;ctx.globalAlpha=al;var sz=p.r*2;ctx.drawImage(S[p.sp],p.x-p.r,p.y-p.r,sz,sz)}ctx.globalAlpha=1}
function frame(t){if(!run)return;draw(t,true);requestAnimationFrame(frame)}
function start(){if(run||reduce)return;run=true;requestAnimationFrame(frame)}
size();for(var i=0;i<N;i++)P.push(mk(true));
if(reduce){draw(0,false)}
addEventListener('resize',function(){size();if(reduce)draw(0,false)});
if('IntersectionObserver' in window){new IntersectionObserver(function(es){if(es[0].isIntersecting)start();else run=false},{threshold:0}).observe(hero)}else start();
d.addEventListener('visibilitychange',function(){if(d.hidden)run=false;else start()});
}
ncStars('ncStars','syxHero',80,40);ncStars('ncStars2','ncFoot',44,22);
var all=[].slice.call(d.querySelectorAll('.nc-video'));
all.forEach(function(box){var btn=box.querySelector('.nc-play'),id=box.getAttribute('data-video'),sent={};
 btn.addEventListener('click',function(){if(btn.getAttribute('aria-disabled')==='true'||!box.getAttribute('data-src'))return;
  var v=box.querySelector('video');if(!v){v=d.createElement('video');v.src=box.getAttribute('data-src');v.controls=true;v.playsInline=true;v.setAttribute('playsinline','');v.preload='metadata';v.setAttribute('aria-label',box.getAttribute('aria-label'));
   if(box.getAttribute('data-vtt')){var t=d.createElement('track');t.kind='captions';t.srclang='he';t.label='עברית';t.src=box.getAttribute('data-vtt');t.default=true;v.appendChild(t)}
   box.appendChild(v);
   v.addEventListener('play',function(){all.forEach(function(o){var ov=o.querySelector('video');if(ov&&ov!==v)ov.pause()});if(!sent.p){sent.p=1;ev('NewCustomerVideoPlay',id)}});
   v.addEventListener('timeupdate',function(){if(!v.duration)return;var pc=Math.floor(v.currentTime/v.duration*4)*25;if(pc>=25&&!sent[pc]){sent[pc]=1;ev('NewCustomerVideoProgress',id,{percent:pc})}});
   v.addEventListener('ended',function(){if(!sent[100]){sent[100]=1;ev('NewCustomerVideoProgress',id,{percent:100})}})}
  btn.style.display='none';box.querySelector('.nc-vlabel').style.display='none';var pr=v.play();if(pr&&pr.catch)pr.catch(function(){})});
});
if('IntersectionObserver' in window){all.forEach(function(b){new IntersectionObserver(function(es){if(!es[0].isIntersecting){var v=b.querySelector('video');if(v)v.pause()}},{threshold:0}).observe(b)})}
})();</script>'''

def dots():
    items = [("syxHero", "פתיחה"), ("nc-s2", "הזדהות"), ("nc-s3", "היכרות"), ("nc-s4", "שי בפעולה"), ("nc-s5", "קולות מהדרך"), ("nc-s6", "לקראת הפגישה")]
    return '<div class="nc-dots" aria-hidden="true">' + "".join(f'<i data-for="{i}"><b>{t}</b></i>' for i, t in items) + '</div>'

# weak devices (<=4 cores, <=4 GB, or data-saver): keep the reveals, drop the always-running loops (dust canvas, push-in, shimmer, pulses)
LITE_JS = "<script>(function(){var n=navigator,e=document.currentScript.parentNode;if((n.hardwareConcurrency&&n.hardwareConcurrency<=4)||(n.deviceMemory&&n.deviceMemory<=4)||(n.connection&&n.connection.saveData))e.classList.add('nc-lite')})()</script>"

def page_js():
    j = build.js_conf(); a = "cv.getContext&&w.innerWidth>=820){"
    assert a in j
    return j.replace(a, "cv.getContext&&w.innerWidth>=820&&!w.document.querySelector('.nc-lite')){", 1)

def html_doc():
    return (build.FONTS + "<style>" + build.css() + "\n" + build.rd("nc.css") + "</style>" +
            f'<div class="syx" id="top">{build.JSFLAG}{LITE_JS}<div class="syx-progress" aria-hidden="true"><i></i></div>' + logobar() + dots() + "<main>" + body() + "</main>" + foot() + "</div>" + page_js() + NC_JS + build.rd("ms_slider.js"))

# ---------------------------------------------------------------- destination site
def api(method, path, body=None):
    cmd = ["curl", "-sS", "-m", "120", "-X", method, TARGET + "/wp-json" + path, "-H", "Authorization: " + AUTH, "-H", "Content-Type: application/json"]
    if body is not None:
        os.makedirs("/tmp/ms/nc", exist_ok=True); open("/tmp/ms/nc/payload.json", "w", encoding="utf-8").write(json.dumps(body, ensure_ascii=False)); cmd += ["--data-binary", "@/tmp/ms/nc/payload.json"]
    out = subprocess.run(cmd, capture_output=True, text=True).stdout
    try: return json.loads(out)
    except Exception: return {"raw": out[:200]}

def library():
    """filename -> url of every image already in the destination media library"""
    m = {}
    for p in range(1, 6):
        for _ in range(8):   # the host sometimes answers with an anti-bot interstitial instead of JSON: retry
            r = api("GET", f"/wp/v2/media?per_page=100&page={p}&media_type=image&_fields=source_url")
            if isinstance(r, list): break
            time.sleep(6)
        if not isinstance(r, list): return None   # library listing blocked by the host: caller falls back to the known upload paths
        if not r: break
        for x in r: m[x["source_url"].rsplit("/", 1)[1]] = x["source_url"]
    return m

def map_media(h):
    lib = library(); missing = []
    if lib is None:   # all of these were uploaded to the destination media library under /2026/11/ (verified earlier)
        lib = {f: UP + f for f in KNOWN_MEDIA}
    def sub(x):
        fn = x.group(0).rsplit("/", 1)[1]
        if fn.endswith(".mp4"): return x.group(0)   # video: see ACTION_VIDEO
        if fn in lib: return lib[fn]
        missing.append(x.group(0)); return x.group(0)
    h = OLD_UP.sub(sub, h)
    assert not missing, ("not in the destination media library (upload first)", sorted(set(missing)))
    return h

def build_html():
    h = map_media(html_doc())
    h = re.sub(r'https://nivcreative\.com(?![^"\'\s<>]*\.mp4)', "NIVCREATIVE-LEFTOVER", h)
    assert "NIVCREATIVE-LEFTOVER" not in h, "a nivcreative.com URL is still referenced"
    return h

def elementor(h):
    return [{"id": "ncroot0", "elType": "container", "settings": {"flex_direction": "column", "content_width": "full", "padding": {"unit": "px", "top": "0", "right": "0", "bottom": "0", "left": "0", "isLinked": True}, "css_classes": "syx-root"},
             "elements": [{"id": "nchtml0", "elType": "widget", "settings": {"html": h}, "elements": [], "widgetType": "html"}], "isInner": False}]

def page():
    data = elementor(build_html())
    title = "הכנה לפגישת פריצה עסקית | שי יפרח"
    ex = api("GET", f"/wp/v2/pages?slug={SLUG}&status=any&context=edit&_fields=id,status")
    assert isinstance(ex, list), ("slug lookup failed, not creating a duplicate page; retry", str(ex)[:120])
    pid = ex[0]["id"] if ex else None
    status = ex[0]["status"] if pid else "draft"
    payload = {"title": title, "slug": SLUG, "status": status, "template": "elementor_canvas",
               "meta": {"_elementor_edit_mode": "builder", "_elementor_template_type": "wp-page", "_elementor_version": "4.4.0", "_elementor_data": json.dumps(data, ensure_ascii=False),
                        "_elementor_page_settings": {"hide_title": "yes", "background_background": "classic", "background_color": "#0B0B0C"}}}
    r = api("POST", f"/wp/v2/pages/{pid}" if pid else "/wp/v2/pages", payload)
    print({k: r.get(k) for k in ("id", "status", "link", "slug", "code", "message")})
    if "id" in r:
        s = api("POST", "/wp-abilities/v1/abilities/yoast-seo/update-post-seo-data/run", {"input": {"post_id": r["id"], "noindex": True, "seo_title": title, "meta_description": "הכנה קצרה לקראת פגישת פריצה עסקית עם שי יפרח."}})
        print("yoast noindex:", json.dumps(s, ensure_ascii=False)[:200])
    subprocess.run(["curl", "-sS", "-m", "60", "-X", "DELETE", TARGET + "/wp-json/elementor/v1/cache", "-H", "Authorization: " + AUTH], capture_output=True)

if __name__ == "__main__":
    if sys.argv[1] == "preview":
        os.makedirs("/tmp/ms/nc", exist_ok=True)
        open("/tmp/ms/nc/preview.html", "w", encoding="utf-8").write('<!doctype html><html lang="he" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>new-customer</title></head><body style="margin:0;background:#0b0b0c">' + build_html() + "</body></html>")
        print("preview written")
    elif sys.argv[1] == "page": page()
