<?php
declare(strict_types=1);

final class DistanceWeightPricingRelease
{
    public static function apply(PDO $db): void
    {
        if (!$db->inTransaction()) throw new LogicException('Pricing release requires a transaction.');
        $c = require __DIR__ . '/../Config/delivery-pricing.php';
        foreach (['VND', 'USD'] as $currency) {
            $q = $db->prepare('SELECT COALESCE(MAX(config_version),0)+1 FROM pricing_policies WHERE currency_code = ?');
            $q->execute([$currency]); $version = $q->fetchColumn();
            $q = $db->prepare('INSERT INTO pricing_policies (policy_name,currency_code,return_fee_minor,active,effective_from,config_version,calculation_rule,max_distance_km,base_fee_minor,included_distance_km,per_km_minor,included_weight_kg,per_kg_minor,max_weight_kg) VALUES (:name,:currency,:return_fee,FALSE,now(),:version,:rule,:max_distance,:base,:included_distance,:per_km,:included_weight,:per_kg,:max_weight) RETURNING pricing_policy_id');
            $q->execute(['name'=>$c['release'],'currency'=>$currency,'return_fee'=>$c['return_fee_minor'][$currency],'version'=>$version,'rule'=>$c['rule'],'max_distance'=>$c['max_distance_km'],'base'=>$c['base_fee_minor'][$currency],'included_distance'=>$c['included_distance_km'],'per_km'=>$c['per_km_minor'][$currency],'included_weight'=>$c['included_weight_kg'],'per_kg'=>$c['per_kg_minor'][$currency],'max_weight'=>$c['max_weight_kg']]);
            $id = $q->fetchColumn();
            $q = $db->prepare('INSERT INTO time_price_bands (pricing_policy_id,starts_at_local,ends_at_local,multiplier) VALUES (?,?,?,?)');
            foreach ($c['time_bands'] as $band) $q->execute([$id,$band['start'],$band['end'],$band['multiplier']]);
            $q = $db->prepare('INSERT INTO weather_price_bands (pricing_policy_id,provider_condition_code,label,multiplier) VALUES (?,?,?,?)');
            foreach ($c['weather'] as $condition=>$multiplier) $q->execute([$id,$condition,$condition,$multiplier]);
            $db->prepare('UPDATE pricing_policies SET active = FALSE WHERE currency_code = ? AND active')->execute([$currency]);
            $db->prepare('UPDATE pricing_policies SET active = TRUE WHERE pricing_policy_id = ?')->execute([$id]);
            $q = $db->prepare("INSERT INTO audit_logs (actor_kind,action,entity_type,entity_id,new_value) VALUES ('SYSTEM','DISTANCE_WEIGHT_PRICING_RELEASE','PRICING_POLICY',:id,CAST(:value AS jsonb))");
            $q->execute(['id'=>$id,'value'=>json_encode(['release'=>$c['release'],'config_version'=>$version,'currency_code'=>$currency,'approval'=>'User approved distance and weight proposal in Codex chat, 2026-11-05'],JSON_THROW_ON_ERROR)]);
        }
    }
}
