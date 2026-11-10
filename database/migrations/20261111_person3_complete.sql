SET LOCAL search_path TO somi, public;
CREATE TABLE bank_account_changes (
 request_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
 shipper_id BIGINT NOT NULL REFERENCES shipper_profiles(user_id),
 client_key VARCHAR(100) NOT NULL, request_hash CHAR(64) NOT NULL,
 encrypted_payload TEXT, token_hash TEXT NOT NULL,
 attempts SMALLINT NOT NULL DEFAULT 0 CHECK(attempts BETWEEN 0 AND 5),
 expires_at TIMESTAMPTZ NOT NULL, consumed_at TIMESTAMPTZ,
 bank_account_id BIGINT REFERENCES shipper_bank_accounts(bank_account_id),
 created_at TIMESTAMPTZ NOT NULL DEFAULT now(), UNIQUE(shipper_id,client_key)
);
ALTER TABLE private_files ADD COLUMN message_id BIGINT REFERENCES chat_messages(message_id);
CREATE INDEX ix_private_files_message ON private_files(message_id) WHERE deleted_at IS NULL;
CREATE TABLE support_replies (
 reply_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
 ticket_id BIGINT NOT NULL REFERENCES support_tickets(ticket_id),
 sender_id BIGINT NOT NULL REFERENCES users(user_id),
 sender_role VARCHAR(10) NOT NULL CHECK(sender_role IN ('CUSTOMER','SHIPPER','ADMIN')),
 body TEXT NOT NULL, client_key VARCHAR(100) NOT NULL,
 created_at TIMESTAMPTZ NOT NULL DEFAULT now(), UNIQUE(sender_id,client_key)
);
CREATE INDEX ix_support_replies_ticket ON support_replies(ticket_id,reply_id DESC);
CREATE TABLE person3_event_failures (
 event_id BIGINT PRIMARY KEY REFERENCES order_events(event_id),
 attempts INTEGER NOT NULL DEFAULT 0,
 next_attempt_at TIMESTAMPTZ NOT NULL DEFAULT now(),
 last_error_code VARCHAR(100) NOT NULL,
 status VARCHAR(10) NOT NULL CHECK(status IN ('PENDING','FAILED'))
);
INSERT INTO permissions(permission_code,description) VALUES('person3.manage_jobs','Read and retry Person 3 event failures') ON CONFLICT DO NOTHING;
INSERT INTO role_permissions(role_id,permission_id) SELECT r.role_id,p.permission_id FROM roles r CROSS JOIN permissions p WHERE r.role_code='ADMIN' AND p.permission_code='person3.manage_jobs' ON CONFLICT DO NOTHING;
CREATE TABLE payout_fx_quotes (
 fx_quote_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
 earning_id BIGINT NOT NULL UNIQUE REFERENCES shipper_earnings(earning_id),
 rate NUMERIC(18,8) NOT NULL CHECK(rate>0), provider VARCHAR(100) NOT NULL,
 reference VARCHAR(180) NOT NULL UNIQUE, evidence_reference TEXT NOT NULL,
 created_by BIGINT NOT NULL REFERENCES users(user_id), created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE TABLE payout_actions (
 action_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
 payout_id BIGINT NOT NULL REFERENCES shipper_payouts(payout_id),
 actor_id BIGINT NOT NULL REFERENCES users(user_id),
 action VARCHAR(20) NOT NULL CHECK(action IN ('FAILED','RETRY')),
 client_key VARCHAR(100) NOT NULL, reason TEXT NOT NULL, evidence_reference TEXT NOT NULL, payload_hash CHAR(64) NOT NULL,
 created_at TIMESTAMPTZ NOT NULL DEFAULT now(), UNIQUE(actor_id,client_key)
);
