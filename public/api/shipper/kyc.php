<?php
declare(strict_types=1);
require_once __DIR__ . '/_shipper.php';
require_once __DIR__ . '/../../../app/Services/ShipperKycService.php';

// GET: trạng thái duyệt, lý do bị trả về và tài liệu đã nộp.
// POST {identity?: {document_type, document_country_code, identity_number},
//       documents?: [{document_type, reference_file_id}]}
//      nộp lại hồ sơ khi đang NEEDS_INFO hoặc REJECTED.
$method = $_SERVER['REQUEST_METHOD'] ?? '';
shipper_run(['GET', 'POST'], $method === 'POST' ? 'profile.update_own' : 'profile.read_own', $method === 'POST',
    static function (PDO $db, int $shipperId) use ($method): array {
        $service = new ShipperKycService($db);
        if ($method === 'GET') return [200, $service->status($shipperId)];
        $input = shipper_body(['identity', 'documents']);
        $identity = $input['identity'] ?? null;
        $documents = $input['documents'] ?? [];
        if (($identity !== null && !is_array($identity)) || !is_array($documents) || !array_is_list($documents)) {
            throw new DeliveryApiError(422, 'VALIDATION_ERROR', 'identity must be an object and documents must be a list.');
        }
        return [200, $service->resubmit($shipperId, $identity, $documents)];
    });
