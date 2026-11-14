<?php
declare(strict_types=1);
require_once __DIR__ . '/DeliveryContext.php';

/** Đơn đang giao của Shipper, lịch sử, và hai mốc đầu: lấy hàng và bắt đầu giao. */
final class DeliveryService
{
    public function __construct(private PDO $db) {}

    /** Đơn Shipper đang chạy (hoặc null). Chỉ sau khi nhận đơn mới thấy thông tin liên hệ. */
    public function current(int $shipperId): array
    {
        $q = $this->db->prepare(
            "SELECT a.assignment_id, a.assigned_at, o.order_id, o.order_code, o.status,
                    o.pickup_label, o.pickup_address, o.pickup_latitude, o.pickup_longitude, o.sender_name, o.sender_phone,
                    o.delivery_label, o.delivery_address, o.delivery_latitude, o.delivery_longitude, o.receiver_name, o.receiver_phone,
                    o.distance_km, o.estimated_duration_minutes, o.estimated_weight_kg, o.parcel_description, o.customer_note,
                    c.name_vi AS category_name_vi, c.name_en AS category_name_en,
                    d.delivery_id, d.picked_up_at, d.delivery_started_at, d.arrived_at, d.delivered_at, d.failure_reason_code,
                    d.return_started_at, d.returned_at, d.customer_return_confirmed_at
             FROM order_assignments a
             JOIN orders o ON o.order_id = a.order_id
             JOIN parcel_categories c ON c.category_id = o.category_id
             JOIN deliveries d ON d.assignment_id = a.assignment_id
             WHERE a.shipper_id = ? AND a.assignment_status = 'ACTIVE'"
        );
        $q->execute([$shipperId]);
        $delivery = $q->fetch();
        if (!$delivery) return ['delivery' => null];
        $q = $this->db->prepare("SELECT e.evidence_id,e.evidence_type,e.mime_type,e.byte_size,e.uploaded_at,f.file_id FROM delivery_evidence e JOIN private_files f ON f.object_key=e.object_key AND f.owner_id=e.uploaded_by AND f.order_id=? AND f.purpose='EVIDENCE' WHERE e.delivery_id=? AND e.deleted_at IS NULL AND f.deleted_at IS NULL AND f.delete_after>now() ORDER BY e.evidence_id");
        $q->execute([$delivery['order_id'],$delivery['delivery_id']]);
        $delivery['evidence'] = $q->fetchAll();
        $q=$this->db->prepare("SELECT cancellation_request_id,decision,reason,decision_note FROM cancellation_requests WHERE assignment_id=? AND requester_role='SHIPPER' ORDER BY cancellation_request_id DESC LIMIT 1");$q->execute([$delivery['assignment_id']]);$delivery['cancellation_request']=$q->fetch()?:null;
        $delivery['next_actions'] = self::nextActions($delivery);
        return ['delivery' => $delivery];
    }

    /** Những thao tác hợp lệ ở trạng thái hiện tại. Giao diện chỉ hiển thị nút theo danh sách này. */
    private static function nextActions(array $delivery): array
    {
        $types = array_column($delivery['evidence'], 'evidence_type');
        return match ($delivery['status']) {
            'SHIPPER_ASSIGNED' => ['PICKUP'],
            'PICKED_UP' => ['START_DELIVERY', 'FAIL'],
            'IN_DELIVERY' => empty($delivery['arrived_at']) ? ['ARRIVED', 'FAIL']
                : (in_array('DELIVERY_PHOTO', $types, true) ? ['ADD_DELIVERY_PHOTO', 'DELIVERED', 'FAIL'] : ['ADD_DELIVERY_PHOTO', 'FAIL']),
            'RETURNING' => $delivery['returned_at'] !== null ? []
                : (in_array('RETURN_PHOTO', $types, true) ? ['ADD_RETURN_PHOTO', 'RETURNED'] : ['ADD_RETURN_PHOTO']),
            default => [],
        };
    }

