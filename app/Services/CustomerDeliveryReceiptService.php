<?php
declare(strict_types=1);
require_once __DIR__.'/AccessControl.php';

final class CustomerDeliveryReceiptService
{
    public function __construct(private PDO $db) {}

    public function detail(int $customerId,int $orderId):array
    {
        AccessControl::requireOrder($this->db,$customerId,'CUSTOMER',$orderId);
        $q=$this->db->prepare("SELECT d.picked_up_at,d.delivery_started_at,d.arrived_at,d.delivered_at,d.return_started_at,d.returned_at,d.customer_return_confirmed_at,
            d.failure_reason_code,d.failure_note,u.full_name AS shipper_name
            FROM deliveries d JOIN order_assignments a USING(assignment_id) JOIN users u ON u.user_id=a.shipper_id
            WHERE d.order_id=? AND (a.assignment_status IN ('ACTIVE','COMPLETED','RETURNED') OR d.picked_up_at IS NOT NULL)
            ORDER BY a.assignment_id");
        $q->execute([$orderId]);$deliveries=$q->fetchAll();
        $q=$this->db->prepare("SELECT f.file_id,e.evidence_type,e.uploaded_at FROM delivery_evidence e JOIN deliveries d USING(delivery_id)
            JOIN order_assignments a ON a.assignment_id=d.assignment_id AND a.shipper_id=e.uploaded_by
            JOIN private_files f ON f.object_key=e.object_key AND f.owner_id=e.uploaded_by AND f.order_id=d.order_id AND f.purpose='EVIDENCE'
            WHERE d.order_id=? AND e.deleted_at IS NULL AND e.delete_after>now() AND f.deleted_at IS NULL AND f.delete_after>now()
            ORDER BY e.uploaded_at,e.evidence_id");
        $q->execute([$orderId]);
        return ['deliveries'=>$deliveries,'evidence'=>$q->fetchAll()];
    }
}
