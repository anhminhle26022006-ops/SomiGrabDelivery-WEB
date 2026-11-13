const fs=require('fs');
(async()=>{
 const fixtures=JSON.parse(fs.readFileSync('tests/.runtime/p1-sessions/fixtures.json','utf8'));
 const targets=await(await fetch('http://127.0.0.1:9340/json')).json();const ws=new WebSocket(targets.find(t=>t.type==='page').webSocketDebuggerUrl);await new Promise(r=>ws.onopen=r);
 let sequence=0;const pending=new Map(),errors=[];ws.onmessage=e=>{const m=JSON.parse(e.data);if(m.id){pending.get(m.id)?.(m);pending.delete(m.id);}if(m.method==='Runtime.exceptionThrown')errors.push(m.params.exceptionDetails.exception?.description||m.params.exceptionDetails.text);};
 const send=(method,params={})=>new Promise((resolve,reject)=>{const id=++sequence;const timer=setTimeout(()=>reject(Error(method+' timeout')),30000);pending.set(id,m=>{clearTimeout(timer);m.error?reject(Error(JSON.stringify(m.error))):resolve(m.result);});ws.send(JSON.stringify({id,method,params}));});
 const evaluate=async expression=>{const r=await send('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(r.exceptionDetails)throw Error(r.exceptionDetails.exception?.description||'evaluation failed');return r.result.value;};
 let checks=0;const check=(ok,label)=>{if(!ok)throw Error(label);checks++;};const pause=ms=>new Promise(r=>setTimeout(r,ms));
 async function screenshot(name,width=1440){await send('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:width===375});await pause(100);const shot=await send('Page.captureScreenshot',{format:'png',captureBeyondViewport:true});fs.writeFileSync('tests/.runtime/'+name+'.png',Buffer.from(shot.data,'base64'));}
 await send('Page.enable');await send('Runtime.enable');await send('Network.enable');await send('Network.setCookie',{name:'PHPSESSID',value:fixtures.customer,url:'http://127.0.0.1:3047/'});
 // Only browser rendering uses fixtures. Service/HTTP tests cover actual backend contracts.
 // Intercept all browser API requests: these checks never create an application order/payment.
 await send('Page.addScriptToEvaluateOnNewDocument',{source:`
 window.fixtureRequests=[];window.fixtureDelay=0;window.fixturePayment=null;window.fixtureOrderStatus='PENDING_PAYMENT';
 const profile={user_id:'2',full_name:'Fixture Customer',email:'fixture@example.invalid',phone:'0900000001',nationality_code:'VN',app_language:'vi'};
 const snapshot={currency_code:'VND',context:{distance_km:'4.000'},parcel:{weight_kg:'2.000'},components:{base_fee_minor:'20000',total_charge_minor:'24000',time_multiplier:'1.0000',weather_multiplier:'1.0000'},cancellation_fees:{PENDING_PAYMENT:'0'}};
 window.fetch=async(path,options={})=>{const p=String(path);if(!p.includes('public/api/'))throw Error('Unexpected fixture request');fixtureRequests.push({path:p,method:options.method||'GET',body:options.body,key:options.headers?.['Idempotency-Key']});let data={};
 if(p.includes('account/profile.php')){if(location.search.includes('fixture_error=1'))return new Response(JSON.stringify({code:'SERVICE_UNAVAILABLE'}),{status:503});data={profile};}
 else if(p.includes('addresses.php'))data={items:[{address_id:'1',label:'Fixture',address_detail:'Test address'}]};
 else if(p.includes('parcel-categories.php'))data={items:[{category_id:'1',restriction_type:'ALLOWED',name_vi:'Hàng thử',name_en:'Test parcel'}]};
 else if(p.includes('quotes.php')){await new Promise(r=>setTimeout(r,window.fixtureDelay));data={quote:{quote_id:'12345678901234567890123456789012',snapshot,expires_at:'2099-01-01T00:00:00Z'}};}
 else if(p.includes('cancel-order.php')){window.fixtureOrderStatus='CANCELLED';data={};}
 else if(p.includes('payments.php')){if(options.method==='POST')window.fixturePayment={payment_id:'7',status:'PROCESSING',amount_minor:'24000',currency_code:'VND',checkout_url:'https://example.invalid/checkout'};data={payment:window.fixturePayment};}
 else if(p.includes('refunds.php'))data={items:[]};
 else if(p.includes('orders.php?id='))data={order:{order_id:'1',order_code:'TEST-ORDER',status:window.fixtureOrderStatus,currency_code:window.fixtureCurrency||'VND',total_charge_minor:'24000',pickup_address:'Test pickup',delivery_address:'Test delivery',sender_name:'Sender',sender_phone:'0900000001',receiver_name:'Receiver',receiver_phone:'0900000002',distance_km:'4.000',estimated_weight_kg:'2.000',pricing_snapshot:snapshot,status_history:[]}};
 else if(p.includes('orders.php')){if(options.method==='POST')return new Response(JSON.stringify({code:'SERVICE_UNAVAILABLE'}),{status:503});data={items:location.search.includes('fixture_orders=1')?[{order_id:'1',order_code:'TEST-001',status:'PENDING_PAYMENT',currency_code:'VND',total_charge_minor:'24000',created_at:'2026-11-01T00:00:00Z',pickup_address:'Test A',delivery_address:'Test B',receiver_name:'Fixture Nguyen'},{order_id:'2',order_code:'TEST-002',status:'DELIVERED',currency_code:'VND',total_charge_minor:'24000',created_at:'2010-01-01T00:00:00Z',pickup_address:'Test A',delivery_address:'Test C',receiver_name:'Fixture Tran'}]:[],next_before_id:null};}
 return new Response(JSON.stringify(data),{status:200,headers:{'Content-Type':'application/json'}});};`});
 async function load(view){await send('Page.navigate',{url:'http://127.0.0.1:3047/?page=customer&view='+view});for(let i=0;i<200;i++){await pause(100);if(await evaluate(`document.querySelector('[data-customer-server]')?.dataset.loaded==='true'`))return;}throw Error('view timeout '+view);}
 await send('Page.navigate',{url:'http://127.0.0.1:3047/?page=pricing'});await pause(300);await evaluate("localStorage.setItem('somi-language','en')");
 for(const language of ['en','vi']){
   await evaluate(`localStorage.setItem('somi-language','${language}')`);await load('orders');check(await evaluate(`document.querySelector('[data-customer-server]').textContent.includes(${JSON.stringify(language==='vi'?'Bạn chưa có đơn hàng nào':'No orders yet')})`),'empty orders '+language);
   check(await evaluate("document.querySelector('[data-account-name]').textContent==='Fixture Customer'"),'real profile header '+language);
   check(await evaluate("!window.SomiStore"),'live page excludes demo store '+language);
   await load('orders&fixture_orders=1');check(await evaluate("document.querySelectorAll('[data-list] .c-order-row').length===2"),'server order cards '+language);
   await evaluate("document.querySelector('[data-search]').value='Tran';document.querySelector('[data-search]').dispatchEvent(new Event('input'))");check(await evaluate("document.querySelectorAll('[data-list] .c-order-row').length===1&&document.querySelector('[data-list]').textContent.includes('TEST-002')"),'recipient search '+language);
   await evaluate("document.querySelector('[data-from]').value='2020-01-01';document.querySelector('[data-from]').dispatchEvent(new Event('input'))");check(await evaluate("document.querySelectorAll('[data-list] .c-order-row').length===0"),'date filter '+language);
   await evaluate("document.querySelector('[data-clear]').click();document.querySelector('[data-tab=done]').click()");check(await evaluate("document.querySelectorAll('[data-list] .c-order-row').length===1&&document.querySelector('[data-list]').textContent.includes('TEST-002')"),'status tabs '+language);
   await load('create');check(await evaluate("!!document.querySelector('[data-create]')"),'create form '+language);
   if(language==='vi'){await screenshot('restored-create-desktop');await screenshot('restored-create-mobile',375);}
   check(await evaluate("!!document.querySelector('[data-stepper]')&&!!document.querySelector('[data-create-form-wrap] .col-lg-7')&&!!document.querySelector('.col-lg-5.c-sticky [data-quote]')"),'baseline four-step layout '+language);
   check(await evaluate("!document.querySelector('.c-nav a[href*=backend]')"),'technical tab removed '+language);
   await evaluate(`(()=>{const f=document.querySelector('[data-create-form]');for(const point of ['pickup','delivery']){const s=f.querySelector('[data-side="'+point+'"] [data-saved]');s.value='1';s.dispatchEvent(new Event('change',{bubbles:true}));f.elements[point+'-name'].value='Fixture';f.elements[point+'-phone'].value='0900000001';}f.elements['pkg-weight'].value='2';f.requestSubmit();f.requestSubmit();f.requestSubmit();})()`);
   for(let i=0;i<60;i++){if(await evaluate("!!document.querySelector('[data-confirm]')"))break;await pause(100);}
   check(await evaluate("!!document.querySelector('[data-confirm]')"),'server quote rendered '+language);
   check(await evaluate("fixtureRequests.some(r=>r.path.includes('quotes.php')&&JSON.parse(r.body).parcel.weight_kg==='2.000')"),'weight sent to server '+language);
   await evaluate("document.querySelector('[name=agree]').checked=true;document.querySelector('[data-create-form]').requestSubmit()");await pause(150);
   await evaluate("document.querySelector('[data-create-form]').requestSubmit()");await pause(150);
   check(await evaluate("(()=>{const r=fixtureRequests.filter(r=>r.path.includes('orders.php')&&r.method==='POST');return r.length===2&&r[0].key===r[1].key&&JSON.parse(r[0].body).quote_id==='12345678901234567890123456789012';})()"),'order retry retains quote and idempotency key '+language);
   await evaluate("document.querySelector('[name=pkg-weight]').value='3';document.querySelector('[name=pkg-weight]').dispatchEvent(new Event('input',{bubbles:true}))");check(await evaluate("!document.querySelector('[data-confirm]')"),'editing invalidates quote '+language);
   await evaluate("window.fixtureDelay=300;document.querySelector('[data-create-form]').requestSubmit();document.querySelector('[name=pkg-weight]').dispatchEvent(new Event('input',{bubbles:true}))");await pause(450);check(await evaluate("!document.querySelector('[data-confirm]')"),'stale quote response discarded '+language);
   await load('order&id=1');check(await evaluate("!!document.querySelector('[data-pay]')&&!!document.querySelector('[data-cancel]')"),'unpaid payment and cancel controls '+language);
   await evaluate("document.querySelector('[data-pay]').requestSubmit()");await pause(250);
   check(await evaluate(`fixtureRequests.some(r=>r.path.includes('payments.php')&&r.method==='POST'&&JSON.parse(r.body).method==='CARD')&&!!document.querySelector('a[href="https://example.invalid/checkout"]')`),'SePay checkout from real payment API '+language);
   await load('payment&id=1');check(await evaluate("!!document.querySelector('[data-payment] .col-lg-7 [data-pay]')&&!!document.querySelector('[data-payment] .col-lg-5.c-sticky .c-dark')"),'baseline payment columns '+language);if(language==='vi')await screenshot('restored-payment-desktop');
   await load('account');if(language==='vi')await screenshot('restored-account-desktop');check(await evaluate("!!document.querySelector('.col-lg-4 [data-profile]')&&!!document.querySelector('.col-lg-8 [data-profile-form]')&&!!document.querySelector('[data-password-form]')"),'baseline account columns and forms '+language);
   await load('dashboard');check(await evaluate("!!document.querySelector('[data-live]')&&!!document.querySelector('[data-todo]')&&!!document.querySelector('[data-figures]')"),'baseline dashboard panels '+language);
   if(language==='vi')await screenshot('restored-dashboard-desktop');
   for(const width of [1440,375]){await send('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:width===375});await pause(100);check(await evaluate('document.documentElement.scrollWidth<=innerWidth+1'),'responsive '+language+' '+width);}
 }
 await send('Page.addScriptToEvaluateOnNewDocument',{source:"window.fixtureCurrency='USD'"});await load('order&id=1');check(await evaluate("!document.querySelector('[data-pay]')&&document.querySelector('[data-customer-server]').textContent.includes('USD')"),'USD checkout disabled in UI');const shot=await send('Page.captureScreenshot',{format:'png',captureBeyondViewport:true});fs.writeFileSync('tests/.runtime/person1-customer-mobile.png',Buffer.from(shot.data,'base64'));
 await load('create&fixture_error=1');check(await evaluate("!!document.querySelector('[data-live-message]')&&!!document.querySelector('[data-stepper]')"),'API failure preserves baseline layout');
 check(errors.length===0,'no runtime exceptions');ws.close();console.log(JSON.stringify({result:'PASS',checks,provider_calls:0,business_writes:0}));
})().catch(e=>{console.error(e);process.exit(1)});
