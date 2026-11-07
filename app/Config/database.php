<?php
declare(strict_types=1);

require_once __DIR__.'/environment.php';
somi_load_env();

/**
 * Returns the shared PDO connection to the SOMI PostgreSQL database.
 * Credentials come from the server/process environment or the ignored local
 * .env file. Server/process variables take precedence.
 */
function somi_db(): PDO
{
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

    $dsn = sprintf(
        'pgsql:host=%s;port=%s;dbname=%s;sslmode=%s;connect_timeout=8',
        $config['SOMI_DB_HOST'],
        $config['SOMI_DB_PORT'],
        $config['SOMI_DB_NAME'],
        $config['SOMI_DB_SSLMODE']
    );

    $connection = new PDO(
        $dsn,
        $config['SOMI_DB_USER'],
        $config['SOMI_DB_PASSWORD'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            // Bind parameters natively in one call, without a separate named PREPARE round trip.
            PDO::PGSQL_ATTR_DISABLE_PREPARES => true,
        ]
    );

    // SOMI_database.sql creates application tables inside the `somi` schema.
    $connection->exec('SET search_path TO somi, public');

    return $connection;
}
