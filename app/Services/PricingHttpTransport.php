<?php
declare(strict_types=1);
require_once __DIR__.'/DeliveryApiError.php';
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
                if (strlen($response) + strlen($chunk) > 524288) return 0;
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

