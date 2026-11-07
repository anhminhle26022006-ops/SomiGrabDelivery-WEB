<?php
declare(strict_types=1);
require_once __DIR__ . '/../_person1.php';
require_once __DIR__ . '/../../../app/Services/CustomerService.php';
try {
    $method = person1_method(['GET', 'POST']);
    $id = person1_identity('CUSTOMER', $method === 'GET' ? 'order.read_own' : 'order.cancel_before_assignment');
    $service = new CustomerService(new Person1Repository(somi_db()));
    if ($method === 'GET') {
        $data = isset($_GET['order_id']) ? $service->order($id, Input::id($_GET['order_id']))
            : $service->orders($id, isset($_GET['before']) ? Input::id($_GET['before']) : PHP_INT_MAX);
        somi_api_json(200, ['data' => $data]);
    }
    $input = somi_api_input();
    Input::choice($input, 'action', ['cancel']);
    somi_api_json(200, ['data' => $service->cancel($id, Input::id($input['order_id'] ?? null), Input::text($input, 'reason', 1000))]);
} catch (Throwable $error) { person1_error($error); }
