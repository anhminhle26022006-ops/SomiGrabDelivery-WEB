<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
// Run inside the web container so retention can access the same private disk.
require_once __DIR__.'/../app/Config/database.php';
$stopping=false;
if(function_exists('pcntl_async_signals')){pcntl_async_signals(true);pcntl_signal(SIGTERM,function()use(&$stopping){$stopping=true;});pcntl_signal(SIGINT,function()use(&$stopping){$stopping=true;});}
$once=in_array('--once',$argv,true);
do {
    $started=microtime(true);
    foreach(['person1-worker.php','person3-worker.php'] as $script){if($stopping)break;$command=escapeshellarg(PHP_BINARY).' '.escapeshellarg(__DIR__.'/'.$script).' --limit=20';passthru($command,$code);if($code!==0)fwrite(STDERR,'Scheduler: '.$script.' failed with exit '.$code."\n");}
    if($once)break;
    while(!$stopping&&microtime(true)-$started<60)sleep(1);
}while(!$stopping);
