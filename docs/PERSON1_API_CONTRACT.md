# API Người 1 — v1 additive

Ngày 2026-11-02. Endpoint dưới `public/api/`, giữ mô hình file PHP hiện có. Không đổi request/response của API auth cũ. API mới dùng `GET` đọc / `POST` ghi; không dùng POST giả cho GET. POST bắt buộc `Content-Type: application/json`, JSON object, same-origin; chỉ pricing cho phép object/array lồng nhau có validation riêng. GET không ghi dữ liệu nghiệp vụ (guard có cập nhật thời gian hoạt động session).

## Authentication / authorization

Cookie HttpOnly, SameSite=Lax, Secure theo origin tin cậy. Danh tính lấy từ server-side session, không nhận customer_id/actor_id từ body. `_person1.php::person1_identity` kiểm ACTIVE, hạn phiên 30 phút, timestamp phát hành, đổi mật khẩu và lần khóa tài khoản gần nhất; kiểm workspace hiện tại tồn tại trong user_roles và permission role_permissions trực tiếp từ PostgreSQL.

Tài khoản đa vai trò phải chọn đúng workspace. API hồ sơ áp dụng cho workspace hiện tại với permission profile.*. Không cấp quyền chỉ vì session chứa chữ admin. Không có endpoint Admin tạo tài khoản thủ công hoặc gán ADMIN.

## Response

Thành công: `{"data": ...}` hoặc `{"status":"ARCHIVED"}`. Tạo mới trả 201, đọc/sửa trả 200. Giá nháp trả thêm `activation: "BLOCKED_BY_BUSINESS_DECISION"`.

Lỗi mới: `{"error":"Thông điệp", "code":"VALIDATION_ERROR"}`; 401 chưa đăng nhập/phiên hết hạn, 403 sai workspace/permission/origin, 404 không tồn tại hoặc không thuộc chủ sở hữu, 405 sai method (Allow header), 415 sai content type, 422 validation, 409 xung đột trạng thái/dữ liệu hoặc thiếu chính sách đã thống nhất. 500 lỗi nội bộ dùng cơ chế error handler cũ, không gửi chi tiết SQL về client. Dạng error auth cũ vẫn là `{"error":"..."}` để tương thích.

BIGINT trả theo kiểu PDO/PostgreSQL có thể là số hoặc chuỗi; client phải chấp nhận cả hai, không tự đổi ID lớn thành float. Tiền lưu bằng số nguyên theo đơn vị nhỏ nhất: VND đồng, USD cent. Draft API giới hạn số tiền tới 2^53−1 để trao đổi JSON an toàn với JavaScript. NUMERIC trả chuỗi; timestamp TIMESTAMPTZ có offset. JSONB của order hiện trả theo kiểu PDO (chuỗi JSON), chưa đổi hợp đồng sang object ở endpoint legacy chưa tồn tại.

## Danh sách endpoint

