<?php
declare(strict_types=1);
$root=dirname(__DIR__);$path=parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH);
if($path!=='/__workspace-test')return require $root.'/index.php';
define('SOMI_FRONT_CONTROLLER',true);
if(!function_exists('e')){function e(string $s):string{return htmlspecialchars($s,ENT_QUOTES,'UTF-8');}function assetUrl(string $path):string{return '/'.$path;}}
$page=($_GET['role']??'')==='shipper'?'shipper':'customer';
$allowedViews=['dashboard','account','create','orders','order','payment','matching','tracking','review','report','reports','notifications','addresses','offers','active','history','earnings','support'];
$view=in_array($_GET['view']??'', $allowedViews, true)?$_GET['view']:'dashboard';
$sessionUserId=77;$shipperPage=$view;$hasShipperWorkspace=true;$hasCustomerWorkspace=true;
$sessionUser=['user_id'=>77,'full_name'=>'Shipper Customer Fixture','email'=>'fixture@example.invalid','phone'=>'0900000077','app_language'=>'vi','account_status'=>'ACTIVE'];
?><!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="/public/assets/css/app.css"><link rel="stylesheet" href="/public/assets/vendor/bootstrap/bootstrap.min.css"><link rel="stylesheet" href="/public/assets/css/bootstrap-workspaces.css"><?php if($page==='shipper'):?><link rel="stylesheet" href="/public/assets/css/modules/shipper.css"><?php endif;?><?php if($page!=='customer'):?><script defer src="/public/assets/vendor/bootstrap/bootstrap.bundle.min.js"></script><?php endif;?><script defer src="/public/assets/js/app.js"></script></head><body data-page="<?=e($page)?>" data-c-view="<?=e($view)?>" data-shipper-view="<?=e($view)?>">
<script type="application/json" id="approved-pricing-config"><?= json_encode(require $root.'/app/Config/delivery-pricing.php', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script defer src="/public/assets/js/delivery-price.js"></script>
<?php $_GET['view']=$view;require $root.'/app/Views/'.$page.'/index.php';?>
<?php if($page==='shipper'):?><script defer src="/public/assets/js/shipper-navigation.js"></script><script defer src="/public/assets/js/shipper.js"></script><?php endif;?>
</body></html>
