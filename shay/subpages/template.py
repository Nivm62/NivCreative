# Generic inner-page template for the Shay Yifrach site (content area between the shared
# header and the shared contact block). Every page is described as data in pages.py and
# rendered here into one HTML string that sits inside the first Elementor HTML widget.
import html

HERO_IMG = "https://nivcreative.com/wp-content/uploads/2026/10/shay-inner-hero.webp"

ARROW = ('<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" '
         'stroke-linejoin="round" aria-hidden="true"><path d="M19 12H5M11 18l-6-6 6-6"/></svg>')

CSS = r"""
<style>
/* ---------- inner pages (sp-*) ---------- */
.syx .sp-skip-target{position:relative;top:-90px}
.syx .sp-hero{position:relative;overflow:hidden;background:#040a18 url(__IMG__) left center/cover no-repeat;padding:150px 0 64px;min-height:440px;display:flex;align-items:flex-end}
.syx .sp-hero::before{content:"";position:absolute;inset:0;background:linear-gradient(270deg,rgba(4,10,24,.92) 0%,rgba(4,10,24,.78) 38%,rgba(4,10,24,.15) 68%,rgba(4,10,24,0) 100%),linear-gradient(0deg,rgba(4,10,24,.55),transparent 35%)}
.syx .sp-hero .wrap{position:relative;z-index:2}
.syx .sp-hero-in{max-width:640px}
.syx .sp-crumbs ol{list-style:none;margin:0 0 18px;padding:0;display:flex;flex-wrap:wrap;gap:8px;font-size:14px;color:rgba(255,255,255,.75)}
.syx .sp-crumbs li{display:flex;align-items:center;gap:8px}
.syx .sp-crumbs li::before{content:none}
.syx .sp-crumbs li+li::before{content:"/";color:rgba(255,255,255,.45)}
.syx .sp-crumbs a{color:#fff;text-decoration:underline;text-decoration-color:rgba(235,198,108,.5);text-underline-offset:4px}
.syx .sp-crumbs [aria-current]{color:rgba(255,255,255,.75)}
.syx .sp-tag{display:block;font-size:15px;font-weight:700;color:var(--gold2);margin-bottom:10px}
.syx .sp-hero h1{margin:0;font-size:clamp(34px,5vw,62px);line-height:1.08;font-weight:900;letter-spacing:-.03em;color:#fff}
.syx .sp-hero h1 .sp-gold{background:var(--goldg);-webkit-background-clip:text;background-clip:text;color:transparent}
.syx .sp-lead{margin:16px 0 0;font-size:clamp(17px,1.7vw,21px);line-height:1.6;color:rgba(255,255,255,.9);max-width:600px}
.syx .sp-hero-in::after{content:"";display:block;width:64px;height:4px;border-radius:4px;background:var(--goldg);margin-top:22px}
.syx .sp-ctas{display:flex;flex-wrap:wrap;gap:12px;margin-top:26px}
.syx .sp-tabs{position:sticky;top:0;z-index:5;background:rgba(251,248,240,.96);backdrop-filter:blur(8px);border-bottom:1px solid rgba(197,150,93,.35)}
.syx .sp-tabs ul{list-style:none;margin:0 auto;padding:0;display:flex;justify-content:center;flex-wrap:wrap;gap:4px 0}
.syx .sp-tabs li::before{content:none}
.syx .sp-tabs li+li{border-right:1px solid rgba(14,28,56,.15)}
.syx .sp-tabs a{display:block;padding:18px 26px 16px;font-size:16px;font-weight:700;color:#1d2433;position:relative}
.syx .sp-tabs a::after{content:"";position:absolute;bottom:8px;right:50%;width:0;height:3px;border-radius:3px;background:#C5965D;transform:translateX(50%);transition:width .3s var(--out)}
.syx .sp-tabs a.is-on::after,.syx .sp-tabs a:hover::after{width:44px}
.syx .sp-main{background:linear-gradient(180deg,#fbf8f0,var(--cream));color:#0e1c38;padding:clamp(40px,6vw,72px) 0}
.syx .sp-sec{scroll-margin-top:120px}
.syx .sp-sec+.sp-sec{margin-top:clamp(44px,6vw,72px)}
.syx .sp-h2{margin:0;font-size:clamp(26px,3vw,38px);line-height:1.15;font-weight:900;letter-spacing:-.02em;color:#0e1c38}
.syx .sp-kick{display:block;font-size:14px;font-weight:700;color:#B9894F;margin-bottom:6px}
.syx .sp-sub{margin:10px 0 0;font-size:17px;line-height:1.6;color:#2b3445;max-width:760px}
.syx .sp-prose{margin-top:16px;max-width:820px}
.syx .sp-prose p{margin:0 0 14px;font-size:17px;line-height:1.75;color:#1d2433}
.syx .sp-prose strong{color:#0e1c38}
.syx .sp-grid{display:grid;gap:18px;margin-top:24px}
.syx .sp-grid.c2{grid-template-columns:repeat(2,1fr)}
.syx .sp-grid.c3{grid-template-columns:repeat(3,1fr)}
.syx .sp-card{position:relative;padding:24px 24px 22px;border-radius:18px;background:rgba(255,255,255,.78);border:1px solid rgba(197,150,93,.4);box-shadow:0 10px 30px rgba(14,28,56,.05)}
.syx .sp-card .n{display:block;font-size:30px;font-weight:300;line-height:1;color:#C5965D;margin-bottom:10px;font-variant-numeric:tabular-nums}
.syx .sp-card h3{margin:0;font-size:20px;font-weight:800;line-height:1.3;color:#0e1c38}
.syx .sp-card p{margin:8px 0 0;font-size:16px;line-height:1.65;color:#2b3445}
.syx .sp-card ul{margin:10px 0 0;padding:0 18px 0 0}
.syx .sp-card li{font-size:15.5px;line-height:1.6;color:#2b3445;margin:4px 0}
.syx .sp-card.dark{background:linear-gradient(160deg,#13264a,#0a1630);border-color:rgba(235,198,108,.4)}
.syx .sp-card.dark h3{color:#fff}.syx .sp-card.dark p,.syx .sp-card.dark li{color:rgba(255,255,255,.88)}.syx .sp-card.dark .n{color:var(--gold2)}
.syx .sp-list{list-style:none;margin:12px 0 0;padding:0;display:grid;gap:10px}
.syx .sp-list li{position:relative;padding-right:30px;font-size:16px;line-height:1.6;color:#1d2433}
.syx .sp-list li::before{content:"";position:absolute;right:0;top:4px;width:18px;height:18px;border-radius:50%;background:#C5965D url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='white' stroke-width='3.4' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M5 12.5l4.5 4.5L19 7.5'/%3E%3C/svg%3E") center/12px no-repeat}
.syx .sp-list.no li::before{background:#8b93a3 url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='white' stroke-width='3.4' stroke-linecap='round'%3E%3Cpath d='M7 7l10 10M17 7L7 17'/%3E%3C/svg%3E") center/11px no-repeat}
.syx .sp-media{margin-top:24px;border-radius:20px;overflow:hidden;border:1.5px solid rgba(197,150,93,.6);box-shadow:0 24px 60px rgba(14,28,56,.16);background:#0a1630}
.syx .sp-media img,.syx .sp-media video{display:block;width:100%;height:auto}
.syx .sp-media figcaption{padding:12px 18px;font-size:14px;color:rgba(255,255,255,.8);background:#0a1630}
.syx .sp-yt{position:relative;display:block;width:100%;aspect-ratio:16/9;border:0;padding:0;cursor:pointer;background:#0a1630 center/cover no-repeat}
.syx .sp-yt::after{content:"";position:absolute;inset:0;background:rgba(4,10,24,.35)}
.syx .sp-yt span{position:absolute;z-index:2;inset:0;margin:auto;width:78px;height:78px;border-radius:50%;border:2px solid #fff;background:rgba(10,18,36,.55);display:grid;place-items:center}
.syx .sp-yt svg{width:28px;height:28px;fill:#fff;transform:translateX(2px)}
.syx .sp-yt iframe{position:absolute;inset:0;width:100%;height:100%;border:0}
.syx .sp-price{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:16px;margin-top:22px;padding:22px 26px;border-radius:18px;background:linear-gradient(160deg,#13264a,#0a1630);border:1px solid rgba(235,198,108,.45);color:#fff}
.syx .sp-price b{font-size:clamp(28px,3vw,38px);font-weight:900;color:var(--gold2)}
.syx .sp-price small{display:block;font-size:14px;color:rgba(255,255,255,.75)}
.syx .sp-note{margin-top:14px;font-size:15px;line-height:1.6;color:#4a5263}
.syx .sp-table{width:100%;margin-top:22px;border-collapse:separate;border-spacing:0;border:1px solid rgba(197,150,93,.45);border-radius:18px;overflow:hidden;background:rgba(255,255,255,.8);font-size:15.5px}
.syx .sp-table th,.syx .sp-table td{padding:14px 16px;text-align:right;vertical-align:top;border-bottom:1px solid rgba(197,150,93,.25);line-height:1.55;color:#1d2433}
.syx .sp-table thead th{background:#0e1c38;color:#fff;font-weight:800}
.syx .sp-table tbody th{font-weight:800;color:#0e1c38}
.syx .sp-table tr:last-child>*{border-bottom:0}
.syx .sp-table a{color:#9a6f37;font-weight:700;text-decoration:underline;text-underline-offset:3px}
.syx .sp-faq{margin-top:20px;display:grid;gap:12px}
.syx .sp-faq details{border-radius:14px;border:1px solid rgba(197,150,93,.45);background:rgba(255,255,255,.8)}
.syx .sp-faq summary{list-style:none;cursor:pointer;display:flex;align-items:center;justify-content:space-between;gap:16px;padding:18px 22px;font-size:17px;font-weight:800;color:#0e1c38}
.syx .sp-faq summary::-webkit-details-marker{display:none}
.syx .sp-faq summary::after{content:"+";flex:none;font-size:26px;font-weight:400;line-height:1;color:#C5965D;transition:transform .3s var(--out)}
.syx .sp-faq details[open] summary::after{transform:rotate(45deg)}
.syx .sp-faq .a{padding:0 22px 18px;font-size:16px;line-height:1.7;color:#2b3445}
.syx .sp-faq .a a,.syx .sp-prose a,.syx .sp-card a.tl,.syx .sp-note a{color:#9a6f37;font-weight:700;text-decoration:underline;text-underline-offset:3px}
.syx .sp-rel{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-top:22px}
.syx .sp-rel a{display:flex;flex-direction:column;gap:6px;padding:20px 22px;border-radius:16px;border:1px solid rgba(197,150,93,.45);background:rgba(255,255,255,.8);transition:transform .3s var(--out),box-shadow .3s var(--out)}
.syx .sp-rel b{font-size:18px;font-weight:800;color:#0e1c38}
.syx .sp-rel span{font-size:15px;line-height:1.55;color:#2b3445}
.syx .sp-rel i{font-style:normal;margin-top:4px;font-size:15px;font-weight:700;color:#9a6f37}
.syx .sp-band{margin-top:clamp(44px,6vw,72px);display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:20px;padding:clamp(24px,3vw,36px);border-radius:24px;background:radial-gradient(70% 120% at 0% 100%,rgba(63,100,170,.3),transparent 60%),linear-gradient(160deg,#13264a,#0a1630);border:1px solid rgba(235,198,108,.4);color:#fff}
.syx .sp-band h2{margin:0;font-size:clamp(24px,2.6vw,32px);font-weight:900;color:#fff}
.syx .sp-band p{margin:8px 0 0;font-size:16px;color:rgba(255,255,255,.85)}
.syx .sp-band .sp-ctas{margin-top:0}
.syx .sp-main .btn-ghost{background:transparent;color:#0e1c38;border-color:#C5965D}
.syx .sp-band .btn-ghost{color:var(--gold2);border-color:var(--gold2)}
.syx .sp-dl{margin-top:16px;display:grid;grid-template-columns:max-content 1fr;gap:10px 22px;font-size:16px;line-height:1.6}
.syx .sp-dl dt{font-weight:800;color:#0e1c38}.syx .sp-dl dd{margin:0;color:#1d2433}
@media (hover:hover) and (pointer:fine){.syx .sp-rel a:hover{transform:translateY(-4px);box-shadow:0 16px 36px rgba(14,28,56,.1)}}
@media (max-width:900px){
 .syx .sp-grid.c3,.syx .sp-rel{grid-template-columns:1fr 1fr}
}
@media (max-width:700px){
 .syx .sp-hero{background-position:22% top;background-size:auto 300px;padding:250px 0 40px;min-height:0}
 .syx .sp-hero::before{background:linear-gradient(180deg,rgba(4,10,24,.1) 0,rgba(4,10,24,.55) 170px,#040a18 290px)}
 .syx .sp-tabs a{padding:14px 14px 12px;font-size:15px}
 .syx .sp-grid.c2,.syx .sp-grid.c3,.syx .sp-rel{grid-template-columns:1fr}
 .syx .sp-table thead{display:none}
 .syx .sp-table,.syx .sp-table tbody,.syx .sp-table tr,.syx .sp-table th,.syx .sp-table td{display:block;width:100%}
 .syx .sp-table tr{border-bottom:1px solid rgba(197,150,93,.45)}
 .syx .sp-table td[data-l]::before{content:attr(data-l);display:block;font-size:13px;font-weight:700;color:#9a6f37}
 .syx .sp-dl{grid-template-columns:1fr;gap:2px}
 .syx .sp-dl dd{margin-bottom:10px}
 .syx .sp-band .btn,.syx .sp-hero .btn{width:100%;justify-content:center}
}
</style>
<script>
(function(){
  /* YouTube facade: load the player only when asked */
  document.addEventListener('click',function(e){
    var b=e.target.closest&&e.target.closest('.sp-yt[data-yt]'); if(!b||b.querySelector('iframe')) return;
    var f=document.createElement('iframe');
    f.src='https://www.youtube-nocookie.com/embed/'+b.getAttribute('data-yt')+'?autoplay=1&rel=0';
    f.allow='autoplay; encrypted-media; picture-in-picture; fullscreen'; f.allowFullscreen=true; f.title=b.getAttribute('aria-label')||'סרטון';
    b.appendChild(f);
  });
  /* the site-wide accessibility panel links to the general statement; on Shay pages point it to Shay's */
  (function fixA11y(n){ var l=document.querySelector('#nv-a11y-foot a[href="/accessibility-declaration/"]');
    if(l){ l.setAttribute('href','/shaymentor/accessibility-declaration/'); } else if(n<40){ setTimeout(function(){fixA11y(n+1)},250); } })(0);
  /* in-page tabs: mark the section in view */
  document.addEventListener('DOMContentLoaded',function(){
    var links=[].slice.call(document.querySelectorAll('.sp-tabs a[href^="#"]')); if(!links.length||!('IntersectionObserver' in window)) return;
    var io=new IntersectionObserver(function(es){es.forEach(function(en){ if(en.isIntersecting){ links.forEach(function(l){ var on=l.getAttribute('href')==='#'+en.target.id; l.classList.toggle('is-on',on); if(on) l.setAttribute('aria-current','true'); else l.removeAttribute('aria-current'); }); } });},{rootMargin:'-30% 0px -60% 0px'});
    links.forEach(function(l){var t=document.getElementById(l.getAttribute('href').slice(1)); if(t) io.observe(t);});
  });
})();
</script>
""".replace("__IMG__", HERO_IMG)

