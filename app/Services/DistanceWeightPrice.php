<?php
declare(strict_types=1);
require_once __DIR__ . '/DeliveryInput.php';

final class DistanceWeightPrice
{
    public static function components(array $policy, string $distance, string $weight): array
    {
        if (bccomp($weight, '0', 3) <= 0 || bccomp($weight, (string) $policy['max_weight_kg'], 3) > 0) {
            throw new DeliveryApiError(422, 'WEIGHT_NOT_SUPPORTED', 'Parcel weight exceeds the supported policy limit.');
        }
        $extraKm = bcsub($distance, (string) $policy['included_distance_km'], 3);
        $extraKg = bcsub($weight, (string) $policy['included_weight_kg'], 3);
        if (bccomp($extraKm, '0', 3) < 0) $extraKm = '0.000';
        if (bccomp($extraKg, '0', 3) < 0) $extraKg = '0.000';
        $distanceFee = bcmul($extraKm, (string) $policy['per_km_minor'], 3);
        $weightFee = bcmul($extraKg, (string) $policy['per_kg_minor'], 3);
        $baseAndDistance = bcadd((string) $policy['base_fee_minor'], $distanceFee, 3);
        return [
            'base_fee_minor'=>(string) $policy['base_fee_minor'],
            'included_distance_km'=>(string) $policy['included_distance_km'], 'included_weight_kg'=>(string) $policy['included_weight_kg'],
            'per_km_minor'=>(string) $policy['per_km_minor'], 'per_kg_minor'=>(string) $policy['per_kg_minor'],
            'extra_distance_km'=>$extraKm, 'extra_weight_kg'=>$extraKg,
            'distance_component_minor'=>$distanceFee, 'weight_component_minor'=>$weightFee,
            'subtotal_minor'=>bcadd($baseAndDistance, $weightFee, 3),
            // Compatibility with orders.distance_fee_minor BIGINT. Exact
            // unrounded components are retained in the quote/order snapshot.
            'distance_fee_minor'=>self::ceil($baseAndDistance),
        ];
    }

    public static function ceil(string $amount): string
    {
        $floor = bcadd($amount, '0', 0);
        return bccomp($amount, $floor, 11) > 0 ? bcadd($floor, '1', 0) : $floor;
    }
}
