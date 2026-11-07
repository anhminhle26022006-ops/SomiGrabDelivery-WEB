<?php
declare(strict_types=1);
require_once __DIR__.'/../_kernel.php';
$method=$_SERVER['REQUEST_METHOD']??'';
somi_run_api(['GET','PUT'],['CUSTOMER','SHIPPER','ADMIN'],$method==='GET'?'profile.read_own':'profile.update_own',$method==='PUT',static function(PDO $db,int $id,string $role)use($method):array{
    if($method==='PUT'){$in=somi_json_body();DeliveryInput::only($in,['full_name']);$name=DeliveryInput::text($in['full_name']??null,'full_name',150);$db->prepare('UPDATE users SET full_name=?,updated_at=now() WHERE user_id=?')->execute([$name,$id]);AuditService::record($db,$id,$role,'PROFILE_UPDATED','USER',$id,[],['full_name'=>$name]);}
    $q=$db->prepare('SELECT user_id,full_name,email,phone,nationality_code,app_language,account_status,email_verified_at,phone_verified_at FROM users WHERE user_id=?');$q->execute([$id]);return [200,['profile'=>$q->fetch()]];
});
