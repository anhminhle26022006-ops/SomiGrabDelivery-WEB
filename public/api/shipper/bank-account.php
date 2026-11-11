<?php
declare(strict_types=1);
require_once __DIR__.'/../_kernel.php';
require_once __DIR__.'/../../../app/Services/ShipperBankService.php';
somi_run_api(['GET','POST'],['SHIPPER'],'person3.use',true,function(PDO $db,int $id):array {
    $service=new ShipperBankService($db);if($_SERVER['REQUEST_METHOD']==='GET')return [200,$service->current($id)];
    $in=somi_json_body(false);$action=$in['action']??'';unset($in['action']);
    $result=match($action){'REQUEST'=>$service->request($id,$in),'CONFIRM'=>$service->confirm($id,$in),default=>throw new DeliveryApiError(422,'ACTION_INVALID','Use REQUEST or CONFIRM.')};
    $status=isset($result['error'])?(in_array($result['code'],['RATE_LIMITED','OTP_COOLDOWN','OTP_LIMIT'],true)?429:422):200;
    return [$status,$result];
});
