(() => {
  'use strict';
  // Reports and notifications use Person3 rather than Customer UI/server modules.
  const app=document.querySelector('[data-customer-app]'),i18n=window.SomiI18n;
  if(!app||!i18n||document.querySelector('[data-customer-server]'))return;
  const language=i18n.lang,currency=i18n.currency;
  app.querySelectorAll('[data-set-lang]').forEach(button=>{
    const selected=button.dataset.setLang===language;
    button.classList.toggle('active',selected);button.setAttribute('aria-pressed',String(selected));
    button.addEventListener('click',()=>{if(button.dataset.setLang!==language)i18n.setLanguage(button.dataset.setLang);});
  });
  app.querySelectorAll('[data-set-currency]').forEach(button=>{
    const selected=button.dataset.setCurrency===currency;
    button.classList.toggle('active',selected);button.setAttribute('aria-pressed',String(selected));
    button.addEventListener('click',()=>{if(button.dataset.setCurrency!==currency)i18n.setCurrency(button.dataset.setCurrency);});
  });
  const flag=app.querySelector('[data-locale-flag]'),selectedFlag=app.querySelector(`[data-set-lang="${language}"] .language-flag-svg`);
  if(flag&&selectedFlag)flag.replaceChildren(selectedFlag.cloneNode(true));
  app.querySelectorAll('[data-locale-current]').forEach(node=>node.textContent=`${language.toUpperCase()} · ${currency}`);
  if(!window.bootstrap?.Dropdown)app.querySelectorAll('[data-locale-btn]').forEach(button=>button.addEventListener('click',()=>{
    const menu=button.parentElement.querySelector('.dropdown-menu');if(!menu)return;
    button.setAttribute('aria-expanded',String(menu.classList.toggle('show')));
  }));
})();
