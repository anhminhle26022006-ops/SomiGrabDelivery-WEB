BEGIN;
SET LOCAL search_path TO somi, public;
ALTER TABLE pricing_policies ADD COLUMN max_distance_km NUMERIC(9,3)
    CHECK (max_distance_km IS NULL OR max_distance_km > 0);
COMMIT;
