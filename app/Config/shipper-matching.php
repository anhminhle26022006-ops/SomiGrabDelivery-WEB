<?php
declare(strict_types=1);

/**
 * Tham số vận hành cho ghép đơn, GPS và bằng chứng giao nhận (Người 2).
 * Đây là giá trị kỹ thuật mặc định, có thể đổi mà không cần sửa service.
 * Chính sách tiền, điểm và thu nhập KHÔNG nằm ở đây.
 */
return [
    // Offer hết hạn sau bao lâu. Database giới hạn tối đa 2 phút (CHECK của order_match_offers).
    'offer_ttl_seconds' => 60,
    // Mỗi vòng gửi offer cho tối đa bao nhiêu Shipper gần nhất.
    'batch_size' => 3,
    // Số vòng gửi offer tối đa trước khi báo không tìm thấy Shipper.
    'max_rounds' => 3,
    // Bán kính tìm Shipper quanh điểm lấy hàng.
    'radius_km' => 5.0,
    // Tổng thời gian tìm kiếm tối đa kể từ lúc đơn vào SEARCHING_SHIPPER.
    'search_timeout_seconds' => 300,
    // Vị trí của Shipper cũ hơn mức này thì không được dùng để ghép đơn.
    'location_max_age_seconds' => 600,
    // Khoảng cách tối thiểu giữa hai lần gửi GPS của cùng một Shipper.
    'gps_min_interval_seconds' => 5,
    // GPS: 7 days. Evidence: 90 days after order closure, shared with Storage retention.
    'gps_retention_days' => 7,
    'evidence_retention_days' => 90,
];
