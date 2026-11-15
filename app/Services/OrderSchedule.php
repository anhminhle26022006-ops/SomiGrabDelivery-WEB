<?php
declare(strict_types=1);
require_once __DIR__ . '/DeliveryInput.php';

/** Immutable pickup appointment, stored in the existing signed-off quote snapshot. */
final class OrderSchedule
{
    public const MIN_NOTICE_SECONDS = 1800;
    public const MAX_ADVANCE_SECONDS = 604800;
    public const DISPATCH_LEAD_SECONDS = 900;
    public const LATE_SEARCH_SECONDS = 900;

    public static function normalize(mixed $input, ?DateTimeImmutable $now = null): array
    {
        if ($input === null) return ['mode' => 'now', 'at' => null, 'dispatch_at' => null];
        if (!is_array($input)) DeliveryInput::invalid('schedule');
        DeliveryInput::only($input, ['mode', 'at']);
        if (($input['mode'] ?? null) === 'now' && ($input['at'] ?? null) === null)
            return ['mode' => 'now', 'at' => null, 'dispatch_at' => null];
        if (($input['mode'] ?? null) !== 'later' || !is_string($input['at'] ?? null)
            || !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?(?:Z|[+-]\d{2}:\d{2})$/D', $input['at']))
            DeliveryInput::invalid('schedule');
        try { $at = new DateTimeImmutable($input['at']); }
        catch (Throwable) { DeliveryInput::invalid('schedule.at'); }
        $errors = DateTimeImmutable::getLastErrors();
        if ($errors !== false && ($errors['warning_count'] || $errors['error_count'])) DeliveryInput::invalid('schedule.at');
        $timestamp = $at->getTimestamp(); $current = ($now ?? new DateTimeImmutable())->getTimestamp();
        if ($timestamp < $current + self::MIN_NOTICE_SECONDS || $timestamp > $current + self::MAX_ADVANCE_SECONDS)
            throw new DeliveryApiError(422, 'SCHEDULE_OUT_OF_RANGE', 'Schedule pickup at least 30 minutes ahead and within 7 days.');
        return ['mode' => 'later', 'at' => gmdate(DATE_ATOM, $timestamp),
            'dispatch_at' => gmdate(DATE_ATOM, $timestamp - self::DISPATCH_LEAD_SECONDS), 'timezone' => 'Asia/Ho_Chi_Minh'];
    }

    public static function fromOrder(array $order): ?array
    {
        $snapshot = $order['pricing_snapshot'] ?? [];
        if (is_string($snapshot)) $snapshot = json_decode($snapshot, true, 512, JSON_THROW_ON_ERROR);
        $schedule = $snapshot['schedule'] ?? null;
        if (!is_array($schedule) || ($schedule['mode'] ?? null) !== 'later') return null;
        $schedule['dispatch_at'] ??= gmdate(DATE_ATOM, strtotime($schedule['at']) - self::DISPATCH_LEAD_SECONDS);
        return $schedule;
    }

    public static function assertNotExpired(array $schedule): void
    {
        if (($schedule['mode'] ?? null) === 'later' && strtotime($schedule['at']) <= time())
            throw new DeliveryApiError(409, 'SCHEDULE_EXPIRED', 'The pickup appointment has passed. Request a new quote.');
    }

    public static function dueSql(string $alias = 'o'): string
    {
        if (!in_array($alias, ['o', 'orders'], true)) throw new LogicException('Invalid schedule query alias.');
        return "(COALESCE($alias.pricing_snapshot #>> '{schedule,mode}','now') <> 'later' OR COALESCE(($alias.pricing_snapshot #>> '{schedule,dispatch_at}')::timestamptz,($alias.pricing_snapshot #>> '{schedule,at}')::timestamptz-interval '15 minutes') <= now())";
    }

    public static function prioritySql(string $alias = 'o'): string
    {
        if (!in_array($alias, ['o', 'orders'], true)) throw new LogicException('Invalid schedule query alias.');
        return "CASE WHEN $alias.pricing_snapshot #>> '{schedule,mode}' = 'later' THEN 0 ELSE 1 END,
            COALESCE(($alias.pricing_snapshot #>> '{schedule,at}')::timestamptz,$alias.paid_at),$alias.paid_at,$alias.order_id";
    }
}
