<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
require_once __DIR__.'/../app/Config/database.php';
$db=somi_db();
try {
    $db->beginTransaction();
    $db->exec('SET LOCAL search_path TO pg_temp, pg_catalog');
    $db->exec('CREATE TEMP TABLE parcel_categories (category_code text PRIMARY KEY, name_vi text, name_en text, restriction_type text, active boolean)');
    $sql=file_get_contents(__DIR__.'/../database/migrations/20261108_common_parcel_categories.sql');
    $sql=preg_replace('/^BEGIN;\s*|^COMMIT;\s*|^SET LOCAL search_path[^;]+;\s*/m','',$sql);
    $db->exec($sql);
    if ((int)$db->query("SELECT count(*) FROM parcel_categories WHERE active AND restriction_type='ALLOWED' AND name_vi<>'' AND name_en<>''")->fetchColumn()!==6) throw new RuntimeException('Missing bilingual defaults');
    $db->exec("UPDATE parcel_categories SET active=false, restriction_type='PROHIBITED', name_vi='Admin edited' WHERE category_code='GIFTS'");
    $db->exec($sql);
    if ((int)$db->query('SELECT count(*) FROM parcel_categories')->fetchColumn()!==6) throw new RuntimeException('Duplicate defaults');
    if (!$db->query("SELECT 1 FROM parcel_categories WHERE category_code='GIFTS' AND NOT active AND restriction_type='PROHIBITED' AND name_vi='Admin edited'")->fetchColumn()) throw new RuntimeException('Admin changes overwritten');
    $db->rollBack();
    echo "PASS: 3 category checks; TEMP tables only; rolled back.\n";
} catch (Throwable $error) {
    somi_rollback_safely($db);
    fwrite(STDERR,'Category test failed: '.somi_database_diagnostic($error).PHP_EOL);
    exit(1);
}
