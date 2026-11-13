<?php
declare(strict_types=1);
require_once __DIR__.'/../_kernel.php';
require_once __DIR__.'/../../../app/Services/AdminFinanceService.php';
header('Cache-Control: no-store');
somi_run_api(['GET','POST'],['ADMIN'],'payment.reconcile',true,static function(PDO $db,int $admin):array{
    $s=new AdminFinanceService($db);
    if($_SERVER['REQUEST_METHOD']==='POST'){
        if(str_starts_with($_SERVER['CONTENT_TYPE']??'','multipart/form-data;'))return [200,$s->confirm($admin,$_POST,$_FILES['receipt']??[])];
        $in=somi_json_body();
        if(($in['action']??'')==='RETRY_PAYOUT'){
            if(($in['transfer_not_completed']??null)!==true)throw new DeliveryApiError(422,'TRANSFER_REVIEW_REQUIRED','Verify no completed transfer exists before retrying.');
            return [200,(new ShipperFinanceService($db))->transition($admin,'RETRY',['payout_id'=>$in['payout_id']??null,'client_key'=>$in['client_key']??null,'reason'=>$in['note']??null,'transfer_not_completed'=>true,'evidence_reference'=>'ADMIN-REVIEW:'.$admin.':'.DeliveryInput::text($in['client_key']??null,'client_key',100)])];
        }
        if(($in['action']??'')==='PREPARE_PAYOUT'){
            AccessControl::requirePermission($db,$admin,'ADMIN','payout.manage');
            $p=(new ShipperFinanceService($db))->reserve(DeliveryInput::id($in['earning_id']??null));
            if(!$p)throw new DeliveryApiError(409,'PAYOUT_NOT_READY','Check completed delivery, approved bank and refund status before preparing a payout.');
            return [200,['payout'=>$p]];
        }
        return [200,$s->review($admin,$in)];
    }
    if(isset($_GET['evidence_id']))return [200,$s->receipt($admin,DeliveryInput::id($_GET['evidence_id']))];
    if(($_GET['resource']??'')==='banks')return [200,$s->banks($admin,$_GET)];
    if(isset($_GET['order_id']))return [200,$s->detail($admin,DeliveryInput::id($_GET['order_id']))];
    return [200,$s->listing($admin,$_GET)];
});
