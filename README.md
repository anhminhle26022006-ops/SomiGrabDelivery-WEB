# SOMI Grab Delivery — Complete Frontend Demo

## Stack thống nhất
- **PHP 8.3+**: render trang và điều hướng theo `?page=...` (Docker: 8.3; local đã kiểm thử: 8.4).
- **HTML**: cấu trúc giao diện.
- **CSS**: giao diện responsive và style riêng cho từng module.
- **JavaScript thuần (Vanilla JS)**: tương tác trình duyệt.

Dự án không cần framework frontend, `npm install` hay build frontend.

## Chạy bằng XAMPP
1. Giải nén toàn bộ nội dung ZIP vào `C:\\xampp\\htdocs\\SOMI-Grab-Delivery`.
2. Mở XAMPP và Start Apache.
3. Truy cập `http://localhost/SOMI-Grab-Delivery/`.

## Chạy bằng PHP built-in server
Mở terminal tại thư mục có `index.php`, chạy:

```bash
php -S localhost:3000 index.php
```

Truy cập `http://localhost:3000/`. Router cho phép PHP built-in server phục vụ trực tiếp các file CSS/JS tĩnh.

## Các trang
- `/?page=home`, `how`, `customers`, `shippers`, `pricing`: website công khai.
- `/?page=auth`: đăng nhập/đăng ký demo.
- `/?page=customer`: giao diện Customer.
- `/?page=shipper`: giao diện Shipper.
- `/?page=shipper&view=offers`, `active`, `history`, `earnings`, `account`: mở trực tiếp từng màn hình Shipper.
- `/?page=admin`: giao diện Admin.

## Cấu trúc
- `app/Views/shared/`: website công khai và xác thực dùng chung.
- `app/Views/customer/`: giao diện Customer.
- `app/Views/shipper/`: giao diện Shipper.
- `app/Views/shipper/pages/dashboard.php`: trang Tổng quan/Dashboard riêng.
- `app/Views/shipper/pages/`: các trang Shipper được tách thành PHP riêng; shell chỉ nạp trang theo `view`.
- `app/Views/admin/`: giao diện Admin.
- `public/assets/css/`: CSS dùng chung và CSS riêng từng module.
- `public/assets/js/app.js`: tương tác dùng chung.
- `public/assets/js/shipper.js`: tương tác riêng Shipper.
- `public/assets/js/shipper-navigation.js`: điều hướng Shipper, hỗ trợ URL `view` và nút back/forward của trình duyệt.

## Phạm vi
Authentication dùng PHP Session và PostgreSQL thật. SMS hiện chỉ có chế độ demo; email cần cấu hình nhà cung cấp. Customer và Admin có màn hình dữ liệu thật tại `?page=customer&view=backend` và `?page=admin&view=backend`; các màn hình demo cũ vẫn dùng dữ liệu cục bộ và chưa được thay thế toàn bộ.

Báo giá/tạo đơn thật đang bị chặn do chưa thống nhất chính sách giá, nguồn khoảng cách/thời tiết và tiền tệ. Matching, giao nhận, thanh toán và refund thuộc Người 2/3; không coi dữ liệu mô phỏng là giao dịch thật.

## Tình trạng project (cập nhật 2026-11-07)

**Nhánh**
- `dev` là nhánh tích hợp mới nhất (commit `2ceda53`, 2026-11-02), đã gộp `UI-ADMIN` (PR #2) và `feature/customer-ui` (PR #3–#5).
- `main` đang chậm hơn `dev` 30 commit (lần cập nhật cuối 2026-09-28, vẫn là bản demo React/Vite cũ) — cần PR `dev → main` khi nhóm chốt bản phát hành.
- `backup/dev-before-php` chỉ là bản lưu trước khi chuyển sang PHP, không merge lại.

**Theo module**

| Module | Phụ trách | Tình trạng |
|---|---|---|
| Website công khai + xác thực | Người 1 / Integration | Đăng ký, đăng nhập email/SĐT, đăng xuất, quên/đặt lại mật khẩu, đổi workspace dùng PHP Session + PostgreSQL thật. SMS chỉ demo; email cần cấu hình Resend. |
| Backend Người 1 (Customer/Admin core) | Người 1 | 32/68 task DONE, 28 PARTIAL, 8 BLOCKED (xem [tiến độ](docs/PERSON1_PROGRESS.md)). 112 checks kiểm thử, 0 lỗi trên DB test riêng. |
| Customer UI | Customer owner | Đủ màn hình Sprint 2 (thanh toán, tìm shipper, theo dõi, đánh giá, báo cáo, thông báo) nhưng phần lớn vẫn dữ liệu demo/localStorage; dữ liệu thật tại `?page=customer&view=backend`. |
| Shipper UI | Shipper owner | Giao diện các trang offers/active/history/earnings/account hoàn chỉnh, dữ liệu demo; chưa có API backend. |
| Admin UI | Admin owner | Các màn hình quản trị đầy đủ; dữ liệu thật (tài khoản, đơn, danh mục, KYC, bảng giá nháp) tại `?page=admin&view=backend`. |
| Matching / giao nhận | Người 2 | Chưa có API trong repo. |
| Thanh toán / refund | Người 3 | Chưa có API trong repo. |

**Đang bị chặn / việc tiếp theo**
1. Chốt chính sách giá (công thức, tiền tệ, nguồn khoảng cách/thời tiết, làm tròn) → mở khóa báo giá và tạo đơn thật (`quote.php`, `create-order.php` hiện trả 409).
2. Người 2/3 xác nhận [hợp đồng tích hợp](docs/PERSON1_INTEGRATION.md) và triển khai API matching, giao hàng, thanh toán/refund.
3. Review rồi áp migration `database/migrations/20261102_person1.sql` lên Supabase (chưa áp; một số API Admin mới chưa chạy được trên DB hiện tại).
4. Thay dần các màn hình demo bằng dữ liệu thật; build Docker PHP 8.3 và kiểm thử deploy.

Chưa sẵn sàng production.

## Backend và kiểm thử Người 1

Hiệu năng, cấu hình Docker production (Apache event + PHP-FPM) và kiểm thử giao diện/tải: xem [PERFORMANCE.md](docs/PERFORMANCE.md).

Xem [hướng dẫn backend](docs/PERSON1_BACKEND.md), [tiến độ](docs/PERSON1_PROGRESS.md), [API](docs/PERSON1_API_CONTRACT.md) và [hợp đồng tích hợp](docs/PERSON1_INTEGRATION.md).

Biến môi trường được mô tả trong `.env.example`; app đọc process environment, không tự nạp file `.env`. Trên Windows, chạy `./scripts/serve.ps1` để nạp cấu hình User và bật PostgreSQL/mbstring cho PHP.
