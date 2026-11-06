-- Apply once after SOMI_database.sql and the existing auth migrations.
-- No monetary policy or parcel category is seeded by this migration.
BEGIN;
SET LOCAL search_path TO somi, public;

-- Explicit opt-in: an existing policy is not silently assigned a new formula.
ALTER TABLE pricing_policies ADD COLUMN calculation_rule VARCHAR(60)
    CHECK (calculation_rule = 'DISTANCE_BAND_X_TIME_X_WEATHER');

CREATE TABLE order_quotes (
    quote_id VARCHAR(32) PRIMARY KEY CHECK (quote_id ~ '^[a-f0-9]{32}$'),
    customer_id BIGINT NOT NULL REFERENCES customer_profiles(user_id) ON DELETE RESTRICT,
    snapshot JSONB NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    expires_at TIMESTAMPTZ NOT NULL,
    CHECK (expires_at > created_at)
);
CREATE INDEX ix_quotes_owner_expiry ON order_quotes(customer_id, expires_at);

ALTER TABLE orders ADD COLUMN quote_id VARCHAR(32) UNIQUE REFERENCES order_quotes(quote_id) ON DELETE RESTRICT;
ALTER TABLE orders ADD COLUMN request_key VARCHAR(100);
CREATE UNIQUE INDEX ux_orders_customer_request_key ON orders(customer_id, request_key) WHERE request_key IS NOT NULL;

-- Transactional integration events. Workers must use their own durable consumer
-- receipts; do not mark an event globally consumed for all subscribers.
CREATE TABLE order_events (
    event_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    order_id BIGINT NOT NULL REFERENCES orders(order_id) ON DELETE RESTRICT,
    event_type VARCHAR(80) NOT NULL,
    deduplication_key VARCHAR(180) NOT NULL UNIQUE,
    payload JSONB NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX ix_order_events_order ON order_events(order_id, event_id);
COMMIT;
