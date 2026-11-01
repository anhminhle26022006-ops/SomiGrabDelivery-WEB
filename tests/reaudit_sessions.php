<?php
// Local test sessions only. No database writes and no password-login claim.
require __DIR__ . '/../app/Config/database.php';
$dir=getenv('SOMI_AUDIT_SESSION_DIR');
if (!$dir || PHP_SAPI !== 'cli') exit(1);
ini_set('session.save_path',$dir);
$db=somi_db();
$query=$db->query("SELECT DISTINCT ON (r.role_code) u.user_id, r.role_code FROM users u JOIN user_roles ur USING(user_id) JOIN roles r USING(role_id) LEFT JOIN shipper_profiles sp ON sp.user_id=u.user_id WHERE u.account_status='ACTIVE' ORDER BY r.role_code,u.user_id");
$fixtures=[];
foreach($query->fetchAll() as $row) {
 session_id(bin2hex(random_bytes(24))); session_start();
 $_SESSION=['somi_user_id'=>(int)$row['user_id'],'somi_role'=>strtolower($row['role_code']),'somi_authenticated_at'=>time(),'somi_last_activity'=>time()];
 $fixtures[strtolower($row['role_code'])]=session_id(); session_write_close();
}
file_put_contents($dir.'/fixtures.json',json_encode($fixtures));
echo json_encode(['local_test_roles'=>array_keys($fixtures),'database_writes'=>0]);
