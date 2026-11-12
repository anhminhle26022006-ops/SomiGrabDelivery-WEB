<?php
declare(strict_types=1);
require_once __DIR__.'/../_kernel.php';
somi_run_api(['GET'],['ADMIN'],'email.read_log',false,static function(PDO $db,int $admin):array{
    $q=$db->prepare('SELECT email_id,user_id,order_id,template_code,language,status,provider_message_id,created_at,accepted_at,expires_at FROM email_messages WHERE email_id<? ORDER BY email_id DESC LIMIT 100');$q->execute([isset($_GET['before_id'])?DeliveryInput::id($_GET['before_id']):PHP_INT_MAX]);return [200,['items'=>$q->fetchAll()]];
});
