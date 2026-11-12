<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../app/Config/database.php';
$code=$argv[1]??'';
if(!preg_match('/^SOMI-[A-F0-9]{20}$/D',$code)){fwrite(STDERR,"Usage: php bin/person1-acceptance-check.php SOMI-<20 hex characters>\n");exit(2);}
try {
    $db=somi_db();$db->beginTransaction();$db->exec('SET TRANSACTION READ ONLY');
    $db->exec("SET LOCAL statement_timeout='10s'");
    $q=$db->prepare("SELECT o.order_id,o.order_code,o.status AS order_status,o.paid_at,p.payment_id,p.status AS payment_status,p.provider,p.amount_minor,p.currency_code
        FROM orders o LEFT JOIN payments p USING(order_id) WHERE o.order_code=?");$q->execute([$code]);$order=$q->fetch();
    if(!$order){$db->rollBack();echo json_encode(['found'=>false]).PHP_EOL;exit(1);}
    $q=$db->prepare("SELECT transaction_type,status,count(*) AS count FROM payment_transactions WHERE payment_id=? GROUP BY transaction_type,status");$q->execute([$order['payment_id']]);$ledger=$q->fetchAll();
    $q=$db->prepare("SELECT event_type,count(*) AS count FROM order_events WHERE order_id=? GROUP BY event_type");$q->execute([$order['order_id']]);$events=$q->fetchAll();
    $q=$db->prepare("SELECT r.consumer,count(*) AS processed_events,max(r.processed_at) AS last_processed_at FROM event_consumer_receipts r JOIN order_events e USING(event_id) WHERE e.order_id=? GROUP BY r.consumer");$q->execute([$order['order_id']]);$receipts=$q->fetchAll();
    $q=$db->prepare("SELECT status,count(*) AS count FROM order_match_offers WHERE order_id=? GROUP BY status");$q->execute([$order['order_id']]);$offers=$q->fetchAll();
    $q=$db->prepare("SELECT status,count(*) AS count FROM email_messages WHERE order_id=? GROUP BY status");$q->execute([$order['order_id']]);$emails=$q->fetchAll();
    $q=$db->prepare("SELECT count(*) FROM audit_logs WHERE action='SEPAY_IPN_ACCEPTED' AND entity_type='PAYMENT' AND entity_id=?");$q->execute([$order['payment_id']]);$ipn=(int)$q->fetchColumn();
    $q=$db->query("SELECT kind,status,count(*) AS count FROM background_jobs GROUP BY kind,status");$jobs=$q->fetchAll();
    $q=$db->query("SELECT policy_type,currency_code,percentage_rate,fixed_amount_minor FROM shipper_earning_policies WHERE effective_from<=now() AND (effective_until IS NULL OR effective_until>now())");$earnings=$q->fetchAll();
    $q=$db->query("SELECT deduction_points,lock_threshold,recovery_points FROM shipper_score_policies WHERE effective_from<=now() AND (effective_until IS NULL OR effective_until>now())");$score=$q->fetchAll();
    $db->commit();
    $providerCheck=null;
    if(in_array('--provider',$argv,true)){
        require_once __DIR__.'/../app/Services/SePayPaymentProvider.php';
        try{$verified=(new SePayPaymentProvider())->reconcile($order);$providerCheck=['paid'=>$verified['status']==='PAID','amount_matches'=>(string)$verified['amount_minor']===(string)$order['amount_minor'],'currency_matches'=>$verified['currency_code']===$order['currency_code']];}
        catch(Throwable $e){$providerCheck=['paid'=>false,'error_code'=>$e instanceof DeliveryApiError?$e->errorCode:get_class($e)];}
    }
    echo json_encode(['read_only'=>true,'provider_read_only'=>$providerCheck,'order'=>$order,'ledger'=>$ledger,'events'=>$events,'consumer_receipts'=>$receipts,'offers'=>$offers,'email_status'=>$emails,'ipn_accepted_audits'=>$ipn,'jobs'=>$jobs,'active_earnings'=>$earnings,'active_score'=>$score,'limits'=>['database_is_local_config_not_hosting_attestation'=>true,'old_callbacks_have_no_origin_audit'=>true,'accepted_email_is_not_inbox_delivery'=>true]],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL;
}catch(Throwable $e){if(isset($db)&&$db->inTransaction())$db->rollBack();fwrite(STDERR,'Read-only acceptance check failed: '.get_class($e).' code '.$e->getCode().PHP_EOL);exit(1);}
