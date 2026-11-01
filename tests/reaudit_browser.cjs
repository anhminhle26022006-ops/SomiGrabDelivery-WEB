const fs=require('fs');
(async()=>{
const fixtures=JSON.parse(fs.readFileSync('tests/.runtime/fixtures.json','utf8').replace(/^\uFEFF/,''));
let targets;
for(let i=0;i<40;i++){try{targets=await(await fetch('http://127.0.0.1:9332/json')).json();break}catch{await new Promise(r=>setTimeout(r,250))}}
if(!targets)throw Error('Chrome CDP is unavailable on port 9332');
const ws=new WebSocket(targets.find(t=>t.type==='page').webSocketDebuggerUrl);await new Promise(r=>ws.onopen=r);
let id=0,loginRequests=0,current='';const pending=new Map(),exceptions=[],networkErrors=[];
ws.onmessage=e=>{const m=JSON.parse(e.data);if(m.id){pending.get(m.id)?.(m);pending.delete(m.id)}if(m.method==='Runtime.exceptionThrown')exceptions.push({page:current,text:m.params.exceptionDetails.exception?.description||m.params.exceptionDetails.text});if(m.method==='Network.requestWillBeSent'&&m.params.request.url.includes('/auth/login.php'))loginRequests++;if(m.method==='Network.responseReceived'&&m.params.response.status>=400)networkErrors.push({page:current,url:m.params.response.url.split('?')[0],status:m.params.response.status})};
const send=(method,params={})=>new Promise((resolve,reject)=>{const n=++id;const timer=setTimeout(()=>{pending.delete(n);reject(Error(method+' timeout'))},20000);pending.set(n,m=>{clearTimeout(timer);m.error?reject(Error(JSON.stringify(m.error))):resolve(m.result)});ws.send(JSON.stringify({id:n,method,params}))});
const evaluate=async expression=>(await send('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true})).result.value;
const sleep=ms=>new Promise(r=>setTimeout(r,ms));
async function load(path){current=path;await send('Page.navigate',{url:'http://127.0.0.1:3033/'+path});await sleep(300);for(let i=0;i<100;i++){if(await evaluate('document.readyState === "complete"'))break;await sleep(100)}await sleep(500)}
await send('Page.enable');await send('Runtime.enable');await send('Network.enable');
const results=[];
for(const width of [1440,768,375]){
 await send('Emulation.setDeviceMetricsOverride',{width,height:900,deviceScaleFactor:1,mobile:width===375});
 for(const role of ['customer','shipper','admin']){
 await send('Network.setCookie',{name:'PHPSESSID',value:fixtures[role],url:'http://127.0.0.1:3033/'});
 await load('?page='+role);
 const actualPage=await evaluate('document.body.dataset.page');
 results.push({test:'workspace role renders',role,width,actualPage,result:actualPage===role?'PASS':'FAIL'});
 const metric=JSON.parse(await evaluate('JSON.stringify({width:innerWidth,scroll:document.documentElement.scrollWidth,pending:!!document.querySelector("[data-shipper-application-form]")})'));
 results.push({test:'workspace overflow',role,width,...metric,result:metric.scroll<=width+1?'PASS':'FAIL'});
 if(role==='shipper'){
 const avatar=await evaluate(`(()=>{const b=document.querySelector('#profileBtn');if(!b)return null;b.click();return document.querySelector('#profileDropdown').classList.contains('open')})()`);
 results.push({test:'shipper avatar opens with one click',width,result:avatar===null?'BLOCKED':avatar?'PASS':'FAIL'});
 const navigated=await evaluate(`(()=>{const a=document.querySelector('#sidebar [data-page="offers"]');if(!a)return false;a.click();return true})()`);
 if(navigated){for(let i=0;i<160;i++){if(await evaluate(`!!document.querySelector('#allOrders button[onclick^="openOrderDetail"]')`))break;await sleep(100)};const view=await evaluate('new URLSearchParams(location.search).get("view")');results.push({test:'shipper offers navigation',width,result:view==='offers'?'PASS':'FAIL'});const offer=await evaluate(`(()=>{const b=document.querySelector('#allOrders button[onclick^="openOrderDetail"]');if(!b)return false;b.click();return !document.querySelector('#orderDetailModal').classList.contains('hidden')})()`);results.push({test:'shipper offer detail opens',width,result:offer?'PASS':'FAIL'});}
 }
 if(role==='customer'){
 const open=await evaluate('document.querySelector("[data-c-avatar]").click(); document.querySelector("[data-c-menu]").classList.contains("show")');
 results.push({test:'customer avatar opens with one click',width,result:open?'PASS':'FAIL'});
 }
 }
}
await send('Emulation.setDeviceMetricsOverride',{width:1440,height:900,deviceScaleFactor:1,mobile:false});
const views={customer:['dashboard','create','orders','order','payment','matching','tracking','review','report','reports','notifications','account','addresses'],shipper:['dashboard','offers','active','history','earnings','account','notifications'],admin:['index','users','customers','shippers','orders','complaints','disputes','payments','reviews','notifications','audit-logs']};
for(const [role,pages] of Object.entries(views)){
 await send('Network.setCookie',{name:'PHPSESSID',value:fixtures[role],url:'http://127.0.0.1:3033/'});
 for(const view of pages){
  const start=exceptions.length;await load('?page='+role+'&view='+view);
  const ok=await evaluate('document.body.dataset.page === '+JSON.stringify(role));
  results.push({test:'private view JavaScript initialization',role,view,result:ok&&exceptions.length===start?'PASS':'FAIL'});
  if(role==='admin' && view!=='index') {
   const filterOk=await evaluate(`(()=>{const b=document.querySelector('.filter-tabs .tab-btn:nth-child(2)');if(!b)return null;b.click();return b.classList.contains('active')})()`);
   results.push({test:'admin filter tab',view,result:filterOk?'PASS':'FAIL'});
   if(view!=='notifications') {
    const searchOk=await evaluate(`(()=>{const input=document.querySelector('.search-box input');input.value='nonexistent-audit-search';input.dispatchEvent(new Event('input',{bubbles:true}));return [...document.querySelectorAll('.users-table-card tbody tr[data-status]')].every(row=>row.hidden) && !document.querySelector('#admin-empty-state').hidden})()`);
    results.push({test:'admin search empty state',view,result:searchOk?'PASS':'FAIL'});
    await evaluate(`document.querySelector('.search-box input').value='';document.querySelector('.search-box input').dispatchEvent(new Event('input'));document.querySelector('.filter-tabs .tab-btn').click()`);
   }
   if(view==='users') {
    const modalOk=await evaluate(`document.querySelector('.action-link-btn').click();document.querySelector('#userModal').classList.contains('show')`);
    const closed=await evaluate(`document.dispatchEvent(new KeyboardEvent('keydown',{key:'Escape'}));!document.querySelector('#userModal').classList.contains('show')`);
    results.push({test:'admin account read-only modal opens and closes',result:modalOk&&closed?'PASS':'FAIL'});
   }
   if(view==='notifications') {
    const readOk=await evaluate(`markAllRead();document.querySelectorAll('.notification-item.unread').length===0`);
    results.push({test:'admin notification preview mark read',result:readOk?'PASS':'FAIL'});
   }
   await send('Emulation.setDeviceMetricsOverride',{width:375,height:900,deviceScaleFactor:1,mobile:true});
   await sleep(100);
   const scroll=await evaluate('document.documentElement.scrollWidth');
   results.push({test:'admin management mobile overflow',view,scroll,result:scroll<=376?'PASS':'FAIL'});
   await send('Emulation.setDeviceMetricsOverride',{width:1440,height:900,deviceScaleFactor:1,mobile:false});
  }

 }
}
await send('Network.deleteCookies',{name:'PHPSESSID',url:'http://127.0.0.1:3033/'});
await load('?page=auth&next=shipper');
const before=loginRequests;
const busy=await evaluate(`document.querySelector('[name="identifier"]').value='somi-audit-nonexistent@example.invalid';document.querySelector('[name="password"]').value='InvalidTestPassword123';for(let i=0;i<3;i++)document.querySelector('[data-auth-form]').dispatchEvent(new Event('submit',{bubbles:true,cancelable:true}));document.querySelector('[data-auth-form]').getAttribute('aria-busy')==='true' && !document.querySelector('[data-auth-status]').hidden`);
await sleep(4000);
results.push({test:'login shows immediate busy feedback',result:busy?'PASS':'FAIL'});
results.push({test:'three submits send one login request',requests:loginRequests-before,result:loginRequests-before===1?'PASS':'FAIL'});
const recovered=await evaluate(`!document.querySelector('[data-auth-submit]').disabled && !document.querySelector('[data-form-error]').hidden`);
results.push({test:'invalid login recovers with visible error',result:recovered?'PASS':'FAIL'});
await send('Page.addScriptToEvaluateOnNewDocument',{source:`Object.defineProperty(window,'localStorage',{get(){throw new DOMException('Storage blocked','SecurityError')}})`});
await load('?page=auth');
const storageSafe=await evaluate(`document.querySelector('[name="identifier"]').value='somi-audit-nonexistent@example.invalid';document.querySelector('[name="password"]').value='InvalidTestPassword123';document.querySelector('[data-auth-form]').dispatchEvent(new Event('submit',{bubbles:true,cancelable:true}));document.querySelector('[data-auth-form]').getAttribute('aria-busy')==='true'`);
await sleep(4000);results.push({test:'login handler works when browser storage is denied',result:storageSafe?'PASS':'FAIL'});
fs.writeFileSync('docs/reaudit-browser-results.json',JSON.stringify({results,exceptions,networkErrors},null,2));console.log(JSON.stringify({checks:results.length,failures:results.filter(r=>r.result==='FAIL'),exceptions,networkErrors}));await send('Browser.close');
})().catch(e=>{console.error(e.message);process.exit(1)});
