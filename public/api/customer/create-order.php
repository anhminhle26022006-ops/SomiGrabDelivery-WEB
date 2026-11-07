<?php
declare(strict_types=1);
require_once __DIR__.'/../_bootstrap.php';
if(($_SERVER['REQUEST_METHOD']??'')!=='POST'){
    header('Allow: POST');somi_api_json(405,['code'=>'METHOD_NOT_ALLOWED','error'=>'Method not allowed.']);
}
require __DIR__.'/orders.php';
