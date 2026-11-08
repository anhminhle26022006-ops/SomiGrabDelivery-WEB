<?php
declare(strict_types=1);
require_once __DIR__.'/../_kernel.php';
require_once __DIR__.'/../../../app/Services/AdminConsoleService.php';
$section=$_GET['section']??'overview';
$permissions=['overview'=>'order.read_all','accounts'=>'account.read','account'=>'account.read','tickets'=>'support.manage_all','chat'=>'chat.read_for_support','reviews'=>'order.read_all','notifications'=>'account.read'];
if(!is_string($section)||!isset($permissions[$section]))somi_api_json(422,['code'=>'VALIDATION_ERROR','error'=>'Invalid section.']);
// Audited reads use a transaction so audit and sensitive read succeed together.
somi_run_api(['GET'],['ADMIN'],$permissions[$section],true,static function(PDO $db,int $admin)use($section):array{
    $service=new AdminConsoleService($db);
    if($section==='overview')$result=$service->overview($admin);
    elseif($section==='accounts')$result=$service->accounts($admin,$_GET);
    elseif($section==='account')$result=$service->account($admin,DeliveryInput::id($_GET['id']??null));
    elseif($section==='tickets')$result=$service->tickets($admin,$_GET);
    elseif($section==='chat')$result=$service->chat($admin,DeliveryInput::id($_GET['ticket_id']??null));
    elseif($section==='reviews')$result=$service->reviews($admin,$_GET);
    else $result=$service->notifications($admin,$_GET);
    return [200,$result];
});
