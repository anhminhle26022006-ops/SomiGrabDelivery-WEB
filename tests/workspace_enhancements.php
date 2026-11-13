<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
require_once __DIR__.'/../app/Config/database.php';
require_once __DIR__.'/../app/Services/WorkspaceWeatherService.php';
require_once __DIR__.'/../app/Services/AccountAvatarService.php';
require_once __DIR__.'/../app/Services/WorkspaceProfileSchema.php';
$checks=0;
function we_check(bool $ok,string $label):void{global $checks;$checks++;if(!$ok)throw new RuntimeException($label);}
function we_reject(callable $fn,string $code):void{try{$fn();}catch(DeliveryApiError $e){we_check($e->errorCode===$code,'Expected '.$code.', got '.$e->errorCode);return;}throw new RuntimeException('Expected '.$code);}
final class WorkspaceForecastFixture implements PricingHttpTransport {
    public array $query=[];public array $data;
    public function request(string $method,string $url,array $headers,?array $body,int $timeoutMs):array{parse_str(parse_url($url,PHP_URL_QUERY),$this->query);return $this->data;}
}
$now=new DateTimeImmutable('2026-11-13T07:15:00Z');$epoch=$now->getTimestamp();
$http=new WorkspaceForecastFixture();$http->data=['current'=>['time'=>$epoch,'weather_code'=>2,'temperature_2m'=>30,'apparent_temperature'=>33,'relative_humidity_2m'=>74,'wind_speed_10m'=>12],
    'hourly'=>['time'=>[$epoch-900,$epoch+2700,$epoch+6300,$epoch+9900],'temperature_2m'=>[30,29,28,27],'precipitation_probability'=>[15,null,75,65],'weather_code'=>[2,3,61,63]]];
putenv('OPEN_METEO_MODE=free');
$service=new WorkspaceWeatherService($http);$weather=$service->forecast('centre',$now);
we_check($weather['temperature_c']===30.0&&count($weather['hours'])===4,'Real provider fields normalized');
we_check($weather['hours'][1]['rain_probability']===null,'Missing rain probability is never invented');
we_check($http->query['latitude']==='10.7769'&&$http->query['longitude']==='106.7009','Provider only receives a fixed public area centre');
we_reject(fn()=>$service->forecast('10.123456,106.654321',$now),'VALIDATION_ERROR');
$http->data['current']['time']=$epoch-3600;we_reject(fn()=>$service->forecast('centre',$now),'WEATHER_UNAVAILABLE');
$png=base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aG1sAAAAASUVORK5CYII=');
we_check(AccountAvatarService::validate($png)==='image/png','Raster image accepted');
we_reject(fn()=>AccountAvatarService::validate('<svg onload="alert(1)"></svg>'),'AVATAR_TYPE_INVALID');
we_reject(fn()=>AccountAvatarService::validate(str_repeat('x',1048577)),'AVATAR_SIZE_INVALID');
$large=substr_replace($png,pack('N',2048),16,4);we_reject(fn()=>AccountAvatarService::validate($large),'AVATAR_DIMENSIONS_INVALID');
$db=somi_db();$db->exec("SET search_path TO pg_temp,pg_catalog; CREATE TEMP TABLE users(user_id BIGINT PRIMARY KEY); INSERT INTO users VALUES(1),(2); CREATE TEMP TABLE audit_logs(actor_id bigint,actor_kind text,action text,entity_type text,entity_id bigint,old_value jsonb,new_value jsonb)");
$db->beginTransaction();
try{
    WorkspaceProfileSchema::apply($db);
    we_check($db->query("SELECT relpersistence FROM pg_class WHERE oid='account_avatars'::regclass")->fetchColumn()==='t','All avatar writes use a TEMP table');
    $avatars=new AccountAvatarService($db);we_check($avatars->metadata(1)['avatar_url']===null,'New account has no fake avatar');
    $first=$avatars->save(1,'CUSTOMER',$png);we_check(str_contains($first['avatar_url'],'content=1&v='),'Upload returns a session-bound image endpoint');
    we_check($avatars->content(1)['bytes']===$png,'Stored image survives a separate read');
    we_check($avatars->metadata(2)['avatar_url']===null,'Other account cannot discover an avatar');we_reject(fn()=>$avatars->content(2),'AVATAR_NOT_FOUND');
    $next=$avatars->save(1,'SHIPPER',$png);we_check($next['avatar_url']!==$first['avatar_url'],'Same user can update avatar in either workspace');
    $avatars->remove(1,'SHIPPER');we_check($avatars->metadata(1)['avatar_url']===null,'Remove persists');
    we_check((int)$db->query('SELECT count(*) FROM audit_logs')->fetchColumn()===3,'Uploads and removal audited');
    $db->rollBack();
}catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
echo "PASS: $checks weather/avatar checks; mocked weather and TEMP tables only.\n";
