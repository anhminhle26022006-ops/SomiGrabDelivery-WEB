<?php
declare(strict_types=1);
require_once __DIR__.'/../_kernel.php';
require_once __DIR__.'/../../../app/Services/AccountAvatarService.php';
require_once __DIR__.'/../../../app/Services/AuthRateLimiter.php';
$method=$_SERVER['REQUEST_METHOD']??'';
$content=$method==='GET'&&isset($_GET['content']);
somi_run_api(['GET','POST','DELETE'],['CUSTOMER','SHIPPER','ADMIN'],$method==='GET'?'profile.read_own':'profile.update_own',$method!=='GET',static function(PDO $db,int $user,string $role)use($method,$content):array{
    $service=new AccountAvatarService($db);
    if ($method==='DELETE') return [200,$service->remove($user,$role)];
    if ($method==='POST') {
        AuthRateLimiter::hit($db,'avatar:'.$user,10,60);
        if (!preg_match('/^multipart\/form-data\s*;/i',$_SERVER['CONTENT_TYPE']??'')) throw new DeliveryApiError(415,'MULTIPART_REQUIRED','Upload the image as multipart/form-data.');
        $file=$_FILES['avatar']??[];
        if (in_array($file['error']??null,[UPLOAD_ERR_INI_SIZE,UPLOAD_ERR_FORM_SIZE],true)) throw new DeliveryApiError(422,'AVATAR_SIZE_INVALID','Avatar must be at most 1 MiB.');
        if (($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name']??'')) DeliveryInput::invalid('avatar');
        $bytes=file_get_contents($file['tmp_name'],false,null,0,1048577);
        return [200,$service->save($user,$role,$bytes===false?'':$bytes)];
    }
    return [200,$content?$service->content($user):$service->metadata($user)];
},false,$content?static function(array $file):void{
    header('Content-Type: '.$file['mime_type']);header('X-Content-Type-Options: nosniff');header('Cache-Control: private, no-store');header('Content-Length: '.strlen($file['bytes']));echo $file['bytes'];
}:null);
