<?php
declare(strict_types=1);
require_once __DIR__.'/../_kernel.php';
require_once __DIR__.'/../../../app/Services/PaymentService.php';
somi_run_api(['POST'],['ADMIN'],'payment.reconcile',true,static function(PDO $db,int $admin):array{
    $in=somi_json_body();DeliveryInput::only($in,['bank_account_id','kind','payment_id','refund_id','bank_reference','original_payment_reference','evidence_reference','amount_minor','currency_code']);$service=new PaymentService($db);
    if(($in['kind']??null)==='PAYMENT')$result=$service->reconcileBank($admin,DeliveryInput::id($in['payment_id']??null),$in);
    elseif(($in['kind']??null)==='REFUND')$result=$service->reconcileBankRefund($admin,DeliveryInput::id($in['refund_id']??null),$in);
    else DeliveryInput::invalid('kind');return [200,$result];
});
