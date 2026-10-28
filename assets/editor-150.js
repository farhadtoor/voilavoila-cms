'use strict';
(() => {
  const form=document.querySelector('#content-editor');if(!form)return;
  const en=document.body.dataset.lang==='en',word=(fr,eng)=>en?eng:fr;
  const panels=[...form.querySelectorAll('.language-panel')];
  const mode=document.querySelector('#editor-bilingual');let dual=false;
  const currentLanguage=()=>form.querySelector('.language-tab.active')?.dataset.language||'fr';
  const applyMode=()=>{form.classList.toggle('bilingual-editor',dual);mode?.setAttribute('aria-pressed',String(dual));panels.forEach(p=>p.hidden=!dual&&p.dataset.language!==currentLanguage());};
  mode?.addEventListener('click',()=>{dual=!dual;applyMode();});
  form.querySelectorAll('.language-tab').forEach(b=>b.addEventListener('click',applyMode));
  const textOf=html=>new DOMParser().parseFromString(html,'text/html').body.textContent||'';
  const count=()=>{
    const complete=[];
    for(const language of ['fr','en']){
      const title=form.elements['title_'+language].value.trim(),body=form.elements['body_'+language].value;
      const words=textOf(body).trim().split(/\s+/u).filter(Boolean).length;
      const target=form.querySelector('[data-counter="'+language+'"]');
      if(target)target.textContent=words+' '+word('mots','words')+' · '+Math.max(1,Math.ceil(words/200))+' min '+word('de lecture estimée','estimated reading');
      complete.push(language.toUpperCase()+' : '+(title&&textOf(body).trim()?word('renseigné','filled'):word('à compléter','incomplete')));
    }
    const target=document.querySelector('#translation-status');if(target)target.textContent=complete.join(' · ');
  };
  form.addEventListener('input',count);count();
  form.querySelectorAll('[data-copy-language]').forEach(button=>button.addEventListener('click',()=>{
    const to=button.dataset.copyLanguage,from=to==='fr'?'en':'fr';
    if(['title','excerpt','body'].some(k=>form.elements[k+'_'+to].value.trim())&&!confirm(word('Remplacer les champs '+to.toUpperCase()+' par ceux de '+from.toUpperCase()+' ?','Replace '+to.toUpperCase()+' fields with '+from.toUpperCase()+'?')))return;
    for(const key of ['title','excerpt','body']){const input=form.elements[key+'_'+to];input.value=form.elements[key+'_'+from].value;input.dispatchEvent(new Event('input',{bubbles:true}));}
    count();
  }));
})();
