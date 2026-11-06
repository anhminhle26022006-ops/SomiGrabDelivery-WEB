BEGIN;
SET LOCAL search_path TO somi, public;
ALTER TABLE pricing_policies DROP CONSTRAINT pricing_policies_calculation_rule_check;
ALTER TABLE pricing_policies ADD CONSTRAINT pricing_policies_calculation_rule_check
    CHECK (calculation_rule IN ('DISTANCE_BAND_X_TIME_X_WEATHER', 'BASE_PLUS_DISTANCE_PLUS_WEIGHT'));
ALTER TABLE pricing_policies
    ADD COLUMN base_fee_minor BIGINT CHECK (base_fee_minor >= 0),
    ADD COLUMN included_distance_km NUMERIC(9,3) CHECK (included_distance_km >= 0),
    ADD COLUMN per_km_minor BIGINT CHECK (per_km_minor >= 0),
    ADD COLUMN included_weight_kg NUMERIC(9,3) CHECK (included_weight_kg >= 0),
    ADD COLUMN per_kg_minor BIGINT CHECK (per_kg_minor >= 0),
    ADD COLUMN max_weight_kg NUMERIC(9,3) CHECK (max_weight_kg > 0),
    ADD CONSTRAINT ck_distance_weight_pricing_fields CHECK (
        calculation_rule <> 'BASE_PLUS_DISTANCE_PLUS_WEIGHT' OR
        (base_fee_minor IS NOT NULL AND included_distance_km IS NOT NULL AND per_km_minor IS NOT NULL
         AND included_weight_kg IS NOT NULL AND per_kg_minor IS NOT NULL AND max_weight_kg IS NOT NULL
         AND max_distance_km IS NOT NULL AND max_weight_kg >= included_weight_kg
         AND max_distance_km > included_distance_km));
COMMIT;
