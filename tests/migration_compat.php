<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
require_once __DIR__.'/../app/Config/database.php';
$db=somi_db();$checks=0;
function check(bool $ok,string $name):void{global $checks;$checks++;if(!$ok)throw new RuntimeException($name);}
try{
    $db->beginTransaction();$db->exec('SET LOCAL search_path TO pg_temp,pg_catalog');
    $sql=file_get_contents(__DIR__.'/../database/SOMI_database.sql');
    $sql=preg_replace('/^BEGIN;\s*|^COMMIT;\s*|^CREATE SCHEMA[^;]+;\s*|^SET search_path[^;]+;\s*/m','',$sql);
    $sql=preg_replace('/CREATE FUNCTION erase_identity_document_on_account_close\(\).*?(?=CREATE TABLE shipper_score_policies)/s','',$sql);
    $db->exec(str_replace('CREATE TABLE ','CREATE TEMP TABLE ',$sql));
    // Model the earlier branch's table without changing its historical migration.
    $db->exec("INSERT INTO users(full_name,email,phone,password_hash,nationality_code,date_of_birth,gender,app_language,account_status) VALUES('Fixture','fixture@example.invalid','0900000001','test','VN','2000-01-01','M','vi','ACTIVE');
      CREATE TEMP TABLE nationality_change_requests(request_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,user_id BIGINT NOT NULL REFERENCES users(user_id),old_country_code CHAR(2) NOT NULL,requested_country_code CHAR(2) NOT NULL,reason TEXT NOT NULL,status VARCHAR(20) NOT NULL DEFAULT 'PENDING',review_note TEXT,reviewed_by BIGINT,reviewed_at TIMESTAMPTZ,created_at TIMESTAMPTZ NOT NULL DEFAULT now(),CHECK(old_country_code<>requested_country_code));
      CREATE UNIQUE INDEX ux_nationality_pending ON nationality_change_requests(user_id) WHERE status='PENDING';
      INSERT INTO nationality_change_requests(user_id,old_country_code,requested_country_code,reason) VALUES(1,'VN','US','Fixture');");
    foreach(['20261105_customer_orders','20261106_person1_backend','20261107_person1_compat']as $name){
        $sql=file_get_contents(__DIR__.'/../database/migrations/'.$name.'.sql');$sql=preg_replace('/^BEGIN;\s*|^COMMIT;\s*|^SET LOCAL search_path[^;]+;\s*/m','',$sql);
        $sql=str_replace('CREATE TABLE IF NOT EXISTS ','CREATE TEMP TABLE IF NOT EXISTS ',$sql);$db->exec($sql);
    }
    check($db->query('SELECT nationality_code FROM nationality_change_requests WHERE request_id=1')->fetchColumn()==='US','legacy request preserved');
    $db->exec("UPDATE nationality_change_requests SET status='REJECTED' WHERE request_id=1; INSERT INTO nationality_change_requests(user_id,nationality_code,reason) VALUES(1,'JP','Fixture');");
    check((int)$db->query('SELECT count(*) FROM nationality_change_requests')->fetchColumn()===2,'new request works alongside legacy rows');
    check($db->query('SELECT old_country_code FROM nationality_change_requests WHERE request_id=2')->fetchColumn()===null,'legacy country does not block new workflow');
    check((int)$db->query('SELECT count(*) FROM pricing_configuration_drafts')->fetchColumn()===0,'draft table contains no invented policies');
    $db->exec('ALTER TABLE pricing_configuration_drafts DISABLE ROW LEVEL SECURITY');
    $db->exec("INSERT INTO pricing_configuration_drafts(currency_code,configuration,created_by) VALUES('VND','{}',1)");
    check((int)$db->query('SELECT count(*) FROM pricing_policies WHERE active')->fetchColumn()===0,'draft cannot activate live pricing');
    $db->rollBack();echo json_encode(['result'=>'PASS','checks'=>$checks,'persistent_business_writes'=>0]).PHP_EOL;
}catch(Throwable $e){if($db->inTransaction())$db->rollBack();fwrite(STDERR,get_class($e).': '.$e->getMessage().PHP_EOL);exit(1);}
