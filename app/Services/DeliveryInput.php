<?php
declare(strict_types=1);
require_once __DIR__ . '/DeliveryApiError.php';

final class DeliveryInput
{
    public static function only(array $data, array $keys): void
    {
        if (array_diff(array_keys($data), $keys)) {
            throw new DeliveryApiError(422, 'INVALID_FIELDS', 'Request contains unsupported fields.');
        }
    }

    public static function text($value, string $field, int $max, bool $required = true): ?string
    {
        if ($value === null && !$required) return null;
        if (!is_string($value)) self::invalid($field);
        $value = trim($value);
        if (($required && $value === '') || mb_strlen($value) > $max || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $value)) self::invalid($field);
        return $value === '' && !$required ? null : $value;
    }

    public static function id($value, string $field = 'id'): int
    {
        if ((!is_int($value) && !is_string($value)) || !preg_match('/^[1-9][0-9]{0,17}$/D', (string) $value)) self::invalid($field);
        return (int) $value;
    }

    public static function decimal($value, string $field, int $scale, string $min, string $max): string
    {
        if ((!is_string($value) && !is_int($value) && !is_float($value)) || !preg_match('/^-?[0-9]{1,9}(?:\.[0-9]{1,' . $scale . '})?$/D', (string) $value)) self::invalid($field);
        $value = (string) $value;
        if (bccomp($value, $min, $scale) < 0 || bccomp($value, $max, $scale) > 0) self::invalid($field);
        return bcadd($value, '0', $scale);
    }

    public static function phone($value): string
    {
        $value = self::text($value, 'contact_phone', 20);
        if (!preg_match('/^\+?[0-9]{8,15}$/D', $value)) self::invalid('contact_phone');
        return $value;
    }

    public static function address(array $data): array
    {
        self::only($data, ['label', 'country_code', 'province', 'district', 'ward', 'address_detail', 'latitude', 'longitude', 'is_default']);
        if (($data['country_code'] ?? 'VN') !== 'VN') self::invalid('country_code');
        if (isset($data['is_default']) && !is_bool($data['is_default'])) self::invalid('is_default');
        $out = ['country_code' => 'VN'];
        foreach (['label' => 60, 'province' => 120, 'district' => 120, 'ward' => 120] as $field => $max) {
            $out[$field] = self::text($data[$field] ?? null, $field, $max, false);
        }
        $out['address_detail'] = self::text($data['address_detail'] ?? null, 'address_detail', 255);
        $out['latitude'] = self::decimal($data['latitude'] ?? null, 'latitude', 7, '-90', '90');
        $out['longitude'] = self::decimal($data['longitude'] ?? null, 'longitude', 7, '-180', '180');
        $out['is_default'] = ($data['is_default'] ?? false) ? 'true' : 'false';
        return $out;
    }

    public static function invalid(string $field): void
    {
        throw new DeliveryApiError(422, 'VALIDATION_ERROR', 'Invalid or missing field: ' . $field . '.');
    }
}
