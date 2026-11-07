<?php
declare(strict_types=1);
require_once __DIR__.'/../_kernel.php';
require_once __DIR__.'/../../../app/Services/AdminPolicyService.php';
$method=$_SERVER['REQUEST_METHOD']??'';
somi_run_api(['GET','POST'],['ADMIN'],'pricing.manage',$method==='POST',static function(PDO $db,int $admin)use($method):array{
    if($method==='GET'&&isset($_GET['id'])){
        $id=DeliveryInput::id($_GET['id']);$q=$db->prepare('SELECT * FROM pricing_policies WHERE pricing_policy_id=?');$q->execute([$id]);$policy=$q->fetch();if(!$policy)throw new DeliveryApiError(404,'POLICY_NOT_FOUND','Policy not found.');
        foreach(['time_bands'=>'time_price_bands','weather_bands'=>'weather_price_bands','cancellation_fees'=>'cancellation_fee_rules'] as $key=>$table){$q=$db->prepare('SELECT * FROM '.$table.' WHERE pricing_policy_id=?');$q->execute([$id]);$policy[$key]=$q->fetchAll();}return [200,['policy'=>$policy]];
    }
    if($method==='GET')return [200,['pricing'=>$db->query('SELECT * FROM pricing_policies ORDER BY pricing_policy_id DESC LIMIT 100')->fetchAll(),'earning'=>$db->query('SELECT * FROM shipper_earning_policies ORDER BY earning_policy_id DESC LIMIT 100')->fetchAll(),'score'=>$db->query('SELECT * FROM shipper_score_policies ORDER BY policy_id DESC LIMIT 100')->fetchAll()]];
    $in=somi_json_body();DeliveryInput::only($in,['action','policy','pricing_policy_id']);$service=new AdminPolicyService($db);$action=$in['action']??null;
    if($action==='ACTIVATE_PRICING')return [200,['policy'=>$service->activate($admin,DeliveryInput::id($in['pricing_policy_id']??null))]];
    if(!is_array($in['policy']??null))DeliveryInput::invalid('policy');
    if($action==='PRICING')$result=$service->createPricing($admin,$in['policy']);elseif($action==='EARNING')$result=$service->earning($admin,$in['policy']);elseif($action==='SCORE')$result=$service->score($admin,$in['policy']);else DeliveryInput::invalid('action');return [201,['policy'=>$result]];
});
