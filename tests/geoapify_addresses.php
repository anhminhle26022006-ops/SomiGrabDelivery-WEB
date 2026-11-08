<?php
declare(strict_types=1);
require_once __DIR__.'/../app/Services/GeoapifyAddressService.php';
$checks=0;
function check(bool $ok,string $message):void{global $checks;$checks++;if(!$ok)throw new RuntimeException($message);}
function fails(callable $fn,string $code):void{try{$fn();}catch(DeliveryApiError $e){check($e->errorCode===$code,$code);return;}throw new RuntimeException('Expected '.$code);}
final class GeoFixture implements PricingHttpTransport {
 public array $calls=[];public string $country='vn';public bool $broken=false;
 public function request(string $method,string $url,array $headers,?array $body,int $timeoutMs):array {
  $this->calls[]=compact('method','url','headers','body','timeoutMs');
  if($this->broken)throw new RuntimeException('provider secret');
  if(str_contains($url,'/routing?'))return ['features'=>[['geometry'=>['type'=>'MultiLineString','coordinates'=>[[[106.7,10.7],[106.8,10.8]]]]]]];
  return ['results'=>[['formatted'=>'Test, Vietnam','country_code'=>$this->country,'state'=>'Ho Chi Minh City','lat'=>10.776912345,'lon'=>106.700912345]]];
 }
}
$http=new GeoFixture();$service=new GeoapifyAddressService($http,'fixture-key');
$result=$service->resolve(['action'=>'autocomplete','input'=>'Test']);
check(count($result['items'])===1,'autocomplete');
check($result['items'][0]['address']['latitude']==='10.7769123','normalize precision');
check(!str_contains(json_encode($result),'fixture-key'),'no key leak');
parse_str(parse_url($http->calls[0]['url'],PHP_URL_QUERY),$query);
check($query['filter']==='countrycode:vn','country filter');check($query['bias']==='proximity:106.7009,10.7769','longitude first bias');
$result=$service->resolve(['action'=>'reverse','latitude'=>'10.7000000','longitude'=>'106.7000000']);
check($result['address']['latitude']==='10.7000000','retain exact selected pin');
$result=$service->resolve(['action'=>'route','pickup'=>['latitude'=>'10.7','longitude'=>'106.7'],'delivery'=>['latitude'=>'10.8','longitude'=>'106.8']]);
check($result['route']['geometry']['type']==='MultiLineString','route geometry');
check(str_contains($http->calls[2]['url'],'mode=motorcycle'),'motorcycle route');
fails(fn()=>$service->resolve(['action'=>'reverse','latitude'=>'91','longitude'=>'106']),'VALIDATION_ERROR');
fails(fn()=>$service->resolve(['action'=>'autocomplete','input'=>'test','extra'=>'x']),'INVALID_FIELDS');
fails(fn()=>$service->resolve(['action'=>'invalid']),'VALIDATION_ERROR');
$http->country='us';fails(fn()=>$service->resolve(['action'=>'reverse','latitude'=>'10','longitude'=>'106']),'OUTSIDE_SERVICE_AREA');
$http->broken=true;fails(fn()=>$service->resolve(['action'=>'autocomplete','input'=>'test']),'PLACES_UNAVAILABLE');
fails(fn()=>(new GeoapifyAddressService($http,''))->resolve(['action'=>'autocomplete','input'=>'test']),'PLACES_UNAVAILABLE');
echo json_encode(['passed'=>$checks,'external_requests'=>0]).PHP_EOL;
