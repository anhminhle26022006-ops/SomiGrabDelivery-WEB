<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../app/Config/environment.php';
somi_load_env();
require_once __DIR__.'/../app/Services/SePayPaymentProvider.php';

// Configuration only: no database access, provider request, checkout or transfer.
$provider=new SePayPaymentProvider();$problems=$provider->configurationProblems();
foreach(['curl','bcmath','openssl']as $extension)if(!extension_loaded($extension))$problems['PHP_EXTENSION_'.$extension]='Enable this PHP extension.';
$url=getenv('SOMI_PUBLIC_URL')?:'';
$report=['configuration_ready'=>!$problems,'environment'=>isset($problems['SEPAY_ENVIRONMENT'])?'invalid':(getenv('SEPAY_ENVIRONMENT')?:'sandbox'),'problems'=>$problems,'ipn_url'=>isset($problems['SOMI_PUBLIC_URL'])?null:rtrim($url,'/').'/public/api/payments/callback.php','live_connection_verified'=>false,'external_requests'=>0];
echo json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL;
exit($problems?1:0);
