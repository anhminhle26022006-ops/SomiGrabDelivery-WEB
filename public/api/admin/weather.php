<?php
declare(strict_types=1);
require_once __DIR__.'/../_kernel.php';
require_once __DIR__.'/../../../app/Services/OpenMeteoWeatherService.php';
require_once __DIR__.'/../../../app/Services/AuthRateLimiter.php';
// Public city-centre reference point; no user GPS is collected for this panel.
somi_run_api(['GET'],['ADMIN'],'order.read_all',false,static function(PDO $db,int $admin):array{
    AuthRateLimiter::hit($db,'admin-weather:'.$admin,10,60);
    $weather=(new OpenMeteoWeatherService())->observe(['latitude'=>'10.7769000','longitude'=>'106.7009000'],new DateTimeImmutable('now',new DateTimeZone('UTC')));
    return [200,['weather'=>$weather,'location'=>['name'=>'Ho Chi Minh City','scope'=>'city_centre']]];
});
