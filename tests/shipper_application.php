<?php
declare(strict_types=1);
require_once __DIR__.'/../app/Services/ShipperApplicationService.php';
require_once __DIR__.'/../app/Services/ShipperApplicationSchema.php';
require_once __DIR__.'/../app/Services/AdminService.php';
$checks=0;
function app_check(bool $ok,string $label):void {global $checks;$checks++;if(!$ok)throw new RuntimeException($label);}
function app_reject(callable $fn,string $code='VALIDATION_ERROR'):void {
    try {$fn();} catch (DeliveryApiError $e) {app_check($e->errorCode===$code, 'Expected '.$code.', got '.$e->errorCode);return;}
    throw new RuntimeException('Expected rejection: '.$code);
}
function app_tx(PDO $db,callable $fn):mixed {$db->beginTransaction();try{$result=$fn();$db->commit();return $result;}catch(Throwable $e){$db->rollBack();throw $e;}}
$types=['IDENTITY_FRONT','IDENTITY_BACK','PORTRAIT','DRIVER_LICENSE','DRIVER_LICENSE_BACK','VEHICLE_REGISTRATION','VEHICLE_INSURANCE'];
$input=['residential_address'=>'123 Nguyễn Trãi, TP HCM','operating_area'=>'TP HCM','emergency_contact_name'=>'Nguyễn Văn A','emergency_contact_phone'=>'0900000001','emergency_contact_relationship'=>'Anh trai',
    'vehicle_type'=>'MOTORBIKE','vehicle_registration'=>'59-X1 123.45','vehicle_brand'=>'Honda','vehicle_model'=>'Wave','vehicle_color'=>'Đen','vehicle_year'=>'2020','vehicle_owner_name'=>'Test Applicant','vehicle_ownership'=>'OWNED',
    'license_number'=>'012345678901','license_class'=>'A1','license_issued_on'=>'2020-01-01','license_expires_on'=>'2099-01-01','license_no_expiry'=>false,'insurance_expires_on'=>'2099-01-01','consent'=>true,
    'documents'=>array_map(static fn($type,$i)=>['document_type'=>$type,'reference_file_id'=>$i+1],$types,array_keys($types))];
$today=new DateTimeImmutable('2026-11-13');
$valid=ShipperApplicationService::validate($input,$today);
app_check($valid['vehicle_registration']==='59-X1123.45','Normalised licence plate');
foreach (['residential_address'=>'','vehicle_year'=>'2100','vehicle_type'=>'OTHER','license_issued_on'=>'2026-02-30','license_expires_on'=>'2026-11-13','insurance_expires_on'=>'2026-11-12','consent'=>false,'emergency_contact_phone'=>'abc'] as $key=>$value) {
    app_reject(fn()=>ShipperApplicationService::validate(array_replace($input,[$key=>$value]),$today));
}
app_reject(fn()=>ShipperApplicationService::validate(array_replace($input,['documents'=>array_slice($input['documents'],1)]),$today));
$duplicates=$input;$duplicates['documents'][1]['reference_file_id']=1;app_reject(fn()=>ShipperApplicationService::validate($duplicates,$today));
$borrowed=$input;$borrowed['vehicle_ownership']='BORROWED';app_reject(fn()=>ShipperApplicationService::validate($borrowed,$today));
$noExpiry=$input;$noExpiry['license_no_expiry']=true;unset($noExpiry['license_expires_on']);app_check(ShipperApplicationService::validate($noExpiry,$today)['license_expires_on']===null,'No-expiry licence');
$bicycle=$input;$bicycle['vehicle_type']='BICYCLE';$bicycle['documents']=array_slice($input['documents'],0,3);unset($bicycle['license_number'],$bicycle['insurance_expires_on'],$bicycle['vehicle_registration']);app_check(count(ShipperApplicationService::validate($bicycle,$today)['documents'])===3,'Bicycle requirements');
echo "PASS: application validation.\n";

