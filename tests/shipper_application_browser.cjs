const assert = require('node:assert/strict');
const fs = require('node:fs');
const {spawn} = require('node:child_process');
const {chromium} = require('playwright');
const base='http://127.0.0.1:3098';
const server=spawn(process.env.SOMI_TEST_PHP || 'C:/xampp/php/php.exe',['-S','127.0.0.1:3098','tests/shipper_application_fixture.php'],{stdio:'ignore',windowsHide:true});
(async()=>{
 let browser;
 try {
  for(let i=0;i<50;i++){try{await new Promise((resolve,reject)=>require('node:http').get(base+'/__application-test',res=>{res.resume();res.on('end',resolve);}).on('error',reject));break;}catch{}await new Promise(r=>setTimeout(r,100));}
  const executablePath=['C:/Program Files/Google/Chrome/Application/chrome.exe','C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe'].find(fs.existsSync);
  browser=await chromium.launch({headless:true,executablePath});
  const page=await browser.newPage();const errors=[],uploads=[],submissions=[];let rejectSubmission=true;
  page.on('pageerror',e=>errors.push(e.message));
  await page.addInitScript(()=>localStorage.setItem('somi-language','vi'));
  await page.route('**/*',async route=>{
   const url=route.request().url();if(!url.startsWith(base))return route.abort();
   if(url.includes('/api/storage.php')){uploads.push(route.request().postDataBuffer());return route.fulfill({json:{file_id:String(uploads.length)}});}
   if(url.includes('/auth/switch-workspace.php')){submissions.push(route.request().postDataJSON());return route.fulfill({status:rejectSubmission?422:200,json:rejectSubmission?{error:'Fixture: kiểm tra lại hồ sơ'}:{role:'shipper'}});}
   if(url.includes('/public/api/'))return route.fulfill({json:{items:[],profile:{full_name:'Test Customer',email:'test@example.invalid'},orders:[],stats:{},eligibility:{blockers:[]},can_work:false}});
   return route.continue();
  });
  fs.mkdirSync('tests/.runtime/application',{recursive:true});
  for(const width of [1440,390]){
   await page.setViewportSize({width,height:900});await page.goto(base+'/__application-test',{waitUntil:'networkidle'});
   assert.equal(await page.locator('[data-shipper-application-form]:visible').count(),0);
   await page.locator('[data-c-avatar]').click();await page.locator('[data-bs-target="#shipperApplicationModal"]').click();
   await page.locator('#shipperApplicationModal.show').waitFor();
   assert.equal(await page.locator('[data-sa-document]:enabled').count(),7);
   assert.equal(await page.locator('body').evaluate(el=>el.scrollWidth<=innerWidth+1),true,'No horizontal overflow');
   await page.locator('[name=vehicle_type]').selectOption('BICYCLE');
   assert.equal(await page.locator('[name=license_number]').isDisabled(),true);
   assert.equal(await page.locator('[data-sa-document]:enabled').count(),3);
   await page.locator('[name=vehicle_type]').selectOption('CAR');
   await page.locator('[name=license_no_expiry]').check();assert.equal(await page.locator('[name=license_expires_on]').isDisabled(),true);
   await page.locator('[name=vehicle_ownership]').selectOption('BORROWED');assert.equal(await page.locator('[data-sa-document]:enabled').count(),8);
   await page.screenshot({path:`tests/.runtime/application/modal-${width}.png`});
   await page.keyboard.press('Escape');await page.locator('#shipperApplicationModal').waitFor({state:'hidden'});
  }
  // A retry reuses private uploads and sends all fields and document references.
  await page.locator('[data-c-avatar]').click();await page.locator('[data-bs-target="#shipperApplicationModal"]').click();
  await page.locator('[name=vehicle_ownership]').selectOption('OWNED');
  const values={residential_address:'123 Test Street',operating_area:'TP HCM',emergency_contact_name:'Test Contact',emergency_contact_phone:'0900000001',emergency_contact_relationship:'Anh trai',vehicle_registration:'59X1-12345',vehicle_brand:'Honda',vehicle_model:'Wave',vehicle_color:'Black',vehicle_year:'2020',vehicle_owner_name:'Test Customer',license_number:'123456789012',license_class:'A1',license_issued_on:'2020-01-01',insurance_expires_on:'2099-01-01'};
  for(const [name,value] of Object.entries(values))await page.locator(`[name=${name}]`).fill(value);
  await page.locator('[name=consent]').check();
  for(const input of await page.locator('[data-sa-document]:enabled').all())await input.setInputFiles({name:'doc.png',mimeType:'image/png',buffer:Buffer.from('test-fixture')});
  await page.locator('[data-shipper-application-form] [type=submit]').click();
  await page.locator('[data-switch-error]').waitFor({state:'visible'});
  assert.equal(uploads.length,7);assert.equal(submissions[0].documents.length,7);assert.equal(submissions[0].residential_address,values.residential_address);assert.equal(submissions[0].consent,true);
  await page.locator('[data-shipper-application-form] [type=submit]').click();await page.waitForFunction(()=>document.querySelector('[data-sa-progress]').textContent==='');assert.equal(uploads.length,7,'Retry does not reupload');assert.equal(submissions.length,2);
  await page.goto(base+'/__application-test?shipper=1',{waitUntil:'networkidle'});
  assert.equal(await page.locator('[data-shipper-application-form]').count(),0);assert.equal(await page.locator('[data-workspace-switch=SHIPPER]').count(),1);await page.locator('[data-c-avatar]').click();assert.equal(await page.locator('[data-workspace-switch=SHIPPER]').isVisible(),true);
  assert.deepEqual(errors,[]);console.log('PASS: avatar-only entry, responsive modal, conditional fields, private upload submission, retry and shipper switch.');
 }finally{if(browser)await browser.close();server.kill();}
})().catch(e=>{console.error(e);process.exitCode=1;});
