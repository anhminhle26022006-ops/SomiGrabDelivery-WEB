<?php
declare(strict_types=1);
require_once __DIR__ . '/Input.php';

/** Draft validation only. No invented formula, activation, price or multiplier defaults. */
final class PricingConfiguration
{
    public static function validate(array $input): array
    {
        $currency = Input::choice($input, 'currency_code', ['VND', 'USD']);
        if (!isset($input['configuration']) || !is_array($input['configuration']) || array_is_list($input['configuration'])) {
            throw new ApiProblem(422, 'configuration must be an object.', 'VALIDATION_ERROR');
        }
        $config = $input['configuration'];
        $allowed = ['distance_bands','time_bands','weather_bands','return_fee_minor','cancellation_rules',
            'successful_delivery_percentage','failed_return_fixed_minor','score_policy'];
        if (array_diff(array_keys($config), $allowed) || !$config) throw new ApiProblem(422, 'Unsupported or empty configuration.', 'VALIDATION_ERROR');
        foreach (['return_fee_minor','failed_return_fixed_minor'] as $key) {
            if (isset($config[$key])) self::minor($config[$key]);
        }
        if (isset($config['successful_delivery_percentage'])) Input::number($config, 'successful_delivery_percentage', 0, 100);
        foreach (['distance_bands','time_bands','weather_bands','cancellation_rules'] as $key) {
            if (!array_key_exists($key, $config)) continue;
            if (!is_array($config[$key]) || !array_is_list($config[$key]) || count($config[$key]) > 100) {
                throw new ApiProblem(422, "$key must be an array of up to 100 entries.", 'VALIDATION_ERROR');
            }
            foreach ($config[$key] as $band) {
                if (!is_array($band)) throw new ApiProblem(422, 'Invalid configuration entry.', 'VALIDATION_ERROR');
                if ($key === 'distance_bands') {
                    $min = Input::number($band, 'min_km', 0, 999999);
                    if (!array_key_exists('max_km', $band)) throw new ApiProblem(422, 'max_km is required (null for open-ended).', 'VALIDATION_ERROR');
                    if ($band['max_km'] !== null && Input::number($band, 'max_km', 0, 999999) <= $min) throw new ApiProblem(422, 'Invalid distance interval.', 'VALIDATION_ERROR');
                    self::minor($band['amount_minor'] ?? null);
                } elseif ($key === 'time_bands') {
                    foreach (['starts_at_local','ends_at_local'] as $field) {
                        if (!preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/D', Input::text($band, $field, 5))) throw new ApiProblem(422, 'Invalid local time.', 'VALIDATION_ERROR');
                    }
                    if ($band['starts_at_local'] === $band['ends_at_local']) throw new ApiProblem(422, 'Time interval must not be empty.', 'VALIDATION_ERROR');
                    if (!array_key_exists('day_of_week', $band) || ($band['day_of_week'] !== null && (!is_int($band['day_of_week']) || $band['day_of_week'] < 0 || $band['day_of_week'] > 6))) throw new ApiProblem(422, 'Invalid day_of_week.', 'VALIDATION_ERROR');
                    Input::number($band, 'multiplier', 0, 9999);
                } elseif ($key === 'weather_bands') {
                    Input::text($band, 'provider_condition_code', 80);
                    Input::number($band, 'multiplier', 0, 9999);
                } else {
                    Input::choice($band, 'after_status', ['PENDING_PAYMENT','SEARCHING_SHIPPER','NO_SHIPPER_FOUND','SHIPPER_ASSIGNED','PICKED_UP','IN_DELIVERY']);
                    self::minor($band['fee_minor'] ?? null);
                }
            }
        }
        if (isset($config['score_policy'])) {
            if (!is_array($config['score_policy'])) throw new ApiProblem(422, 'Invalid score policy.', 'VALIDATION_ERROR');
            foreach (['deduction_points','lock_threshold','recovery_points'] as $key) Input::number($config['score_policy'], $key, 0, 99999);
        }
        return ['currency_code' => $currency, 'configuration' => $config];
    }

    public static function minor(mixed $value): int
    {
        if (!is_int($value) || $value < 0 || $value > 9007199254740991) throw new ApiProblem(422, 'Money must be a non-negative safe integer in minor units.', 'VALIDATION_ERROR');
        return $value;
    }
}
