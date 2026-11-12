<?php
declare(strict_types=1);
require_once __DIR__.'/../app/Services/DatabaseDiagnostics.php';
$checks=0;
function check(bool $ok): void { global $checks; $checks++; if (!$ok) throw new RuntimeException('Diagnostic regression'); }
$error=new PDOException('SQLSTATE[08006] [7] FATAL: (EMAXCONNSESSION) max clients reached; password=fixture-secret',7);
check(somi_database_diagnostic($error)==='sqlstate=08006 category=connection_limit');
check(strpos(somi_database_diagnostic($error),'fixture-secret')===false);
$error=new PDOException('private driver detail');$error->errorInfo=['08006',7,'private driver detail'];
check(somi_database_diagnostic($error)==='sqlstate=08006 category=connection_failure');
foreach(['53300'=>'connection_limit','42P01'=>'schema_mismatch','42703'=>'schema_mismatch','40001'=>'concurrent_change','40P01'=>'concurrent_change'] as $state=>$kind){
    $error=new PDOException('private');$error->errorInfo=[$state];
    check(somi_database_diagnostic($error)==='sqlstate='.$state.' category='.$kind);
}
check(somi_database_diagnostic(new RuntimeException('secret'))==='sqlstate=unknown category=unclassified');
class BrokenRollback extends PDO {
    public function __construct() {}
    public function inTransaction(): bool { return true; }
    public function rollBack(): bool { throw new PDOException('SQLSTATE[08006] lost connection'); }
}
somi_rollback_safely(new BrokenRollback());check(true);
echo json_encode(['result'=>'PASS','checks'=>$checks,'external_requests'=>0]).PHP_EOL;
