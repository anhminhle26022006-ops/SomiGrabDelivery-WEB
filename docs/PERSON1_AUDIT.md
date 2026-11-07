# Person 1 — Audit ban đầu

> Historical snapshot. Current integration, approved pricing, SePay/payment/email APIs and ownership: [PERSON1-API.md](PERSON1-API.md), [BACKEND-SCOPE.md](BACKEND-SCOPE.md).


Ngày 2026-11-02. Nhánh dev. 68 task; baseline trước implementation đợt này.

## Hiện trạng

PHP 8.4.27 local; Docker PHP 8.3 Apache; PDO PostgreSQL/Supabase, PHP Session, vanilla JS, Bootstrap Customer. Không package manager/build frontend. DB kết nối thành công và bảng users tồn tại (đã kiểm tra đọc). Secret Windows User environment, không in giá trị. Chưa có migration runner. Không có AGENTS.md tìm thấy trong repository.

Các thay đổi navigation/ngôn ngữ từ yêu cầu trước đang uncommitted và được giữ nguyên. Không reset/ghi đè. Phạm vi lần này theo Người 1; không triển khai matching, delivery, payment/refund của Người 2/3. TEAM-OWNERSHIP.md cũ chia theo UI owner; yêu cầu hiện tại cho phép nền tảng/Customer/Admin. Không sửa module Shipper trừ API review được giao.

API thực: auth và admin/review-shipper.php. Customer store.js lưu localStorage; Admin chủ yếu sample rows. Không coi view, DDL hoặc báo cáo cũ là bằng chứng DONE. Schema order bắt buộc en→USD, vi→VND, trong khi Customer chọn tiền tệ độc lập; trạng thái PENDING/MATCHING/ASSIGNED/COMPLETED khác DB. Không tự nới constraint khi chưa chốt hợp đồng.

## Bằng chứng baseline

- node tests/reaudit_auth_frontend.cjs: PASS.
- php tests/request_origin.php: 21 checks, 0 failures.
- DB connection + existence somi.users: PASS read-only trong phiên này.
- Các báo cáo reaudit cũ chỉ tham khảo, không tái sử dụng PASS cho triển khai mới.

## Ma trận

