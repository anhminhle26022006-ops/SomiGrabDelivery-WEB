<?php
declare(strict_types=1);
require_once __DIR__.'/../_kernel.php';
require_once __DIR__.'/../../../app/Services/ShipperApplicationService.php';
somi_run_api(['GET'], ['ADMIN'], 'shipper.verify', true, static function(PDO $db, int $admin): array {
    return [200, (new ShipperApplicationService($db))->forAdmin($admin, DeliveryInput::id($_GET['shipper_id'] ?? null))];
});
