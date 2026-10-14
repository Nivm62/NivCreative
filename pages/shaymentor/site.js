<script>
(function(){
 'use strict';
 var d=document,w=window,roots=d.querySelectorAll('.syx');
 var reduce=w.matchMedia('(prefers-reduced-motion: reduce)').matches;
 var fine=w.matchMedia('(hover:hover) and (pointer:fine)').matches;
 var $=function(s,c){return (c||d).querySelector(s)},$$=function(s,c){return Array.prototype.slice.call((c||d).querySelectorAll(s))};
 function ready(){requestAnimationFrame(function(){requestAnimationFrame(function(){roots.forEach(function(r){r.classList.add('is-ready')})})})}
 if(d.fonts&&d.fonts.ready){Promise.race([d.fonts.ready,new Promise(function(r){setTimeout(r,900)})]).then(ready)}else{ready()}

 /* reveal on scroll */
 var rv=$$('.rv');
 if('IntersectionObserver' in w){
  var io=new IntersectionObserver(function(es){es.forEach(function(e){if(e.isIntersecting){e.target.classList.add('in');io.unobserve(e.target)}})},{threshold:.15,rootMargin:'0px 0px -6% 0px'});
  rv.forEach(function(el){io.observe(el)});
 }else{rv.forEach(function(el){el.classList.add('in')})}

 /* video facade: load the YouTube iframe only on click */
 $$('.play[data-yt]').forEach(function(b){b.addEventListener('click',function(){
  var box=b.closest('.vbox,.vcard');if(!box)return;
  var f=d.createElement('iframe');f.src='https://www.youtube-nocookie.com/embed/'+b.dataset.yt+'?autoplay=1&rel=0';
  f.allow='autoplay; encrypted-media; picture-in-picture; fullscreen';f.allowFullscreen=true;f.title='סרטון של שי יפרח';
  box.appendChild(f);
 })});

 /* menu: hamburger + submenus */
 var head=$('#syxHead'),mbtn=$('.menu-btn'),mnav=$('#syxNav');
 function closeSubs(except){$$('.has-sub',mnav).forEach(function(li){if(li!==except){li.classList.remove('open');var t=$('.subtoggle',li);if(t)t.setAttribute('aria-expanded','false')}})}
 if(mbtn&&mnav){
  mbtn.addEventListener('click',function(){var o=head.classList.toggle('open');mbtn.setAttribute('aria-expanded',o);mbtn.setAttribute('aria-label',o?'סגירת תפריט':'פתיחת תפריט')});
  $$('.subtoggle',mnav).forEach(function(t){t.addEventListener('click',function(){var li=t.closest('.has-sub'),o=!li.classList.contains('open');closeSubs(li);li.classList.toggle('open',o);t.setAttribute('aria-expanded',o)})});
  d.addEventListener('keydown',function(e){if(e.key==='Escape'){closeSubs();if(head.classList.contains('open')){head.classList.remove('open');mbtn.setAttribute('aria-expanded','false');mbtn.focus()}}});
  d.addEventListener('click',function(e){if(!head.contains(e.target)){closeSubs();head.classList.remove('open');mbtn.setAttribute('aria-expanded','false')}});
 }
 /* scroll-linked: progress, header, parallax, sticky cta */
 var bar=$('.syx-progress i'),hero=$('#syxHero'),heroBg=hero&&$('.bg',hero),
     onePic=$('.syx-one .pic .im'),sticky=$('#syxSticky'),contact=$('#contact'),lastY=0,tick=false;
 function onScroll(){
  var y=w.scrollY,h=d.documentElement.scrollHeight-w.innerHeight;
  if(bar)bar.style.transform='scaleX('+(h>0?y/h:0)+')';
  if(head){head.classList.toggle('is-solid',y>40);head.classList.toggle('is-hidden',y>lastY&&y>320&&!head.contains(d.activeElement)&&!head.classList.contains('open'))}
  lastY=y;
  if(!reduce){
   if(heroBg&&y<w.innerHeight*1.2&&!fine)heroBg.style.setProperty('--py',(y*.12)+'px');
   if(onePic){var r=onePic.parentNode.getBoundingClientRect();if(r.bottom>0&&r.top<w.innerHeight){onePic.style.setProperty('--ty',(((r.top+r.height/2-w.innerHeight/2)/w.innerHeight)*-9)+'%')}}
  }
  if(sticky){var cr=contact?contact.getBoundingClientRect():null;sticky.classList.toggle('is-on',y>w.innerHeight*.8&&!(cr&&cr.top<w.innerHeight*.75))}
  tick=false;
 }
 w.addEventListener('scroll',function(){if(!tick){tick=true;requestAnimationFrame(onScroll)}},{passive:true});
 w.addEventListener('resize',onScroll);onScroll();

 if(!reduce&&fine){
  /* hero: spotlight + parallax */
  if(hero){
   var tx=0,ty=0,cx=0,cy=0,sx=0,sy=0,raf=0;
   var loop=function(){
    cx+=(tx-cx)*.08;cy+=(ty-cy)*.08;
    hero.style.setProperty('--sx',sx+'px');hero.style.setProperty('--sy',sy+'px');
    if(heroBg){heroBg.style.setProperty('--px',(cx*-18)+'px');heroBg.style.setProperty('--py',(cy*-12)+'px')}
    raf=(Math.abs(tx-cx)>.001||Math.abs(ty-cy)>.001)?requestAnimationFrame(loop):0;
   };
   hero.addEventListener('pointermove',function(e){
    var r=hero.getBoundingClientRect();sx=e.clientX-r.left;sy=e.clientY-r.top;
    tx=sx/r.width-.5;ty=sy/r.height-.5;
    hero.classList.add('is-live');if(!raf)raf=requestAnimationFrame(loop);
   });
   hero.addEventListener('pointerleave',function(){hero.classList.remove('is-live');tx=ty=0;if(!raf)raf=requestAnimationFrame(loop)});
  }
  /* 3D tilt + glare on cards */
  $$('[data-tilt]').forEach(function(c){
   c.addEventListener('pointermove',function(e){
    var r=c.getBoundingClientRect(),x=(e.clientX-r.left)/r.width,y=(e.clientY-r.top)/r.height;
    c.style.setProperty('--ry',((x-.5)*-12).toFixed(2)+'deg');c.style.setProperty('--rx',((y-.5)*10).toFixed(2)+'deg');
    c.style.setProperty('--gx',(x*100)+'%');c.style.setProperty('--gy',(y*100)+'%');
   });
   c.addEventListener('pointerleave',function(){c.style.setProperty('--rx','0deg');c.style.setProperty('--ry','0deg')});
  });
  /* magnetic buttons */
  $$('[data-magnet]').forEach(function(b){
   b.addEventListener('pointermove',function(e){var r=b.getBoundingClientRect();
    b.style.setProperty('--bx',((e.clientX-r.left-r.width/2)*.22).toFixed(1)+'px');b.style.setProperty('--by',((e.clientY-r.top-r.height/2)*.3).toFixed(1)+'px')});
   b.addEventListener('pointerleave',function(){b.style.setProperty('--bx','0px');b.style.setProperty('--by','0px')});
  });
 }

 /* gold dust canvas */
 var cv=$('#syxDust');
 if(cv&&hero&&!reduce&&cv.getContext){
  var ctx=cv.getContext('2d'),W=0,H=0,dpr=Math.min(2,w.devicePixelRatio||1),P=[],run=false,N=w.innerWidth<700?24:50;
  var size=function(){var r=cv.getBoundingClientRect();W=r.width;H=r.height;cv.width=W*dpr;cv.height=H*dpr;ctx.setTransform(dpr,0,0,dpr,0,0)};
  var mk=function(init){return{x:Math.random()*W,y:init?Math.random()*H:H+10,r:Math.random()*1.8+.4,v:Math.random()*.35+.12,a:Math.random()*.6+.25,ph:Math.random()*6.28,s:Math.random()*.6+.4}};
  var frame=function(t){
   if(!run)return;ctx.clearRect(0,0,W,H);
   for(var i=0;i<P.length;i++){var p=P[i];p.y-=p.v;p.x+=Math.sin(t/1400*p.s+p.ph)*.25;
    if(p.y<-10){P[i]=mk(false);continue}
    var tw=.6+.4*Math.sin(t/500+p.ph);ctx.beginPath();ctx.fillStyle='rgba(255,215,130,'+(p.a*tw).toFixed(3)+')';ctx.shadowColor='rgba(235,198,108,.9)';ctx.shadowBlur=8;ctx.arc(p.x,p.y,p.r,0,6.283);ctx.fill()}
   requestAnimationFrame(frame);
  };
  var start=function(){if(run)return;run=true;requestAnimationFrame(frame)};
  size();for(var i=0;i<N;i++)P.push(mk(true));
  w.addEventListener('resize',size);
  if('IntersectionObserver' in w){new IntersectionObserver(function(es){if(es[0].isIntersecting)start();else run=false},{threshold:0}).observe(hero)}else start();
 }
})();
</script>