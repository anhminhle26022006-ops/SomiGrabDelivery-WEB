<?php
declare(strict_types=1);
require_once __DIR__.'/../_kernel.php';
require_once __DIR__.'/../../../app/Services/PaymentService.php';
somi_run_api(['POST'],['ADMIN'],'payment.reconcile',true,static function(PDO $db,int $admin):array{
    $in=somi_json_body();DeliveryInput::only($in,['refund_id','provider_refund_id','original_payment_reference','original_method_confirmed','evidence_reference','amount_minor','currency_code']);
    return [200,(new PaymentService($db))->reconcileProviderRefund($admin,DeliveryInput::id($in['refund_id']??null),$in)];
});
