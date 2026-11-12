<?php
declare(strict_types=1);
require_once __DIR__ . '/_shipper.php';
require_once __DIR__ . '/../../../app/Services/OfferService.php';

// GET: các offer còn hiệu lực của Shipper đang đăng nhập.
// POST {offer_id, note?}: từ chối một offer.
$method = $_SERVER['REQUEST_METHOD'] ?? '';
shipper_run(['GET', 'POST'], $method === 'POST' ? 'offer.respond_own' : 'offer.read_own', $method === 'POST',
    static function (PDO $db, int $shipperId) use ($method): array {
        $service = new OfferService($db);
        if ($method === 'GET') return [200, $service->pending($shipperId)];
        $input = shipper_body(['offer_id', 'note']);
        return [200, $service->reject($shipperId, DeliveryInput::id($input['offer_id'] ?? null, 'offer_id'), DeliveryInput::text($input['note'] ?? null, 'note', 300, false))];
    });
