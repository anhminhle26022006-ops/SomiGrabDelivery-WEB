<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../app/Config/environment.php';somi_load_env();
$root=rtrim(getenv('SOMI_STORAGE_ROOT')?:'',"/\\");$project=realpath(__DIR__.'/..');
if($root===''||!preg_match('~^(?:/|[A-Za-z]:[/\\\\])~',$root)||preg_match('~^[A-Za-z]:$~',$root)){fwrite(STDERR,"Private Storage requires a dedicated absolute directory.\n");exit(1);}
if(!preg_match('/^somi-(files|private)(-[a-z0-9_-]+)?$/iD',basename(str_replace('\\','/',$root)))){fwrite(STDERR,"Use a dedicated directory named somi-files or somi-private.\n");exit(1);}
if(!is_dir($root)&&!mkdir($root,0700,true)){fwrite(STDERR,"Cannot initialize private Storage.\n");exit(1);}
$real=realpath($root);
if(!$real||!preg_match('/^somi-(files|private)(-[a-z0-9_-]+)?$/iD',basename(str_replace('\\','/',$real)))||str_starts_with(strtolower(str_replace('\\','/',$real)).'/',strtolower(str_replace('\\','/',$project)).'/')){fwrite(STDERR,"Storage must be a dedicated directory outside the application web root.\n");exit(1);}
if(PHP_OS_FAMILY!=='Windows'){chmod($real,0700);if(function_exists('posix_geteuid')&&posix_geteuid()===0){chown($real,'www-data');chgrp($real,'www-data');}}
echo "Private Storage initialized.\n";
