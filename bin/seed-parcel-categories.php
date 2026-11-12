<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__.'/../app/Config/database.php';
if (($argv[1] ?? '') !== '--apply') {
    fwrite(STDERR, "Usage: php bin/seed-parcel-categories.php --apply\nAdds missing common categories; preserves existing Admin settings.\n");
    exit(2);
}
$db = null;
try {
    $db = somi_db();
    $db->exec(file_get_contents(__DIR__.'/../database/migrations/20261108_common_parcel_categories.sql'));
    $allowed = (int)$db->query("SELECT count(*) FROM somi.parcel_categories WHERE active AND restriction_type='ALLOWED'")->fetchColumn();
    echo json_encode(['result'=>'PASS', 'active_allowed_categories'=>$allowed, 'existing_categories_preserved'=>true], JSON_PRETTY_PRINT).PHP_EOL;
} catch (Throwable $error) {
    if ($db instanceof PDO) somi_rollback_safely($db);
    fwrite(STDERR, 'Category setup failed: '.somi_database_diagnostic($error).PHP_EOL);
    exit(1);
}
