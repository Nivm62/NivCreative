<script>
(function(){
 'use strict';
 var d=document,root=d.querySelector('.ms-slider');if(!root)return;
 var reduce=window.matchMedia('(prefers-reduced-motion: reduce)').matches;
 var track=root.querySelector('.ms-track'),cards=[].slice.call(root.querySelectorAll('.ms-vcard')),
     prev=root.querySelector('.ms-prev'),next=root.querySelector('.ms-next'),bar=root.querySelector('.ms-prog i'),
     dots=[].slice.call(root.querySelectorAll('.ms-dots button')),live=root.querySelector('.ms-live'),active=0,raf=0;
 function mid(c){var r=c.getBoundingClientRect(),t=track.getBoundingClientRect();return Math.abs((r.left+r.width/2)-(t.left+t.width/2))}
 function setActive(i){active=i;cards.forEach(function(c,k){c.classList.toggle('is-active',k===i);c.setAttribute('aria-current',k===i?'true':'false')});
  dots.forEach(function(b,k){b.setAttribute('aria-selected',k===i?'true':'false');b.tabIndex=k===i?0:-1});
  if(bar)bar.style.transform='scaleX('+((i+1)/cards.length)+')';
  prev.disabled=i===0;next.disabled=i===cards.length-1;
  if(live)live.textContent='עדות '+(i+1)+' מתוך '+cards.length}
 function onScroll(){if(raf)return;raf=requestAnimationFrame(function(){raf=0;var b=0,m=1e9;cards.forEach(function(c,k){var x=mid(c);if(x<m){m=x;b=k}});if(b!==active)setActive(b)})}
 /* move only the track (never the page): scrollIntoView would also scroll the document vertically */
 function go(i,instant){i=Math.max(0,Math.min(cards.length-1,i));var c=cards[i].getBoundingClientRect(),t=track.getBoundingClientRect();track.scrollBy({left:(c.left+c.width/2)-(t.left+t.width/2),top:0,behavior:(reduce||instant)?'auto':'smooth'})}
 track.addEventListener('scroll',onScroll,{passive:true});
 /* the visual order is right-to-left, so "next" is the card on the left */
 prev.addEventListener('click',function(){go(active-1)});next.addEventListener('click',function(){go(active+1)});
 dots.forEach(function(b,k){b.addEventListener('click',function(){go(k)})});
 track.addEventListener('keydown',function(e){var k=e.key;if(k==='ArrowLeft'){e.preventDefault();go(active+1)}else if(k==='ArrowRight'){e.preventDefault();go(active-1)}});
 /* play: one video at a time, load only on click */
 function stopAll(except){cards.forEach(function(c){var v=c.querySelector('video');if(v&&c!==except){v.pause();c.classList.remove('is-playing')}})}
 cards.forEach(function(c,k){var btn=c.querySelector('.ms-play');
  btn.addEventListener('click',function(){
   if(k!==active){go(k)}
   var v=c.querySelector('video');if(!v){v=d.createElement('video');v.src=c.dataset.src;v.poster=c.dataset.poster;v.controls=true;v.playsInline=true;v.setAttribute('playsinline','');v.preload='auto';v.setAttribute('aria-label',c.dataset.label);c.querySelector('.ms-frame').appendChild(v);
    v.addEventListener('ended',function(){c.classList.remove('is-playing')});v.addEventListener('pause',function(){if(!v.seeking&&!v.ended)c.classList.remove('is-playing')});v.addEventListener('play',function(){stopAll(c);c.classList.add('is-playing')})}
   stopAll(c);c.classList.add('is-playing');var p=v.play();if(p&&p.catch)p.catch(function(){})});
  c.addEventListener('click',function(e){if(k!==active&&!e.target.closest('video')){e.preventDefault();go(k)}})});
 /* pause when the section leaves the viewport */
 if('IntersectionObserver' in window){new IntersectionObserver(function(es){if(!es[0].isIntersecting)stopAll(null)},{threshold:0}).observe(root)}
 /* drag to scroll with a mouse */
 var down=false,sx=0,sl=0,moved=false;
 track.addEventListener('pointerdown',function(e){if(e.pointerType!=='mouse'||e.target.closest('video,button'))return;down=true;moved=false;sx=e.clientX;sl=track.scrollLeft;track.classList.add('drag')});
 window.addEventListener('pointermove',function(e){if(!down)return;var dx=e.clientX-sx;if(Math.abs(dx)>4)moved=true;track.scrollLeft=sl-dx});
 window.addEventListener('pointerup',function(){if(!down)return;down=false;track.classList.remove('drag');if(moved){var b=0,m=1e9;cards.forEach(function(c,k){var x=mid(c);if(x<m){m=x;b=k}});go(b)}});
 setActive(0);
 /* start with the first card centred, without moving the page */
 go(0,true);
})();
</script>
