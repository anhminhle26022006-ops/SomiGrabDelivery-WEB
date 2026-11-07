<?php
declare(strict_types=1);
require_once __DIR__.'/PaymentProvider.php';

/** SePay Payment Gateway, distinct from SePay Bank API v2. */
final class SePayPaymentProvider implements PaymentProvider
{
    public function name(): string { return 'SEPAY'; }
    private function environment(): string { return getenv('SEPAY_ENVIRONMENT')?:'sandbox'; }
    /** Safe diagnostics: names and reasons only, never credential values. */
    public function configurationProblems(): array
    {
        $problems=[];$mode=$this->environment();
        if(!in_array($mode,['sandbox','production'],true))$problems['SEPAY_ENVIRONMENT']='Choose sandbox or production.';
        if(!preg_match('/^[A-Za-z0-9_-]+$/D',getenv('SEPAY_MERCHANT_ID')?:''))$problems['SEPAY_MERCHANT_ID']='Set the merchant ID for this environment.';
        $secret=getenv('SEPAY_SECRET_KEY')?:'';
        if($secret===''||trim($secret)!==$secret||preg_match('/[\x00-\x1F\x7F]/',$secret))$problems['SEPAY_SECRET_KEY']='Set a valid merchant signing secret.';
        elseif(($mode==='production'&&str_starts_with($secret,'spsk_test_'))||($mode==='sandbox'&&str_starts_with($secret,'spsk_live_')))$problems['SEPAY_SECRET_KEY']='The key belongs to the other environment.';
        $ipn=getenv('SEPAY_IPN_SECRET')?:'';
        if(strlen($ipn)<32||trim($ipn)!==$ipn||preg_match('/[\x00-\x1F\x7F]/',$ipn))$problems['SEPAY_IPN_SECRET']='Set an independent IPN secret of at least 32 characters.';
        elseif($secret!==''&&hash_equals($secret,$ipn))$problems['SEPAY_IPN_SECRET']='Use a different secret from the merchant signing key.';
        $url=getenv('SOMI_PUBLIC_URL')?:'';$parts=parse_url($url);
        if(!filter_var($url,FILTER_VALIDATE_URL)||!is_array($parts)||($parts['scheme']??null)!=='https'||empty($parts['host'])||isset($parts['user'])||isset($parts['pass'])||isset($parts['query'])||isset($parts['fragment']))$problems['SOMI_PUBLIC_URL']='Set the public HTTPS application base URL without credentials, query or fragment.';
        return $problems;
    }
    public function assertConfigured(): void
    {
        if($this->configurationProblems())throw new DeliveryApiError(503,'PAYMENT_PROVIDER_UNAVAILABLE','SePay is not configured.');
    }
    public function invoice(int $id): string { return 'SOMI-'.($this->environment()==='production'?'LIVE':'TEST').'-P'.$id; }
    public function checkout(array $payment,string $key): array
    {
        $this->assertConfigured();
        if($payment['currency_code']!=='VND')throw new DeliveryApiError(422,'PAYMENT_CURRENCY_UNSUPPORTED','SePay only supports VND.');
        return ['provider_payment_ref'=>$this->invoice((int)$payment['payment_id']),'checkout_url'=>rtrim(getenv('SOMI_PUBLIC_URL'),'/').'/public/api/customer/checkout.php?order_id='.$payment['order_id']];
    }
    public function form(array $p,string $language): array
    {
        $this->checkout($p,'');$return=rtrim(getenv('SOMI_PUBLIC_URL'),'/').'/?page=customer&view=payment&id='.$p['order_id'];
        $fields=['order_amount'=>(string)$p['amount_minor'],'merchant'=>getenv('SEPAY_MERCHANT_ID'),'currency'=>'VND','operation'=>'PURCHASE','order_description'=>'SOMI order '.$p['order_id'],'order_invoice_number'=>$this->invoice((int)$p['payment_id'])];
        if($p['method']!=='GATEWAY')$fields['payment_method']=$p['method'];
        $fields+=['success_url'=>$return,'error_url'=>$return,'cancel_url'=>$return];
        $signed=[];foreach($fields as $name=>$value)$signed[]=$name.'='.$value;
        $fields['lang']=$language==='vi'?'vi':'en';$fields['signature']=base64_encode(hash_hmac('sha256',implode(',',$signed),getenv('SEPAY_SECRET_KEY'),true));
        return ['action'=>$this->environment()==='production'?'https://pay.sepay.vn/v1/checkout/init':'https://pay-sandbox.sepay.vn/v1/checkout/init','fields'=>$fields];
    }
    private static function amount($v): string
    {
        if((!is_string($v)&&!is_int($v))||!preg_match('/^(0|[1-9][0-9]{0,18})(?:\.0{1,2})?$/D',(string)$v))throw new DeliveryApiError(409,'PAYMENT_MISMATCH','Invalid SePay amount.');
        $amount=explode('.',(string)$v)[0];
        if(bccomp($amount,'9223372036854775807')>0)throw new DeliveryApiError(409,'PAYMENT_MISMATCH','Invalid SePay amount.');
        return $amount;
    }
    private function paidEvent(array $order,array $transaction): array
    {
        $invoice=$order['order_invoice_number']??'';$prefix=preg_quote('SOMI-'.($this->environment()==='production'?'LIVE':'TEST').'-P','/');
        if(!is_string($invoice))throw new DeliveryApiError(409,'PAYMENT_MISMATCH','Invalid SePay invoice.');
        if(!preg_match('/^'.$prefix.'([1-9][0-9]*)$/D',$invoice,$m)||($order['order_status']??null)!=='CAPTURED'||($transaction['transaction_type']??null)!=='PAYMENT'||($transaction['transaction_status']??null)!=='APPROVED'||($order['order_currency']??null)!=='VND'||($transaction['transaction_currency']??null)!=='VND'||self::amount($order['order_amount']??null)!==self::amount($transaction['transaction_amount']??null))throw new DeliveryApiError(409,'PAYMENT_MISMATCH','SePay payment data mismatch.');
        return ['kind'=>'PAYMENT','payment_id'=>$m[1],'event_id'=>'payment:'.DeliveryInput::text($transaction['id']??null,'transaction.id',100),'provider_payment_ref'=>$invoice,'amount_minor'=>self::amount($order['order_amount']),'currency_code'=>'VND','status'=>'PAID'];
    }
    public function verifyCallback(string $raw,array $headers): array
    {
        $this->assertConfigured();$headers=array_change_key_case($headers,CASE_LOWER);
        if(!hash_equals(getenv('SEPAY_IPN_SECRET'),(string)($headers['x-secret-key']??'')))throw new DeliveryApiError(401,'INVALID_CALLBACK_SIGNATURE','IPN authentication failed.');
        try{$event=json_decode($raw,true,32,JSON_THROW_ON_ERROR);}catch(JsonException $e){throw new DeliveryApiError(400,'INVALID_JSON','Invalid IPN JSON.');}
        // VOID is a reversal, not a confirmed refund against an existing refund request.
        if(!is_array($event)||($event['notification_type']??null)!=='ORDER_PAID'||!is_array($event['order']??null)||!is_array($event['transaction']??null))throw new DeliveryApiError(422,'UNSUPPORTED_PAYMENT_EVENT','Unsupported SePay event; manual investigation is required.');
        return $this->paidEvent($event['order'],$event['transaction']);
    }
    public function reconcile(array $payment): array
    {
        $this->assertConfigured();$invoice=$this->invoice((int)$payment['payment_id']);$body='';
        $host=$this->environment()==='production'?'https://pgapi.sepay.vn':'https://pgapi-sandbox.sepay.vn';
        $curl=curl_init($host.'/v1/order/detail/'.rawurlencode($invoice));
        curl_setopt_array($curl,[CURLOPT_HTTPAUTH=>CURLAUTH_BASIC,CURLOPT_USERPWD=>getenv('SEPAY_MERCHANT_ID').':'.getenv('SEPAY_SECRET_KEY'),CURLOPT_HTTPHEADER=>['Accept: application/json'],CURLOPT_CONNECTTIMEOUT=>3,CURLOPT_TIMEOUT=>20,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_WRITEFUNCTION=>static function($c,string $chunk)use(&$body):int{if(strlen($body)+strlen($chunk)>65536)return 0;$body.=$chunk;return strlen($chunk);}]);
        $ok=curl_exec($curl);$status=(int)curl_getinfo($curl,CURLINFO_HTTP_CODE);curl_close($curl);
        if($ok===false||$status!==200)throw new DeliveryApiError(503,'PAYMENT_RECONCILIATION_PENDING','SePay reconciliation is unavailable or pending.');
        $data=json_decode($body,true);$order=$data['data']??[];
        if(($order['order_invoice_number']??null)!==$invoice)throw new DeliveryApiError(409,'PAYMENT_MISMATCH','SePay invoice mismatch.');
        foreach($order['transactions']??[] as $transaction)if(($transaction['transaction_type']??null)==='PAYMENT'&&($transaction['transaction_status']??null)==='APPROVED')return $this->paidEvent($order,$transaction);
        throw new DeliveryApiError(503,'PAYMENT_STILL_PENDING','No approved SePay payment was found.');
    }
    public function refund(array $refund,array $payment,string $key): array
    {
        // Bank API v2 refunds cannot be used with Payment Gateway transaction IDs.
        throw new DeliveryApiError(503,'SEPAY_REFUND_REVIEW_REQUIRED','Gateway refunds require provider-supported original-method processing and reconciliation.');
    }
}
