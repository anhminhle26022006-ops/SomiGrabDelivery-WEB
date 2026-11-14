BEGIN;
SET LOCAL search_path TO somi, public;

-- NULL expiry means an invitation stays open until the order/shipper changes state.
ALTER TABLE order_match_offers ALTER COLUMN expires_at DROP NOT NULL;
UPDATE order_match_offers SET expires_at=NULL WHERE status='PENDING';
ALTER TABLE orders ADD COLUMN IF NOT EXISTS matching_checked_at TIMESTAMPTZ;
ALTER TABLE deliveries ADD COLUMN IF NOT EXISTS arrived_at TIMESTAMPTZ;
ALTER TABLE deliveries ADD COLUMN IF NOT EXISTS arrival_latitude NUMERIC(10,7);
ALTER TABLE deliveries ADD COLUMN IF NOT EXISTS arrival_longitude NUMERIC(10,7);

-- Existing cancelled/exhausted orders and refund records are left unchanged.
COMMIT;
