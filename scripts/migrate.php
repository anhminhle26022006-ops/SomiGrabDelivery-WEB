<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
require_once __DIR__ . '/../app/Config/database.php';

try {
    $db = somi_db();
    $db->query("SELECT pg_advisory_lock(hashtext('somi-migrations'))");
    try {
        $exists = $db->query("SELECT to_regclass('somi.users')")->fetchColumn();
        if (!$exists) {
            if (!in_array('--baseline', $argv, true)) throw new RuntimeException('Empty database: run with --baseline to install the canonical schema.');
            $db->exec(file_get_contents(__DIR__ . '/../database/SOMI_database.sql'));
        }
        $db->exec('CREATE TABLE IF NOT EXISTS somi.schema_migrations (filename text PRIMARY KEY, sha256 char(64) NOT NULL, applied_at timestamptz NOT NULL DEFAULT now())');
        $files = glob(__DIR__ . '/../database/migrations/*.sql');
        sort($files);
        foreach ($files as $path) {
            $name = basename($path);
            $sql = file_get_contents($path);
            $hash = hash('sha256', $sql);
            $check = $db->prepare('SELECT sha256 FROM somi.schema_migrations WHERE filename = :name');
            $check->execute(['name' => $name]);
            $old = $check->fetchColumn();
            if ($old !== false) {
                if ($old !== $hash) throw new RuntimeException('Applied migration checksum differs: ' . $name);
                echo "Already applied: $name\n";
                continue;
            }
            $db->beginTransaction();
            try {
                $db->exec($sql);
                $insert = $db->prepare('INSERT INTO somi.schema_migrations(filename, sha256) VALUES (:name, :hash)');
                $insert->execute(['name' => $name, 'hash' => $hash]);
                $db->commit();
                echo "Applied: $name\n";
            } catch (Throwable $error) {
                if ($db->inTransaction()) $db->rollBack();
                throw $error;
            }
        }
    } finally {
        $db->query("SELECT pg_advisory_unlock(hashtext('somi-migrations'))");
    }
} catch (Throwable $error) {
    // Do not expose DSN or driver messages containing connection details.
    fwrite(STDERR, 'Migration failed (' . get_class($error) . '). Review schema/checksums and connectivity.' . PHP_EOL);
    exit(1);
}
