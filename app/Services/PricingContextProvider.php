<?php
declare(strict_types=1);
require_once __DIR__ . '/DeliveryApiError.php';

interface PricingContextProvider
{
    /** Trusted server result: distance_km, duration_minutes, weather_condition,
     * provider, observed_at. Customer-supplied prices/distance/weather are never used. */
    public function resolve(array $pickup, array $delivery, DateTimeImmutable $at): array;
}

final class HttpPricingContextProvider implements PricingContextProvider
{
    public function resolve(array $pickup, array $delivery, DateTimeImmutable $at): array
    {
        $url = getenv('SOMI_PRICING_CONTEXT_URL') ?: '';
        $token = getenv('SOMI_PRICING_CONTEXT_TOKEN') ?: '';
        if (parse_url($url, PHP_URL_SCHEME) !== 'https' || $token === '' || preg_match('/[\r\n]/', $token)) {
            throw new DeliveryApiError(503, 'PRICING_CONTEXT_UNAVAILABLE', 'Routing and weather provider is not configured.');
        }
        $body = '';
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode(['pickup' => ['latitude' => $pickup['latitude'], 'longitude' => $pickup['longitude']], 'delivery' => ['latitude' => $delivery['latitude'], 'longitude' => $delivery['longitude']], 'requested_at' => $at->format(DATE_ATOM)], JSON_THROW_ON_ERROR),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $token],
            CURLOPT_CONNECTTIMEOUT => 3, CURLOPT_TIMEOUT => 10,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$body): int {
                if (strlen($body) + strlen($chunk) > 65536) return 0;
                $body .= $chunk;
                return strlen($chunk);
            },
        ]);
        $ok = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        $result = json_decode($body, true);
        if ($ok === false || $status !== 200 || !is_array($result)) {
            throw new DeliveryApiError(503, 'PRICING_CONTEXT_UNAVAILABLE', 'Routing and weather provider is unavailable.');
        }
        return $result;
    }
}
