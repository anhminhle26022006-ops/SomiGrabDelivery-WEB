<?php
declare(strict_types=1);
require_once __DIR__ . '/_shipper.php';
require_once __DIR__ . '/../../../app/Services/AccessControl.php';
require_once __DIR__ . '/../../../app/Services/GeoapifyAddressService.php';
require_once __DIR__ . '/../../../app/Services/AuthRateLimiter.php';
shipper_run(['GET'], 'delivery.update_assigned', false, static function(PDO $db, int $shipperId): array {
    $orderId = DeliveryInput::id($_GET['order_id'] ?? null, 'order_id');
    $order = AccessControl::requireOrder($db, $shipperId, 'SHIPPER', $orderId);
    AuthRateLimiter::hit($db, 'shipper-route:' . $shipperId, 30, 60);
    return [200, (new GeoapifyAddressService())->resolve([
        'action' => 'route',
        'pickup' => ['latitude' => $order['pickup_latitude'], 'longitude' => $order['pickup_longitude']],
        'delivery' => ['latitude' => $order['delivery_latitude'], 'longitude' => $order['delivery_longitude']],
    ])];
});
