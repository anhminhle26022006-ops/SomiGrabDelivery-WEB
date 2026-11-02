# Người 1 — Tiến độ sau triển khai

Ngày 2026-11-02. Nhánh dev. Không commit/push/merge/deploy. Giữ nguyên thay đổi navigation/ngôn ngữ trước đó.

## Mức hoàn thành

Tổng 68 task trong prompt (bao gồm các điểm phối hợp với Người2/3). DONE chỉ khi có implementation và bằng chứng test; PARTIAL/BLOCKED/NOT_VERIFIED không cộng vào hoàn thành.

| Trạng thái | Trước | Sau |
|---|---:|---:|
| DONE | 1 | 32 |
| PARTIAL | 12 | 28 |
| MISSING | 31 | 0 |
| BUG | 4 | 0 |
| BLOCKED | 15 | 8 |
| NOT_VERIFIED | 5 | 0 |

Tỷ lệ DONE: 1/68 (1.47%) → 32/68 (47.06%). Đây không phải tỷ lệ sẵn sàng production.

## Kết quả từng task

| ID | Công việc | Trạng thái | File/API liên quan | Bằng chứng | Vấn đề còn lại | Ưu tiên |
|---|---|---|---|---|---|---|
| A-01 | Kiến trúc backend phân tách route/API, service nghiệp vụ và data access. | PARTIAL | public/api/_person1.php; app/Repositories/Person1Repository.php; app/Services/CustomerService.php; app/Services/AdminService.php | Routes/service/data access tách cho API mới; service/HTTP tests PASS. | Auth cũ vẫn có SQL trong endpoint; không refactor hàng loạt đang hoạt động. | P1 |
| A-02 | Validation đầu vào và chuẩn hóa API response/error. | PARTIAL | app/Services/Input.php; app/Services/ApiProblem.php; public/api/_person1.php | Validation type/ID/coordinates/money, error code/status cho API mới; tests PASS. | Error auth cũ giữ tương thích; body-size limit và shared rate limiter chưa triển khai. | P1 |
| A-03 | Cấu hình PostgreSQL theo môi trường. | DONE | app/Config/database.php::somi_db; scripts/serve.ps1 | Supabase read-only đủ23 bảng; PostgreSQL16 test kết nối/migration PASS. | — | P1 |
| A-04 | Không hardcode mật khẩu hoặc secret. | DONE | .env.example; .gitignore; app/Config/database.php | Environment-only credentials; scan154 file text không phát hiện pattern secret; .runtime/env ignored. | Scan heuristic không bảo đảm phát hiện mọi dạng secret hoặc secret ngoài repository. | P1 |
| A-05 | Migrations nền đầy đủ, có thể chạy trên database sạch. | DONE | scripts/migrate.php; database/SOMI_database.sql; database/migrations/20261102_person1.sql | Baseline sạch PostgreSQL16 PASS, rerun checksum ledger/advisory lock skip đúng. | Chưa apply lên Supabase; chưa build image PHP8.3. | P0 |
| A-06 | Quy ước thống nhất về tên bảng, khóa ngoại, trạng thái, timestamp, timezone và tiền tệ. | PARTIAL | database/SOMI_database.sql; app/Services/OrderContract.php; docs/PERSON1_INTEGRATION.md | Giữ snake_case, FK, TIMESTAMPTZ, integer minor money; vocabulary DB thống nhất cho API mới. | Language/currency CHECK xung đột demo và graph giao hàng cần chốt với Người2/3. | P1 |
| A-07 | Có `.env.example` và tài liệu chạy backend. | DONE | .env.example; scripts/serve.ps1; docs/PERSON1_BACKEND.md | Có hướng dẫn env/extension/migration và scripts kiểm thử thực chạy PASS. | — | P1 |
| A-08 | Middleware authentication. | DONE | app/Services/SessionGuard.php; public/api/_person1.php::person1_identity | HTTP login/logout/suspend-restore; expired/missing timestamp/forged role/microsecond reset assertions PASS. | — | P1 |
| A-09 | Middleware authorization theo role/permission. | DONE | public/api/_person1.php::person1_identity; database/migrations/20261102_person1.sql | Vai trò session đối chiếu DB + permission; wrong-workspace và DB permission revocation assertions PASS. | API Người2/3 chưa tồn tại; chưa chứng minh chúng dùng middleware này. | P0 |
| A-10 | Lấy chính xác danh tính người dùng từ session/token. | DONE | app/Services/SessionGuard.php; public/api/_person1.php::person1_identity | HTTP login/logout/suspend-restore; expired/missing timestamp/forged role/microsecond reset assertions PASS. | — | P0 |
| B-01 | Đăng ký bằng email/số điện thoại. | PARTIAL | public/api/auth/register.php; app/Services/PhoneVerificationService.php; EmailVerificationService.php | Verification timestamp/activation, wrong-attempt lock và replay tests PostgreSQL PASS. | Chưa kiểm thử registration→SMS/email thật. SMS chỉ demo; delivery provider/Resend config/key cần riêng. | P1 |
| B-02 | Đăng nhập bằng email/số điện thoại. | DONE | public/api/auth/login.php | HTTP login thật email4 workspace và phone; wrong password/Customer-as-Admin401 PASS. Lock user khi kiểm mật khẩu để phối hợp reset/suspend. | — | P1 |
| B-03 | Đăng xuất và vô hiệu hóa session/token theo cơ chế thực tế. | DONE | public/api/auth/logout.php; app/Services/SessionGuard.php | HTTP logout xóa cookie/session; request tiếp theo401 PASS. | — | P1 |
| B-04 | Xác minh email/điện thoại. | PARTIAL | public/api/auth/register.php; app/Services/PhoneVerificationService.php; EmailVerificationService.php | Verification timestamp/activation, wrong-attempt lock và replay tests PostgreSQL PASS. | Chưa kiểm thử registration→SMS/email thật. SMS chỉ demo; delivery provider/Resend config/key cần riêng. | P1 |
| B-05 | Gửi lại mã xác minh. | PARTIAL | public/api/auth/register.php; app/Services/PhoneVerificationService.php; EmailVerificationService.php | Verification timestamp/activation, wrong-attempt lock và replay tests PostgreSQL PASS. | Chưa kiểm thử registration→SMS/email thật. SMS chỉ demo; delivery provider/Resend config/key cần riêng. | P1 |
| B-06 | Quên mật khẩu. | PARTIAL | public/api/auth/request-password-reset.php; app/Services/PasswordResetService.php::request | Known/unknown email response giống nhau khi lỗi provider PASS. | Chưa xác minh email delivery; timing enumeration và shared rate limiting còn mở. | P1 |
| B-07 | Đặt lại mật khẩu an toàn. | DONE | app/Services/PasswordResetService.php::reset; app/Services/SessionGuard.php | Reset PostgreSQL, token single-use, hai token concurrent chỉ1 thành công/không deadlock; thu hồi session theo giây lẻ PASS. | Không chứng minh email gửi link đến người nhận thật. | P1 |
| B-08 | API hồ sơ người dùng. | DONE | public/api/auth/profile.php | HTTP profile read/update actual identity, reject role injection, không hash PASS; browser Customer render. | — | P1 |
| B-09 | Một tài khoản có thể có đồng thời role Customer và Shipper. | DONE | public/api/auth/switch-workspace.php | HTTP Shipper thêm Customer, profile còn cả2 role PASS; session role chỉ đổi sau commit. | — | P1 |
| B-10 | Kiểm tra quyền sở hữu dữ liệu ở backend. | DONE | app/Services/CustomerService.php; public/api/auth/profile.php | Cross-owner address edit/archive và order read404; other list không chứa đơn; actual session identity PASS. | Không đánh giá endpoint offer chưa tồn tại. | P1 |
| B-11 | Shipper không truy cập được offer/đơn ngoài phạm vi được phép. | BLOCKED | docs/PERSON1_INTEGRATION.md | Không có API offer backend để kiểm thử. | DEPENDENCY_PERSON2: ownership offer/order cần implementation và tests của Người2. | P1 |
| B-12 | Admin xem, khóa/mở tài khoản. | DONE | public/api/admin/accounts.php; app/Services/AdminService.php; app/Repositories/Person1Repository.php::audit | Lookup/lock/unlock, self-action denied, status history+audit cùng transaction; suspension revokes old sessions PASS. | Không đổi quyền truy cập tài khoản Admin khi chưa có governance policy. | P1 |
| B-13 | Admin xử lý yêu cầu đổi quốc tịch. | PARTIAL | public/api/auth/nationality-request.php; public/api/admin/nationality-requests.php | Request/list/reject, pending uniqueness, audit và country preservation PASS. | BLOCKED_BY_BUSINESS_DECISION: approve cần thay giấy tờ/tái xác minh. | P1 |
| B-14 | Ghi lịch sử trạng thái tài khoản. | DONE | public/api/admin/accounts.php; app/Services/AdminService.php; app/Repositories/Person1Repository.php::audit | Lookup/lock/unlock, self-action denied, status history+audit cùng transaction; suspension revokes old sessions PASS. | Không đổi quyền truy cập tài khoản Admin khi chưa có governance policy. | P1 |
| B-15 | Audit log cho thao tác Admin nhạy cảm. | DONE | public/api/admin/accounts.php; app/Services/AdminService.php; app/Repositories/Person1Repository.php::audit | Lookup/lock/unlock, self-action denied, status history+audit cùng transaction; suspension revokes old sessions PASS. | Không đổi quyền truy cập tài khoản Admin khi chưa có governance policy. | P1 |
| C-01 | CRUD địa chỉ Customer. | DONE | public/api/customer/addresses.php; app/Services/CustomerService.php::saveAddress/archiveAddress | CRUD soft archive, default invariant, cross-owner404, coordinate bounds, used-address409; HTTP/service/browser persistence PASS. | Tên/phone liên hệ riêng địa chỉ chưa có schema; không tự thêm field chưa chốt. | P1 |
| C-02 | Phân quyền sở hữu địa chỉ. | DONE | public/api/customer/addresses.php; app/Services/CustomerService.php::saveAddress/archiveAddress | CRUD soft archive, default invariant, cross-owner404, coordinate bounds, used-address409; HTTP/service/browser persistence PASS. | Tên/phone liên hệ riêng địa chỉ chưa có schema; không tự thêm field chưa chốt. | P1 |
| C-03 | Danh mục loại kiện hàng. | DONE | public/api/customer/categories.php | HTTP actual catalog có restriction_type, chỉ active PASS. | — | P1 |
| C-04 | Admin đánh dấu loại hàng được phép/hạn chế/cấm. | DONE | public/api/admin/categories.php | HTTP đổi restriction persisted và transaction audit; validation choices PASS. | — | P1 |
| C-05 | Tạo đơn với địa chỉ lấy/giao. | BLOCKED | public/api/customer/create-order.php; quote.php; database/SOMI_database.sql | Không tạo đơn thật từ giá client; HTTP409 có code rõ PASS. | BLOCKED_BY_BUSINESS_DECISION: server quotation/currency/distance/weather + payment contract. Các cột có sẵn không chứng minh flow đã xong. | P0 |
| C-06 | Lưu tọa độ hợp lệ. | PARTIAL | app/Services/CustomerService.php::saveAddress; database/SOMI_database.sql | Địa chỉ lưu tọa độ hợp lệ và reject ngoài range PASS. | Tạo đơn thật bị chặn nên chưa kiểm chứng snapshot tọa độ qua API create. | P1 |
| C-07 | Lưu thông tin liên hệ người gửi/người nhận. | BLOCKED | public/api/customer/create-order.php; quote.php; database/SOMI_database.sql | Không tạo đơn thật từ giá client; HTTP409 có code rõ PASS. | BLOCKED_BY_BUSINESS_DECISION: server quotation/currency/distance/weather + payment contract. Các cột có sẵn không chứng minh flow đã xong. | P1 |
| C-08 | Lưu loại hàng, khối lượng. | BLOCKED | public/api/customer/create-order.php; quote.php; database/SOMI_database.sql | Không tạo đơn thật từ giá client; HTTP409 có code rõ PASS. | BLOCKED_BY_BUSINESS_DECISION: server quotation/currency/distance/weather + payment contract. Các cột có sẵn không chứng minh flow đã xong. | P1 |
| C-09 | Snapshot địa chỉ/liên hệ tại thời điểm tạo đơn. | PARTIAL | app/Services/CustomerService.php; tests/person1_services.php | Snapshot địa chỉ của order test trực tiếp trong DB không đổi sau sửa saved address PASS. | Chưa có server create-order flow để chứng minh snapshot contact/address lúc tạo. | P1 |
| C-10 | Customer xem danh sách đơn. | DONE | public/api/customer/orders.php; app/Services/CustomerService.php::orders/order | Owner list/detail/history actual PostgreSQL; IDOR404; browser order detail PASS. | Chỉ đọc đơn có sẵn; không phải bằng chứng API tạo đơn hoạt động. | P1 |
| C-11 | Customer xem chi tiết đơn của mình. | DONE | public/api/customer/orders.php; app/Services/CustomerService.php::orders/order | Owner list/detail/history actual PostgreSQL; IDOR404; browser order detail PASS. | Chỉ đọc đơn có sẵn; không phải bằng chứng API tạo đơn hoạt động. | P1 |
| C-12 | Customer xem trạng thái và lịch sử đơn. | DONE | public/api/customer/orders.php; app/Services/CustomerService.php::orders/order | Owner list/detail/history actual PostgreSQL; IDOR404; browser order detail PASS. | Chỉ đọc đơn có sẵn; không phải bằng chứng API tạo đơn hoạt động. | P1 |
| C-13 | Customer được hủy đơn trước khi Shipper nhận theo quy tắc nghiệp vụ. | PARTIAL | app/Services/CustomerService.php::cancel; app/Services/OrderContract.php | Unassigned/unpaid cancel idempotent, history, stale assigned409, row-lock races PASS. | DEPENDENCY_PERSON3: có bất kỳ payment record/paid_at thì chặn409 chờ refund contract. | P1 |
| C-14 | Có hợp đồng trạng thái đơn dùng chung với Người 2. | PARTIAL | app/Services/OrderContract.php; docs/PERSON1_INTEGRATION.md | Vocabulary DB và BEFORE_ASSIGNMENT dùng trong service, docs lock protocol; test contract simulator PASS. | Chưa được Người2 xác nhận/áp dụng transition graph. | P1 |
| C-15 | Lưu lịch sử chuyển trạng thái. | PARTIAL | app/Services/CustomerService.php::cancel; tests/person1_concurrency.php | History và concurrency path hủy đã PASS; matching simulator dùng cùng lock không nhận đơn cancelled. | Chưa test backend matching/delivery/payment thực của Người2/3; chưa graph cho toàn bộ trạng thái. | P0 |
| C-16 | Ngăn chuyển trạng thái không hợp lệ và xử lý cập nhật đồng thời. | PARTIAL | app/Services/CustomerService.php::cancel; tests/person1_concurrency.php | History và concurrency path hủy đã PASS; matching simulator dùng cùng lock không nhận đơn cancelled. | Chưa test backend matching/delivery/payment thực của Người2/3; chưa graph cho toàn bộ trạng thái. | P0 |
| D-01 | Admin cấu hình giá theo khoảng cách. | PARTIAL | app/Services/PricingConfiguration.php; public/api/admin/pricing.php; database/migrations/20261102_person1.sql | Typed validation, explicit VND/USD cấu hình đầy đủ lưu draft + audit; invalid time/money/percent tests PASS; browser draft save PASS. | BLOCKED_BY_BUSINESS_DECISION: draft không kích hoạt, không tính/gán giá hoặc thu nhập thực. | P1 |
| D-02 | Admin cấu hình giá theo khung giờ. | PARTIAL | app/Services/PricingConfiguration.php; public/api/admin/pricing.php; database/migrations/20261102_person1.sql | Typed validation, explicit VND/USD cấu hình đầy đủ lưu draft + audit; invalid time/money/percent tests PASS; browser draft save PASS. | BLOCKED_BY_BUSINESS_DECISION: draft không kích hoạt, không tính/gán giá hoặc thu nhập thực. | P1 |
| D-03 | Admin cấu hình ảnh hưởng thời tiết. | PARTIAL | app/Services/PricingConfiguration.php; public/api/admin/pricing.php; database/migrations/20261102_person1.sql | Typed validation, explicit VND/USD cấu hình đầy đủ lưu draft + audit; invalid time/money/percent tests PASS; browser draft save PASS. | BLOCKED_BY_BUSINESS_DECISION: draft không kích hoạt, không tính/gán giá hoặc thu nhập thực. | P1 |
| D-04 | Bảng giá VND và USD riêng. | PARTIAL | app/Services/PricingConfiguration.php; public/api/admin/pricing.php; database/migrations/20261102_person1.sql | Typed validation, explicit VND/USD cấu hình đầy đủ lưu draft + audit; invalid time/money/percent tests PASS; browser draft save PASS. | BLOCKED_BY_BUSINESS_DECISION: draft không kích hoạt, không tính/gán giá hoặc thu nhập thực. | P1 |
| D-05 | Tính báo giá theo chính sách đã thống nhất. | BLOCKED | public/api/customer/quote.php; create-order.php; docs/PERSON1_INTEGRATION.md | Quote/create fail-closed409 đã test; không dùng công thức demo. | BLOCKED_BY_BUSINESS_DECISION: công thức/ranh giới/hệ số, nguồn distance/weather, rounding và policy snapshot contract. | P1 |
| D-06 | Làm tròn lên đơn vị tiền nhỏ nhất theo từng loại tiền. | BLOCKED | public/api/customer/quote.php; create-order.php; docs/PERSON1_INTEGRATION.md | Quote/create fail-closed409 đã test; không dùng công thức demo. | BLOCKED_BY_BUSINESS_DECISION: công thức/ranh giới/hệ số, nguồn distance/weather, rounding và policy snapshot contract. | P1 |
| D-07 | Snapshot chính sách giá lên đơn. | BLOCKED | public/api/customer/quote.php; create-order.php; docs/PERSON1_INTEGRATION.md | Quote/create fail-closed409 đã test; không dùng công thức demo. | BLOCKED_BY_BUSINESS_DECISION: công thức/ranh giới/hệ số, nguồn distance/weather, rounding và policy snapshot contract. | P1 |
| D-08 | Snapshot các thành phần tạo thành tổng giá. | BLOCKED | public/api/customer/quote.php; create-order.php; docs/PERSON1_INTEGRATION.md | Quote/create fail-closed409 đã test; không dùng công thức demo. | BLOCKED_BY_BUSINESS_DECISION: công thức/ranh giới/hệ số, nguồn distance/weather, rounding và policy snapshot contract. | P1 |
| D-09 | Không thay đổi giá đơn đã thanh toán. | PARTIAL | public/api/admin/orders.php; app/Services/CustomerService.php::cancel | API mới không có update price; cancel test giữ total và pricing_snapshot PASS. | Chưa chứng minh paid-order immutability khi tích hợp Người3 và server create. | P1 |
| D-10 | Admin cấu hình phí hủy. | PARTIAL | app/Services/PricingConfiguration.php; public/api/admin/pricing.php; database/migrations/20261102_person1.sql | Typed validation, explicit VND/USD cấu hình đầy đủ lưu draft + audit; invalid time/money/percent tests PASS; browser draft save PASS. | BLOCKED_BY_BUSINESS_DECISION: draft không kích hoạt, không tính/gán giá hoặc thu nhập thực. | P1 |
| D-11 | Admin cấu hình phí hoàn hàng. | PARTIAL | app/Services/PricingConfiguration.php; public/api/admin/pricing.php; database/migrations/20261102_person1.sql | Typed validation, explicit VND/USD cấu hình đầy đủ lưu draft + audit; invalid time/money/percent tests PASS; browser draft save PASS. | BLOCKED_BY_BUSINESS_DECISION: draft không kích hoạt, không tính/gán giá hoặc thu nhập thực. | P1 |
| D-12 | Admin cấu hình phần trăm thu nhập Shipper. | PARTIAL | app/Services/PricingConfiguration.php; public/api/admin/pricing.php; database/migrations/20261102_person1.sql | Typed validation, explicit VND/USD cấu hình đầy đủ lưu draft + audit; invalid time/money/percent tests PASS; browser draft save PASS. | BLOCKED_BY_BUSINESS_DECISION: draft không kích hoạt, không tính/gán giá hoặc thu nhập thực. | P1 |
| D-13 | Admin cấu hình bồi hoàn đơn hoàn. | PARTIAL | app/Services/PricingConfiguration.php; public/api/admin/pricing.php; database/migrations/20261102_person1.sql | Typed validation, explicit VND/USD cấu hình đầy đủ lưu draft + audit; invalid time/money/percent tests PASS; browser draft save PASS. | BLOCKED_BY_BUSINESS_DECISION: draft không kích hoạt, không tính/gán giá hoặc thu nhập thực. | P1 |
| D-14 | Admin cấu hình chính sách điểm Shipper. | PARTIAL | app/Services/PricingConfiguration.php; public/api/admin/pricing.php; database/migrations/20261102_person1.sql | Typed validation, explicit VND/USD cấu hình đầy đủ lưu draft + audit; invalid time/money/percent tests PASS; browser draft save PASS. | BLOCKED_BY_BUSINESS_DECISION: draft không kích hoạt, không tính/gán giá hoặc thu nhập thực. | P1 |
| E-01 | API Admin tra cứu Customer. | DONE | public/api/admin/accounts.php; app/Services/AdminService.php::accounts | HTTP lookup Customer/Shipper qua q/role actual data PASS, permission enforced. | — | P1 |
| E-02 | API Admin tra cứu Shipper. | DONE | public/api/admin/accounts.php; app/Services/AdminService.php::accounts | HTTP lookup Customer/Shipper qua q/role actual data PASS, permission enforced. | — | P1 |
| E-03 | API Admin tra cứu và xem chi tiết đơn hàng. | DONE | public/api/admin/orders.php | HTTP list và detail/history actual DB PASS. | — | P1 |
| E-04 | Admin duyệt hồ sơ KYC. | DONE | public/api/admin/review-shipper.php | APPROVED/REJECTED/NEEDS_INFO HTTP PASS, stale409; identity persisted và audit cả3 quyết định assertions PASS. | Chưa có UI xem evidence upload/number nhạy cảm; không tuyên bố kiểm định tài liệu thực tế. | P1 |
| E-05 | Admin từ chối KYC. | DONE | public/api/admin/review-shipper.php | APPROVED/REJECTED/NEEDS_INFO HTTP PASS, stale409; identity persisted và audit cả3 quyết định assertions PASS. | Chưa có UI xem evidence upload/number nhạy cảm; không tuyên bố kiểm định tài liệu thực tế. | P1 |
| E-06 | Admin yêu cầu bổ sung hồ sơ. | DONE | public/api/admin/review-shipper.php | APPROVED/REJECTED/NEEDS_INFO HTTP PASS, stale409; identity persisted và audit cả3 quyết định assertions PASS. | Chưa có UI xem evidence upload/number nhạy cảm; không tuyên bố kiểm định tài liệu thực tế. | P1 |
| E-07 | Lưu trạng thái và lịch sử xét duyệt KYC. | DONE | public/api/admin/review-shipper.php | APPROVED/REJECTED/NEEDS_INFO HTTP PASS, stale409; identity persisted và audit cả3 quyết định assertions PASS. | Chưa có UI xem evidence upload/number nhạy cảm; không tuyên bố kiểm định tài liệu thực tế. | P1 |
| E-08 | Hợp đồng API cho matching với Người 2. | PARTIAL | docs/PERSON1_INTEGRATION.md; app/Services/OrderContract.php | Có đề xuất identity/permission, lock order, idempotency, payment guard và ownership. | Chờ Người2/3 xác nhận và implementation; không gọi contract test là E2E matching/payment. | P1 |
| E-09 | Hợp đồng API cho trạng thái giao hàng với Người 2. | PARTIAL | docs/PERSON1_INTEGRATION.md; app/Services/OrderContract.php | Có đề xuất identity/permission, lock order, idempotency, payment guard và ownership. | Chờ Người2/3 xác nhận và implementation; không gọi contract test là E2E matching/payment. | P1 |
| E-10 | Hợp đồng API thanh toán/refund với Người 3. | PARTIAL | docs/PERSON1_INTEGRATION.md; app/Services/OrderContract.php | Có đề xuất identity/permission, lock order, idempotency, payment guard và ownership. | Chờ Người2/3 xác nhận và implementation; không gọi contract test là E2E matching/payment. | P1 |
| E-11 | Review migrations và khả năng tích hợp. | DONE | scripts/migrate.php; docs/PERSON1_BACKEND.md | Canonical baseline/migrations PostgreSQL16 test PASS; Supabase23 prerequisites read-only PASS, new migration chưa áp. | — | P1 |
| E-12 | Chuẩn hóa authentication/authorization giữa các module. | PARTIAL | public/api/_person1.php; app/Services/SessionGuard.php; public/api/auth/switch-workspace.php | Middleware dùng cho API mới/review; bỏ duplicate guard, giữ auth legacy tương thích; test permission/session PASS. | Người2/3 chưa có API để xác nhận chuẩn hóa toàn module. | P0 |
| E-13 | Tài liệu API và hướng dẫn tích hợp. | DONE | docs/PERSON1_API_CONTRACT.md; PERSON1_INTEGRATION.md; PERSON1_BACKEND.md | Có request/response/status/permission, paths, env/test/migration instructions và giới hạn. | — | P1 |

