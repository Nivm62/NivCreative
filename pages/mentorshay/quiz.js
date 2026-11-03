<script>
(function(){
 'use strict';
 var root=document.getElementById('qz');if(!root)return;
 var reduce=window.matchMedia('(prefers-reduced-motion: reduce)').matches;
 var BASE='/mentorshay/';
 var WA='https://wa.me/972539457696';
 var TRACKS={
  workshop:{name:'סדנת התפתחות אישית',url:BASE+'workshop/',cta:'לעמוד הסדנה',why:'מסגרת קבוצתית לבניית בסיס אישי חזק: ביטחון, משמעת עצמית וייעוד, עם סביבה תומכת ומפגשים.'},
  business:{name:'ליווי עסקי ומנטלי',url:BASE+'business/',cta:'לעמוד הליווי',why:'ליווי שמחבר עבודה על בעל העסק עם בידול, תכנון, שיווק ומכירות, בסדר הנכון ובקצב שמתאים לעסק.'},
  course:{name:'הקורס הדיגיטלי MINDSET ELITE',url:BASE+'course/',cta:'לעמוד הקורס',why:'21 פרקים בווידאו ללמידה עצמאית, בזמן ובקצב שלכם, על ביטחון, משמעת, שגרה וסביבה מנצחת.'},
  session:{name:'פגישת 1:1 עם שי',url:BASE+'personal-session/',cta:'לעמוד הפגישה',why:'פגישה אחת ממוקדת סביב אתגר, החלטה או כיוון, בלי להיכנס לתהליך ארוך.'},
  free:{name:'הדרכה עסקית ללא עלות',url:BASE+'vsl/',cta:'לצפייה בהדרכה',why:'נקודת פתיחה בלי התחייבות: הדרכה בווידאו על הסדר הנכון, קודם בונים את האדם ואז את העסק.'}
 };
 var QUIZZES=[
  {id:'general',title:'איזה מסלול מתאים לי?',sub:'שישה שאלות קצרות, לכל מי שרוצה להתקדם בחיים ובעסק.',time:'כדקה וחצי',
   q:[
    {t:'מה הכי חשוב לך לקדם עכשיו?',a:[['בסיס אישי: ביטחון, משמעת ושגרה',{workshop:3,course:2}],['העסק שלי: בידול, שיווק ומכירות',{business:3}],['החלטה או אתגר אחד ספציפי',{session:3}],['עדיין לא יודע/ת, רוצה להכיר קודם',{free:3,course:1}]]},
    {t:'איך נוח לך ללמוד ולהתקדם?',a:[['בקבוצה, במפגשים וביחד עם אחרים',{workshop:3}],['בליווי אישי',{business:2,session:2}],['לבד, בקצב ובזמן שלי',{course:3}],['קודם ללמוד קצת בחינם',{free:3,course:1}]]},
    {t:'מה המצב שלך היום?',a:[['אין לי עסק, או שאני בתחילת הדרך',{workshop:1,course:1,free:1}],['יש לי עסק פעיל ואני רוצה לצמוח',{business:3}],['יש לי עסק, אבל אני מרגיש/ה תקוע/ה',{business:2,session:2}],['זה לא קשור לעסק, אלא לחיים האישיים',{workshop:2,course:2}]]},
    {t:'כמה מחויבות אתה/את מוכנ/ה לתת?',a:[['מפגש אחד ממוקד',{session:3,free:1}],['תהליך מתמשך',{business:3,workshop:1}],['גמיש, לפי מה שנוח לי',{course:3}],['רוצה קודם להתרשם',{free:3}]]},
    {t:'מה הכי מעכב אותך?',a:[['חוסר ביטחון ומחשבות שעוצרות אותי',{workshop:2,course:2}],['חוסר סדר ובהירות בעסק',{business:3}],['דחיינות וקושי להתמיד',{workshop:2,course:1}],['החלטה שאני לא מצליח/ה לקבל',{session:3}]]},
    {t:'מה הצעד שנוח לך לעשות עכשיו?',a:[['לצפות בתוכן',{free:3,course:1}],['לשוחח עם מישהו ולבדוק התאמה',{business:2,session:1}],['להשאיר פרטים ולקבל עדכון',{workshop:1,free:1}],['להתחיל ללמוד מיד',{course:3}]]}
   ]},
  {id:'business',title:'שאלון לבעלות ובעלי עסקים, מטפלות ומאמנים',sub:'חמש שאלות על שלב העסק, כדי להבין מאיפה כדאי להתחיל.',time:'כדקה',
   q:[
    {t:'באיזה שלב העסק שלך?',a:[['עוד לא התחלתי, אני רק חושב/ת על זה',{free:2,course:2,workshop:1}],['בתחילת הדרך, יש מעט לקוחות',{business:1,course:1,free:1,session:1}],['פעיל ורוצה לצמוח',{business:3}],['פעיל, אבל מרגיש/ה תקוע/ה',{session:2,business:2}]]},
    {t:'איפה הכי קשה לך כרגע?',a:[['להסביר מה מייחד אותי ולמי אני מתאים/ה',{business:2,session:1}],['תמחור, חבילות ושירותים',{business:2,session:1}],['שיווק, תוכן ורשתות',{business:2,free:2}],['שיחות מכירה וסגירה',{session:2,business:1}],['סדר, ארגון וכספים',{business:1,course:1,session:1}]]},
    {t:'מה בעיקר עוצר אותך מבפנים?',a:[['פחד מחשיפה ומ"מה יגידו"',{workshop:2,course:1,business:1}],['תחושה שאני לא מספיק טוב/ה',{workshop:2,course:2}],['קושי להתמיד ולבצע',{workshop:2,course:1}],['שום דבר מיוחד, חסר לי רק כיוון',{session:2,business:1}]]},
    {t:'איזה סוג תמיכה מתאים לך?',a:[['ליווי אישי לאורך זמן',{business:3}],['שיחה ממוקדת אחת עם שי',{session:3}],['ללמוד לבד לפי תוכן מוכן',{course:3}],['קודם לראות תוכן בחינם',{free:3}]]},
    {t:'מה הכי חשוב לך להשיג בחודשים הקרובים?',a:[['בהירות: לדעת מה הצעד הבא',{session:2,free:1,business:1}],['תוכנית עבודה לעסק',{business:2,session:1}],['ביטחון ושגרה שיחזיקו אותי',{workshop:2,course:2}],['להתרשם ולהבין אם זה בשבילי',{free:3}]]}
   ]}
 ];
 var hub=root.querySelector('.qz-hub'),stage=root.querySelector('.qz-stage'),live=root.querySelector('.qz-live');
 var quiz=null,idx=0,score=null,answers=[];
 function el(t,c,h){var e=document.createElement(t);if(c)e.className=c;if(h!=null)e.innerHTML=h;return e}
 function say(m){live.textContent='';setTimeout(function(){live.textContent=m},30)}
 function show(node){stage.innerHTML='';stage.appendChild(node);if(!reduce){node.classList.add('qz-in')}}
 function focusTop(){var h=stage.querySelector('h2,h3,legend');if(h){h.setAttribute('tabindex','-1');h.focus({preventScroll:true})}
  var r=stage.getBoundingClientRect();if(r.top<0||r.top>window.innerHeight*.5){window.scrollTo({top:window.scrollY+r.top-110,behavior:reduce?'auto':'smooth'})}}
 function start(id){quiz=QUIZZES.filter(function(q){return q.id===id})[0];idx=0;answers=[];score={workshop:0,business:0,course:0,session:0,free:0};hub.hidden=true;stage.hidden=false;ask();}
 function ask(){
  var q=quiz.q[idx],n=quiz.q.length;
  var box=el('div','qz-card');
  var top=el('div','qz-top','<span class="qz-count">שאלה '+(idx+1)+' מתוך '+n+'</span><span class="qz-bar" aria-hidden="true"><i style="transform:scaleX('+((idx)/n)+')"></i></span>');
  var fs=el('fieldset','qz-fs');var lg=el('legend','qz-q',q.t);fs.appendChild(lg);
  var list=el('div','qz-opts');
  q.a.forEach(function(a,k){
   var id='qz-'+quiz.id+'-'+idx+'-'+k;
   var w=el('div','qz-opt');
   var inp=el('input');inp.type='radio';inp.name='qz-'+quiz.id+'-'+idx;inp.id=id;inp.value=k;
   if(answers[idx]===k)inp.checked=true;
   var lb=el('label',null,'<span class="qz-mark" aria-hidden="true"></span><span>'+a[0]+'</span>');lb.htmlFor=id;
   inp.addEventListener('change',function(){answers[idx]=k;nxt.disabled=false;box.querySelectorAll('.qz-opt').forEach(function(o){o.classList.remove('on')});w.classList.add('on')});
   w.appendChild(inp);w.appendChild(lb);list.appendChild(w);if(answers[idx]===k)w.classList.add('on');
  });
  fs.appendChild(list);
  var nav=el('div','qz-nav');
  var back=el('button','btn btn-ghost btn-sm','חזרה');back.type='button';
  var nxt=el('button','btn btn-gold btn-sm',idx===n-1?'לתוצאה':'לשאלה הבאה');nxt.type='button';nxt.disabled=answers[idx]==null;
  back.addEventListener('click',function(){if(idx===0){reset()}else{idx--;ask()}});
  nxt.addEventListener('click',function(){if(answers[idx]==null)return;if(idx<n-1){idx++;ask()}else{result()}});
  nav.appendChild(back);nav.appendChild(nxt);
  box.appendChild(top);box.appendChild(fs);box.appendChild(nav);show(box);focusTop();say('שאלה '+(idx+1)+' מתוך '+n);
 }
 function compute(){
  var s={workshop:0,business:0,course:0,session:0,free:0};
  answers.forEach(function(k,i){var w=quiz.q[i].a[k][1];Object.keys(w).forEach(function(t){s[t]+=w[t]})});
  var order=['business','session','workshop','course','free'];
  var arr=order.map(function(t){return [t,s[t]]}).sort(function(a,b){return b[1]-a[1]});
  return arr;
 }
 function result(){
  var arr=compute(),top=arr[0][0],sec=arr.filter(function(a,i){return i>0&&a[1]>0&&a[1]>=arr[0][1]*0.6}).slice(0,2).map(function(a){return a[0]});
  var T=TRACKS[top];
  var box=el('div','qz-card qz-res');
  var h='<span class="qz-kick">התוצאה שלכם</span><h2 class="qz-rt">נראה שהכי מתאים להתחיל ב<b>'+T.name+'</b></h2><p class="qz-rw">'+T.why+'</p>';
  h+='<div class="qz-cta"><a class="btn btn-gold" href="'+T.url+'">'+T.cta+' <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 12H5M11 18l-6-6 6-6"/></svg></a></div>';
  if(sec.length){h+='<div class="qz-sec"><h3>אפשר גם להכיר</h3><ul>'+sec.map(function(t){return '<li><a href="'+TRACKS[t].url+'"><b>'+TRACKS[t].name+'</b><span>'+TRACKS[t].why+'</span></a></li>'}).join('')+'</ul></div>'}
  h+='<p class="qz-note">השאלון נועד להכוונה ראשונית בלבד, ואינו תחליף לשיחה אישית או להתחייבות לתוצאה. תכנים, מחירים ומועדים נמסרים בעמודי המסלולים או בשיחה.</p>';
  box.innerHTML=h;
  var act=el('div','qz-act');
  var text='שלום, מילאתי את שאלון ההתאמה באתר. יצא לי: '+T.name+'. אשמח לשמוע עוד.';
  var wa=el('a','btn btn-ink','לשיחה בוואטסאפ');wa.href=WA+'?text='+encodeURIComponent(text);wa.target='_blank';wa.rel='noopener';
  var lead=el('a','btn btn-ghost','להשארת פרטים');lead.href='#contact';
  var again=el('button','btn-link','למלא שוב');again.type='button';again.addEventListener('click',reset);
  act.appendChild(wa);act.appendChild(lead);act.appendChild(again);box.appendChild(act);
  show(box);focusTop();say('התוצאה: '+T.name);
  var f=document.querySelector('input[name="form_fields[quiz_result]"]');
  if(f){f.value=quiz.title+' → '+T.name+(sec.length?' (גם: '+sec.map(function(t){return TRACKS[t].name}).join(', ')+')':'')}
  var sel=document.querySelector('select[name="form_fields[track]"]');
  var map={workshop:'התפתחות אישית (סדנה)',business:'ליווי עסקי ומנטלי',course:'הקורס הדיגיטלי',session:'פגישת 1:1',free:'תוכן ללא עלות'};
  if(sel){for(var i=0;i<sel.options.length;i++){if(sel.options[i].text.indexOf(map[top])===0||sel.options[i].value.indexOf(map[top])===0){sel.selectedIndex=i;break}}}
 }
 function reset(){stage.hidden=true;stage.innerHTML='';hub.hidden=false;quiz=null;var h=hub.querySelector('h2');if(h){h.setAttribute('tabindex','-1');h.focus({preventScroll:true})}}
 hub.querySelectorAll('[data-quiz]').forEach(function(b){b.addEventListener('click',function(){start(b.getAttribute('data-quiz'))})});
})();
</script>
