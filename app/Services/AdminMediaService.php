<?php
declare(strict_types=1);
require_once __DIR__.'/PrivateStorageService.php';

/** Lists only registered, retained documents; bytes still use audited Storage authorization. */
final class AdminMediaService
{
    public function __construct(private PDO $db) {}
    public function listing(int $admin, array $in): array
    {
        AccessControl::requirePermission($this->db,$admin,'ADMIN','storage.read_support');
        if(isset($in['shipper_id']) === isset($in['order_id'])) DeliveryInput::invalid('shipper_id or order_id');
        $before=isset($in['before_id'])?DeliveryInput::id($in['before_id']):PHP_INT_MAX;
        if(isset($in['shipper_id'])) {
            AccessControl::requirePermission($this->db,$admin,'ADMIN','shipper.verify');
            $id=DeliveryInput::id($in['shipper_id']);
            $sql="SELECT f.file_id,f.original_name,f.mime_type,f.byte_size,d.document_type AS kind,d.uploaded_at AS created_at
                FROM private_files f JOIN shipper_documents d ON d.object_key=f.object_key AND d.shipper_id=f.owner_id
                WHERE d.shipper_id=? AND f.purpose='KYC' AND d.deleted_at IS NULL AND (d.delete_after IS NULL OR d.delete_after>now())";
            $action='KYC_FILES_LIST';$entity='SHIPPER_PROFILE';
        } else {
            AccessControl::requirePermission($this->db,$admin,'ADMIN','order.read_all');
            AccessControl::requirePermission($this->db,$admin,'ADMIN','support.manage_all');
            $id=DeliveryInput::id($in['order_id']);
            $sql="SELECT f.file_id,f.original_name,f.mime_type,f.byte_size,e.evidence_type AS kind,e.uploaded_at AS created_at
                FROM private_files f JOIN delivery_evidence e ON e.object_key=f.object_key AND e.uploaded_by=f.owner_id
                JOIN deliveries d ON d.delivery_id=e.delivery_id
                WHERE d.order_id=? AND f.order_id=d.order_id AND f.purpose='EVIDENCE' AND e.deleted_at IS NULL AND e.delete_after>now()";
            $action='ORDER_EVIDENCE_LIST';$entity='ORDER';
        }
        $q=$this->db->prepare($sql.' AND f.deleted_at IS NULL AND f.delete_after>now() AND f.file_id<? ORDER BY f.file_id DESC LIMIT 51');
        $q->execute([$id,$before]);$items=$q->fetchAll();$more=count($items)>50;if($more)array_pop($items);
        AuditService::record($this->db,$admin,'ADMIN',$action,$entity,$id);
        return ['items'=>$items,'next_before_id'=>$more?(string)end($items)['file_id']:null];
    }
}
