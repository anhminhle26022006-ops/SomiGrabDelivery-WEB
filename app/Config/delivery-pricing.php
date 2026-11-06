<?php
declare(strict_types=1);

// Approved by the user on 2026-11-05. Shared by the release migration,
// public Pricing page and Customer demo. Existing order snapshots stay intact.
return [
    'release' => '20261105_distance_weight_pricing',
    'rule' => 'BASE_PLUS_DISTANCE_PLUS_WEIGHT',
    'timezone' => 'Asia/Ho_Chi_Minh',
    'service_area' => 'HO_CHI_MINH_CITY',
    'max_distance_km' => '40.000',
    'included_distance_km' => '3.000',
    'included_weight_kg' => '3.000',
    'max_weight_kg' => '30.000',
    'base_fee_minor' => ['VND' => 20000, 'USD' => 99],
    'per_km_minor' => ['VND' => 4000, 'USD' => 20],
    'per_kg_minor' => ['VND' => 2500, 'USD' => 10],
    'time_bands' => [
        ['start' => '00:00:00', 'end' => '07:00:00', 'multiplier' => '1.0000'],
        ['start' => '07:00:00', 'end' => '09:00:00', 'multiplier' => '1.2000'],
        ['start' => '09:00:00', 'end' => '17:00:00', 'multiplier' => '1.0000'],
        ['start' => '17:00:00', 'end' => '20:00:00', 'multiplier' => '1.2000'],
        ['start' => '20:00:00', 'end' => '00:00:00', 'multiplier' => '1.0000'],
    ],
    'weather' => ['NORMAL' => '1.0000', 'LIGHT_RAIN' => '1.1000', 'RAIN' => '1.2000'],
    'return_fee_minor' => ['VND' => 10000, 'USD' => 50],
    'cancellation_before_assignment_minor' => 0,
];
