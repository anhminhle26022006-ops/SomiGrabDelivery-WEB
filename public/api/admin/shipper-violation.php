<?php
declare(strict_types=1);
require_once __DIR__.'/../_kernel.php';
require_once __DIR__.'/../../../app/Services/ShipperScoreService.php';
somi_run_api(['POST'],['ADMIN'],'shipper.verify',true,static fn(PDO $db,int $admin):array=>[200,(new ShipperScoreService($db))->violation($admin,somi_json_body())]);