e = html.escape


def btn(text, href, kind="gold"):
    return f'<a class="btn btn-{kind}" href="{href}" data-magnet>{text} {ARROW}</a>'


def render_block(b):
    t = b["type"]
    if t == "prose":
        return '<div class="sp-prose">' + "".join(f"<p>{p}</p>" for p in b["paras"]) + "</div>"
    if t == "cards":
        cols = b.get("cols", 3)
        out = []
        for i, c in enumerate(b["items"], 1):
            num = f'<span class="n">{i:02d}</span>' if b.get("numbered") else ""
            body = f'<p>{c["text"]}</p>' if c.get("text") else ""
            if c.get("list"):
                body += "<ul>" + "".join(f"<li>{x}</li>" for x in c["list"]) + "</ul>"
            if c.get("link"):
                body += f'<p><a class="tl" href="{c["link"][1]}">{c["link"][0]}</a></p>'
            cls = "sp-card dark" if c.get("dark") else "sp-card"
            out.append(f'<div class="{cls}">{num}<h3>{c["title"]}</h3>{body}</div>')
        return f'<div class="sp-grid c{cols}">' + "".join(out) + "</div>"
    if t == "fit":
        return ('<div class="sp-grid c2">'
                f'<div class="sp-card"><h3>{b.get("yes_title","מתאים למי ש…")}</h3><ul class="sp-list">' + "".join(f"<li>{x}</li>" for x in b["yes"]) + "</ul></div>"
                f'<div class="sp-card"><h3>{b.get("no_title","פחות מתאים למי ש…")}</h3><ul class="sp-list no">' + "".join(f"<li>{x}</li>" for x in b["no"]) + "</ul></div></div>")
    if t == "list":
        return '<ul class="sp-list">' + "".join(f"<li>{x}</li>" for x in b["items"]) + "</ul>"
    if t == "image":
        return f'<figure class="sp-media"><img src="{b["src"]}" alt="{e(b["alt"])}" loading="lazy" width="{b.get("w",1600)}" height="{b.get("h",900)}"/>' + (f'<figcaption>{b["caption"]}</figcaption>' if b.get("caption") else "") + "</figure>"
    if t == "youtube":
        return (f'<div class="sp-media"><button type="button" class="sp-yt" data-yt="{b["id"]}" aria-label="{e(b["label"])}" '
                f'style="background-image:url(https://img.youtube.com/vi/{b["id"]}/hqdefault.jpg)"><span><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5.5v13l11-6.5z"/></svg></span></button></div>')
    if t == "video":
        return (f'<figure class="sp-media"><video controls preload="none" playsinline poster="{b["poster"]}" aria-label="{e(b["label"])}">'
                f'<source src="{b["src"]}" type="video/mp4"/></video>' + (f'<figcaption>{b["caption"]}</figcaption>' if b.get("caption") else "") + "</figure>")
    if t == "price":
        return (f'<div class="sp-price"><div><small>{b["label"]}</small><b>{b["value"]}</b></div>' + (btn(*b["cta"]) if b.get("cta") else "") + "</div>"
                + (f'<p class="sp-note">{b["note"]}</p>' if b.get("note") else ""))
    if t == "table":
        head = "".join(f'<th scope="col">{h}</th>' for h in b["head"])
        rows = ""
        for r in b["rows"]:
            rows += "<tr>" + f'<th scope="row">{r[0]}</th>' + "".join(f'<td data-l="{e(b["head"][i+1])}">{c}</td>' for i, c in enumerate(r[1:])) + "</tr>"
        return f'<table class="sp-table"><caption class="elementor-screen-only">{b["caption"]}</caption><thead><tr>{head}</tr></thead><tbody>{rows}</tbody></table>'
    if t == "faq":
        return '<div class="sp-faq">' + "".join(f'<details><summary>{q}</summary><div class="a">{a}</div></details>' for q, a in b["items"]) + "</div>"
    if t == "dl":
        return '<dl class="sp-dl">' + "".join(f"<dt>{k}</dt><dd>{v}</dd>" for k, v in b["items"]) + "</dl>"
    if t == "note":
        return f'<p class="sp-note">{b["text"]}</p>'
    if t == "related":
        return '<div class="sp-rel">' + "".join(f'<a href="{h}"><b>{ti}</b><span>{d}</span><i>{cta} ←</i></a>' for ti, d, h, cta in b["items"]) + "</div>"
    raise ValueError(t)


