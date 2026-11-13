SET LOCAL search_path TO somi, public;
ALTER TABLE customer_bank_accounts ADD COLUMN review_status VARCHAR(20) NOT NULL DEFAULT 'APPROVED' CHECK(review_status IN ('PENDING','APPROVED','REJECTED'));
ALTER TABLE customer_bank_accounts ADD COLUMN review_note TEXT;
ALTER TABLE customer_bank_accounts ADD COLUMN reviewed_by BIGINT REFERENCES users(user_id);
ALTER TABLE customer_bank_accounts ADD COLUMN reviewed_at TIMESTAMPTZ;
ALTER TABLE shipper_bank_accounts ADD COLUMN review_status VARCHAR(20) NOT NULL DEFAULT 'APPROVED' CHECK(review_status IN ('PENDING','APPROVED','REJECTED'));
ALTER TABLE shipper_bank_accounts ADD COLUMN review_note TEXT;
ALTER TABLE shipper_bank_accounts ADD COLUMN reviewed_by BIGINT REFERENCES users(user_id);
ALTER TABLE shipper_bank_accounts ADD COLUMN reviewed_at TIMESTAMPTZ;
CREATE TABLE finance_evidence (
 evidence_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
 order_id BIGINT NOT NULL REFERENCES orders(order_id),
 actor_id BIGINT NOT NULL REFERENCES users(user_id),
 action VARCHAR(20) NOT NULL CHECK(action IN ('PAYMENT','REFUND','PAYOUT')),
 target_id BIGINT NOT NULL,
 encrypted_payload TEXT NOT NULL,
 created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
 UNIQUE(action,target_id)
);

UPDATE customer_bank_accounts SET review_status='PENDING';
UPDATE shipper_bank_accounts SET review_status='PENDING';
