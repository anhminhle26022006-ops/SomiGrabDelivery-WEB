<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../app/Config/database.php';
require_once __DIR__.'/../app/Services/Person3Worker.php';
$limit=20;foreach(array_slice($argv,1) as $arg){if(!preg_match('/^--limit=([1-9][0-9]{0,2})$/D',$arg,$m)){fwrite(STDERR,"Usage: php bin/person3-worker.php --limit=20\n");exit(2);}$limit=(int)$m[1];}
try{$worker=new Person3Worker(somi_db());$n=0;while($n<$limit&&$worker->consumeOne())$n++;$earned=$worker->settle($limit);$worker->retain();echo "Person3: $n events, $earned earnings.\n";}catch(Throwable $e){fwrite(STDERR,'Person3 worker failed: '.get_class($e).', code '.$e->getCode()."\n");exit(1);}
