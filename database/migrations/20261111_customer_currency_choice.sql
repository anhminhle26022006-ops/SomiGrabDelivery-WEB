BEGIN;
SET LOCAL search_path TO somi, public;
-- Currency is chosen for each quote/order, independently of the email language.
-- Keep the existing individual language, currency FK, amount and status constraints.
DO $$
DECLARE legacy record;
BEGIN
    FOR legacy IN
        SELECT conname FROM pg_constraint
        WHERE conrelid='orders'::regclass AND contype='c' AND array_length(conkey,1)=2
          AND pg_get_constraintdef(oid) LIKE '%app_language_at_order%'
          AND pg_get_constraintdef(oid) LIKE '%currency_code%'
    LOOP
        EXECUTE format('ALTER TABLE orders DROP CONSTRAINT %I',legacy.conname);
    END LOOP;
END $$;
COMMIT;
