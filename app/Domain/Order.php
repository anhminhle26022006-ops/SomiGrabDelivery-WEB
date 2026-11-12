<?php
declare(strict_types=1);
namespace Somi\Domain;

/** Persisted order state. Services own transactions, authorization and side effects. */
final class Order
{
    public const TRANSITIONS = [
        'PENDING_PAYMENT' => ['SEARCHING_SHIPPER', 'CANCELLED'],
        'SEARCHING_SHIPPER' => ['SHIPPER_ASSIGNED', 'NO_SHIPPER_FOUND', 'CANCELLED'],
        'NO_SHIPPER_FOUND' => ['SEARCHING_SHIPPER', 'CANCELLED'],
        'SHIPPER_ASSIGNED' => ['PICKED_UP', 'SEARCHING_SHIPPER'],
        'PICKED_UP' => ['IN_DELIVERY', 'RETURNING'],
        'IN_DELIVERY' => ['DELIVERED', 'RETURNING'],
        'RETURNING' => ['RETURNED'],
        'DELIVERED' => [], 'RETURNED' => [], 'CANCELLED' => [],
    ];

    public function __construct(private int $orderId, private int $customerId,
        private string $pickupAddress, private string $deliveryAddress,
        private string $feeMinor, private string $currencyCode, private string $status)
    {
        if ($orderId < 1 || $customerId < 1) throw new \InvalidArgumentException('Order identities must be positive.');
        if (!array_key_exists($status, self::TRANSITIONS)) throw new \InvalidArgumentException('Unknown order status.');
    }

    public static function fromRow(array $row): self
    {
        return new self((int)$row['order_id'], (int)$row['customer_id'], (string)$row['pickup_address'],
            (string)$row['delivery_address'], (string)$row['total_charge_minor'],
            (string)$row['currency_code'], (string)$row['status']);
    }

    public function id(): int { return $this->orderId; }
    public function customerId(): int { return $this->customerId; }
    public function pickupAddress(): string { return $this->pickupAddress; }
    public function deliveryAddress(): string { return $this->deliveryAddress; }
    public function feeMinor(): string { return $this->feeMinor; }
    public function currencyCode(): string { return $this->currencyCode; }
    public function status(): string { return $this->status; }
    public function canTransitionTo(string $to): bool { return in_array($to, self::TRANSITIONS[$this->status], true); }
    public function canBeCancelledByCustomer(int $customerId): bool
    {
        return $this->customerId === $customerId && $this->canTransitionTo('CANCELLED');
    }
}
