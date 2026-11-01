"""Read-only HTTP checks. Start php -S 127.0.0.1:3031 index.php first."""
import urllib.request, urllib.error, json, sys
BASE = (sys.argv[1] if len(sys.argv) > 1 else 'http://127.0.0.1:3031').rstrip('/')
class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *args): return None
client = urllib.request.build_opener(NoRedirect)
results = []
def check(path, expected, method='GET', body=None, headers=None):
    req = urllib.request.Request(BASE + path, data=body, method=method, headers=headers or {})
    try: response = client.open(req)
    except urllib.error.HTTPError as error: response = error
    payload = response.read()
    ok = response.code == expected and b'Fatal error' not in payload and b'Warning:' not in payload
    results.append({'path': path, 'method': method, 'expected': expected, 'actual': response.code, 'result': 'PASS' if ok else 'FAIL'})
for page in ['home','how','customers','shippers','pricing','auth','reset-password']:
    check('/?page='+page,200)
for page in ['customer','shipper','admin']:
    check('/?page='+page,303)
check('/admin',303)
check('/admin/',303)
check('/?page=unknown',404)
for path in ['/database/SOMI_database.sql','/app/Views/admin/users.php','/.git/config','/README.md','/public/api/_bootstrap.php','/missing']:
    check(path,404)
check('/public/assets/js/app.js',200)
check('/public/api/auth/login.php',405)
check('/public/api/auth/login.php',415,'POST',b'{}')
headers={'Content-Type':'application/json'}
check('/public/api/auth/login.php',400,'POST',b'{',headers)
check('/public/api/auth/login.php',422,'POST',b'{}',headers)
check('/public/api/auth/switch-workspace.php',401,'POST',b'{}',headers)
check('/public/api/admin/review-shipper.php',401,'POST',b'{}',headers)
check('/public/api/auth/logout.php',403,'POST',b'{}',dict(headers,Origin='https://example.invalid'))
check('/public/api/auth/logout.php',200,'POST',b'{}',headers)
print(json.dumps(results,indent=2))
sys.exit(any(row['result']=='FAIL' for row in results))
