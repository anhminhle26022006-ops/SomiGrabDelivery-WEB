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
for($i=0;$i<$limit;$i++){if(!$worker->once())break;$jobs++;}
echo json_encode(['events_processed'=>$events,'jobs_attempted'=>$jobs]).PHP_EOL;
