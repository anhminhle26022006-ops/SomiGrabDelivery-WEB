<?php
declare(strict_types=1);

/** Pickup appointments are frozen in the quote/order snapshot, in UTC. */
final class PickupSchedule
{
    public static function validate(mixed $value, bool $creatingOrder = false): array
    {
        if ($value === null) return ['mode' => 'now', 'at' => null];
        if (!is_array($value)) DeliveryInput::invalid('schedule');
        DeliveryInput::only($value, ['mode', 'at']);
        if (($value['mode'] ?? null) === 'now' && empty($value['at'])) return ['mode' => 'now', 'at' => null];
        $raw = $value['at'] ?? null;
        if (($value['mode'] ?? null) !== 'later' || !is_string($raw)
            || !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?(?:Z|[+-]\d{2}:\d{2})$/D', $raw)) DeliveryInput::invalid('schedule');
        try { $at = new DateTimeImmutable($raw); } catch (Exception $e) { DeliveryInput::invalid('schedule.at'); }
        $errors = DateTimeImmutable::getLastErrors();
        if ($errors && ($errors['warning_count'] || $errors['error_count'])) DeliveryInput::invalid('schedule.at');
        $now = new DateTimeImmutable('now');
        if ($at < $now->modify($creatingOrder ? '+0 seconds' : '+30 minutes') || $at > $now->modify('+7 days')) {
            throw new DeliveryApiError(422, 'INVALID_PICKUP_TIME', 'Choose pickup at least 30 minutes ahead and within 7 days.');
        }
        return ['mode' => 'later', 'at' => $at->setTimezone(new DateTimeZone('UTC'))->format(DATE_ATOM)];
    }

    public static function matchingAt(array $order): ?DateTimeImmutable
    {
        $snapshot = is_array($order['pricing_snapshot'] ?? null) ? $order['pricing_snapshot'] : json_decode($order['pricing_snapshot'] ?? '{}', true);
        return ($snapshot['schedule']['mode'] ?? 'now') === 'later'
            ? (new DateTimeImmutable($snapshot['schedule']['at']))->modify('-30 minutes') : null;
    }

    public static function dueSql(string $alias = 'o'): string
    {
        return "(COALESCE($alias.pricing_snapshot #>> '{schedule,mode}', 'now') <> 'later' OR CAST($alias.pricing_snapshot #>> '{schedule,at}' AS timestamptz) <= now() + interval '30 minutes')";
    }
}
