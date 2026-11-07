<?php
declare(strict_types=1);
require_once __DIR__ . '/DeliveryInput.php';

final class AuditService
{
    public static function record(PDO $db, ?int $actorId, string $kind, string $action, string $entity, ?int $id, array $old = [], array $new = []): void
    {
        if (!$db->inTransaction()) throw new LogicException('Audit and business changes must share a transaction.');
        $redact = static function (array $data) use (&$redact): array {
            foreach ($data as $key=>&$value) {
                if (preg_match('/password|token|secret|cipher|identity_number|document_number|api_key/i',(string)$key)) $value='[REDACTED]';
                elseif (is_array($value)) $value=$redact($value);
            }
            return $data;
        };
        $q=$db->prepare('INSERT INTO audit_logs(actor_id,actor_kind,action,entity_type,entity_id,old_value,new_value) VALUES (?,?,?,?,?,CAST(? AS jsonb),CAST(? AS jsonb))');
        $q->execute([$actorId,$kind,$action,$entity,$id,json_encode($redact($old),JSON_THROW_ON_ERROR),json_encode($redact($new),JSON_THROW_ON_ERROR)]);
    }
}
