<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
require_once __DIR__.'/../app/Config/database.php';
$missing=false;
foreach(['SOMI_DB_HOST','SOMI_DB_PORT','SOMI_DB_NAME','SOMI_DB_USER','SOMI_DB_PASSWORD','SOMI_DB_SSLMODE','GEOAPIFY_API_KEY'] as $name){$ok=getenv($name)!==false&&getenv($name)!=='';echo $name.'='.($ok?'OK':'MISSING').PHP_EOL;$missing=$missing||!$ok;}
foreach(['pdo_pgsql','curl','mbstring','bcmath'] as $extension){$ok=extension_loaded($extension);echo $extension.'='.($ok?'OK':'MISSING').PHP_EOL;$missing=$missing||!$ok;}
echo 'GEOAPIFY_BROWSER_API_KEY='.(getenv('GEOAPIFY_BROWSER_API_KEY') ? 'OK' : 'MISSING (map tiles only; does not block address API)').PHP_EOL;
if($missing)exit(1);
try{$db=somi_db();$q=$db->query("SELECT to_regclass('somi.customer_addresses') IS NOT NULL");if(!$q->fetchColumn())throw new RuntimeException('Schema missing');echo 'Supabase=CONNECTED; address schema=OK'.PHP_EOL;}catch(Throwable $e){echo 'Supabase=FAILED; check connection settings, password and migrations.'.PHP_EOL;exit(1);}
