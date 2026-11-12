<?php
declare(strict_types=1);
require_once __DIR__.'/../app/Domain/Shipper.php';
require_once __DIR__.'/../app/Domain/Order.php';
require_once __DIR__.'/../app/Services/OrderLifecycleService.php';

use Somi\Domain\User;
use Somi\Domain\Shipper;
use Somi\Domain\Order;

$checks = 0;
function check(bool $ok, string $message): void {
    global $checks;
    if (!$ok) throw new RuntimeException($message);
    $checks++;
}
$row = ['user_id'=>'3','full_name'=>'Nguyễn Minh Anh','account_status'=>'ACTIVE',
    'vehicle_type'=>'MOTORBIKE','verification_status'=>'APPROVED','identity_review_status'=>'APPROVED',
    'reliability_score'=>'60.00','availability'=>'f'];
$shipper = Shipper::fromRow($row);
check($shipper instanceof User && $shipper->id() === 3, 'Inherited account identity');
check(!$shipper->isAvailable(), 'PostgreSQL false is not PHP truthy string');
check($shipper->workBlockers('60') === [], 'Threshold boundary remains eligible');
check($shipper->workBlockers('60.01') === ['SCORE_LOCKED'], 'Policy threshold blocks low score');
$blocked = Shipper::fromRow(array_replace($row, ['account_status'=>'SUSPENDED',
    'verification_status'=>'PENDING','identity_review_status'=>null]));
check($blocked->workBlockers(null) === ['ACCOUNT_NOT_ACTIVE','KYC_NOT_APPROVED','IDENTITY_NOT_APPROVED'], 'All account/KYC blockers preserved');
check(OrderLifecycleService::TRANSITIONS === Order::TRANSITIONS, 'Legacy service shares entity state rules');
foreach (Order::TRANSITIONS as $status => $targets) {
    $order = Order::fromRow(['order_id'=>'1','customer_id'=>'2','pickup_address'=>'A',
        'delivery_address'=>'B','total_charge_minor'=>'9007199254740993','currency_code'=>'VND','status'=>$status]);
    check($order->feeMinor() === '9007199254740993', 'Money stays exact');
    check(!$order->canBeCancelledByCustomer(99), 'Foreign customer cannot cancel');
    check($order->canBeCancelledByCustomer(2) === in_array($status, ['PENDING_PAYMENT','SEARCHING_SHIPPER','NO_SHIPPER_FOUND'], true), 'Customer cancellation contract');
    foreach (array_keys(Order::TRANSITIONS) as $target) {
        check($order->canTransitionTo($target) === in_array($target, $targets, true), "$status -> $target");
    }
}
echo "Domain entities: $checks checks passed.\n";
