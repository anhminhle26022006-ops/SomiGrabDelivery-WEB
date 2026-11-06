<?php
declare(strict_types=1);
require_once __DIR__ . '/../_delivery.php';
delivery_run(['POST'], 'order.cancel_before_assignment', true, static function (PDO $db, int $owner): array {
    $input = delivery_body();
    DeliveryInput::only($input, ['reason']);
    $reason = DeliveryInput::text($input['reason'] ?? null, 'reason', 500);
    return [200, ['order' => (new CustomerOrderService($db))->cancel($owner, delivery_query_id(), $reason)]];
});
