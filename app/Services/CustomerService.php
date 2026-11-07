<?php
declare(strict_types=1);
require_once __DIR__ . '/Input.php';
require_once __DIR__ . '/OrderContract.php';
require_once __DIR__ . '/../Repositories/Person1Repository.php';

final class CustomerService
{
    public function __construct(private Person1Repository $repo) {}

    public function addresses(int $owner): array
    {
        return $this->repo->all('SELECT * FROM customer_addresses WHERE customer_id = :owner
            AND archived_at IS NULL ORDER BY is_default DESC, address_id DESC', ['owner' => $owner]);
    }

    public function saveAddress(int $owner, array $input): array
    {
        $id = isset($input['address_id']) ? Input::id($input['address_id']) : null;
        $values = ['label' => Input::text($input, 'label', 60, false),
            'address_detail' => Input::text($input, 'address_detail', 255),
            'province' => Input::text($input, 'province', 120, false),
            'district' => Input::text($input, 'district', 120, false),
            'ward' => Input::text($input, 'ward', 120, false),
            'latitude' => Input::number($input, 'latitude', -90, 90),
            'longitude' => Input::number($input, 'longitude', -180, 180),
            'is_default' => Input::boolean($input, 'is_default') ? 'true' : 'false', 'owner' => $owner];
        return $this->repo->transaction(function () use ($owner, $id, $values) {
            // Serialize the default-address invariant even when the address book is empty.
            if (!$this->repo->one('SELECT user_id FROM customer_profiles WHERE user_id = :id FOR UPDATE', ['id' => $owner])) {
                throw new ApiProblem(403, 'Customer profile required.', 'FORBIDDEN');
            }
            if ($id !== null && !$this->repo->one('SELECT address_id FROM customer_addresses
                WHERE address_id = :id AND customer_id = :owner AND archived_at IS NULL FOR UPDATE', ['id' => $id, 'owner' => $owner])) {
                throw new ApiProblem(404, 'Address not found.', 'NOT_FOUND');
            }
            if ($values['is_default'] === 'true') {
                $this->repo->all('UPDATE customer_addresses SET is_default = FALSE, updated_at = now()
                    WHERE customer_id = :owner AND is_default = TRUE AND archived_at IS NULL', ['owner' => $owner]);
            }
            if ($id === null) return $this->repo->one('INSERT INTO customer_addresses
                (customer_id, label, address_detail, province, district, ward, latitude, longitude, is_default)
                VALUES (:owner, :label, :address_detail, :province, :district, :ward, :latitude, :longitude, CAST(:is_default AS boolean)) RETURNING *', $values);
            $values['id'] = $id;
            return $this->repo->one('UPDATE customer_addresses SET label = :label, address_detail = :address_detail,
                province = :province, district = :district, ward = :ward, latitude = :latitude, longitude = :longitude,
                is_default = CAST(:is_default AS boolean), updated_at = now()
                WHERE address_id = :id AND customer_id = :owner RETURNING *', $values);
        });
    }

    public function archiveAddress(int $owner, int $id): void
    {
        $this->repo->transaction(function () use ($owner, $id) {
            $this->repo->one('SELECT user_id FROM customer_profiles WHERE user_id = :id FOR UPDATE', ['id' => $owner]);
            $row = $this->repo->one('SELECT address_id FROM customer_addresses
                WHERE address_id = :id AND customer_id = :owner AND archived_at IS NULL FOR UPDATE', ['id' => $id, 'owner' => $owner]);
            if (!$row) throw new ApiProblem(404, 'Address not found.', 'NOT_FOUND');
            $used = $this->repo->one('SELECT order_id FROM orders WHERE pickup_saved_address_id = :pickup OR delivery_saved_address_id = :delivery LIMIT 1', ['pickup' => $id, 'delivery' => $id]);
            if ($used) throw new ApiProblem(409, 'An address used in an order cannot be deleted.', 'ADDRESS_IN_USE');
            $this->repo->all('UPDATE customer_addresses SET archived_at = now(), is_default = FALSE, updated_at = now()
                WHERE address_id = :id AND customer_id = :owner', ['id' => $id, 'owner' => $owner]);
        });
    }

    public function orders(int $owner, int $before = PHP_INT_MAX): array
    {
        return $this->repo->all('SELECT order_id, order_code, status, currency_code, total_charge_minor,
            pickup_address, delivery_address, created_at, updated_at FROM orders
            WHERE customer_id = :owner AND order_id < :before ORDER BY order_id DESC LIMIT 100', ['owner' => $owner, 'before' => $before]);
    }

    public function order(int $owner, int $id): array
    {
        $row = $this->repo->one('SELECT * FROM orders WHERE customer_id = :owner AND order_id = :id', ['owner' => $owner, 'id' => $id]);
        if (!$row) throw new ApiProblem(404, 'Order not found.', 'NOT_FOUND');
        $row['history'] = $this->repo->all('SELECT old_status, new_status, actor_kind, note, created_at
            FROM order_status_history WHERE order_id = :id ORDER BY status_history_id', ['id' => $id]);
        return $row;
    }

    public function cancel(int $owner, int $id, string $reason): array
    {
        return $this->repo->transaction(function () use ($owner, $id, $reason) {
            // Matching must take this same order lock BEFORE offers/assignments/payment locks.
            $order = $this->repo->one('SELECT * FROM orders WHERE order_id = :id AND customer_id = :owner FOR UPDATE', ['id' => $id, 'owner' => $owner]);
            if (!$order) throw new ApiProblem(404, 'Order not found.', 'NOT_FOUND');
            if ($order['status'] === 'CANCELLED') return $order;
            if (!OrderContract::canCancelBeforeAssignment($order['status'])) {
                throw new ApiProblem(409, 'The order can no longer be cancelled before assignment.', 'ORDER_STATE_CONFLICT');
            }
            $assigned = $this->repo->one("SELECT 1 FROM order_assignments WHERE order_id = :id AND assignment_status = 'ACTIVE'", ['id' => $id]);
            if ($assigned) throw new ApiProblem(409, 'A shipper has already been assigned.', 'ORDER_STATE_CONFLICT');
            // Refund handling belongs to Person 3; never silently cancel paid/in-flight money.
            if ($order['paid_at'] !== null || $this->repo->one('SELECT 1 FROM payments WHERE order_id = :id LIMIT 1', ['id' => $id])) {
                throw new ApiProblem(409, 'Cancellation requires the payment/refund contract.', 'PAYMENT_INTEGRATION_REQUIRED');
            }
            $row = $this->repo->one("UPDATE orders SET status = 'CANCELLED', cancelled_at = now(), closed_at = now(), updated_at = now()
                WHERE order_id = :id RETURNING *", ['id' => $id]);
            $this->repo->all("UPDATE order_match_offers SET status = 'CANCELLED', responded_at = now()
                WHERE order_id = :id AND status = 'PENDING'", ['id' => $id]);
            $this->repo->all("INSERT INTO order_status_history (order_id, old_status, new_status, actor_id, actor_kind, note)
                VALUES (:id, :old, 'CANCELLED', :actor, 'CUSTOMER', :note)", ['id' => $id, 'old' => $order['status'], 'actor' => $owner, 'note' => $reason]);
            return $row;
        });
    }
}
