-- Add OTP attempt tracking to an existing SOMI database.
-- Safe to run once; it does not recreate or delete existing data.
ALTER TABLE somi.email_verifications
    ADD COLUMN IF NOT EXISTS attempt_count SMALLINT NOT NULL DEFAULT 0;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conname = 'email_verifications_attempt_count_check'
          AND conrelid = 'somi.email_verifications'::regclass
    ) THEN
        ALTER TABLE somi.email_verifications
            ADD CONSTRAINT email_verifications_attempt_count_check
            CHECK (attempt_count BETWEEN 0 AND 5);
    END IF;
END $$;
