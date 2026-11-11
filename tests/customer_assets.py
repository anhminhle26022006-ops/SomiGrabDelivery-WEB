"""Render actual Customer templates to verify independent local assets and safe profile bootstrap."""
import json,os,re,subprocess
php=os.environ.get('SOMI_TEST_PHP','C:/xampp/php/php.exe')
checks=0
for view in ['orders','dashboard','account','create','addresses']:
 code="""function e($v){return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');} function assetUrl($p){return $p;} $_GET['view']=$argv[1];$sessionUserId=1;$sessionUser=['user_id'=>1,'full_name'=>'</script><img src=x onerror=alert(1)>','app_language'=>'vi','password_changed_at'=>'sensitive-test','has_permission'=>true];require 'app/Views/customer/index.php';"""
 result=subprocess.run([php,'-r',code,view],capture_output=True,text=True,encoding='utf-8',timeout=10)
 assert result.returncode==0,result.stderr
 html=result.stdout
 assert 'cdn.jsdelivr.net' not in html and 'unpkg.com' not in html,(view,'CDN dependency');checks+=1
 assert 'public/assets/vendor/bootstrap/bootstrap.bundle.min.js' in html,(view,'local bootstrap');checks+=1
 assert 'public/assets/vendor/bootstrap/bootstrap.min.css' in html,(view,'local bootstrap styles');checks+=1
 assert ('public/assets/vendor/leaflet/leaflet.js' in html)==(view in ['create','addresses']),(view,'conditional map');checks+=1
 match=re.search(r'<script type="application/json" id="customer-profile-data">(.*?)</script>',html,re.S)
 assert match and '<img' not in match[1],(view,'bootstrap escaping');checks+=1
 profile=json.loads(match[1])['profile']
 assert profile['full_name'].startswith('</script>') and 'has_permission' not in profile and 'password_changed_at' not in profile,(view,'profile whitelist');checks+=1
print(json.dumps({'checks':checks,'result':'PASS','database_writes':0,'provider_requests':0}))
