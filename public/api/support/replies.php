<?php
declare(strict_types=1);
require_once __DIR__.'/../_kernel.php';
require_once __DIR__.'/../../../app/Services/InteractionService.php';
$permission=($_SESSION['somi_role']??'')==='ADMIN'?'support.manage_all':'person3.use';
somi_run_api(['GET','POST'],['CUSTOMER','SHIPPER','ADMIN'],$permission,true,function(PDO $db,int $id,string $role):array {
    $write=$_SERVER['REQUEST_METHOD']==='POST';return [200,(new InteractionService($db))->replies($id,$role,$write?somi_json_body(false):$_GET,$write)];
});
