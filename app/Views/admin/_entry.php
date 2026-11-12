<?php
// Views are render fragments. Always enter through the authenticated front controller.
if (!defined('SOMI_FRONT_CONTROLLER')) {
    $script=str_replace('\\','/',$_SERVER['SCRIPT_NAME']??'');
    $basePath=explode('/app/Views/admin/',$script,2)[0];
    if(!str_contains($script,'/app/Views/admin/')){http_response_code(404);exit('Not found');}
    $view=pathinfo($script,PATHINFO_FILENAME);
    $allowed=['index','users','customers','shippers','orders','complaints','disputes','payments','reviews','notifications','audit-logs','backend'];
    if(!in_array($view,$allowed,true))$view='index';
    header('Location: '.$basePath.'/index.php?page=admin&view='.rawurlencode($view),true,303);
    exit;
}
