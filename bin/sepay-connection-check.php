<?php
declare(strict_types=1);
// Explicit network diagnostics only: GET provider API; malformed IPN fails before DB access.
// No checkout, valid payment event, reconciliation, refund, email or money movement.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../app/Config/environment.php';somi_load_env();
require_once __DIR__.'/../app/Services/SePayPaymentProvider.php';
$problems=(new SePayPaymentProvider())->configurationProblems();
if($problems){echo json_encode(['ready'=>false,'configuration_problems'=>$problems],JSON_PRETTY_PRINT).PHP_EOL;exit(1);}
function diagnosticRequest(string $url,string $method,array $headers=[],?string $body=null,bool $providerAuth=false):array {
    $response='';$curl=curl_init($url);
    curl_setopt_array($curl,[CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>30,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_WRITEFUNCTION=>static function($handle,string $chunk)use(&$response):int{if(strlen($response)+strlen($chunk)>65536)return 0;$response.=$chunk;return strlen($chunk);}]);
    if($providerAuth)curl_setopt_array($curl,[CURLOPT_HTTPAUTH=>CURLAUTH_BASIC,CURLOPT_USERPWD=>getenv('SEPAY_MERCHANT_ID').':'.getenv('SEPAY_SECRET_KEY')]);
    if($body!==null)curl_setopt($curl,CURLOPT_POSTFIELDS,$body);
    $ok=curl_exec($curl);$status=(int)curl_getinfo($curl,CURLINFO_HTTP_CODE);curl_close($curl);
    return ['transport_ok'=>$ok!==false,'status'=>$status,'json'=>json_decode($response,true)];
}
$environment=getenv('SEPAY_ENVIRONMENT')?:'sandbox';
$host=$environment==='production'?'https://pgapi.sepay.vn':'https://pgapi-sandbox.sepay.vn';
$api=diagnosticRequest($host.'/v1/order?per_page=1&page=1','GET',['Accept: application/json'],null,true);
$checks=['merchant_api'=>['http_status'=>$api['status'],'passed'=>$api['transport_ok']&&$api['status']===200&&is_array($api['json']['data']??null)]];
$callback=rtrim(getenv('SOMI_PUBLIC_URL'),'/').'/public/api/payments/callback.php';
foreach([
    'ipn_method_guard'=>['GET',[],null,405,'METHOD_NOT_ALLOWED'],
    'ipn_requires_secret'=>['POST',['Content-Type: application/json'],'{',401,'INVALID_CALLBACK_SIGNATURE'],
    'ipn_secret_matches_hosting'=>['POST',['Content-Type: application/json','X-Secret-Key: '.getenv('SEPAY_IPN_SECRET')],'{',400,'INVALID_JSON']
] as $name=>[$method,$headers,$body,$expected,$expectedCode]){
    $r=diagnosticRequest($callback,$method,$headers,$body);
    $code=$r['json']['error']['code']??$r['json']['code']??null;
    // Emit only recognized application codes, never provider bodies or arbitrary response content.
    $safeCode=is_string($code)&&preg_match('/^[A-Z_]{1,60}$/D',$code)?$code:null;
    $checks[$name]=['http_status'=>$r['status'],'application_code'=>$safeCode,'passed'=>$r['transport_ok']&&$r['status']===$expected&&$safeCode===$expectedCode];
}
$ready=true;foreach($checks as $check)if(!$check['passed'])$ready=false;
echo json_encode(['environment'=>$environment,'checks'=>$checks,'ready'=>$ready,'payment_created'=>false,'funds_moved'=>false,'valid_ipn_sent'=>false,'payment_roundtrip_verified'=>false],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL;
exit($ready?0:1);
