-- SOMI proposed PostgreSQL schema.
-- Business design: database/DESIGN.md. This is not wired to the PHP demo yet.
-- Apply only after backend implementation and the remaining operational policies are approved.

BEGIN;

CREATE SCHEMA IF NOT EXISTS somi;
SET search_path TO somi, public;

-- Keep lookup rows instead of hard-coded client-side role checks. Seed CUSTOMER,
-- SHIPPER, ADMIN plus permissions through a separate deployment seed.
CREATE TABLE roles (
    role_id          BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    role_code        VARCHAR(40) NOT NULL UNIQUE,
    name             VARCHAR(100) NOT NULL,
    description      TEXT,
    created_at       TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE permissions (
    permission_id    BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    permission_code  VARCHAR(100) NOT NULL UNIQUE,
    description      TEXT
);

CREATE TABLE role_permissions (
    role_id          BIGINT NOT NULL REFERENCES roles(role_id) ON DELETE CASCADE,
    permission_id    BIGINT NOT NULL REFERENCES permissions(permission_id) ON DELETE CASCADE,
    PRIMARY KEY (role_id, permission_id)
);

CREATE TABLE users (
    user_id              BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    full_name            VARCHAR(150) NOT NULL,
    email                VARCHAR(254) NOT NULL UNIQUE,
    phone                VARCHAR(20) NOT NULL UNIQUE,
    password_hash        VARCHAR(255) NOT NULL,
    nationality_code     CHAR(2) NOT NULL,
    date_of_birth        DATE NOT NULL,
    gender               VARCHAR(1) NOT NULL CHECK (gender IN ('M', 'F')),
    app_language         VARCHAR(2) NOT NULL CHECK (app_language IN ('vi', 'en')),
    account_status       VARCHAR(20) NOT NULL DEFAULT 'PENDING_VERIFICATION'
                         CHECK (account_status IN ('PENDING_VERIFICATION', 'ACTIVE', 'SUSPENDED', 'CLOSED')),
    email_verified_at    TIMESTAMPTZ,
    phone_verified_at    TIMESTAMPTZ,
    last_login_at        TIMESTAMPTZ,
    password_changed_at TIMESTAMPTZ,
    anonymized_at        TIMESTAMPTZ,
    created_at           TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at           TIMESTAMPTZ NOT NULL DEFAULT now(),
    CHECK (email = lower(email))
);

CREATE TABLE user_roles (
    user_id          BIGINT NOT NULL REFERENCES users(user_id) ON DELETE CASCADE,
    role_id          BIGINT NOT NULL REFERENCES roles(role_id) ON DELETE RESTRICT,
    assigned_at      TIMESTAMPTZ NOT NULL DEFAULT now(),
    assigned_by      BIGINT REFERENCES users(user_id) ON DELETE SET NULL,
    PRIMARY KEY (user_id, role_id)
);

CREATE INDEX ix_user_roles_role ON user_roles(role_id, user_id);
CREATE INDEX ix_users_status_created ON users(account_status, created_at DESC);

CREATE TABLE email_verifications (
    verification_id  BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    user_id          BIGINT NOT NULL REFERENCES users(user_id) ON DELETE CASCADE,
    email            VARCHAR(254) NOT NULL,
    token_hash       VARCHAR(255) NOT NULL UNIQUE,
    purpose          VARCHAR(30) NOT NULL CHECK (purpose IN ('VERIFY_EMAIL', 'CHANGE_EMAIL')),
    expires_at       TIMESTAMPTZ NOT NULL,
    consumed_at      TIMESTAMPTZ,
    created_at       TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX ix_email_verifications_user ON email_verifications(user_id, purpose, created_at DESC);

CREATE TABLE password_reset_tokens (
    reset_token_id    BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    user_id           BIGINT NOT NULL REFERENCES users(user_id) ON DELETE CASCADE,
    token_hash        VARCHAR(255) NOT NULL UNIQUE,
    expires_at        TIMESTAMPTZ NOT NULL,
    consumed_at       TIMESTAMPTZ,
    created_at        TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX ix_password_reset_user ON password_reset_tokens(user_id, created_at DESC);

CREATE TABLE phone_verifications (
    verification_id  BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    user_id          BIGINT NOT NULL REFERENCES users(user_id) ON DELETE CASCADE,
    phone            VARCHAR(20) NOT NULL,
    code_hash        VARCHAR(255) NOT NULL,
    purpose          VARCHAR(30) NOT NULL CHECK (purpose IN ('VERIFY_PHONE', 'CHANGE_PHONE', 'CHANGE_BANK_ACCOUNT')),
    attempt_count    INTEGER NOT NULL DEFAULT 0 CHECK (attempt_count >= 0),
    expires_at       TIMESTAMPTZ NOT NULL,
    consumed_at      TIMESTAMPTZ,
    created_at       TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX ix_phone_verifications_user ON phone_verifications(user_id, purpose, created_at DESC);

CREATE TABLE account_status_history (
    history_id       BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    user_id          BIGINT NOT NULL REFERENCES users(user_id) ON DELETE RESTRICT,
    old_status       VARCHAR(20),
    new_status       VARCHAR(20) NOT NULL,
    reason           TEXT,
    changed_by       BIGINT REFERENCES users(user_id) ON DELETE SET NULL,
    created_at       TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX ix_account_status_history_user ON account_status_history(user_id, created_at DESC);

CREATE TABLE customer_profiles (
    user_id          BIGINT PRIMARY KEY REFERENCES users(user_id) ON DELETE RESTRICT,
    created_at       TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at       TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE shipper_profiles (
    user_id                    BIGINT PRIMARY KEY REFERENCES users(user_id) ON DELETE RESTRICT,
    vehicle_type               VARCHAR(80) NOT NULL,
    vehicle_registration       VARCHAR(40) NOT NULL,
    verification_status        VARCHAR(20) NOT NULL DEFAULT 'PENDING'
                               CHECK (verification_status IN ('PENDING', 'NEEDS_INFO', 'APPROVED', 'REJECTED')),
    availability               BOOLEAN NOT NULL DEFAULT FALSE,
    reliability_score          NUMERIC(7,2) NOT NULL DEFAULT 100 CHECK (reliability_score >= 0),
    customer_rating_average    NUMERIC(3,2) NOT NULL DEFAULT 0 CHECK (customer_rating_average BETWEEN 0 AND 5),
    customer_rating_count      INTEGER NOT NULL DEFAULT 0 CHECK (customer_rating_count >= 0),
    last_active_at             TIMESTAMPTZ,
    approved_at                TIMESTAMPTZ,
    approved_by                BIGINT REFERENCES users(user_id) ON DELETE SET NULL,
    created_at                 TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at                 TIMESTAMPTZ NOT NULL DEFAULT now(),
    UNIQUE (vehicle_registration)
);
CREATE INDEX ix_shipper_available ON shipper_profiles(availability, verification_status)
    WHERE availability = TRUE;

CREATE TABLE shipper_documents (
    document_id        BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    shipper_id         BIGINT NOT NULL REFERENCES shipper_profiles(user_id) ON DELETE RESTRICT,
    document_type      VARCHAR(60) NOT NULL,
    object_key         TEXT NOT NULL,
    original_name      VARCHAR(255),
    mime_type          VARCHAR(120) NOT NULL,
    byte_size          BIGINT NOT NULL CHECK (byte_size >= 0),
    checksum_sha256    CHAR(64),
    review_status      VARCHAR(20) NOT NULL DEFAULT 'PENDING'
                       CHECK (review_status IN ('PENDING', 'APPROVED', 'REJECTED', 'NEEDS_INFO')),
    rejection_reason   TEXT,
    uploaded_at        TIMESTAMPTZ NOT NULL DEFAULT now(),
    reviewed_at        TIMESTAMPTZ,
    reviewed_by        BIGINT REFERENCES users(user_id) ON DELETE SET NULL,
    delete_after       TIMESTAMPTZ,
    deleted_at         TIMESTAMPTZ
);
CREATE INDEX ix_shipper_documents_retention ON shipper_documents(delete_after)
    WHERE deleted_at IS NULL;

CREATE TABLE shipper_score_policies (
    policy_id          BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    deduction_points   NUMERIC(7,2) NOT NULL CHECK (deduction_points >= 0),
    lock_threshold     NUMERIC(7,2) NOT NULL CHECK (lock_threshold >= 0),
    recovery_points    NUMERIC(7,2) NOT NULL CHECK (recovery_points >= 0),
    effective_from     TIMESTAMPTZ NOT NULL,
    effective_until    TIMESTAMPTZ,
    changed_by         BIGINT REFERENCES users(user_id) ON DELETE SET NULL,
    created_at         TIMESTAMPTZ NOT NULL DEFAULT now(),
    CHECK (effective_until IS NULL OR effective_until > effective_from)
);

CREATE TABLE shipper_score_events (
    score_event_id     BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    shipper_id         BIGINT NOT NULL REFERENCES shipper_profiles(user_id) ON DELETE RESTRICT,
    order_id           BIGINT,
    event_type         VARCHAR(40) NOT NULL CHECK (event_type IN ('CANCEL_BEFORE_PICKUP', 'ADMIN_ADJUSTMENT', 'RECOVERY')),
    points_delta       NUMERIC(7,2) NOT NULL,
    score_after        NUMERIC(7,2) NOT NULL CHECK (score_after >= 0),
    reason             TEXT,
    actor_id           BIGINT REFERENCES users(user_id) ON DELETE SET NULL,
    created_at         TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX ix_shipper_score_events_shipper ON shipper_score_events(shipper_id, created_at DESC);

CREATE TABLE shipper_bank_accounts (
    bank_account_id    BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    shipper_id         BIGINT NOT NULL REFERENCES shipper_profiles(user_id) ON DELETE RESTRICT,
    bank_code          VARCHAR(40) NOT NULL,
    bank_name          VARCHAR(120),
    account_number_enc BYTEA NOT NULL,
    account_holder     VARCHAR(150) NOT NULL,
    verified_at        TIMESTAMPTZ NOT NULL,
    replaced_at        TIMESTAMPTZ,
    created_at         TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE UNIQUE INDEX ux_shipper_current_bank_account ON shipper_bank_accounts(shipper_id)
    WHERE replaced_at IS NULL;

CREATE TABLE customer_addresses (
    address_id         BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    customer_id        BIGINT NOT NULL REFERENCES customer_profiles(user_id) ON DELETE RESTRICT,
    label              VARCHAR(60),
    country_code       CHAR(2) NOT NULL DEFAULT 'VN' CHECK (country_code = 'VN'),
    province           VARCHAR(120),
    district           VARCHAR(120),
    ward               VARCHAR(120),
    address_detail     VARCHAR(255) NOT NULL,
    latitude           NUMERIC(10,7) NOT NULL CHECK (latitude BETWEEN -90 AND 90),
    longitude          NUMERIC(10,7) NOT NULL CHECK (longitude BETWEEN -180 AND 180),
    is_default         BOOLEAN NOT NULL DEFAULT FALSE,
    created_at         TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at         TIMESTAMPTZ NOT NULL DEFAULT now(),
    archived_at        TIMESTAMPTZ
);
CREATE UNIQUE INDEX ux_customer_default_address ON customer_addresses(customer_id)
    WHERE is_default = TRUE AND archived_at IS NULL;
CREATE INDEX ix_customer_addresses_owner ON customer_addresses(customer_id, created_at DESC)
    WHERE archived_at IS NULL;

CREATE TABLE parcel_categories (
    category_id        BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    category_code      VARCHAR(60) NOT NULL UNIQUE,
    name_vi            VARCHAR(120) NOT NULL,
    name_en            VARCHAR(120) NOT NULL,
    description        TEXT,
    restriction_type   VARCHAR(20) NOT NULL DEFAULT 'ALLOWED'
                       CHECK (restriction_type IN ('ALLOWED', 'RESTRICTED', 'PROHIBITED')),
    active             BOOLEAN NOT NULL DEFAULT TRUE,
    created_at         TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at         TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE pricing_policies (
    pricing_policy_id  BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    policy_name        VARCHAR(120) NOT NULL,
    currency_code      CHAR(3) NOT NULL CHECK (currency_code IN ('VND', 'USD')),
    return_fee_minor   BIGINT NOT NULL DEFAULT 0 CHECK (return_fee_minor >= 0),
    active             BOOLEAN NOT NULL DEFAULT FALSE,
    effective_from     TIMESTAMPTZ NOT NULL,
    effective_until    TIMESTAMPTZ,
    config_version     INTEGER NOT NULL DEFAULT 1 CHECK (config_version > 0),
    created_by         BIGINT REFERENCES users(user_id) ON DELETE SET NULL,
    created_at         TIMESTAMPTZ NOT NULL DEFAULT now(),
    CHECK (effective_until IS NULL OR effective_until > effective_from),
    UNIQUE (currency_code, config_version)
);
CREATE UNIQUE INDEX ux_active_pricing_policy_currency ON pricing_policies(currency_code)
    WHERE active = TRUE;

CREATE TABLE distance_price_bands (
    distance_band_id   BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    pricing_policy_id  BIGINT NOT NULL REFERENCES pricing_policies(pricing_policy_id) ON DELETE CASCADE,
    min_km             NUMERIC(9,3) NOT NULL CHECK (min_km >= 0),
    max_km             NUMERIC(9,3),
    amount_minor       BIGINT NOT NULL CHECK (amount_minor >= 0),
    UNIQUE (pricing_policy_id, min_km),
    CHECK (max_km IS NULL OR max_km > min_km)
);

CREATE TABLE time_price_bands (
    time_band_id       BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    pricing_policy_id  BIGINT NOT NULL REFERENCES pricing_policies(pricing_policy_id) ON DELETE CASCADE,
    day_of_week        SMALLINT CHECK (day_of_week BETWEEN 0 AND 6),
    starts_at_local    TIME NOT NULL,
    ends_at_local      TIME NOT NULL,
    multiplier         NUMERIC(8,4) NOT NULL DEFAULT 1 CHECK (multiplier >= 0),
    CHECK (starts_at_local <> ends_at_local)
);
CREATE INDEX ix_time_price_policy ON time_price_bands(pricing_policy_id);

CREATE TABLE weather_price_bands (
    weather_band_id    BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    pricing_policy_id  BIGINT NOT NULL REFERENCES pricing_policies(pricing_policy_id) ON DELETE CASCADE,
    provider_condition_code VARCHAR(80) NOT NULL,
    label              VARCHAR(120) NOT NULL,
    multiplier         NUMERIC(8,4) NOT NULL DEFAULT 1 CHECK (multiplier >= 0),
    UNIQUE (pricing_policy_id, provider_condition_code)
);

CREATE TABLE cancellation_fee_rules (
    cancellation_fee_rule_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    pricing_policy_id  BIGINT NOT NULL REFERENCES pricing_policies(pricing_policy_id) ON DELETE CASCADE,
    after_status       VARCHAR(30) NOT NULL,
    fee_minor          BIGINT NOT NULL CHECK (fee_minor >= 0),
    UNIQUE (pricing_policy_id, after_status)
);

CREATE TABLE shipper_earning_policies (
    earning_policy_id  BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    policy_type        VARCHAR(30) NOT NULL CHECK (policy_type IN ('SUCCESSFUL_DELIVERY_PERCENT', 'FAILED_RETURN_FIXED')),
    currency_code      CHAR(3) NOT NULL CHECK (currency_code IN ('VND', 'USD')),
    percentage_rate    NUMERIC(7,4),
    fixed_amount_minor BIGINT,
    effective_from     TIMESTAMPTZ NOT NULL,
    effective_until    TIMESTAMPTZ,
    changed_by         BIGINT REFERENCES users(user_id) ON DELETE SET NULL,
    created_at         TIMESTAMPTZ NOT NULL DEFAULT now(),
    CHECK ((policy_type = 'SUCCESSFUL_DELIVERY_PERCENT' AND percentage_rate BETWEEN 0 AND 100 AND fixed_amount_minor IS NULL)
        OR (policy_type = 'FAILED_RETURN_FIXED' AND percentage_rate IS NULL AND fixed_amount_minor IS NOT NULL AND fixed_amount_minor >= 0)),
    CHECK (effective_until IS NULL OR effective_until > effective_from)
);

CREATE TABLE orders (
    order_id                    BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    order_code                  VARCHAR(32) NOT NULL UNIQUE,
    customer_id                 BIGINT NOT NULL REFERENCES customer_profiles(user_id) ON DELETE RESTRICT,
    status                      VARCHAR(30) NOT NULL DEFAULT 'PENDING_PAYMENT'
                                CHECK (status IN ('PENDING_PAYMENT', 'SEARCHING_SHIPPER', 'NO_SHIPPER_FOUND', 'SHIPPER_ASSIGNED', 'PICKED_UP', 'IN_DELIVERY', 'DELIVERED', 'RETURNING', 'RETURNED', 'CANCELLED')),
    currency_code               CHAR(3) NOT NULL CHECK (currency_code IN ('VND', 'USD')),
    app_language_at_order       VARCHAR(2) NOT NULL CHECK (app_language_at_order IN ('vi', 'en')),
    pricing_policy_id           BIGINT REFERENCES pricing_policies(pricing_policy_id) ON DELETE RESTRICT,
    pricing_policy_version      INTEGER,
    pricing_snapshot            JSONB NOT NULL DEFAULT '{}'::jsonb,
    pickup_saved_address_id     BIGINT REFERENCES customer_addresses(address_id) ON DELETE SET NULL,
    delivery_saved_address_id   BIGINT REFERENCES customer_addresses(address_id) ON DELETE SET NULL,
    distance_km                 NUMERIC(9,3) NOT NULL CHECK (distance_km >= 0),
    estimated_duration_minutes  INTEGER CHECK (estimated_duration_minutes IS NULL OR estimated_duration_minutes > 0),
    expected_delivery_at        TIMESTAMPTZ,
    pickup_label                VARCHAR(80),
    pickup_address              VARCHAR(500) NOT NULL,
    pickup_latitude             NUMERIC(10,7) NOT NULL CHECK (pickup_latitude BETWEEN -90 AND 90),
    pickup_longitude            NUMERIC(10,7) NOT NULL CHECK (pickup_longitude BETWEEN -180 AND 180),
    sender_name                 VARCHAR(150) NOT NULL,
    sender_phone                VARCHAR(20) NOT NULL,
    delivery_label              VARCHAR(80),
    delivery_address            VARCHAR(500) NOT NULL,
    delivery_latitude           NUMERIC(10,7) NOT NULL CHECK (delivery_latitude BETWEEN -90 AND 90),
    delivery_longitude          NUMERIC(10,7) NOT NULL CHECK (delivery_longitude BETWEEN -180 AND 180),
    receiver_name               VARCHAR(150) NOT NULL,
    receiver_phone              VARCHAR(20) NOT NULL,
    category_id                 BIGINT NOT NULL REFERENCES parcel_categories(category_id) ON DELETE RESTRICT,
    parcel_description          TEXT,
    estimated_weight_kg         NUMERIC(9,3) NOT NULL CHECK (estimated_weight_kg > 0),
    length_cm                   NUMERIC(9,2) CHECK (length_cm IS NULL OR length_cm > 0),
    width_cm                    NUMERIC(9,2) CHECK (width_cm IS NULL OR width_cm > 0),
    height_cm                   NUMERIC(9,2) CHECK (height_cm IS NULL OR height_cm > 0),
    customer_note               TEXT,
    distance_fee_minor          BIGINT NOT NULL CHECK (distance_fee_minor >= 0),
    time_multiplier             NUMERIC(8,4) NOT NULL DEFAULT 1 CHECK (time_multiplier >= 0),
    weather_multiplier          NUMERIC(8,4) NOT NULL DEFAULT 1 CHECK (weather_multiplier >= 0),
    total_charge_minor          BIGINT NOT NULL CHECK (total_charge_minor >= 0),
    return_fee_minor            BIGINT NOT NULL DEFAULT 0 CHECK (return_fee_minor >= 0),
    cancellation_fee_minor      BIGINT NOT NULL DEFAULT 0 CHECK (cancellation_fee_minor >= 0),
    pricing_weather_provider    VARCHAR(100),
    pricing_weather_condition   VARCHAR(100),
    pricing_weather_forecast_at TIMESTAMPTZ,
    created_at                  TIMESTAMPTZ NOT NULL DEFAULT now(),
    paid_at                     TIMESTAMPTZ,
    picked_up_at                TIMESTAMPTZ,
    completed_at                TIMESTAMPTZ,
    closed_at                   TIMESTAMPTZ,
    cancelled_at                TIMESTAMPTZ,
    updated_at                  TIMESTAMPTZ NOT NULL DEFAULT now(),
    CHECK ((app_language_at_order = 'vi' AND currency_code = 'VND') OR (app_language_at_order = 'en' AND currency_code = 'USD'))
);
CREATE INDEX ix_orders_customer_status ON orders(customer_id, status, created_at DESC);
CREATE INDEX ix_orders_status_created ON orders(status, created_at);
CREATE INDEX ix_orders_created ON orders(created_at DESC);

ALTER TABLE shipper_score_events
    ADD CONSTRAINT fk_shipper_score_events_order
    FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE RESTRICT;

CREATE TABLE order_status_history (
    status_history_id   BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    order_id            BIGINT NOT NULL REFERENCES orders(order_id) ON DELETE RESTRICT,
    old_status          VARCHAR(30),
    new_status          VARCHAR(30) NOT NULL,
    actor_id            BIGINT REFERENCES users(user_id) ON DELETE SET NULL,
    actor_kind          VARCHAR(20) NOT NULL CHECK (actor_kind IN ('CUSTOMER', 'SHIPPER', 'ADMIN', 'SYSTEM', 'PROVIDER')),
    note                TEXT,
    metadata            JSONB NOT NULL DEFAULT '{}'::jsonb,
    created_at          TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX ix_order_status_history_order ON order_status_history(order_id, created_at);

CREATE TABLE order_match_offers (
    offer_id             BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    order_id             BIGINT NOT NULL REFERENCES orders(order_id) ON DELETE RESTRICT,
    shipper_id           BIGINT NOT NULL REFERENCES shipper_profiles(user_id) ON DELETE RESTRICT,
    distance_to_pickup_km NUMERIC(9,3),
    match_score          NUMERIC(10,5),
    status               VARCHAR(20) NOT NULL DEFAULT 'PENDING'
                         CHECK (status IN ('PENDING', 'ACCEPTED', 'REJECTED', 'EXPIRED', 'CANCELLED', 'LOST_RACE')),
    sent_at              TIMESTAMPTZ NOT NULL DEFAULT now(),
    expires_at           TIMESTAMPTZ NOT NULL,
    responded_at         TIMESTAMPTZ,
    response_note        TEXT,
    UNIQUE (order_id, shipper_id, sent_at),
    CHECK (expires_at <= sent_at + INTERVAL '2 minutes')
);
CREATE INDEX ix_offers_shipper_status ON order_match_offers(shipper_id, status, expires_at);
CREATE INDEX ix_offers_order_status ON order_match_offers(order_id, status, sent_at);

CREATE TABLE order_assignments (
    assignment_id        BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    order_id             BIGINT NOT NULL REFERENCES orders(order_id) ON DELETE RESTRICT,
    shipper_id           BIGINT NOT NULL REFERENCES shipper_profiles(user_id) ON DELETE RESTRICT,
    offer_id             BIGINT REFERENCES order_match_offers(offer_id) ON DELETE RESTRICT,
    assignment_status    VARCHAR(20) NOT NULL DEFAULT 'ACTIVE'
                         CHECK (assignment_status IN ('ACTIVE', 'CANCELLED_BEFORE_PICKUP', 'COMPLETED', 'RETURNED', 'ADMIN_CANCELLED')),
    assigned_at          TIMESTAMPTZ NOT NULL DEFAULT now(),
    ended_at             TIMESTAMPTZ,
    ended_reason         TEXT
);
CREATE UNIQUE INDEX ux_order_single_active_assignment ON order_assignments(order_id)
    WHERE assignment_status = 'ACTIVE';
CREATE INDEX ix_assignments_shipper_status ON order_assignments(shipper_id, assignment_status, assigned_at DESC);

CREATE TABLE deliveries (
    delivery_id          BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    order_id             BIGINT NOT NULL REFERENCES orders(order_id) ON DELETE RESTRICT,
    assignment_id        BIGINT NOT NULL UNIQUE REFERENCES order_assignments(assignment_id) ON DELETE RESTRICT,
    picked_up_at         TIMESTAMPTZ,
    delivery_started_at  TIMESTAMPTZ,
    delivered_at         TIMESTAMPTZ,
    failure_reason_code VARCHAR(60),
    failure_note         TEXT,
    return_started_at    TIMESTAMPTZ,
    returned_at          TIMESTAMPTZ,
    customer_return_confirmed_at TIMESTAMPTZ,
    created_at           TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at           TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX ix_deliveries_order ON deliveries(order_id, created_at DESC);

CREATE TABLE cancellation_requests (
    cancellation_request_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    order_id             BIGINT NOT NULL REFERENCES orders(order_id) ON DELETE RESTRICT,
    requested_by         BIGINT NOT NULL REFERENCES users(user_id) ON DELETE RESTRICT,
    requester_role       VARCHAR(20) NOT NULL CHECK (requester_role IN ('CUSTOMER', 'SHIPPER', 'ADMIN')),
    reason_code          VARCHAR(60),
    reason               TEXT NOT NULL,
    requires_admin_decision BOOLEAN NOT NULL,
    decision             VARCHAR(20) NOT NULL DEFAULT 'PENDING'
                         CHECK (decision IN ('PENDING', 'APPROVED', 'REJECTED', 'NOT_REQUIRED')),
    decided_by           BIGINT REFERENCES users(user_id) ON DELETE SET NULL,
    decision_note        TEXT,
    requested_at         TIMESTAMPTZ NOT NULL DEFAULT now(),
    decided_at           TIMESTAMPTZ
);
CREATE INDEX ix_cancellation_order ON cancellation_requests(order_id, requested_at DESC);

CREATE TABLE delivery_evidence (
    evidence_id          BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    delivery_id          BIGINT NOT NULL REFERENCES deliveries(delivery_id) ON DELETE RESTRICT,
    evidence_type        VARCHAR(20) NOT NULL CHECK (evidence_type IN ('DELIVERY_PHOTO', 'RETURN_PHOTO')),
    object_key           TEXT NOT NULL,
    mime_type            VARCHAR(120) NOT NULL,
    byte_size            BIGINT NOT NULL CHECK (byte_size >= 0),
    checksum_sha256      CHAR(64),
    uploaded_by          BIGINT NOT NULL REFERENCES users(user_id) ON DELETE RESTRICT,
    uploaded_at          TIMESTAMPTZ NOT NULL DEFAULT now(),
    delete_after         TIMESTAMPTZ NOT NULL,
    deleted_at           TIMESTAMPTZ
);
CREATE INDEX ix_delivery_evidence_retention ON delivery_evidence(delete_after)
    WHERE deleted_at IS NULL;

CREATE TABLE order_reviews (
    review_id            BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    order_id             BIGINT NOT NULL UNIQUE REFERENCES orders(order_id) ON DELETE RESTRICT,
    customer_id          BIGINT NOT NULL REFERENCES customer_profiles(user_id) ON DELETE RESTRICT,
    shipper_id           BIGINT NOT NULL REFERENCES shipper_profiles(user_id) ON DELETE RESTRICT,
    rating               SMALLINT NOT NULL CHECK (rating BETWEEN 1 AND 5),
    comment              VARCHAR(1000),
    created_at           TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at           TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX ix_reviews_shipper ON order_reviews(shipper_id, created_at DESC);

CREATE TABLE shipper_locations (
    location_id          BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    shipper_id           BIGINT NOT NULL REFERENCES shipper_profiles(user_id) ON DELETE RESTRICT,
    order_id             BIGINT NOT NULL REFERENCES orders(order_id) ON DELETE RESTRICT,
    latitude             NUMERIC(10,7) NOT NULL CHECK (latitude BETWEEN -90 AND 90),
    longitude            NUMERIC(10,7) NOT NULL CHECK (longitude BETWEEN -180 AND 180),
    accuracy_meters      NUMERIC(8,2) CHECK (accuracy_meters IS NULL OR accuracy_meters >= 0),
    recorded_at          TIMESTAMPTZ NOT NULL DEFAULT now(),
    delete_after         TIMESTAMPTZ NOT NULL,
    deleted_at           TIMESTAMPTZ
);
CREATE INDEX ix_shipper_locations_order_time ON shipper_locations(order_id, recorded_at DESC);
CREATE INDEX ix_shipper_locations_retention ON shipper_locations(delete_after) WHERE deleted_at IS NULL;

CREATE TABLE payments (
    payment_id           BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    order_id             BIGINT NOT NULL UNIQUE REFERENCES orders(order_id) ON DELETE RESTRICT,
    amount_minor         BIGINT NOT NULL CHECK (amount_minor >= 0),
    currency_code        CHAR(3) NOT NULL CHECK (currency_code IN ('VND', 'USD')),
    method               VARCHAR(30) NOT NULL CHECK (method IN ('GATEWAY', 'CARD', 'BANK_TRANSFER')),
    provider             VARCHAR(100),
    status               VARCHAR(20) NOT NULL DEFAULT 'PENDING'
                         CHECK (status IN ('PENDING', 'PROCESSING', 'PAID', 'FAILED', 'CANCELLED', 'PARTIALLY_REFUNDED', 'REFUNDED')),
    provider_payment_ref VARCHAR(180),
    paid_at              TIMESTAMPTZ,
    created_at           TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at           TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE payment_transactions (
    payment_transaction_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    payment_id           BIGINT NOT NULL REFERENCES payments(payment_id) ON DELETE RESTRICT,
    provider             VARCHAR(100) NOT NULL,
    provider_transaction_id VARCHAR(180),
    idempotency_key      VARCHAR(180) UNIQUE,
    transaction_type     VARCHAR(20) NOT NULL CHECK (transaction_type IN ('AUTHORIZE', 'CAPTURE', 'PAYMENT', 'CALLBACK', 'RECONCILIATION')),
    amount_minor         BIGINT NOT NULL CHECK (amount_minor >= 0),
    currency_code        CHAR(3) NOT NULL CHECK (currency_code IN ('VND', 'USD')),
    status               VARCHAR(30) NOT NULL,
    provider_payload     JSONB,
    occurred_at          TIMESTAMPTZ,
    received_at          TIMESTAMPTZ NOT NULL DEFAULT now(),
    UNIQUE (provider, provider_transaction_id)
);
CREATE INDEX ix_payment_transactions_payment ON payment_transactions(payment_id, received_at DESC);

CREATE TABLE refunds (
    refund_id            BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    payment_id           BIGINT NOT NULL REFERENCES payments(payment_id) ON DELETE RESTRICT,
    order_id             BIGINT NOT NULL REFERENCES orders(order_id) ON DELETE RESTRICT,
    cancellation_request_id BIGINT REFERENCES cancellation_requests(cancellation_request_id) ON DELETE RESTRICT,
    reason               VARCHAR(40) NOT NULL CHECK (reason IN ('CUSTOMER_CANCELLATION', 'RETURN_FEE_DEDUCTION', 'ADMIN_ADJUSTMENT')),
    gross_amount_minor   BIGINT NOT NULL CHECK (gross_amount_minor >= 0),
    deducted_fee_minor   BIGINT NOT NULL DEFAULT 0 CHECK (deducted_fee_minor >= 0),
    refund_amount_minor  BIGINT NOT NULL CHECK (refund_amount_minor >= 0),
    currency_code        CHAR(3) NOT NULL CHECK (currency_code IN ('VND', 'USD')),
    status               VARCHAR(20) NOT NULL DEFAULT 'PENDING'
                         CHECK (status IN ('PENDING', 'PROCESSING', 'SUCCEEDED', 'FAILED')),
    provider             VARCHAR(100),
    provider_refund_id   VARCHAR(180),
    created_at           TIMESTAMPTZ NOT NULL DEFAULT now(),
    completed_at         TIMESTAMPTZ,
    CHECK (gross_amount_minor = deducted_fee_minor + refund_amount_minor)
);
CREATE INDEX ix_refunds_order ON refunds(order_id, created_at DESC);

CREATE TABLE shipper_earnings (
    earning_id           BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    order_id             BIGINT NOT NULL UNIQUE REFERENCES orders(order_id) ON DELETE RESTRICT,
    shipper_id           BIGINT NOT NULL REFERENCES shipper_profiles(user_id) ON DELETE RESTRICT,
    earning_type         VARCHAR(30) NOT NULL CHECK (earning_type IN ('SUCCESSFUL_DELIVERY', 'FAILED_RETURN')),
    basis_amount_minor   BIGINT NOT NULL CHECK (basis_amount_minor >= 0),
    percentage_rate      NUMERIC(7,4),
    gross_amount_minor   BIGINT NOT NULL CHECK (gross_amount_minor >= 0),
    currency_code        CHAR(3) NOT NULL CHECK (currency_code IN ('VND', 'USD')),
    payout_status        VARCHAR(20) NOT NULL DEFAULT 'PENDING'
                         CHECK (payout_status IN ('PENDING', 'PAID', 'FAILED', 'CANCELLED')),
    policy_snapshot      JSONB NOT NULL DEFAULT '{}'::jsonb,
    created_at           TIMESTAMPTZ NOT NULL DEFAULT now(),
    CHECK (earning_type <> 'SUCCESSFUL_DELIVERY' OR percentage_rate IS NOT NULL)
);
CREATE INDEX ix_shipper_earnings_shipper ON shipper_earnings(shipper_id, created_at DESC);

CREATE TABLE shipper_payouts (
    payout_id            BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    earning_id           BIGINT NOT NULL UNIQUE REFERENCES shipper_earnings(earning_id) ON DELETE RESTRICT,
    shipper_id           BIGINT NOT NULL REFERENCES shipper_profiles(user_id) ON DELETE RESTRICT,
    bank_account_id      BIGINT NOT NULL REFERENCES shipper_bank_accounts(bank_account_id) ON DELETE RESTRICT,
    source_amount_minor  BIGINT NOT NULL CHECK (source_amount_minor >= 0),
    source_currency      CHAR(3) NOT NULL CHECK (source_currency IN ('VND', 'USD')),
    payout_amount_minor  BIGINT NOT NULL CHECK (payout_amount_minor >= 0),
    payout_currency      CHAR(3) NOT NULL DEFAULT 'VND' CHECK (payout_currency = 'VND'),
    fx_rate              NUMERIC(18,8) CHECK (fx_rate IS NULL OR fx_rate > 0),
    fx_provider          VARCHAR(100),
    fx_reference         VARCHAR(180),
    status               VARCHAR(20) NOT NULL DEFAULT 'PENDING'
                         CHECK (status IN ('PENDING', 'PROCESSING', 'SUCCEEDED', 'FAILED')),
    provider_payout_ref  VARCHAR(180),
    failure_reason       TEXT,
    created_at           TIMESTAMPTZ NOT NULL DEFAULT now(),
    completed_at         TIMESTAMPTZ,
    CHECK ((source_currency = 'USD' AND fx_rate IS NOT NULL AND fx_provider IS NOT NULL)
        OR (source_currency = 'VND' AND fx_rate IS NULL))
);
CREATE INDEX ix_shipper_payouts_shipper ON shipper_payouts(shipper_id, created_at DESC);

CREATE TABLE support_tickets (
    ticket_id            BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    ticket_code          VARCHAR(32) NOT NULL UNIQUE,
    order_id             BIGINT NOT NULL REFERENCES orders(order_id) ON DELETE RESTRICT,
    opened_by            BIGINT NOT NULL REFERENCES users(user_id) ON DELETE RESTRICT,
    category             VARCHAR(60) NOT NULL,
    subject              VARCHAR(200),
    description          TEXT NOT NULL,
    status               VARCHAR(20) NOT NULL DEFAULT 'NEW'
                         CHECK (status IN ('NEW', 'IN_PROGRESS', 'RESOLVED', 'CLOSED')),
    resolution           TEXT,
    resolved_by          BIGINT REFERENCES users(user_id) ON DELETE SET NULL,
    created_at           TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at           TIMESTAMPTZ NOT NULL DEFAULT now(),
    resolved_at          TIMESTAMPTZ,
    closed_at            TIMESTAMPTZ
);
CREATE INDEX ix_support_tickets_status ON support_tickets(status, created_at DESC);
CREATE INDEX ix_support_tickets_order ON support_tickets(order_id, created_at DESC);

CREATE TABLE support_ticket_events (
    ticket_event_id     BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    ticket_id           BIGINT NOT NULL REFERENCES support_tickets(ticket_id) ON DELETE RESTRICT,
    old_status          VARCHAR(20),
    new_status          VARCHAR(20) NOT NULL CHECK (new_status IN ('NEW', 'IN_PROGRESS', 'RESOLVED', 'CLOSED')),
    note                TEXT,
    changed_by          BIGINT REFERENCES users(user_id) ON DELETE SET NULL,
    created_at          TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX ix_support_ticket_events_ticket ON support_ticket_events(ticket_id, created_at);

CREATE TABLE support_attachments (
    attachment_id       BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    ticket_id           BIGINT NOT NULL REFERENCES support_tickets(ticket_id) ON DELETE RESTRICT,
    uploaded_by         BIGINT NOT NULL REFERENCES users(user_id) ON DELETE RESTRICT,
    object_key          TEXT NOT NULL,
    original_name       VARCHAR(255),
    mime_type           VARCHAR(120) NOT NULL,
    byte_size           BIGINT NOT NULL CHECK (byte_size >= 0),
    checksum_sha256     CHAR(64),
    uploaded_at         TIMESTAMPTZ NOT NULL DEFAULT now(),
    delete_after        TIMESTAMPTZ NOT NULL,
    deleted_at          TIMESTAMPTZ
);
CREATE INDEX ix_support_attachments_retention ON support_attachments(delete_after)
    WHERE deleted_at IS NULL;

CREATE TABLE chat_messages (
    message_id          BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    order_id            BIGINT NOT NULL REFERENCES orders(order_id) ON DELETE RESTRICT,
    sender_id           BIGINT NOT NULL REFERENCES users(user_id) ON DELETE RESTRICT,
    message_type        VARCHAR(20) NOT NULL DEFAULT 'TEXT' CHECK (message_type IN ('TEXT', 'SYSTEM')),
    body                TEXT,
    sent_at             TIMESTAMPTZ NOT NULL DEFAULT now(),
    read_at             TIMESTAMPTZ,
    delete_after        TIMESTAMPTZ NOT NULL,
    deleted_at          TIMESTAMPTZ,
    CHECK (message_type <> 'TEXT' OR body IS NOT NULL)
);
CREATE INDEX ix_chat_messages_order_time ON chat_messages(order_id, sent_at);
CREATE INDEX ix_chat_messages_retention ON chat_messages(delete_after) WHERE deleted_at IS NULL;

CREATE TABLE chat_attachments (
    attachment_id       BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    message_id          BIGINT NOT NULL REFERENCES chat_messages(message_id) ON DELETE RESTRICT,
    object_key          TEXT NOT NULL,
    original_name       VARCHAR(255),
    mime_type           VARCHAR(120) NOT NULL,
    byte_size           BIGINT NOT NULL CHECK (byte_size >= 0),
    checksum_sha256     CHAR(64),
    delete_after        TIMESTAMPTZ NOT NULL,
    deleted_at          TIMESTAMPTZ
);
CREATE INDEX ix_chat_attachments_retention ON chat_attachments(delete_after) WHERE deleted_at IS NULL;

CREATE TABLE notifications (
    notification_id     BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    user_id             BIGINT NOT NULL REFERENCES users(user_id) ON DELETE RESTRICT,
    order_id            BIGINT REFERENCES orders(order_id) ON DELETE RESTRICT,
    notification_type   VARCHAR(60) NOT NULL,
    title_vi            VARCHAR(200) NOT NULL,
    message_vi          TEXT NOT NULL,
    title_en            VARCHAR(200) NOT NULL,
    message_en          TEXT NOT NULL,
    in_app_status       VARCHAR(20) NOT NULL DEFAULT 'PENDING' CHECK (in_app_status IN ('PENDING', 'SENT', 'FAILED')),
    email_status        VARCHAR(20) NOT NULL DEFAULT 'PENDING' CHECK (email_status IN ('PENDING', 'SENT', 'FAILED', 'SKIPPED')),
    email_language      VARCHAR(2) CHECK (email_language IN ('vi', 'en')),
    read_at             TIMESTAMPTZ,
    email_sent_at       TIMESTAMPTZ,
    created_at          TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX ix_notifications_user_unread ON notifications(user_id, created_at DESC) WHERE read_at IS NULL;

CREATE TABLE audit_logs (
    audit_id            BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    actor_id            BIGINT REFERENCES users(user_id) ON DELETE SET NULL,
    actor_kind          VARCHAR(20) NOT NULL CHECK (actor_kind IN ('CUSTOMER', 'SHIPPER', 'ADMIN', 'SYSTEM', 'PROVIDER')),
    action              VARCHAR(100) NOT NULL,
    entity_type         VARCHAR(80) NOT NULL,
    entity_id           BIGINT,
    old_value           JSONB,
    new_value           JSONB,
    ip_address          INET,
    request_id          VARCHAR(120),
    created_at          TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX ix_audit_entity ON audit_logs(entity_type, entity_id, created_at DESC);
CREATE INDEX ix_audit_actor ON audit_logs(actor_id, created_at DESC);

-- Deliberately not encoded as SQL constraints: cross-row ownership/state rules
-- must be checked by backend transactions (e.g. address belongs to customer,
-- review owner matches order owner, current shipper matches assignment, legal
-- order-state transitions, offer first-accept locking, role/profile consistency).

COMMIT;
