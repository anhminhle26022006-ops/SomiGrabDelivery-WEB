<?php
declare(strict_types=1);
require_once __DIR__ . '/../_kernel.php';
require_once __DIR__ . '/../../../app/Services/AdminService.php';
somi_run_api(['POST'], ['ADMIN'], 'shipper.verify', true, static function (PDO $db, int $admin): array {
    $input = somi_json_body();
    DeliveryInput::only($input, ['shipper_id', 'decision', 'note']);
    return [200, (new AdminService($db))->reviewShipper($admin, DeliveryInput::id($input['shipper_id'] ?? null), DeliveryInput::text($input['decision'] ?? null, 'decision', 20), DeliveryInput::text($input['note'] ?? '', 'note', 1000, false) ?? '')];
});
