<?php
declare(strict_types=1);
require_once __DIR__.'/../_delivery.php';
require_once __DIR__.'/../../../app/Services/CustomerDeliveryReceiptService.php';
delivery_run(['GET'],'order.read_own',false,static function(PDO $db,int $owner):array{
    return [200,(new CustomerDeliveryReceiptService($db))->detail($owner,delivery_query_id('order_id'))];
});
