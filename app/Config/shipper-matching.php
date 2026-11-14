<?php
declare(strict_types=1);

/**
 * Tham số vận hành cho ghép đơn, GPS và bằng chứng giao nhận (Người 2).
 * Đây là giá trị kỹ thuật mặc định, có thể đổi mà không cần sửa service.
 * Chính sách tiền, điểm và thu nhập KHÔNG nằm ở đây.
 */
return [
    // No per-invitation TTL; the customer order has one five-minute matching window.
    'search_timeout_seconds' => 300,
    // Bán kính tìm Shipper quanh điểm lấy hàng.
    'radius_km' => 5.0,
    // Vị trí của Shipper cũ hơn mức này thì không được dùng để ghép đơn.
    'location_max_age_seconds' => 600,
    // Khoảng cách tối thiểu giữa hai lần gửi GPS của cùng một Shipper.
    'gps_min_interval_seconds' => 5,
    // GPS: 7 days. Evidence: 90 days after order closure, shared with Storage retention.
    'gps_retention_days' => 7,
    'evidence_retention_days' => 90,
];
