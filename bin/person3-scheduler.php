<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
// Run inside the web container with the same DB/storage environment as the web app.
require_once __DIR__.'/../app/Config/database.php';
$stopping=false;
if(function_exists('pcntl_async_signals')){pcntl_async_signals(true);pcntl_signal(SIGTERM,function()use(&$stopping){$stopping=true;});pcntl_signal(SIGINT,function()use(&$stopping){$stopping=true;});}
$lastRetention=0;
$once=in_array('--once',$argv,true);
// Print configuration checks only, never credentials; diagnose the deployed environment.
passthru(escapeshellarg(PHP_BINARY).' '.escapeshellarg(__DIR__.'/person3-check.php'),$readinessCode);
if($readinessCode!==0)fwrite(STDERR,"Scheduler: Person 3 readiness checks failed; inspect the boolean checks above.\n");
do {
    $started=microtime(true);
    foreach(['person1-worker.php','shipper-worker.php','person1-worker.php','person3-worker.php'] as $script){if($stopping)break;$command=escapeshellarg(PHP_BINARY).' '.escapeshellarg(__DIR__.'/'.$script).($script==='shipper-worker.php'?' --seconds=0':' --limit=20');passthru($command,$code);if($code!==0)fwrite(STDERR,'Scheduler: '.$script.' failed with exit '.$code."\n");}
    if(!$stopping && time()-$lastRetention>=86400){
        passthru(escapeshellarg(PHP_BINARY).' '.escapeshellarg(__DIR__.'/shipper-retention.php'),$retentionCode);
        if($retentionCode===0)$lastRetention=time();
        else fwrite(STDERR,"Scheduler: GPS retention failed; retrying next cycle.\n");
    }
    echo json_encode(['scheduler_cycle_finished_at'=>gmdate('c'),'duration_ms'=>(int)round((microtime(true)-$started)*1000),'gps_retention_last_success_at'=>$lastRetention?gmdate('c',$lastRetention):null]).PHP_EOL;
    if($once)break;
    while(!$stopping&&microtime(true)-$started<60)sleep(1);
}while(!$stopping);
