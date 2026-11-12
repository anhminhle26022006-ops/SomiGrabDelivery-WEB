<?php
declare(strict_types=1);
require_once __DIR__ . '/PricingContextProvider.php';
require_once __DIR__ . '/DeliveryInput.php';

require_once __DIR__.'/PricingHttpTransport.php';
require_once __DIR__.'/OpenMeteoWeatherService.php';
final class GeoapifyMeteoPricingProvider implements PricingContextProvider
{
    private PricingHttpTransport $http;
    private string $geoapifyKey;
    private string $meteoKey;

    public function __construct(?PricingHttpTransport $http = null, ?string $geoapifyKey = null, ?string $meteoKey = null)
    {
        $this->http = $http ?? new CurlPricingHttpTransport();
        $this->geoapifyKey = $geoapifyKey ?? (getenv('GEOAPIFY_API_KEY') ?: '');
        $this->meteoKey = $meteoKey ?? (getenv('OPEN_METEO_API_KEY') ?: '');
    }

    public function resolve(array $pickup, array $delivery, DateTimeImmutable $at): array
    {
        if ($this->geoapifyKey === '' || preg_match('/[\r\n]/', $this->geoapifyKey . $this->meteoKey)) {
            throw new DeliveryApiError(503, 'PRICING_CONTEXT_UNAVAILABLE', 'Geoapify API key is required.');
        }
        $deadline = microtime(true) + 10;
        $this->requireServiceArea($pickup, $delivery, $deadline);
        $query = http_build_query(['waypoints' => $pickup['latitude'] . ',' . $pickup['longitude'] . '|' . $delivery['latitude'] . ',' . $delivery['longitude'],
            'mode' => 'motorcycle', 'units' => 'metric', 'apiKey' => $this->geoapifyKey]);
        $data = $this->request('routing', $query, $deadline);
        if (!isset($data['features']) || !is_array($data['features'])) $this->unavailable();
        if ($data['features'] === []) throw new DeliveryApiError(422, 'ROUTE_NOT_FOUND', 'No supported motorcycle route was found.');
        if (!is_array($data['features'][0] ?? null) || !is_array($data['features'][0]['properties'] ?? null)) $this->unavailable();
        $route = $data['features'][0]['properties'] ?? [];
        $meters = $route['distance'] ?? null; $seconds = $route['time'] ?? null;
        if ((!is_int($meters) && !is_float($meters)) || !is_finite((float)$meters) || $meters < 0 ||
            (!is_int($seconds) && !is_float($seconds)) || !is_finite((float)$seconds) || $seconds < 0 ||
            ($route['distance_units'] ?? 'meters') !== 'meters' || ($route['time_units'] ?? 'seconds') !== 'seconds')
            throw new DeliveryApiError(503, 'PRICING_CONTEXT_UNAVAILABLE', 'Routing service returned invalid data.');
        $config = require __DIR__ . '/../Config/delivery-pricing.php';
        $meters = number_format((float)$meters, 3, '.', '');
        if (bccomp($meters, bcmul($config['max_distance_km'], '1000', 3), 3) >= 0)
            throw new DeliveryApiError(422, 'OUTSIDE_SERVICE_AREA', 'Delivery route is outside the supported distance range.');
        $minutes = max(1, (int)ceil($seconds / 60));
        $weather=(new OpenMeteoWeatherService($this->http,$this->meteoKey))->current($pickup,$at,$this->remaining($deadline));
        return ['distance_km'=>bcdiv($meters,'1000',3),'duration_minutes'=>$minutes,
            'weather_condition'=>$weather['weather_condition'],'provider'=>'Geoapify motorcycle + Open-Meteo',
            'observed_at'=>$at->format(DATE_ATOM),'weather_observed_at'=>$weather['weather_observed_at'],'weather_code'=>$weather['weather_code']];
    }

    public static function condition(int $code): string {return OpenMeteoWeatherService::condition($code);}

    private function requireServiceArea(array $pickup, array $delivery, float $deadline): void
    {
        foreach ([$pickup, $delivery] as $point) {
            $query = http_build_query(['lat' => $point['latitude'], 'lon' => $point['longitude'], 'lang' => 'en', 'format' => 'json', 'limit' => 1, 'apiKey' => $this->geoapifyKey]);
            $data = $this->request('geocode/reverse', $query, $deadline);
            if (!isset($data['results']) || !is_array($data['results'])) throw new DeliveryApiError(503, 'PRICING_CONTEXT_UNAVAILABLE', 'Service area verification is unavailable.');
            $p = $data['results'][0] ?? null;
            if (!is_array($p) || !is_string($p['country_code'] ?? null) || !is_string($p['state'] ?? $p['city'] ?? null)) $this->unavailable();
            $city = $p['state'] ?? $p['city'] ?? '';
            if (strtolower($p['country_code'] ?? '') !== 'vn' || ($point['country_code'] ?? '') !== 'VN' ||
                !in_array($city, ['Ho Chi Minh City', 'Ho Chi Minh', 'Hồ Chí Minh', 'Thành phố Hồ Chí Minh', 'Ho Chi Minh city'], true))
                throw new DeliveryApiError(422, 'OUTSIDE_SERVICE_AREA', 'Pickup and delivery must both be within Ho Chi Minh City.');

        }
    }
    private function request(string $path, string $query, float $deadline): array
    {
        try {
            $data = $this->http->request('GET', 'https://api.geoapify.com/v1/' . $path . '?' . $query, ['Accept: application/json'], null, $this->remaining($deadline));
            if (isset($data['error'])) $this->unavailable();
            return $data;
        } catch (Throwable $error) {
            // Provider exceptions may contain URLs or keys; expose only our stable error.
            $this->unavailable();
        }
    }
    private function unavailable(): void
    {
        throw new DeliveryApiError(503, 'PRICING_CONTEXT_UNAVAILABLE', 'Routing or service area verification is temporarily unavailable.');
    }
    private function remaining(float $deadline): int {
        $ms=(int)floor(($deadline-microtime(true))*1000);
        if($ms<1)throw new DeliveryApiError(503,'PRICING_CONTEXT_UNAVAILABLE','Routing and weather request timed out.');
        return $ms;
    }
}
function somi_pricing_context_provider(): PricingContextProvider {
    return getenv('SOMI_PRICING_CONTEXT_URL') ? new HttpPricingContextProvider() : new GeoapifyMeteoPricingProvider();
}
