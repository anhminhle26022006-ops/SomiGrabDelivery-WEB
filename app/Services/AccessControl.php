<?php
declare(strict_types=1);
require_once __DIR__.'/AuditService.php';

final class AccessControl
{
    public static function requirePermission(PDO $db,int $id,string $role,string $permission): void
    {
        if (!in_array($role,['CUSTOMER','SHIPPER','ADMIN'],true)) throw new DeliveryApiError(403,'PERMISSION_DENIED','Permission denied.');
        $q=$db->prepare("SELECT 1 FROM users u JOIN user_roles ur ON ur.user_id=u.user_id JOIN roles r USING(role_id) JOIN role_permissions rp USING(role_id) JOIN permissions p USING(permission_id) WHERE u.user_id=:id AND u.account_status='ACTIVE' AND r.role_code=:role AND p.permission_code=:permission");
        $q->execute(['id'=>$id,'role'=>$role,'permission'=>$permission]);
        if (!$q->fetchColumn()) throw new DeliveryApiError(403,'PERMISSION_DENIED','Permission denied.');
    }

    public static function requireOrder(PDO $db,int $userId,string $role,int $orderId): array
    {
        $q=$db->prepare('SELECT * FROM orders WHERE order_id=?'); $q->execute([$orderId]);$order=$q->fetch();
        $allowed=$order && $role==='CUSTOMER' && (int)$order['customer_id']===$userId;
        if ($order && $role==='SHIPPER') {
            $q=$db->prepare("SELECT 1 FROM order_assignments WHERE order_id=? AND shipper_id=? AND assignment_status='ACTIVE'");$q->execute([$orderId,$userId]);$allowed=(bool)$q->fetchColumn();
        }
        if (!$allowed) throw new DeliveryApiError(404,'ORDER_NOT_FOUND','Order not found.');
        return $order;
    }

    public static function requireOffer(PDO $db,int $shipperId,int $offerId): array
    {
        $q=$db->prepare('SELECT * FROM order_match_offers WHERE offer_id=? AND shipper_id=?');$q->execute([$offerId,$shipperId]);$offer=$q->fetch();
        if (!$offer) throw new DeliveryApiError(404,'OFFER_NOT_FOUND','Offer not found.');
        return $offer;
    }

    /** Internal helper for Person 3. Caller authenticates the admin and owns a transaction. */
    public static function auditAdminChatRead(PDO $db,int $adminId,int $ticketId,int $orderId): void
    {
        self::requirePermission($db,$adminId,'ADMIN','chat.read_for_support');
        $q=$db->prepare('SELECT 1 FROM support_tickets WHERE ticket_id=? AND order_id=?');$q->execute([$ticketId,$orderId]);
        if (!$q->fetchColumn()) throw new DeliveryApiError(403,'RELATED_TICKET_REQUIRED','A related support ticket is required.');
        AuditService::record($db,$adminId,'ADMIN','CHAT_READ_FOR_TICKET','ORDER',$orderId,[],['ticket_id'=>$ticketId]);
    }
}
