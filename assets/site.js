'use strict';
(() => {
  const en=document.documentElement.lang==='en';
  const menu=document.querySelector('.site-menu-button');
  const rail=document.querySelector('.profile-rail');
  menu?.addEventListener('click',()=>{const open=rail.classList.toggle('mobile-open');menu.setAttribute('aria-expanded',String(open));});
  document.querySelectorAll('[data-share]').forEach(button=>button.addEventListener('click',async()=>{
    const url=new URL(button.dataset.share,location.origin).href;
    try{if(navigator.share){await navigator.share({title:button.dataset.title,url});}else{await navigator.clipboard.writeText(url);const toast=document.querySelector('#site-toast');toast.textContent=en?'Link copied.':'Lien copié.';toast.classList.add('visible');setTimeout(()=>toast.classList.remove('visible'),2500);}}catch(error){if(error.name!=='AbortError'){const toast=document.querySelector('#site-toast');toast.textContent=en?'Copy the address from your browser.':'Copiez l’adresse depuis votre navigateur.';toast.classList.add('visible');setTimeout(()=>toast.classList.remove('visible'),3000);}}
  }));
})();
