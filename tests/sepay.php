<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
require_once __DIR__.'/../app/Services/SePayPaymentProvider.php';
$names=['SEPAY_ENVIRONMENT','SEPAY_MERCHANT_ID','SEPAY_SECRET_KEY','SEPAY_IPN_SECRET','SOMI_PUBLIC_URL'];
$original=[];foreach($names as $name)$original[$name]=getenv($name);
$checks=0;
function check(bool $ok,string $label):void{global $checks;$checks++;if(!$ok)throw new RuntimeException('FAILED: '.$label);}
function rejects(callable $fn,string $code):void{try{$fn();}catch(DeliveryApiError $e){check($e->errorCode===$code,$code);return;}throw new RuntimeException('Expected '.$code);}
function fixture():void{foreach(['SEPAY_ENVIRONMENT'=>'sandbox','SEPAY_MERCHANT_ID'=>'FIXTURE_MERCHANT','SEPAY_SECRET_KEY'=>'spsk_test_fixture_not_a_real_key','SEPAY_IPN_SECRET'=>str_repeat('i',32),'SOMI_PUBLIC_URL'=>'https://app.example/somi/']as $name=>$value)putenv($name.'='.$value);}
try{
    fixture();$provider=new SePayPaymentProvider();check(!$provider->configurationProblems(),'valid sandbox configuration');
    foreach($names as $name){fixture();putenv($name.'=');if($name==='SEPAY_ENVIRONMENT'){check(!$provider->configurationProblems(),'default mode is sandbox');continue;}check(isset($provider->configurationProblems()[$name]),'missing '.$name);}
    foreach(['http://app.example','https://app.example?secret=value','https://app.example/#fragment','https://user:password@app.example','https://']as $url){fixture();putenv('SOMI_PUBLIC_URL='.$url);check(isset($provider->configurationProblems()['SOMI_PUBLIC_URL']),'invalid application base URL');}
    fixture();putenv('SEPAY_ENVIRONMENT=production');check(isset($provider->configurationProblems()['SEPAY_SECRET_KEY']),'test key cannot use production');
    fixture();putenv('SEPAY_SECRET_KEY=spsk_live_fixture');check(isset($provider->configurationProblems()['SEPAY_SECRET_KEY']),'live key cannot use sandbox');
    fixture();putenv('SEPAY_SECRET_KEY='.str_repeat('s',32));putenv('SEPAY_IPN_SECRET='.getenv('SEPAY_SECRET_KEY'));check(isset($provider->configurationProblems()['SEPAY_IPN_SECRET']),'independent IPN secret');
    fixture();putenv("SEPAY_SECRET_KEY=invalid\r\nheader");check(isset($provider->configurationProblems()['SEPAY_SECRET_KEY']),'invalid secret control characters');
    fixture();$payment=['payment_id'=>'7','order_id'=>'9','amount_minor'=>'100000','currency_code'=>'VND','method'=>'CARD'];
    $checkout=$provider->checkout($payment,'fixture');check($checkout['checkout_url']==='https://app.example/somi/public/api/customer/checkout.php?order_id=9','deployment subdirectory preserved');
    $form=$provider->form($payment,'en');$return='https://app.example/somi/?page=customer&view=payment&id=9';
    $canonical='order_amount=100000,merchant=FIXTURE_MERCHANT,currency=VND,operation=PURCHASE,order_description=SOMI order 9,order_invoice_number=SOMI-TEST-P7,payment_method=CARD,success_url='.$return.',error_url='.$return.',cancel_url='.$return;
    check($form['fields']['signature']===base64_encode(hash_hmac('sha256',$canonical,getenv('SEPAY_SECRET_KEY'),true)),'documented signature order');
    check($form['action']==='https://pay-sandbox.sepay.vn/v1/checkout/init'&&$form['fields']['lang']==='en','sandbox checkout and English');
    check($provider->form($payment,'vi')['fields']['signature']===$form['fields']['signature'],'language is not signed');
    foreach(['GATEWAY','CARD','BANK_TRANSFER']as $method){$p=array_replace($payment,['method'=>$method]);$fields=$provider->form($p,'vi')['fields'];check($fields['payment_method']===($method==='GATEWAY'?'BANK_TRANSFER':$method),'method '.$method);}
    $qr=$provider->form(array_replace($payment,['method'=>'GATEWAY']),'vi')['fields'];
    $qrCanonical=str_replace('payment_method=CARD','payment_method=BANK_TRANSFER',$canonical);
    check($qr['signature']===base64_encode(hash_hmac('sha256',$qrCanonical,getenv('SEPAY_SECRET_KEY'),true)),'QR choice is included in the signed checkout fields');
    rejects(fn()=>$provider->checkout(array_replace($payment,['currency_code'=>'USD']),'fixture'),'PAYMENT_CURRENCY_UNSUPPORTED');
    $event=['notification_type'=>'ORDER_PAID','order'=>['order_invoice_number'=>'SOMI-TEST-P7','order_status'=>'CAPTURED','order_currency'=>'VND','order_amount'=>'100000.00'],'transaction'=>['id'=>'fixture-transaction','transaction_type'=>'PAYMENT','transaction_status'=>'APPROVED','transaction_currency'=>'VND','transaction_amount'=>'100000']];
    $headers=['X-Secret-Key'=>str_repeat('i',32)];$raw=json_encode($event,JSON_THROW_ON_ERROR);
    $verified=$provider->verifyCallback($raw,$headers);check($verified['payment_id']==='7'&&$verified['amount_minor']==='100000'&&$verified['status']==='PAID','verified IPN mapping without database');
    rejects(fn()=>$provider->verifyCallback($raw,[]),'INVALID_CALLBACK_SIGNATURE');rejects(fn()=>$provider->verifyCallback($raw,['X-Secret-Key'=>'wrong']),'INVALID_CALLBACK_SIGNATURE');rejects(fn()=>$provider->verifyCallback('{',$headers),'INVALID_JSON');rejects(fn()=>$provider->verifyCallback('[]',$headers),'UNSUPPORTED_PAYMENT_EVENT');
    foreach([['order','order_invoice_number','SOMI-LIVE-P7'],['order','order_invoice_number',[]],['order','order_status','PENDING'],['order','order_currency','USD'],['transaction','transaction_status','DECLINED'],['transaction','transaction_type','REFUND'],['transaction','transaction_currency','USD'],['transaction','transaction_amount','99999'],['order','order_amount','100000.50'],['order','order_amount','1e5'],['order','order_amount','9223372036854775808']]as [$object,$key,$value]){$invalid=$event;$invalid[$object][$key]=$value;rejects(fn()=>$provider->verifyCallback(json_encode($invalid,JSON_THROW_ON_ERROR),$headers),'PAYMENT_MISMATCH');}
    fixture();putenv('SEPAY_ENVIRONMENT=production');putenv('SEPAY_SECRET_KEY=spsk_live_fixture');check($provider->form($payment,'vi')['action']==='https://pay.sepay.vn/v1/checkout/init','production host requires matching configuration');
    echo json_encode(['result'=>'PASS','checks'=>$checks,'external_requests'=>0,'database_writes'=>0],JSON_PRETTY_PRINT).PHP_EOL;
}catch(Throwable $e){fwrite(STDERR,get_class($e).': '.$e->getMessage().PHP_EOL);exit(1);}
finally{foreach($original as $name=>$value)putenv($value===false?$name:$name.'='.$value);}
