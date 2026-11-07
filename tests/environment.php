<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
require_once __DIR__.'/../app/Config/environment.php';
$file=tempnam(sys_get_temp_dir(),'somi-env-');$prefix='SOMI_ENV_TEST_'.strtoupper(bin2hex(random_bytes(5))).'_';$keys=[];$checks=0;
function check(bool $ok,string $label):void{global $checks;$checks++;if(!$ok)throw new RuntimeException('FAILED: '.$label);}
function keyName(string $name):string{global $prefix,$keys;$key=$prefix.$name;$keys[]=$key;return $key;}
try{
    $plain=keyName('PLAIN');$quoted=keyName('QUOTED');$single=keyName('SINGLE');$escaped=keyName('ESCAPED');$literal=keyName('LITERAL');$blank=keyName('BLANK');$priority=keyName('PRIORITY');$export=keyName('EXPORT');$empty=keyName('EMPTY');
    putenv($priority.'=from-server');putenv($empty.'=');
    $data="\xEF\xBB\xBF# fixture\r\n".$plain."=alpha # comment\r\n".$quoted.'="with # and spaces" # trailing comment'."\r\n".$single."='C:\\test\\path'\r\n".$escaped.'="quote: \" slash: \\\\ newline: \\n"'."\r\n".$literal.'="${SHELL_VAR} $(do-not-run) `do-not-run`"'."\r\n".$blank."=\r\n".$priority."=from-file\r\nexport ".$export."=yes\r\n".$empty."=from-file\r\n";
    file_put_contents($file,$data);somi_load_env($file);
    check(getenv($plain)==='alpha','unquoted inline comment');check(getenv($quoted)==='with # and spaces','quoted hash and spaces');check(getenv($single)==='C:\\test\\path','single-quoted backslashes');check(getenv($escaped)==="quote: \" slash: \\ newline: \n",'double-quoted escapes');check(getenv($literal)==='${SHELL_VAR} $(do-not-run) `do-not-run`','no interpolation or execution');check(getenv($blank)===false,'blank template ignored');check(getenv($priority)==='from-server','process overrides file');check(getenv($export)==='yes','optional export prefix');check(getenv($empty)==='','explicit empty process value preserved');
    $atomic=keyName('ATOMIC');$secret='fixture-secret-not-for-error-output';file_put_contents($file,$atomic."=new\nINVALID ".$secret."\n");$rejected=false;try{somi_load_env($file);}catch(RuntimeException $e){$rejected=true;check(!str_contains($e->getMessage(),$secret),'errors never include file contents');}check($rejected&&getenv($atomic)===false,'invalid file never partially applies');
    file_put_contents($file,$atomic.'="unclosed');$rejected=false;try{somi_load_env($file);}catch(RuntimeException $e){$rejected=true;}check($rejected,'unclosed quotes rejected');
    file_put_contents($file,$atomic.'="valid" trailing');$rejected=false;try{somi_load_env($file);}catch(RuntimeException $e){$rejected=true;}check($rejected,'trailing text rejected');
    somi_load_env($file.'.missing');check(true,'missing file is optional');
    echo json_encode(['result'=>'PASS','checks'=>$checks,'external_requests'=>0,'database_writes'=>0]).PHP_EOL;
}catch(Throwable $e){fwrite(STDERR,$e->getMessage().PHP_EOL);exit(1);}
finally{if(is_file($file))unlink($file);foreach($keys as $key)putenv($key);}
