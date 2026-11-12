# SOMI Grab Delivery — Audit 2026-11-01

> **Tài liệu lịch sử:** kết luận, phân công và trạng thái repository dưới đây thuộc ngày kiểm tra ghi trong báo cáo. Xem [scope hiện hành 06/11/2026](BACKEND-SCOPE.md), [API/giá hiện hành](DELIVERY-API.md). Giữ nguyên bằng chứng kiểm thử quá khứ; không dùng báo cáo này để giao việc hiện tại.

## A. Project Overview

PHP templates + vanilla JavaScript + CSS; Customer nạp Bootstrap 5.3.3 CDN. Không có TypeScript, Composer, npm manifest hoặc build/test script trước audit. Backend dùng PDO PostgreSQL, cURL Resend và OpenSSL AES-256-GCM cho giấy tờ. PHP cục bộ: 8.0.30; Docker khai báo PHP 8.3 Apache.

- `index.php`: front controller, whitelist page/view, kiểm tra session/role, render views.
- `app/Views/shared`: public, auth, reset password.
- `app/Views/customer`: 13 view; dashboard, tạo đơn, thanh toán, matching, tracking, review, report, account, addresses, notification.
- `app/Views/shipper`: dashboard, offers, active, history, earnings, account, notifications, pending application.
- `app/Views/admin`: dashboard và 10 trang quản lý; dữ liệu phần lớn tĩnh.
- `public/api/auth`: register/login, phone/email verification, password reset, switch workspace; bổ sung logout.
- `public/api/admin/review-shipper.php`: duyệt hồ sơ qua transaction, ghi audit log.
- `database`: hai SQL schema và hai migration; không tự apply bất kỳ file nào. `schema.sql` ghi rõ proposed; không được giả định tương đương schema đang chạy.
- `resources/countries.json`, assets CSS/JS; PDF là tài liệu tham khảo, chưa kiểm tra nội dung hình ảnh.

Chạy từ root: `C:/xampp/php/php.exe -S 127.0.0.1:3031 index.php`. Apache cần AllowOverride và mod_rewrite. Cần các biến `SOMI_DB_*`, key mã hóa giấy tờ, cấu hình Resend và APP_URL cho luồng tương ứng; không lưu giá trị trong báo cáo. Demo OTP phải bật rõ `SOMI_PHONE_OTP_MODE=demo`; không còn tự bật qua Host. SMS thật chưa có provider.

Điểm khôi phục trước sửa: nhánh `codex/audit-backup-20261101`, commit gốc `5cf89d8`. Thay đổi nằm ở working tree; chưa commit, push hoặc deploy. Yêu cầu review integration-owner trong TEAM-OWNERSHIP vẫn cần thực hiện trước merge.

## B. Issues Found

