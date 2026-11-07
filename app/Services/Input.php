<?php
declare(strict_types=1);
require_once __DIR__ . '/ApiProblem.php';

final class Input
{
    public static function text(array $input, string $key, int $max, bool $required = true): string
    {
        $value = $input[$key] ?? '';
        if (!is_string($value) || ($required && trim($value) === '') || mb_strlen(trim($value)) > $max) {
            throw new ApiProblem(422, "Invalid field: $key.", 'VALIDATION_ERROR');
        }
        return trim($value);
    }

    public static function id(mixed $value): int
    {
        if (is_bool($value) || !is_scalar($value) || !preg_match('/^[1-9][0-9]*$/D', (string) $value)
            || filter_var($value, FILTER_VALIDATE_INT) === false) {
            throw new ApiProblem(422, 'A positive integer ID is required.', 'VALIDATION_ERROR');
        }
        return (int) $value;
    }

    public static function number(array $input, string $key, float $min, float $max): float
    {
        $value = $input[$key] ?? null;
        if ((!is_int($value) && !is_float($value) && !is_string($value)) || !is_numeric($value)
            || !is_finite((float) $value) || (float) $value < $min || (float) $value > $max) {
            throw new ApiProblem(422, "Invalid field: $key.", 'VALIDATION_ERROR');
        }
        return (float) $value;
    }

    public static function choice(array $input, string $key, array $choices): string
    {
        $value = self::text($input, $key, 80);
        if (!in_array($value, $choices, true)) throw new ApiProblem(422, "Invalid field: $key.", 'VALIDATION_ERROR');
        return $value;
    }

    public static function boolean(array $input, string $key): bool
    {
        if (!isset($input[$key]) || !is_bool($input[$key])) throw new ApiProblem(422, "Invalid field: $key.", 'VALIDATION_ERROR');
        return $input[$key];
    }
}
