<?php
declare(strict_types=1);
require_once __DIR__.'/DeliveryApiError.php';

interface SmsProvider
{
    public function assertConfigured(): void;
    public function send(string $phone,string $code,string $key): void;
}

/** Adapter protocol; a real vendor is deliberately not selected by default. */
final class BridgeSmsProvider implements SmsProvider
{
    public function assertConfigured(): void
    {
        if(parse_url(getenv('SOMI_SMS_BRIDGE_URL')?:'',PHP_URL_SCHEME)!=='https'||!getenv('SOMI_SMS_BRIDGE_TOKEN'))throw new DeliveryApiError(503,'SMS_PROVIDER_UNAVAILABLE','SMS delivery is not configured.');
    }
    public function send(string $phone,string $code,string $key): void
    {
        $this->assertConfigured();$token=getenv('SOMI_SMS_BRIDGE_TOKEN');if(preg_match('/[\r\n]/',$token))throw new RuntimeException('Invalid SMS provider configuration.');
        $curl=curl_init(getenv('SOMI_SMS_BRIDGE_URL'));$body='';curl_setopt_array($curl,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode(['phone'=>$phone,'code'=>$code,'expires_in_seconds'=>300]),CURLOPT_HTTPHEADER=>['Content-Type: application/json','Authorization: Bearer '.$token,'Idempotency-Key: '.$key],CURLOPT_CONNECTTIMEOUT=>3,CURLOPT_TIMEOUT=>10,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_WRITEFUNCTION=>static function($handle,string $chunk)use(&$body):int{if(strlen($body)+strlen($chunk)>4096)return 0;$body.=$chunk;return strlen($chunk);}]);
        $ok=curl_exec($curl);$status=(int)curl_getinfo($curl,CURLINFO_HTTP_CODE);curl_close($curl);$response=json_decode($body,true);
        if($ok===false||$status<200||$status>=300||($response['accepted']??false)!==true)throw new DeliveryApiError(503,'SMS_PROVIDER_UNAVAILABLE','SMS delivery was not accepted by the provider.');
    }
}
