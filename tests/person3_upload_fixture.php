<?php
declare(strict_types=1);
// Test-only router: explicitly enabled loopback CLI-server, never an application endpoint.
if(PHP_SAPI!=='cli-server'||getenv('SOMI_PERSON3_UPLOAD_TEST')!=='1'||($_SERVER['REMOTE_ADDR']??'')!=='127.0.0.1'){http_response_code(404);exit;}
require_once __DIR__.'/../app/Config/database.php';
require_once __DIR__.'/../app/Services/PrivateStorageService.php';
$db=null;
try {
    $db=somi_db();$db->exec('SET search_path TO pg_temp,pg_catalog');
    $db->exec("CREATE TEMP TABLE orders(order_id bigint,customer_id bigint,status text); INSERT INTO orders VALUES(1,2,'SHIPPER_ASSIGNED'); CREATE TEMP TABLE order_assignments(order_id bigint,shipper_id bigint,assignment_status text); INSERT INTO order_assignments VALUES(1,4,'ACTIVE'); CREATE TEMP TABLE support_tickets(ticket_id bigint,opened_by bigint,status text,opened_role text); INSERT INTO support_tickets VALUES(1,2,'NEW','CUSTOMER')");
    $db->exec("CREATE TEMP TABLE private_files(file_id bigint GENERATED ALWAYS AS IDENTITY,owner_id bigint,order_id bigint,ticket_id bigint,message_id bigint,purpose text,object_key text,original_name text,mime_type text,byte_size bigint,checksum_sha256 text,created_at timestamptz DEFAULT now(),delete_after timestamptz DEFAULT 'infinity',deleted_at timestamptz)");
    $db->beginTransaction();$service=new PrivateStorageService($db);$result=$service->upload(2,'CUSTOMER',$_POST,$_FILES['file']??[]);
    $signed=$service->signed((int)$result['file_id'],2,'CUSTOMER');parse_str($signed['download_query'],$query);$download=$service->download((int)$result['file_id'],2,'CUSTOMER',$query);
    $result['download_hash']=hash_file('sha256',$download['path']);$result['download_size']=filesize($download['path']);
    $db->exec("UPDATE private_files SET delete_after=now()-interval '1 second'");$result['deleted_count']=$service->retain();$result['object_removed']=!is_file($download['path']);$result['orphans_removed']=$service->pruneOrphans();$db->commit();
    header('Content-Type: application/json');echo json_encode($result,JSON_THROW_ON_ERROR);
}catch(Throwable $e){if($db instanceof PDO&&$db->inTransaction())$db->rollBack();http_response_code($e instanceof DeliveryApiError?$e->httpStatus:503);header('Content-Type: application/json');echo json_encode(['code'=>$e instanceof DeliveryApiError?$e->errorCode:'FIXTURE_FAILED']);}
