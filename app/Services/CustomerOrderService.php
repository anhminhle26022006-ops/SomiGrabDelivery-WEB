<?php
declare(strict_types=1);
require_once __DIR__ . '/OrderQuoteService.php';
require_once __DIR__ . '/OrderLifecycleService.php';

final class CustomerOrderService
{
    private PDO $db;
    public function __construct(PDO $db) { $this->db = $db; }

    public function create(int $customerId, string $quoteId, string $requestKey): array
    {
        if (!$this->db->inTransaction()) throw new LogicException('Order creation requires a transaction.');
        if (!preg_match('/^[a-f0-9]{32}$/D', $quoteId)) DeliveryInput::invalid('quote_id');
        if (!preg_match('/^[A-Za-z0-9._:-]{8,100}$/D', $requestKey)) DeliveryInput::invalid('Idempotency-Key');
        // Serialize this customer's create requests, including different keys for
        // one quote. Database uniqueness also protects non-HTTP callers.
        $q = $this->db->prepare('SELECT user_id FROM customer_profiles WHERE user_id = :id FOR UPDATE');
        $q->execute(['id' => $customerId]);
        if (!$q->fetchColumn()) throw new DeliveryApiError(403, 'CUSTOMER_REQUIRED', 'Customer profile required.');
        $q = $this->db->prepare('SELECT order_id, quote_id FROM orders WHERE customer_id = :owner AND request_key = :key');
        $q->execute(['owner' => $customerId, 'key' => $requestKey]);
        $existing = $q->fetch();
        if ($existing) {
            if ($existing['quote_id'] !== $quoteId) throw new DeliveryApiError(409, 'IDEMPOTENCY_CONFLICT', 'Idempotency key was already used with another quote.');
            return ['created' => false, 'order' => $this->detail($customerId, (int) $existing['order_id'])];
        }
        $q = $this->db->prepare('SELECT *, expires_at > clock_timestamp() AS valid FROM order_quotes WHERE quote_id = :id AND customer_id = :owner FOR UPDATE');
        $q->execute(['id' => $quoteId, 'owner' => $customerId]);
        $quote = $q->fetch();
        if (!$quote) throw new DeliveryApiError(404, 'QUOTE_NOT_FOUND', 'Quote not found.');
        $q = $this->db->prepare('SELECT order_id FROM orders WHERE quote_id = :id');
        $q->execute(['id' => $quoteId]);
        if ($q->fetchColumn()) throw new DeliveryApiError(409, 'QUOTE_ALREADY_USED', 'Quote was already used. Retry with the original idempotency key.');
        if (!$quote['valid']) throw new DeliveryApiError(409, 'QUOTE_EXPIRED', 'Quote expired. Request a new quote.');
        $s = json_decode($quote['snapshot'], true, 512, JSON_THROW_ON_ERROR);
        (new OrderQuoteService($this->db, new HttpPricingContextProvider()))->requireCategory((int) $s['parcel']['category_id']);
        $p = $s['pickup']; $d = $s['delivery']; $parcel = $s['parcel']; $fees = $s['components']; $context = $s['context'];
        $data = [
            'order_code' => 'SOMI-' . strtoupper(bin2hex(random_bytes(10))), 'customer_id' => $customerId,
            'quote_id' => $quoteId, 'request_key' => $requestKey, 'currency_code' => $s['currency_code'], 'app_language_at_order' => $s['app_language'],
            'pricing_policy_id' => $s['pricing_policy_id'], 'pricing_policy_version' => $s['pricing_policy_version'],
            'pricing_snapshot' => json_encode($s, JSON_THROW_ON_ERROR),
            'pickup_saved_address_id' => $p['saved_address_id'], 'delivery_saved_address_id' => $d['saved_address_id'],
            'distance_km' => $context['distance_km'], 'estimated_duration_minutes' => $context['duration_minutes'],
            'pickup_label' => $p['label'], 'pickup_address' => $p['address'], 'pickup_latitude' => $p['latitude'], 'pickup_longitude' => $p['longitude'],
            'sender_name' => $p['contact_name'], 'sender_phone' => $p['contact_phone'],
            'delivery_label' => $d['label'], 'delivery_address' => $d['address'], 'delivery_latitude' => $d['latitude'], 'delivery_longitude' => $d['longitude'],
            'receiver_name' => $d['contact_name'], 'receiver_phone' => $d['contact_phone'],
            'category_id' => $parcel['category_id'], 'parcel_description' => $parcel['description'], 'estimated_weight_kg' => $parcel['weight_kg'],
            'length_cm' => $parcel['length_cm'], 'width_cm' => $parcel['width_cm'], 'height_cm' => $parcel['height_cm'], 'customer_note' => $s['customer_note'],
            'distance_fee_minor' => $fees['distance_fee_minor'], 'time_multiplier' => $fees['time_multiplier'], 'weather_multiplier' => $fees['weather_multiplier'],
            'total_charge_minor' => $fees['total_charge_minor'], 'return_fee_minor' => $fees['return_fee_minor'],
            'pricing_weather_provider' => $context['provider'], 'pricing_weather_condition' => $context['weather_condition'], 'pricing_weather_forecast_at' => $context['observed_at'],
        ];
        $keys = array_keys($data);
        $values = array_map(static fn($key) => $key === 'pricing_snapshot' ? 'CAST(:pricing_snapshot AS jsonb)' : ':' . $key, $keys);
        $q = $this->db->prepare('INSERT INTO orders (' . implode(', ', $keys) . ') VALUES (' . implode(', ', $values) . ') RETURNING *');
        $q->execute($data);
        $order = $q->fetch();
        OrderLifecycleService::history($this->db, (int) $order['order_id'], null, 'PENDING_PAYMENT', $customerId, 'CUSTOMER');
        OrderLifecycleService::event($this->db, $order, 'OrderCreated', 'created:' . $order['order_id']);
        return ['created' => true, 'order' => $this->detail($customerId, (int) $order['order_id'])];
    }

