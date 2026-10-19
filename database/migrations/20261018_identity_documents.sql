-- Store identity numbers encrypted by PHP; Shipper review only.
-- Customer records use NOT_REQUIRED; Shipper records should be created as PENDING.
CREATE TABLE IF NOT EXISTS somi.user_identity_documents (
    identity_document_id       BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    user_id                    BIGINT NOT NULL UNIQUE REFERENCES somi.users(user_id) ON DELETE CASCADE,
    document_type              VARCHAR(30) NOT NULL
                               CHECK (document_type IN ('NATIONAL_ID', 'PASSPORT', 'FOREIGN_ID')),
    document_country_code      CHAR(2) NOT NULL,
    identity_number_ciphertext TEXT NOT NULL,
    encryption_key_version     SMALLINT NOT NULL DEFAULT 1 CHECK (encryption_key_version > 0),
    review_status              VARCHAR(20) NOT NULL DEFAULT 'NOT_REQUIRED'
                               CHECK (review_status IN ('NOT_REQUIRED', 'PENDING', 'APPROVED', 'REJECTED', 'NEEDS_INFO')),
    rejection_reason           TEXT,
    reviewed_by                BIGINT REFERENCES somi.users(user_id) ON DELETE SET NULL,
    reviewed_at                TIMESTAMPTZ,
    created_at                 TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at                 TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS ix_user_identity_documents_review
    ON somi.user_identity_documents(review_status, created_at DESC);
ALTER TABLE somi.user_identity_documents ENABLE ROW LEVEL SECURITY;

CREATE TABLE IF NOT EXISTS somi.identity_document_access_logs (
    access_log_id              BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    identity_document_id       BIGINT REFERENCES somi.user_identity_documents(identity_document_id) ON DELETE SET NULL,
    admin_user_id              BIGINT REFERENCES somi.users(user_id) ON DELETE SET NULL,
    target_user_id             BIGINT REFERENCES somi.users(user_id) ON DELETE SET NULL,
    action                     VARCHAR(30) NOT NULL CHECK (action IN ('VIEW_FULL')),
    accessed_at                TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS ix_identity_document_access_target
    ON somi.identity_document_access_logs(target_user_id, accessed_at DESC);
ALTER TABLE somi.identity_document_access_logs ENABLE ROW LEVEL SECURITY;

CREATE OR REPLACE FUNCTION somi.erase_identity_document_on_account_close()
RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
    IF NEW.account_status = 'CLOSED' AND OLD.account_status IS DISTINCT FROM 'CLOSED' THEN
        DELETE FROM somi.user_identity_documents WHERE user_id = NEW.user_id;
    END IF;
    RETURN NEW;
END;
$$;

DROP TRIGGER IF EXISTS trg_erase_identity_document_on_account_close ON somi.users;
CREATE TRIGGER trg_erase_identity_document_on_account_close
AFTER UPDATE OF account_status ON somi.users
FOR EACH ROW EXECUTE FUNCTION somi.erase_identity_document_on_account_close();

INSERT INTO somi.permissions (permission_code, description)
VALUES ('identity.read_sensitive', 'Xem số giấy tờ định danh đầy đủ đã giải mã; mọi lần xem phải được audit.')
ON CONFLICT (permission_code) DO UPDATE
SET description = EXCLUDED.description;

INSERT INTO somi.role_permissions (role_id, permission_id)
SELECT r.role_id, p.permission_id
FROM somi.roles r
CROSS JOIN somi.permissions p
WHERE r.role_code = 'ADMIN' AND p.permission_code = 'identity.read_sensitive'
ON CONFLICT DO NOTHING;
