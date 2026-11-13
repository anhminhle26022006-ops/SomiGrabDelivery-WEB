(() => {
  'use strict';
  function translate(){let en=document.documentElement.lang==='en';try{en=localStorage.getItem('somi-language')==='en';}catch{}document.querySelectorAll('[data-w-vi][data-w-en]').forEach(el=>{const text=en?el.dataset.wEn:el.dataset.wVi;if(el.textContent!==text)el.textContent=text;});}
  document.querySelectorAll('[data-shift-checklist]').forEach(panel=>{const update=()=>{panel.querySelector('[data-shift-ready]').textContent=panel.querySelectorAll('input:checked').length+' / '+panel.querySelectorAll('input').length;};panel.addEventListener('change',update);update();});
  document.addEventListener('somi:language-change',translate);translate();
})();