    public function list(int $customerId, ?int $before, int $limit, ?string $status): array
    {
        if ($limit < 1 || $limit > 100) DeliveryInput::invalid('limit');
        $params = ['owner' => $customerId];
        $where = 'customer_id = :owner';
        if ($before !== null) { $where .= ' AND order_id < :before'; $params['before'] = $before; }
        if ($status !== null) {
            if (!array_key_exists($status, OrderLifecycleService::TRANSITIONS)) DeliveryInput::invalid('status');
            $where .= ' AND status = :status'; $params['status'] = $status;
        }
        $q = $this->db->prepare('SELECT order_id, order_code, status, currency_code, total_charge_minor, pickup_address, delivery_address, created_at, paid_at, cancelled_at FROM orders WHERE ' . $where . ' ORDER BY order_id DESC LIMIT ' . ($limit + 1));
        $q->execute($params);
        $items = $q->fetchAll();
        $more = count($items) > $limit;
        if ($more) array_pop($items);
        return ['items' => $items, 'next_before_id' => $more ? (string) $items[count($items) - 1]['order_id'] : null];
    }

    public function detail(int $customerId, int $id): array
    {
        $q = $this->db->prepare('SELECT * FROM orders WHERE order_id = :id AND customer_id = :owner');
        $q->execute(['id' => $id, 'owner' => $customerId]);
        $order = $q->fetch();
        if (!$order) throw new DeliveryApiError(404, 'ORDER_NOT_FOUND', 'Order not found.');
        unset($order['request_key']);
        $order['pricing_snapshot'] = json_decode($order['pricing_snapshot'], true, 512, JSON_THROW_ON_ERROR);
        $q = $this->db->prepare('SELECT old_status, new_status, actor_kind, note, created_at FROM order_status_history WHERE order_id = :id ORDER BY status_history_id');
        $q->execute(['id' => $id]);
        $order['status_history'] = $q->fetchAll();
        return $order;
    }

    public function cancel(int $customerId, int $id, string $reason): array
    {
        $reason = DeliveryInput::text($reason, 'reason', 500);
        // Read ownership first; unknown and foreign IDs have the same 404 response.
        $this->detail($customerId, $id);
        $order = OrderLifecycleService::lock($this->db, $id);
        if ((int) $order['customer_id'] !== $customerId) throw new DeliveryApiError(404, 'ORDER_NOT_FOUND', 'Order not found.');
        if ($order['status'] === 'CANCELLED') return $this->detail($customerId, $id);
        if (!in_array($order['status'], ['PENDING_PAYMENT', 'SEARCHING_SHIPPER', 'NO_SHIPPER_FOUND'], true)) {
            throw new DeliveryApiError(409, 'ORDER_NOT_CANCELLABLE', 'Customer cancellation is only allowed before Shipper assignment.');
        }
        $q = $this->db->prepare("SELECT 1 FROM order_assignments WHERE order_id = :id AND assignment_status = 'ACTIVE'");
        $q->execute(['id' => $id]);
        if ($q->fetchColumn()) throw new DeliveryApiError(409, 'ORDER_NOT_CANCELLABLE', 'Order already has an active assignment.');
        $q = $this->db->prepare("UPDATE orders SET status = 'CANCELLED', cancellation_fee_minor = 0, cancelled_at = now(), closed_at = now(), updated_at = now() WHERE order_id = :id");
        $q->execute(['id' => $id]);
        $q = $this->db->prepare("UPDATE order_match_offers SET status = 'CANCELLED', responded_at = now() WHERE order_id = :id AND status = 'PENDING'");
        $q->execute(['id' => $id]);
        $q = $this->db->prepare('SELECT status FROM payments WHERE order_id = :id FOR UPDATE');
        $q->execute(['id' => $id]);
        $paymentStatus = $q->fetchColumn();
        $paid = $order['paid_at'] !== null || $paymentStatus === 'PAID';
        OrderLifecycleService::history($this->db, $id, $order['status'], 'CANCELLED', $customerId, 'CUSTOMER', $reason);
        OrderLifecycleService::event($this->db, $order, 'OrderCancelled', 'cancelled:' . $id, ['reason' => 'CUSTOMER_CANCELLATION', 'refund_required_hint' => $paid]);
        if ($paid) OrderLifecycleService::event($this->db, $order, 'RefundRequested', 'refund:cancel:' . $id, ['reason' => 'CUSTOMER_CANCELLATION', 'deducted_fee_minor' => '0']);
        return $this->detail($customerId, $id);
    }
}
