<?php
declare(strict_types=1);
require_once __DIR__.'/PricingHttpTransport.php';
require_once __DIR__.'/DeliveryInput.php';

/** Dashboard forecasts use fixed public area centres, never account addresses or GPS. */
final class WorkspaceWeatherService
{
    public const AREAS = [
        'centre'=>['latitude'=>'10.7769','longitude'=>'106.7009','name_vi'=>'Trung tâm TP.HCM','name_en'=>'Central Ho Chi Minh City'],
        'east'=>['latitude'=>'10.8494','longitude'=>'106.7537','name_vi'=>'Khu vực Thủ Đức','name_en'=>'Thu Duc area'],
        'south'=>['latitude'=>'10.7290','longitude'=>'106.7210','name_vi'=>'Khu vực Quận 7','name_en'=>'District 7 area'],
    ];
    public function __construct(private ?PricingHttpTransport $http=null) {}

    public function forecast(string $area,?DateTimeImmutable $now=null): array
    {
        if (!isset(self::AREAS[$area])) DeliveryInput::invalid('area');
        $point=self::AREAS[$area];$now??=new DateTimeImmutable('now',new DateTimeZone('UTC'));
        $key=getenv('OPEN_METEO_API_KEY')?:'';$mode=getenv('OPEN_METEO_MODE')?:($key!==''?'commercial':'free');
        if (!in_array($mode,['free','commercial'],true)||($mode==='commercial'&&$key==='')||preg_match('/[\r\n]/',$key)) throw new DeliveryApiError(503,'WEATHER_UNAVAILABLE','Weather service is not configured.');
        $params=['latitude'=>$point['latitude'],'longitude'=>$point['longitude'],'current'=>'temperature_2m,apparent_temperature,relative_humidity_2m,weather_code,wind_speed_10m',
            'hourly'=>'temperature_2m,precipitation_probability,weather_code','forecast_hours'=>5,'timezone'=>'GMT','timeformat'=>'unixtime'];
        $host=$mode==='commercial'?'https://customer-api.open-meteo.com/v1/forecast':'https://api.open-meteo.com/v1/forecast';
        if ($mode==='commercial') $params['apikey']=$key;
        try {$data=($this->http??new CurlPricingHttpTransport())->request('GET',$host.'?'.http_build_query($params),['Accept: application/json'],null,8000);}
        catch(Throwable $e){throw new DeliveryApiError(503,'WEATHER_UNAVAILABLE','Weather is temporarily unavailable.');}
        $current=$data['current']??[];$time=$current['time']??null;
        if (!is_int($time)||abs($now->getTimestamp()-$time)>1800||!is_numeric($current['temperature_2m']??null)||!is_int($current['weather_code']??null)) throw new DeliveryApiError(503,'WEATHER_UNAVAILABLE','Weather data is unavailable or outdated.');
        $number=static fn($value)=>is_numeric($value)&&is_finite((float)$value)?(float)$value:null;
        $hours=[];
        foreach (($data['hourly']['time']??[]) as $i=>$at) {
            if (!is_int($at)||$at<$now->getTimestamp()-3600||$at>$now->getTimestamp()+18000) continue;
            $temperature=$number($data['hourly']['temperature_2m'][$i]??null);$rain=$number($data['hourly']['precipitation_probability'][$i]??null);$code=$data['hourly']['weather_code'][$i]??null;
            if ($temperature===null||!is_int($code)) continue;
            $hours[]=['time'=>gmdate(DATE_ATOM,$at),'temperature_c'=>$temperature,'rain_probability'=>$rain!==null&&$rain>=0&&$rain<=100?$rain:null,'weather_code'=>$code];
            if (count($hours)===4) break;
        }
        return ['provider'=>'Open-Meteo','observed_at'=>gmdate(DATE_ATOM,$time),'temperature_c'=>$number($current['temperature_2m']),
            'feels_like_c'=>$number($current['apparent_temperature']??null),'humidity_percent'=>$number($current['relative_humidity_2m']??null),
            'wind_kmh'=>$number($current['wind_speed_10m']??null),'weather_code'=>$current['weather_code'],'hours'=>$hours];
    }

    public function cached(string $area): array
    {
        if (!isset(self::AREAS[$area])) DeliveryInput::invalid('area');
        $config=(getenv('OPEN_METEO_MODE')?:'').(getenv('OPEN_METEO_API_KEY')?:'');
        $path=sys_get_temp_dir().DIRECTORY_SEPARATOR.'somi-weather-'.hash('sha256',$area.$config).'.json';
        $cached=is_file($path)?json_decode((string)@file_get_contents($path),true):null;
        if (is_array($cached)&&($cached['expires']??0)>time()&&isset($cached['weather'])) return $cached['weather'];
        $weather=$this->forecast($area);
        @file_put_contents($path,json_encode(['expires'=>time()+300,'weather'=>$weather]),LOCK_EX);
        return $weather;
    }
}
