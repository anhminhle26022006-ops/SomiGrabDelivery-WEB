<?php
declare(strict_types=1);
require_once __DIR__.'/shipper_fixtures.php';
require_once __DIR__.'/../app/Services/ApprovedShipperPolicyRelease.php';
require_once __DIR__.'/../app/Services/AdminPolicyService.php';
require_once __DIR__.'/../app/Services/ShipperCancellationService.php';
require_once __DIR__.'/../app/Services/ShipperAvailabilityService.php';
require_once __DIR__.'/../app/Services/MatchingService.php';
require_once __DIR__.'/../app/Services/OfferAcceptService.php';
require_once __DIR__.'/../app/Services/DeliveryCompletionService.php';
require_once __DIR__.'/../app/Services/DeliveryReturnService.php';
require_once __DIR__.'/../app/Services/Person1Worker.php';
require_once __DIR__.'/../app/Services/Person3Worker.php';
require_once __DIR__.'/../app/Services/ShipperBankService.php';
function assigned(PDO $db,int $shipper=SHIPPER_A):int {
    tx($db,fn()=>(new ShipperAvailabilityService($db))->turnOn($shipper,NEAR[0],NEAR[1]));$id=paid_order($db);
    tx($db,fn()=>(new MatchingService($db))->dispatch($id));$offer=(int)value($db,"SELECT offer_id FROM order_match_offers WHERE order_id=? AND shipper_id=? AND status='PENDING'",[$id,$shipper]);
    tx($db,fn()=>(new OfferAcceptService($db))->accept($shipper,$offer));return $id;
}
function begin_delivery(PDO $db,int $id):void {tx($db,fn()=>(new DeliveryService($db))->pickup(SHIPPER_A,$id));tx($db,fn()=>(new DeliveryService($db))->startDelivery(SHIPPER_A,$id));}
function deliver(PDO $db,int $id):array {
    $file=storage_file($db,SHIPPER_A,'EVIDENCE',$id);$service=new DeliveryCompletionService($db);
    tx($db,fn()=>$service->addEvidence(SHIPPER_A,$id,'DELIVERY_PHOTO',$file,null,null,null));return tx($db,fn()=>$service->delivered(SHIPPER_A,$id));
}
function finish_return(PDO $db,int $id):void {
    $file=storage_file($db,SHIPPER_A,'EVIDENCE',$id);tx($db,fn()=>(new DeliveryCompletionService($db))->addEvidence(SHIPPER_A,$id,'RETURN_PHOTO',$file,null,null,null));
    tx($db,fn()=>(new DeliveryReturnService($db))->shipperReturned(SHIPPER_A,$id));tx($db,fn()=>(new DeliveryReturnService($db))->customerConfirmed(CUSTOMER,$id));
}
function refund_manually(PDO $db,int $id):void {
    $db->prepare("UPDATE payments SET provider='SEPAY',provider_payment_ref=? WHERE order_id=?")->execute(['TEST-PAID-'.$id,$id]);
    $refund=tx($db,fn()=>(new PaymentService($db))->requestRefund($id,'RETURN_FEE_DEDUCTION','test-return-refund-'.$id));
    (new Person1Worker($db))->execute(['kind'=>'REFUND','payload'=>['refund_id'=>$refund['refund_id']]]);
    check(value($db,'SELECT status FROM refunds WHERE refund_id=?',[$refund['refund_id']])==='PROCESSING','manual refund never pretends funds moved');
    tx($db,fn()=>(new PaymentService($db))->reconcileProviderRefund(ADMIN,(int)$refund['refund_id'],['provider_refund_id'=>'TEST-REFUND-'.$id,'original_payment_reference'=>'TEST-PAID-'.$id,'evidence_reference'=>'TEST-NOT-A-REAL-TRANSFER','amount_minor'=>(string)$refund['refund_amount_minor'],'currency_code'=>'VND','original_method_confirmed'=>true]));
}
try {
    $db=shipper_test_db();putenv('SOMI_PAYOUT_MODE=manual');putenv('SOMI_MESSAGE_ENCRYPTION_KEY='.base64_encode(random_bytes(32)));
    tx($db,fn()=>ApprovedShipperPolicyRelease::apply($db));tx($db,fn()=>ApprovedShipperPolicyRelease::apply($db));
    check((int)value($db,"SELECT count(*) FROM shipper_earning_policies WHERE effective_until IS NULL")===2,'two approved VND earning policies');
    check((string)value($db,'SELECT deduction_points FROM shipper_score_policies WHERE effective_until IS NULL')==='10.00','before pickup deduction 10');
    check((string)value($db,'SELECT post_pickup_deduction_points FROM shipper_score_policies WHERE effective_until IS NULL')==='20.00','after pickup deduction 20');
    check((int)value($db,"SELECT count(*) FROM audit_logs WHERE action='APPROVED_SHIPPER_POLICY_RELEASED'")===1,'release replays once');
    $cancel=new ShipperCancellationService($db);$score=new ShipperScoreService($db);$finance=new ShipperFinanceService($db);
    $id=assigned($db);$body=['order_id'=>$id,'reason_code'=>'SHIPPER_FAULT','reason'=>'Fixture self cancellation','client_key'=>'cancel-fixture-1'];
    rejects(fn()=>tx($db,fn()=>$cancel->request(SHIPPER_B,$body)),'ORDER_NOT_FOUND');
    $r=tx($db,fn()=>$cancel->request(SHIPPER_A,$body));check($r['request']['decision']==='NOT_REQUIRED','self fault before pickup releases immediately');
    check(value($db,'SELECT status FROM orders WHERE order_id=?',[$id])==='SEARCHING_SHIPPER','paid order restarts matching');
    check((string)value($db,'SELECT reliability_score FROM shipper_profiles WHERE user_id=3')==='90.00','deduction recorded once');
    tx($db,fn()=>$cancel->request(SHIPPER_A,$body));check((string)value($db,'SELECT reliability_score FROM shipper_profiles WHERE user_id=3')==='90.00','request replay cannot deduct twice');
    rejects(fn()=>tx($db,fn()=>$cancel->request(SHIPPER_A,array_replace($body,['reason'=>'changed']))),'IDEMPOTENCY_CONFLICT');
    check((int)value($db,'SELECT count(*) FROM refunds WHERE order_id=?',[$id])===0,'cancellation of assignment does not refund Customer');
    check((int)value($db,"SELECT count(*) FROM order_events WHERE order_id=? AND deduplication_key LIKE 'matching:cancel:%'",[$id])===1,'rematching event once');
    check(tx($db,fn()=>(new MatchingService($db))->dispatch($id))['result']==='WAITING','shipper cannot receive the same order after cancelling it');

    $external=assigned($db);$request=['order_id'=>$external,'reason_code'=>'EXTERNAL_ISSUE','reason'=>'Fixture road closure','client_key'=>'external-fixture'];
    $r=tx($db,fn()=>$cancel->request(SHIPPER_A,$request));check($r['request']['decision']==='PENDING','external cause needs verification');
    check((string)value($db,'SELECT reliability_score FROM shipper_profiles WHERE user_id=3')==='90.00','no unverified deduction');
    rejects(fn()=>tx($db,fn()=>$cancel->request(SHIPPER_A,array_replace($request,['client_key'=>'external-again']))),'CANCELLATION_PENDING');
    $review=['request_id'=>$r['request']['cancellation_request_id'],'decision'=>'APPROVED','shipper_at_fault'=>false,'note'=>'Verified external issue','evidence_reference'=>'TEST-ROAD-CLOSURE'];
    rejects(fn()=>tx($db,fn()=>$cancel->review(CUSTOMER,$review)),'PERMISSION_DENIED');
    check(count(tx($db,fn()=>$cancel->listing(ADMIN,[]))['items'])===1,'Admin pending queue');
    tx($db,fn()=>$cancel->review(ADMIN,$review));tx($db,fn()=>$cancel->review(ADMIN,$review));
    check(value($db,'SELECT status FROM orders WHERE order_id=?',[$external])==='SEARCHING_SHIPPER','verified external cause restarts matching');
    check((string)value($db,'SELECT reliability_score FROM shipper_profiles WHERE user_id=3')==='90.00','external cancellation no penalty');
    rejects(fn()=>tx($db,fn()=>$cancel->review(ADMIN,array_replace($review,['shipper_at_fault'=>true]))),'REQUEST_ALREADY_REVIEWED');

    // Actual OTP verification on isolated queued email, no provider request.
    $db->prepare('UPDATE users SET password_hash=? WHERE user_id=3')->execute([password_hash('FixturePassword!234',PASSWORD_DEFAULT)]);
    $bank=new ShipperBankService($db);$b=tx($db,fn()=>$bank->request(3,['bank_code'=>'TEST','bank_name'=>'Fixture','account_holder'=>'Fixture Shipper','account_number'=>'000009876543','current_password'=>'FixturePassword!234','client_key'=>'policy-bank-fixture']));
    $mail=EncryptedPayload::open(value($db,'SELECT encrypted_payload FROM email_messages WHERE deduplication_key=?',['bank-change:'.$b['request_id']]));preg_match('/: ([0-9]{6})/',$mail['text'],$match);
    tx($db,fn()=>$bank->confirm(3,['request_id'=>$b['request_id'],'code'=>$match[1]]));
    $returned=assigned($db);begin_delivery($db,$returned);
    $r=tx($db,fn()=>$cancel->request(3,['order_id'=>$returned,'reason_code'=>'EXTERNAL_ISSUE','reason'=>'Fixture incident','client_key'=>'after-pickup-fixture']));
    check(value($db,'SELECT status FROM orders WHERE order_id=?',[$returned])==='IN_DELIVERY','request after pickup cannot cancel goods custody');
    tx($db,fn()=>$cancel->review(ADMIN,['request_id'=>$r['request']['cancellation_request_id'],'decision'=>'APPROVED','shipper_at_fault'=>false,'note'=>'Verified return','evidence_reference'=>'TEST-INCIDENT']));
    check(value($db,'SELECT status FROM orders WHERE order_id=?',[$returned])==='RETURNING','Admin approves return rather than terminal cancellation');
    check(value($db,'SELECT assignment_status FROM order_assignments WHERE order_id=?',[$returned])==='ACTIVE','shipper remains responsible for returning goods');
    finish_return($db,$returned);check((int)value($db,'SELECT count(*) FROM shipper_earnings WHERE order_id=?',[$returned])===0,'compensation waits for refund reconciliation');
    refund_manually($db,$returned);
    check((string)value($db,'SELECT gross_amount_minor FROM shipper_earnings WHERE order_id=?',[$returned])==='10000','approved return earns exactly 10000 VND');
    check((string)value($db,'SELECT reliability_score FROM shipper_profiles WHERE user_id=3')==='90.00','return does not earn delivery recovery');
    $payout=value($db,'SELECT p.payout_id FROM shipper_payouts p JOIN shipper_earnings e USING(earning_id) WHERE e.order_id=?',[$returned]);
    check($payout!==false,'refund reconciliation immediately reserves manual payout');
    check(value($db,'SELECT status FROM shipper_payouts WHERE payout_id=?',[$payout])==='PROCESSING','reservation not a completed transfer');
    $proof=['payout_id'=>$payout,'provider_payout_ref'=>'TEST-PAYOUT-RETURN','evidence_reference'=>'TEST-NOT-A-REAL-PAYOUT','amount_minor'=>'10000','currency_code'=>'VND'];
    tx($db,fn()=>$finance->reconcile(ADMIN,$proof));tx($db,fn()=>$finance->reconcile(ADMIN,$proof));
    check(value($db,'SELECT payout_status FROM shipper_earnings WHERE order_id=?',[$returned])==='PAID','manual evidence confirms paid earning');

    $fault=assigned($db);begin_delivery($db,$fault);$assignment=value($db,'SELECT assignment_id FROM order_assignments WHERE order_id=?',[$fault]);
    $r=tx($db,fn()=>$cancel->request(3,['order_id'=>$fault,'reason_code'=>'SHIPPER_FAULT','reason'=>'Fixture vehicle misuse','client_key'=>'after-fault-fixture']));
    $review=['request_id'=>$r['request']['cancellation_request_id'],'decision'=>'APPROVED','shipper_at_fault'=>true,'note'=>'Verified misconduct','evidence_reference'=>'TEST-VERIFIED'];
    tx($db,fn()=>$cancel->review(ADMIN,$review));check((string)value($db,'SELECT reliability_score FROM shipper_profiles WHERE user_id=3')==='70.00','verified fault after pickup deducts 20');
    $v=['assignment_id'=>$assignment,'shipper_at_fault'=>true,'reason'=>'Duplicate report','evidence_reference'=>'TEST-VERIFIED'];tx($db,fn()=>$score->violation(ADMIN,$v));
    check((string)value($db,'SELECT reliability_score FROM shipper_profiles WHERE user_id=3')==='70.00','same assignment violation is not double counted');
    rejects(fn()=>tx($db,fn()=>$score->violation(CUSTOMER,$v)),'PERMISSION_DENIED');
    rejects(fn()=>tx($db,fn()=>$score->violation(ADMIN,array_replace($v,['shipper_at_fault'=>false]))),'FAULT_CONFIRMATION_REQUIRED');
    finish_return($db,$fault);refund_manually($db,$fault);check((int)value($db,'SELECT count(*) FROM shipper_earnings WHERE order_id=?',[$fault])===0,'fault return compensation held for finance review');

    $db->exec('UPDATE shipper_profiles SET reliability_score=99 WHERE user_id=3');$delivered=assigned($db);begin_delivery($db,$delivered);$result=deliver($db,$delivered);
    check((string)value($db,'SELECT reliability_score FROM shipper_profiles WHERE user_id=3')==='100.00','recovery capped at 100');
    check((string)value($db,"SELECT points_delta FROM shipper_score_events WHERE order_id=? AND event_type='RECOVERY'",[$delivered])==='1.00','actual capped recovery recorded');
    check($result['settlement']['amount_minor']==='24000'&&$result['settlement']['payout_id']!==null,'80 percent and payout reserved in completion transaction');
    check(value($db,'SELECT payout_status FROM shipper_earnings WHERE order_id=?',[$delivered])==='PENDING','no false PAID before transfer');
    tx($db,fn()=>$score->recovery($delivered));tx($db,fn()=>$finance->settleOrder($delivered));
    check((int)value($db,"SELECT count(*) FROM shipper_score_events WHERE order_id=? AND event_type='RECOVERY'",[$delivered])===1,'recovery replay once');
    check((int)value($db,'SELECT count(*) FROM shipper_earnings WHERE order_id=?',[$delivered])===1,'one earning on replay');
    check((int)value($db,'SELECT count(*) FROM shipper_payouts p JOIN shipper_earnings e USING(earning_id) WHERE e.order_id=?',[$delivered])===1,'one transfer reservation on replay');

    $db->exec('UPDATE shipper_profiles SET reliability_score=98 WHERE user_id=3');$late=assigned($db);begin_delivery($db,$late);
    $r=tx($db,fn()=>$cancel->request(3,['order_id'=>$late,'reason_code'=>'EXTERNAL_ISSUE','reason'=>'Fixture pending incident','client_key'=>'pending-completion-fixture']));deliver($db,$late);
    check((string)value($db,'SELECT reliability_score FROM shipper_profiles WHERE user_id=3')==='98.00','pending review holds recovery');
    check((int)value($db,'SELECT count(*) FROM shipper_earnings WHERE order_id=?',[$late])===0,'pending review holds earnings');
    $review=['request_id'=>$r['request']['cancellation_request_id'],'decision'=>'REJECTED','shipper_at_fault'=>false,'note'=>'Completed safely, no fault','evidence_reference'=>'TEST-COMPLETED'];tx($db,fn()=>$cancel->review(ADMIN,$review));
    check(value($db,'SELECT status FROM orders WHERE order_id=?',[$late])==='DELIVERED','closing stale request does not change delivery status');
    check((string)value($db,'SELECT reliability_score FROM shipper_profiles WHERE user_id=3')==='100.00','cleared review recovers points');
    check((string)value($db,'SELECT gross_amount_minor FROM shipper_earnings WHERE order_id=?',[$late])==='24000','cleared review settles immediately');

    $alreadyReturning=assigned($db);begin_delivery($db,$alreadyReturning);$r=tx($db,fn()=>$cancel->request(3,['order_id'=>$alreadyReturning,'reason_code'=>'EXTERNAL_ISSUE','reason'=>'Recipient unavailable','client_key'=>'already-returning-fixture']));
    tx($db,fn()=>(new DeliveryReturnService($db))->fail(3,$alreadyReturning,'RECIPIENT_ABSENT','Fixture return started during review'));
    tx($db,fn()=>$cancel->review(ADMIN,['request_id'=>$r['request']['cancellation_request_id'],'decision'=>'APPROVED','shipper_at_fault'=>false,'note'=>'Return already started, verified','evidence_reference'=>'TEST-RETURNING']));
    check(value($db,'SELECT status FROM orders WHERE order_id=?',[$alreadyReturning])==='RETURNING','review accepts a return already started without repeating transition');
    check(value($db,'SELECT decision FROM cancellation_requests WHERE cancellation_request_id=?',[$r['request']['cancellation_request_id']])==='APPROVED','return-in-progress request can be resolved');
    finish_return($db,$alreadyReturning);refund_manually($db,$alreadyReturning);
    $db->exec('UPDATE shipper_profiles SET reliability_score=65 WHERE user_id=3');$locked=assigned($db);tx($db,fn()=>$cancel->request(3,['order_id'=>$locked,'reason_code'=>'SHIPPER_FAULT','reason'=>'Fixture lock threshold','client_key'=>'threshold-fixture']));
    check((string)value($db,'SELECT reliability_score FROM shipper_profiles WHERE user_id=3')==='55.00'&&!value($db,'SELECT availability FROM shipper_profiles WHERE user_id=3'),'below 60 disables availability');
    rejects(fn()=>tx($db,fn()=>(new ShipperAvailabilityService($db))->turnOn(3,NEAR[0],NEAR[1])),'SHIPPER_NOT_ELIGIBLE');
    $db->exec('UPDATE shipper_profiles SET reliability_score=60 WHERE user_id=3');tx($db,fn()=>(new ShipperAvailabilityService($db))->turnOn(3,NEAR[0],NEAR[1]));check(true,'exactly 60 can work');
    $missing=assigned($db);rejects(fn()=>tx($db,function()use($db,$cancel,$missing){$db->exec("UPDATE shipper_score_policies SET effective_until=now() WHERE effective_until IS NULL");return $cancel->request(3,['order_id'=>$missing,'reason_code'=>'SHIPPER_FAULT','reason'=>'Missing policy fixture','client_key'=>'missing-policy-fixture']);}),'SCORE_POLICY_REQUIRED');
    check(value($db,'SELECT status FROM orders WHERE order_id=?',[$missing])==='SHIPPER_ASSIGNED','missing policy rolls back cancellation');
    begin_delivery($db,$missing);$db->exec('UPDATE shipper_profiles SET reliability_score=5 WHERE user_id=3');$a=value($db,'SELECT assignment_id FROM order_assignments WHERE order_id=?',[$missing]);
    tx($db,fn()=>$score->violation(ADMIN,['assignment_id'=>$a,'shipper_at_fault'=>true,'reason'=>'Floor fixture','evidence_reference'=>'TEST-FLOOR']));
    check((string)value($db,'SELECT reliability_score FROM shipper_profiles WHERE user_id=3')==='0.00','score does not go below zero');
    $snapshot=json_decode((string)value($db,"SELECT policy_snapshot FROM shipper_score_events WHERE order_id=? AND event_type='POST_PICKUP_VIOLATION'",[$missing]),true);check((string)$snapshot['post_pickup_deduction_points']==='20.00','score event freezes policy');
    tx($db,fn()=>(new AdminPolicyService($db))->score(ADMIN,['deduction_points'=>'10','lock_threshold'=>'60','recovery_points'=>'2']));
    check((string)value($db,'SELECT post_pickup_deduction_points FROM shipper_score_policies WHERE effective_until IS NULL')==='20.00','legacy policy caller preserves post-pickup deduction');
    $worker=new Person3Worker($db);for($i=0;$i<100&&$worker->consumeOne();$i++){}
    check((int)value($db,"SELECT count(*) FROM shipper_finance_reviews WHERE reason='ShipperViolationConfirmed'")===2,'verified fault creates finance review');
    echo "PASS: $checks approved-policy workflow checks; TEMP tables, queued OTP and manual transfer fixtures only.\n";
}catch(Throwable $e){if(isset($db)&&$db->inTransaction())$db->rollBack();fwrite(STDERR,get_class($e).': '.$e->getMessage().' @ '.$e->getLine()."\n");exit(1);}
