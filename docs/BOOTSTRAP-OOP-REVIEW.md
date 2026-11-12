# Bootstrap và thực thể OOP — 13/11/2026

## Nhánh

Đã fetch và merge `origin/dev` vào `MINH-ANH` trước khi sửa code, commit
`f5f7556`. Conflict add/add do hai lịch sử có commit tương đương nhưng khác hash.
Cây mã MINH-ANH trước merge và commit `a7cb123` trong lịch sử dev cùng hash
`6b0272c3da10ea0a60cb6170ee643a564b697004`: dev giữ nguyên baseline Minh Anh.

## Bootstrap

- Front controller nạp Bootstrap CSS/bundle nội bộ cho website công khai,
  Shipper và Admin. Customer có Bootstrap ở shell riêng, không nạp lặp.
- Trang chủ dùng `row g-3`, `col-12 col-md-4` cho ba thẻ tính năng,
  utility flex/wrap/gap cho nút hero.
- Dashboard Shipper dùng `row g-3`, `col-12 col-sm-6 col-xl-3`
  cho thống kê; thanh trạng thái dùng utility flex/spacing.
- CSS riêng giữ màu/chữ/nhận diện. Dialog tự viết đổi sang `somi-modal`
  để tránh hành vi ẩn/toàn màn hình từ Bootstrap `.modal`.
  CSS tương thích giữ chiều cao dải tiến độ và hướng ngang thẻ thống kê.

## Thực thể OOP

Các lớp `app/Domain` dùng namespace `Somi\Domain`, thuộc tính private,
constructor và phương thức có kiểu; không chứa mật khẩu/kết nối DB.

| Thực thể | Nội dung | Backend sử dụng |
| --- | --- | --- |
| `User` | ID, tên, email, điện thoại, trạng thái | `SessionGuard`: tài khoản hoạt động |
| `Shipper extends User` | Phương tiện, KYC, giấy tờ, điểm, availability | `ShipperEligibility`: blockers theo policy hiện hành |
| `Order` | ID, chủ đơn, địa chỉ, phí dạng chuỗi chính xác, tiền tệ, trạng thái | `CustomerOrderService`: quyền hủy; `DeliveryContext`: chuyển trạng thái |

`OrderLifecycleService::TRANSITIONS` tham chiếu bảng trạng thái của `Order`,
không sao chép quy tắc. Quyền sở hữu, khóa DB, transaction, lịch sử và sự kiện
vẫn do service hiện hành phụ trách.

Class Diagram là mô hình khái niệm. Thao tác có IO như login, email, thanh toán
và lưu DB thuộc service; không thêm phương thức entity rỗng để giống sơ đồ.
Chưa chuyển tất cả bảng thành entity hoặc viết lại toàn bộ MVC. Cấu trúc vẫn
là front controller + view PHP + service/repository. PDO và DTO JSON dùng mảng
ở biên lưu trữ/truyền tải, các quy tắc vừa tách dùng đối tượng thực.

## AJAX, dịch vụ ngoài và Admin

Giữ AJAX `fetch`/JSON và adapter gọi dịch vụ ngoài hiện có (Geoapify,
Open-Meteo, SePay theo cấu hình). Không tạo dịch vụ mới để đáp ứng tiêu chí
sử dụng webservice. Admin đã có tạo/quản lý danh mục kiện hàng qua
`AdminService` và `public/api/admin/parcel-categories.php`; không thêm sản phẩm
hoặc bài viết không thuộc nghiệp vụ giao hàng.

## Kiểm tra

- `php tests/domain_entities.php`: 136 kiểm tra qua, không cần DB, gồm toàn bộ
  cặp trạng thái, quyền hủy, ngưỡng điểm và kế thừa tài khoản.
- `node tests/bootstrap_workspace_browser.cjs`: 19 kiểm tra qua trên Chromium,
  viewport 1440/390, grid/dialog/overflow/runtime. API Shipper dùng fixture;
  Bootstrap, CSS, JS và view là mã thật. Ảnh: `tests/.runtime/bootstrap`.
- `node tests/person3_browser.cjs`: 27 kiểm tra qua.
- `node tests/admin_browser.cjs`: 248 kiểm tra qua, 15 view ở 3 kích thước.
- `python tests/person3_http.py`: 33 kiểm tra HTTP guard qua, không đăng nhập.
- Cú pháp PHP toàn bộ `app`, `public/api`, `tests` và `index.php` qua.
- Kiểm tra lại kết nối: DNS Supabase hoạt động ngoài sandbox; lỗi trước đó
  do sandbox chặn mạng. PHP CLI XAMPP chưa bật `pdo_pgsql` mặc định.
  Chạy `php -d extension=pdo_pgsql tests/shipper_matching.php` ngoài sandbox
  đã qua 53 kiểm tra DB; `tests/shipper_delivery.php` qua 58 kiểm tra.
  Chỉ dùng bảng TEMP, không chạy migration hoặc sửa dữ liệu nghiệp vụ thật.
- Chạy lại DB sau khi khắc phục môi trường: `tests/customer_orders.php` qua
  113 kiểm tra, `tests/person1_backend.php` qua 74, `tests/admin_console.php`
  qua 37, `tests/shipper_policy_workflows.php` qua 62 và
  `tests/person3_backend.php` qua 122 kiểm tra. Tổng cộng 519 kiểm tra DB qua.

## Chẩn đoán môi trường DB

`somi_db()` báo rõ khi chưa nạp `pdo_pgsql`, thay vì lỗi hằng số PDO không tồn tại.
Hai script `scripts/test-person1.ps1` và `scripts/test-person3.ps1` tự truyền
`-d extension=pdo_pgsql` nếu PHP CLI chưa bật extension. Không cần sửa php.ini
toàn máy, DNS, IP hay thông tin Supabase trong `.env` để chạy kiểm thử này.
Đã kiểm tra nhánh thiếu driver bằng `php -n`, cơ chế tự nạp driver của runner,
10 kiểm tra chẩn đoán DB và 136 kiểm tra thực thể; tất cả qua.

Trong môi trường sandbox bị hạn chế mạng, cần cấp quyền chạy kiểm thử DB
ngoài sandbox. Đổi hostname hoặc hardcode IP không khắc phục nguyên nhân
hạn chế quyền truy cập. Kết quả DB dùng provider thanh toán/email giả lập;
không chứng minh giao dịch hoặc gửi thư thật.

Playwright dùng môi trường có sẵn qua `NODE_PATH`. Ứng dụng không cần thêm
package hoặc build frontend.
