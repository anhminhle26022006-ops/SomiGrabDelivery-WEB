<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
require_once __DIR__.'/../app/Config/environment.php';
require_once __DIR__.'/../app/Services/OpenMeteoWeatherService.php';
somi_load_env();
try {
    $weather=(new OpenMeteoWeatherService())->current(['latitude'=>'10.7769000','longitude'=>'106.7009000'],new DateTimeImmutable('now',new DateTimeZone('UTC')));
    echo json_encode(['result'=>'PASS','location'=>'Ho Chi Minh City','weather'=>$weather],JSON_PRETTY_PRINT).PHP_EOL;
} catch(DeliveryApiError $e) {
    echo json_encode(['result'=>$e->errorCode==='WEATHER_SERVICE_PAUSED'?'CONNECTED_UNSAFE_WEATHER':'FAILED','code'=>$e->errorCode]).PHP_EOL;
    exit($e->errorCode==='WEATHER_SERVICE_PAUSED'?0:1);
}