## Kế hoạch theo dependency và tiêu chí nghiệm thu

| Ưu tiên | Việc | Dependency | Files/phạm vi | Tiêu chí nghiệm thu / trạng thái |
|---|---|---|---|---|
| P0 | PostgreSQL/runtime và tái lập migration | PostgreSQL test riêng | Config/database.php, scripts/migrate.php, scripts/serve.ps1 | DONE: baseline sạch + rerun; Supabase read-only; không đổi hệ DB |
| P0 | Identity/role/permission, suspend/reset concurrency | Bảng auth/RBAC | app/Services/SessionGuard.php, _person1.php, login.php, app/Services/PasswordResetService.php | DONE phần đã kiểm: wrong role/owner denied, old session revoked, reset race chỉ1 thắng |
| P0 | Server quotation và tạo đơn | Quyết định giá/currency/distance/weather, phối hợp3 | quote/create-order.php, service/repository order trong lần tiếp | BLOCKED: không lấy tổng giá client; cần quote server có thời hạn, snapshot và transaction/idempotency có test |
| P1 | Hồ sơ, địa chỉ, đọc đơn, hủy trước nhận | Auth/schema hiện hữu | app/Services/CustomerService.php và API Customer | DONE CRUD/read; PARTIAL cancel: cần refund contract khi có payment |
| P1 | Admin core và KYC | RBAC, migration mới | app/Services/AdminService.php, API admin, 20261102_person1.sql | DONE read/status/KYC/history/audit; apply migration Supabase chưa thực hiện |
| P1 | Kích hoạt giá/thu nhập/điểm | Công thức/phạm vi/hệ số được phê duyệt | PricingConfiguration.php, pricing API, service giá tương lai | PARTIAL: draft đã kiểm; activation/quotes chưa có, không tự đặt mặc định |
| P1 | Đổi quốc tịch APPROVED | Quy trình thay giấy tờ/tái KYC | nationality endpoints/service | PARTIAL: request/list/reject đã kiểm; approve chặn rõ |
| P1 | Matching/giao nhận/payment/refund tích hợp | Implementation2/3 | PERSON1_INTEGRATION.md, shared OrderContract | Test hai worker thật khi module sẵn sàng; hiện chỉ contract simulator |
| P2 | Email/SMS và registration/reset delivery E2E | Provider sandbox, test identity key | auth services/endpoints | NOT_RUN delivery thật; cần tài khoản test riêng, không gửi OTP/mail thử vào tài khoản production |
| P2 | Shared login/OTP abuse limiter và timing enumeration | Store/chính sách vận hành thống nhất | auth middleware/services | Chưa triển khai; phải có integration test cross-session, không dựa cookie limiter giả |
| P2 | Thay UI demo và hoàn thiện UI mới | Server create/quote + state graph2/3 | Customer/Admin views và JS | Màn hình backend mới có persistence; chưa thay toàn bộ demo, chưa toàn bộ i18n/responsive |
| P2 | Deploy validation | Review migration/RBAC/RLS, PHP8.3 build, hosting proxy | Dockerfile, RequestOrigin.php, deployment | NOT_RUN build/deploy/reverse proxy thật, không gán PASS |

