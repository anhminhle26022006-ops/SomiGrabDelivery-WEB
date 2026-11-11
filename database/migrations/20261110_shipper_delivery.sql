-- Người 2: Shipper availability, matching/offer và giao nhận.
-- Chỉ bổ sung cột và index; không sửa dữ liệu, không seed chính sách điểm/thu nhập.
BEGIN;
SET LOCAL search_path TO somi, public;

-- Vị trí gần nhất của Shipper khi đang bật nhận đơn. Dùng để tính khoảng cách tới
-- điểm lấy hàng lúc ghép đơn; lịch sử GPS theo đơn vẫn nằm ở shipper_locations.
ALTER TABLE shipper_profiles
    ADD COLUMN last_latitude NUMERIC(10,7) CHECK (last_latitude BETWEEN -90 AND 90),
    ADD COLUMN last_longitude NUMERIC(10,7) CHECK (last_longitude BETWEEN -180 AND 180),
    ADD COLUMN last_location_at TIMESTAMPTZ;

-- Một Shipper chỉ có tối đa một offer đang chờ cho cùng một đơn.
CREATE UNIQUE INDEX ux_offer_pending_once ON order_match_offers(order_id, shipper_id)
    WHERE status = 'PENDING';

-- Một Shipper chỉ chạy một đơn tại một thời điểm. Cùng với
-- ux_order_single_active_assignment, database tự chặn mọi trường hợp nhận trùng.
CREATE UNIQUE INDEX ux_shipper_single_active_assignment ON order_assignments(shipper_id)
    WHERE assignment_status = 'ACTIVE';

-- Phục vụ giới hạn tần suất gửi GPS (đọc điểm mới nhất của từng Shipper).
CREATE INDEX ix_shipper_locations_shipper_time ON shipper_locations(shipper_id, recorded_at DESC);
COMMIT;
