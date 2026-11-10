SET LOCAL search_path TO somi, public;
CREATE TABLE IF NOT EXISTS private_files (
 file_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
 owner_id BIGINT NOT NULL REFERENCES users(user_id),
 order_id BIGINT REFERENCES orders(order_id),
 ticket_id BIGINT REFERENCES support_tickets(ticket_id),
 purpose VARCHAR(20) NOT NULL CHECK(purpose IN ('CHAT','TICKET','KYC','EVIDENCE')),
 object_key VARCHAR(64) NOT NULL UNIQUE,
 original_name VARCHAR(255) NOT NULL,
 mime_type VARCHAR(120) NOT NULL,
 byte_size BIGINT NOT NULL CHECK(byte_size>0),
 checksum_sha256 CHAR(64) NOT NULL,
 created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
 delete_after TIMESTAMPTZ NOT NULL DEFAULT 'infinity',
 deleted_at TIMESTAMPTZ,
 CHECK((purpose IN ('CHAT','EVIDENCE') AND order_id IS NOT NULL AND ticket_id IS NULL)
 OR (purpose='TICKET' AND ticket_id IS NOT NULL AND order_id IS NULL)
 OR (purpose='KYC' AND order_id IS NULL AND ticket_id IS NULL))
);
CREATE INDEX IF NOT EXISTS ix_private_files_retention ON private_files(delete_after) WHERE deleted_at IS NULL;
ALTER TABLE chat_messages ADD COLUMN IF NOT EXISTS client_key VARCHAR(100);
CREATE UNIQUE INDEX IF NOT EXISTS ux_chat_client_key ON chat_messages(sender_id,client_key);
ALTER TABLE support_tickets ADD COLUMN IF NOT EXISTS client_key VARCHAR(100);
CREATE UNIQUE INDEX IF NOT EXISTS ux_ticket_client_key ON support_tickets(opened_by,client_key);
ALTER TABLE support_tickets ALTER COLUMN order_id DROP NOT NULL;
ALTER TABLE notifications ADD COLUMN IF NOT EXISTS dedup_key VARCHAR(180);
CREATE UNIQUE INDEX IF NOT EXISTS ux_notification_dedup ON notifications(user_id,dedup_key);
ALTER TABLE shipper_payouts ADD COLUMN IF NOT EXISTS evidence_reference TEXT;
CREATE UNIQUE INDEX IF NOT EXISTS ux_payout_provider_ref ON shipper_payouts(provider_payout_ref);
CREATE TABLE IF NOT EXISTS shipper_finance_reviews (
 review_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
 event_id BIGINT NOT NULL UNIQUE REFERENCES order_events(event_id),
 order_id BIGINT NOT NULL REFERENCES orders(order_id),
 reason VARCHAR(60) NOT NULL,
 created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE TABLE IF NOT EXISTS person3_worker_cursors (
 name VARCHAR(30) PRIMARY KEY,
 last_id BIGINT NOT NULL DEFAULT 0
);
INSERT INTO person3_worker_cursors(name) VALUES('earnings'),('payouts') ON CONFLICT DO NOTHING;
INSERT INTO permissions(permission_code,description) VALUES
('person3.use','Chat, ticket and in-app notifications'),('payout.manage','Reconcile shipper payouts'),('storage.read_support','Audited private file reads for support and KYC') ON CONFLICT DO NOTHING;
INSERT INTO role_permissions(role_id,permission_id)
SELECT r.role_id,p.permission_id FROM roles r CROSS JOIN permissions p
WHERE (r.role_code IN ('CUSTOMER','SHIPPER') AND p.permission_code='person3.use')
OR (r.role_code='ADMIN' AND p.permission_code IN ('payout.manage','storage.read_support')) ON CONFLICT DO NOTHING;
