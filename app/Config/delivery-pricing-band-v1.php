<?php
declare(strict_types=1);

// Approved by the user on 2026-11-05. Shared by the release migration,
// public Pricing page and Customer demo. Existing order snapshots stay intact.
return [
    'release' => '20261105_approved_pricing_v1',
    'rule' => 'DISTANCE_BAND_X_TIME_X_WEATHER',
    'timezone' => 'Asia/Ho_Chi_Minh',
    'service_area' => 'HO_CHI_MINH_CITY',
    'max_distance_km' => '40.000',
    'distance_bands' => [
        ['min_km' => '0.000', 'max_km' => '3.000', 'VND' => 20000, 'USD' => 99],
        ['min_km' => '3.000', 'max_km' => '5.000', 'VND' => 25000, 'USD' => 129],
        ['min_km' => '5.000', 'max_km' => '10.000', 'VND' => 40000, 'USD' => 199],
        ['min_km' => '10.000', 'max_km' => '15.000', 'VND' => 60000, 'USD' => 299],
        ['min_km' => '15.000', 'max_km' => '20.000', 'VND' => 80000, 'USD' => 399],
    ],
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
