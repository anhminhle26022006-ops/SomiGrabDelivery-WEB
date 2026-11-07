<?php
declare(strict_types=1);
require_once __DIR__.'/_bootstrap.php';
require_once __DIR__.'/../../app/Services/SessionGuard.php';
require_once __DIR__.'/../../app/Services/AccessControl.php';

function somi_json_body(bool $nested=true,int $maxBytes=65536): array
{
    if (!preg_match('/^application\/json(?:\s*;\s*charset=utf-8)?$/iD',trim($_SERVER['CONTENT_TYPE']??''))) throw new DeliveryApiError(415,'JSON_REQUIRED','Content-Type must be application/json.');
    $body=file_get_contents('php://input',false,null,0,$maxBytes+1);
    if (strlen($body)>$maxBytes) throw new DeliveryApiError(413,'BODY_TOO_LARGE','JSON body is too large.');
    try{$data=json_decode($body,true,32,JSON_THROW_ON_ERROR);}catch(JsonException $e){throw new DeliveryApiError(400,'INVALID_JSON','Request body must be valid JSON.');}
    if(!is_array($data)||!str_starts_with(ltrim($body),'{'))throw new DeliveryApiError(400,'INVALID_JSON','Request body must be a JSON object.');
    if(!$nested)foreach($data as $value)if(is_array($value))throw new DeliveryApiError(422,'VALIDATION_ERROR','Fields must contain scalar values.');
    return $data;
}

function somi_wire(array $data): array
{
    foreach($data as $key=>&$value){
        if(is_array($value))$value=somi_wire($value);
        elseif($value!==null && is_string($key) && (str_ends_with($key,'_id')||str_ends_with($key,'_minor')))$value=(string)$value;
    }unset($value);return $data;
}

function somi_run_api(array $methods,array $roles,string $permission,bool $write,callable $handler,bool $repeatableRead=false,?callable $render=null): void
{
    $db=null;
    try{
        if(!in_array($_SERVER['REQUEST_METHOD']??'',$methods,true)){header('Allow: '.implode(', ',$methods));throw new DeliveryApiError(405,'METHOD_NOT_ALLOWED','Method not allowed.');}
        if($write&&!in_array($_SERVER['REQUEST_METHOD']??'',['GET','HEAD'],true)){$origin=$_SERVER['HTTP_ORIGIN']??null;if(($origin!==null&&!somi_origin_allowed($origin))||($origin===null&&($_SERVER['HTTP_SEC_FETCH_SITE']??'')!=='same-origin'))throw new DeliveryApiError(403,'ORIGIN_REQUIRED','A same-origin request is required.');}
        $id=$_SESSION['somi_user_id']??null;
        if(!is_int($id)||$id<1||empty($_SESSION['somi_authenticated_at']))throw new DeliveryApiError(401,'AUTHENTICATION_REQUIRED','Please sign in again.');
        $db=somi_db();$user=null;
        if(!somi_require_active_session($user))throw new DeliveryApiError(401,'AUTHENTICATION_REQUIRED','Please sign in again.');
        $role=strtoupper((string)($_SESSION['somi_role']??''));
        if(!in_array($role,$roles,true))throw new DeliveryApiError(403,$roles===['CUSTOMER']?'CUSTOMER_REQUIRED':'ROLE_REQUIRED','Switch to an authorized workspace.');
        AccessControl::requirePermission($db,$id,$role,$permission);
        if($role==='CUSTOMER'){$q=$db->prepare('SELECT 1 FROM customer_profiles WHERE user_id=?');$q->execute([$id]);if(!$q->fetchColumn())throw new DeliveryApiError(403,'CUSTOMER_REQUIRED','Customer profile required.');}
        session_write_close();
        if($write){$db->beginTransaction();if($repeatableRead)$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');}
        [$status,$payload]=$handler($db,$id,$role);
        if($write)$db->commit();
        if($render!==null){http_response_code($status);$render($payload);exit;}
        somi_api_json($status,somi_wire($payload));
    }catch(Throwable $e){
        if($db instanceof PDO&&$db->inTransaction())$db->rollBack();
        if($e instanceof DeliveryApiError)somi_api_json($e->httpStatus,['code'=>$e->errorCode,'error'=>$e->getMessage()]);
        if($e instanceof PDOException&&in_array((string)$e->getCode(),['40001','40P01','23505'],true))somi_api_json(409,['code'=>'CONCURRENT_CHANGE','error'=>'Concurrent change detected. Retry with the same idempotency key.']);
        error_log('[SOMI API] '.get_class($e).' code='.$e->getCode());
        somi_api_json(503,['code'=>'SERVICE_UNAVAILABLE','error'=>'Service is unavailable. Check migrations and configuration.']);
    }
}
