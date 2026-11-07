<?php
declare(strict_types=1);

final class ServiceRangeRelease
{
    /** Publish a new policy version without changing any approved prices. */
    public static function apply(PDO $db): void
    {
        if (!$db->inTransaction()) throw new LogicException('Service range release requires a transaction.');
        $policies = $db->query('SELECT * FROM pricing_policies WHERE active ORDER BY currency_code FOR UPDATE')->fetchAll();
        foreach ($policies as $old) {
            $q = $db->prepare('SELECT COALESCE(MAX(config_version), 0) + 1 FROM pricing_policies WHERE currency_code = ?');
            $q->execute([$old['currency_code']]); $version = $q->fetchColumn();
            $q = $db->prepare("INSERT INTO pricing_policies (policy_name,currency_code,return_fee_minor,active,effective_from,effective_until,config_version,calculation_rule,max_distance_km) VALUES ('20261105_service_range_40km',:currency,:return_fee,FALSE,now(),:until,:version,:rule,40) RETURNING pricing_policy_id");
            $q->execute(['currency'=>$old['currency_code'],'return_fee'=>$old['return_fee_minor'],'until'=>$old['effective_until'],'version'=>$version,'rule'=>$old['calculation_rule']]);
            $id = $q->fetchColumn();
            foreach (['distance_price_bands'=>'min_km,max_km,amount_minor', 'time_price_bands'=>'day_of_week,starts_at_local,ends_at_local,multiplier', 'weather_price_bands'=>'provider_condition_code,label,multiplier', 'cancellation_fee_rules'=>'after_status,fee_minor'] as $table=>$columns) {
                $q = $db->prepare('INSERT INTO ' . $table . ' (pricing_policy_id,' . $columns . ') SELECT :new_id,' . $columns . ' FROM ' . $table . ' WHERE pricing_policy_id = :old_id');
                $q->execute(['new_id'=>$id,'old_id'=>$old['pricing_policy_id']]);
            }
            $db->prepare('UPDATE pricing_policies SET active = FALSE WHERE pricing_policy_id = ?')->execute([$old['pricing_policy_id']]);
            $db->prepare('UPDATE pricing_policies SET active = TRUE WHERE pricing_policy_id = ?')->execute([$id]);
            $q = $db->prepare("INSERT INTO audit_logs (actor_kind,action,entity_type,entity_id,old_value,new_value) VALUES ('SYSTEM','SERVICE_RANGE_40KM','PRICING_POLICY',:id,CAST(:old AS jsonb),CAST(:new AS jsonb))");
            $q->execute(['id'=>$id,'old'=>json_encode(['pricing_policy_id'=>$old['pricing_policy_id'],'max_distance_km'=>$old['max_distance_km']]),'new'=>json_encode(['pricing_policy_id'=>$id,'max_distance_km'=>'40.000','config_version'=>$version,'approval'=>'User requested 40 km in Codex chat, 2026-11-05'])]);
        }
    }
}
