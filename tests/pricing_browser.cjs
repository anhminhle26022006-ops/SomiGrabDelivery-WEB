const fs = require('fs');
(async () => {
  let targets;
  for (let attempt=0;attempt<40;attempt++) {
    try { targets = await (await fetch('http://127.0.0.1:9340/json')).json(); if (targets.some(t=>t.type==='page')) break; }
    catch { /* Chrome process may still be starting. */ }
    await new Promise(resolve=>setTimeout(resolve,250));
  }
  if (!targets?.some(t=>t.type==='page')) throw Error('Chrome CDP is unavailable');
  const ws = new WebSocket(targets.find(t => t.type === 'page').webSocketDebuggerUrl);
  await new Promise(resolve => ws.onopen = resolve);
  let id = 0; const pending = new Map(), errors = [], results = [];
  ws.onmessage = e => {
    const message = JSON.parse(e.data);
    if (message.id) { pending.get(message.id)?.(message); pending.delete(message.id); }
    if (message.method === 'Runtime.exceptionThrown') errors.push(message.params.exceptionDetails.text);
  };
  const send = (method, params = {}) => new Promise((resolve, reject) => {
    const n = ++id, timer = setTimeout(() => reject(Error(method + ' timed out')), 20000);
    pending.set(n, message => { clearTimeout(timer); message.error ? reject(Error(JSON.stringify(message.error))) : resolve(message.result); });
    ws.send(JSON.stringify({id:n, method, params}));
  });
  const evaluate = async expression => {
    const out = await send('Runtime.evaluate', {expression, returnByValue:true, awaitPromise:true});
    if (out.exceptionDetails) throw Error(out.exceptionDetails.exception?.description || 'Evaluation failed');
    return out.result.value;
  };
  const wait = ms => new Promise(r => setTimeout(r, ms));
  const check = (ok, test) => { results.push({test, result:ok?'PASS':'FAIL'}); if (!ok) throw Error(test); };
  async function load() {
    await send('Page.navigate',{url:'http://127.0.0.1:3047/?page=pricing'});
    for (let i=0;i<100;i++) {
      await wait(100);
      if (await evaluate(`document.readyState === 'complete' && !!document.querySelector('[data-price-estimator]')`)) return;
    }
    throw Error('Page load timed out');
  }
  await send('Page.enable'); await send('Runtime.enable');
  await load();
  for (const language of ['vi','en','vi','en']) {
    await evaluate(`document.querySelector('[data-language-trigger]').click(); document.querySelector('[data-language-option="${language}"]').click()`);
    check(await evaluate(`document.documentElement.lang === '${language}' && document.querySelector('[data-language-current]').textContent === '${language.toUpperCase()}'`), 'picker matches language ' + language);
    check(await evaluate(`[...document.querySelectorAll('[data-copy-en]')].every(el => el.textContent === el.dataset.${language === 'vi' ? 'copyVi' : 'copyEn'})`), 'all Pricing copy translated ' + language);
    check(await evaluate(`document.querySelector('h1').textContent === ${JSON.stringify(language === 'vi' ? 'Biết trước chi phí trước khi gửi.' : 'Know the cost before you send.')}`), 'hero language ' + language);
    check(await evaluate(`document.querySelector('[data-legal="privacy"]').click(); document.querySelector('[data-legal-title]').textContent === ${JSON.stringify(language === 'vi' ? 'Chính sách quyền riêng tư' : 'Privacy Policy')}`), 'footer modal language ' + language);
    await evaluate(`document.querySelector('[data-legal-modal] [data-modal-close]').click()`);
    check(await evaluate(`document.querySelector('[data-menu-toggle]').getAttribute('aria-label') === ${JSON.stringify(language === 'vi' ? 'Mở menu' : 'Open menu')}`), 'accessible menu label ' + language);
    check(await evaluate(`(()=>{const f=document.querySelector('[data-price-estimator]'); f.elements.distance.value='4.5'; f.elements.weight.value='3.5'; f.elements.currency.value='USD'; f.elements.weather.value='LIGHT_RAIN'; f.elements.peak.checked=true; f.dispatchEvent(new Event('input',{bubbles:true})); return document.querySelector('[data-price-result]').textContent === new Intl.NumberFormat('${language === 'vi' ? 'vi-VN' : 'en-US'}',{style:'currency',currency:'USD'}).format(1.77)})()`), 'USD exact distance and weight rounded example ' + language);
    check(await evaluate(`(()=>{const f=document.querySelector('[data-price-estimator]');f.elements.currency.value='VND';f.dispatchEvent(new Event('input',{bubbles:true}));return document.querySelector('[data-price-result]').textContent === new Intl.NumberFormat('${language === 'vi' ? 'vi-VN' : 'en-US'}',{style:'currency',currency:'VND',maximumFractionDigits:0}).format(35970)})()`), 'VND example ' + language);
    check(await evaluate(`(()=>{const f=document.querySelector('[data-price-estimator]');f.elements.weight.value='30.001';f.dispatchEvent(new Event('input',{bubbles:true}));return document.querySelector('[data-price-result]').dataset.invalid === 'true'})()`), 'overweight rejected ' + language);
    await evaluate(`document.querySelector('[name=weight]').value='1'`);
    check(await evaluate(`(()=>{const f=document.querySelector('[data-price-estimator]');f.elements.distance.value='40';f.dispatchEvent(new Event('input',{bubbles:true}));return document.querySelector('[data-price-result]').textContent.includes('${language === 'vi' ? 'dưới 40' : 'under 40'}')})()`), '40 km unsupported ' + language);
  }
  for (const language of ['vi','en']) {
    await evaluate(`document.querySelector('[data-language-option="${language}"]').click()`);
    await evaluate(`(()=>{const f=document.querySelector('[data-price-estimator]');f.elements.distance.value='4';f.elements.currency.value='VND';f.dispatchEvent(new Event('input',{bubbles:true}))})()`);
    for (const width of [1440,768,375]) {
      await send('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:width===375}); await wait(150);
      check(await evaluate('document.documentElement.scrollWidth <= innerWidth + 1'), 'no horizontal overflow ' + language + ' ' + width);
      if (width !== 768) {
        const shot = await send('Page.captureScreenshot',{format:'png',captureBeyondViewport:true});
        fs.writeFileSync(`tests/.runtime/pricing-${language}-${width}.png`, Buffer.from(shot.data,'base64'));
      }
    }
  }
  await load(); check(await evaluate(`document.documentElement.lang === 'en'`),'saved language survives reload');
  check(errors.length === 0,'no browser runtime exceptions');
  fs.writeFileSync('docs/pricing-browser-results.json',JSON.stringify({results,errors},null,2));
  console.log(JSON.stringify({result:'PASS',checks:results.length,errors:errors.length}));
  ws.close();
})().catch(error=>{console.error(error);process.exit(1)});
