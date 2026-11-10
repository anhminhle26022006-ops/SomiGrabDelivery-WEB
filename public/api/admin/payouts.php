<?php
declare(strict_types=1);
require_once __DIR__.'/../_kernel.php';
require_once __DIR__.'/../../../app/Services/ShipperFinanceService.php';
somi_run_api(['GET','POST'],['ADMIN'],'payout.manage',true,function(PDO $db,int $id):array {
    if($_SERVER['REQUEST_METHOD']==='POST'){
        $in=somi_json_body(false);$action=$in['action']??'RECONCILE';unset($in['action']);$service=new ShipperFinanceService($db);
        return [200,match($action){'RECONCILE'=>$service->reconcile($id,$in),'FX_QUOTE'=>$service->fxQuote($id,$in),'FAILED','RETRY'=>$service->transition($id,$action,$in),default=>throw new DeliveryApiError(422,'ACTION_INVALID','Unknown payout action.')}];
    }
    if(isset($_GET['payout_id']))return [200,(new ShipperFinanceService($db))->destination($id,DeliveryInput::id($_GET['payout_id']))];
    $before=isset($_GET['before_id'])?DeliveryInput::id($_GET['before_id']):PHP_INT_MAX;
    if(($_GET['resource']??'')==='earnings'){
        $currency=$_GET['currency_code']??'USD';if(!in_array($currency,['USD','VND'],true))DeliveryInput::invalid('currency_code');
        $q=$db->prepare('SELECT e.earning_id,e.order_id,e.shipper_id,e.gross_amount_minor,e.currency_code,e.payout_status,f.rate,f.provider,f.reference FROM shipper_earnings e LEFT JOIN payout_fx_quotes f USING(earning_id) WHERE e.earning_id<? AND e.currency_code=? ORDER BY e.earning_id DESC LIMIT 50');$q->execute([$before,$currency]);$items=$q->fetchAll();return [200,['items'=>$items,'next_cursor'=>count($items)===50?(string)end($items)['earning_id']:null]];
    }
    $q=$db->prepare('SELECT payout_id,earning_id,shipper_id,source_amount_minor,source_currency,payout_amount_minor,payout_currency,status,provider_payout_ref,failure_reason,created_at FROM shipper_payouts WHERE payout_id<? ORDER BY payout_id DESC LIMIT 50');$q->execute([$before]);$items=$q->fetchAll();return [200,['items'=>$items,'next_cursor'=>count($items)===50?(string)end($items)['payout_id']:null]];
});
