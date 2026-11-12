<?php
declare(strict_types=1);
require_once __DIR__.'/../_kernel.php';
somi_run_api(['GET'],['ADMIN'],'audit.read',false,static function(PDO $db,int $admin):array{
    $q=$db->prepare('SELECT audit_id,actor_id,actor_kind,action,entity_type,entity_id,old_value,new_value,created_at FROM audit_logs WHERE audit_id<? ORDER BY audit_id DESC LIMIT 100');$q->execute([isset($_GET['before_id'])?DeliveryInput::id($_GET['before_id']):PHP_INT_MAX]);return [200,['items'=>$q->fetchAll()]];
});
