BEGIN;
SET LOCAL search_path TO somi, public;

-- Insert missing defaults only. Preserve Admin restrictions, names and status.
INSERT INTO parcel_categories (category_code, name_vi, name_en, restriction_type, active) VALUES
    ('DOCUMENTS', 'Tài liệu, giấy tờ', 'Documents', 'ALLOWED', TRUE),
    ('BOOKS_STATIONERY', 'Sách và văn phòng phẩm', 'Books and stationery', 'ALLOWED', TRUE),
    ('CLOTHING_ACCESSORIES', 'Quần áo và phụ kiện', 'Clothing and accessories', 'ALLOWED', TRUE),
    ('PACKAGED_DRY_FOOD', 'Thực phẩm khô đóng gói', 'Packaged dry food', 'ALLOWED', TRUE),
    ('HOUSEHOLD_ITEMS', 'Đồ gia dụng nhỏ', 'Small household items', 'ALLOWED', TRUE),
    ('GIFTS', 'Quà tặng', 'Gifts', 'ALLOWED', TRUE)
ON CONFLICT (category_code) DO NOTHING;
COMMIT;
