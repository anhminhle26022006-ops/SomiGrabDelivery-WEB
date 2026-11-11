# Minh Anh — Người 3: tình trạng nghiệm thu

Cập nhật 12/11/2026, nhánh `MINH-ANH`. Bản chức năng chính: `3b6a9ee`. Bản hoàn thiện Supabase/OTP/quyền file: `934df4e`, đã commit và đẩy lên origin/MINH-ANH.

## Đã hoàn thiện trong source

Đối chiếu Render ngày 12/11: service `SomiGrabDelivery_website` theo nhánh `dev`. Bản tích hợp `cb11408` đã deploy và asset public khớp source. Bản sửa worker `32a57d7` đã Live: Supervisor đặt HOME đúng cho www-data, khắc phục lỗi libpq tìm chứng chỉ trong /root. Log xác nhận Person3 xử lý 4 events, 0 earnings và worker email thử 5 jobs. Chưa coi số jobs đã thử là email đã đến inbox hoặc có thu nhập phát sinh.

- Geoapify/Leaflet, CRUD/default địa chỉ và API địa lý dùng chung Customer/Shipper.
- Chat theo đơn, phân trang, read receipt, file gắn từng tin nhắn; chỉ người có quyền theo đơn được tham gia.
- Ticket theo đơn/tài khoản, lịch sử, phản hồi hai chiều và đọc chat/file hỗ trợ có audit.
- Notification VI/EN, unread/mark-all, consumer chống trùng, backoff và retry sự kiện lỗi.
- Storage Supabase private, upload MIME whitelist 5 MiB, SHA-256, signed download ràng buộc user, retention; giữ khả năng đọc file local cũ.
- Tài khoản ngân hàng mã hóa, đổi ngân hàng bằng mật khẩu hiện tại và OTP email; cooldown, expiry, attempt limit và retry an toàn.
- Earning theo policy có hiệu lực và payment/giao nhận/refund; payout Admin chuyển khoản + đối soát, ghi nhận thất bại/retry và FX có chứng từ.
- UI Customer/Shipper/Admin nối API, bao gồm OTP/resend, file chat, ticket reply, earnings, FX, payout và sự kiện lỗi.
- Migrations, worker, scheduler, cấu hình Docker/Supervisor, OpenAPI và lệnh kiểm thử riêng.

## Các sửa trong đợt rà soát cuối

- Xin OTP mới tạo client_key mới; không lấy lại challenge hết hạn.
- Bộ chọn upload dùng đơn/ticket thuộc quyền, ẩn KYC/EVIDENCE với Customer.
- Admin không liệt kê hoặc tải draft CHAT chưa gửi, dù có ticket liên quan.
- Chat tải metadata file theo batch thay vì truy vấn riêng cho từng tin nhắn.
- Mật khẩu đổi ngân hàng được đối chiếu nguyên vẹn, không tự trim khoảng trắng.
- Tương thích Supabase REST: HTTP400/statusCode404 khi chưa có bucket; bodyless DELETE không gắn Content-Type JSON.

## Bằng chứng kiểm thử

| Bộ kiểm tra | Đạt |
|---|---:|
| Backend Người 3, TEMP tables | 122 |
| Chrome UI, HTTP fixture | 24 |
| HTTP guard trên loopback | 24 |
| Multipart upload/download/retention local | 23 |
| Adapter Supabase, transport fixture | 14 |
| Hồi quy Admin | 37 |
| Hồi quy Người 1 | 74 |

PHP/JS thay đổi và PowerShell test runner đã kiểm cú pháp; git diff --check đạt. Chưa build/chạy Docker thực tế vì máy không có Docker được phát hiện. Fixture không chứng minh inbox delivery hoặc chuyển tiền thật.

## Đã kiểm chứng với Supabase thật

- SOMI_DB_* kết nối PostgreSQL Supabase đang có; không tạo lại database.
- Ba migration Người 3 đã áp dụng, bao gồm metadata backend/bucket.
- Bucket `somi-private` đã tạo với public=false, MIME whitelist và giới hạn 5 MiB.
- Đã upload PDF tự sinh không có dữ liệu cá nhân, tải bằng API authenticated, đối chiếu SHA-256 và xóa đúng object mẫu thành công.
- Local `person3-check.php` trả `ready: true`, `supabase_private_bucket: true`.
- `.env` vẫn ignored; không đưa key, OTP hoặc dữ liệu ngân hàng thật vào Git.

