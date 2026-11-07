-- Additive migration. No business rows, prices or financial defaults are seeded.
CREATE TABLE IF NOT EXISTS somi.nationality_change_requests (
    request_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    user_id BIGINT NOT NULL REFERENCES somi.users(user_id) ON DELETE RESTRICT,
    old_country_code CHAR(2) NOT NULL,
    requested_country_code CHAR(2) NOT NULL CHECK (requested_country_code ~ '^[A-Z]{2}$'),
    reason TEXT NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'PENDING' CHECK (status IN ('PENDING','APPROVED','REJECTED')),
    review_note TEXT,
    reviewed_by BIGINT REFERENCES somi.users(user_id) ON DELETE RESTRICT,
    reviewed_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    CHECK (old_country_code <> requested_country_code)
);
CREATE UNIQUE INDEX IF NOT EXISTS ux_nationality_pending ON somi.nationality_change_requests(user_id) WHERE status = 'PENDING';
CREATE TABLE IF NOT EXISTS somi.pricing_configuration_drafts (
    draft_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    currency_code CHAR(3) NOT NULL CHECK (currency_code IN ('VND','USD')),
    configuration JSONB NOT NULL CHECK (jsonb_typeof(configuration) = 'object'),
    created_by BIGINT NOT NULL REFERENCES somi.users(user_id) ON DELETE RESTRICT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
ALTER TABLE somi.nationality_change_requests ENABLE ROW LEVEL SECURITY;
ALTER TABLE somi.pricing_configuration_drafts ENABLE ROW LEVEL SECURITY;
INSERT INTO somi.permissions (permission_code, description) VALUES
    ('account.read_all', 'Read accounts for administration.'),
    ('order.read_all', 'Read orders for administration.'),
    ('nationality.review', 'Review nationality change requests.')
ON CONFLICT (permission_code) DO NOTHING;
INSERT INTO somi.role_permissions (role_id, permission_id)
SELECT r.role_id, p.permission_id FROM somi.roles r CROSS JOIN somi.permissions p
WHERE r.role_code = 'ADMIN' AND p.permission_code IN ('account.read_all','order.read_all','nationality.review')
ON CONFLICT DO NOTHING;
