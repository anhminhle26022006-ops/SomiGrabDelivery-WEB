<?php
declare(strict_types=1);
require_once __DIR__.'/_kernel.php';
require_once __DIR__.'/../../app/Services/PrivateStorageService.php';
$permission=($_SESSION['somi_role']??'')==='ADMIN'?'storage.read_support':'person3.use';
if(($_SERVER['REQUEST_METHOD']??'')==='GET'&&isset($_GET['signature'])){
 somi_run_api(['GET'],['CUSTOMER','SHIPPER','ADMIN'],$permission,true,fn(PDO $db,int $user,string $role)=>[200,(new PrivateStorageService($db))->download(DeliveryInput::id($_GET['file_id']??null),$user,$role,$_GET)],false,function(array $file):void {
    header('Content-Type: '.$file['mime_type']);header('X-Content-Type-Options: nosniff');header('Cache-Control: private, no-store');header('Content-Disposition: attachment; filename="download"; filename*=UTF-8\'\''.rawurlencode($file['original_name']));header('Content-Length: '.(isset($file['bytes'])?strlen($file['bytes']):filesize($file['path'])));if(isset($file['bytes']))echo $file['bytes'];else readfile($file['path']);
 });
}else{
 somi_run_api(['GET','POST','DELETE'],['CUSTOMER','SHIPPER','ADMIN'],$permission,true,function(PDO $db,int $user,string $role):array {
    $service=new PrivateStorageService($db);
    if(isset($_GET['ticket_id'])&&$role!=='ADMIN')throw new DeliveryApiError(403,'ADMIN_REQUIRED','Admin workspace required.');
    if($_SERVER['REQUEST_METHOD']==='DELETE')return [200,$service->discard($user,DeliveryInput::id($_GET['file_id']??null),$role)];
    if(isset($_GET['reference_file_id']))return [200,$service->reference($user,$role,$_GET)];
    if($_SERVER['REQUEST_METHOD']==='POST'){if($role==='ADMIN')throw new DeliveryApiError(403,'UPLOAD_FORBIDDEN','Customer or Shipper upload only.');if(!preg_match('/^multipart\/form-data\s*;/i',$_SERVER['CONTENT_TYPE']??''))throw new DeliveryApiError(415,'MULTIPART_REQUIRED','Use multipart/form-data for upload.');return [201,$service->upload($user,$role,$_POST,$_FILES['file']??[])];}
    if(!isset($_GET['file_id']))return [200,$service->listing($user,$_GET,$role)];
    return [200,$service->signed(DeliveryInput::id($_GET['file_id']??null),$user,$role)];
 });
}
