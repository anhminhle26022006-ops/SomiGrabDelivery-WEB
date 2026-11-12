<?php
declare(strict_types=1);

/** Fixed diagnostic labels only; never log a DSN, credentials or raw exception. */
function somi_database_diagnostic(Throwable $error): string
{
    $state = $error instanceof PDOException ? (string)($error->errorInfo[0] ?? $error->getCode()) : '';
    // libpq connection failures sometimes expose only driver code 7.
    if (!preg_match('/^[A-Z0-9]{5}$/D', $state) && preg_match('/SQLSTATE\[([A-Z0-9]{5})\]/', $error->getMessage(), $match)) $state = $match[1];
    $state = preg_match('/^[A-Z0-9]{5}$/D', $state) ? $state : 'unknown';
    $kind = 'unclassified';
    if ($error instanceof PDOException) {
        if (strpos($error->getMessage(), 'EMAXCONNSESSION') !== false || $state === '53300') $kind = 'connection_limit';
        elseif (substr($state, 0, 2) === '08') $kind = 'connection_failure';
        elseif (in_array($state, ['42P01', '42703'], true)) $kind = 'schema_mismatch';
        elseif (in_array($state, ['40001', '40P01'], true)) $kind = 'concurrent_change';
    }
    return 'sqlstate='.$state.' category='.$kind;
}

function somi_rollback_safely(PDO $db): void
{
    try { if ($db->inTransaction()) $db->rollBack(); }
    catch (Throwable $error) { error_log('[SOMI rollback] '.somi_database_diagnostic($error)); }
}
