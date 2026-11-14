<?php
declare(strict_types=1);
require_once __DIR__.'/shipper_fixtures.php';
require_once __DIR__.'/../app/Services/ShipperAvailabilityService.php';
require_once __DIR__.'/../app/Services/MatchingService.php';
require_once __DIR__.'/../app/Services/OfferAcceptService.php';
require_once __DIR__.'/../app/Services/CustomerOrderService.php';

if(($argv[1]??'')==='worker'){
    $schema=$argv[2]??'';
    if(!preg_match('/^shipper_race_[a-f0-9]{16}$/D',$schema))exit(2);
    $db=somi_db();$db->exec('SET search_path TO "'.$schema.'", pg_catalog');
    echo "READY\n";flush();fgets(STDIN);
    try{$db->beginTransaction();
        if($argv[3]==='accept')(new OfferAcceptService($db))->accept((int)$argv[4],(int)$argv[5]);
        else (new CustomerOrderService($db))->cancel((int)$argv[4],(int)$argv[5],'Concurrent customer cancellation');
        $db->commit();echo "OK\n";
    }catch(DeliveryApiError $e){$db->rollBack();echo $e->errorCode."\n";}
    catch(Throwable $e){if($db->inTransaction())$db->rollBack();fwrite(STDERR,$e->getMessage());echo "ERROR\n";exit(1);}
    exit;
}
if(($argv[1]??'')!=='--isolated')exit("Usage: php -d extension=pdo_pgsql tests/open_dispatch_race.php --isolated\nCreates and removes an isolated test schema; no application tables are modified.\n");
$schema='shipper_race_'.bin2hex(random_bytes(8));$db=null;
function concurrently(string $schema,array $actions):array{
    $workers=[];
    foreach($actions as $action){
        $cmd=array_merge([PHP_BINARY,'-d','extension=pdo_pgsql',__FILE__,'worker',$schema],array_map('strval',$action));
        $proc=proc_open($cmd,[['pipe','r'],['pipe','w'],['pipe','w']],$pipes);
        if(!is_resource($proc)||trim((string)fgets($pipes[1]))!=='READY')throw new RuntimeException('Race worker failed to start.');
        $workers[]=[$proc,$pipes];
    }
    foreach($workers as [,$pipes]){fwrite($pipes[0],"GO\n");fflush($pipes[0]);}
    $results=[];
    foreach($workers as [$proc,$pipes]){
        $results[]=trim((string)fgets($pipes[1]));$err=stream_get_contents($pipes[2]);foreach($pipes as $pipe)fclose($pipe);
        if(proc_close($proc)!==0)throw new RuntimeException('Race worker: '.$err);
    }
    return $results;
}
try{
    $db=somi_db();$db=shipper_test_db($schema);$matching=new MatchingService($db);
    tx($db,fn()=>(new ShipperAvailabilityService($db))->turnOn(SHIPPER_A,NEAR[0],NEAR[1]));
    tx($db,fn()=>(new ShipperAvailabilityService($db))->turnOn(SHIPPER_B,FARTHER[0],FARTHER[1]));
    for($round=0;$round<3;$round++){
        $id=paid_order($db);tx($db,fn()=>$matching->dispatch($id));
        $a=(int)value($db,'SELECT offer_id FROM order_match_offers WHERE order_id=? AND shipper_id=?',[$id,SHIPPER_A]);
        $b=(int)value($db,'SELECT offer_id FROM order_match_offers WHERE order_id=? AND shipper_id=?',[$id,SHIPPER_B]);
        $results=concurrently($schema,[['accept',SHIPPER_A,$a],['accept',SHIPPER_B,$b]]);sort($results);
        check($results===['OFFER_LOST_RACE','OK'],'two simultaneous accepts have exactly one winner');
        check((int)value($db,"SELECT count(*) FROM order_assignments WHERE order_id=? AND assignment_status='ACTIVE'",[$id])===1,'one active assignment');
        check((int)value($db,'SELECT count(*) FROM deliveries WHERE order_id=?',[$id])===1,'one delivery record');
        $db->prepare("UPDATE order_assignments SET assignment_status='COMPLETED' WHERE order_id=?")->execute([$id]);
        $id=paid_order($db);tx($db,fn()=>$matching->dispatch($id));
        $a=(int)value($db,'SELECT offer_id FROM order_match_offers WHERE order_id=? AND shipper_id=?',[$id,SHIPPER_A]);
        [$accept,$cancel]=concurrently($schema,[['accept',SHIPPER_A,$a],['cancel',CUSTOMER,$id]]);
        $status=value($db,'SELECT status FROM orders WHERE order_id=?',[$id]);
        $count=(int)value($db,'SELECT count(*) FROM order_assignments WHERE order_id=?',[$id]);
        check(($accept==='OK'&&$cancel==='ORDER_NOT_CANCELLABLE'&&$status==='SHIPPER_ASSIGNED'&&$count===1)
            ||($cancel==='OK'&&in_array($accept,['OFFER_NOT_PENDING','ORDER_NOT_AVAILABLE'],true)&&$status==='CANCELLED'&&$count===0),'accept/cancel race remains consistent');
        $db->prepare("UPDATE order_assignments SET assignment_status='COMPLETED' WHERE order_id=?")->execute([$id]);
    }
    echo "Open dispatch real concurrent-process checks passed: $checks\n";
}catch(Throwable $e){fwrite(STDERR,$e->getMessage()."\n");$GLOBALS['race_exit_code']=1;}
finally{
    if($db){if($db->inTransaction())$db->rollBack();$db->exec('SET search_path TO pg_catalog');$db->exec('DROP SCHEMA IF EXISTS "'.$schema.'" CASCADE');}
}
exit($GLOBALS['race_exit_code']??0);
