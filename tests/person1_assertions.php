<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli' || getenv('SOMI_DB_NAME') !== 'somi_person1_test' || getenv('SOMI_DB_HOST') !== '127.0.0.1') exit('Test database required.');
require __DIR__.'/../app/Config/database.php';
require __DIR__.'/../app/Services/SessionGuard.php';
require __DIR__.'/../app/Repositories/Person1Repository.php';
$repo=new Person1Repository(somi_db());$f=json_decode(file_get_contents(__DIR__.'/.runtime/person1-fixtures.json'),true);$r=[];
function proof(string $name,bool $ok): void {global $r;$r[]=['test'=>$name,'result'=>$ok?'PASS':'FAIL'];}
foreach(['NEEDS_INFO','APPROVED','REJECTED'] as $decision){
    proof('KYC audit '.$decision,count($repo->all('SELECT 1 FROM audit_logs WHERE actor_id=:actor AND action=:action',['actor'=>$f['users']['admin']['id'],'action'=>'SHIPPER_APPLICATION_'.$decision]))>0);
}
proof('KYC rejected identity persisted',$repo->one('SELECT review_status FROM user_identity_documents WHERE user_id=:id',['id'=>$f['users']['shipper_reject']['id']])['review_status']==='REJECTED');
proof('KYC approved identity persisted',$repo->one('SELECT review_status FROM user_identity_documents WHERE user_id=:id',['id'=>$f['users']['shipper']['id']])['review_status']==='APPROVED');
proof('draft does not activate a real pricing policy',(int)$repo->one('SELECT count(*) n FROM pricing_policies')['n']===0);
proof('nationality rejection preserves user country',$repo->one('SELECT nationality_code FROM users WHERE user_id=:id',['id'=>$f['users']['customer']['id']])['nationality_code']==='VN');
proof('nationality review audited',count($repo->all("SELECT 1 FROM audit_logs WHERE actor_id=:id AND action='NATIONALITY_REQUEST_REJECTED'",['id'=>$f['users']['admin']['id']]))>0);
session_id(bin2hex(random_bytes(24)));session_start();
$_SESSION=['somi_user_id'=>$f['users']['customer']['id'],'somi_role'=>'customer','somi_authenticated_at'=>time()-2000,'somi_last_activity'=>time()-2000];
proof('expired session denied and cleared',!somi_require_active_session()&&$_SESSION===[]);
$_SESSION=['somi_user_id'=>$f['users']['customer']['id'],'somi_role'=>'customer','somi_last_activity'=>time()];
proof('missing issued timestamp denied',!somi_require_active_session());
$changed=$repo->one('SELECT password_changed_at FROM users WHERE user_id=:id',['id'=>$f['users']['other']['id']])['password_changed_at'];
$_SESSION=['somi_user_id'=>$f['users']['other']['id'],'somi_role'=>'customer','somi_authenticated_at'=>(float)(new DateTimeImmutable($changed))->format('U.u')-.000001,'somi_last_activity'=>time()];
proof('password reset revokes session with microsecond precision',!somi_require_active_session());
require __DIR__.'/../public/api/_person1.php';
$_SESSION=['somi_user_id'=>$f['users']['customer']['id'],'somi_role'=>'admin','somi_authenticated_at'=>microtime(true),'somi_last_activity'=>time()];
$denied=false;try{person1_identity('ADMIN','account.read_all');}catch(ApiProblem $e){$denied=$e->status===403;}
proof('forged session workspace never grants Admin role',$denied);
$_SESSION=['somi_user_id'=>$f['users']['admin']['id'],'somi_role'=>'admin','somi_authenticated_at'=>microtime(true),'somi_last_activity'=>time()];
$db=somi_db();$db->beginTransaction();
$repo->all("DELETE FROM role_permissions WHERE role_id=(SELECT role_id FROM roles WHERE role_code='ADMIN') AND permission_id=(SELECT permission_id FROM permissions WHERE permission_code='account.read_all')");
$denied=false;try{person1_identity('ADMIN','account.read_all');}catch(ApiProblem $e){$denied=$e->status===403;}
$db->rollBack();proof('permission revoked in DB denies API authorization',$denied);
session_destroy();
file_put_contents(__DIR__.'/../docs/person1-assertion-results.json',json_encode($r,JSON_PRETTY_PRINT));
$fail=count(array_filter($r,fn($x)=>$x['result']==='FAIL'));echo json_encode(['checks'=>count($r),'failures'=>$fail]);exit($fail?1:0);