| ID | Severity | File | Issue | Root Cause | Status |
|---|---|---|---|---|---|
| SEC-01 | Critical | index.php, .htaccess | File SQL/Git và template Admin có thể truy cập trực tiếp | Router phục vụ mọi file, Apache thiếu chặn thư mục nội bộ | Fixed; Apache runtime chưa test |
| SEC-02 | Critical | index.php, auth/login.php | Pending/suspended có thể có session rồi mở workspace | Gán session trước kiểm tra status; route chỉ kiểm tra role | Fixed; DB integration BLOCKED |
| SEC-03 | High | app.js, shipper/index.php | Logout chỉ điều hướng hoặc hiện toast | Không có endpoint hủy session | Fixed |
| SEC-04 | High | SessionGuard.php | Session không kiểm tra idle/status/password reset | Thiếu kiểm tra session với DB | Added guard; DB integration BLOCKED |
| SEC-05 | High | api/_bootstrap.php | POST thiếu bảo vệ cross-origin rõ ràng | Chấp nhận JSON dù Content-Type là form/text | Fixed: JSON-only, Origin và Fetch Metadata checks |
| SEC-06 | High | api/_bootstrap.php | PDOException có thể trả thông tin DB trong lỗi 422 | PDOException kế thừa RuntimeException | Fixed |
| SEC-07 | High | PhoneVerificationService.php | Có thể chọn demo OTP bằng Host | Tin HTTP_HOST để quyết định trả OTP | Fixed: explicit configuration only |
| SEC-08 | Medium | customer/store.js | Dữ liệu demo dùng chung giữa tài khoản trên cùng browser | Một localStorage key toàn cục | Fixed: key theo user ID; dữ liệu cũ không xóa/migrate |
| ROUTE-01 | High | customer/pages/nofitications.php | Notification gây require lỗi | Tên file sai so với whitelist | Renamed notifications.php |
| ROUTE-02 | High | admin/*.php, index.php | Link quản lý 404; CSS sai đường dẫn | Template standalone liên kết file tương đối | Fixed route whitelist và đường dẫn assets |
| ROUTE-03 | Medium | index.php, customer/index.php | view dạng array có thể gây TypeError | Thiếu kiểm tra kiểu GET | Fixed |
| RUNTIME-01 | High | Dockerfile | Register/switch có thể lỗi undefined mb_strlen | Docker thiếu mbstring | Fixed definition; image build BLOCKED |
| UI-01 | Medium | admin/*.php | HTML document lồng document | Include cả doctype/head/body vào shell | Fixed; giữ style/font hiện tại |
| FUNC-01 | High | customer/store.js, shipper.js, admin views | Tạo/nhận/theo dõi đơn, thanh toán, hồ sơ và quản lý chưa nối DB | Source hiện tại là demo frontend | OPEN; không dựng mock mới hoặc tuyên bố xử lý thật |
| ENV-01 | High | DB runtime | Truy vấn SELECT 1 thất bại | PDOException; không in chi tiết kết nối | BLOCKED; cần DB test truy cập được |
| SEC-09 | High | auth/login.php, register.php | Không thấy giới hạn brute force liên request/tài khoản trên login | Thiếu shared rate limiter | OPEN; cần chính sách và shared store ở deployment |
| CONFIG-01 | Medium | PasswordResetService.php | URL reset fallback hardcoded deployment | baseUrl fallback production cũ | OPEN; cấu hình SOMI_APP_URL đúng môi trường |
| DOC-01 | Low | README.md | Mô tả toàn dự án là frontend demo dù đã có auth DB | Tài liệu cũ | Báo cáo này bổ sung thực trạng |

## C. Fixes Applied

| ID | File Modified | Change Description | Verification Result |
|---|---|---|---|
| SEC-01 | index.php, .htaccess | Chỉ cho router phục vụ public, cấm nội bộ; /admin tương thích built-in | HTTP SQL/Git/template/bootstrap 404, /admin redirect PASS; Apache BLOCKED |
| SEC-02/04 | SessionGuard.php, index.php, login/register.php, switch-workspace.php, review-shipper.php | Kiểm tra ACTIVE, idle 30 phút, password_changed_at; tạo timestamp session; workspace lỗi DB trả 503 | PHP lint PASS; xác thực thật BLOCKED. Session cũ thiếu timestamp phải đăng nhập lại |
| SEC-03 | logout.php, app.js, shipper/index.php, admin views | Hủy session/cookie và nối nút signout | Endpoint 200 PASS; logout user thật BLOCKED |
| SEC-05/06 | _bootstrap.php | JSON object/scalar validation, 415, cross-origin 403, che lỗi DB/provider/config | HTTP 400/415/403 PASS, lint PASS |
| SEC-07/08 | PhoneVerificationService.php, customer/store.js, customer/index.php | OTP demo opt-in; namespace dữ liệu demo theo user | Lint/syntax PASS; không xác nhận bằng SMS/database |
| ROUTE-01/03 | customer index/pages, index.php | Đúng tên notification; kiểm tra kiểu view | Lint PASS; authenticated render BLOCKED |
| ROUTE-02/UI-01 | admin views, index.php | Whitelist view, link qua controller, bỏ document wrappers lồng nhau, giữ font/icon | Lint PASS; authenticated navigation BLOCKED |
| RUNTIME-01 | Dockerfile | Thêm libonig-dev và mbstring | Source review; Docker build chưa chạy |

## D. Functional Test Results

| Module | Function | Expected Result | Actual Result | PASS/FAIL/BLOCKED |
|---|---|---|---|---|
| Source | PHP lint | Không syntax error | 58/58 file hợp lệ | PASS |
| Source | JS node --check | Không syntax error | 20/20 file hợp lệ | PASS |
| Source | TypeScript/build | Có script tương ứng nếu tồn tại | Không có TS/build manifest | N/A |
| Public | 7 page home/how/customers/shippers/pricing/auth/reset-password | HTTP 200, không PHP warning/fatal trong body | 7/7 đúng | PASS |
| Routing | Chưa login vào Customer/Shipper/Admin, /admin và /admin/ | 303 auth | Đúng ở 5 route | PASS |
| Security | File nội bộ, bootstrap, route lạ | 404 trên built-in | Đúng | PASS |
| API | Sai method/content type/JSON/credentials thiếu | 405/415/400/422 | Đúng | PASS |
| API | Switch/review chưa login | 401 | Đúng | PASS |
| API | Logout khác Origin | 403 | Đúng | PASS |
| API | Logout không có login | JSON 200 | Đúng | PASS |
| Public UI | 7 trang × 1440/768/375px | Không tràn ngang, không uncaught JS exception | 21/21 phép đo không tràn; 0 runtime exception thu được | PASS |
| Public UI | Login/register/reset thành công | Dữ liệu DB và email thật | DB SELECT 1 lỗi; không gửi email thử hoặc ghi DB | BLOCKED |
| Customer | 13 view, dropdown, form, notification, order lifecycle | Render, tương tác và dữ liệu đúng | Không có session test với DB hoạt động; order/payment hiện demo | BLOCKED |
| Shipper | Tabs/avatar/notices/accept/active/history/earnings/account/support | Tương tác và đồng bộ DB | Source demo; không đủ môi trường end-to-end | BLOCKED |
| Shipper | Registration và duyệt thật | Lưu DB, admin duyệt, role/status nhất quán | Chưa thực hiện giao dịch DB | BLOCKED |
| Admin | Role isolation Customer/Shipper/Admin đã login | 403/redirect hoặc đúng trang | Chỉ review code và test chưa login, chưa có tài khoản test | BLOCKED |
| Responsive private | 1440/768/375 | Không overflow/chồng lấn | Chưa vào được workspace thật | BLOCKED |
| Console/Network | Lỗi JS/runtime trên trang public | Không uncaught exception | Chrome CDP thu 0 exception; chưa đánh giá toàn bộ network/CDN/interactions | PASS (giới hạn trên) |
| Apache/Docker | Rewrite/build và proxy cookie HTTPS | Tương đương runtime mục tiêu | Không có Docker CLI; chưa chạy Apache test instance | BLOCKED |

28 HTTP checks lưu tại audit-http-results.json, script tests/audit_smoke.py. Browser script tests/audit_browser.cjs dùng Chrome CDP port 9331 và Node WebSocket; kết quả audit-browser-results.json. Phép đo responsive chỉ xét scrollWidth, không phải chứng nhận mọi chi tiết hình ảnh/modal/dropdown. Không tuyên bố toàn bộ hệ thống đã PASS.

## E. Remaining Issues

1. Cần DB staging/test truy cập được và tài khoản kiểm thử từng role để chạy auth, rollback, permissions, review application và regression sau login. Không sửa secrets hoặc tự thử ghi vào DB có sẵn.
2. Order/payment/tracking/customer account và đa số quản trị chỉ là demo; không có API nghiệp vụ tương ứng. Cần yêu cầu nghiệp vụ/API contract trước khi triển khai ngoài phạm vi sửa lỗi nhỏ. Dữ liệu demo Customer cũ được giữ trong key cũ và không tự đưa sang tài khoản khác.
3. Shared rate limiting login/register/reset, session behavior sau verification, concurrency duyệt/OTP/reset, SMTP/Resend và SMS thật chưa được chứng minh end-to-end. Không bật demo OTP trên production.
4. Hai schema SQL cần đối chiếu migration với DB thật, không chọn hoặc áp dụng tự động.
5. Cấu hình SOMI_APP_URL, HTTPS/proxy Secure cookies và Apache AllowOverride cần kiểm thử deployment. Bảo vệ Origin dùng scheme server nhận; proxy phải cấu hình HTTPS đúng. Không tin X-Forwarded headers tùy ý.
6. Shipper nhận đơn hiện chỉ thay đổi array trong bộ nhớ; chuyển trang/reload có thể mất trạng thái. Chưa có backend để đồng bộ. Admin nhiều nút quản lý/search/filter là UI tĩnh. Không thay bằng mock mới để che lỗi.
7. Chưa có kiểm thử đầy đủ XSS trên mọi thao tác sau login, browser Back/Forward private, dropdown/modal và console/network ở private workspace.
8. Không cần thay đổi schema, xóa dữ liệu hay deploy cho các bản sửa đã áp dụng. Merge cần integration-owner review theo TEAM-OWNERSHIP; không tự merge.

## F. Final Assessment

| Area | Đánh giá theo bằng chứng |
|---|---|
| Code Quality | PHP/JS syntax hợp lệ; đã sửa require sai và routing; chưa chứng minh runtime mọi nhánh |
| Functionality | Public chạy HTTP; auth backend tồn tại; nghiệp vụ delivery/payment còn demo, chưa sẵn sàng vận hành |
| Security | Đã xử lý bypass status, truy cập file nội bộ, logout, CSRF/content-type, lỗi DB lộ thông tin; rate limit và authenticated tests còn thiếu |
| Maintainability | Giữ PHP/JS hiện có; thêm session guard dùng chung và smoke scripts; hai schema/tài liệu cũ cần hợp nhất theo thực tế DB |
| Responsive UI | 21 phép đo public không tràn ngang; private/modal chưa xác minh |
| Deployment Readiness | CHƯA sẵn sàng production: DB integration, nghiệp vụ backend, rate limiting và deployment checks còn BLOCKED/OPEN |
