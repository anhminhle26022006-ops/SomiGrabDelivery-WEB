<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
require __DIR__ . '/../app/Services/RequestOrigin.php';
$cases = [
 ['http direct','http://somi.invalid','somi.invalid','off','same-origin','',true,false],
 ['https direct','https://somi.invalid','somi.invalid','on','same-origin','',true,true],
 ['proxy https','https://somi.invalid','somi.invalid','off','same-origin','',true,true],
 ['proxy lacks metadata','https://somi.invalid','somi.invalid','off','','',false,false],
 ['proxy same-site insufficient','https://somi.invalid','somi.invalid','off','same-site','',false,false],
 ['foreign domain','https://evil.invalid','somi.invalid','off','same-origin','',false,false],
 ['suffix attack','https://somi.invalid.evil.invalid','somi.invalid','off','same-origin','',false,false],
 ['foreign port','https://somi.invalid:8443','somi.invalid','off','same-origin','',false,false],
 ['opaque origin','null','somi.invalid','off','same-origin','',false,false],
 ['credentials','https://user@somi.invalid','somi.invalid','off','same-origin','',false,false],
 ['path','https://somi.invalid/evil','somi.invalid','off','same-origin','',false,false],
 ['query','https://somi.invalid?evil','somi.invalid','off','same-origin','',false,false],
 ['fragment','https://somi.invalid#evil','somi.invalid','off','same-origin','',false,false],
 ['default port','https://somi.invalid:443','somi.invalid','off','same-origin','',true,true],
 ['case normalized','HTTPS://SOMI.INVALID','somi.invalid','off','same-origin','',true,true],
 ['http local port','http://localhost:3033','localhost:3033','off','same-origin','',true,false],
 ['ipv6 local','http://[::1]:3033','[::1]:3033','off','same-origin','',true,false],
 ['configured proxy','https://public.invalid','internal.invalid','off','','https://public.invalid/app',true,true],
 ['configured foreign origin','https://evil.invalid','internal.invalid','off','','https://public.invalid',false,false],
 ['https downgrade','http://somi.invalid','somi.invalid','on','same-origin','',false,true],
];
$oldApp = getenv('SOMI_APP_URL'); $oldRender = getenv('RENDER_EXTERNAL_URL');
putenv('RENDER_EXTERNAL_URL'); $failures=[];
foreach($cases as [$name,$origin,$host,$https,$site,$configured,$allowed,$secure]) {
 $_SERVER=['HTTP_HOST'=>$host,'HTTPS'=>$https,'HTTP_ORIGIN'=>$origin,'HTTP_SEC_FETCH_SITE'=>$site,'HTTP_X_FORWARDED_PROTO'=>'https'];
 putenv($configured === '' ? 'SOMI_APP_URL' : 'SOMI_APP_URL='.$configured);
 if(somi_origin_allowed($origin)!==$allowed || somi_session_is_secure()!==$secure) $failures[]=$name;
}
putenv('SOMI_APP_URL'); putenv('RENDER_EXTERNAL_URL=https://hosted.invalid');
$_SERVER=['HTTP_HOST'=>'hosted.invalid','HTTP_ORIGIN'=>'https://hosted.invalid','HTTPS'=>'off'];
if(!somi_origin_allowed('https://hosted.invalid') || !somi_session_is_secure()) $failures[]='hosting public URL';
putenv($oldApp === false ? 'SOMI_APP_URL' : 'SOMI_APP_URL='.$oldApp);
putenv($oldRender === false ? 'RENDER_EXTERNAL_URL' : 'RENDER_EXTERNAL_URL='.$oldRender);
echo json_encode(['checks'=>count($cases)+1,'failures'=>$failures],JSON_PRETTY_PRINT).PHP_EOL;
exit($failures ? 1 : 0);
