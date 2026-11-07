# SOMI — Re-audit sau Supabase Restore, Login & UI

> **Tài liệu lịch sử:** kết luận, phân công và trạng thái repository dưới đây thuộc ngày kiểm tra ghi trong báo cáo. Xem [scope hiện hành 06/11/2026](BACKEND-SCOPE.md), [API/giá hiện hành](DELIVERY-API.md). Giữ nguyên bằng chứng kiểm thử quá khứ; không dùng báo cáo này để giao việc hiện tại.

Ngày: 2026-11-01.

Cập nhật sau audit: đăng nhập Admin bằng mật khẩu thật, mở workspace, logout và chặn truy cập sau logout đã PASS 4/4; artifact `reaudit-password-admin.json`. Customer/Shipper vẫn chưa có kiểm thử mật khẩu thật. Các mục BLOCKED bên dưới mô tả thời điểm audit trước khi có credential Admin.
 Báo cáo này bổ sung audit trước; trạng thái DB BLOCKED trong báo cáo trước đã được kiểm tra lại và hiện kết nối được.

## A. Project Overview

Giữ kiến trúc PHP + PDO PostgreSQL/Supabase + PHP Session + HTML/CSS/Bootstrap/Vanilla JS. Không thêm framework hoặc build tool. Entry point `index.php`; API auth ở `public/api/auth`, API duyệt shipper ở `public/api/admin`; views chia Public/Customer/Shipper/Admin.

DB được kiểm tra bằng truy vấn READ ONLY và metadata. Các bảng auth/roles/profiles/verification/password reset/audit cần thiết có mặt. Không đổi schema, secrets, account status, mật khẩu hay dữ liệu nghiệp vụ. Không deploy hoặc push. Có snapshot source trước đợt sửa mới tại file `somi-reaudit-before-fixes.zip` trong thư mục tạm hệ thống; snapshot bao gồm working tree chưa commit của audit trước.

PHP cục bộ 8.0.30; 58 PHP và 21 JS trong source chạy lint/syntax thành công. Docker vẫn khai báo PHP 8.3; chưa build image hoặc chứng minh tương đương production.

## B. Issues Found

