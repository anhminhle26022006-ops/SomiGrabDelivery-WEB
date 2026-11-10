<?php
declare(strict_types=1);
require_once __DIR__.'/_kernel.php';
require_once __DIR__.'/../../app/Services/InteractionService.php';
somi_run_api(['GET','POST'],['CUSTOMER','SHIPPER'],'person3.use',true,function(PDO $db,int $id,string $role):array {
    $resource=$_GET['resource']??'';$write=$_SERVER['REQUEST_METHOD']==='POST';$in=$write?somi_json_body(true):$_GET;
    $service=new InteractionService($db);
    $result=match($resource){'orders'=>!$write?$service->orders($id,$role,$in):throw new DeliveryApiError(405,'METHOD_NOT_ALLOWED','Read only.'),'chat'=>$service->chat($id,$role,$in,$write),'tickets'=>$service->tickets($id,$role,$in,$write),'notifications'=>$service->notifications($id,$in,$write),default=>throw new DeliveryApiError(404,'RESOURCE_NOT_FOUND','Unknown resource.')};
    return [200,$result];
});
