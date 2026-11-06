<?php
declare(strict_types=1);
require_once __DIR__ . '/DeliveryInput.php';

final class CustomerAddressService
{
    private PDO $db;
    public function __construct(PDO $db) { $this->db = $db; }

    public function all(int $customerId): array
    {
        $q = $this->db->prepare('SELECT * FROM customer_addresses WHERE customer_id = :owner AND archived_at IS NULL ORDER BY is_default DESC, address_id DESC');
        $q->execute(['owner' => $customerId]);
        return $q->fetchAll();
    }

    public function get(int $customerId, int $id): array
    {
        $q = $this->db->prepare('SELECT * FROM customer_addresses WHERE address_id = :id AND customer_id = :owner AND archived_at IS NULL');
        $q->execute(['id' => $id, 'owner' => $customerId]);
        $row = $q->fetch();
        if (!$row) throw new DeliveryApiError(404, 'ADDRESS_NOT_FOUND', 'Address not found.');
        return $row;
    }

    // Caller owns transaction. Lock the profile before all address writes so
    // concurrent changes of the default address cannot violate the unique index.
    public function save(int $customerId, array $data, ?int $id = null): array
    {
        $data = DeliveryInput::address($data);
        $this->lockOwner($customerId);
        if ($id !== null) $this->get($customerId, $id);
        if ($data['is_default'] === 'true') {
            $q = $this->db->prepare('UPDATE customer_addresses SET is_default = FALSE, updated_at = now() WHERE customer_id = :owner AND is_default AND archived_at IS NULL');
            $q->execute(['owner' => $customerId]);
        }
        if ($id === null) {
            $sql = 'INSERT INTO customer_addresses (customer_id, ' . implode(', ', array_keys($data)) . ') VALUES (:owner, :' . implode(', :', array_keys($data)) . ') RETURNING *';
        } else {
            $sets = array_map(static fn($key) => $key . ' = :' . $key, array_keys($data));
            $sql = 'UPDATE customer_addresses SET ' . implode(', ', $sets) . ', updated_at = now() WHERE address_id = :id AND customer_id = :owner RETURNING *';
            $data['id'] = $id;
        }
        $q = $this->db->prepare($sql);
        $q->execute($data + ['owner' => $customerId]);
        return $q->fetch();
    }

    public function archive(int $customerId, int $id): void
    {
        $this->lockOwner($customerId);
        $this->get($customerId, $id);
        $q = $this->db->prepare('UPDATE customer_addresses SET archived_at = now(), updated_at = now(), is_default = FALSE WHERE address_id = :id AND customer_id = :owner');
        $q->execute(['id' => $id, 'owner' => $customerId]);
    }

    private function lockOwner(int $id): void
    {
        if (!$this->db->inTransaction()) throw new LogicException('Address writes require a transaction.');
        $q = $this->db->prepare('SELECT user_id FROM customer_profiles WHERE user_id = :id FOR UPDATE');
        $q->execute(['id' => $id]);
        if (!$q->fetchColumn()) throw new DeliveryApiError(403, 'CUSTOMER_REQUIRED', 'Customer profile required.');
    }
}
