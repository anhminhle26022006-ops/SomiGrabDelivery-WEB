SET LOCAL search_path TO somi, public;
ALTER TABLE order_assignments ALTER COLUMN assignment_status TYPE VARCHAR(30);
ALTER TABLE shipper_score_policies ADD COLUMN post_pickup_deduction_points NUMERIC(7,2) NOT NULL DEFAULT 20 CHECK(post_pickup_deduction_points>=0), ADD COLUMN max_score NUMERIC(7,2) NOT NULL DEFAULT 100 CHECK(max_score>0);
ALTER TABLE shipper_score_events DROP CONSTRAINT shipper_score_events_event_type_check;
ALTER TABLE shipper_score_events ADD CONSTRAINT shipper_score_events_event_type_check CHECK(event_type IN ('CANCEL_BEFORE_PICKUP','POST_PICKUP_VIOLATION','ADMIN_ADJUSTMENT','RECOVERY'));
ALTER TABLE shipper_score_events ADD COLUMN source_key VARCHAR(120) UNIQUE, ADD COLUMN policy_snapshot JSONB, ADD COLUMN evidence_reference TEXT;
ALTER TABLE cancellation_requests ADD COLUMN assignment_id BIGINT REFERENCES order_assignments(assignment_id), ADD COLUMN request_key VARCHAR(100), ADD COLUMN payload_hash CHAR(64), ADD COLUMN review_hash CHAR(64), ADD COLUMN shipper_at_fault BOOLEAN, ADD COLUMN evidence_reference TEXT;
CREATE UNIQUE INDEX ux_shipper_cancel_key ON cancellation_requests(requested_by,request_key) WHERE request_key IS NOT NULL;
CREATE UNIQUE INDEX ux_shipper_cancel_pending ON cancellation_requests(assignment_id) WHERE assignment_id IS NOT NULL AND decision='PENDING';
