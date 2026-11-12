<?php
declare(strict_types=1);
require_once __DIR__.'/_shipper.php';
require_once __DIR__.'/../../../app/Services/ShipperCancellationService.php';
shipper_run(['POST'],'delivery.update_assigned',true,static fn(PDO $db,int $shipper):array=>[200,(new ShipperCancellationService($db))->request($shipper,somi_json_body())]);