    /** Các đơn đã kết thúc của Shipper, mới nhất trước, phân trang bằng before_id. */
    public function history(int $shipperId, ?int $beforeId, int $limit): array
    {
        $limit = max(1, min(50, $limit));
        $q = $this->db->prepare(
            "SELECT a.assignment_id, a.assignment_status, a.assigned_at, a.ended_at, o.order_id, o.order_code, o.status,
                    o.pickup_address, o.delivery_address, o.distance_km,
                    e.gross_amount_minor,e.currency_code,e.payout_status
             FROM order_assignments a JOIN orders o ON o.order_id = a.order_id
             LEFT JOIN shipper_earnings e ON e.order_id=a.order_id AND e.shipper_id=a.shipper_id AND a.assignment_status IN ('COMPLETED','RETURNED')
             WHERE a.shipper_id = ? AND a.assignment_status <> 'ACTIVE' AND (CAST(? AS bigint) IS NULL OR a.assignment_id < CAST(? AS bigint))
             ORDER BY a.assignment_id DESC LIMIT ?"
        );
        $q->execute([$shipperId, $beforeId, $beforeId, $limit]);
        $items=$q->fetchAll();
        return ['items' => $items,'next_before_id'=>count($items)===$limit?(string)$items[count($items)-1]['assignment_id']:null];
    }

