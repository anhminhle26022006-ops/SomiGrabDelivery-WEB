<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
require_once __DIR__.'/../app/Config/database.php';
if(in_array('--baseline',$argv,true)){
    $db=somi_db();if(!$db->query("SELECT to_regclass('somi.users')")->fetchColumn())$db->exec(file_get_contents(__DIR__.'/../database/SOMI_database.sql'));
}
// One canonical runner applies SQL plus the approved pricing release functions.
$argv=['migrate-customer-orders.php','--apply'];
require __DIR__.'/../database/migrate-customer-orders.php';
