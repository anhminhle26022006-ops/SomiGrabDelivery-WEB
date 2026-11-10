<?php
declare(strict_types=1);
require_once __DIR__ . '/DeliveryApiError.php';

/** Kiểm tra dữ liệu đầu vào dùng riêng cho các API Shipper. */
final class ShipperInput
{
    /** Đọc tham số vận hành trong app/Config/shipper-matching.php. */
    public static function config(string $key): int|float
    {
        static $config = null;
        $config ??= require __DIR__ . '/../Config/shipper-matching.php';
        return $config[$key];
    }

    /** Tọa độ phải là số hữu hạn nằm trong khoảng hợp lệ; trả về chuỗi 7 chữ số thập phân. */
    public static function coordinate(mixed $value, string $field, float $limit): string
    {
        if ((!is_int($value) && !is_float($value) && !is_string($value)) || !is_numeric($value)
            || !is_finite((float) $value) || abs((float) $value) > $limit) {
            throw new DeliveryApiError(422, 'VALIDATION_ERROR', "Invalid field: $field.");
        }
        return number_format((float) $value, 7, '.', '');
    }

    public static function boolean(mixed $value, string $field): bool
    {
        if (!is_bool($value)) throw new DeliveryApiError(422, 'VALIDATION_ERROR', "Invalid field: $field.");
        return $value;
    }

    public static function choice(mixed $value, string $field, array $choices): string
    {
        if (!is_string($value) || !in_array($value, $choices, true)) {
            throw new DeliveryApiError(422, 'VALIDATION_ERROR', "Invalid field: $field.");
        }
        return $value;
    }
}
