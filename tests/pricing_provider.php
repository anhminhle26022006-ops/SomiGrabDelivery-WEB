<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
require_once __DIR__ . '/../app/Services/GeoapifyMeteoPricingProvider.php';
$checks = 0;
function verify(bool $ok, string $message): void { global $checks; $checks++; if (!$ok) throw new RuntimeException($message); }
function reject(callable $fn, string $code): void {
    try { $fn(); } catch (DeliveryApiError $e) { verify($e->errorCode === $code, $code . ' expected; got ' . $e->errorCode); return; }
    throw new RuntimeException('Expected ' . $code);
}
final class FakePricingTransport implements PricingHttpTransport {
    public array $requests = [];
    public int $distance = 4125;
    public int $code = 61;
    public int $age = 900;
    public bool $fallback = false;
    public bool $noRoute = false;
    public string $city = 'Ho Chi Minh City';
    public string $geocodeStatus = 'OK';
    public function request(string $method, string $url, array $headers, ?array $body, int $timeoutMs): array {
        $this->requests[] = compact('method', 'url', 'headers', 'body', 'timeoutMs');
        if (str_contains($url, '/geocode/')) return $this->geocodeStatus==='OK' ? ['results'=>[['country_code'=>'vn','state'=>$this->city]]] : ['error'=>'provider failure'];
        if (str_contains($url, '/routing?')) return $this->noRoute ? ['features'=>[]] : ['features'=>[['properties'=>['distance'=>$this->distance,'time'=>$this->fallback ? -1 : 1201,'distance_units'=>'meters','time_units'=>'seconds']]]];
        return ['current' => ['weather_code' => $this->code, 'time' => time() - $this->age]];
    }
}
$http = new FakePricingTransport();
$provider = new GeoapifyMeteoPricingProvider($http, 'test-geoapify', 'test-meteo');
$point = ['latitude'=>'10.7','longitude'=>'106.7','country_code'=>'VN']; $now = new DateTimeImmutable('now');
$data = $provider->resolve($point, $point, $now);
verify($data['distance_km'] === '4.125' && $data['duration_minutes'] === 21, 'route units and duration ceiling');
verify($data['weather_condition'] === 'LIGHT_RAIN', 'weather normalization');
verify(str_contains($http->requests[2]['url'], 'mode=motorcycle'), 'motorcycle routing');
verify(str_starts_with($http->requests[3]['url'], 'https://customer-api.open-meteo.com/'), 'commercial weather endpoint');
verify($http->requests[3]['timeoutMs'] <= $http->requests[0]['timeoutMs'], 'shared timeout budget');
verify($data['weather_observed_at'] !== $data['observed_at'], 'source time separate from context freshness');
$http->distance = 39999; verify($provider->resolve($point,$point,$now)['distance_km'] === '39.999', 'route below 40 km accepted');
$http->distance = 40000; reject(fn()=> $provider->resolve($point,$point,$now), 'OUTSIDE_SERVICE_AREA'); $http->distance = 4125;
$http->age = 1801; reject(fn()=> $provider->resolve($point,$point,$now), 'PRICING_CONTEXT_UNAVAILABLE'); $http->age = 900;
$http->code = 95; reject(fn()=> $provider->resolve($point,$point,$now), 'WEATHER_SERVICE_PAUSED'); $http->code = 0;
$http->fallback = true; reject(fn()=> $provider->resolve($point,$point,$now), 'PRICING_CONTEXT_UNAVAILABLE'); $http->fallback = false;
$http->noRoute = true; reject(fn()=> $provider->resolve($point,$point,$now), 'ROUTE_NOT_FOUND'); $http->noRoute = false;
reject(fn()=> (new GeoapifyMeteoPricingProvider($http, '', 'test'))->resolve($point,$point,$now), 'PRICING_CONTEXT_UNAVAILABLE');
$http->city = 'Hanoi'; reject(fn()=> $provider->resolve($point,$point,$now), 'OUTSIDE_SERVICE_AREA'); $http->city = 'Ho Chi Minh City';
$http->geocodeStatus = 'REQUEST_DENIED'; reject(fn()=> $provider->resolve($point,$point,$now), 'PRICING_CONTEXT_UNAVAILABLE'); $http->geocodeStatus = 'OK';
foreach ([0=>'NORMAL',45=>'NORMAL',51=>'LIGHT_RAIN',61=>'LIGHT_RAIN',55=>'RAIN',65=>'RAIN',82=>'RAIN'] as $code=>$expected) verify(GeoapifyMeteoPricingProvider::condition($code) === $expected, 'WMO mapping ' . $code);
reject(fn()=> GeoapifyMeteoPricingProvider::condition(999), 'PRICING_CONTEXT_UNAVAILABLE');
echo json_encode(['result'=>'PASS','checks'=>$checks,'external_requests'=>0], JSON_PRETTY_PRINT) . PHP_EOL;
