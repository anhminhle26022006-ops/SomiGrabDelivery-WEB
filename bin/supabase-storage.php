<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../app/Config/environment.php';somi_load_env();
require_once __DIR__.'/../app/Services/SupabaseStorage.php';
try{$result=(new SupabaseStorage())->checkBucket(in_array('--init',$argv,true));echo json_encode($result,JSON_PRETTY_PRINT).PHP_EOL;}catch(DeliveryApiError $e){fwrite(STDERR,$e->errorCode.': '.$e->getMessage().PHP_EOL);exit(1);}
