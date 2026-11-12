<?php
declare(strict_types=1);
require_once __DIR__.'/DeliveryInput.php';

interface PaymentProvider
{
    public function name(): string;
    public function assertConfigured(): void;
    public function checkout(array $payment,string $key): array;
    public function reconcile(array $payment): array;
    public function refund(array $refund,array $payment,string $key): array;
    /** Must authenticate raw bytes before returning a provider event. */
    public function verifyCallback(string $raw,array $headers): array;
}

/** Integration contract for a trusted adapter; not an invented live payment gateway. */
final class BridgePaymentProvider implements PaymentProvider
{
    public function name(): string { return 'PAYMENT_BRIDGE'; }
    public function assertConfigured(): void
    {
        if(parse_url(getenv('SOMI_PAYMENT_BRIDGE_URL')?:'',PHP_URL_SCHEME)!=='https'||!getenv('SOMI_PAYMENT_BRIDGE_TOKEN')||strlen(getenv('SOMI_PAYMENT_WEBHOOK_SECRET')?:'')<32)throw new DeliveryApiError(503,'PAYMENT_PROVIDER_UNAVAILABLE','Payment provider is not configured.');
    }
    private function request(string $path,array $data,string $key): array
    {
        $this->assertConfigured();$body='';$token=getenv('SOMI_PAYMENT_BRIDGE_TOKEN');
        if(preg_match('/[\r\n]/',$token))throw new DeliveryApiError(503,'PAYMENT_PROVIDER_UNAVAILABLE','Payment provider configuration is invalid.');
        $curl=curl_init(rtrim(getenv('SOMI_PAYMENT_BRIDGE_URL'),'/').$path);
        curl_setopt_array($curl,[CURLOPT_POST=>true,CURLOPT_HTTPHEADER=>['Content-Type: application/json','Authorization: Bearer '.$token,'Idempotency-Key: '.$key],CURLOPT_POSTFIELDS=>json_encode($data,JSON_THROW_ON_ERROR),CURLOPT_CONNECTTIMEOUT=>3,CURLOPT_TIMEOUT=>20,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_WRITEFUNCTION=>static function($handle,string $chunk)use(&$body):int{if(strlen($body)+strlen($chunk)>65536)return 0;$body.=$chunk;return strlen($chunk);}]);
        $ok=curl_exec($curl);$status=(int)curl_getinfo($curl,CURLINFO_HTTP_CODE);curl_close($curl);$result=json_decode($body,true);
        if($ok===false||$status<200||$status>=300||!is_array($result))throw new DeliveryApiError(503,'PAYMENT_PROVIDER_UNAVAILABLE','Payment provider request failed.');
        return $result;
    }
    public function checkout(array $payment,string $key): array { return $this->request('/checkout',['payment_id'=>(string)$payment['payment_id'],'order_id'=>(string)$payment['order_id'],'amount_minor'=>(string)$payment['amount_minor'],'currency_code'=>$payment['currency_code'],'method'=>$payment['method']],$key); }
    public function reconcile(array $payment): array { return $this->request('/reconcile',['payment_id'=>(string)$payment['payment_id'],'provider_payment_ref'=>$payment['provider_payment_ref']],'reconcile:'.$payment['payment_id']); }
    public function refund(array $refund,array $payment,string $key): array { return $this->request('/refund',['refund_id'=>(string)$refund['refund_id'],'payment_id'=>(string)$payment['payment_id'],'provider_payment_ref'=>$payment['provider_payment_ref'],'amount_minor'=>(string)$refund['refund_amount_minor'],'currency_code'=>$refund['currency_code'],'destination'=>'ORIGINAL_METHOD'],$key); }
    public function verifyCallback(string $raw,array $headers): array
    {
        $this->assertConfigured();$headers=array_change_key_case($headers,CASE_LOWER);$at=(string)($headers['x-somi-timestamp']??'');$signature=(string)($headers['x-somi-signature']??'');
        if(!preg_match('/^[0-9]{10}$/D',$at)||abs(time()-(int)$at)>300||!preg_match('/^[a-f0-9]{64}$/D',$signature)||!hash_equals(hash_hmac('sha256',$at.'.'.$raw,getenv('SOMI_PAYMENT_WEBHOOK_SECRET')),$signature))throw new DeliveryApiError(401,'INVALID_CALLBACK_SIGNATURE','Callback authentication failed.');
        try{$data=json_decode($raw,true,32,JSON_THROW_ON_ERROR);}catch(JsonException $e){throw new DeliveryApiError(400,'INVALID_JSON','Invalid callback JSON.');}
        if(!is_array($data)||!str_starts_with(ltrim($raw),'{'))throw new DeliveryApiError(400,'INVALID_JSON','Invalid callback object.');
        return $data;
    }
}
