<?php
declare(strict_types=1);
require_once __DIR__ . '/../_delivery.php';
$method = $_SERVER['REQUEST_METHOD'] ?? '';
delivery_run(['GET', 'POST'], $method === 'POST' ? 'order.create' : 'order.read_own', $method === 'POST', static function (PDO $db, int $owner) use ($method): array {
    $service = new CustomerOrderService($db);
    if ($method === 'GET') {
        $id = delivery_query_id(isset($_GET['order_id'])?'order_id':'id', true);
        if ($id !== null) return [200, ['order' => $service->detail($owner, $id)]];
        $limit = isset($_GET['limit']) ? DeliveryInput::id($_GET['limit'], 'limit') : 20;
        $status = isset($_GET['status']) ? DeliveryInput::text($_GET['status'], 'status', 30) : null;
        return [200, $service->list($owner, delivery_query_id('before_id', true), $limit, $status)];
    }
    $input = delivery_body();
    if(($input['action']??null)==='cancel'){
        AccessControl::requirePermission($db,$owner,'CUSTOMER','order.cancel_before_assignment');
        DeliveryInput::only($input,['action','order_id','reason']);
        return [200,['order'=>$service->cancel($owner,DeliveryInput::id($input['order_id']??null),DeliveryInput::text($input['reason']??null,'reason',500))]];
    }
    DeliveryInput::only($input, ['quote_id']);
    $quoteId = DeliveryInput::text($input['quote_id'] ?? null, 'quote_id', 32);
    $key = DeliveryInput::text($_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? null, 'Idempotency-Key', 100);
    $result = $service->create($owner, $quoteId, $key);
    return [$result['created'] ? 201 : 200, $result];
});