| ID | Severity | File | Issue | Root Cause | Status |
|---|---|---|---|---|---|
| LOGIN-01 | High | app.js: finishAuth | Login Customer từ URL next=shipper đưa vào Shipper rồi bị auth redirect | Client lấy next ưu tiên hơn role đã đăng nhập | FIXED; unit frontend PASS, password login chưa có credentials |
| LOGIN-02 | High | app.js: auth submit | Enter/submit nhiều lần có thể gửi nhiều request | disabled button không chặn event submit tiếp theo | FIXED; Chrome ba submit chỉ một request |
| LOGIN-03 | Medium | app.js: setupLanguage/finishAuth/logout | Storage bị chặn có thể dừng handler hoặc chặn redirect sau auth | Truy cập localStorage không có fallback | FIXED; Chrome storage-denied và frontend redirect PASS |
| LOGIN-04 | Medium | auth/login.php | API có thể báo lỗi cập nhật last-login sau khi đã cấp session ACTIVE | Thiết lập session trước thao tác DB có thể thất bại | FIXED ordering; lint PASS, chưa injection fault transaction thật |
| UI-01 | High | shipper.js / shipper-navigation.js | Một click sidebar có hai handler gọi navigate | Module cũ và router mới cùng bind sidebar | FIXED; browser navigation PASS |
| UI-02 | Medium | app.js, auth.php, app.css | Pending request không có thông báo rõ; fetch không có giới hạn chờ phía client | Chỉ disable một số nút, thiếu trạng thái chờ chung | FIXED: status aria-live, aria-busy, controls lock, timeout 30s; không retry tự động thao tác ghi |
| UI-03 | Medium | customer/ui.js | Mỗi lần render local data chờ thêm 380ms | Delay mô phỏng network trong frontend demo | FIXED: render ở lượt event loop kế tiếp; không đổi data source |
| UI-04 | High | admin views, admin.js | 10 script modules/*.js trả 404; filter/search/openModal là hàm không tồn tại | Template gọi module chưa có source | FIXED các handler preview dùng chung; không dựng dữ liệu/API mới |
| UI-05 | Medium | admin.css | Dashboard tablet tràn 1053px ở viewport 768; mobile có thể tự mở rộng layout viewport | Sidebar fixed, flex/grid min-content; không có breakpoint Admin | FIXED: min-width:0/minmax, cuộn bảng, sidebar thích ứng trên mobile |
| UI-06 | Low | shipper.css | Header có thể vượt 375px vài pixel | Tổng độ rộng nút switch/language/avatar và gaps | FIXED spacing mobile, giữ phong cách |
| PERF-01 | Medium | SessionGuard.php, index.php | Nhiều lần round-trip PostgreSQL cho mỗi trang | User, roles, shipper profile, identity được query riêng | FIXED: một query guard lấy các thông tin này; quyền vẫn đọc DB mỗi request |
| PERF-02 | Medium | index.php, database.php | Giữ session lock trong lúc render; thiếu connect_timeout tường minh | Session chỉ tự đóng cuối request; DSN không giới hạn connect | FIXED: session_write_close sau kiểm tra quyền, connect_timeout=8 |
| CLEAN-01 | Low | api/_bootstrap.php | Lặp Content-Type và Fetch Metadata checks | Patch audit trước bị áp dụng trùng một phần | FIXED; input body đọc một lần |
| FUNC-01 | High | Customer/Shipper/Admin | Phần lớn order/payment/tracking/manage vẫn là dữ liệu demo/tĩnh | Chưa có API nghiệp vụ tương ứng; restore DB không tự nối frontend | OPEN; không báo thành công giao dịch thật |
| SEC-01 | High | auth/deployment | Chưa có shared login rate limiter | Source không có shared store/chính sách giới hạn thử login | OPEN; không triển khai limiter giả chỉ dựa trên cookie |
| DEPLOY-01 | Medium | _bootstrap.php / hosting | Chưa xác minh Origin/Secure cookie sau TLS reverse proxy | So sánh scheme từ server; topology proxy chưa kiểm thử | OPEN; cần kiểm thử môi trường đích, không tự tin X-Forwarded-* |

## C. Fixes Applied

| Group | File Modified | Change Description | Verification Result |
|---|---|---|---|
| Login | app.js, shared/auth.php, app.css | Redirect đúng role từ response, storage fallback, lock request, thông báo chờ và phục hồi khi lỗi | Chrome wrong-password/double-submit/storage-denied và unit finishAuth |
| Session/DB | SessionGuard.php, index.php, database.php, login.php | Gom SELECT, trả dữ liệu guard qua tham số, giữ kiểm tra ACTIVE/role/idle/password_changed_at; chỉ cấp session sau last-login thành công | Private HTTP, session security, PHP lint |
| Shipper | shipper.js, shipper.css | Một owner điều hướng sidebar; co header mobile | Avatar/tab/offer detail và responsive browser |
| Customer | customer/ui.js | Bỏ chờ mô phỏng 380ms khi render dữ liệu có sẵn | Browser initialization của tất cả view Customer |
| Admin | admin views, admin.js, index.php, admin.css | Bỏ tham chiếu file thiếu, nạp một script dùng chung; lọc và tìm kiếm kết hợp, empty state, date sort khi có field, modal Users read-only, notification read-state tại trang; sửa responsive | Browser kiểm tra từng view và control được liệt kê trong artifact |
| API cleanup | _bootstrap.php | Gỡ checks trùng, đọc JSON body một lần | Public/API smoke 28 checks |

Admin preview được gắn nhãn rõ là sample data; modal không có nút giả lưu thay đổi. Các handler chỉ thao tác trên row đã có trong HTML, không sinh tài khoản/đơn hàng giả, không thay API thực. Nút quản lý khác, export và hành động backend chưa có API vẫn nằm trong Remaining Issues. Bộ lọc và mark-read preview không được coi là thao tác đã lưu database.

## D. Functional Test Results

- Database: PASS kết nối, SELECT 1, thống kê tổng hợp và metadata trong transaction READ ONLY. Chi tiết `reaudit-db-results.json`; không ghi giá trị secrets hoặc thông tin tài khoản vào báo cáo.
- PHP lint: 58/58 PASS. JavaScript node --check: 21/21 PASS. Không có TypeScript/build manifest. `git diff --check` PASS.
- HTTP public/API: 28/28 PASS, `reaudit-public-http-results.json`.
- HTTP private: 47/47 PASS bằng session test trên localhost. Kiểm tra 13 view Customer, 14 alias/view Shipper, 11 view Admin, role mismatch và GET view dạng array; `reaudit-http-results.json`.
- Session security: 4/4 PASS — expired, thiếu timestamp, tự gán role Admin bị 303; API duyệt shipper với session giả role bị 403 trước transaction ghi; `reaudit-session-security-results.json`.
- Frontend auth unit: PASS role customer/shipper/admin, next xung đột, storage denied, invalid role. Đây là unit logic điều hướng, không phải login mật khẩu.
- Browser: 96/96 PASS, 0 FAIL, 0 BLOCKED và 0 runtime exceptions; từng assertion trong `reaudit-browser-results.json`; bao gồm responsive 1440/768/375, avatar, Offers/detail, private initialization, Admin filters/search/modal và login invalid/double-submit/storage-denied. Assertion offer detail được sửa để chờ DOM sẵn sàng, thay vì giả định DB luôn trả trong 2.8s.
- Password login thành công: **BLOCKED**. Người dùng xác nhận có tài khoản thật các role nhưng chưa cung cấp credentials qua môi trường. `tests/reaudit_login.py` kiểm tra thiếu biến và đã trả BLOCKED; không gọi đó là PASS.

### Cách hiểu session tests

Test harness chỉ đọc user ID/role từ Supabase rồi tạo PHP session trong thư mục test riêng trên máy local. Không thêm endpoint bypass vào app và không sửa role hay mật khẩu trong DB. Đây là bằng chứng render/authorization khi có session, không chứng minh password_verify/cookie handoff sau login thành công. Session files/cookies/browser profile nằm trong `tests/.runtime`, được ignore khỏi Git; không lưu vào báo cáo.

### Số đo độ trễ

| Role | Baseline root trước sửa (1 sample, ms) | Root sau sửa (median 3 samples, ms) | Số SELECT workspace trước → sau |
|---|---:|---:|---|
| Customer | 1453 | 1184 | 2 → 1 |
| Shipper | 1972 | 1093 | 4 → 1 |
| Admin | 1823 | 1467 | 3 → 2 (gồm danh sách chờ duyệt) |

Đây là đo quan sát qua mạng Supabase, mẫu ít và có biến động/tải kiểm thử khác; không phải benchmark kiểm soát. Không kết luận mọi click đều nhanh hơn theo tỷ lệ cố định. Artifact gốc: `reaudit-baseline.json`, `reaudit-performance.json`. Database guard không cache quyền qua request, để role/status thay đổi vẫn có hiệu lực.

## E. Remaining Issues & cách tiếp tục

1. **Mật khẩu thật / login thành công:** cần `SOMI_TEST_IDENTIFIER`, `SOMI_TEST_PASSWORD`, `SOMI_TEST_ROLE` cho tài khoản test riêng; không gửi password vào chat. Chạy `python tests/reaudit_login.py` với PHP localhost 3033 đang dùng session directory test. Script lưu kết quả theo role, kiểm tra login → workspace → logout → route bị chặn; chỉ ghi metadata last-login theo luồng bình thường. Chưa được chạy thành công cho bất kỳ role nào trong đợt này.
2. **Luồng ghi thực tế:** register, verification, reset password, workspace grant/application, approve/reject và email/SMS chưa được test end-to-end bằng tài khoản riêng. Không duyệt hồ sơ hay reset password của tài khoản thật chỉ để test.
3. **Delivery/payment/tracking:** frontend đang demo; nhận đơn Shipper thay array trong memory và không đồng bộ backend qua reload. Các view Admin còn dùng sample rows. Restore Supabase không khắc phục khoảng trống API này. Cần API contract và phạm vi nghiệp vụ trước khi triển khai luồng mới; không mock để che lỗi.
4. **Admin controls chưa có backend:** quản lý trạng thái tài khoản, đơn hàng, complaint/dispute/payment/review, export và badge/avatar/header actions không có endpoint hoàn chỉnh. Adapter mới chỉ khôi phục các tương tác preview được template gọi; không tuyên bố chúng quản lý database thật. Sort dựa trên ngày có sẵn trong row; view không có trường ngày chưa thể chứng minh thứ tự newest/oldest.
5. **Security/deployment:** shared rate limiting, audit password reset concurrency và cookie/origin qua proxy cần kiểm thử tiếp. Chưa build Docker hoặc deploy Apache. Không thay production config.
6. **Network nhỏ:** favicon mặc định chưa có, Chrome có thể yêu cầu `/favicon.ico` và nhận 404. API login 401 trong test sai mật khẩu là kết quả mong đợi; không tính là lỗi API cần che giấu. CDN/fonts ngoài chỉ được ghi nhận theo lượt test, chưa kiểm tra mọi điều kiện mạng.
7. **Responsive:** bài đo overflow và kiểm tra control không thay cho visual QA mọi màn hình, modal dài, bàn phím mobile hoặc mọi dữ liệu đầu vào.
8. **Review:** thay đổi vẫn ở working tree, chưa commit/push/merge. Cần integration-owner review theo TEAM-OWNERSHIP trước merge.

### Chạy lại kiểm thử

Từ root, tạo session directory test và set `SOMI_AUDIT_SESSION_DIR` trỏ tới `tests/.runtime`, chạy `C:/xampp/php/php.exe tests/reaudit_sessions.php`. Chỉ dùng trên máy local.

Server: `C:/xampp/php/php.exe -d session.save_path=D:/Documents/SOMI-Grab-Delivery-Web/tests/.runtime -S 127.0.0.1:3033 index.php`.

- `python tests/audit_smoke.py http://127.0.0.1:3033`
- `python tests/reaudit_http.py`
- `node tests/reaudit_auth_frontend.cjs`
- `C:/xampp/php/php.exe tests/reaudit_db.php`
- Browser: mở Chrome headless với CDP port 9332, profile riêng dưới `tests/.runtime`, rồi `node tests/reaudit_browser.cjs` (Node hỗ trợ WebSocket). Script đóng Chrome test khi xong.
- Real password: cấu hình biến test trong process đang chạy script rồi `python tests/reaudit_login.py`; test này thực hiện đăng nhập thật, gồm cập nhật last-login bình thường.

## F. Final Assessment

| Area | Kết luận dựa trên bằng chứng |
|---|---|
| Code Quality | Syntax PASS; đã gỡ handler/check trùng, khôi phục file JS Admin thiếu và thêm regression scripts |
| Functionality | DB đọc được, private routes render với session và quyền bị kiểm tra; password login thật và nghiệp vụ ghi chưa đủ bằng chứng |
| Security | Bypass session giả Admin/expired bị chặn cả route và API; rate limiting và deployment proxy vẫn cần xử lý |
| Maintainability | Giữ stack, gom query guard và adapter Admin dùng chung, không đổi schema |
| Responsive UI | Đã sửa lỗi overflow tái hiện ở Admin và Shipper; phạm vi PASS theo artifact browser, không chứng nhận mọi trạng thái |
| Deployment Readiness | Chưa đủ bằng chứng để coi là production-ready; cần real-password tests, backend nghiệp vụ và deployment checks |
