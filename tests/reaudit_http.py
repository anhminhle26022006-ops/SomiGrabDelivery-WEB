import json,time,urllib.request,urllib.error
from pathlib import Path
BASE='http://127.0.0.1:3033'
fixtures=json.loads(Path('tests/.runtime/fixtures.json').read_text(encoding='utf-8-sig'))
class NoRedirect(urllib.request.HTTPRedirectHandler):
 def redirect_request(self,*args):return None
client=urllib.request.build_opener(NoRedirect)
results=[]
def check(role,path,expected):
 req=urllib.request.Request(BASE+path,headers={'Cookie':'PHPSESSID='+fixtures[role]})
 start=time.perf_counter()
 try:response=client.open(req)
 except urllib.error.HTTPError as e:response=e
 body=response.read().decode();ok=response.code==expected and 'Fatal error' not in body and 'Warning:' not in body
 results.append({'role':role,'path':path,'status':response.code,'expected':expected,'elapsed_ms':round((time.perf_counter()-start)*1000),'result':'PASS' if ok else 'FAIL','shipper_pending':'Shipper application' in body})
views={'customer':['dashboard','create','orders','order','payment','matching','tracking','review','report','reports','notifications','account','addresses'], 'shipper':['dashboard','offers','detail','active','history','earnings','account','registration','chat','support','cancel','withdraw','report','notifications'], 'admin':['index','users','customers','shippers','orders','complaints','disputes','payments','reviews','notifications','audit-logs']}
for role in fixtures:
 for view in views[role]:check(role,'/?page='+role+'&view='+view,200)
 for target in views:
  if target!=role:check(role,'/?page='+target,303)
 check(role,'/?page='+role+'&view[]=bad',200)
Path('docs/reaudit-http-results.json').write_text(json.dumps(results,indent=2))
print(json.dumps({'checks':len(results),'failures':[r for r in results if r['result']=='FAIL'],'shipper_pending_responses':sum(r['shipper_pending'] for r in results)}))
