# Kết quả kiểm tra API Customer — 05/11/2026

- `php tests/customer_orders.php`: PASS 84 checks. PostgreSQL TEMP tables, search_path pg_temp/pg_catalog, rollback toàn bộ. Bao gồm các release legacy và công thức mới, audit, giữ snapshot cũ, ví dụ 15/20/39 km và 1/5 kg cho VND/USD, số lẻ km/kg, làm tròn một lần, ranh giới 3 km/3 kg/40 km/30 kg.
- `php tests/pricing_provider.php`: PASS 23 checks, không gọi dịch vụ ngoài. Bao gồm TWO_WHEELER, 39,999 km được chấp nhận/40 km bị từ chối, đơn vị km/phút, timeout chung, commercial endpoint, geocoding TP.HCM/ngoài vùng, weather mapping/staleness và dừng nhận đơn khi thời tiết nguy hiểm.
- `node tests/pricing_browser.cjs`: PASS 44 checks, 0 runtime exceptions. Đổi VI/EN nhiều lần, toàn bộ nội dung Pricing/CTA/menu/footer modal, input trọng lượng, tính 4,5 km/3,5 kg/cao điểm/mưa nhẹ đúng 35.970đ và 1,77 USD, giới hạn 40 km và quá 30 kg, lưu ngôn ngữ sau reload và responsive 1440/768/375. Artifact `pricing-browser-results.json`; ảnh trong tests/.runtime.
- `node tests/customer_pricing.cjs`: PASS. Customer demo dùng chung hàm tính số nguyên BigInt, trọng lượng ảnh hưởng giá, làm tròn một lần, phí hoàn cố định và không dùng tỷ giá giả định.
- PHP lint: 87 file PHP ngoài demoUI và 23 JS assets, 0 lỗi cú pháp (bao gồm tests và migration runner).
- HTTP local PHP 8.0.30: 10/10 checks đạt. Sáu endpoint mới chặn chưa đăng nhập bằng 401; GET quote/cancel và PATCH addresses trả 405; POST khác origin trả 403.
- `node tests/reaudit_auth_frontend.cjs`: PASS.
- `php tests/request_origin.php`: 21 checks, 0 lỗi.
- `git diff --check`: PASS; Git chỉ cảnh báo chuẩn hóa LF/CRLF của README.

Các checks nghiệp vụ kiểm tra quyền sở hữu địa chỉ/đơn/quote, một default address, tọa độ/boolean sai, giá làm tròn chính xác kể cả vượt độ chính xác số JS, time band qua đêm, category bị cấm/hạn chế, provider cũ, policy thiếu/trùng time band, snapshot địa chỉ và giá bất biến, create replay/key conflict/quote dùng lại/quote hết hạn, currency theo tài khoản, keyset pagination, payment chưa xác nhận, callback lặp, cancel offer/history/refund event, tiền về sau hủy, assignment chặn hủy và category bị cấm sau quote.

Không tuyên bố đã kiểm chứng: provider thật, password login thật trong đợt này, browser nối API mới, concurrency nhiều process, Docker build hoặc deployment. API frontend chưa được nối. Integration event consumer/refund/payment provider/matching của các module khác chưa được triển khai.

Sau khi người dùng duyệt, đã áp dụng `20261105_customer_orders` và `20261105_approved_pricing` lên database cấu hình. Chạy runner lần hai thành công, không tạo thêm policy. Đọc lại xác nhận VND/USD version 1 active, 5 distance bands mỗi currency, max_distance_km=20, return fee 10.000đ/50 cent. Có audit release. Không tạo tài khoản, đơn, payment hoặc category thật.

Cập nhật mới nhất: đã áp dụng `20261105_service_range_40km` rồi `20261105_distance_weight_pricing` sau khi người dùng duyệt công thức và trọng lượng. VND/USD **version 3** active, rule BASE_PLUS_DISTANCE_PLUS_WEIGHT, max_distance=40 km exclusive, max_weight=30 kg inclusive, included_distance=3 km, included_weight=3 kg. Cơ bản VND/USD=20000/99, mỗi km=4000/20, mỗi kg=2500/10; return_fee giữ 10000/50. Đọc lại DB đúng tham số, có hai audit release mới; chạy lại runner không sinh policy lặp. Không chỉnh sửa đơn cũ. Cấu hình legacy được lưu cố định ở delivery-pricing-band-v1.php để migration cũ không phụ thuộc cấu hình hiện hành.

Người dùng chốt phạm vi TP. Hồ Chí Minh. GOOGLE_MAPS_API_KEY và OPEN_METEO_API_KEY chưa có trong process environment; danh mục active trong DB bằng 0. Vì vậy provider thật/báo giá và tạo đơn end-to-end chưa thể kiểm chứng. Không báo mock là giao dịch thật. Các biến runtime cần được cấu hình ở môi trường chạy PHP, xem runtime.env.example. Chưa push hoặc deploy hosting.
