<?php
declare(strict_types=1);
require_once __DIR__ . '/../_person1.php';
require_once __DIR__ . '/../../../app/Services/CustomerService.php';
try {
    $method = person1_method(['GET', 'POST']);
    $id = person1_identity('CUSTOMER', 'address.manage_own');
    $service = new CustomerService(new Person1Repository(somi_db()));
    if ($method === 'GET') somi_api_json(200, ['data' => $service->addresses($id)]);
    $input = somi_api_input();
    $action = Input::choice($input, 'action', ['save', 'archive']);
    if ($action === 'archive') {
        $service->archiveAddress($id, Input::id($input['address_id'] ?? null));
        somi_api_json(200, ['status' => 'ARCHIVED']);
    }
    somi_api_json(isset($input['address_id']) ? 200 : 201, ['data' => $service->saveAddress($id, $input)]);
} catch (Throwable $error) { person1_error($error); }
