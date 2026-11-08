<?php
declare(strict_types=1);
require_once __DIR__.'/PricingHttpTransport.php';
require_once __DIR__.'/DeliveryInput.php';

final class OpenMeteoWeatherService
{
    private PricingHttpTransport $http;
    private string $key;
    private string $mode;
    public function __construct(?PricingHttpTransport $http=null, ?string $key=null, ?string $mode=null) {
        $this->http=$http??new CurlPricingHttpTransport();
        $this->key=$key??(getenv('OPEN_METEO_API_KEY')?:'');
        $this->mode=$mode??(getenv('OPEN_METEO_MODE')?:($this->key!==''?'commercial':'free'));
    }
    public function current(array $point, DateTimeImmutable $at, int $timeoutMs=8000): array {
        $latitude=DeliveryInput::decimal($point['latitude']??null,'latitude',7,'-90','90');
        $longitude=DeliveryInput::decimal($point['longitude']??null,'longitude',7,'-180','180');
        if(!in_array($this->mode,['free','commercial'],true)||($this->mode==='commercial'&&$this->key==='')||preg_match('/[\r\n]/',$this->key))
            throw new DeliveryApiError(503,'PRICING_CONTEXT_UNAVAILABLE','Weather service is not configured.');
        $params=['latitude'=>$latitude,'longitude'=>$longitude,'current'=>'weather_code','timezone'=>'GMT','timeformat'=>'unixtime'];
        $host='https://api.open-meteo.com/v1/forecast';
        if($this->mode==='commercial'){$host='https://customer-api.open-meteo.com/v1/forecast';$params['apikey']=$this->key;}
        try {
            $data=$this->http->request('GET',$host.'?'.http_build_query($params),['Accept: application/json'],null,max(1,min(8000,$timeoutMs)));
        } catch(Throwable $e) {throw new DeliveryApiError(503,'PRICING_CONTEXT_UNAVAILABLE','Weather service is temporarily unavailable.');}
        $code=$data['current']['weather_code']??null;$time=$data['current']['time']??null;
        if(isset($data['error'])||!is_int($code)||!is_int($time)||$at->getTimestamp()-$time>1800||$time>$at->getTimestamp()+60)
            throw new DeliveryApiError(503,'PRICING_CONTEXT_UNAVAILABLE','Weather service returned invalid or stale data.');
        return ['weather_condition'=>self::condition($code),'weather_code'=>$code,'weather_observed_at'=>gmdate(DATE_ATOM,$time),'provider'=>'Open-Meteo'];
    }
    public static function condition(int $code): string
    {
        if (in_array($code, [0,1,2,3,45,48], true)) return 'NORMAL';
        if (in_array($code, [51,53,61,80], true)) return 'LIGHT_RAIN';
        if (in_array($code, [55,63,65,81,82], true)) return 'RAIN';
        if (in_array($code, [56,57,66,67,71,73,75,77,85,86,95,96,99], true)) {
            throw new DeliveryApiError(422, 'WEATHER_SERVICE_PAUSED', 'New deliveries are temporarily paused due to unsafe weather.');
        }
        throw new DeliveryApiError(503, 'PRICING_CONTEXT_UNAVAILABLE', 'Weather condition is not recognized.');
    }

}
