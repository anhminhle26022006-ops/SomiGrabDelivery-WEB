<?php
declare(strict_types=1);
require_once __DIR__ . '/../_person1.php';
try {
    person1_method(['GET']);
    person1_identity('ADMIN', 'order.read_all');
    $repo = new Person1Repository(somi_db());
    if (isset($_GET['order_id'])) {
        $id = Input::id($_GET['order_id']);
        $order = $repo->one('SELECT * FROM orders WHERE order_id = :id', ['id' => $id]);
        if (!$order) throw new ApiProblem(404, 'Order not found.', 'NOT_FOUND');
        $order['history'] = $repo->all('SELECT * FROM order_status_history WHERE order_id = :id ORDER BY status_history_id', ['id' => $id]);
        somi_api_json(200, ['data' => $order]);
    }
    $before = isset($_GET['before']) ? Input::id($_GET['before']) : PHP_INT_MAX;
    somi_api_json(200, ['data' => $repo->all('SELECT order_id, order_code, customer_id, status, currency_code,
        total_charge_minor, created_at FROM orders WHERE order_id < :before ORDER BY order_id DESC LIMIT 100', ['before' => $before])]);
} catch (Throwable $error) { person1_error($error); }