    /** Read-only receipt for this shipper's ended assignment, never another courier's delivery. */
    public function historyDetail(int $shipperId,int $assignmentId): array
    {
        $q=$this->db->prepare("SELECT a.assignment_id,a.assignment_status,a.assigned_at,a.ended_at,a.ended_reason,
            o.order_id,o.order_code,o.pickup_address,o.delivery_address,o.sender_name,o.sender_phone,o.receiver_name,o.receiver_phone,o.distance_km,o.parcel_description,o.estimated_weight_kg,o.customer_note,
            c.name_vi AS category_name_vi,c.name_en AS category_name_en,
            d.delivery_id,d.picked_up_at,d.delivery_started_at,d.arrived_at,d.delivered_at,d.return_started_at,d.returned_at,d.customer_return_confirmed_at,d.failure_reason_code,d.failure_note,
            e.gross_amount_minor,e.currency_code,e.payout_status
            FROM order_assignments a JOIN orders o ON o.order_id=a.order_id
            JOIN parcel_categories c ON c.category_id=o.category_id
            LEFT JOIN deliveries d ON d.assignment_id=a.assignment_id
            LEFT JOIN shipper_earnings e ON e.order_id=a.order_id AND e.shipper_id=a.shipper_id AND a.assignment_status IN ('COMPLETED','RETURNED')
            WHERE a.assignment_id=? AND a.shipper_id=? AND a.assignment_status<>'ACTIVE'");
        $q->execute([$assignmentId,$shipperId]);$receipt=$q->fetch();
        if(!$receipt)throw new DeliveryApiError(404,'ORDER_NOT_FOUND','History receipt not found.');
        $q=$this->db->prepare("SELECT new_status,note,created_at FROM order_status_history WHERE order_id=? AND created_at>=CAST(? AS timestamptz) AND created_at<=COALESCE(CAST(? AS timestamptz),now()) ORDER BY status_history_id");
        $q->execute([$receipt['order_id'],$receipt['assigned_at'],$receipt['ended_at']??$receipt['delivered_at']??$receipt['customer_return_confirmed_at']??$receipt['returned_at']??$receipt['assigned_at']]);$receipt['timeline']=$q->fetchAll();
        $q=$this->db->prepare("SELECT reason,decision,decision_note,requested_at FROM cancellation_requests WHERE assignment_id=? AND requested_by=? ORDER BY cancellation_request_id DESC");
        $q->execute([$assignmentId,$shipperId]);$receipt['cancellations']=$q->fetchAll();
        $q=$this->db->prepare("SELECT f.file_id,e.evidence_type,e.uploaded_at FROM delivery_evidence e
            JOIN private_files f ON f.object_key=e.object_key AND f.owner_id=e.uploaded_by AND f.order_id=? AND f.purpose='EVIDENCE'
            WHERE e.delivery_id=? AND e.uploaded_by=? AND e.deleted_at IS NULL AND f.deleted_at IS NULL AND f.delete_after>now() AND e.delete_after>now()
            ORDER BY e.evidence_id");
        $q->execute([$receipt['order_id'],$receipt['delivery_id'],$shipperId]);$receipt['evidence']=$q->fetchAll();
        if(empty($receipt['picked_up_at'])&&!in_array($receipt['assignment_status'],['COMPLETED','RETURNED'],true))unset($receipt['sender_name'],$receipt['sender_phone'],$receipt['receiver_name'],$receipt['receiver_phone']);
        unset($receipt['delivery_id']);
        return ['receipt'=>$receipt];
    }

    /** Shipper xác nhận đã lấy hàng tại điểm gửi: SHIPPER_ASSIGNED -> PICKED_UP. */
    public function pickup(int $shipperId, int $orderId): array
    {
        $ctx = DeliveryContext::lockActive($this->db, $shipperId, $orderId);
        if ($ctx['order']['status'] === 'PICKED_UP') return $this->current($shipperId);
        DeliveryContext::transition($this->db, $ctx['order'], 'PICKED_UP', $shipperId, 'SHIPPER');
        $this->db->prepare('UPDATE orders SET picked_up_at = now() WHERE order_id = ?')->execute([$orderId]);
        $this->db->prepare('UPDATE deliveries SET picked_up_at = now(), updated_at = now() WHERE delivery_id = ?')->execute([$ctx['delivery']['delivery_id']]);
        OrderLifecycleService::event($this->db, $ctx['order'], 'OrderPickedUp', 'picked-up:' . $ctx['assignment']['assignment_id'], ['shipper_id' => (string) $shipperId]);
        return $this->current($shipperId);
    }

    /** Shipper rời điểm lấy hàng để đi giao: PICKED_UP -> IN_DELIVERY. */
    public function startDelivery(int $shipperId, int $orderId): array
    {
        $ctx = DeliveryContext::lockActive($this->db, $shipperId, $orderId);
        if ($ctx['order']['status'] === 'IN_DELIVERY') return $this->current($shipperId);
        DeliveryContext::transition($this->db, $ctx['order'], 'IN_DELIVERY', $shipperId, 'SHIPPER');
        $this->db->prepare('UPDATE deliveries SET delivery_started_at = now(), updated_at = now() WHERE delivery_id = ?')->execute([$ctx['delivery']['delivery_id']]);
        OrderLifecycleService::event($this->db,$ctx['order'],'OrderDelivering','delivering:'.$ctx['assignment']['assignment_id'],['shipper_id'=>(string)$shipperId]);
        return $this->current($shipperId);
    }

    public static function distanceMeters(float $lat, float $lng, float $targetLat, float $targetLng): float
    {
        $a=sin(deg2rad($targetLat-$lat)/2)**2+cos(deg2rad($lat))*cos(deg2rad($targetLat))*sin(deg2rad($targetLng-$lng)/2)**2;
        return 6371000*2*asin(sqrt(min(1,max(0,$a))));
    }

    /** Arrival is a delivery milestone, not proof that the parcel was handed over. */
    public function arrive(int $shipperId, int $orderId): array
    {
        $ctx=DeliveryContext::lockActive($this->db,$shipperId,$orderId);
        if($ctx['order']['status']!=='IN_DELIVERY') throw new DeliveryApiError(409,'INVALID_ORDER_STATE','Arrival requires a delivery in progress.');
        if($ctx['delivery']['arrived_at']!==null) return $this->current($shipperId);
        $q=$this->db->prepare("SELECT latitude,longitude,accuracy_meters FROM shipper_locations WHERE order_id=? AND shipper_id=? AND deleted_at IS NULL AND recorded_at>now()-interval '2 minutes' AND delete_after>now() ORDER BY recorded_at DESC,location_id DESC LIMIT 1");
        $q->execute([$orderId,$shipperId]);$point=$q->fetch();
        if(!$point||$point['accuracy_meters']===null||(float)$point['accuracy_meters']>200
            ||self::distanceMeters((float)$point['latitude'],(float)$point['longitude'],(float)$ctx['order']['delivery_latitude'],(float)$ctx['order']['delivery_longitude'])>200) {
            throw new DeliveryApiError(409,'ARRIVAL_LOCATION_REQUIRED','A recent accurate GPS position within 200 meters of the delivery point is required.');
        }
        $this->db->prepare('UPDATE deliveries SET arrived_at=now(),arrival_latitude=?,arrival_longitude=?,updated_at=now() WHERE delivery_id=?')->execute([$point['latitude'],$point['longitude'],$ctx['delivery']['delivery_id']]);
        OrderLifecycleService::history($this->db,$orderId,'IN_DELIVERY','IN_DELIVERY',$shipperId,'SHIPPER','Đã đến điểm giao hàng (GPS).');
        OrderLifecycleService::event($this->db,$ctx['order'],'OrderArrived','arrived:'.$ctx['assignment']['assignment_id'],['shipper_id'=>(string)$shipperId]);
        return $this->current($shipperId);
    }
}
