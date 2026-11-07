BEGIN;
SET LOCAL search_path TO somi, public;

DO $compat$
BEGIN
    IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema=current_schema() AND table_name='nationality_change_requests' AND column_name='requested_country_code')
       AND NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema=current_schema() AND table_name='nationality_change_requests' AND column_name='nationality_code') THEN
        ALTER TABLE nationality_change_requests RENAME COLUMN requested_country_code TO nationality_code;
        ALTER TABLE nationality_change_requests ALTER COLUMN old_country_code DROP NOT NULL;
    END IF;
END $compat$;
CREATE TABLE IF NOT EXISTS nationality_change_requests (
    request_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    user_id BIGINT NOT NULL REFERENCES users(user_id),
    nationality_code CHAR(2) NOT NULL,
    reason TEXT NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'PENDING' CHECK (status IN ('PENDING','APPROVED','REJECTED')),
    reviewed_by BIGINT REFERENCES users(user_id), review_note TEXT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(), reviewed_at TIMESTAMPTZ
);
CREATE UNIQUE INDEX IF NOT EXISTS ux_nationality_pending ON nationality_change_requests(user_id) WHERE status='PENDING';

ALTER TABLE payments ADD COLUMN request_key VARCHAR(100), ADD COLUMN checkout_url TEXT,
    ADD COLUMN failure_code VARCHAR(80);
CREATE UNIQUE INDEX ux_payment_provider_ref ON payments(provider,provider_payment_ref) WHERE provider_payment_ref IS NOT NULL;
ALTER TABLE refunds ADD COLUMN request_key VARCHAR(180), ADD COLUMN failure_code VARCHAR(80);
ALTER TABLE refunds ADD COLUMN reconciliation_reference VARCHAR(180) UNIQUE,
    ADD COLUMN reconciled_by BIGINT REFERENCES users(user_id), ADD COLUMN evidence_reference TEXT;
CREATE UNIQUE INDEX ux_refund_request ON refunds(request_key) WHERE request_key IS NOT NULL;
CREATE UNIQUE INDEX ux_refund_provider_ref ON refunds(provider,provider_refund_id) WHERE provider_refund_id IS NOT NULL;

CREATE TABLE payment_receipts (
    receipt_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    provider VARCHAR(100) NOT NULL, external_id VARCHAR(180) NOT NULL,
    payload_hash CHAR(64) NOT NULL, payment_id BIGINT NOT NULL REFERENCES payments(payment_id),
    receipt_kind VARCHAR(20) NOT NULL CHECK(receipt_kind IN ('PAYMENT','REFUND')),
    received_at TIMESTAMPTZ NOT NULL DEFAULT now(), UNIQUE(provider,external_id)
);
CREATE TABLE bank_reconciliations (
    reconciliation_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    payment_id BIGINT NOT NULL REFERENCES payments(payment_id),
    bank_reference VARCHAR(180) NOT NULL UNIQUE,
    evidence_reference VARCHAR(500) NOT NULL,
    reconciled_by BIGINT NOT NULL REFERENCES users(user_id),
    created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE TABLE background_jobs (
    job_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    kind VARCHAR(40) NOT NULL CHECK(kind IN ('PAYMENT_CHECKOUT','PAYMENT_RECONCILE','REFUND','EMAIL')),
    deduplication_key VARCHAR(180) NOT NULL UNIQUE, payload JSONB NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'PENDING' CHECK(status IN ('PENDING','RUNNING','DONE','FAILED')),
    attempts INTEGER NOT NULL DEFAULT 0, available_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    lock_token VARCHAR(32), locked_until TIMESTAMPTZ, last_error_code VARCHAR(100),
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(), completed_at TIMESTAMPTZ
);
CREATE INDEX ix_jobs_available ON background_jobs(status,available_at);
CREATE TABLE email_messages (
    email_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    user_id BIGINT NOT NULL REFERENCES users(user_id), order_id BIGINT REFERENCES orders(order_id),
    template_code VARCHAR(80) NOT NULL, language VARCHAR(2) NOT NULL CHECK(language IN ('vi','en')),
    deduplication_key VARCHAR(180) NOT NULL UNIQUE, encrypted_payload TEXT,
    status VARCHAR(20) NOT NULL DEFAULT 'PENDING' CHECK(status IN ('PENDING','ACCEPTED','FAILED','EXPIRED')),
    provider_message_id VARCHAR(180), expires_at TIMESTAMPTZ NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(), accepted_at TIMESTAMPTZ
);
CREATE TABLE email_delivery_attempts (
    attempt_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    email_id BIGINT NOT NULL REFERENCES email_messages(email_id),
    result VARCHAR(20) NOT NULL CHECK(result IN ('ACCEPTED','FAILED','EXPIRED')),
    error_code VARCHAR(100), created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE TABLE event_consumer_receipts (
    consumer VARCHAR(80) NOT NULL, event_id BIGINT NOT NULL REFERENCES order_events(event_id),
    processed_at TIMESTAMPTZ NOT NULL DEFAULT now(), PRIMARY KEY(consumer,event_id)
);
CREATE TABLE auth_rate_limits (
    bucket_hash CHAR(64) PRIMARY KEY, attempts INTEGER NOT NULL DEFAULT 0,
    window_started_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

INSERT INTO permissions(permission_code,description) VALUES
('account.read','Tra cứu tài khoản.'),('account.nationality_review','Duyệt đổi quốc tịch.'),
('order.read_all','Tra cứu đơn quản trị.'),('payment.reconcile','Đối soát thanh toán và xác nhận sao kê.'),
('parcel.manage','Quản lý danh mục kiện hàng.'),('email.read_log','Đọc metadata gửi email.'),
('profile.nationality_request','Yêu cầu đổi quốc tịch.') ON CONFLICT(permission_code) DO NOTHING;
INSERT INTO role_permissions(role_id,permission_id)
SELECT r.role_id,p.permission_id FROM roles r CROSS JOIN permissions p
WHERE r.role_code='ADMIN' AND p.permission_code IN ('account.read','account.nationality_review','order.read_all','payment.reconcile','parcel.manage','email.read_log')
ON CONFLICT DO NOTHING;
INSERT INTO role_permissions(role_id,permission_id)
SELECT r.role_id,p.permission_id FROM roles r CROSS JOIN permissions p
WHERE r.role_code IN ('CUSTOMER','SHIPPER') AND p.permission_code='profile.nationality_request'
ON CONFLICT DO NOTHING;
COMMIT;
