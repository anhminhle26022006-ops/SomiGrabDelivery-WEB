# Kiểm thử chức năng ngày 07/11/2026

Đợt kiểm tra sau commit `e86b4fb`. Kết quả dưới đây thuộc mã nguồn local với các sửa lỗi đang chờ triển khai; không xác nhận Render đã chạy bản sửa.

## Lỗi đã tái hiện

Mở kết nối PostgreSQL thất bại: `SQLSTATE[08006]`, `EMAXCONNSESSION`, `max clients reached in session mode`, `pool_size: 15`. Cấu hình trước đó cho phép 40 PHP worker persistent/container. Pool đầy khiến cả API và workspace trả 503, không phụ thuộc riêng trang tạo đơn. Không tự đóng kết nối của người dùng khác hoặc chạy tải lên database này.

Sửa: persistent chuyển sang opt-in, mặc định 0; giới hạn worker mặc định 6; FPM ondemand thoát sau 10 giây rảnh; log phân loại connection_limit/connection_failure/schema_mismatch bằng nhãn cố định; rollback không che lỗi mất kết nối. Không tự chạy lại giao dịch ghi khi chưa biết commit đã thành công hay chưa.

**Render cần đặt `SOMI_DB_PERSISTENT=0`, `SOMI_MAX_WORKERS=6` và restart/redeploy.** Environment đã đặt trên Render ghi đè giá trị mặc định của source/Docker. Tổng mọi instance, background worker và công cụ quản trị vẫn phải nằm trong ngân sách pool 15; 6 không bảo đảm đủ nếu có nhiều instance hoặc ứng dụng dùng chung pool.

## Kết quả thực chạy

| Nhóm | Kết quả | Phạm vi thực tế |
|---|---|---|
| PHP lint | PASS, 154 file | Cú pháp PHP, không xác nhận hành vi runtime |
| JavaScript Customer | PASS | `node --check` server.js |
| Nạp environment | PASS, 14 | Ưu tiên process env, dữ liệu literal, lỗi định dạng |
| Chẩn đoán database | PASS, 10 | Giới hạn pool, SQLSTATE, không lộ raw exception, rollback mất kết nối |
| SePay | PASS, 40 | Signature, cấu hình, callback fixture; không gọi SePay thật |
| Nguồn báo giá | PASS, 23 | Provider fixture và lỗi nguồn dữ liệu |
| Origin | PASS, 21 | Quy tắc origin/proxy |
| Giá trên frontend | PASS | Quãng đường, trọng lượng, làm tròn, giới hạn 40 km/30 kg, tiền tệ |
| Auth frontend | PASS | Chuyển hướng role, lỗi storage, role không hợp lệ |
| Public pricing trên Chrome | PASS, 44 | VI/EN, tính giá và giao diện; không tạo đơn |
| HTTP không đăng nhập | PASS, 37 | 7 trang công khai, chặn 7 đường dẫn nội bộ, 15 API cần auth, 5 method sai, 3 origin sai |
| Customer service | BLOCKED | Suite 85 check bị chặn tại mở DB, chưa chạy assertion nghiệp vụ đợt này |
| Backend người 1 | BLOCKED | Suite 74 check bị chặn tại mở DB |
| Migration tương thích | BLOCKED | Suite 5 check bị chặn tại mở DB |
| HTTP có session/role | BLOCKED | Qua 15 kiểm tra 401; kiểm tra role đầu tiên nhận 503 do pool đầy |
| Customer UI fixture | Chưa chạy lại | 47 check từng qua trước đợt này; cần HTML workspace có session và DB hoạt động |
| Concurrency/load | Chưa chạy | Bộ cũ yêu cầu PostgreSQL local cách ly; máy này chưa có. Không dùng Supabase đang đầy để chạy tải |

Không cộng các check bị chặn hoặc kết quả lịch sử vào số PASS đợt này. Tổng PASS có đếm assertion: **189**, cộng hai bộ helper JS và kiểm tra cú pháp; 15 HTTP 401 lặp lại trong bộ có session không cộng lần nữa.

## Phần cần kiểm tra tiếp sau khi giải phóng pool

1. Chạy lại `tests/customer_orders.php`, `tests/person1_backend.php`, `tests/migration_compat.php` từ bản test không chứa `.env` local; các suite dùng TEMP tables, không ghi bảng nghiệp vụ thật.
2. Tạo session test mới bằng `tests/reaudit_sessions.php` vào thư mục session riêng rồi chạy `tests/person1_http.py` và `tests/person1_browser.cjs`. Session fixture không chứng minh login bằng mật khẩu.
3. Trong DB test cách ly: đăng ký, xác minh email/OTP, login/logout/reset password, đổi role, quyền Admin/Customer/Shipper, địa chỉ CRUD/default/archive, báo giá và hết hạn, tạo đơn/retry cùng idempotency key, danh sách/chi tiết/hủy đơn, thanh toán/callback lặp/sai số tiền/late payment/refund, worker retry và tranh chấp giao dịch.
4. Với môi trường sandbox đã cấu hình: Google Routes/Geocoding, Open-Meteo, checkout SePay → IPN → trạng thái thanh toán và đơn. Chưa có bằng chứng roundtrip sandbox thật trong đợt này. SMS chưa có nhà cung cấp; không ghi nhận gửi SMS thật thành công.
5. Shipper matching/GPS/proof-of-delivery, báo cáo, đánh giá và các màn demo cần kiểm tra theo backend tương ứng; không coi màn demo là chức năng đã hoàn tất.

## Lệnh kiểm tra

PHP local: `C:/xampp/php/php.exe`. `scripts/test-person1.ps1 -Php C:/xampp/php/php.exe` chạy bộ service hiện hành và dừng khi gặp lỗi. `tests/deployment_smoke.py` dùng server local 127.0.0.1:3047, không login, không ghi nghiệp vụ hoặc gọi provider. Chrome pricing dùng CDP 9340. Tất cả artifacts/session test nằm trong `tests/.runtime/` đã ignore.

File `.env` local đang có lỗi cú pháp dòng 7; không sửa hoặc in các key của người dùng. Các test DB/HTTP chạy bằng bản sao sạch không chứa `.env`, dùng process environment. Cần sửa dòng này trước khi chạy trực tiếp từ checkout local.
