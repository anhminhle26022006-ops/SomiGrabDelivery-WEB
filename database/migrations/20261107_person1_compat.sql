BEGIN;
SET LOCAL search_path TO somi, public;
-- Retain the earlier Admin draft console. Drafts never activate live pricing.
CREATE TABLE IF NOT EXISTS pricing_configuration_drafts (
 draft_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
 currency_code CHAR(3) NOT NULL CHECK(currency_code IN ('VND','USD')),
 configuration JSONB NOT NULL CHECK(jsonb_typeof(configuration)='object'),
 created_by BIGINT NOT NULL REFERENCES users(user_id),
 created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
ALTER TABLE pricing_configuration_drafts ENABLE ROW LEVEL SECURITY;
COMMIT;
