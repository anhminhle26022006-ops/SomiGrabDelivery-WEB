<?php
declare(strict_types=1);
require_once __DIR__ . '/../_kernel.php';
require_once __DIR__ . '/../../../app/Services/DeliveryInput.php';
require_once __DIR__ . '/../../../app/Services/ShipperInput.php';

/**
 * Điểm vào chung của các API Shipper. Dùng lại somi_run_api() của nền tảng nên
 * session, workspace SHIPPER, permission, same-origin, transaction và định dạng
 * lỗi đều giống các API Customer/Admin; module Shipper không tự làm cơ chế riêng.
 */
function shipper_run(array $methods, string $permission, bool $write, callable $handler): void
{
    somi_run_api($methods, ['SHIPPER'], $permission, $write, $handler);
}

function shipper_body(array $allowedFields): array
{
    $input = somi_json_body();
    DeliveryInput::only($input, $allowedFields);
    return $input;
}
