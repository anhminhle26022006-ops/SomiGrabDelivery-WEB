<?php
declare(strict_types=1);

/** Versioned, additive release; caller owns the migration transaction. */
final class ShipperApplicationSchema
{
    public static function apply(PDO $db): void
    {
        if (!$db->inTransaction()) throw new LogicException('Migration requires a transaction.');
        $db->exec("ALTER TABLE shipper_profiles ADD COLUMN IF NOT EXISTS application_details JSONB CHECK (application_details IS NULL OR jsonb_typeof(application_details) = 'object')");
        $db->exec('ALTER TABLE shipper_profiles ADD COLUMN IF NOT EXISTS application_submitted_at TIMESTAMPTZ');
    }
}
