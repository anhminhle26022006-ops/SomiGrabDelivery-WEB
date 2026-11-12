<?php
declare(strict_types=1);
require_once __DIR__.'/../_kernel.php';
require_once __DIR__.'/../../../app/Services/ShipperCancellationService.php';
somi_run_api(['GET','POST'],['ADMIN'],'order.cancel_admin',true,static function(PDO $db,int $admin):array{
    $service=new ShipperCancellationService($db);
    return [200,($_SERVER['REQUEST_METHOD']==='POST')?$service->review($admin,somi_json_body()):$service->listing($admin,$_GET)];
});
