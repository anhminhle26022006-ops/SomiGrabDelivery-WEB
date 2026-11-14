<?php
declare(strict_types=1);
require_once __DIR__.'/JobQueue.php';
require_once __DIR__.'/DemoMode.php';
require_once __DIR__.'/EncryptedPayload.php';
require_once __DIR__.'/DeliveryInput.php';

final class EmailService
{
    public static function language(array $user): string { return ($user['app_language']??null)==='vi'?'vi':'en'; }

    /** Shared internal entry point. It cannot send arbitrary email to arbitrary recipients. */
    public static function enqueue(PDO $db,int $userId,string $template,string $key,array $variables=[],?int $orderId=null,int $ttlSeconds=82800): void
    {
        if(!$db->inTransaction())throw new LogicException('Email must be enqueued in the business transaction.');
        $q=$db->prepare('SELECT email,app_language FROM users WHERE user_id=?');$q->execute([$userId]);$user=$q->fetch();
        if(!$user||!filter_var($user['email'],FILTER_VALIDATE_EMAIL))throw new DeliveryApiError(422,'INVALID_EMAIL','Recipient email is invalid.');
        if(DemoMode::email($user['email']))return; // No verification, OTP or transactional provider calls for demonstration identities.
        $language=self::language($user);
        [$subject,$text]=self::render($template,$language,$variables);
        $payload=EncryptedPayload::seal(['to'=>$user['email'],'subject'=>$subject,'text'=>$text,'html'=>'<p>'.nl2br(htmlspecialchars($text,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8')).'</p>']);
        $q=$db->prepare('INSERT INTO email_messages(user_id,order_id,template_code,language,deduplication_key,encrypted_payload,expires_at) VALUES (?,?,?,?,?,?,clock_timestamp()+make_interval(secs=>?)) ON CONFLICT(deduplication_key) DO NOTHING RETURNING email_id');
        $q->execute([$userId,$orderId,$template,$language,$key,$payload,min(82800,$ttlSeconds)]);$id=$q->fetchColumn();
        if($id)JobQueue::enqueue($db,'EMAIL','email:'.$id,['email_id'=>(string)$id]);
    }

    public static function render(string $template,string $language,array $v): array
    {
        $vi=$language==='vi';$code=(string)($v['order_code']??'');
        $titles=[
            'VERIFY_EMAIL'=>['Mã xác minh email SOMI','Your SOMI email verification code'],
            'RESET_PASSWORD'=>['Đặt lại mật khẩu SOMI','Reset your SOMI password'],
            'BANK_ACCOUNT_CHANGE'=>['Xác nhận tài khoản nhận tiền SOMI','Confirm your SOMI payout account'],
            'OrderCreated'=>['Đã tạo đơn SOMI','SOMI order created'],
            'PaymentConfirmed'=>['Thanh toán đã được xác nhận','Payment confirmed'],
            'OrderCancelled'=>['Đơn hàng đã hủy','Order cancelled'],
            'OrderDelivered'=>['Đơn hàng đã giao','Order delivered'],
            'OrderReturning'=>['Đơn hàng đang hoàn','Order is returning'],
            'OrderReturned'=>['Đã hoàn hàng','Order returned'],
            'RefundSucceeded'=>['Đã hoàn tiền','Refund completed'],
        ];
        if(!isset($titles[$template]))throw new DeliveryApiError(422,'EMAIL_TEMPLATE_UNKNOWN','Unknown email template.');
        $subject=$titles[$template][$vi?0:1];
        if($template==='VERIFY_EMAIL')$text=$vi?'Mã xác minh SOMI: '.($v['code']??'').'. Có hiệu lực trong 5 phút.':'Your SOMI verification code: '.($v['code']??'').'. Expires in 5 minutes.';
        elseif($template==='RESET_PASSWORD')$text=$vi?'Đặt lại mật khẩu SOMI: '.($v['url']??'')."\nLiên kết có hiệu lực trong 30 phút.":'Reset your SOMI password: '.($v['url']??'')."\nThis link expires in 30 minutes.";
        elseif($template==='BANK_ACCOUNT_CHANGE')$text=($vi?'Mã xác nhận thay tài khoản nhận tiền kết thúc bằng ':'Confirmation code for payout account ending ').($v['last_four']??'').': '.($v['code']??'').($vi?'. Hết hạn trong 5 phút. Không chia sẻ mã này.':'. Expires in 5 minutes. Do not share this code.');
        else $text=$subject.($code!==''?' — '.$code:'')."\n".($vi?'Xem chi tiết trong tài khoản SOMI của bạn.':'View the details in your SOMI account.');
        if($template==='OrderCancelled' && ($v['reason']??null)==='NO_SHIPPER_FOUND') {
            $text.="\n".($vi?'Không tìm được shipper phù hợp trong 5 phút tìm kiếm nên hệ thống tự hủy đơn. Yêu cầu hoàn toàn bộ tiền đã được tạo; đây chưa phải xác nhận đã chuyển tiền hoàn. Theo dõi trạng thái hoàn tiền và kiểm tra tài khoản ngân hàng trong tài khoản SOMI.':'The system cancelled this order because no eligible shipper was found within the five-minute matching window. A full refund has been requested; this does not confirm that the refund has been transferred. Track the refund status and check your bank details in your SOMI account.');
        }
        return [$subject,$text];
    }
}