def render(page):
    crumbs = '<li><a href="/shaymentor/">עמוד הבית</a></li>'
    for name, href in page.get("parents", []):
        crumbs += f'<li><a href="{href}">{name}</a></li>'
    crumbs += f'<li><span aria-current="page">{page["crumb"]}</span></li>'
    ctas = "".join(btn(*c) if len(c) == 2 else btn(c[0], c[1], c[2]) for c in page.get("ctas", []))
    hero = (f'<span class="sp-skip-target" id="nv-main-content" tabindex="-1"></span>'
            f'<section class="sp-hero" aria-labelledby="sp-h1"><div class="wrap"><div class="sp-hero-in">'
            f'<nav class="sp-crumbs" aria-label="פירורי לחם"><ol>{crumbs}</ol></nav>'
            f'<span class="sp-tag">שי יפרח | מנטור מנטלי ועסקי</span>'
            f'<h1 id="sp-h1">{page["h1"]}</h1><p class="sp-lead">{page["lead"]}</p>'
            + (f'<div class="sp-ctas">{ctas}</div>' if ctas else "") + "</div></div></section>")
    tabs = [s for s in page["sections"] if s.get("tab")]
    tabs_html = ""
    if len(tabs) > 1:
        tabs_html = ('<nav class="sp-tabs" aria-label="ניווט בעמוד"><ul>' +
                     "".join(f'<li><a href="#{s["id"]}"' + (' class="is-on"' if i == 0 else "") + f'>{s["tab"]}</a></li>' for i, s in enumerate(tabs)) + "</ul></nav>")
    secs = ""
    for s in page["sections"]:
        head = (f'<span class="sp-kick">{s["kick"]}</span>' if s.get("kick") else "") + f'<h2 class="sp-h2" id="{s["id"]}-t">{s["title"]}</h2>' + (f'<p class="sp-sub">{s["sub"]}</p>' if s.get("sub") else "")
        secs += f'<section class="sp-sec" id="{s["id"]}" aria-labelledby="{s["id"]}-t">{head}' + "".join(render_block(b) for b in s["blocks"]) + "</section>"
    band = ""
    if page.get("band"):
        bd = page["band"]
        band = (f'<div class="sp-band"><div><h2>{bd["title"]}</h2><p>{bd["text"]}</p></div><div class="sp-ctas">'
                + "".join(btn(*c) if len(c) == 2 else btn(c[0], c[1], c[2]) for c in bd["ctas"]) + "</div></div>")
    return CSS + hero + tabs_html + f'<div class="sp-main"><div class="wrap">{secs}{band}</div></div>'
