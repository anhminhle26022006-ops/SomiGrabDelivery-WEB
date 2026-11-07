<?php
declare(strict_types=1);

/** Parameterized database access shared by the Person 1 services. */
final class Person1Repository
{
    public function __construct(public PDO $db) {}

    public function all(string $sql, array $params = []): array
    {
        $statement = $this->db->prepare($sql);
        $statement->execute($params);
        return $statement->fetchAll();
    }

    public function one(string $sql, array $params = []): ?array
    {
        return $this->all($sql, $params)[0] ?? null;
    }

    public function transaction(callable $operation): mixed
    {
        $this->db->beginTransaction();
        try {
            $value = $operation();
            $this->db->commit();
            return $value;
        } catch (Throwable $error) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $error;
        }
    }

    public function audit(int $actor, string $action, string $entity, int $id, array $old, array $new): void
    {
        $this->all("INSERT INTO audit_logs (actor_id, actor_kind, action, entity_type, entity_id, old_value, new_value)
            VALUES (:actor, 'ADMIN', :action, :entity, :id, CAST(:old AS jsonb), CAST(:new AS jsonb))",
            ['actor' => $actor, 'action' => $action, 'entity' => $entity, 'id' => $id,
             'old' => json_encode($old, JSON_THROW_ON_ERROR), 'new' => json_encode($new, JSON_THROW_ON_ERROR)]);
    }
}
