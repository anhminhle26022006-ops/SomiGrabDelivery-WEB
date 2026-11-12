<?php
declare(strict_types=1);
require_once __DIR__.'/DeliveryApiError.php';
/** Reserved, non-deliverable identities used only by the hosted demonstration dataset. */
final class DemoMode
{
    public const EMAIL_PATTERN='%@demo.somi.invalid';
    public static function email(string $email):bool{return str_ends_with(strtolower($email),'@demo.somi.invalid');}
    public static function user(PDO $db,int $id):bool{$q=$db->prepare('SELECT email FROM users WHERE user_id=?');$q->execute([$id]);return self::email((string)$q->fetchColumn());}
    public static function denyTransfer():never{throw new DeliveryApiError(409,'DEMO_TRANSFER_DISABLED','Demo data cannot initiate or reconcile a real money transfer.');}
    public static function payout(PDO $db,int $payout):bool{$q=$db->prepare('SELECT u.email FROM shipper_payouts p JOIN users u ON u.user_id=p.shipper_id WHERE p.payout_id=?');$q->execute([$payout]);return self::email((string)$q->fetchColumn());}
}
