<?php
declare(strict_types=1);

final class WorkspaceProfileSchema
{
    public static function apply(PDO $db): void
    {
        if (!$db->inTransaction()) throw new LogicException('Migration requires a transaction.');
        $db->exec("CREATE TABLE IF NOT EXISTS account_avatars (
            user_id BIGINT PRIMARY KEY REFERENCES users(user_id) ON DELETE CASCADE,
            image_data BYTEA NOT NULL CHECK(octet_length(image_data) BETWEEN 1 AND 1048576),
            mime_type VARCHAR(30) NOT NULL CHECK(mime_type IN ('image/jpeg','image/png','image/webp')),
            version VARCHAR(24) NOT NULL,
            updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
        )");
        // Access runs through the authenticated PHP API, not the public Data API.
        $db->exec('ALTER TABLE account_avatars ENABLE ROW LEVEL SECURITY');
        $db->exec('REVOKE ALL ON account_avatars FROM PUBLIC');
    }
}
