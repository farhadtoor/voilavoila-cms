'use strict';
(() => {
  const en=document.body.dataset.lang==='en';
  const words=(fr,eng)=>en?eng:fr;
  const toast=(message)=>{const el=document.querySelector('#toast');el.textContent=message;el.classList.add('show');setTimeout(()=>el.classList.remove('show'),3000);};
  const collapse=document.querySelector('.sidebar-collapse');
  const sidebarKey='vv-sidebar:'+location.pathname.replace(/[^/]*$/,'');
  const setCollapsed=(value,persist=false)=>{
    document.documentElement.classList.toggle('sidebar-collapsed',value);
    if(collapse){const label=words(value?'Déplier la barre latérale':'Replier la barre latérale',value?'Expand sidebar':'Collapse sidebar');collapse.setAttribute('aria-expanded',String(!value));collapse.setAttribute('aria-label',label);collapse.title=label;}
    if(persist)try{localStorage.setItem(sidebarKey,value?'1':'0');}catch{}
  };
  try{setCollapsed(localStorage.getItem(sidebarKey)==='1');}catch{setCollapsed(false);}
  collapse?.addEventListener('click',()=>setCollapsed(!document.documentElement.classList.contains('sidebar-collapsed'),true));
  const menu=document.querySelector('.menu-toggle');
  const rail=document.querySelector('#sidebar'),narrow=matchMedia('(max-width:760px)');
  const syncRail=()=>{if(rail)rail.inert=narrow.matches&&!rail.classList.contains('is-open');};
  narrow.addEventListener('change',()=>{rail?.classList.remove('is-open');menu?.setAttribute('aria-expanded','false');syncRail();});
  syncRail();
  menu?.addEventListener('click',()=>{const open=document.querySelector('#sidebar').classList.toggle('is-open');menu.setAttribute('aria-expanded',String(open));syncRail();});
  document.addEventListener('click',ev=>{if(!ev.target.closest('.sidebar,.menu-toggle')){document.querySelector('#sidebar')?.classList.remove('is-open');menu?.setAttribute('aria-expanded','false');syncRail();}});
  document.addEventListener('keydown',ev=>{if(ev.key==='Escape'&&document.querySelector('#sidebar')?.classList.contains('is-open')){document.querySelector('#sidebar').classList.remove('is-open');menu?.setAttribute('aria-expanded','false');menu?.focus();syncRail();}});
  const dialog=document.querySelector('#command-palette');
  const input=document.querySelector('#command-input');
  const openPalette=()=>{if(!dialog.open)dialog.showModal();input.value='';dialog.querySelectorAll('.command-results a').forEach(a=>a.hidden=false);input.focus();};
  document.querySelector('.search-launch')?.addEventListener('click',openPalette);
  document.addEventListener('keydown',ev=>{if((ev.metaKey||ev.ctrlKey)&&ev.key.toLowerCase()==='k'){ev.preventDefault();openPalette();}});
  input?.addEventListener('input',()=>{const q=input.value.toLocaleLowerCase();dialog.querySelectorAll('.command-results a').forEach(a=>a.hidden=!a.textContent.toLocaleLowerCase().includes(q));});
  dialog?.addEventListener('keydown',ev=>{const links=[...dialog.querySelectorAll('.command-results a:not([hidden])')];if(ev.key==='ArrowDown'||ev.key==='ArrowUp'){ev.preventDefault();const i=links.indexOf(document.activeElement),next=ev.key==='ArrowDown'?(i+1)%links.length:(i<=0?links.length-1:i-1);links[next]?.focus();}if(ev.key==='Enter'&&document.activeElement===input){ev.preventDefault();links[0]?.click();}});
  dialog?.addEventListener('click',ev=>{if(ev.target===dialog){const r=dialog.getBoundingClientRect();if(ev.clientX<r.left||ev.clientX>r.right||ev.clientY<r.top||ev.clientY>r.bottom)dialog.close();}});
  document.querySelectorAll('[data-copy]').forEach(btn=>btn.addEventListener('click',async()=>{try{await navigator.clipboard.writeText(new URL(btn.dataset.copy,location.origin).href);toast(words('Lien copié.','Link copied.'));}catch{toast(words('Copie indisponible. Ouvrez le média puis copiez son adresse.','Copy unavailable. Open the media and copy its address.'));}}));
  const form=document.querySelector('#content-editor');
  if(!form)return;
  let dirty=false, submitting=false;
  const mark=()=>{dirty=true;const state=document.querySelector('#save-state');state.textContent=words('Modifications non enregistrées','Unsaved changes');state.classList.add('dirty');};
  form.addEventListener('input',mark);
  form.addEventListener('change',mark);
  form.addEventListener('submit',async event=>{
    event.preventDefault();
    const button=form.querySelector('button[type=submit]');
    if(button.disabled)return;
    button.disabled=true;
    try{
      // Check the owner session before leaving the page, so a long editing session does not discard text.
      const response=await fetch(location.href,{credentials:'same-origin',cache:'no-store'});
      const fresh=new DOMParser().parseFromString(await response.text(),'text/html');
      const csrf=fresh.querySelector('meta[name=csrf-token]')?.content;
      if(!response.ok||!csrf){
        let notice=document.querySelector('#editor-session-notice');
        if(!notice){notice=document.createElement('div');notice.id='editor-session-notice';notice.className='notice error';form.before(notice);}
        notice.replaceChildren(document.createTextNode(words('Votre texte est conservé sur cette page. Reconnectez-vous dans un autre onglet, puis réessayez. ','Your text is kept on this page. Sign in in another tab, then try again. ')));
        const link=document.createElement('a');link.href=new URL('login.php',location.href).href;link.target='_blank';link.rel='noopener';link.textContent=words('Ouvrir la connexion','Open sign in');notice.append(link);
        notice.scrollIntoView({block:'center'});return;
      }
      form.elements.csrf.value=csrf;submitting=true;HTMLFormElement.prototype.submit.call(form);
    }catch{toast(words('Connexion indisponible. Votre texte reste sur cette page.','Connection unavailable. Your text stays on this page.'));}
    finally{button.disabled=false;}
  });
  addEventListener('beforeunload',ev=>{if(dirty&&!submitting){ev.preventDefault();ev.returnValue='';}});
  document.querySelectorAll('.language-tab').forEach(btn=>btn.addEventListener('click',()=>{document.querySelectorAll('.language-tab').forEach(b=>{const active=b===btn;b.classList.toggle('active',active);b.setAttribute('aria-selected',String(active));});document.querySelectorAll('.language-panel').forEach(p=>p.hidden=p.dataset.language!==btn.dataset.language);}));
  let editingLanguage=form.querySelector('.language-tab.active')?.dataset.language||'fr';
  form.addEventListener('focusin',ev=>{const panel=ev.target.closest('.language-panel');if(panel)editingLanguage=panel.dataset.language;});
  const activeText=()=>form.classList.contains('bilingual-editor')?form.querySelector('.language-panel[data-language="'+editingLanguage+'"] textarea.body-editor'):form.querySelector('.language-panel:not([hidden]) textarea.body-editor');
  const insert=(textarea,text)=>{textarea.setRangeText(text,textarea.selectionStart,textarea.selectionEnd,'end');textarea.focus();textarea.hidden=false;textarea.closest('.language-panel').querySelector('.editor-preview').hidden=true;mark();};
  form.querySelectorAll('[data-insert]').forEach(btn=>btn.addEventListener('click',()=>{const area=activeText();const selection=area.value.slice(area.selectionStart,area.selectionEnd)||words('Votre texte','Your text');const tag=btn.dataset.insert;let html;if(tag==='a'){const address=prompt(words('Adresse du lien (HTTPS)','Link URL (HTTPS)'),'https://');if(!address)return;const safe=address.replaceAll('&','&amp;').replaceAll('"','&quot;').replaceAll('<','&lt;');html=`<a href="${safe}">${selection}</a>`;}else if(tag==='ul'){html=`<ul>\n<li>${selection}</li>\n</ul>`;}else{html=`<${tag}>${selection}</${tag}>`;}insert(area,html);}));
  form.querySelectorAll('[data-preview]').forEach(btn=>btn.addEventListener('click',async()=>{const panel=btn.closest('.language-panel'),area=panel.querySelector('.body-editor'),preview=panel.querySelector('.editor-preview');if(!preview.hidden){preview.hidden=true;area.hidden=false;btn.textContent=words('Aperçu','Preview');return;}btn.disabled=true;try{const payload=new FormData();payload.set('csrf',form.elements.csrf.value);payload.set('action','editor.preview');payload.set('html',area.value);const response=await fetch(location.href,{method:'POST',body:payload,credentials:'same-origin',headers:{Accept:'application/json'}});const data=await response.json();if(!response.ok)throw Error(data.error||words('Aperçu indisponible.','Preview unavailable.'));preview.innerHTML=data.html;preview.hidden=false;area.hidden=true;btn.textContent=words('Écrire','Write');}catch(error){toast(error.message);}finally{btn.disabled=false;}}));
  document.querySelector('#insert-media')?.addEventListener('click',()=>{const id=form.elements.insert_media.value;if(!id){toast(words('Choisissez une image publique.','Choose a public image.'));return;}const path=new URL('media.php',location.href);path.searchParams.set('id',id);insert(activeText(),`<img src="${path.pathname}?id=${encodeURIComponent(id)}" alt="${(form.elements.insert_media.selectedOptions[0]?.dataset.alt||'').replaceAll('&','&amp;').replaceAll('"','&quot;').replaceAll('<','&lt;')}">`);});
  document.querySelector('#insert-document')?.addEventListener('click',()=>{const select=form.elements.insert_document,id=select.value;if(!id){toast(words('Choisissez un document public.','Choose a public document.'));return;}const path=new URL('media.php',location.href);path.searchParams.set('id',id);path.searchParams.set('download','1');const escape=text=>text.replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;').replaceAll('"','&quot;');const name=select.selectedOptions[0].dataset.name||words('Télécharger le document','Download document');insert(activeText(),'<p><a href="'+escape(path.pathname+path.search)+'">'+escape(name)+'</a></p>');});
  document.addEventListener('keydown',ev=>{if((ev.ctrlKey||ev.metaKey)&&ev.key.toLowerCase()==='s'){ev.preventDefault();form.requestSubmit();}});
})();
