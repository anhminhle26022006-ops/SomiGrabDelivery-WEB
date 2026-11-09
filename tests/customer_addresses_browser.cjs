const fs = require('fs'), path = require('path'), http = require('http');
const cp = require('child_process'), assert = require('node:assert/strict');
const root = path.resolve(__dirname, '..');
const runtime = path.join(__dirname, '.runtime', 'customer-addresses');
fs.mkdirSync(runtime, {recursive:true});
const browserPath = ['C:/Program Files/Google/Chrome/Application/chrome.exe', 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe'].find(fs.existsSync);
assert.ok(browserPath, 'Chrome or Edge is required');
// Serve production scripts and the address page fragment, with no login or live API writes.
const page = fs.readFileSync(path.join(root, 'app/Views/customer/pages/addresses.php'), 'utf8').replace(/<\?php.*?\?>/gs, '');
const server = http.createServer((req, res) => {
  const name = req.url.split('?')[0];
  const scripts = ['maps.js', 'places.js', 'server.js'];
  if (scripts.includes(name.slice(1))) {
    res.setHeader('Content-Type', 'application/javascript; charset=utf-8');
    return res.end(fs.readFileSync(path.join(root, 'public/assets/js/customer', name.slice(1))));
  }
  if (name !== '/') { res.writeHead(404); return res.end(); }
  res.setHeader('Content-Type', 'text/html; charset=utf-8');
  res.end(`<!doctype html><html lang="vi"><body data-c-view="addresses"><main data-customer-server data-geoapify-key="fixture-browser-key">${page}</main><script defer src="/maps.js"></script><script defer src="/places.js"></script><script defer src="/server.js"></script></body></html>`);
});
let browser, ws, checks = 0;
const sleep = ms => new Promise(r => setTimeout(r, ms));
const check = (ok, label) => { assert.ok(ok, label); checks++; };
(async () => {
  await new Promise(r => server.listen(3061, '127.0.0.1', r));
  browser = cp.spawn(browserPath, ['--headless=new', '--remote-debugging-port=9361', '--user-data-dir='+path.join(runtime, 'profile'), '--no-first-run', '--no-default-browser-check', 'about:blank'], {stdio:'ignore', windowsHide:true});
  let targets;
  for (let i=0;i<60&&!targets;i++) { try { targets=await(await fetch('http://127.0.0.1:9361/json')).json(); } catch { await sleep(100); } }
  assert.ok(targets, 'CDP is available');
  ws = new WebSocket(targets.find(t => t.type==='page').webSocketDebuggerUrl);
  await new Promise(r => ws.onopen=r);
  let sequence=0; const pending=new Map(), errors=[];
  ws.onmessage=e=>{const m=JSON.parse(e.data);if(m.id){pending.get(m.id)?.(m);pending.delete(m.id);}if(m.method==='Runtime.exceptionThrown')errors.push(m.params.exceptionDetails.exception?.description||m.params.exceptionDetails.text);};
  const send=(method,params={})=>new Promise((resolve,reject)=>{const id=++sequence,timer=setTimeout(()=>reject(Error(method+' timeout')),15000);pending.set(id,m=>{clearTimeout(timer);m.error?reject(Error(JSON.stringify(m.error))):resolve(m.result);});ws.send(JSON.stringify({id,method,params}));});
  const evaluate=async expression=>{const r=await send('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(r.exceptionDetails)throw Error(r.exceptionDetails.exception?.description||r.exceptionDetails.text);return r.result.value;};
  const waitFor=async expression=>{for(let i=0;i<100;i++){if(await evaluate(expression))return;await sleep(50);}throw Error('Waiting for '+expression);};
  await send('Page.enable'); await send('Runtime.enable');
  await send('Page.addScriptToEvaluateOnNewDocument', {source:`(${function () {
    window.fixtureItems=[];window.fixtureWrites=[];window.fixtureMaps=[];window.fixtureDelayReverse=false;window.fixtureFail=false;
    window.confirm=()=>true;
    const address={address_detail:'Selected fixture address',province:'Ho Chi Minh City',district:'District 1',ward:'Fixture ward',latitude:'10.7769000',longitude:'106.7009000',country_code:'VN'};
    window.L={map:()=>{const m={events:{},setView(){return this;},on(k,f){this.events[k]=f;},invalidateSize(){},remove(){this.removed=true;}};fixtureMaps.push(m);return m;},tileLayer:()=>({addTo(){}}),circleMarker:p=>({addTo(m){m.pin=p;this.map=m;return this;},setLatLng(p){this.map.pin=p;},remove(){this.map.pin=null;}})};
    window.fetch=async(url,options={})=>{
      const u=new URL(url,location.href),method=options.method||'GET',body=options.body?JSON.parse(options.body):{};
      let data={};
      if(u.pathname.endsWith('/account/profile.php'))data={profile:{full_name:'Fixture Customer',app_language:'vi'}};
      else if(u.pathname.endsWith('/customer/places.php')){
        if(body.action==='reverse'){
          if(fixtureDelayReverse)await new Promise(r=>window.fixtureResolveReverse=r);
          data={address:{...address,address_detail:'Pinned fixture address',latitude:body.latitude,longitude:body.longitude}};
        }else data={items:[{text:address.address_detail,address:{...address}}]};
      }else if(u.pathname.endsWith('/customer/addresses.php')){
        if(method==='GET')data={items:structuredClone(fixtureItems)};
        else{
          fixtureWrites.push({method,body,id:u.searchParams.get('id')});
          if(fixtureFail)return new Response(JSON.stringify({code:'SERVICE_UNAVAILABLE'}),{status:503});
          if(method==='DELETE')fixtureItems=fixtureItems.filter(a=>a.address_id!==u.searchParams.get('id'));
          else{
            if(body.is_default)fixtureItems.forEach(a=>a.is_default=false);
            const id=u.searchParams.get('id')||String(fixtureItems.length+1),saved={...body,address_id:id};
            if(method==='PUT')fixtureItems=fixtureItems.map(a=>a.address_id===id?saved:a);else fixtureItems.push(saved);
            data={address:saved};
          }
        }
      }else throw Error('Unexpected API fixture request');
      return new Response(JSON.stringify(data),{status:200});
    };
    window.field=name=>document.querySelector('[data-address-form]').elements.namedItem(name);
    window.enter=(name,value)=>{field(name).value=value;field(name).dispatchEvent(new Event('input',{bubbles:true}));};
  }.toString()})();`});
  await send('Page.navigate',{url:'http://127.0.0.1:3061/'});
  await waitFor(`document.querySelector('[data-customer-server]')?.dataset.loaded==='true'`);
  check(await evaluate(`document.querySelectorAll('[data-edit]').length===0`),'empty address book');
  const click=selector=>evaluate(`document.querySelector(${JSON.stringify(selector)}).click()`);
  const submit=()=>evaluate(`document.querySelector('[data-address-form]').requestSubmit()`);
  const search=async()=>{await evaluate(`enter('address_detail','Nguyen Hue')`);await waitFor(`document.querySelector('[role=option]')`);await click('[role=option]');};
  await click('[data-add]'); await search();
  check(await evaluate(`field('latitude').value==='10.7769000'&&field('province').value==='Ho Chi Minh City'`),'search selection fills location');
  await evaluate(`enter('label','Home');field('is_default').checked=true`);await submit();
  await waitFor(`document.querySelectorAll('[data-edit]').length===1`);
  check(await evaluate(`fixtureWrites[0].method==='POST'&&fixtureItems[0].is_default===true`),'save new default address');
  await click('[data-edit="1"]');await evaluate(`enter('label','Home renamed')`);
  check(await evaluate(`field('latitude').value==='10.7769000'`),'label edits retain coordinates');
  for(const name of ['address_detail','province','district','ward']){
    await evaluate(`enter(${JSON.stringify(name)},'Changed location')`);
    check(await evaluate(`field('latitude').value===''&&field('longitude').value===''&&!document.querySelector('[data-address-form]').checkValidity()`),'invalidate coordinates on '+name);
    const before=await evaluate('fixtureWrites.length');await submit();
    check(await evaluate(`fixtureWrites.length===${before}`),'block save without new coordinates after '+name);
    await search();
  }
  await evaluate(`fixtureMaps.at(-1).events.click({latlng:{lat:10.78,lng:106.71}})`);
  await waitFor(`field('address_detail').value==='Pinned fixture address'`);
  check(await evaluate(`field('latitude').value==='10.7800000'&&field('longitude').value==='106.7100000'`),'pin selection retains exact coordinates');
  await submit();await waitFor(`document.querySelector('[data-address-editor]').hidden`);
  check(await evaluate(`fixtureItems[0].label==='Home renamed'&&fixtureWrites.at(-1).method==='PUT'`),'edit and save existing address');
  await click('[data-add]');await search();await evaluate(`field('is_default').checked=true`);await submit();
  await waitFor(`document.querySelectorAll('[data-edit]').length===2`);
  check(await evaluate(`fixtureItems.filter(a=>a.is_default).length===1&&fixtureItems[1].is_default`),'switch default address');
  await click('[data-edit="2"]');await evaluate(`enter('latitude','91')`);
  check(await evaluate(`!field('latitude').checkValidity()`),'reject latitude outside range');
  await evaluate(`enter('latitude','10.78');enter('longitude','181')`);
  check(await evaluate(`!field('longitude').checkValidity()`),'reject longitude outside range');
  await evaluate(`enter('longitude','106.71');fixtureDelayReverse=true;fixtureMaps.at(-1).events.click({latlng:{lat:10.79,lng:106.72}})`);
  await waitFor(`typeof fixtureResolveReverse==='function'`);
  await evaluate(`enter('ward','New ward');fixtureResolveReverse()`);await sleep(100);
  check(await evaluate(`field('latitude').value===''&&field('ward').value==='New ward'`),'ignore stale pin after location edit');
  await evaluate(`fixtureDelayReverse=false`);await search();await evaluate(`fixtureFail=true`);await submit();
  await waitFor(`document.querySelector('[data-error] [role=alert]')`);
  check(await evaluate(`!document.querySelector('[data-address-form] button:not([type])').disabled`),'save failure keeps form available for retry');
  await evaluate(`fixtureFail=false;fixtureDelayReverse=true;fixtureMaps.at(-1).events.click({latlng:{lat:10.8,lng:106.73}})`);
  await waitFor(`typeof fixtureResolveReverse==='function'`);await click('[data-close]');
  await evaluate(`fixtureResolveReverse()`);await sleep(100);
  check(await evaluate(`document.querySelector('[data-address-editor]').hidden&&fixtureMaps.at(-1).removed&&field('latitude').value===''`),'cancel disposes map and ignores pending pin');
  await click('[data-archive="2"]');await waitFor(`document.querySelectorAll('[data-edit]').length===1`);
  check(await evaluate(`fixtureWrites.at(-1).method==='DELETE'&&!fixtureItems.some(a=>a.address_id==='2')`),'delete address');
  await click('[data-edit="1"]');
  check(await evaluate(`field('label').value==='Home renamed'&&field('latitude').value==='10.7800000'`),'reload saved values');
  check(errors.length===0,'no browser exceptions: '+errors.join('; '));
  console.log(JSON.stringify({passed:checks,live_database_writes:0,provider_requests:0}));
})().catch(e=>{console.error(e);process.exitCode=1;}).finally(()=>{ws?.close();browser?.kill();server.close();});
