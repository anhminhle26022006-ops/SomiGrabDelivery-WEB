<?php
declare(strict_types=1);
require_once __DIR__.'/../_bootstrap.php';
require_once __DIR__.'/../../../app/Services/PaymentService.php';
$db=null;
try{
    if(($_SERVER['REQUEST_METHOD']??'')!=='POST'){header('Allow: POST');throw new DeliveryApiError(405,'METHOD_NOT_ALLOWED','Method not allowed.');}
    $raw=file_get_contents('php://input',false,null,0,65537);if(strlen($raw)>65536)throw new DeliveryApiError(413,'BODY_TOO_LARGE','Callback is too large.');
    $provider=new SePayPaymentProvider();$event=$provider->verifyCallback($raw,['x-secret-key'=>$_SERVER['HTTP_X_SECRET_KEY']??'']);
    session_write_close();$db=somi_db();$db->beginTransaction();$result=(new PaymentService($db,$provider))->applyVerified($event,$provider->name());$db->commit();somi_api_json(200,['success'=>true]+$result);
}catch(Throwable $e){if($db instanceof PDO)somi_rollback_safely($db);somi_api_handle_error($e);}
