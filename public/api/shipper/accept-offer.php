<?php
declare(strict_types=1);
require_once __DIR__ . '/_shipper.php';
require_once __DIR__ . '/../../../app/Services/OfferAcceptService.php';

// POST {offer_id}: nhận đơn. 201 khi vừa nhận, 200 khi gọi lại sau lần nhận thành công.
// 409 OFFER_LOST_RACE / OFFER_EXPIRED / ORDER_NOT_AVAILABLE khi không còn nhận được.
shipper_run(['POST'], 'offer.respond_own', true, static function (PDO $db, int $shipperId): array {
    $input = shipper_body(['offer_id']);
    $result = (new OfferAcceptService($db))->accept($shipperId, DeliveryInput::id($input['offer_id'] ?? null, 'offer_id'));
    return [$result['created'] ? 201 : 200, $result];
});
