<?php
declare(strict_types=1);
require_once __DIR__.'/AccessControl.php';

/** Shared predicates keep navigation counts and list priorities consistent. */
final class AdminWorkQueue {
    public const ORDER_WORK="(o.status='NO_SHIPPER_FOUND' OR EXISTS(SELECT 1 FROM cancellation_requests c WHERE c.order_id=o.order_id AND c.requester_role='SHIPPER' AND c.decision='PENDING'))";
    public const FINANCE_WORK="(u.email NOT LIKE '%@demo.somi.invalid' AND ((p.provider='MANUAL_BANK' AND p.status IN ('PENDING','PROCESSING')) OR EXISTS(SELECT 1 FROM refunds r WHERE r.order_id=o.order_id AND r.status IN ('PENDING','PROCESSING','FAILED') AND r.provider<>'DEMO') OR EXISTS(SELECT 1 FROM shipper_earnings e LEFT JOIN shipper_payouts sp USING(earning_id) WHERE e.order_id=o.order_id AND (sp.status IN ('PENDING','PROCESSING','FAILED') OR (sp.payout_id IS NULL AND e.payout_status='PENDING'))) OR (p.status IN ('PAID','PARTIALLY_REFUNDED') AND o.status IN ('CANCELLED','RETURNED') AND NOT EXISTS(SELECT 1 FROM refunds r WHERE r.order_id=o.order_id))))";
    public static function rank(string $predicate):string{return "CASE WHEN $predicate THEN 0 ELSE 1 END";}
    /** Composite priority + ID cursor, including compatibility with existing before_id clients. */
    public static function page(PDO $db,string $sql,array $args,string $id,array $in,int $limit=50):array{
        $query='WITH work_items AS ('.$sql.') SELECT w.* FROM work_items w';
        if(isset($in['before_id'])){
            $before=DeliveryInput::id($in['before_id']);$priority=$in['before_priority']??null;
            if($priority!==null&&((!is_int($priority)&&!is_string($priority))||!in_array((string)$priority,['0','1'],true)))DeliveryInput::invalid('before_priority');
            $query.=" CROSS JOIN (SELECT COALESCE(?::integer,(SELECT work_priority FROM work_items WHERE $id=?),1) AS priority) b WHERE (w.work_priority>b.priority OR (w.work_priority=b.priority AND w.$id<?))";
            array_push($args,$priority,$before,$before);
        }
        $query.=" ORDER BY w.work_priority,w.$id DESC LIMIT ".($limit+1);
        $q=$db->prepare($query);$q->execute($args);$items=$q->fetchAll();$more=count($items)>$limit;if($more)array_pop($items);$last=$items?end($items):null;
        return ['items'=>$items,'next_before_id'=>$more?(string)$last[$id]:null,'next_priority'=>$more?(int)$last['work_priority']:null];
    }
    public static function counts(PDO $db,int $admin):array{
        foreach(['account.read','order.read_all','payment.reconcile','support.manage_all'] as $permission)AccessControl::requirePermission($db,$admin,'ADMIN',$permission);
        $q=$db->prepare("SELECT
          (SELECT count(*) FROM shipper_profiles WHERE verification_status='PENDING') AS shippers,
          (SELECT count(*) FROM shipper_profiles sp WHERE sp.verification_status='PENDING' AND EXISTS(SELECT 1 FROM user_roles ur JOIN roles r USING(role_id) WHERE ur.user_id=sp.user_id AND r.role_code='CUSTOMER')) AS customers,
          (SELECT count(*) FROM support_tickets WHERE status IN ('NEW','IN_PROGRESS')) AS complaints,
          (SELECT count(*) FROM orders o WHERE ".self::ORDER_WORK.") AS orders,
          (SELECT count(*) FROM orders o JOIN users u ON u.user_id=o.customer_id LEFT JOIN payments p USING(order_id) WHERE ".self::FINANCE_WORK.") AS payments,
          ((SELECT count(*) FROM (SELECT DISTINCT ON(customer_id) review_status FROM customer_bank_accounts ORDER BY customer_id,bank_account_id DESC) b WHERE review_status='PENDING')+(SELECT count(*) FROM shipper_bank_accounts WHERE replaced_at IS NULL AND review_status='PENDING')) AS \"bank-accounts\",
          (SELECT count(*) FROM nationality_change_requests WHERE status='PENDING') AS nationality,
          (SELECT count(*) FROM background_jobs WHERE status='FAILED') AS operations,
          (SELECT count(*) FROM notifications WHERE user_id=? AND read_at IS NULL AND recipient_role='ADMIN') AS notifications");
        $q->execute([$admin]);$counts=array_map('intval',$q->fetch());$counts['users']=$counts['shippers'];return ['counts'=>$counts];
    }
}
