<?php
declare(strict_types=1);
require_once __DIR__.'/environment.php';
require_once __DIR__.'/../Services/DatabaseDiagnostics.php';
somi_load_env();

/**
 * Returns the shared PDO connection to the SOMI PostgreSQL database.
 * Credentials come from server/process variables or ignored local .env.
 * Server/process variables take precedence.
 *
 * Latency matters more than anything else here: against a remote database
 * (Supabase) a fresh TLS connection costs ~0.8s and every round trip ~0.3s.
 *  - Persistent connections are opt-in. Session poolers reserve a backend for
 *    each PHP worker, including idle workers; size the pool before enabling.
 *  - search_path is sent in the connection startup packet instead of a SET
 *    round trip. Poolers that drop startup options (e.g. PgBouncer/Supavisor
 *    transaction mode) need SOMI_DB_SET_SEARCH_PATH=1.
 *  - Statements are sent with PQexecParams (one round trip, parameters still
 *    bound server-side) instead of PREPARE + EXECUTE (two round trips). This
 *    also keeps the app compatible with transaction-mode poolers.
 */
function somi_db(): PDO
{
    if (!extension_loaded('pdo_pgsql')) {
        throw new RuntimeException('PostgreSQL PDO driver is not loaded. Enable pdo_pgsql in the active php.ini or run PHP with -d extension=pdo_pgsql.');
    }
    static $connection = null;

    if ($connection instanceof PDO) {
        return $connection;
    }

    $requiredVariables = [
        'SOMI_DB_HOST',
        'SOMI_DB_PORT',
        'SOMI_DB_NAME',
        'SOMI_DB_USER',
        'SOMI_DB_PASSWORD',
        'SOMI_DB_SSLMODE',
    ];

    $config = [];
    foreach ($requiredVariables as $variable) {
        $value = getenv($variable);
        if ($value === false || $value === '') {
            throw new RuntimeException("Required environment variable is missing: {$variable}");
        }
        $config[$variable] = $value;
    }

    $setSearchPath = getenv('SOMI_DB_SET_SEARCH_PATH') === '1';
    $dsn = sprintf(
        'pgsql:host=%s;port=%s;dbname=%s;sslmode=%s;connect_timeout=8%s',
        $config['SOMI_DB_HOST'],
        $config['SOMI_DB_PORT'],
        $config['SOMI_DB_NAME'],
        $config['SOMI_DB_SSLMODE'],
        // SOMI_database.sql creates application tables inside the `somi` schema.
        $setSearchPath ? '' : ";options='-c search_path=somi,public'"
    );

    $persistent = PHP_SAPI !== 'cli' && getenv('SOMI_DB_PERSISTENT') === '1';
    $disablePrepares = defined('Pdo\Pgsql::ATTR_DISABLE_PREPARES')
        ? constant('Pdo\Pgsql::ATTR_DISABLE_PREPARES')
        : PDO::PGSQL_ATTR_DISABLE_PREPARES;

    $connection = new PDO(
        $dsn,
        $config['SOMI_DB_USER'],
        $config['SOMI_DB_PASSWORD'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_PERSISTENT => $persistent,
            $disablePrepares => true,
        ]
    );

    if ($setSearchPath) {
        $connection->exec('SET search_path TO somi, public');
    }

    return $connection;
}
