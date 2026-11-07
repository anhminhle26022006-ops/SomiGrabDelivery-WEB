<?php
declare(strict_types=1);
require_once __DIR__.'/../_kernel.php';
require_once __DIR__.'/../../../app/Services/AdminService.php';
$method=$_SERVER['REQUEST_METHOD']??'';
somi_run_api(['GET','POST','PUT'],['ADMIN'],'parcel.manage',$method!=='GET',static function(PDO $db,int $admin)use($method):array{
    if($method==='GET')return [200,['items'=>$db->query('SELECT * FROM parcel_categories ORDER BY category_id')->fetchAll()]];
    $id=$method==='PUT'?DeliveryInput::id($_GET['id']??null):null;return [$method==='POST'?201:200,['category'=>(new AdminService($db))->category($admin,somi_json_body(),$id)]];
});
