<?php
declare(strict_types=1);
require_once __DIR__ . '/../_delivery.php';
$method = $_SERVER['REQUEST_METHOD'] ?? '';
delivery_run(['GET', 'POST', 'PUT', 'DELETE'], 'address.manage_own', $method !== 'GET', static function (PDO $db, int $owner) use ($method): array {
    $service = new CustomerAddressService($db);
    if ($method === 'GET') {
        $id = delivery_query_id('id', true);
        return [200, $id === null ? ['items' => $service->all($owner)] : ['address' => $service->get($owner, $id)]];
    }
    if ($method === 'DELETE') {
        $service->archive($owner, delivery_query_id());
        return [200, ['archived' => true]];
    }
    $id = $method === 'PUT' ? delivery_query_id() : null;
    return [$method === 'POST' ? 201 : 200, ['address' => $service->save($owner, delivery_body(), $id)]];
});
