<?php
declare(strict_types=1);
require_once __DIR__.'/../_kernel.php';
require_once __DIR__.'/../../../app/Services/GeoapifyAddressService.php';
require_once __DIR__.'/../../../app/Services/AuthRateLimiter.php';
// Shared geocoding/routing, with no database transaction during external I/O.
$origin=$_SERVER['HTTP_ORIGIN']??null;
if(($_SERVER['REQUEST_METHOD']??'')==='POST'&&(($origin!==null&&!somi_origin_allowed($origin))||($origin===null&&($_SERVER['HTTP_SEC_FETCH_SITE']??'')!=='same-origin')))somi_api_json(403,['code'=>'ORIGIN_REQUIRED','error'=>'A same-origin request is required.']);
somi_run_api(['POST'],['CUSTOMER','SHIPPER'],'person3.use',false,function(PDO $db,int $user):array {
    $in=somi_json_body();AuthRateLimiter::hit($db,'person3-geo:'.$user,60,60);return [200,(new GeoapifyAddressService())->resolve($in)];
});
