<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../app/Config/database.php';
require_once __DIR__ . '/../app/Services/ApprovedPricingRelease.php';
require_once __DIR__ . '/../app/Services/ServiceRangeRelease.php';
require_once __DIR__ . '/../app/Services/DistanceWeightPricingRelease.php';
if (($argv[1] ?? '') !== '--apply') {
    fwrite(STDERR, "Usage: php database/migrate-customer-orders.php --apply\nApplies versioned backend migrations to the configured SOMI database.\n");
    exit(2);
}
$db = null;
try {
    $db = somi_db();
    $db->beginTransaction();
    $db->exec("SELECT pg_advisory_xact_lock(20261105, 1)");
    $db->exec('CREATE TABLE IF NOT EXISTS somi.backend_migrations (version VARCHAR(100) PRIMARY KEY, applied_at TIMESTAMPTZ NOT NULL DEFAULT now())');
    // Preserve either earlier ledger format; never overwrite its history.
    if($db->query("SELECT EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema='somi' AND table_name='schema_migrations' AND column_name='version')")->fetchColumn()){
        $db->exec('INSERT INTO somi.backend_migrations(version,applied_at) SELECT version,applied_at FROM somi.schema_migrations ON CONFLICT(version) DO NOTHING');
    }
    foreach (['20261105_customer_orders', '20261105_approved_pricing', '20261105_service_range_40km', '20261105_distance_weight_pricing', '20261106_person1_backend','20261107_person1_compat','20261108_common_parcel_categories'] as $version) {
    $q = $db->prepare('SELECT 1 FROM somi.backend_migrations WHERE version = :version');
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
    $q = $db->prepare('INSERT INTO somi.backend_migrations (version) VALUES (:version)');
    $q->execute(['version' => $version]);
    }
    $db->commit();
    echo "Backend migrations are up to date, including Person 1 APIs and missing common parcel categories.\n";
} catch (Throwable $error) {
    if ($db instanceof PDO && $db->inTransaction()) $db->rollBack();
    fwrite(STDERR, 'Migration failed (' . get_class($error) . ', code ' . $error->getCode() . "). No partial migration committed.\n");
    exit(1);
}
