<?php
declare(strict_types=1);
require_once __DIR__.'/../app/Services/OpenMeteoWeatherService.php';
$checks=0;
function check(bool $ok,string $label):void{global $checks;$checks++;if(!$ok)throw new RuntimeException($label);}
function rejects(callable $f,string $code):void{try{$f();}catch(DeliveryApiError $e){check($e->errorCode===$code,$code);return;}throw new RuntimeException('Expected '.$code);}
final class WeatherFixture implements PricingHttpTransport {
 public array $requests=[];public int $age=900;public $code=61;public bool $broken=false;
 public function request(string $method,string $url,array $headers,?array $body,int $timeoutMs):array{
  $this->requests[]=compact('method','url','timeoutMs');if($this->broken)throw new RuntimeException('provider secret');
  return ['current'=>['weather_code'=>$this->code,'time'=>time()-$this->age]];
 }
}
$http=new WeatherFixture();$point=['latitude'=>'10.7','longitude'=>'106.7'];$now=new DateTimeImmutable('now');
$service=new OpenMeteoWeatherService($http,'','free');$data=$service->current($point,$now,1200);
check($data['weather_condition']==='LIGHT_RAIN','weather policy');check(str_starts_with($http->requests[0]['url'],'https://api.open-meteo.com/'),'free endpoint');
check(!str_contains($http->requests[0]['url'],'apikey='),'no API key required or sent');check($http->requests[0]['timeoutMs']===1200,'shared timeout budget');
check($data['weather_observed_at']!==$now->format(DATE_ATOM),'provider timestamp preserved');
(new OpenMeteoWeatherService($http,'test-secret','commercial'))->current($point,$now);
check(str_starts_with($http->requests[1]['url'],'https://customer-api.open-meteo.com/'),'commercial endpoint');check(str_contains($http->requests[1]['url'],'apikey=test-secret'),'commercial key');
(new OpenMeteoWeatherService($http,'unused-secret','free'))->current($point,$now);check(!str_contains($http->requests[2]['url'],'unused-secret'),'never send paid key to free endpoint');
rejects(fn()=>(new OpenMeteoWeatherService($http,'','commercial'))->current($point,$now),'PRICING_CONTEXT_UNAVAILABLE');
rejects(fn()=>(new OpenMeteoWeatherService($http,'','bad'))->current($point,$now),'PRICING_CONTEXT_UNAVAILABLE');
$http->age=1801;rejects(fn()=>$service->current($point,$now),'PRICING_CONTEXT_UNAVAILABLE');$http->age=-61;rejects(fn()=>$service->current($point,$now),'PRICING_CONTEXT_UNAVAILABLE');$http->age=0;
$http->code=95;rejects(fn()=>$service->current($point,$now),'WEATHER_SERVICE_PAUSED');$http->code='61';rejects(fn()=>$service->current($point,$now),'PRICING_CONTEXT_UNAVAILABLE');$http->code=999;rejects(fn()=>$service->current($point,$now),'PRICING_CONTEXT_UNAVAILABLE');
$http->broken=true;rejects(fn()=>$service->current($point,$now),'PRICING_CONTEXT_UNAVAILABLE');
echo json_encode(['passed'=>$checks,'external_requests'=>0]).PHP_EOL;
