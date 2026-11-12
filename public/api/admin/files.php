<?php
declare(strict_types=1);
require_once __DIR__.'/../_kernel.php';
require_once __DIR__.'/../../../app/Services/AdminMediaService.php';
somi_run_api(['GET'],['ADMIN'],'storage.read_support',true,static fn(PDO $db,int $admin):array=>[200,(new AdminMediaService($db))->listing($admin,$_GET)]);
