<?php
declare(strict_types=1);
require_once __DIR__.'/../_kernel.php';
require_once __DIR__.'/../../../app/Services/WorkspaceWeatherService.php';
require_once __DIR__.'/../../../app/Services/AuthRateLimiter.php';
somi_run_api(['GET'],['CUSTOMER','SHIPPER'],'profile.read_own',false,static function(PDO $db,int $user):array{
    AuthRateLimiter::hit($db,'workspace-weather:'.$user,20,60);
    DeliveryInput::only($_GET,['area']);$area=$_GET['area']??'centre';
    if (!is_string($area)||!isset(WorkspaceWeatherService::AREAS[$area])) DeliveryInput::invalid('area');
    $location=WorkspaceWeatherService::AREAS[$area];
    return [200,['weather'=>(new WorkspaceWeatherService())->cached($area),'location'=>['name_vi'=>$location['name_vi'],'name_en'=>$location['name_en']]]];
});
