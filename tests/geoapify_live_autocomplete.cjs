// Explicit live-provider smoke: production autocomplete and Geoapify service,
// public/sample address only. No application sessions or database access.
const fs=require('fs'),path=require('path'),http=require('http'),cp=require('child_process'),assert=require('node:assert/strict');
const root=path.resolve(__dirname,'..'),runtime=path.join(__dirname,'.runtime','live-autocomplete');fs.mkdirSync(runtime,{recursive:true});
const port=3069,cdp=9369,base='http://127.0.0.1:'+port;
const php='require getcwd()."/app/Config/environment.php"; somi_load_env(); require getcwd()."/app/Services/GeoapifyAddressService.php"; try { $body=json_decode(stream_get_contents(STDIN),true,32,JSON_THROW_ON_ERROR); echo json_encode((new GeoapifyAddressService())->resolve($body),JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR); } catch(Throwable $e) { echo "{}"; exit(1); }';
let browser,ws,requests=0;
const server=http.createServer(async(req,res)=>{
 if(req.url==='/places.js'){res.setHeader('Content-Type','application/javascript');return res.end(fs.readFileSync(path.join(root,'public/assets/js/customer/places.js')));}
 if(req.url==='/lookup'&&req.method==='POST'){
  if(req.headers.origin!==base){res.writeHead(403);return res.end('{}');}
  let body='';for await(const chunk of req){body+=chunk;if(body.length>2048){res.writeHead(413);return res.end('{}');}}
  requests++;const worker=cp.spawn('C:/xampp/php/php.exe',['-r',php],{cwd:root,windowsHide:true,stdio:['pipe','pipe','ignore']});
  let output='';worker.stdout.on('data',d=>output+=d);worker.on('close',code=>{res.writeHead(code?503:200,{'Content-Type':'application/json; charset=utf-8'});res.end(output||'{}');});worker.stdin.end(body);return;
 }
 res.setHeader('Content-Type','text/html; charset=utf-8');res.end(`<!doctype html><html lang="vi"><head><meta charset="utf-8"><style>body{font:16px system-ui;max-width:760px;margin:45px auto;padding:20px;color:#14284b}input{width:100%;box-sizing:border-box;padding:15px;border:1px solid #bdc8da;border-radius:10px;font:inherit}.list-group{display:grid;border:1px solid #dfe5ef;border-radius:10px;margin-top:6px}.list-group-item{padding:14px;background:white;border:0;border-bottom:1px solid #dfe5ef;text-align:left;font:inherit}.c-hint{display:block;margin-top:10px}button[hidden]{display:none}</style></head><body><h2>Địa chỉ giao hàng</h2><label for="address">Nhập địa chỉ để xem gợi ý tự động</label><input id="address"><script src="/places.js"></script><script>
window.requestCount=0;window.selectedAddress=null;SomiPlaces.attach(document.querySelector('#address'),{api:async(path,method,body)=>{requestCount++;const response=await fetch('/lookup',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)});if(!response.ok)throw Error('Provider unavailable');return response.json();},onInvalidate(){selectedAddress=null;},onSelect(a){selectedAddress=a;}});
</script></body></html>`);
});
const sleep=ms=>new Promise(r=>setTimeout(r,ms));
(async()=>{
 await new Promise(r=>server.listen(port,'127.0.0.1',r));
 browser=cp.spawn('C:/Program Files/Google/Chrome/Application/chrome.exe',['--headless=new','--remote-debugging-port='+cdp,'--user-data-dir='+path.join(runtime,'profile'),'--no-first-run','about:blank'],{stdio:'ignore',windowsHide:true});
 let tabs;for(let i=0;i<60&&!tabs;i++){try{tabs=await(await fetch('http://127.0.0.1:'+cdp+'/json')).json();}catch{await sleep(100);}}
 assert.ok(tabs,'Browser started');ws=new WebSocket(tabs.find(t=>t.type==='page').webSocketDebuggerUrl);await new Promise(r=>ws.onopen=r);
 let serial=0;const pending=new Map();ws.onmessage=e=>{const m=JSON.parse(e.data);if(m.id){pending.get(m.id)?.(m);pending.delete(m.id);}};
 const send=(method,params={})=>new Promise((resolve,reject)=>{const id=++serial;pending.set(id,m=>m.error?reject(Error(JSON.stringify(m.error))):resolve(m.result));ws.send(JSON.stringify({id,method,params}));});
 const evaluate=async expression=>{const r=await send('Runtime.evaluate',{expression,returnByValue:true});if(r.exceptionDetails)throw Error(r.exceptionDetails.text);return r.result.value;};
 await send('Page.enable');await send('Page.navigate',{url:base});
 for(let i=0;i<60&&!await evaluate('!!window.SomiPlaces');i++)await sleep(100);
 const started=Date.now();await send('Runtime.evaluate',{expression:"document.querySelector('#address').focus()"});
 await send('Input.insertText',{text:'157/5 Nguyễn Gia Trí, Thạnh Mỹ Tây, Hồ Chí Minh 700000, Việt Nam'});
 let visible=false;for(let i=0;i<160;i++){visible=await evaluate("document.querySelector('#address').getAttribute('aria-expanded')==='true'&&document.querySelector('[role=option]')?.getBoundingClientRect().height>0");if(visible)break;await sleep(100);}
 assert.ok(visible,'Real suggestions automatically become visible without Enter');
 const ms=Date.now()-started,labels=await evaluate("[...document.querySelectorAll('[role=option]')].map(e=>e.textContent)");
 assert.ok(labels.some(s=>s.includes('157/5')),'Exact alley/house number exists in suggestions');
 const shot=await send('Page.captureScreenshot',{format:'png',captureBeyondViewport:true});fs.writeFileSync(path.join(runtime,'automatic-suggestions.png'),Buffer.from(shot.data,'base64'));
 await evaluate("document.querySelector('[role=option]').click()");assert.ok(await evaluate('!!selectedAddress?.latitude&&!!selectedAddress?.longitude'),'Suggestion selects resolved coordinates');
 const result={result:'PASS',automatic_suggestions:true,enter_pressed:false,ms,provider_requests:requests,database_writes:0};fs.writeFileSync(path.join(runtime,'results.json'),JSON.stringify(result,null,2));console.log(JSON.stringify(result));
})().catch(e=>{console.error(e);process.exitCode=1;}).finally(()=>{ws?.close();browser?.kill();server.close();});
