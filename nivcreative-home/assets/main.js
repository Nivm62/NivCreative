(function(){
  var nav=document.getElementById('nav'),burger=document.getElementById('burger'),menu=document.getElementById('menu');

  function onScroll(){nav.classList.toggle('scrolled',window.scrollY>20)}
  window.addEventListener('scroll',onScroll,{passive:true});onScroll();

  burger.addEventListener('click',function(){
    var o=menu.classList.toggle('open');burger.setAttribute('aria-expanded',o);
  });
  menu.addEventListener('click',function(e){
    if(e.target.tagName==='A'){menu.classList.remove('open');burger.setAttribute('aria-expanded',false)}
  });

  // reveal on scroll
  var io=new IntersectionObserver(function(es){
    es.forEach(function(e){
      if(!e.isIntersecting)return;
      e.target.classList.add('in');io.unobserve(e.target);
      e.target.querySelectorAll&&e.target.classList.contains('stat')&&count(e.target.querySelector('[data-count]'));
    });
  },{threshold:.15});
  document.querySelectorAll('.reveal,.hero-art').forEach(function(el){io.observe(el)});

  // counters
  function count(el){
    if(!el)return;
    var to=+el.dataset.count,suf=el.dataset.suffix||'',t0=null,dur=1600;
    function step(t){
      if(!t0)t0=t;var p=Math.min((t-t0)/dur,1),v=Math.round(to*(1-Math.pow(1-p,3)));
      el.textContent=v+suf;if(p<1)requestAnimationFrame(step);
    }
    el.textContent='0'+suf;requestAnimationFrame(step);
  }

  // active nav link
  var links=[].slice.call(menu.querySelectorAll('a[href^="#"]:not(.btn)'));
  var secs=links.map(function(a){return document.querySelector(a.getAttribute('href'))});
  var so=new IntersectionObserver(function(es){
    es.forEach(function(e){
      if(e.isIntersecting){
        links.forEach(function(a){a.classList.toggle('active',a.getAttribute('href')==='#'+e.target.id)});
      }
    });
  },{rootMargin:'-45% 0px -50% 0px'});
  secs.forEach(function(s){s&&so.observe(s)});

  // hero parallax on mouse
  var art=document.getElementById('heroArt');
  if(art&&window.matchMedia('(hover:hover)').matches){
    var items=art.querySelectorAll('[data-depth]');
    art.addEventListener('mousemove',function(e){
      var r=art.getBoundingClientRect(),x=(e.clientX-r.left)/r.width-.5,y=(e.clientY-r.top)/r.height-.5;
      items.forEach(function(el){var d=+el.dataset.depth;el.style.marginLeft=(x*d)+'px';el.style.marginTop=(y*d)+'px'});
      art.style.transform='rotateY('+(x*6)+'deg) rotateX('+(-y*6)+'deg)';
    });
    art.addEventListener('mouseleave',function(){
      art.style.transform='';items.forEach(function(el){el.style.marginLeft=el.style.marginTop=''});
    });
  }
})();