## Kiểm thử thực tế

- PostgreSQL16 Docker riêng somi_person1_test, cổng55432, server test3034 và cookie/session riêng. Không ghi test data vào Supabase.
- Baseline sạch và incremental migrations PASS; chạy lại ledger skip đúng. Supabase READ ONLY đủ23 bảng và84 cột prerequisites (person1-supabase-schema-results.json), migration mới chưa áp.
- Service: xem person1-service-results.json. HTTP password/API: person1-http-results.json. Concurrency: person1-concurrency-results.json. DB/session/permission: person1-assertion-results.json. Kết quả từng check được lưu, không chỉ dựa lint.
- Browser: login Customer và Admin, address save + reload, đọc order/history; Admin đọc accounts/catalog, lưu draft không activation. Sửa lỗi response array adapter phát hiện qua browser. Screenshot person1-admin-preview.png dùng dữ liệu test. Chưa kiểm full responsive/i18n mọi màn hình.
- Static: 93 PHP lint,27 JS syntax,158 file text scan secret heuristic không có finding; git diff --check PASS. Existing frontend auth assertions PASS; origin21 checks PASS.
- NOT_RUN: API tạo đơn/báo giá thành công (business blocked), email/SMS delivery thật, payment/refund/matching/delivery backend E2E, production destructive tests, Docker app build, production migrations/deploy.

