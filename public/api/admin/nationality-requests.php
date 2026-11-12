<?php
declare(strict_types=1);
require_once __DIR__.'/../_kernel.php';
require_once __DIR__.'/../../../app/Services/AdminService.php';
$method=$_SERVER['REQUEST_METHOD']??'';
somi_run_api(['GET','POST'],['ADMIN'],'account.nationality_review',$method==='POST',static function(PDO $db,int $admin)use($method):array{
    if($method==='GET')return [200,['items'=>$db->query('SELECT request_id,user_id,nationality_code,reason,status,reviewed_by,review_note,created_at,reviewed_at FROM nationality_change_requests ORDER BY request_id DESC LIMIT 100')->fetchAll()]];
    $in=somi_json_body();DeliveryInput::only($in,['request_id','decision','note']);return [200,(new AdminService($db))->reviewNationality($admin,DeliveryInput::id($in['request_id']??null),DeliveryInput::text($in['decision']??null,'decision',20),DeliveryInput::text($in['note']??null,'note',1000))];
});
