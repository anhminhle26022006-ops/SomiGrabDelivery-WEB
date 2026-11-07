<?php
declare(strict_types=1);

/** PostgreSQL vocabulary; intentionally does not invent delivery/payment transitions. */
final class OrderContract
{
    public const STATUSES = ['PENDING_PAYMENT','SEARCHING_SHIPPER','NO_SHIPPER_FOUND','SHIPPER_ASSIGNED',
        'PICKED_UP','IN_DELIVERY','DELIVERED','RETURNING','RETURNED','CANCELLED'];
    public const BEFORE_ASSIGNMENT = ['PENDING_PAYMENT','SEARCHING_SHIPPER','NO_SHIPPER_FOUND'];

    public static function canCancelBeforeAssignment(string $status): bool
    {
        return in_array($status, self::BEFORE_ASSIGNMENT, true);
    }
}
