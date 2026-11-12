"""Real password login test; credentials come only from process environment.
SOMI_TEST_IDENTIFIER / SOMI_TEST_PASSWORD / SOMI_TEST_ROLE are required.
Writes only normal login metadata; logs out at the end. Never prints credentials.
"""
import os,json,time,http.cookiejar,urllib.request,urllib.error,sys
from pathlib import Path
keys=['SOMI_TEST_IDENTIFIER','SOMI_TEST_PASSWORD','SOMI_TEST_ROLE']
if not all(os.environ.get(k) for k in keys):
 print('BLOCKED: configure SOMI_TEST_IDENTIFIER, SOMI_TEST_PASSWORD, SOMI_TEST_ROLE');sys.exit(2)
base=os.environ.get('SOMI_TEST_BASE_URL','http://127.0.0.1:3033').rstrip('/')
role=os.environ['SOMI_TEST_ROLE'].lower()
if role not in ['customer','shipper','admin']:raise SystemExit('Invalid test role')
class NoRedirect(urllib.request.HTTPRedirectHandler):
 def redirect_request(self,*args):return None
client=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()),NoRedirect)
def request(path,body=None):
 req=urllib.request.Request(base+path,data=None if body is None else json.dumps(body).encode(),headers={'Content-Type':'application/json','Origin':base})
 try:response=client.open(req,timeout=20)
 except urllib.error.HTTPError as e:response=e
 return response.code,response.read().decode()
results=[]
try:
 start=time.perf_counter();code,body=request('/public/api/auth/login.php',{'identifier':os.environ[keys[0]],'password':os.environ[keys[1]],'role':role})
 try:payload=json.loads(body)
 except ValueError:payload={}
 signed=code==200 and payload.get('status')=='SIGNED_IN' and payload.get('role')==role
 results.append({'test':'real password login','role':role,'http':code,'elapsed_ms':round((time.perf_counter()-start)*1000),'result':'PASS' if signed else 'FAIL'})
 if signed:
  code,body=request('/?page='+role);results.append({'test':'workspace after password login','http':code,'result':'PASS' if code==200 and 'Fatal error' not in body else 'FAIL'})
finally:
 code,_=request('/public/api/auth/logout.php',{});results.append({'test':'logout','http':code,'result':'PASS' if code==200 else 'FAIL'})
code,_=request('/?page='+role);results.append({'test':'protected route after logout','http':code,'result':'PASS' if code==303 else 'FAIL'})
Path('docs/reaudit-password-'+role+'.json').write_text(json.dumps(results,indent=2));print(json.dumps(results));sys.exit(any(r['result']=='FAIL' for r in results))
