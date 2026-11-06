<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../app/Config/database.php';
require_once __DIR__ . '/../app/Services/ApprovedPricingRelease.php';
require_once __DIR__ . '/../app/Services/ServiceRangeRelease.php';
require_once __DIR__ . '/../app/Services/DistanceWeightPricingRelease.php';
if (($argv[1] ?? '') !== '--apply') {
    fwrite(STDERR, "Usage: php database/migrate-customer-orders.php --apply\nApplies the additive customer-order migration to the configured SOMI database.\n");
    exit(2);
}
$db = null;
try {
    $db = somi_db();
    $db->beginTransaction();
    $db->exec("SELECT pg_advisory_xact_lock(20261105, 1)");
    $db->exec('CREATE TABLE IF NOT EXISTS somi.schema_migrations (version VARCHAR(100) PRIMARY KEY, applied_at TIMESTAMPTZ NOT NULL DEFAULT now())');
    foreach (['20261105_customer_orders', '20261105_approved_pricing', '20261105_service_range_40km', '20261105_distance_weight_pricing'] as $version) {
    $q = $db->prepare('SELECT 1 FROM somi.schema_migrations WHERE version = :version');
    $q->execute(['version' => $version]);
    if ($q->fetchColumn()) continue;
    if ($version === '20261105_service_range_40km') ServiceRangeRelease::apply($db);
    else {
    $sql = file_get_contents(__DIR__ . '/migrations/' . $version . '.sql');
    $sql = preg_replace('/^BEGIN;\s*|^COMMIT;\s*/m', '', $sql);
    $db->exec($sql);
    if ($version === '20261105_approved_pricing') ApprovedPricingRelease::apply($db);
    if ($version === '20261105_distance_weight_pricing') DistanceWeightPricingRelease::apply($db);
    }
    $q = $db->prepare('INSERT INTO somi.schema_migrations (version) VALUES (:version)');
    $q->execute(['version' => $version]);
    }
    $db->commit();
    echo "Customer-order and approved-pricing migrations are up to date. No parcel categories seeded.\n";
} catch (Throwable $error) {
    if ($db instanceof PDO && $db->inTransaction()) $db->rollBack();
    fwrite(STDERR, 'Migration failed (' . get_class($error) . ', code ' . $error->getCode() . "). No partial migration committed.\n");
    exit(1);
}
