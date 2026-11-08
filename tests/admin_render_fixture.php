<?php
// Local browser fixture only. Production index.php never routes to this file.
define('SOMI_FRONT_CONTROLLER',true);
$root=dirname(__DIR__);$path=parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH);
if(str_starts_with($path,'/public/assets/')&&preg_match('/\.(css|js)$/D',$path)&&is_file($root.$path))return false;
if($path!=='/__admin-test'){http_response_code(404);exit;}
function e($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}
function assetUrl($path){return '/'.$path;}
$adminView=$_GET['view']??'index';
?><!doctype html><html lang="en"><head><meta charset="utf-8"><base href="/"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Admin UI Fixture</title><link rel="stylesheet" href="/public/assets/css/app.css"><link rel="stylesheet" href="/public/assets/css/modules/admin.css"><script defer src="/public/assets/js/app.js"></script><script defer src="/public/assets/js/admin.js"></script></head><body data-page="admin"><?php require $root.'/app/Views/admin/live.php'; ?></body></html>
