SET LOCAL search_path TO somi, public;
CREATE TABLE customer_bank_accounts (
 bank_account_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
 customer_id BIGINT NOT NULL REFERENCES customer_profiles(user_id),
 bank_name VARCHAR(120) NOT NULL,
 account_holder VARCHAR(150) NOT NULL,
 encrypted_payload TEXT NOT NULL,
 created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX ix_customer_bank_latest ON customer_bank_accounts(customer_id,bank_account_id DESC);
ALTER TABLE refunds ADD COLUMN refund_bank_account_id BIGINT REFERENCES customer_bank_accounts(bank_account_id);
