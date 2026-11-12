const fs=require('node:fs'),assert=require('node:assert/strict');
const {chromium}=require('playwright');
(async()=>{
 const browser=await chromium.launch({channel:'chrome',headless:true});const page=await browser.newPage();let checks=0;const check=(ok,label)=>{assert.ok(ok,label);checks++;};const calls=[],errors=[];page.on('pageerror',e=>errors.push(e.message));
 try{
 await page.route('http://shipper.test/**',async route=>{
  const request=route.request(),url=new URL(request.url());calls.push({url:url.pathname,body:request.postData(),headers:request.headers()});
  if(url.pathname==='/'){
   const html=fs.readFileSync('app/Views/shipper/pages/account.php','utf8').replace(/<\?php[\s\S]*?\?>|<\?=[\s\S]*?\?>/g,'');
   return route.fulfill({contentType:'text/html; charset=utf-8',body:`<!doctype html><meta charset="utf-8"><body>${html}<script>window.somiPerson3Open=tab=>window.openedTab=tab;</script><script>${fs.readFileSync('public/assets/js/shipper-api.js','utf8')}</script><script>${fs.readFileSync('public/assets/js/shipper-account.js','utf8')}</script></body>`});
  }
  let data={};if(url.pathname.endsWith('profile.php'))data={profile:{full_name:'Fixture Shipper',vehicle_type:'Xe máy',vehicle_registration:'FIXTURE',verification_status:'NEEDS_INFO'}};
  if(url.pathname.endsWith('kyc.php'))data=request.method()==='GET'?{can_resubmit:true,rejection_reason:'Ảnh mờ'}:{verification_status:'PENDING'};
  if(url.pathname.endsWith('storage.php'))data={file_id:'42'};
  return route.fulfill({contentType:'application/json',body:JSON.stringify(data)});
 });
 await page.goto('http://shipper.test/');await page.waitForFunction(()=>!document.getElementById('kycResubmitForm').hidden);
 await page.getByRole('button',{name:'Quản lý tài khoản nhận tiền'}).click();check(await page.evaluate(()=>window.openedTab)==='bank','account opens real bank panel');
 check(await page.locator('#payoutNumber').count()===0,'demo bank inputs removed');
 await page.locator('[name=document_file]').setInputFiles({name:'fixture.pdf',mimeType:'application/pdf',buffer:Buffer.from('%PDF-1.4 fixture')});
 await page.getByRole('button',{name:'Tải giấy tờ và nộp lại hồ sơ'}).click();await page.waitForFunction(()=>document.getElementById('kycUploadStatus').textContent.includes('Đã nộp'));
 const upload=calls.find(x=>x.url.endsWith('storage.php'));check(upload.headers['content-type'].startsWith('multipart/form-data;'),'KYC sends actual multipart file');check(upload.body.includes('fixture.pdf')&&upload.body.includes('KYC'),'KYC purpose and file sent');
 const submit=calls.find(x=>x.url.endsWith('kyc.php')&&x.body);check(JSON.parse(submit.body).documents[0].reference_file_id==='42','KYC submits provider file id');check(!submit.body.includes('file_reference'),'KYC does not invent object path');
 check(errors.length===0,'no browser errors');console.log(`Shipper Storage browser: ${checks} checks passed (HTTP fixtures).`);
 }finally{await browser.close();}
})().catch(error=>{console.error(error);process.exit(1);});
