const fs=require('fs'),path=require('path'),http=require('http'),cp=require('child_process'),assert=require('node:assert/strict');
const root=path.resolve(__dirname,'..'),runtime=path.join(__dirname,'.runtime','customer-orders');fs.mkdirSync(runtime,{recursive:true});
const browserPath=['C:/Program Files/Google/Chrome/Application/chrome.exe','C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe'].find(fs.existsSync);
assert.ok(browserPath,'Chrome or Edge required');
// Render real Customer fragments/scripts; all API responses and mutations are fixtures.
const server=http.createServer((req,res)=>{
 const u=new URL(req.url,'http://127.0.0.1'),script=u.pathname.slice(1);
 if(['maps.js','places.js','server.js'].includes(script)){res.setHeader('Content-Type','application/javascript; charset=utf-8');return res.end(fs.readFileSync(path.join(root,'public/assets/js/customer',script)));}
 if(u.pathname!=='/'){res.writeHead(404);return res.end();}
 const view=['create','orders','order'].includes(u.searchParams.get('view'))?u.searchParams.get('view'):'orders';
 const fragment=fs.readFileSync(path.join(root,'app/Views/customer/pages',view+'.php'),'utf8').replace(/<\?php.*?\?>/gs,'');
 res.setHeader('Content-Type','text/html; charset=utf-8');res.end(`<!doctype html><html lang="vi"><body data-c-view="${view}"><main data-customer-server>${fragment}</main><script defer src="/maps.js"></script><script defer src="/places.js"></script><script defer src="/server.js"></script></body></html>`);
});
let browser,ws,checks=0;const sleep=ms=>new Promise(r=>setTimeout(r,ms));
const check=(ok,label)=>{assert.ok(ok,label);checks++;};
(async()=>{
 await new Promise(r=>server.listen(3063,'127.0.0.1',r));
 browser=cp.spawn(browserPath,['--headless=new','--remote-debugging-port=9363','--user-data-dir='+path.join(runtime,'profile'),'--no-first-run','--no-default-browser-check','about:blank'],{stdio:'ignore',windowsHide:true});
 let targets;for(let i=0;i<60&&!targets;i++){try{targets=await(await fetch('http://127.0.0.1:9363/json')).json();}catch{await sleep(100);}}assert.ok(targets,'CDP available');
 ws=new WebSocket(targets.find(t=>t.type==='page').webSocketDebuggerUrl);await new Promise(r=>ws.onopen=r);
 let sequence=0;const pending=new Map(),errors=[];
 ws.onmessage=e=>{const m=JSON.parse(e.data);if(m.id){pending.get(m.id)?.(m);pending.delete(m.id);}if(m.method==='Runtime.exceptionThrown')errors.push(m.params.exceptionDetails.exception?.description||m.params.exceptionDetails.text);};
 const send=(method,params={})=>new Promise((resolve,reject)=>{const id=++sequence,timer=setTimeout(()=>reject(Error(method+' timeout')),15000);pending.set(id,m=>{clearTimeout(timer);m.error?reject(Error(JSON.stringify(m.error))):resolve(m.result);});ws.send(JSON.stringify({id,method,params}));});
 const evaluate=async expression=>{const r=await send('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(r.exceptionDetails)throw Error(r.exceptionDetails.exception?.description||r.exceptionDetails.text);return r.result.value;};
 const waitFor=async expression=>{for(let i=0;i<100;i++){if(await evaluate(expression))return;await sleep(50);}throw Error('Waiting for '+expression);};
 const click=selector=>evaluate(`document.querySelector(${JSON.stringify(selector)}).click()`);
 await send('Page.enable');await send('Runtime.enable');
 await send('Page.addScriptToEvaluateOnNewDocument',{source:`(${function(){
  window.fixtureRequests=[];window.fixtureCancelConflict=false;window.fixtureQuoteError=null;
  const snapshot={currency_code:'VND',context:{distance_km:'4.500',weather_condition:'LIGHT_RAIN'},parcel:{weight_kg:'3.500'},cancellation_fees:{PENDING_PAYMENT:'0'},components:{base_fee_minor:'20000',distance_component_minor:'6000',weight_component_minor:'1250',time_multiplier:'1.2000',weather_multiplier:'1.1000',total_charge_minor:'35970'}};
  const history=[{old_status:null,new_status:'PENDING_PAYMENT',created_at:'2026-11-09T01:00:00Z'}];
  window.fixtureOrder={order_id:'7',order_code:'SOMI-FIXTURE-007',status:'PENDING_PAYMENT',currency_code:'VND',total_charge_minor:'35970',pickup_address:'Frozen pickup',delivery_address:'Frozen delivery',sender_name:'Sender',sender_phone:'0900000001',receiver_name:'Receiver',receiver_phone:'0900000002',distance_km:'4.500',estimated_weight_kg:'3.500',parcel_description:'Fixture parcel',created_at:'2026-11-09T01:00:00Z',pricing_snapshot:snapshot,status_history:history};
  window.fetch=async(url,options={})=>{
   const u=new URL(url,location.href),body=options.body?JSON.parse(options.body):null,method=options.method||'GET';fixtureRequests.push({path:u.pathname,search:u.search,method,body,key:options.headers?.['Idempotency-Key']});let data={};
   if(u.pathname.endsWith('/account/profile.php'))data={profile:{full_name:'Fixture Customer',phone:'0900000001',app_language:'vi'}};
   else if(u.pathname.endsWith('/addresses.php'))data={items:[{address_id:'1',label:'Home',address_detail:'Saved fixture address',latitude:'10.77',longitude:'106.70',is_default:true}]};
   else if(u.pathname.endsWith('/parcel-categories.php'))data={items:[{category_id:'1',restriction_type:'ALLOWED',name_vi:'Fixture category'}]};
   else if(u.pathname.endsWith('/quotes.php')){
    if(fixtureQuoteError)return new Response(JSON.stringify({code:fixtureQuoteError}),{status:503});
    data={quote:{quote_id:'0123456789abcdef0123456789abcdef',expires_at:'2099-01-01T00:00:00Z',snapshot}};
   }else if(u.pathname.endsWith('/cancel-order.php')){
    if(fixtureCancelConflict)return new Response(JSON.stringify({code:'ORDER_NOT_CANCELLABLE'}),{status:409});
    fixtureOrder.status='CANCELLED';history.push({old_status:'PENDING_PAYMENT',new_status:'CANCELLED',note:body.reason,created_at:'2026-11-09T01:02:00Z'});data={order:fixtureOrder};
   }else if(u.pathname.endsWith('/orders.php')){
    // Simulate an uncertain create response to verify retry uses the same key.
    if(method==='POST')return new Response(JSON.stringify({code:'SERVICE_UNAVAILABLE'}),{status:503});
    data=u.searchParams.has('id')?{order:fixtureOrder}:{items:u.searchParams.has('before_id')?[fixtureOrder]:[{...fixtureOrder,order_id:'8',order_code:'SOMI-FIXTURE-008',status:'DELIVERED',receiver_name:'Other recipient'}],next_before_id:u.searchParams.has('before_id')?null:'8'};
   }else if(u.pathname.endsWith('/payments.php'))data={payment:null};
   else if(u.pathname.endsWith('/refunds.php'))data={items:[]};
   else throw Error('Unexpected API fixture request');
   return new Response(JSON.stringify(data),{status:200});
  };
  window.form=()=>document.querySelector('[data-create-form]');
  window.field=n=>form().elements.namedItem(n);
 }.toString()})();`});
 async function load(view){await send('Page.navigate',{url:'http://127.0.0.1:3063/?view='+view});await waitFor(`document.querySelector('[data-customer-server]')?.dataset.loaded==='true'&&document.body.dataset.cView===${JSON.stringify(view.split('&')[0])}`);}
 await load('orders');
 check(await evaluate(`document.querySelectorAll('.c-order-row').length===2`),'history loads every cursor page');
 check(await evaluate(`fixtureRequests.some(r=>r.search.includes('before_id=8'))`),'history requests next cursor');
 await click('[data-tab="done"]');check(await evaluate(`document.querySelectorAll('.c-order-row').length===1&&document.querySelector('.c-order-row').textContent.includes('008')`),'filter delivered orders');
 await click('[data-tab="all"]');await evaluate(`document.querySelector('[data-search]').value='Receiver';document.querySelector('[data-search]').dispatchEvent(new Event('input'))`);
 check(await evaluate(`document.querySelectorAll('.c-order-row').length===1&&document.querySelector('.c-order-row').textContent.includes('007')`),'search recipient');
 await load('order&id=7');
 check(await evaluate(`document.querySelector('[data-order]').textContent.includes('Frozen pickup')&&document.querySelector('[data-order]').textContent.includes('Frozen delivery')`),'detail shows frozen addresses');
 check(await evaluate(`document.querySelector('.c-fees-total').textContent.includes('35.970')`),'detail displays accepted total');
 check(await evaluate(`document.querySelectorAll('.c-tl-item').length===1&&!!document.querySelector('[data-cancel]')`),'initial timeline and cancellation available');
 await evaluate(`fixtureCancelConflict=true;document.querySelector('[name=reason]').value='Changed plans';document.querySelector('[data-cancel]').requestSubmit()`);
 await waitFor(`document.querySelector('[data-cancel-error] [role=alert]')`);
 check(await evaluate(`document.querySelector('[data-cancel-error]').textContent.includes('không thể hủy')&&!document.querySelector('[data-cancel] button').disabled`),'assignment race explains cancellation rejection and allows refresh');
 await evaluate(`fixtureCancelConflict=false;document.querySelector('[data-cancel]').requestSubmit()`);
 await waitFor(`!document.querySelector('[data-cancel]')&&document.querySelectorAll('.c-tl-item').length===2`);
 check(await evaluate(`document.querySelector('.c-timeline').textContent.includes('Changed plans')&&document.querySelector('.c-fees-total').textContent.includes('35.970')`),'cancel adds reason to timeline and retains price');
 await evaluate(`fixtureOrder.status='SHIPPER_ASSIGNED';document.querySelector('[data-refresh]').click()`);
 await waitFor(`document.querySelector('[data-order]').textContent.includes('Đã phân công')`);
 check(await evaluate(`!document.querySelector('[data-cancel]')`),'assigned order has no customer cancellation action');
 await load('create');
 await evaluate(`const select=document.querySelector('[data-side=delivery] [data-saved]');select.value='1';select.dispatchEvent(new Event('change'));field('delivery-name').value='Receiver';field('delivery-phone').value='0900000002';field('pkg-weight').value='3.5';field('pkg-quantity').value='1'`);
 const submit=()=>evaluate(`form().requestSubmit()`);
 await submit();await submit();await submit();
 await waitFor(`!document.querySelector('[data-step="4"]').hidden`);
 check(await evaluate(`document.querySelector('[data-quote]').textContent.includes('35.970')&&document.querySelector('[data-quote]').textContent.includes('Mưa nhẹ')`),'quote shows accepted price and weather');
 await evaluate(`field('agree').checked=true`);await submit();
 await waitFor(`fixtureRequests.filter(r=>r.path.endsWith('/orders.php')&&r.method==='POST').length===1&&!document.querySelector('[data-next]').disabled`);
 await submit();await waitFor(`fixtureRequests.filter(r=>r.path.endsWith('/orders.php')&&r.method==='POST').length===2`);
 check(await evaluate(`(()=>{const writes=fixtureRequests.filter(r=>r.path.endsWith('/orders.php')&&r.method==='POST');return writes[0].key===writes[1].key&&!!writes[0].key&&Object.keys(writes[0].body).join(',')==='quote_id';})()`),'create retries same quote/key without client price or address');
 await evaluate(`field('pkg-weight').value='4';field('pkg-weight').dispatchEvent(new Event('input',{bubbles:true}))`);
 check(await evaluate(`document.querySelector('[data-step="4"]').hidden&&!document.querySelector('[data-quote]').textContent.includes('35.970')`),'parcel edit invalidates accepted quote');
 for(const [code,message] of [['WEATHER_SERVICE_PAUSED','thời tiết nguy hiểm'],['ROUTE_NOT_FOUND','tuyến xe máy'],['PRICING_CONTEXT_UNAVAILABLE','chưa sẵn sàng']]){
  await evaluate(`fixtureQuoteError=${JSON.stringify(code)}`);await submit();
  await waitFor(`document.querySelector('[data-create-error]').textContent.includes(${JSON.stringify(message)})`);
  check(await evaluate(`document.querySelector('[data-step="4"]').hidden&&!document.querySelector('[data-next]').disabled`),'quote error blocks confirmation: '+code);
 }
 check(errors.length===0,'no browser exceptions: '+errors.join('; '));
 console.log(JSON.stringify({passed:checks,live_database_writes:0,provider_requests:0}));
})().catch(e=>{console.error(e);process.exitCode=1;}).finally(()=>{ws?.close();browser?.kill();server.close();});