| Endpoint | Method | Quyền | Request / kết quả |
|---|---|---|---|
| auth/profile.php | GET | profile.read_own | data: user_id, full_name, email, phone, nationality_code, date_of_birth, gender, app_language, account_status, verified timestamps, roles; không hash/ciphertext |
| auth/profile.php | POST | profile.update_own | full_name (1–150), app_language vi/en; từ chối field ngoài danh sách. Không đổi email/phone/quốc tịch/quyền qua đây |
| auth/nationality-request.php | GET | profile.update_own | tối đa 100 yêu cầu của chính user |
| auth/nationality-request.php | POST | profile.update_own | requested_country_code từ resources/countries.json, reason 1–1000; 201 request_id/status; mỗi user tối đa 1 pending |
| customer/addresses.php | GET | CUSTOMER, address.manage_own | data: địa chỉ chưa archived của chính user |
| customer/addresses.php | POST | như GET | action=save; address_id optional; label≤60, address_detail 1–255, province/district/ward≤120, latitude [-90,90], longitude [-180,180], is_default boolean. Country hiện VN theo schema |
| customer/addresses.php | POST | như GET | action=archive, address_id. 409 ADDRESS_IN_USE khi địa chỉ đã dùng; soft archive, không xóa snapshot |
| customer/categories.php | GET | CUSTOMER, order.create | active categories, gồm restriction_type để UI biết hạn chế; không được hiểu ALLOWED là đủ điều kiện tạo đơn khi giá chưa sẵn sàng |
| customer/orders.php | GET | CUSTOMER, order.read_own | optional order_id; không có ID trả tối đa 100 đơn mới nhất; before cursor exclusive order_id. Chi tiết có history |
| customer/orders.php | POST | CUSTOMER, order.cancel_before_assignment | action=cancel, order_id, reason 1–1000. 200 order hoặc 409 ORDER_STATE_CONFLICT / PAYMENT_INTEGRATION_REQUIRED |
| customer/quote.php | POST | CUSTOMER, order.create | 409 BLOCKED_BY_BUSINESS_DECISION. Chưa có hợp đồng báo giá thực thi |
| customer/create-order.php | POST | như quote | 409 BLOCKED_BY_BUSINESS_DECISION. Không nhận tổng giá từ trình duyệt để tạo đơn thật |
| admin/accounts.php | GET | ADMIN, account.read_all | q≤150, role CUSTOMER/SHIPPER optional, before optional; tối đa 100. q so khớp tên/email; ký tự LIKE escape |
| admin/accounts.php | POST | ADMIN, account.suspend | user_id, account_status ACTIVE/SUSPENDED, reason 1–1000. Chỉ chuyển ACTIVE↔SUSPENDED. Không tự khóa, không đổi quyền truy cập tài khoản Admin; không kích hoạt pending/closed |
| admin/orders.php | GET | ADMIN, order.read_all | order_id optional, before optional; danh sách 100 hoặc chi tiết + history; không đổi giá/trạng thái |
| admin/categories.php | GET / POST | ADMIN, pricing.manage | GET tất cả; POST category_id, restriction_type ALLOWED/RESTRICTED/PROHIBITED, active boolean; audit trong cùng transaction |
| admin/pricing.php | GET / POST | ADMIN, pricing.manage | GET 100 bản nháp; POST currency_code VND/USD và configuration object; không kích hoạt bảng giá |
| admin/nationality-requests.php | GET / POST | ADMIN, nationality.review | GET 100 pending; POST request_id, decision APPROVED/REJECTED, note. REJECTED có audit; APPROVED trả 409 BLOCKED_BY_BUSINESS_DECISION khi chưa có quy trình thay giấy tờ; không tự duyệt yêu cầu mình |
| admin/review-shipper.php | POST | ADMIN, shipper.verify | shipper_id, decision APPROVED/REJECTED/NEEDS_INFO; note bắt buộc khi không approve, tối đa1000. PENDING/NEEDS_INFO mới được xét; thiếu identity hoặc stale trả409; khóa user→profile→identity, cập nhật identity/profile và audit nguyên tử |

## Ví dụ địa chỉ

```json
{"action":"save","label":"Nhà","address_detail":"Địa chỉ người dùng nhập","province":"","district":"","ward":"","latitude":10.25,"longitude":106.25,"is_default":true}
```

Đây là cấu trúc request, không phải seed dữ liệu vào production. API địa chỉ hiện chưa lưu tên/số điện thoại liên hệ riêng vì schema chưa có cột này; luồng tạo đơn phải snapshot sender/receiver riêng khi được triển khai.

## Cấu hình giá nháp

Các key cho phép (không key nào tự có giá mặc định):

- distance_bands: tối đa100 entry min_km, max_km (null khi mở), amount_minor nguyên không âm.
- time_bands: day_of_week null hoặc0–6; starts_at_local/ends_at_local HH:MM khác nhau; multiplier không âm.
- weather_bands: provider_condition_code, multiplier không âm.
- cancellation_rules: after_status thuộc trạng thái đã liệt kê trong validation, fee_minor nguyên không âm.
- return_fee_minor, failed_return_fixed_minor; successful_delivery_percentage 0–100.
- score_policy: đủ deduction_points, lock_threshold, recovery_points không âm.

Draft không chứng nhận khoảng giá đã đầy đủ, không chồng lấn hoặc công thức được thống nhất. Chưa có activation endpoint. Trước activation phải chốt cộng/nhân hệ số, ranh giới khoảng, khung qua ngày, timezone chính sách, provider distance/weather, làm tròn từng bước hay cuối và cách chọn currency. Không tự dùng giá demo trong store.js.

## Auth hiện hữu

Giữ các endpoint register/login/logout/verify-phone/verify-email-code/resend-phone-code/send-email-code/request-password-reset/complete-password-reset/switch-workspace. Login dùng identifier, password, role; switch-workspace dùng target, không phải role. Reset request trả cùng status cho email tồn tại/không tồn tại kể cả lỗi nhà cung cấp; chỉ xác nhận tiếp nhận, không chứng minh email đã được gửi. Chưa loại trừ hoàn toàn timing enumeration; chưa có shared rate limiter được thống nhất.
