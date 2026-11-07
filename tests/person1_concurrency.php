<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli' || getenv('SOMI_DB_NAME') !== 'somi_person1_test' || getenv('SOMI_DB_HOST') !== '127.0.0.1') exit('Test database required.');
require __DIR__ . '/../app/Config/database.php';
require __DIR__ . '/../app/Services/CustomerService.php';
require __DIR__ . '/../app/Services/PasswordResetService.php';
$f = json_decode(file_get_contents(__DIR__ . '/.runtime/person1-fixtures.json'), true);
$id = $f['order_id']; $owner = $f['users']['customer']['id'];
$db = somi_db(); $repo = new Person1Repository($db); $service = new CustomerService($repo);
if (in_array($argv[1] ?? '', ['reset-one','reset-two'], true)) {
    $index=$argv[1]==='reset-one'?0:1;
    echo "READY\n";flush();fgets(STDIN);
    try{PasswordResetService::reset($f['reset_tokens'][$index],$f['reset_passwords'][$index]);echo "RESET\n";}
    catch(InvalidArgumentException){echo "INVALID\n";}
    exit;
}
if (($argv[1] ?? '') === 'accept-worker') {
    // Person 2 contract simulator, NOT an implemented matching endpoint.
    $db->beginTransaction();
    $row = $repo->one('SELECT status FROM orders WHERE order_id=:id FOR UPDATE', ['id'=>$id]);
    echo "LOCKED\n"; flush();
    usleep(400000);
    if ($row['status']==='SEARCHING_SHIPPER') {
        $repo->all("UPDATE orders SET status='SHIPPER_ASSIGNED' WHERE order_id=:id",['id'=>$id]);
        $repo->all('INSERT INTO order_assignments(order_id,shipper_id) VALUES (:id,:shipper)', ['id'=>$id,'shipper'=>$f['users']['shipper']['id']]);
    }
    $db->commit(); exit;
}
if (($argv[1] ?? '') === 'cancel-worker') {
    echo "READY\n"; flush(); fgets(STDIN);
    try { $service->cancel($owner,$id,'Concurrent cancellation test'); echo "CANCELLED\n"; }
    catch(Throwable $e) { echo get_class($e)."\n"; exit(1); }
    exit;
}
$results=[];
function verify(string $name,bool $ok): void { global $results;$results[]=['test'=>$name,'result'=>$ok?'PASS':'FAIL']; }
function worker(string $mode): array {
    $command=[PHP_BINARY,'-d','extension_dir='.dirname(PHP_BINARY).'/ext','-d','extension=pdo_pgsql',__FILE__,$mode];
    $process=proc_open($command,[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    if(!is_resource($process))throw new RuntimeException('Cannot start worker.');
    return [$process,$pipes];
}
try {
    $repo->all('DELETE FROM order_assignments WHERE order_id=:id',['id'=>$id]);
    $repo->all("UPDATE orders SET status='SEARCHING_SHIPPER',paid_at=NULL WHERE order_id=:id",['id'=>$id]);
    [$p,$pipes]=worker('accept-worker');
    if(trim(fgets($pipes[1]))!=='LOCKED')throw new RuntimeException('Worker did not acquire lock.');
    $start=microtime(true);$conflict=false;
    try{$service->cancel($owner,$id,'Racing cancellation');}catch(ApiProblem $e){$conflict=$e->status===409;}
    foreach($pipes as $pipe)fclose($pipe);$code=proc_close($p);
    verify('cancel waits for competing order lock and rejects accepted assignment',$conflict&&microtime(true)-$start>=.2&&$code===0);
    verify('assignment wins without cancellation history',$repo->one('SELECT status FROM orders WHERE order_id=:id',['id'=>$id])['status']==='SHIPPER_ASSIGNED');
    $repo->all('DELETE FROM order_assignments WHERE order_id=:id',['id'=>$id]);
    $repo->all('DELETE FROM order_status_history WHERE order_id=:id',['id'=>$id]);
    $repo->all("UPDATE orders SET status='SEARCHING_SHIPPER' WHERE order_id=:id",['id'=>$id]);
    [$first,$fp]=worker('cancel-worker');[$second,$sp]=worker('cancel-worker');
    if(trim(fgets($fp[1]))!=='READY'||trim(fgets($sp[1]))!=='READY')throw new RuntimeException('Workers not ready.');
    fwrite($fp[0],"GO\n");fflush($fp[0]);fwrite($sp[0],"GO\n");fflush($sp[0]);
    $one=trim(fgets($fp[1]));$two=trim(fgets($sp[1]));
    foreach(array_merge($fp,$sp) as $pipe)fclose($pipe);
    $c1=proc_close($first);$c2=proc_close($second);
    verify('two concurrent cancellations both succeed idempotently',$one==='CANCELLED'&&$two==='CANCELLED'&&$c1===0&&$c2===0);
    verify('concurrent cancellation creates exactly one history row',(int)$repo->one('SELECT count(*) n FROM order_status_history WHERE order_id=:id',['id'=>$id])['n']===1);
    [$p,$pipes]=worker('accept-worker');fgets($pipes[1]);foreach($pipes as $pipe)fclose($pipe);$code=proc_close($p);
    verify('matching contract rejects order cancelled before assignment',$code===0&&count($repo->all('SELECT 1 FROM order_assignments WHERE order_id=:id',['id'=>$id]))===0);
    $f['reset_tokens']=[bin2hex(random_bytes(32)),bin2hex(random_bytes(32))];
    $f['reset_passwords']=[bin2hex(random_bytes(16)),bin2hex(random_bytes(16))];
    foreach($f['reset_tokens'] as $token)$repo->all("INSERT INTO password_reset_tokens(user_id,token_hash,expires_at) VALUES (:id,:hash,now()+interval '30 minutes')",['id'=>$f['users']['other']['id'],'hash'=>hash('sha256',$token)]);
    file_put_contents(__DIR__.'/.runtime/person1-fixtures.json',json_encode($f));
    [$first,$fp]=worker('reset-one');[$second,$sp]=worker('reset-two');
    if(trim(fgets($fp[1]))!=='READY'||trim(fgets($sp[1]))!=='READY')throw new RuntimeException('Reset workers not ready.');
    fwrite($fp[0],"GO\n");fflush($fp[0]);fwrite($sp[0],"GO\n");fflush($sp[0]);
    $outcomes=[trim(fgets($fp[1])),trim(fgets($sp[1]))];
    foreach(array_merge($fp,$sp) as $pipe)fclose($pipe);
    $c1=proc_close($first);$c2=proc_close($second);sort($outcomes);
    verify('two valid reset tokens race without deadlock, exactly one wins',$outcomes===['INVALID','RESET']&&$c1===0&&$c2===0);
    verify('reset race consumes all outstanding tokens',(int)$repo->one('SELECT count(*) n FROM password_reset_tokens WHERE user_id=:id AND consumed_at IS NULL',['id'=>$f['users']['other']['id']])['n']===0);
}catch(Throwable $e){$results[]=['test'=>'concurrency exception','result'=>'FAIL','class'=>get_class($e),'message'=>$e->getMessage()];}
file_put_contents(__DIR__.'/../docs/person1-concurrency-results.json',json_encode($results,JSON_PRETTY_PRINT));
$failures=count(array_filter($results,fn($x)=>$x['result']==='FAIL'));echo json_encode(['checks'=>count($results),'failures'=>$failures]);exit($failures?1:0);
