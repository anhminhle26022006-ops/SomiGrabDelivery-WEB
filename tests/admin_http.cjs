const cp=require('child_process'),assert=require('node:assert/strict'),path=require('path'),http=require('node:http');
function fetch(url,options={},depth=0){return new Promise((resolve,reject)=>{if(depth>8)return reject(Error('Redirect loop'));const req=http.request(url,{method:options.method||'GET',headers:{Connection:'close',...options.headers},agent:false},res=>{let body='';res.on('data',c=>body+=c);res.on('end',()=>{if([301,302,303,307,308].includes(res.statusCode)&&res.headers.location&&options.redirect!=='manual'){fetch(new URL(res.headers.location,url).href,{...options,method:'GET',body:undefined},depth+1).then(resolve,reject);return;}resolve({status:res.statusCode,url,headers:{get:name=>res.headers[name.toLowerCase()]},text:async()=>body});});});req.on('error',reject);if(options.body)req.write(options.body);req.end();});}

const server=cp.spawn('C:/xampp/php/php.exe',['-d','extension=pdo_pgsql','-S','127.0.0.1:3061','index.php'],{cwd:path.resolve(__dirname,'..'),stdio:'ignore',windowsHide:true});
const plain=cp.spawn('C:/xampp/php/php.exe',['-S','127.0.0.1:3062','-t',path.resolve(__dirname,'..')],{cwd:path.resolve(__dirname,'..'),stdio:'ignore',windowsHide:true});
const wait=ms=>new Promise(r=>setTimeout(r,ms));const base='http://127.0.0.1:3061';let checks=0;
(async()=>{
 for(let i=0;i<60;i++){try{await fetch(base);break;}catch{await wait(100);}}
 for(const path of ['/public/api/admin/weather.php','/public/api/admin/console.php?section=overview','/public/api/admin/console.php?section=accounts','/public/api/admin/console.php?section=tickets','/public/api/admin/console.php?section=chat&ticket_id=1']){const r=await fetch(base+path);assert.equal(r.status,401,path);checks++;}
 let r=await fetch(base+'/public/api/admin/actions.php');assert.equal(r.status,405);checks++;
 r=await fetch(base+'/public/api/admin/actions.php',{method:'POST',headers:{'Content-Type':'application/json',Origin:'https://untrusted.example'},body:JSON.stringify({action:'CANCEL_ORDER',order_id:'1',reason:'fixture'})});assert.equal(r.status,403);checks++;
 r=await fetch(base+'/public/api/admin/actions.php',{method:'POST',headers:{'Content-Type':'application/json',Origin:base},body:JSON.stringify({action:'TICKET_STATUS',ticket_id:'1',status:'CLOSED',note:'fixture'})});assert.equal(r.status,401);checks++;
 r=await fetch(base+'/?page=admin',{redirect:'manual'});assert.equal(r.status,303);assert.ok(r.headers.get('location').includes('page=auth'));checks+=2;
 for(const path of ['/admin','/admin/']){r=await fetch(base+path);assert.equal(r.status,200,path+' reaches login');assert.ok(r.url.includes('page=auth'),path+' redirects without looping');checks+=2;}
 for(const path of ['/.env','/.env.example','/tests/admin_render_fixture.php']){r=await fetch(base+path);assert.equal(r.status,404,path);checks++;}
 for(const template of ['index','users','shippers','payments','live']){r=await fetch('http://127.0.0.1:3062/app/Views/admin/'+template+'.php',{redirect:'manual'});assert.equal(r.status,303,template+' must redirect through auth');assert.ok(r.headers.get('location').includes('/index.php?page=admin'));checks+=2;}
 r=await fetch('http://127.0.0.1:3062/app/Views/admin/index.php');const html=await r.text();assert.equal(r.status,200);assert.ok(!/Fatal error|Warning:|Undefined variable/.test(html));assert.ok(r.url.includes('page=auth'));checks+=3;
 r=await fetch(base+'/public/assets/css/modules/admin-live.css');assert.equal(r.status,200);assert.ok((await r.text()).includes('.admin-live'));checks+=2;
 console.log('PASS: '+checks+' Admin HTTP auth/origin/method/private-file checks; no DB writes.');
})().catch(e=>{console.error(e);process.exitCode=1;}).finally(()=>{server.kill();plain.kill();setTimeout(()=>process.exit(process.exitCode||0),300);});
