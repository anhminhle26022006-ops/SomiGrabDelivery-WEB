<?php
declare(strict_types=1);
require_once __DIR__.'/../_kernel.php';
require_once __DIR__.'/../../../app/Services/AdminConsoleService.php';
somi_run_api(['POST'],['ADMIN'],'account.read',true,static function(PDO $db,int $admin):array{
    $in=somi_json_body();$action=$in['action']??null;unset($in['action']);$service=new AdminConsoleService($db);
    if($action==='RESTORE_SCORE')return [200,$service->restoreScore($admin,$in)];
    if($action==='TICKET_STATUS')return [200,$service->ticketStatus($admin,$in)];
    if($action==='MARK_READ'){DeliveryInput::only($in,['notification_id']);return [200,$service->markRead($admin,DeliveryInput::id($in['notification_id']??null))];}
    if($action==='CANCEL_ORDER'){DeliveryInput::only($in,['order_id','reason']);return [200,$service->cancel($admin,DeliveryInput::id($in['order_id']??null),DeliveryInput::text($in['reason']??null,'reason',1000))];}
    DeliveryInput::invalid('action');
});
