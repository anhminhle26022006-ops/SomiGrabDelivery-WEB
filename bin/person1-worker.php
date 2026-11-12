<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
require_once __DIR__.'/../app/Config/database.php';
require_once __DIR__.'/../app/Services/Person1Worker.php';
$limit=20;foreach($argv as $arg)if(str_starts_with($arg,'--limit='))$limit=max(1,min(500,(int)substr($arg,8)));
$db=somi_db();$events=0;$jobs=0;$worker=new Person1Worker($db);
for($i=0;$i<$limit;$i++){
    try{if(!Person1EventConsumer::consumeOne($db))break;$events++;}
    catch(Throwable $e){fwrite(STDERR,'Event processing failed: '.get_class($e).PHP_EOL);exit(1);}
}
// Sweep also covers NoShipperFound events consumed before the auto-cancel policy release.
$q=$db->prepare("SELECT order_id FROM orders WHERE status='NO_SHIPPER_FOUND' ORDER BY order_id LIMIT ?");$q->bindValue(1,$limit,PDO::PARAM_INT);$q->execute();$cancelled=0;
foreach($q->fetchAll(PDO::FETCH_COLUMN) as $orderId){
    try{$db->beginTransaction();if(Person1EventConsumer::resolveNoShipper($db,(int)$orderId))$cancelled++;$db->commit();}
    catch(Throwable $e){if($db->inTransaction())$db->rollBack();fwrite(STDERR,'Auto cancellation failed: '.get_class($e).PHP_EOL);exit(1);}
}
for($i=0;$i<$limit;$i++){if(!$worker->once())break;$jobs++;}
echo json_encode(['events_processed'=>$events,'jobs_attempted'=>$jobs,'no_shipper_cancelled'=>$cancelled]).PHP_EOL;
