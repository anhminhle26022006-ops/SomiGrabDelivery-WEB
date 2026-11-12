SET LOCAL search_path TO somi, public;
ALTER TABLE private_files ADD COLUMN storage_backend VARCHAR(20) NOT NULL DEFAULT 'local' CHECK(storage_backend IN ('local','supabase'));
ALTER TABLE private_files ADD COLUMN storage_bucket VARCHAR(63);
ALTER TABLE private_files ADD CONSTRAINT private_files_storage_bucket CHECK((storage_backend='local' AND storage_bucket IS NULL) OR (storage_backend='supabase' AND storage_bucket IS NOT NULL));
