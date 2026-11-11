<?php
declare(strict_types=1);
require_once __DIR__ . '/_shipper.php';
require_once __DIR__ . '/../../../app/Services/DeliveryCompletionService.php';

// POST {order_id, evidence_type: "DELIVERY_PHOTO" | "RETURN_PHOTO", file_reference, mime_type, byte_size, checksum_sha256?}
shipper_run(['POST'], 'delivery.upload_evidence', true, static function (PDO $db, int $shipperId): array {
    $input = shipper_body(['order_id', 'evidence_type', 'file_reference', 'mime_type', 'byte_size', 'checksum_sha256']);
    return [201, (new DeliveryCompletionService($db))->addEvidence(
        $shipperId,
        DeliveryInput::id($input['order_id'] ?? null, 'order_id'),
        ShipperInput::choice($input['evidence_type'] ?? null, 'evidence_type', ['DELIVERY_PHOTO', 'RETURN_PHOTO']),
        $input['file_reference'] ?? null, $input['mime_type'] ?? null, $input['byte_size'] ?? null, $input['checksum_sha256'] ?? null
    )];
});
