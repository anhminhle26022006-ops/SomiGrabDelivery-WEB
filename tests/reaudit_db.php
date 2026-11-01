<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
require __DIR__ . '/../app/Config/database.php';
$started=microtime(true);
try {
 $db=somi_db();$db->beginTransaction();$db->exec('SET TRANSACTION READ ONLY');
 $db->query('SELECT 1')->fetchColumn();
 $counts=$db->query("SELECT r.role_code, u.account_status, count(*) AS total FROM users u JOIN user_roles ur USING(user_id) JOIN roles r USING(role_id) GROUP BY r.role_code,u.account_status ORDER BY 1,2")->fetchAll();
 $tables=$db->query("SELECT table_name FROM information_schema.tables WHERE table_schema='somi' AND table_name IN ('users','roles','user_roles','shipper_profiles','user_identity_documents','email_verifications','phone_verifications','password_reset_tokens','audit_logs') ORDER BY 1")->fetchAll(PDO::FETCH_COLUMN);
 $db->rollBack();
 $result=['result'=>'PASS','read_only'=>true,'elapsed_ms'=>(int)round((microtime(true)-$started)*1000),'role_status_counts'=>$counts,'required_tables_found'=>$tables];
} catch(Throwable $e) {$result=['result'=>'BLOCKED','error_class'=>get_class($e)];}
file_put_contents(__DIR__.'/../docs/reaudit-db-results.json',json_encode($result,JSON_PRETTY_PRINT));
echo json_encode(['result'=>$result['result'],'read_only'=>true]);
