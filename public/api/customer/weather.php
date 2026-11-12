<?php
declare(strict_types=1);
require_once __DIR__ . '/../_delivery.php';
require_once __DIR__ . '/../../../app/Services/OpenMeteoWeatherService.php';
require_once __DIR__ . '/../../../app/Services/AuthRateLimiter.php';

// Same-origin checks apply to POST; no database transaction is held during weather I/O.
delivery_run(['POST'], 'order.create', false, static function (PDO $db, int $owner): array {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? null;
    if (($origin !== null && !somi_origin_allowed($origin)) || ($origin === null && ($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '') !== 'same-origin'))
        throw new DeliveryApiError(403, 'ORIGIN_REQUIRED', 'A same-origin request is required.');
    $input = delivery_body();
    AuthRateLimiter::hit($db, 'weather:' . $owner, 20, 60);
    DeliveryInput::only($input, ['latitude', 'longitude']);
    return [200, ['weather' => (new OpenMeteoWeatherService())->current($input, new DateTimeImmutable('now', new DateTimeZone('UTC')))]];
});
