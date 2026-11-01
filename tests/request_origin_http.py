"""Non-mutating API checks for HTTPS termination; start local PHP on 3033."""
import json,urllib.request,urllib.error
cases=[('proxy same-origin','https://somi.invalid','same-origin',422,True),('proxy without metadata','https://somi.invalid','',403,False),('foreign domain','https://evil.invalid','same-origin',403,False),('foreign port','https://somi.invalid:8443','same-origin',403,False),('cross-site metadata','https://somi.invalid','cross-site',403,False),('direct http','http://somi.invalid','same-origin',422,False)]
results=[]
for name,origin,site,expected,secure in cases:
 headers={'Content-Type':'application/json','Host':'somi.invalid','Origin':origin}
 if site:headers['Sec-Fetch-Site']=site
 req=urllib.request.Request('http://127.0.0.1:3033/public/api/auth/login.php',data=b'{}',headers=headers)
 try:response=urllib.request.urlopen(req)
 except urllib.error.HTTPError as error:response=error
 response.read();cookie=response.headers.get('Set-Cookie','')
 ok=response.code==expected and (not secure or 'secure' in cookie.lower())
 results.append({'test':name,'http':response.code,'result':'PASS' if ok else 'FAIL'})
print(json.dumps(results,indent=2))
raise SystemExit(any(row['result']=='FAIL' for row in results))
