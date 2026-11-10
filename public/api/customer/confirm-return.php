<?php
declare(strict_types=1);
require_once __DIR__ . '/../_kernel.php';
require_once __DIR__ . '/../../../app/Services/DeliveryInput.php';
require_once __DIR__ . '/../../../app/Services/DeliveryReturnService.php';

// POST {order_id}: Customer xác nhận đã nhận lại hàng hoàn. Thuộc nghiệp vụ hoàn hàng của Người 2;
// file nằm trong thư mục customer vì người gọi là Customer.
somi_run_api(['POST'], ['CUSTOMER'], 'order.read_own', true, static function (PDO $db, int $customerId): array {
    $input = somi_json_body();
    DeliveryInput::only($input, ['order_id']);
    return [200, (new DeliveryReturnService($db))->customerConfirmed($customerId, DeliveryInput::id($input['order_id'] ?? null, 'order_id'))];
});
