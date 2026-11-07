<?php
declare(strict_types=1);
require_once __DIR__.'/../_kernel.php';
require_once __DIR__.'/../../../app/Services/AdminService.php';
$method=$_SERVER['REQUEST_METHOD']??'';
somi_run_api(['GET','POST'],['CUSTOMER','SHIPPER'],'profile.nationality_request',$method==='POST',static function(PDO $db,int $id)use($method):array{
    if($method==='GET'){$q=$db->prepare('SELECT *,nationality_code AS requested_country_code FROM nationality_change_requests WHERE user_id=? ORDER BY request_id DESC LIMIT 100');$q->execute([$id]);return [200,['data'=>$q->fetchAll()]];}
    $in=somi_json_body();DeliveryInput::only($in,['requested_country_code','reason']);return [201,['data'=>(new AdminService($db))->requestNationality($id,DeliveryInput::text($in['requested_country_code']??null,'requested_country_code',2),DeliveryInput::text($in['reason']??null,'reason',1000))]];
});
