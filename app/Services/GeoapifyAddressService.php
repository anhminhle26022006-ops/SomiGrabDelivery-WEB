<?php
declare(strict_types=1);
require_once __DIR__.'/PricingHttpTransport.php';
require_once __DIR__.'/DeliveryInput.php';

final class GeoapifyAddressService
{
    private PricingHttpTransport $http;
    private string $key;
    public function __construct(?PricingHttpTransport $http=null, ?string $key=null) {
        $this->http=$http??new CurlPricingHttpTransport();
        $this->key=$key??(getenv('GEOAPIFY_API_KEY')?:'');
    }
    public function resolve(array $input): array {
        DeliveryInput::only($input,['action','input','language','session_token','latitude','longitude','pickup','delivery']);
        $language=$input['language']??'vi';
        if(!in_array($language,['vi','en'],true))DeliveryInput::invalid('language');
        $action=$input['action']??'';
        if(in_array($action,['autocomplete','geocode'],true)) {
            $text=DeliveryInput::text($input['input']??null,'input',200);
            if(mb_strlen($text)<3)return ['items'=>[]];
            $data=$this->request('geocode/'.($action==='autocomplete'?'autocomplete':'search'),[
                'text'=>$text,'lang'=>$language,'filter'=>'countrycode:vn','bias'=>'proximity:106.7009,10.7769','format'=>'json','limit'=>5]);
            if(!isset($data['results'])||!is_array($data['results']))$this->unavailable();
            $items=[];
            foreach($data['results'] as $p) {
                if(strtolower($p['country_code']??'')!=='vn')continue;
                $address=$this->address($p);
                $items[]=['text'=>$address['address_detail'],'address'=>$address];
            }
            return ['items'=>$items];
        }
        if($action==='reverse') {
            $point=$this->point($input);
            $data=$this->request('geocode/reverse',['lat'=>$point['latitude'],'lon'=>$point['longitude'],'lang'=>$language,'format'=>'json','limit'=>1]);
            if(!isset($data['results'])||!is_array($data['results']))$this->unavailable();
            $p=$data['results'][0]??null;
            if(!$p)throw new DeliveryApiError(422,'ADDRESS_NOT_FOUND','No address found at this location.');
            $address=$this->address($p);
            // Keep the selected pin, not the provider's nearest street/building centroid.
            $address['latitude']=$point['latitude'];$address['longitude']=$point['longitude'];
            return ['address'=>$address];
        }
        if($action==='route') {
            if(!is_array($input['pickup']??null)||!is_array($input['delivery']??null))DeliveryInput::invalid('pickup/delivery');
            $a=$this->point($input['pickup']);$b=$this->point($input['delivery']);
            $data=$this->request('routing',['waypoints'=>$a['latitude'].','.$a['longitude'].'|'.$b['latitude'].','.$b['longitude'],'mode'=>'motorcycle','units'=>'metric']);
            $feature=$data['features'][0]??null;
            if(!$feature)throw new DeliveryApiError(422,'ROUTE_NOT_FOUND','No motorcycle route found.');
            $geometry=$feature['geometry']??null;
            if(!is_array($geometry)||!in_array($geometry['type']??'', ['LineString','MultiLineString'],true)||!is_array($geometry['coordinates']??null))$this->unavailable();
            return ['route'=>['type'=>'Feature','geometry'=>$geometry,'properties'=>[]]];
        }
        DeliveryInput::invalid('action');
    }
    private function point(array $p): array {
        return ['latitude'=>DeliveryInput::decimal($p['latitude']??null,'latitude',7,'-90','90'),
            'longitude'=>DeliveryInput::decimal($p['longitude']??null,'longitude',7,'-180','180')];
    }
    private function address(array $p): array {
        if(strtolower($p['country_code']??'')!=='vn')throw new DeliveryApiError(422,'OUTSIDE_SERVICE_AREA','Choose an address in Vietnam.');
        $lat=$p['lat']??null;$lon=$p['lon']??null;
        if(!is_numeric($lat)||!is_numeric($lon)||!is_finite((float)$lat)||!is_finite((float)$lon))$this->unavailable();
        $address=DeliveryInput::address(['address_detail'=>$p['formatted']??null,'country_code'=>'VN',
            'province'=>$p['state']??$p['city']??null,'district'=>$p['county']??$p['district']??null,'ward'=>$p['suburb']??null,
            'latitude'=>number_format((float)$lat,7,'.',''),'longitude'=>number_format((float)$lon,7,'.','')]);
        unset($address['label'],$address['is_default']);return $address;
    }
    private function request(string $path,array $params): array {
        if($this->key===''||preg_match('/[\r\n]/',$this->key))$this->unavailable();
        try {
            $data=$this->http->request('GET','https://api.geoapify.com/v1/'.$path.'?'.http_build_query($params+['apiKey'=>$this->key]),['Accept: application/json'],null,8000);
            if(isset($data['error']))$this->unavailable();return $data;
        } catch(Throwable $e) {$this->unavailable();}
    }
    private function unavailable(): void {throw new DeliveryApiError(503,'PLACES_UNAVAILABLE','Address or map service is temporarily unavailable.');}
}
