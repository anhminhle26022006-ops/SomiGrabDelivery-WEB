const fs=require('fs'),path=require('path'),cp=require('child_process'),assert=require('node:assert/strict');
const root=path.resolve(__dirname,'..'),runtime=path.join(__dirname,'.runtime','admin-live');fs.mkdirSync(runtime,{recursive:true});
const browserPath=['C:/Program Files/Google/Chrome/Application/chrome.exe','C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe'].find(fs.existsSync);
if(!browserPath)throw Error('Browser unavailable');
const php=cp.spawn('C:/xampp/php/php.exe',['-S','127.0.0.1:3059','tests/admin_render_fixture.php'],{cwd:root,stdio:'ignore',windowsHide:true});
const browser=cp.spawn(browserPath,['--headless=new','--remote-debugging-port=9359','--user-data-dir='+path.join(runtime,'profile'),'--no-first-run','--no-default-browser-check','about:blank'],{stdio:'ignore',windowsHide:true});
const sleep=ms=>new Promise(r=>setTimeout(r,ms));let ws,checks=0;
const check=(ok,label)=>{checks++;assert.ok(ok,label);};
(async()=>{
 let targets;for(let i=0;i<80&&!targets;i++){try{targets=await(await fetch('http://127.0.0.1:9359/json')).json();}catch{await sleep(150);}}if(!targets)throw Error('CDP unavailable');
 ws=new WebSocket(targets.find(t=>t.type==='page').webSocketDebuggerUrl);await new Promise(r=>ws.onopen=r);
 let sequence=0;const pending=new Map(),errors=[];
 ws.onmessage=e=>{const m=JSON.parse(e.data);if(m.id){pending.get(m.id)?.(m);pending.delete(m.id);}if(m.method==='Runtime.exceptionThrown')errors.push(m.params.exceptionDetails.exception?.description||m.params.exceptionDetails.text);};
 const send=(method,params={})=>new Promise((resolve,reject)=>{const id=++sequence,timer=setTimeout(()=>reject(Error(method+' timeout')),15000);pending.set(id,m=>{clearTimeout(timer);m.error?reject(Error(JSON.stringify(m.error))):resolve(m.result);});ws.send(JSON.stringify({id,method,params}));});
 const evaluate=async expression=>{const r=await send('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(r.exceptionDetails)throw Error(r.exceptionDetails.exception?.description||r.exceptionDetails.text);return r.result.value;};
 await send('Page.enable');await send('Runtime.enable');
 await send('Page.addScriptToEvaluateOnNewDocument',{source: 
 "localStorage.setItem('somi-language','vi');"+
 "window.fixturePosts=[];window.fixtureDeny=false;window.ticketStatus='NEW';"+
 "window.fetch="+String(async function(url,options={}){
 const u=new URL(String(url),location.href),p=u.pathname,b=options.body?JSON.parse(options.body):{};let data={};
 const person={user_id:'2',full_name:'Fixture <script>bad</script>',email:'test@example.invalid',phone:'0900000002',nationality_code:'VN',account_status:'ACTIVE',created_at:'2026-11-09T00:00:00Z',roles:['CUSTOMER','SHIPPER'],verification_status:'PENDING',vehicle_type:'Motorcycle',vehicle_registration:'TEST',reliability_score:'20'};
 const order={order_id:'7',order_code:'TEST-007',customer_id:'2',status:'SHIPPER_ASSIGNED',currency_code:'VND',total_charge_minor:'24000',pickup_address:'Fixture pickup',delivery_address:'Fixture destination',sender_name:'Sender',sender_phone:'0900000001',receiver_name:'Recipient',receiver_phone:'0900000002',created_at:'2026-11-09T00:00:00Z',status_history:[]};
 const policy={pricing_policy_id:'3',policy_name:'Fixture policy',currency_code:'VND',config_version:1,active:true,base_fee_minor:'20000',included_distance_km:'3',per_km_minor:'4000',included_weight_kg:'3',per_kg_minor:'2500',max_distance_km:'40',max_weight_kg:'30',return_fee_minor:'0',time_bands:[{day_of_week:null,starts_at_local:'00:00:00',ends_at_local:'12:00:00',multiplier:'1'},{day_of_week:null,starts_at_local:'12:00:00',ends_at_local:'00:00:00',multiplier:'1.2'}],weather_bands:['NORMAL','LIGHT_RAIN','RAIN'].map(code=>({provider_condition_code:code,label:code,multiplier:'1'})),cancellation_fees:[]};
 if((options.method||'GET')!=='GET'){fixturePosts.push({path:p,body:b});if(fixtureDeny)return new Response(JSON.stringify({error:'Forbidden fixture',code:'PERMISSION_DENIED'}),{status:403});if(b.action==='TICKET_STATUS')ticketStatus=b.status;return new Response(JSON.stringify({success:true}),{status:200});}
 if(p.endsWith('/account/profile.php'))data={profile:{...person,full_name:'Fixture Admin'}};
 else if(p.endsWith('/admin/console.php')){
 const section=u.searchParams.get('section');
 if(section==='overview')data={stats:{accounts:5,orders:3,active_orders:1,pending_shippers:2,open_tickets:1,failed_jobs:0},collected:[{currency_code:'VND',collected_minor:'50000'}],days:Array.from({length:7},(_,i)=>({day:'2026-11-0'+(i+1),orders:i})),recent_orders:[order],recent_audit:[]};
 else if(section==='accounts')data={items:[person],next_before_id:null};
 else if(section==='account')data={account:{...person,shipper:{vehicle_type:'Motorcycle',vehicle_registration:'TEST',verification_status:'PENDING',reliability_score:'20'},identity:{document_type:'NATIONAL_ID',document_country_code:'VN'},history:[]}};
 else if(section==='tickets')data=u.searchParams.has('id')?{ticket:{ticket_id:'4',ticket_code:'T-004',order_id:'7',order_code:'TEST-007',reporter_name:person.full_name,subject:'Test support',description:'Fixture support',status:ticketStatus,events:[]}}:{items:[{ticket_id:'4',ticket_code:'T-004',order_code:'TEST-007',reporter_name:person.full_name,subject:'Test support',status:ticketStatus,created_at:'2026-11-09T00:00:00Z'}],next_before_id:null};
 else if(section==='chat')data={items:[{sender_name:'Fixture sender',body:'Safe <img src=x onerror=alert(1)> text',sent_at:'2026-11-09T00:00:00Z'}]};
 else if(section==='reviews')data={items:[{review_id:'5',order_id:'7',order_code:'TEST-007',customer_name:'Customer',shipper_name:'Shipper',rating:4,comment:'Fixture review',created_at:'2026-11-09T00:00:00Z'}],next_before_id:null};
 else if(section==='notifications')data={items:[{notification_id:'8',title_vi:'Thông báo thử',message_vi:'Fixture notification',title_en:'Test notification',message_en:'Fixture notification',read_at:null}],next_before_id:null};
 }
 else if(p.endsWith('/orders.php'))data=u.searchParams.has('id')?{order}:{items:[order]};
 else if(p.endsWith('/payments.php'))data={items:[{payment_id:'9',order_id:'7',amount_minor:'24000',currency_code:'VND',status:'PAID',provider:'SEPAY'}]};
 else if(p.endsWith('/refunds.php'))data={items:[{refund_id:'10',order_id:'7',refund_amount_minor:'23000',currency_code:'VND',status:'PROCESSING'}]};
 else if(p.endsWith('/parcel-categories.php'))data={items:[{category_id:'1',category_code:'TEST',name_vi:'Hàng thử',name_en:'Test parcel',restriction_type:'ALLOWED',active:true}]};
 else if(p.endsWith('/nationality-requests.php'))data={items:[{request_id:'11',user_id:'2',nationality_code:'US',status:'PENDING',reason:'Fixture'}]};
 else if(p.endsWith('/policies.php'))data=u.searchParams.has('id')?{policy}:{pricing:[policy],earning:[],score:[]};
 else if(p.endsWith('/audit-logs.php'))data={items:[{audit_id:'12',actor_id:'1',actor_kind:'ADMIN',action:'TEST',entity_type:'USER',entity_id:'2',old_value:{status:'ACTIVE'},new_value:{status:'SUSPENDED'}}]};
 else if(p.endsWith('/jobs.php'))data={items:[{job_id:'13',kind:'EMAIL',status:'FAILED',attempts:8,last_error_code:'TEST'}]};
 else if(p.endsWith('/email-logs.php'))data={items:[]};
 else throw Error('Unmocked '+p);
 return new Response(JSON.stringify(data),{status:200,headers:{'Content-Type':'application/json'}});
 })});
 async function waitFor(expression){for(let i=0;i<100;i++){if(await evaluate(expression))return;await sleep(50);}throw Error('Timed out waiting: '+expression);}
 async function navigate(view,width=1440){await send('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:width<768});await send('Page.navigate',{url:'http://127.0.0.1:3059/__admin-test?view='+view});await waitFor("document.querySelector('[data-admin-content]')?.getAttribute('aria-busy')==='false'");}
 async function click(action){await evaluate("document.querySelector('[data-action=\""+action+"\"]').click()");await sleep(120);}
 const sections=['index','users','customers','shippers','orders','payments','refunds','complaints','reviews','categories','policies','nationality','notifications','audit-logs','operations'];
 for(const width of [1440,768,375])for(const section of sections){await navigate(section,width);check(await evaluate("!document.querySelector('[data-admin-status] .admin-error')"),section+' no API error');if(!(await evaluate('document.documentElement.scrollWidth<=innerWidth+1'))){const shot=await send('Page.captureScreenshot',{format:'png',captureBeyondViewport:true});fs.writeFileSync(path.join(runtime,'overflow.png'),Buffer.from(shot.data,'base64'));console.log(await evaluate("JSON.stringify([...document.querySelectorAll('*')].filter(e=>e.getBoundingClientRect().right>innerWidth+1).map(e=>({tag:e.tagName,cls:e.className,right:e.getBoundingClientRect().right,width:e.getBoundingClientRect().width})).slice(0,12))"));}check(await evaluate('document.documentElement.scrollWidth<=innerWidth+1'),section+' responsive '+width);check(await evaluate("!document.querySelector('[data-admin-content] script')"),section+' escapes HTML');}
 await navigate('shippers');await click('account');await waitFor("document.querySelector('dialog').open");check(await evaluate("document.querySelector('dialog').textContent.includes('NATIONAL_ID')"),'KYC metadata visible');check(await evaluate("!document.querySelector('dialog script')"),'account name escaped');
 await click('kyc-info');check(await evaluate('fixturePosts.length===0'),'KYC requires confirmation');await evaluate("document.querySelector('[name=note]').value='Fixture missing evidence';document.querySelector('[data-mutation]').requestSubmit()");await waitFor('fixturePosts.length===1');check(await evaluate("fixturePosts[0].body.decision==='NEEDS_INFO'"),'KYC request payload');
 await navigate('users');await click('suspend');await evaluate("fixtureDeny=true;document.querySelector('[name=note]').value='Test';document.querySelector('[data-mutation]').requestSubmit()");await waitFor("!!document.querySelector('[data-form-error] .admin-error')");check(await evaluate("document.querySelector('dialog').open"),'permission failure preserves form');
 await navigate('complaints');await click('ticket');await click('ticket-transition');await evaluate("document.querySelector('[name=note]').value='Fixture handling';document.querySelector('[data-mutation]').requestSubmit()");await waitFor('fixturePosts.length===1');check(await evaluate("fixturePosts[0].body.status==='IN_PROGRESS'"),'ticket correct next state');
 await navigate('policies');await click('policy');await click('clone-policy');check(await evaluate("document.querySelectorAll('[data-time-band]').length===2"),'clone preserves time bands');await click('add-band');check(await evaluate("document.querySelectorAll('[data-time-band]').length===3"),'add band');
 await navigate('orders');await click('order');await click('cancel-order');check(await evaluate('fixturePosts.length===0'),'cancel requires explicit confirmation');
 await navigate('index',1440);const desktop=await send('Page.captureScreenshot',{format:'png',captureBeyondViewport:true});fs.writeFileSync(path.join(runtime,'dashboard-desktop.png'),Buffer.from(desktop.data,'base64'));
 await navigate('shippers',375);const mobile=await send('Page.captureScreenshot',{format:'png',captureBeyondViewport:true});fs.writeFileSync(path.join(runtime,'shippers-mobile.png'),Buffer.from(mobile.data,'base64'));
 check(errors.length===0,'no JavaScript exceptions: '+errors.join(';'));
 fs.writeFileSync(path.join(runtime,'results.json'),JSON.stringify({checks,errors,sections:sections.length,widths:[1440,768,375],source:'browser fixtures; no real backend mutations'},null,2));console.log('PASS: '+checks+' Admin browser checks; 15 views at 3 widths; no real mutations.');
 await send('Browser.close');
})().catch(e=>{console.error(e);process.exitCode=1;}).finally(()=>{try{ws?.close();}catch{}browser.kill();php.kill();setTimeout(()=>process.exit(process.exitCode||0),500);});