| ID | Công việc | Trạng thái | File/API liên quan | Bằng chứng | Vấn đề cần xử lý | Mức ưu tiên |
|---|---|---|---|---|---|---|
| A-01 | Kiến trúc backend phân tách route/API, service nghiệp vụ và data access. | PARTIAL | public/api/_bootstrap.php; app/Config/database.php; app/Services/SessionGuard.php | Chưa có API/service nghiệp vụ tương ứng. | Xem PERSON1_PROGRESS.md | P1 |
| A-02 | Validation đầu vào và chuẩn hóa API response/error. | PARTIAL | public/api/_bootstrap.php; app/Config/database.php; app/Services/SessionGuard.php | Chưa có API/service nghiệp vụ tương ứng. | Xem PERSON1_PROGRESS.md | P1 |
| A-03 | Cấu hình PostgreSQL theo môi trường. | DONE | app/Config/database.php::somi_db | Kết nối Supabase thành công bằng environment, kiểm somi.users trước đợt này. | Xem PERSON1_PROGRESS.md | P1 |
| A-04 | Không hardcode mật khẩu hoặc secret. | NOT_VERIFIED | public/api/_bootstrap.php; app/Config/database.php; app/Services/SessionGuard.php | DB lấy secret từ environment; cần scan toàn repository. | Xem PERSON1_PROGRESS.md | P1 |
| A-05 | Migrations nền đầy đủ, có thể chạy trên database sạch. | NOT_VERIFIED | database/SOMI_database.sql; database/migrations/ | Có hai baseline SQL; chưa chạy database sạch. | Xem PERSON1_PROGRESS.md | P0 |
| A-06 | Quy ước thống nhất về tên bảng, khóa ngoại, trạng thái, timestamp, timezone và tiền tệ. | PARTIAL | public/api/_bootstrap.php; app/Config/database.php; app/Services/SessionGuard.php | Chưa có API/service nghiệp vụ tương ứng. | Xem PERSON1_PROGRESS.md | P1 |
| A-07 | Có `.env.example` và tài liệu chạy backend. | MISSING | .env.example; README.md | Chưa có .env.example hoặc hướng dẫn migration/backend phù hợp. | Xem PERSON1_PROGRESS.md | P1 |
| A-08 | Middleware authentication. | BUG | app/Services/SessionGuard.php | Chỉ xét account_status hiện tại; phiên cũ có thể sống lại sau suspend→restore; strtotime bỏ phần giây lẻ. | Xem PERSON1_PROGRESS.md | P1 |
| A-09 | Middleware authorization theo role/permission. | PARTIAL | public/api/_bootstrap.php; app/Config/database.php; app/Services/SessionGuard.php | Chưa có API/service nghiệp vụ tương ứng. | Xem PERSON1_PROGRESS.md | P0 |
| A-10 | Lấy chính xác danh tính người dùng từ session/token. | NOT_VERIFIED | app/Services/SessionGuard.php; public/api/auth/ | Chưa kiểm thử identity/role giả trên database cô lập. | Xem PERSON1_PROGRESS.md | P0 |
| B-01 | Đăng ký bằng email/số điện thoại. | PARTIAL | public/api/auth/; app/Services/*VerificationService.php; PasswordResetService.php | Có implementation; chưa chạy E2E test độc lập. SMS chỉ có demo mode. | Xem PERSON1_PROGRESS.md | P1 |
| B-02 | Đăng nhập bằng email/số điện thoại. | PARTIAL | public/api/auth/; app/Services/*VerificationService.php; PasswordResetService.php | Có implementation; chưa chạy E2E test độc lập. SMS chỉ có demo mode. | Xem PERSON1_PROGRESS.md | P1 |
| B-03 | Đăng xuất và vô hiệu hóa session/token theo cơ chế thực tế. | PARTIAL | public/api/auth/; app/Services/*VerificationService.php; PasswordResetService.php | Có implementation; chưa chạy E2E test độc lập. SMS chỉ có demo mode. | Xem PERSON1_PROGRESS.md | P1 |
| B-04 | Xác minh email/điện thoại. | PARTIAL | public/api/auth/; app/Services/*VerificationService.php; PasswordResetService.php | Có implementation; chưa chạy E2E test độc lập. SMS chỉ có demo mode. | Xem PERSON1_PROGRESS.md | P1 |
| B-05 | Gửi lại mã xác minh. | PARTIAL | public/api/auth/; app/Services/*VerificationService.php; PasswordResetService.php | Có implementation; chưa chạy E2E test độc lập. SMS chỉ có demo mode. | Xem PERSON1_PROGRESS.md | P1 |
| B-06 | Quên mật khẩu. | BUG | public/api/auth/request-password-reset.php | Error provider/config trả500 cho email thật, email không tồn tại trả200; khác biệt response làm lộ sự tồn tại tài khoản. | Xem PERSON1_PROGRESS.md | P1 |
| B-07 | Đặt lại mật khẩu an toàn. | BUG | app/Services/PasswordResetService.php::reset | Khóa token trước user; hai token khác nhau cho một user có thể tạo vòng chờ khi consume tất cả token. | Xem PERSON1_PROGRESS.md | P1 |
| B-08 | API hồ sơ người dùng. | MISSING | public/api/ | Chưa có API/service nghiệp vụ tương ứng. | Xem PERSON1_PROGRESS.md | P1 |
| B-09 | Một tài khoản có thể có đồng thời role Customer và Shipper. | NOT_VERIFIED | public/api/auth/switch-workspace.php | Chưa có API/service nghiệp vụ tương ứng. | Xem PERSON1_PROGRESS.md | P1 |
| B-10 | Kiểm tra quyền sở hữu dữ liệu ở backend. | MISSING | public/api/ | Chưa có API/service nghiệp vụ tương ứng. | Xem PERSON1_PROGRESS.md | P1 |
| B-11 | Shipper không truy cập được offer/đơn ngoài phạm vi được phép. | BLOCKED | public/api/ | API offer chưa tồn tại; phối hợp Người 2. | Xem PERSON1_PROGRESS.md | P1 |
| B-12 | Admin xem, khóa/mở tài khoản. | MISSING | public/api/ | Chưa có API/service nghiệp vụ tương ứng. | Xem PERSON1_PROGRESS.md | P1 |
| B-13 | Admin xử lý yêu cầu đổi quốc tịch. | MISSING | public/api/ | Chưa có API/service nghiệp vụ tương ứng. | Xem PERSON1_PROGRESS.md | P1 |
| B-14 | Ghi lịch sử trạng thái tài khoản. | MISSING | public/api/ | Chưa có API/service nghiệp vụ tương ứng. | Xem PERSON1_PROGRESS.md | P1 |
| B-15 | Audit log cho thao tác Admin nhạy cảm. | MISSING | public/api/ | Chưa có API/service nghiệp vụ tương ứng. | Xem PERSON1_PROGRESS.md | P1 |
| C-01 | CRUD địa chỉ Customer. | MISSING | public/assets/js/customer/store.js; database/SOMI_database.sql | Frontend localStorage, không có API PostgreSQL; chưa kiểm thử persistence/ownership. | Xem PERSON1_PROGRESS.md | P1 |
| C-02 | Phân quyền sở hữu địa chỉ. | MISSING | public/assets/js/customer/store.js; database/SOMI_database.sql | Frontend localStorage, không có API PostgreSQL; chưa kiểm thử persistence/ownership. | Xem PERSON1_PROGRESS.md | P1 |
| C-03 | Danh mục loại kiện hàng. | MISSING | public/assets/js/customer/store.js; database/SOMI_database.sql | Frontend localStorage, không có API PostgreSQL; chưa kiểm thử persistence/ownership. | Xem PERSON1_PROGRESS.md | P1 |
| C-04 | Admin đánh dấu loại hàng được phép/hạn chế/cấm. | MISSING | public/assets/js/customer/store.js; database/SOMI_database.sql | Frontend localStorage, không có API PostgreSQL; chưa kiểm thử persistence/ownership. | Xem PERSON1_PROGRESS.md | P1 |
| C-05 | Tạo đơn với địa chỉ lấy/giao. | MISSING | public/assets/js/customer/store.js; database/SOMI_database.sql | Frontend localStorage, không có API PostgreSQL; chưa kiểm thử persistence/ownership. | Xem PERSON1_PROGRESS.md | P0 |
| C-06 | Lưu tọa độ hợp lệ. | MISSING | public/assets/js/customer/store.js; database/SOMI_database.sql | Frontend localStorage, không có API PostgreSQL; chưa kiểm thử persistence/ownership. | Xem PERSON1_PROGRESS.md | P1 |
| C-07 | Lưu thông tin liên hệ người gửi/người nhận. | MISSING | public/assets/js/customer/store.js; database/SOMI_database.sql | Frontend localStorage, không có API PostgreSQL; chưa kiểm thử persistence/ownership. | Xem PERSON1_PROGRESS.md | P1 |
| C-08 | Lưu loại hàng, khối lượng. | MISSING | public/assets/js/customer/store.js; database/SOMI_database.sql | Frontend localStorage, không có API PostgreSQL; chưa kiểm thử persistence/ownership. | Xem PERSON1_PROGRESS.md | P1 |
| C-09 | Snapshot địa chỉ/liên hệ tại thời điểm tạo đơn. | MISSING | public/assets/js/customer/store.js; database/SOMI_database.sql | Frontend localStorage, không có API PostgreSQL; chưa kiểm thử persistence/ownership. | Xem PERSON1_PROGRESS.md | P1 |
| C-10 | Customer xem danh sách đơn. | MISSING | public/assets/js/customer/store.js; database/SOMI_database.sql | Frontend localStorage, không có API PostgreSQL; chưa kiểm thử persistence/ownership. | Xem PERSON1_PROGRESS.md | P1 |
| C-11 | Customer xem chi tiết đơn của mình. | MISSING | public/assets/js/customer/store.js; database/SOMI_database.sql | Frontend localStorage, không có API PostgreSQL; chưa kiểm thử persistence/ownership. | Xem PERSON1_PROGRESS.md | P1 |
| C-12 | Customer xem trạng thái và lịch sử đơn. | MISSING | public/assets/js/customer/store.js; database/SOMI_database.sql | Frontend localStorage, không có API PostgreSQL; chưa kiểm thử persistence/ownership. | Xem PERSON1_PROGRESS.md | P1 |
| C-13 | Customer được hủy đơn trước khi Shipper nhận theo quy tắc nghiệp vụ. | MISSING | public/assets/js/customer/store.js; database/SOMI_database.sql | Frontend localStorage, không có API PostgreSQL; chưa kiểm thử persistence/ownership. | Xem PERSON1_PROGRESS.md | P1 |
| C-14 | Có hợp đồng trạng thái đơn dùng chung với Người 2. | MISSING | public/assets/js/customer/store.js; database/SOMI_database.sql | Frontend localStorage, không có API PostgreSQL; chưa kiểm thử persistence/ownership. | Xem PERSON1_PROGRESS.md | P1 |
| C-15 | Lưu lịch sử chuyển trạng thái. | MISSING | public/assets/js/customer/store.js; database/SOMI_database.sql | Frontend localStorage, không có API PostgreSQL; chưa kiểm thử persistence/ownership. | Xem PERSON1_PROGRESS.md | P0 |
| C-16 | Ngăn chuyển trạng thái không hợp lệ và xử lý cập nhật đồng thời. | MISSING | public/assets/js/customer/store.js; database/SOMI_database.sql | Frontend localStorage, không có API PostgreSQL; chưa kiểm thử persistence/ownership. | Xem PERSON1_PROGRESS.md | P0 |
| D-01 | Admin cấu hình giá theo khoảng cách. | BLOCKED | database/SOMI_database.sql:283; public/assets/js/customer/store.js:34 | BLOCKED_BY_BUSINESS_DECISION: demo có giá mẫu; chưa có phê duyệt công thức, làm tròn theo bước, nguồn khoảng cách/thời tiết. | Xem PERSON1_PROGRESS.md | P1 |
| D-02 | Admin cấu hình giá theo khung giờ. | BLOCKED | database/SOMI_database.sql:283; public/assets/js/customer/store.js:34 | BLOCKED_BY_BUSINESS_DECISION: demo có giá mẫu; chưa có phê duyệt công thức, làm tròn theo bước, nguồn khoảng cách/thời tiết. | Xem PERSON1_PROGRESS.md | P1 |
| D-03 | Admin cấu hình ảnh hưởng thời tiết. | BLOCKED | database/SOMI_database.sql:283; public/assets/js/customer/store.js:34 | BLOCKED_BY_BUSINESS_DECISION: demo có giá mẫu; chưa có phê duyệt công thức, làm tròn theo bước, nguồn khoảng cách/thời tiết. | Xem PERSON1_PROGRESS.md | P1 |
| D-04 | Bảng giá VND và USD riêng. | BLOCKED | database/SOMI_database.sql:283; public/assets/js/customer/store.js:34 | BLOCKED_BY_BUSINESS_DECISION: demo có giá mẫu; chưa có phê duyệt công thức, làm tròn theo bước, nguồn khoảng cách/thời tiết. | Xem PERSON1_PROGRESS.md | P1 |
| D-05 | Tính báo giá theo chính sách đã thống nhất. | BLOCKED | database/SOMI_database.sql:283; public/assets/js/customer/store.js:34 | BLOCKED_BY_BUSINESS_DECISION: demo có giá mẫu; chưa có phê duyệt công thức, làm tròn theo bước, nguồn khoảng cách/thời tiết. | Xem PERSON1_PROGRESS.md | P1 |
| D-06 | Làm tròn lên đơn vị tiền nhỏ nhất theo từng loại tiền. | BLOCKED | database/SOMI_database.sql:283; public/assets/js/customer/store.js:34 | BLOCKED_BY_BUSINESS_DECISION: demo có giá mẫu; chưa có phê duyệt công thức, làm tròn theo bước, nguồn khoảng cách/thời tiết. | Xem PERSON1_PROGRESS.md | P1 |
| D-07 | Snapshot chính sách giá lên đơn. | BLOCKED | database/SOMI_database.sql:283; public/assets/js/customer/store.js:34 | BLOCKED_BY_BUSINESS_DECISION: demo có giá mẫu; chưa có phê duyệt công thức, làm tròn theo bước, nguồn khoảng cách/thời tiết. | Xem PERSON1_PROGRESS.md | P1 |
| D-08 | Snapshot các thành phần tạo thành tổng giá. | BLOCKED | database/SOMI_database.sql:283; public/assets/js/customer/store.js:34 | BLOCKED_BY_BUSINESS_DECISION: demo có giá mẫu; chưa có phê duyệt công thức, làm tròn theo bước, nguồn khoảng cách/thời tiết. | Xem PERSON1_PROGRESS.md | P1 |
| D-09 | Không thay đổi giá đơn đã thanh toán. | BLOCKED | database/SOMI_database.sql:283; public/assets/js/customer/store.js:34 | BLOCKED_BY_BUSINESS_DECISION: demo có giá mẫu; chưa có phê duyệt công thức, làm tròn theo bước, nguồn khoảng cách/thời tiết. | Xem PERSON1_PROGRESS.md | P1 |
| D-10 | Admin cấu hình phí hủy. | BLOCKED | database/SOMI_database.sql:283; public/assets/js/customer/store.js:34 | BLOCKED_BY_BUSINESS_DECISION: demo có giá mẫu; chưa có phê duyệt công thức, làm tròn theo bước, nguồn khoảng cách/thời tiết. | Xem PERSON1_PROGRESS.md | P1 |
| D-11 | Admin cấu hình phí hoàn hàng. | BLOCKED | database/SOMI_database.sql:283; public/assets/js/customer/store.js:34 | BLOCKED_BY_BUSINESS_DECISION: demo có giá mẫu; chưa có phê duyệt công thức, làm tròn theo bước, nguồn khoảng cách/thời tiết. | Xem PERSON1_PROGRESS.md | P1 |
| D-12 | Admin cấu hình phần trăm thu nhập Shipper. | BLOCKED | database/SOMI_database.sql:283; public/assets/js/customer/store.js:34 | BLOCKED_BY_BUSINESS_DECISION: demo có giá mẫu; chưa có phê duyệt công thức, làm tròn theo bước, nguồn khoảng cách/thời tiết. | Xem PERSON1_PROGRESS.md | P1 |
| D-13 | Admin cấu hình bồi hoàn đơn hoàn. | BLOCKED | database/SOMI_database.sql:283; public/assets/js/customer/store.js:34 | BLOCKED_BY_BUSINESS_DECISION: demo có giá mẫu; chưa có phê duyệt công thức, làm tròn theo bước, nguồn khoảng cách/thời tiết. | Xem PERSON1_PROGRESS.md | P1 |
| D-14 | Admin cấu hình chính sách điểm Shipper. | BLOCKED | database/SOMI_database.sql:283; public/assets/js/customer/store.js:34 | BLOCKED_BY_BUSINESS_DECISION: demo có giá mẫu; chưa có phê duyệt công thức, làm tròn theo bước, nguồn khoảng cách/thời tiết. | Xem PERSON1_PROGRESS.md | P1 |
| E-01 | API Admin tra cứu Customer. | MISSING | public/api/ | Chưa có API/service nghiệp vụ tương ứng. | Xem PERSON1_PROGRESS.md | P1 |
| E-02 | API Admin tra cứu Shipper. | MISSING | public/api/ | Chưa có API/service nghiệp vụ tương ứng. | Xem PERSON1_PROGRESS.md | P1 |
| E-03 | API Admin tra cứu và xem chi tiết đơn hàng. | MISSING | public/api/ | Chưa có API/service nghiệp vụ tương ứng. | Xem PERSON1_PROGRESS.md | P1 |
| E-04 | Admin duyệt hồ sơ KYC. | BUG | public/api/admin/review-shipper.php | Approve profile không kiểm tra identity tồn tại; UPDATE identity có thể ảnh hưởng0 dòng nhưng profile vẫn APPROVED. | Xem PERSON1_PROGRESS.md | P1 |
| E-05 | Admin từ chối KYC. | PARTIAL | public/api/admin/review-shipper.php | Có approve/reject Shipper; thiếu NEEDS_INFO; guard lặp; chưa E2E. | Xem PERSON1_PROGRESS.md | P1 |
| E-06 | Admin yêu cầu bổ sung hồ sơ. | MISSING | public/api/admin/review-shipper.php | Endpoint cũ chỉ nhận APPROVED/REJECTED, không nhận NEEDS_INFO. | Xem PERSON1_PROGRESS.md | P1 |
| E-07 | Lưu trạng thái và lịch sử xét duyệt KYC. | PARTIAL | public/api/admin/review-shipper.php | Có approve/reject Shipper; thiếu NEEDS_INFO; guard lặp; chưa E2E. | Xem PERSON1_PROGRESS.md | P1 |
| E-08 | Hợp đồng API cho matching với Người 2. | MISSING | public/api/ | Chưa có API/service nghiệp vụ tương ứng. | Xem PERSON1_PROGRESS.md | P1 |
| E-09 | Hợp đồng API cho trạng thái giao hàng với Người 2. | MISSING | public/api/ | Chưa có API/service nghiệp vụ tương ứng. | Xem PERSON1_PROGRESS.md | P1 |
| E-10 | Hợp đồng API thanh toán/refund với Người 3. | MISSING | public/api/ | Chưa có API/service nghiệp vụ tương ứng. | Xem PERSON1_PROGRESS.md | P1 |
| E-11 | Review migrations và khả năng tích hợp. | NOT_VERIFIED | database/SOMI_database.sql; database/migrations/ | Chưa chạy lại migration trên database sạch trong phiên audit mới. | Xem PERSON1_PROGRESS.md | P1 |
| E-12 | Chuẩn hóa authentication/authorization giữa các module. | PARTIAL | app/Services/SessionGuard.php; public/api/ | Guard active dùng chung nhưng có code gọi lặp; thiếu middleware permission dùng lại cho API nghiệp vụ. | Xem PERSON1_PROGRESS.md | P0 |
| E-13 | Tài liệu API và hướng dẫn tích hợp. | MISSING | public/api/ | Chưa có API/service nghiệp vụ tương ứng. | Xem PERSON1_PROGRESS.md | P1 |

## Thống kê ban đầu

DONE: 1; PARTIAL: 12; MISSING: 31; BUG: 4; BLOCKED: 15; NOT_VERIFIED: 5

DONE / tổng: 1/68 = 1.47%. Các BUG có bằng chứng source/lock-order được ghi bổ sung sau khảo sát; đây vẫn là trạng thái source trước sửa. Báo cáo cũ không tính vào DONE.
