<?php
declare(strict_types=1);
require_once __DIR__ . '/_shipper.php';
require_once __DIR__ . '/../../../app/Services/DeliveryService.php';

// GET: đơn đang giao. GET ?history=1&before_id=&limit=: các đơn đã kết thúc.
// POST {order_id, action: "PICKUP" | "START_DELIVERY"}: cập nhật mốc giao nhận.
$method = $_SERVER['REQUEST_METHOD'] ?? '';
shipper_run(['GET', 'POST'], 'delivery.update_assigned', $method === 'POST',
    static function (PDO $db, int $shipperId) use ($method): array {
        $service = new DeliveryService($db);
        if ($method === 'GET') {
            if (isset($_GET['assignment_id'])) return [200,$service->historyDetail($shipperId,DeliveryInput::id($_GET['assignment_id'],'assignment_id'))];
            if (!isset($_GET['history'])) return [200, $service->current($shipperId)];
            $before = isset($_GET['before_id']) ? DeliveryInput::id($_GET['before_id'], 'before_id') : null;
            $limit = isset($_GET['limit']) ? DeliveryInput::id($_GET['limit'], 'limit') : 20;
            return [200, $service->history($shipperId, $before, $limit)];
        }
        $input = shipper_body(['order_id', 'action']);
        $orderId = DeliveryInput::id($input['order_id'] ?? null, 'order_id');
        return match (ShipperInput::choice($input['action'] ?? null, 'action', ['PICKUP', 'START_DELIVERY', 'ARRIVED'])) {
            'PICKUP' => [200, $service->pickup($shipperId, $orderId)],
            'START_DELIVERY' => [200, $service->startDelivery($shipperId, $orderId)],
            'ARRIVED' => [200, $service->arrive($shipperId, $orderId)],
        };
    });
