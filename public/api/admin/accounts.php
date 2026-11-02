<?php
declare(strict_types=1);
require_once __DIR__ . '/../_person1.php';
require_once __DIR__ . '/../../../app/Services/AdminService.php';
try {
    $method = person1_method(['GET', 'POST']);
    $actor = person1_identity('ADMIN', $method === 'GET' ? 'account.read_all' : 'account.suspend');
    $service = new AdminService(new Person1Repository(somi_db()));
    if ($method === 'GET') {
        $role = isset($_GET['role']) ? Input::choice($_GET, 'role', ['CUSTOMER', 'SHIPPER']) : null;
        somi_api_json(200, ['data' => $service->accounts(Input::text($_GET, 'q', 150, false), $role,
            isset($_GET['before']) ? Input::id($_GET['before']) : PHP_INT_MAX)]);
    }
    $input = somi_api_input();
    somi_api_json(200, ['data' => $service->status($actor, Input::id($input['user_id'] ?? null),
        Input::choice($input, 'account_status', ['ACTIVE', 'SUSPENDED']), Input::text($input, 'reason', 1000))]);
} catch (Throwable $error) { person1_error($error); }
