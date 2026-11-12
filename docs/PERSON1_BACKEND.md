# Chạy backend Người 1

> Historical snapshot. Current integration, approved pricing, SePay/payment/email APIs and ownership: [PERSON1-API.md](PERSON1-API.md), [BACKEND-SCOPE.md](BACKEND-SCOPE.md).


## Runtime và môi trường

Giữ PHP 8.3+ / PDO PostgreSQL / PHP Session / vanilla JavaScript. Không thêm framework hoặc package manager. Runtime Windows kiểm thử 8.4.27, PostgreSQL test 16 trong Docker. Dockerfile production vẫn PHP 8.3 Apache; image ứng dụng chưa build trong đợt này.

Extensions: pdo_pgsql, mbstring; curl và openssl cho email/mã hóa giấy tờ. `scripts/serve.ps1` bật pdo_pgsql và mbstring từ thư mục ext của PHP trên Windows. Nếu cần đăng ký/email trên bản PHP chưa bật curl/openssl, bật thêm hai extension trong php.ini. Không thay key mã hóa của dữ liệu đang có.

App đọc process environment; `.env.example` là tài liệu, không được tự động nạp. Không lưu mật khẩu vào repository. Script serve chỉ nạp các biến DB từ Windows User nếu process chưa có giá trị; không ghi lại Windows environment. `SOMI_APP_URL`, RESEND và khóa mã hóa phải được cung cấp cho process khi dùng những chức năng đó.

```powershell
./scripts/serve.ps1
```

## Migration

Baseline chuẩn: `database/SOMI_database.sql`. `database/schema.sql` là bản tham khảo lịch sử, không chạy nối tiếp hai baseline. Runner chỉ cài baseline khi chưa có bảng somi.users và có cờ `--baseline`. Với DB đã tồn tại, runner áp dụng các migration chưa ghi nhận theo tên; kiểm checksum SHA-256, dùng advisory lock để tránh hai runner đồng thời, mỗi migration là một transaction. Không tự sửa checksum hay xóa dữ liệu để chạy tiếp.

```powershell
# Chạy trong môi trường đã có DB environment và extensions PHP cần thiết:
php scripts/migrate.php --baseline  # Database sạch
php scripts/migrate.php             # Database hiện hữu
```

Migration mới `20261102_person1.sql` thêm yêu cầu quốc tịch, cấu hình giá nháp và permission quản trị tra cứu. Không thêm bảng giá mẫu hoặc tài khoản Admin. Bản Supabase hiện tại chỉ được kiểm tra đọc; migration mới CHƯA áp dụng lên Supabase. Admin accounts/orders và nationality/pricing cần migration này trước khi sử dụng ở đó. Role DB backend cần quyền trên schema/tables/sequences; không dùng anonymous client để ghi trực tiếp. Hai bảng mới bật RLS, không cấp chính sách public; kiểm thử với backend role PostgreSQL. Cần review quyền service role thực tế trước deploy.

## Kiểm thử cô lập

Không chạy script seed hoặc service/concurrency tests với Supabase. Các script ghi có guard bắt buộc host `127.0.0.1` và database `somi_person1_test`. Guard tên/host không thay cho việc người chạy xác nhận đúng môi trường.

```powershell
docker run --detach --name somi-person1-test `
  -e POSTGRES_HOST_AUTH_METHOD=trust -e POSTGRES_DB=somi_person1_test `
  -p 127.0.0.1:55432:5432 postgres:16-alpine

# Terminal 1: máy chủ test, DB environment riêng, cookie và session directory riêng
./scripts/test-person1.ps1 -ServeOnly

# Terminal 2: migration, fixture, service, HTTP, concurrency và DB assertions
./scripts/test-person1.ps1

node tests/reaudit_auth_frontend.cjs
php tests/request_origin.php
```

Trust authentication chỉ cho container test local, không dùng cho production. Fixture là dữ liệu thử do script tạo trên DB riêng; không phải chính sách/giá/người dùng thật. `tests/.runtime` bị Git ignore, bao gồm mật khẩu test ngẫu nhiên và session. Chạy lại tạo bộ fixture mới; không tự xóa các bộ trước. Server test ở 3034, server Supabase ở 3000. Có thể dừng container bằng `docker stop somi-person1-test` khi không cần; script không tự xóa container/volume.

## Kiểm tra Supabase chỉ đọc

`tests/person1_schema.php` chỉ dùng transaction READ ONLY và metadata. Đợt này xác nhận đủ 23 bảng điều kiện tiên quyết, chưa có hai bảng migration mới. Không test hủy đơn, khóa tài khoản, reset mật khẩu hoặc KYC bằng tài khoản thật.

## Màn hình

- Customer: `?page=customer&view=backend`: hồ sơ, địa chỉ có tọa độ, đơn/history/hủy chưa có thanh toán, yêu cầu quốc tịch.
- Admin: `?page=admin&view=backend`: tài khoản, đơn, hạn chế loại hàng, giá nháp, KYC và yêu cầu quốc tịch.
- Trang cũ vẫn là demo. Không trộn order code hoặc trạng thái demo vào API thật. Các màn hình mới chưa hoàn tất toàn bộ bản dịch/responsive và chưa thay thế tất cả luồng UI cũ.
