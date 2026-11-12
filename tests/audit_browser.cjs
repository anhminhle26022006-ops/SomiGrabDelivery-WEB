const fs = require('fs');
(async () => {
const targets = await (await fetch('http://127.0.0.1:9331/json')).json();
const ws = new WebSocket(targets.find(t=>t.type==='page').webSocketDebuggerUrl);
await new Promise(r=>ws.onopen=r);
let id=0; const pending=new Map(), errors=[];
ws.onmessage=e=>{const m=JSON.parse(e.data);if(m.id){pending.get(m.id)?.(m);pending.delete(m.id)}if(m.method==='Runtime.exceptionThrown')errors.push(m.params.exceptionDetails.text)};
const send=(method,params={})=>new Promise((resolve,reject)=>{const n=++id;pending.set(n,m=>m.error?reject(Error(JSON.stringify(m.error))):resolve(m.result));ws.send(JSON.stringify({id:n,method,params}))});
await send('Page.enable');await send('Runtime.enable');
const results=[];
for(const width of [1440,768,375]){
 await send('Emulation.setDeviceMetricsOverride',{width,height:900,deviceScaleFactor:1,mobile:width===375});
 for(const page of ['home','how','customers','shippers','pricing','auth','reset-password']){
 await send('Page.navigate',{url:'http://127.0.0.1:3031/?page='+page});
 await new Promise(r=>setTimeout(r,900));
 const evaluated=await send('Runtime.evaluate',{expression:'JSON.stringify({width:innerWidth,scroll:document.documentElement.scrollWidth,title:document.title})',returnByValue:true});
 const metrics=JSON.parse(evaluated.result.value);results.push({page,width,...metrics,result:metrics.scroll<=metrics.width+1?'PASS':'FAIL'});
 }
}
fs.writeFileSync('docs/audit-browser-results.json',JSON.stringify({results,runtimeExceptions:errors},null,2));
console.log(JSON.stringify({checks:results.length,failures:results.filter(r=>r.result==='FAIL'),runtimeExceptions:errors}));
await send('Browser.close');
})().catch(e=>{console.error(e.message);process.exit(1)});
