<?php
declare(strict_types=1);
require_once __DIR__.'/../_kernel.php';
require_once __DIR__.'/../../../app/Services/ShipperFinanceService.php';
somi_run_api(['GET'],['SHIPPER'],'person3.use',false,fn(PDO $db,int $id)=>[200,(new ShipperFinanceService($db))->listing($id,$_GET)]);
