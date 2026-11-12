<?php
declare(strict_types=1);

final class ApprovedPricingRelease
{
    /** Caller owns transaction and migration lock. Never modify old policies or orders. */
    public static function apply(PDO $db): void
    {
        if (!$db->inTransaction()) throw new LogicException('Pricing release requires a transaction.');
        $config = require __DIR__ . '/../Config/delivery-pricing-band-v1.php';
        foreach (['VND', 'USD'] as $currency) {
            $q = $db->prepare('SELECT COALESCE(MAX(config_version), 0) + 1 FROM pricing_policies WHERE currency_code = :currency');
            $q->execute(['currency' => $currency]); $version = $q->fetchColumn();
            $q = $db->prepare('INSERT INTO pricing_policies (policy_name, currency_code, return_fee_minor, active, effective_from, config_version, calculation_rule, max_distance_km) VALUES (:name, :currency, :return_fee, FALSE, now(), :version, :rule, :max) RETURNING pricing_policy_id');
            $q->execute(['name' => $config['release'], 'currency' => $currency, 'return_fee' => $config['return_fee_minor'][$currency], 'version' => $version, 'rule' => $config['rule'], 'max' => $config['max_distance_km']]);
            $id = $q->fetchColumn();
            $q = $db->prepare('INSERT INTO distance_price_bands (pricing_policy_id, min_km, max_km, amount_minor) VALUES (?, ?, ?, ?)');
            foreach ($config['distance_bands'] as $band) $q->execute([$id, $band['min_km'], $band['max_km'], $band[$currency]]);
            $q = $db->prepare('INSERT INTO time_price_bands (pricing_policy_id, starts_at_local, ends_at_local, multiplier) VALUES (?, ?, ?, ?)');
            foreach ($config['time_bands'] as $band) $q->execute([$id, $band['start'], $band['end'], $band['multiplier']]);
            $q = $db->prepare('INSERT INTO weather_price_bands (pricing_policy_id, provider_condition_code, label, multiplier) VALUES (?, ?, ?, ?)');
            foreach ($config['weather'] as $condition => $multiplier) $q->execute([$id, $condition, $condition, $multiplier]);
            $q = $db->prepare('UPDATE pricing_policies SET active = FALSE WHERE currency_code = :currency AND active');
            $q->execute(['currency' => $currency]);
            $db->prepare('UPDATE pricing_policies SET active = TRUE WHERE pricing_policy_id = ?')->execute([$id]);
            $q = $db->prepare("INSERT INTO audit_logs (actor_kind, action, entity_type, entity_id, new_value) VALUES ('SYSTEM', 'APPROVED_PRICING_RELEASE', 'PRICING_POLICY', :id, CAST(:value AS jsonb))");
            $q->execute(['id' => $id, 'value' => json_encode(['release' => $config['release'], 'currency_code' => $currency, 'config_version' => $version, 'approval' => 'User approval in Codex chat, 2026-11-05'], JSON_THROW_ON_ERROR)]);
        }
    }
}
