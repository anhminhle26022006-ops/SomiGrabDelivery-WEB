<?php
declare(strict_types=1);
require_once __DIR__ . '/../_delivery.php';
require_once __DIR__ . '/../../../app/Services/GoogleMeteoPricingProvider.php';
delivery_run(['POST'], 'order.create', true, static function (PDO $db, int $owner): array {
    return [201, ['quote' => (new OrderQuoteService($db, somi_pricing_context_provider()))->create($owner, delivery_body())]];
}, true);
