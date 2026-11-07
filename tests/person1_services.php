<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli' || getenv('SOMI_DB_NAME') !== 'somi_person1_test' || getenv('SOMI_DB_HOST') !== '127.0.0.1') exit('Test database required.');
require __DIR__ . '/../app/Config/database.php';
require __DIR__ . '/../app/Services/CustomerService.php';
require __DIR__ . '/../app/Services/AdminService.php';
require __DIR__ . '/../app/Services/PricingConfiguration.php';
require __DIR__ . '/../app/Services/PasswordResetService.php';
require __DIR__ . '/../app/Services/PhoneVerificationService.php';
require __DIR__ . '/../app/Services/EmailVerificationService.php';
$db = somi_db(); $repo = new Person1Repository($db); $customer = new CustomerService($repo); $admin = new AdminService($repo);
$f = json_decode(file_get_contents(__DIR__ . '/.runtime/person1-fixtures.json'), true);
$owner = $f['users']['customer']['id']; $other = $f['users']['other']['id']; $actor = $f['users']['admin']['id'];
$results = [];
function check(string $name, bool $value): void { global $results; $results[] = ['test' => $name, 'result' => $value ? 'PASS' : 'FAIL']; if (!$value) throw new RuntimeException($name); }
function problem(string $name, int $status, callable $operation): void {
    try { $operation(); check($name, false); } catch (ApiProblem $e) { check($name, $e->status === $status); }
}
try {
    $draft = ['label'=>'Test','address_detail'=>'Test street','province'=>'Test','district'=>'','ward'=>'','latitude'=>10.1234567,'longitude'=>106.7654321,'is_default'=>true];
    $a = $customer->saveAddress($owner, $draft);
    check('address stored in PostgreSQL', (int) $a['customer_id'] === $owner);
    $b = $customer->saveAddress($owner, $draft);
    check('one default address', (int) $repo->one('SELECT count(*) n FROM customer_addresses WHERE customer_id=:id AND is_default', ['id'=>$owner])['n'] === 1);
    problem('cross-owner edit hidden',404,fn()=>$customer->saveAddress($other, $draft+['address_id'=>$a['address_id']]));
    problem('cross-owner archive hidden',404,fn()=>$customer->archiveAddress($other,(int)$a['address_id']));
    problem('coordinate range validation',422,fn()=>$customer->saveAddress($owner, array_replace($draft,['latitude'=>91])));
    problem('boolean rejects string',422,fn()=>$customer->saveAddress($owner, array_replace($draft,['is_default'=>'false'])));
    $edited=$customer->saveAddress($owner,array_replace($draft,['address_id'=>$a['address_id'],'label'=>'Updated','is_default'=>false]));
    check('address update persisted',$edited['label']==='Updated');
    $order = $repo->one("INSERT INTO orders(order_code,customer_id,currency_code,app_language_at_order,pickup_saved_address_id,
        distance_km,pickup_address,pickup_latitude,pickup_longitude,sender_name,sender_phone,delivery_address,delivery_latitude,delivery_longitude,
        receiver_name,receiver_phone,category_id,estimated_weight_kg,distance_fee_minor,total_charge_minor,pricing_snapshot)
        VALUES (:code,:owner,'VND','vi',:address,1,'SNAPSHOT PICKUP',10,106,'Test Sender','+84901234567','SNAPSHOT DELIVERY',11,107,
        'Test Receiver','+84901234568',:category,1,1,1,'{\"test_only\":true}') RETURNING *",
        ['code'=>'TEST-'.bin2hex(random_bytes(6)),'owner'=>$owner,'address'=>$a['address_id'],'category'=>$f['category_id']]);
    $id=(int)$order['order_id'];
    $f['order_id']=$id;
    file_put_contents(__DIR__.'/.runtime/person1-fixtures.json',json_encode($f));
    check('owner lists order',count($customer->orders($owner))>0);
    check('other cannot list order',count($customer->orders($other))===0);
    problem('cross-owner order hidden',404,fn()=>$customer->order($other,$id));
    problem('used address cannot be deleted',409,fn()=>$customer->archiveAddress($owner,(int)$a['address_id']));
    $customer->saveAddress($owner,array_replace($draft,['address_id'=>$a['address_id'],'address_detail'=>'CHANGED','is_default'=>false]));
    check('order address snapshot survives edit',$customer->order($owner,$id)['pickup_address']==='SNAPSHOT PICKUP');
    $repo->all("UPDATE orders SET status='SHIPPER_ASSIGNED' WHERE order_id=:id",['id'=>$id]);
    problem('assigned cancellation rejected',409,fn()=>$customer->cancel($owner,$id,'Test'));
    $repo->all("UPDATE orders SET status='PENDING_PAYMENT',paid_at=now() WHERE order_id=:id",['id'=>$id]);
    problem('paid cancellation blocked without refund',409,fn()=>$customer->cancel($owner,$id,'Test'));
    $repo->all('UPDATE orders SET paid_at=NULL WHERE order_id=:id',['id'=>$id]);
    $cancelled=$customer->cancel($owner,$id,'Test cancellation');
    check('unassigned unpaid cancellation',$cancelled['status']==='CANCELLED');
    $customer->cancel($owner,$id,'Retry');
    check('cancel idempotent single history',count($customer->order($owner,$id)['history'])===1);
    check('cancel preserves price snapshot',$cancelled['pricing_snapshot']===$order['pricing_snapshot'] && $cancelled['total_charge_minor']===$order['total_charge_minor']);
    $customer->archiveAddress($owner,(int)$b['address_id']);
    check('archive disappears from list',count($customer->addresses($owner))===1);
    $admin->status($actor,$other,'SUSPENDED','Test suspend');
    check('suspend history and audit atomic',count($repo->all('SELECT * FROM account_status_history WHERE user_id=:id',['id'=>$other]))===1 && count($repo->all("SELECT * FROM audit_logs WHERE entity_id=:id AND action='ACCOUNT_STATUS_CHANGED'",['id'=>$other]))===1);
    $admin->status($actor,$other,'ACTIVE','Test restore');
    problem('admin self status denied',409,fn()=>$admin->status($actor,$actor,'SUSPENDED','Test'));
    problem('profile pending cannot be activated by status API',409,function()use($repo,$admin,$actor,$other){$repo->all("UPDATE users SET account_status='PENDING_VERIFICATION' WHERE user_id=:id",['id'=>$other]);$admin->status($actor,$other,'ACTIVE','Test');});
    $repo->all("UPDATE users SET account_status='ACTIVE' WHERE user_id=:id",['id'=>$other]);
    $valid=['currency_code'=>'VND','configuration'=>['distance_bands'=>[['min_km'=>0,'max_km'=>null,'amount_minor'=>123]],'return_fee_minor'=>0]];
    check('pricing configuration explicit values',PricingConfiguration::validate($valid)===$valid);
    problem('minor money rejects float',422,fn()=>PricingConfiguration::minor(1.5));
    problem('minor money rejects negative',422,fn()=>PricingConfiguration::minor(-1));
    problem('pricing rejects invented keys',422,fn()=>PricingConfiguration::validate(['currency_code'=>'USD','configuration'=>['unknown'=>12]]));
    $token=bin2hex(random_bytes(32));$repo->all("INSERT INTO password_reset_tokens(user_id,token_hash,expires_at) VALUES (:id,:hash,now()+interval '30 minutes')",['id'=>$other,'hash'=>hash('sha256',$token)]);
    PasswordResetService::reset($token,bin2hex(random_bytes(16)));
    check('password reset consumes token',$repo->one('SELECT consumed_at FROM password_reset_tokens WHERE token_hash=:hash',['hash'=>hash('sha256',$token)])['consumed_at']!==null);
    try{PasswordResetService::reset($token,'AnotherPassword123');check('reset token replay rejected',false);}catch(InvalidArgumentException){check('reset token replay rejected',true);}
    check('reset marks password timestamp',$repo->one('SELECT password_changed_at FROM users WHERE user_id=:id',['id'=>$other])['password_changed_at']!==null);
    // Restore fixture login credentials only in the explicitly isolated test database.
    $repo->all('UPDATE users SET password_hash=:hash,password_changed_at=NULL WHERE user_id=:id',['id'=>$other,'hash'=>password_hash($f['password'],PASSWORD_DEFAULT)]);
    $repo->all("UPDATE users SET account_status='PENDING_VERIFICATION',phone_verified_at=NULL,email_verified_at=NULL WHERE user_id=:id",['id'=>$other]);
    $phone=$repo->one('SELECT phone FROM users WHERE user_id=:id',['id'=>$other])['phone'];
    $repo->all("INSERT INTO phone_verifications(user_id,phone,code_hash,purpose,expires_at) VALUES (:id,:phone,:hash,'VERIFY_PHONE',now()+interval '5 minutes')",['id'=>$other,'phone'=>$phone,'hash'=>password_hash('123456',PASSWORD_DEFAULT)]);
    for($i=0;$i<5;$i++){try{PhoneVerificationService::verifyCode($other,'000000');}catch(RuntimeException){}}
    check('phone wrong attempts persist and stop at five',(int)$repo->one('SELECT attempt_count FROM phone_verifications WHERE user_id=:id ORDER BY verification_id DESC LIMIT 1',['id'=>$other])['attempt_count']===5);
    try{PhoneVerificationService::verifyCode($other,'123456');check('phone lock rejects correct code after limit',false);}catch(RuntimeException){check('phone lock rejects correct code after limit',true);}
    $repo->all('UPDATE phone_verifications SET consumed_at=now() WHERE user_id=:id',['id'=>$other]);
    $repo->all("INSERT INTO phone_verifications(user_id,phone,code_hash,purpose,expires_at) VALUES (:id,:phone,:hash,'VERIFY_PHONE',now()+interval '5 minutes')",['id'=>$other,'phone'=>$phone,'hash'=>password_hash('123456',PASSWORD_DEFAULT)]);
    PhoneVerificationService::verifyCode($other,'123456');
    check('phone verification updates timestamp',$repo->one('SELECT phone_verified_at FROM users WHERE user_id=:id',['id'=>$other])['phone_verified_at']!==null);
    try{PhoneVerificationService::verifyCode($other,'123456');check('phone replay rejected',false);}catch(RuntimeException){check('phone replay rejected',true);}
    $email=$repo->one('SELECT email FROM users WHERE user_id=:id',['id'=>$other])['email'];
    $repo->all("INSERT INTO email_verifications(user_id,email,token_hash,purpose,expires_at) VALUES (:id,:email,:hash,'VERIFY_EMAIL',now()+interval '5 minutes')",['id'=>$other,'email'=>$email,'hash'=>password_hash('654321',PASSWORD_DEFAULT)]);
    EmailVerificationService::verifyCode($other,'654321');
    check('email and phone verification activate account',$repo->one('SELECT account_status FROM users WHERE user_id=:id',['id'=>$other])['account_status']==='ACTIVE');
    try{EmailVerificationService::verifyCode($other,'654321');check('email replay rejected',false);}catch(RuntimeException){check('email replay rejected',true);}
} catch(Throwable $e) { $results[]=['test'=>'uncaught exception','result'=>'FAIL','class'=>get_class($e),'message'=>$e->getMessage()]; }
file_put_contents(__DIR__.'/../docs/person1-service-results.json',json_encode($results,JSON_PRETTY_PRINT));
$failed=count(array_filter($results,fn($r)=>$r['result']==='FAIL'));
echo json_encode(['checks'=>count($results),'failures'=>$failed]); exit($failed?1:0);
