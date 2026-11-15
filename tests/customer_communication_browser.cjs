const fs=require('node:fs'),path=require('node:path'),cp=require('node:child_process'),assert=require('node:assert/strict');
const {chromium}=require('playwright');
(async()=>{
 const browser=await chromium.launch({headless:true,channel:'chrome'});
 try {
 const page=await browser.newPage({viewport:{width:1440,height:1000}}),errors=[],calls=[];let count=0,failUpload=true,failSend=true;
 const check=(value,name)=>{assert.ok(value,name);count++;};page.on('pageerror',e=>errors.push(e.message));
 const orders=[{order_id:'1',order_code:'SOMI-OLD',status:'CANCELLED'},{order_id:'2',order_code:'SOMI-NEW',status:'IN_DELIVERY'}];
 const tickets=[{ticket_id:'10',ticket_code:'TK-OLD',order_id:'1',order_code:'SOMI-OLD',subject:'Hoàn tiền đơn cũ',description:'Nội dung đơn cũ',status:'NEW'}],files=[],replies=[],messages=[];
 await page.route('http://customer.test/**',async route=>{
  const req=route.request(),url=new URL(req.url());
  if(url.pathname==='/')return route.fulfill({contentType:'text/html',body:cp.execFileSync('C:/xampp/php/php.exe',['tests/customer_communication_fixture.php',url.search.slice(1)],{encoding:'utf8',windowsHide:true})});
  if(url.pathname.startsWith('/public/assets/')){
   if(url.pathname.endsWith('.js')&&!url.pathname.endsWith('/customer/communication.js'))return route.fulfill({contentType:'application/javascript',body:''});
   const file=path.join(process.cwd(),url.pathname.slice(1));return fs.existsSync(file)?route.fulfill({contentType:url.pathname.endsWith('.css')?'text/css':url.pathname.endsWith('.js')?'application/javascript':'application/octet-stream',body:fs.readFileSync(file)}):route.abort();
  }
  const body=req.headers()['content-type']?.includes('application/json')?req.postDataJSON():null;calls.push({url:req.url(),body,method:req.method()});let data={},status=200;
  if(url.pathname.endsWith('/customer/orders.php'))data={order:orders.find(o=>o.order_id===url.searchParams.get('id'))};
  else if(url.pathname.endsWith('/interactions.php')){
   const resource=url.searchParams.get('resource');
   if(resource==='orders')data={items:orders,next_cursor:null};
   if(resource==='tickets'){
    if(body){const item={...body,ticket_id:'11',ticket_code:'TK-NEW',status:'NEW',order_code:'SOMI-NEW'};tickets.push(item);data={ticket:item};}
    else if(url.searchParams.has('id'))data={ticket:tickets.find(t=>t.ticket_id===url.searchParams.get('id')),events:[]};
    else data={items:tickets.filter(t=>!url.searchParams.has('order_id')||t.order_id===url.searchParams.get('order_id')),next_cursor:null};
   }
   if(resource==='chat'){
    if(body?.body!==undefined){if(failSend){failSend=false;status=503;data={error:'Fixture send failed'};}else{messages.push({message_id:'1',sender_id:'2',body:body.body,sent_at:'2026-11-15T03:00:00Z',attachments:(body.file_ids||[]).map(id=>({file_id:id,original_name:'chat.pdf'}))});data={message:messages.at(-1)};}}
    else data={items:[...messages].reverse(),next_cursor:null};
   }
  }else if(url.pathname.endsWith('/storage.php')){
   if(req.method()==='POST'){
    const text=req.postData();if(text.includes('name="ticket_id"')){if(failUpload){failUpload=false;status=503;data={error:'Fixture upload failed'};}else{files.push({file_id:'91',original_name:'ticket.pdf',ticket_id:'11'});data={file_id:'91'};}}
    else data={file_id:'92'};
   }else data={items:files.filter(f=>f.ticket_id===url.searchParams.get('ticket_id'))};
  }else if(url.pathname.endsWith('/support/replies.php')){if(body)replies.push({reply_id:'1',sender_role:'CUSTOMER',body:body.body,created_at:'2026-11-15T03:00:00Z'});data={items:[...replies].reverse(),next_cursor:null};}
  return route.fulfill({status,contentType:'application/json',body:JSON.stringify(data)});
 });
 await page.goto('http://customer.test/?page=customer&view=reports&id=2');await page.locator('[data-create]').waitFor();
 check(await page.locator('[data-tickets]').getByText('Hoàn tiền đơn cũ').count()===0,'order support excludes unrelated ticket');
 check(await page.locator('[data-create] [name=order_id]').isDisabled(),'order scope is fixed');
 check(await page.locator('[data-p3-tab]').count()===0,'Customer support has no mixed chat/files tabs');
 check(calls.some(c=>c.url.includes('resource=tickets&order_id=2')),'ticket filter is sent to API');
 const form=page.locator('[data-create]');await form.locator('[name=subject]').fill('Kiểm tra kiện hàng');await form.locator('[name=description]').fill('Ảnh đính kèm trong yêu cầu');
 await form.locator('[name=attachments]').setInputFiles({name:'ticket.pdf',mimeType:'application/pdf',buffer:Buffer.from('%PDF-1.4 fixture')});
 check((await form.locator('[data-selected]').textContent()).includes('Chưa gửi'),'selected preview explicitly not sent');
 await form.locator('[type=submit]').click();await form.locator('[data-feedback]').filter({hasText:'Fixture upload failed'}).waitFor();
 check(tickets.filter(t=>t.ticket_id==='11').length===1,'ticket exists after partial attachment failure');
 await form.locator('[type=submit]').click();await form.locator('[data-feedback]').filter({hasText:'TK-NEW'}).waitFor();
 check(calls.filter(c=>c.body?.subject==='Kiểm tra kiện hàng').length===1,'retry attachments does not create duplicate ticket');
 await page.getByRole('button',{name:'Kiểm tra kiện hàng'}).click();await page.locator('[data-detail] .cc-file').waitFor();
 check((await page.locator('[data-detail]').textContent()).includes('Ảnh đính kèm trong yêu cầu'),'original request remains visible');
 check((await page.locator('[data-detail]').textContent()).includes('Đang chờ Admin phản hồi'),'new ticket shows waiting state');
 const reply=page.locator('.cc-reply-form');await reply.locator('[name=body]').fill('Thông tin bổ sung');await reply.locator('[type=submit]').click();await page.locator('.cc-reply').filter({hasText:'Thông tin bổ sung'}).waitFor();check(replies.length===1,'Customer sends follow-up to Admin');
 fs.mkdirSync('tests/.runtime/customer-communication',{recursive:true});await page.screenshot({path:'tests/.runtime/customer-communication/support-desktop.png',fullPage:true});
 await page.setViewportSize({width:390,height:844});check(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'support mobile has no horizontal overflow');await page.evaluate(()=>scrollTo(0,0));await page.screenshot({path:'tests/.runtime/customer-communication/support-mobile.png',fullPage:true});
 await page.goto('http://customer.test/?page=customer&view=reports');await page.getByRole('button',{name:'Hoàn tiền đơn cũ'}).waitFor();check((await page.locator('[data-tickets]').textContent()).includes('SOMI-OLD'),'general support shows related order code');
 await page.goto('http://customer.test/?page=customer&view=chat&id=1');await page.locator('.cc-closed').waitFor();check(await page.locator('.cc-composer').count()===0,'closed order has no send or upload composer');
 await page.goto('http://customer.test/?page=customer&view=chat&id=2');await page.locator('.cc-composer').waitFor();
 const composer=page.locator('.cc-composer');await composer.locator('[name=body]').fill('<img src=x onerror=alert(1)>');await composer.locator('[name=attachments]').setInputFiles({name:'chat.pdf',mimeType:'application/pdf',buffer:Buffer.from('%PDF-1.4 chat')});
 await composer.locator('[type=submit]').click();await composer.locator('[data-feedback]').filter({hasText:'Fixture send failed'}).waitFor();
 check(await composer.locator('[name=body]').inputValue()==='<img src=x onerror=alert(1)>','chat failure preserves draft');
 await composer.locator('[type=submit]').click();await page.locator('.cc-message').waitFor();const sends=calls.filter(c=>c.body?.file_ids);
 check(sends.length===2&&sends[0].body.client_key===sends[1].body.client_key,'chat retry uses same idempotency key');
 check(calls.filter(c=>c.url.endsWith('/storage.php')&&c.method==='POST').length===3,'chat retry reuses uploaded attachment');
 check(await page.locator('.cc-messages img').count()===0,'user message HTML escaped');
 check(await page.locator('.cc-composer [name=body]').inputValue()==='','successful chat clears draft');
 check(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'chat mobile has no overflow');messages[0].body='Mình đã chuẩn bị kiện hàng, bạn gọi khi đến nhé.';messages.push({message_id:'2',sender_id:'3',body:'Mình sẽ đến trong khoảng 5 phút nhé.',sent_at:'2026-11-15T03:01:00Z',attachments:[]});await page.locator('[data-refresh]').click();await page.getByText('Mình sẽ đến trong khoảng 5 phút nhé.',{exact:true}).waitFor();await page.evaluate(()=>scrollTo(0,0));await page.screenshot({path:'tests/.runtime/customer-communication/chat-mobile.png',fullPage:true});
 await page.setViewportSize({width:1440,height:1000});await page.screenshot({path:'tests/.runtime/customer-communication/chat-desktop.png',fullPage:true});

 for(const width of [768,1024]){await page.setViewportSize({width,height:1000});check(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'tablet shell and new navigation do not overflow at '+width);}
 await page.goto('http://customer.test/?page=customer&view=order&id=2');await page.locator('.cc-composer').waitFor();check(await page.locator('.cc-embedded').count()===1,'order detail embeds only its Shipper chat');check(await page.locator('[data-create]').count()===0,'order detail no longer shows global tickets or upload tabs');
 await page.goto('http://customer.test/?page=customer&view=notifications');check(await page.locator('[data-p3-tab=chat]').count()===0,'notifications page does not expose legacy support/chat navigation');
 await page.goto('http://customer.test/?page=customer&view=chat&id=2');await page.locator('.cc-composer').waitFor();orders[1].status='CANCELLED';await page.locator('[data-refresh]').click();await page.locator('.cc-closed').waitFor();check(await page.locator('.cc-composer').count()===0,'status change closes composer on refresh');orders[1].status='IN_DELIVERY';
 await page.evaluate(()=>localStorage.setItem('somi-language','en'));await page.goto('http://customer.test/?page=customer&view=reports&id=2');await page.getByRole('heading',{name:'SOMI support',exact:true}).waitFor();check(await page.locator('[data-create] [type=submit]').textContent()==='Submit request ↗','English support interface available');
 check(errors.length===0,'no runtime errors');console.log(`Customer communication: ${count} checks passed; local HTTP fixtures, no live uploads.`);
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
