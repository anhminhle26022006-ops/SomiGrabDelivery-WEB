<?php
declare(strict_types=1);
require_once __DIR__.'/AuditService.php';
/** Versioned release of the user's approved 12 November policy; no historical repricing. */
final class ApprovedShipperPolicyRelease
{
    public static function apply(PDO $db): void
    {
        if(!$db->inTransaction())throw new LogicException('Policy release requires a transaction.');
        $db->exec('SELECT pg_advisory_xact_lock(20261112,2)');
        $db->exec('SELECT pg_advisory_xact_lock(20261106,21)');
        $db->exec('SELECT pg_advisory_xact_lock(20261106,22)');
        if($db->query("SELECT 1 FROM audit_logs WHERE action='APPROVED_SHIPPER_POLICY_RELEASED' LIMIT 1")->fetchColumn())return;
        $at=$db->query('SELECT clock_timestamp()')->fetchColumn();
        $db->prepare('UPDATE shipper_score_policies SET effective_until=? WHERE effective_until IS NULL')->execute([$at]);
        $db->prepare('INSERT INTO shipper_score_policies(deduction_points,post_pickup_deduction_points,lock_threshold,recovery_points,max_score,effective_from) VALUES(10,20,60,2,100,?)')->execute([$at]);
        $db->prepare("UPDATE shipper_earning_policies SET effective_until=? WHERE currency_code='VND' AND policy_type IN ('SUCCESSFUL_DELIVERY_PERCENT','FAILED_RETURN_FIXED') AND effective_until IS NULL")->execute([$at]);
        $db->prepare("INSERT INTO shipper_earning_policies(policy_type,currency_code,percentage_rate,fixed_amount_minor,effective_from) VALUES('SUCCESSFUL_DELIVERY_PERCENT','VND',80,NULL,?),('FAILED_RETURN_FIXED','VND',NULL,10000,?)")->execute([$at,$at]);
        AuditService::record($db,null,'SYSTEM','APPROVED_SHIPPER_POLICY_RELEASED','SHIPPER_POLICY',null,[],['release'=>'20261112','successful_percent'=>80,'return_vnd'=>10000,'before_pickup_points'=>10,'after_pickup_points'=>20,'lock_below'=>60,'recovery'=>2,'max_score'=>100,'payout'=>'PER_ORDER_MANUAL']);
    }
}