## Hosting — chưa đánh dấu hoàn tất

Domain: https://somigrabdelivery.online

Kiểm tra GET chưa đăng nhập gần nhất: API notifications, bank-account và person3-jobs trả 401 JSON. Điều này xác nhận route hoạt động và yêu cầu đăng nhập, không chứng minh chức năng sau đăng nhập. Lần đọc trang chủ có timeout; không kết luận site ngừng hoạt động từ một lần timeout.

Asset public đã đối chiếu khớp bản triển khai sau chuẩn hóa CRLF/LF. Render Environment đã có tên các biến driver, URL, server key, bucket, signing key, payout mode và run-workers. Worker đã chạy thành công trên hosting; startup scheduler in các kiểm tra boolean để xác minh giá trị cấu hình mà không lộ secret. Nghiệm thu có đăng nhập và OTP inbox vẫn còn cần thực hiện.

## Checklist phát hành/nghiệm thu còn lại

- [x] Review, quét secret, commit và push bản hoàn thiện `934df4e` lên `MINH-ANH`.
- [x] Deploy bản tích hợp và sửa worker lên service Render theo nhánh dev đã được người dùng xác nhận.
- [x] Render `7820697` báo ready:true; payout_mode_manual, storage_signing_key, supabase_private_bucket và migrations_applied đều true. Worker hoàn thành chu kỳ lúc 03:06:18 GMT+7 ngày 12/11, không có lỗi.
- [ ] Kiểm thử qua phiên Customer/Shipper/Admin: chat/file/ticket/read receipt/notification và quyền tài nguyên chéo.
- [ ] Nhận OTP đổi ngân hàng trong hộp thư test; kiểm tra sai mã, mã hết hạn và xin mã mới.
- [ ] Chạy luồng matching/giao/hoàn bằng assignment và sự kiện thật của Người 2.
- [ ] Admin thực hiện chuyển khoản và đối soát khoản test đã được nhóm chọn; PROCESSING không được coi là đã chuyển tiền.
- [ ] Kiểm map trên điện thoại và domain hosting, quota/mạng và quyền key.

Admin do người dùng cung cấp: `admin@somigrabdelivery.com`. Email không tự cung cấp phiên đăng nhập hoặc quyền Shipper. Không tạo session giả, không lấy password/OTP thật đưa vào tài liệu.

## Cấu hình Render cần có

```dotenv
SOMI_STORAGE_DRIVER=supabase
SUPABASE_URL=https://<project-ref>.supabase.co
SUPABASE_SECRET_KEY=<server-secret-key>
SUPABASE_STORAGE_BUCKET=somi-private
SOMI_STORAGE_SIGNING_KEY=<giu-khoa-da-cau-hinh>
SOMI_PAYOUT_MODE=manual
SOMI_RUN_WORKERS=1
SOMI_MIGRATE_ON_START=1
```

Có thể dùng SUPABASE_SERVICE_ROLE_KEY thay cho SUPABASE_SECRET_KEY. Không thay các khóa mã hóa đã có. Supabase Storage không cần persistent disk Render cho upload mới. Các file cũ mang backend local vẫn cần đúng nơi lưu cũ hoặc quy trình di chuyển được kiểm chứng.

## Lệnh kiểm tra

```powershell
& "C:\xampp\php\php.exe" -d extension=pdo_pgsql bin/person3-check.php
& "C:\xampp\php\php.exe" bin/supabase-storage.php
./scripts/test-person3.ps1 -Php "C:\xampp\php\php.exe" -NodeModules "<thu-muc-node_modules-co-playwright>"
```

Test runner dùng TEMP tables; không gửi mail/chuyển tiền thật. `-SkipBrowser` chỉ bỏ phần Chrome, không có nghĩa bộ UI đã đạt.

Tài liệu chi tiết: [PERSON3-API.md](PERSON3-API.md). Đặc tả import Postman/Swagger: [person3-openapi.json](person3-openapi.json).

Đã dùng phiên Render do người dùng mở để xác minh service, branch, deploy và log worker. Phiên đăng nhập website Customer/Shipper/Admin để nghiệm thu nghiệp vụ vẫn chưa được cung cấp; không gửi mật khẩu qua chat.
