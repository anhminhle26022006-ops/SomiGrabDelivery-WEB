<?php
declare(strict_types=1);
require_once __DIR__.'/../_kernel.php';
require_once __DIR__.'/../../../app/Services/PaymentService.php';
somi_run_api(['GET'],['CUSTOMER'],'order.read_own',false,static function(PDO $db,int $user):array{
    $orderId=DeliveryInput::id($_GET['order_id']??null,'order_id');
    $q=$db->prepare('SELECT p.*,u.app_language,o.status AS order_status FROM payments p JOIN orders o USING(order_id) JOIN users u ON u.user_id=o.customer_id WHERE p.order_id=? AND o.customer_id=?');$q->execute([$orderId,$user]);$p=$q->fetch();
    if(!$p||$p['provider']!=='SEPAY'||$p['order_status']!=='PENDING_PAYMENT'||!in_array($p['status'],['PENDING','PROCESSING'],true))throw new DeliveryApiError(409,'ORDER_NOT_PAYABLE','Checkout is no longer available.');
    return [200,(new SePayPaymentProvider())->form($p,$p['app_language'])+['language'=>$p['app_language']]];
},false,static function(array $form):void{
    header('Content-Type: text/html; charset=utf-8');header('Cache-Control: no-store');header('Referrer-Policy: no-referrer');header("Content-Security-Policy: default-src 'none'; form-action https://pay.sepay.vn https://pay-sandbox.sepay.vn; frame-ancestors 'none'; base-uri 'none'");
    $escape=static fn($s)=>htmlspecialchars((string)$s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');$vi=$form['language']==='vi';
    echo '<!doctype html><html lang="'.($vi?'vi':'en').'"><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>SOMI · SePay</title><h1>'.($vi?'Thanh toán qua SePay':'Pay with SePay').'</h1><form method="post" action="'.$escape($form['action']).'">';
    foreach($form['fields'] as $name=>$value)echo '<input type="hidden" name="'.$escape($name).'" value="'.$escape($value).'">';
    echo '<button type="submit">'.($vi?'Tiếp tục thanh toán':'Continue to payment').'</button></form></html>';
});
