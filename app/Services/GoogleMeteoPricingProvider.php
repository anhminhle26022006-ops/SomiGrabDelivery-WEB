<?php
declare(strict_types=1);
require_once __DIR__ . '/PricingContextProvider.php';
require_once __DIR__ . '/DeliveryInput.php';

interface PricingHttpTransport
{
    public function request(string $method, string $url, array $headers, ?array $body, int $timeoutMs): array;
}

final class CurlPricingHttpTransport implements PricingHttpTransport
{
    public function request(string $method, string $url, array $headers, ?array $body, int $timeoutMs): array
    {
        $response = ''; $curl = curl_init($url);
        curl_setopt_array($curl, [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT_MS => $timeoutMs, CURLOPT_CONNECTTIMEOUT_MS => min(3000, $timeoutMs),
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$response): int {
                if (strlen($response) + strlen($chunk) > 65536) return 0;
                $response .= $chunk; return strlen($chunk);
            }]);
        if ($body !== null) curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($body, JSON_THROW_ON_ERROR));
        $ok = curl_exec($curl); $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE); curl_close($curl);
        $data = json_decode($response, true);
        // Do not log URL, API key, provider response or customer coordinates.
        if ($ok === false || $status !== 200 || !is_array($data)) throw new DeliveryApiError(503, 'PRICING_CONTEXT_UNAVAILABLE', 'Routing or weather service is unavailable.');
        return $data;
    }
}

final class GoogleMeteoPricingProvider implements PricingContextProvider
{
    private PricingHttpTransport $http;
    private string $googleKey;
    private string $meteoKey;

    public function __construct(?PricingHttpTransport $http = null, ?string $googleKey = null, ?string $meteoKey = null)
    {
        $this->http = $http ?? new CurlPricingHttpTransport();
        $this->googleKey = $googleKey ?? (getenv('GOOGLE_MAPS_API_KEY') ?: '');
        $this->meteoKey = $meteoKey ?? (getenv('OPEN_METEO_API_KEY') ?: '');
    }