// All writes below are restricted to connection-local temporary tables.
$db=somi_db();$db->exec('SET search_path TO pg_temp, pg_catalog');
$sql=file_get_contents(__DIR__.'/../database/SOMI_database.sql');
$sql=preg_replace('/^BEGIN;\s*|^COMMIT;\s*|^CREATE SCHEMA[^;]+;\s*|^SET search_path[^;]+;\s*/m','',$sql);
$sql=preg_replace('/CREATE FUNCTION erase_identity_document_on_account_close\(\).*?(?=CREATE TABLE shipper_score_policies)/s','',$sql);
$db->exec(str_replace('CREATE TABLE ','CREATE TEMP TABLE ',$sql));
foreach (['20261105_customer_orders','20261105_approved_pricing','20261105_distance_weight_pricing','20261106_person1_backend','20261111_person3','20261111_person3_complete','20261111_person3_supabase','20261112_shipper_policy_workflows'] as $migration) {
    $sql=file_get_contents(__DIR__.'/../database/migrations/'.$migration.'.sql');$sql=preg_replace('/^BEGIN;\s*|^COMMIT;\s*|^SET LOCAL search_path[^;]+;\s*/m','',$sql);$db->exec(str_replace('CREATE TABLE ','CREATE TEMP TABLE ',$sql));
}
app_check($db->query("SELECT current_setting('search_path')")->fetchColumn()==='pg_temp, pg_catalog','Temporary schema only');
app_tx($db,fn()=>ShipperApplicationSchema::apply($db));
putenv('SOMI_IDENTITY_ENCRYPTION_KEY='.base64_encode(random_bytes(32)));
putenv('SOMI_STORAGE_SIGNING_KEY='.bin2hex(random_bytes(32)));
$db->exec("INSERT INTO users(full_name,email,phone,password_hash,nationality_code,date_of_birth,gender,app_language,account_status,email_verified_at,phone_verified_at) VALUES ('Admin','admin@example.invalid','0900000001','test','VN','2000-01-01','M','vi','ACTIVE',now(),now()),('Applicant','applicant@example.invalid','0900000002','test','VN','2000-01-01','M','vi','ACTIVE',now(),now()),('Other','other@example.invalid','0900000003','test','VN','2000-01-01','M','vi','ACTIVE',now(),now());
INSERT INTO user_roles(user_id,role_id) SELECT v.id,r.role_id FROM (VALUES(1,'ADMIN'),(2,'CUSTOMER'),(3,'CUSTOMER'))v(id,role) JOIN roles r ON r.role_code=v.role;
INSERT INTO customer_profiles(user_id) VALUES(2),(3);
INSERT INTO shipper_profiles(user_id,vehicle_type,vehicle_registration) VALUES(2,'MOTORBIKE','59-X1123.45');
INSERT INTO user_identity_documents(user_id,document_type,document_country_code,identity_number_ciphertext,review_status) VALUES(2,'NATIONAL_ID','VN','fixture','PENDING');");
$svc=new ShipperApplicationService($db);$storage=new PrivateStorageService($db);
app_tx($db,fn()=>$svc->assertApplicant(2));
app_reject(fn()=>app_tx($db,fn()=>(new AdminService($db))->reviewShipper(1,2,'APPROVED','')),'APPLICATION_INCOMPLETE');
foreach ($types as $type) {
    $q=$db->prepare("INSERT INTO private_files(owner_id,purpose,object_key,original_name,mime_type,byte_size,checksum_sha256) VALUES(2,'KYC',?,'fixture.jpg','image/jpeg',100,?)");$q->execute([bin2hex(random_bytes(32)),hash('sha256',$type)]);
}
app_tx($db,fn()=>$svc->save(2,$valid));
$stored=json_decode($db->query('SELECT application_details FROM shipper_profiles WHERE user_id=2')->fetchColumn(),true);
app_check(!isset($stored['license_number'])&&str_starts_with($stored['license_number_ciphertext'],'v1:'),'Licence number encrypted');
app_check((int)$db->query('SELECT count(*) FROM shipper_documents WHERE deleted_at IS NULL')->fetchColumn()===7,'All documents linked');
app_check(!str_contains($db->query('SELECT string_agg(new_value::text,\' \') FROM audit_logs')->fetchColumn(),$input['license_number']),'Licence absent from audit');
$read=app_tx($db,fn()=>$svc->forAdmin(1,2));app_check(!isset($read['details']['license_number_ciphertext']),'Admin response excludes ciphertext');
$own=(new ShipperProfileService($db))->detail(2);
app_check($own['application']['residential_address']===$valid['residential_address'],'Applicant reads saved application address');
app_check(!isset($own['application']['license_number_ciphertext'])&&!isset($own['application']['license_number']),'Own display payload excludes licence secrets');
$status=(new ShipperKycService($db))->status(2);
app_check(count($status['documents'])===7 && !empty($status['documents'][0]['file_id']),'Submitted KYC returns authorized file identifiers');
app_reject(fn()=>app_tx($db,fn()=>$svc->forAdmin(3,2)),'PERMISSION_DENIED');
app_tx($db,fn()=>$storage->signed(1,2,'CUSTOMER'));app_check(true,'Applicant can access own KYC');
app_reject(fn()=>app_tx($db,fn()=>$storage->signed(1,3,'CUSTOMER')),'FILE_NOT_FOUND');
app_tx($db,fn()=>$svc->assertReviewable(2));
$db->exec("UPDATE private_files SET owner_id=3 WHERE file_id=1");
app_reject(fn()=>app_tx($db,fn()=>$svc->save(2,$valid)),'FILE_NOT_FOUND');
$db->exec("UPDATE private_files SET owner_id=2,delete_after=now()-interval '1 second' WHERE file_id=1");
app_reject(fn()=>app_tx($db,fn()=>$svc->assertReviewable(2)));
$db->exec("UPDATE private_files SET delete_after='infinity' WHERE file_id=1; UPDATE users SET date_of_birth=current_date-interval '17 years' WHERE user_id=2");
app_reject(fn()=>$svc->assertApplicant(2),'APPLICANT_AGE_REQUIRED');
$db->exec("UPDATE users SET date_of_birth='2000-01-01',phone_verified_at=NULL WHERE user_id=2");
app_reject(fn()=>$svc->assertApplicant(2),'CONTACT_VERIFICATION_REQUIRED');
$db->exec('UPDATE users SET phone_verified_at=now() WHERE user_id=2');
$result=app_tx($db,fn()=>(new AdminService($db))->reviewShipper(1,2,'APPROVED',''));
app_check($result['status']==='APPROVED','Complete application approved');
app_check((int)$db->query("SELECT count(*) FROM shipper_documents WHERE review_status='APPROVED'")->fetchColumn()===7,'Documents reviewed together');
echo "PASS: $checks checks, including encryption, ownership, expiry, age, permissions and approval using temporary PostgreSQL tables.\n";