Tổng kiểm thử mới: 112 checks, 0 failures (service 34, HTTP 58, concurrency 7, DB/session/permission 13). Đây là kết quả kiểm thử trên database riêng.

## API/chức năng vừa triển khai

12 endpoint mới: auth profile/nationality-request; Customer addresses/orders/categories/quote/create-order; Admin accounts/orders/categories/pricing/nationality-requests. Quote/create là endpoint từ chối có lý do, KHÔNG tính là chức năng hoàn thành. Mở rộng review-shipper NEEDS_INFO + identity existence/locks/audit. Thêm repository, input/problem helpers, shared DB order vocabulary, migration runner và UI backend riêng. Auth sửa lock-order reset, phiên theo timestamp giây lẻ, thu hồi sau suspend, login user lock và workspace session sau commit.

## Files thay đổi

Danh sách working tree tổng, bao gồm thay đổi UI được giữ từ yêu cầu trước (shared/public.php, auth.php, shipper/index.php, shipper/pending.php, app.css/app.js và language-picker.php). Phần lần này không sửa matching/payment/delivery của Người2/3.

- `.gitignore`
- `README.md`
- `app/Services/PasswordResetService.php`
- `app/Services/SessionGuard.php`
- `app/Views/admin/audit-logs.php`
- `app/Views/admin/complaints.php`
- `app/Views/admin/customers.php`
- `app/Views/admin/disputes.php`
- `app/Views/admin/index.php`
- `app/Views/admin/notifications.php`
- `app/Views/admin/orders.php`
- `app/Views/admin/payments.php`
- `app/Views/admin/reviews.php`
- `app/Views/admin/shippers.php`
- `app/Views/admin/users.php`
- `app/Views/customer/index.php`
- `app/Views/shared/auth.php`
- `app/Views/shared/public.php`
- `app/Views/shipper/index.php`
- `app/Views/shipper/pending.php`
- `database/schema.sql`
- `index.php`
- `public/api/_bootstrap.php`
- `public/api/admin/review-shipper.php`
- `public/api/auth/login.php`
- `public/api/auth/register.php`
- `public/api/auth/request-password-reset.php`
- `public/api/auth/switch-workspace.php`
- `public/assets/css/app.css`
- `public/assets/js/app.js`
- `public/assets/js/customer/ui.js`
- `.env.example`
- `app/Repositories/Person1Repository.php`
- `app/Services/AdminService.php`
- `app/Services/ApiProblem.php`
- `app/Services/CustomerService.php`
- `app/Services/Input.php`
- `app/Services/OrderContract.php`
- `app/Services/PricingConfiguration.php`
- `app/Views/admin/backend.php`
- `app/Views/customer/pages/backend.php`
- `app/Views/shared/language-picker.php`
- `app/Views/shared/person1-workspace.php`
- `database/migrations/20261102_person1.sql`
- `docs/PERSON1_API_CONTRACT.md`
- `docs/PERSON1_AUDIT.md`
- `docs/PERSON1_BACKEND.md`
- `docs/PERSON1_INTEGRATION.md`
- `docs/PERSON1_PROGRESS.md`
- `docs/person1-admin-preview.png`
- `docs/person1-assertion-results.json`
- `docs/person1-audit-baseline.json`
- `docs/person1-concurrency-results.json`
- `docs/person1-http-results.json`
- `docs/person1-progress.json`
- `docs/person1-service-results.json`
- `docs/person1-static-results.json`
- `docs/person1-supabase-schema-results.json`
- `docs/person1-test-summary.json`
- `public/api/_person1.php`
- `public/api/admin/accounts.php`
- `public/api/admin/categories.php`
- `public/api/admin/nationality-requests.php`
- `public/api/admin/orders.php`
- `public/api/admin/pricing.php`
- `public/api/auth/nationality-request.php`
- `public/api/auth/profile.php`
- `public/api/customer/addresses.php`
- `public/api/customer/categories.php`
- `public/api/customer/create-order.php`
- `public/api/customer/orders.php`
- `public/api/customer/quote.php`
- `public/assets/css/person1.css`
- `public/assets/js/person1.js`
- `scripts/migrate.php`
- `scripts/serve.ps1`
- `scripts/test-person1.ps1`
- `tests/person1_assertions.php`
- `tests/person1_concurrency.php`
- `tests/person1_http.cjs`
- `tests/person1_schema.php`
- `tests/person1_seed.php`
- `tests/person1_services.php`
- `tests/person1_static.cjs`

## Trước merge/deploy

Review tích hợp theo TEAM-OWNERSHIP.md; review phạm vi/RBAC/RLS migration trước apply. Không chứng nhận production ready; server local3000 còn dùng Supabase chưa migration nên một số API Admin/nationality/pricing mới chưa dùng được tại đó. Màn hình cũ vẫn demo/localStorage. Chốt các business blocker trong bảng, xác nhận hợp đồng2/3 và test môi trường deploy trước khi đưa giao dịch thật vào sử dụng.
