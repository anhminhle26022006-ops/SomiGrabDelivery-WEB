<?php
declare(strict_types=1);
require_once __DIR__.'/../_kernel.php';
require_once __DIR__.'/../../../app/Services/AdminService.php';
$method=$_SERVER['REQUEST_METHOD']??'';
somi_run_api(['GET','POST'],['ADMIN'],$method==='POST'?'account.suspend':'account.read',true,static function(PDO $db,int $admin)use($method):array{
    $service=new AdminService($db);
    if($method==='GET'){
        if(isset($_GET['id'])){
            $id=DeliveryInput::id($_GET['id']);$q=$db->prepare('SELECT user_id,full_name,email,phone,nationality_code,app_language,account_status,email_verified_at,phone_verified_at,created_at FROM users WHERE user_id=?');$q->execute([$id]);$user=$q->fetch();if(!$user)throw new DeliveryApiError(404,'ACCOUNT_NOT_FOUND','Account not found.');
            $q=$db->prepare('SELECT old_status,new_status,reason,changed_by,created_at FROM account_status_history WHERE user_id=? ORDER BY history_id DESC LIMIT 100');$q->execute([$id]);$user['status_history']=$q->fetchAll();AuditService::record($db,$admin,'ADMIN','ACCOUNT_READ','USER',$id);return [200,['account'=>$user]];
        }
        AuditService::record($db,$admin,'ADMIN','ACCOUNTS_SEARCH','USER',null);return [200,['items'=>$service->users($admin,$_GET)]];
    }
    $in=somi_json_body();DeliveryInput::only($in,['user_id','account_status','reason']);return [200,['account'=>$service->accountStatus($admin,DeliveryInput::id($in['user_id']??null),DeliveryInput::text($in['account_status']??null,'account_status',20),DeliveryInput::text($in['reason']??null,'reason',1000))]];
});