    public function resolve(array $pickup, array $delivery, DateTimeImmutable $at): array
    {
        if ($this->googleKey === '' || $this->meteoKey === '' || preg_match('/[\r\n]/', $this->googleKey . $this->meteoKey)) {
            throw new DeliveryApiError(503, 'PRICING_CONTEXT_UNAVAILABLE', 'Google Routes and commercial Open-Meteo API keys are required.');
        }
        $deadline = microtime(true) + 10;
        $this->requireServiceArea($pickup, $delivery, $deadline);
        $waypoint = static fn(array $point): array => ['location' => ['latLng' => ['latitude' => (float) $point['latitude'], 'longitude' => (float) $point['longitude']]]];
        $data = $this->http->request('POST', 'https://routes.googleapis.com/directions/v2:computeRoutes',
            ['Content-Type: application/json', 'X-Goog-Api-Key: ' . $this->googleKey, 'X-Goog-FieldMask: routes.distanceMeters,routes.duration,fallbackInfo'],
            ['origin' => $waypoint($pickup), 'destination' => $waypoint($delivery), 'travelMode' => 'TWO_WHEELER', 'computeAlternativeRoutes' => false], $this->remaining($deadline));
        $route = $data['routes'][0] ?? [];
        $meters = $route['distanceMeters'] ?? null;
        if (!isset($data['routes'][0]) && !isset($data['error'])) throw new DeliveryApiError(422, 'ROUTE_NOT_FOUND', 'No supported motorcycle route was found.');
        if (isset($data['fallbackInfo']) || !is_int($meters) || $meters < 0 || !is_string($route['duration'] ?? null) || !preg_match('/^([0-9]+(?:\.[0-9]{1,9})?)s$/D', $route['duration'], $duration)) {
            throw new DeliveryApiError(503, 'PRICING_CONTEXT_UNAVAILABLE', 'Routing service returned invalid data.');
        }
        $config = require __DIR__ . '/../Config/delivery-pricing.php';
        if (bccomp((string) $meters, bcmul($config['max_distance_km'], '1000', 0), 0) >= 0) throw new DeliveryApiError(422, 'OUTSIDE_SERVICE_AREA', 'Delivery routes must be shorter than ' . (int) $config['max_distance_km'] . ' km.');
        $minutes = max(1, (int) ceil((float) $duration[1] / 60));
        $query = http_build_query(['latitude' => $pickup['latitude'], 'longitude' => $pickup['longitude'], 'current' => 'weather_code', 'timezone' => 'GMT', 'timeformat' => 'unixtime', 'apikey' => $this->meteoKey]);
        $weather = $this->http->request('GET', 'https://customer-api.open-meteo.com/v1/forecast?' . $query, ['Accept: application/json'], null, $this->remaining($deadline));
        $code = $weather['current']['weather_code'] ?? null; $weatherAt = $weather['current']['time'] ?? null;
        if (!is_int($code) || !is_int($weatherAt) || $at->getTimestamp() - $weatherAt > 1800 || $weatherAt > $at->getTimestamp() + 60) {
            throw new DeliveryApiError(503, 'PRICING_CONTEXT_UNAVAILABLE', 'Weather service returned invalid or stale data.');
        }
        return ['distance_km' => bcdiv((string) $meters, '1000', 3), 'duration_minutes' => $minutes,
            'weather_condition' => self::condition($code), 'provider' => 'Google Routes TWO_WHEELER + Open-Meteo',
            'observed_at' => $at->format(DATE_ATOM), 'weather_observed_at' => gmdate(DATE_ATOM, $weatherAt), 'weather_code' => $code];
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

    private function requireServiceArea(array $pickup, array $delivery, float $deadline): void
    {
        foreach ([$pickup, $delivery] as $point) {
            $query = http_build_query(['latlng' => $point['latitude'] . ',' . $point['longitude'], 'result_type' => 'administrative_area_level_1', 'language' => 'en', 'key' => $this->googleKey]);
            $data = $this->http->request('GET', 'https://maps.googleapis.com/maps/api/geocode/json?' . $query, ['Accept: application/json'], null, $this->remaining($deadline));
            if (($data['status'] ?? '') === 'ZERO_RESULTS') throw new DeliveryApiError(422, 'OUTSIDE_SERVICE_AREA', 'Location could not be verified inside Ho Chi Minh City.');
            if (($data['status'] ?? '') !== 'OK' || empty($data['results'])) throw new DeliveryApiError(503, 'PRICING_CONTEXT_UNAVAILABLE', 'Service area verification is unavailable.');
            $inside = false;
            foreach ($data['results'] as $result) {
                $country = null; $city = null;
                foreach ($result['address_components'] ?? [] as $component) {
                    if (in_array('country', $component['types'] ?? [], true)) $country = $component['short_name'] ?? null;
                    if (in_array('administrative_area_level_1', $component['types'] ?? [], true)) $city = $component['long_name'] ?? null;
                }
                if ($country === 'VN' && in_array($city, ['Ho Chi Minh City', 'Ho Chi Minh', 'Hồ Chí Minh', 'Thành phố Hồ Chí Minh'], true)) $inside = true;
            }
            if (!$inside || ($point['country_code'] ?? '') !== 'VN') throw new DeliveryApiError(422, 'OUTSIDE_SERVICE_AREA', 'Pickup and delivery must both be within Ho Chi Minh City.');
        }
    }

    private function remaining(float $deadline): int
    {
        $ms = (int) floor(($deadline - microtime(true)) * 1000);
        if ($ms < 1) throw new DeliveryApiError(503, 'PRICING_CONTEXT_UNAVAILABLE', 'Routing and weather request timed out.');
        return $ms;
    }
}

function somi_pricing_context_provider(): PricingContextProvider
{
    return getenv('SOMI_PRICING_CONTEXT_URL') ? new HttpPricingContextProvider() : new GoogleMeteoPricingProvider();
}
