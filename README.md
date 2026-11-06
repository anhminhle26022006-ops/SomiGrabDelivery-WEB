# SOMI Grab Delivery

## Stack thống nhất
- **PHP 8+**: render trang và điều hướng theo `?page=...`.
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
Project có backend tài khoản/session, email verification/reset và duyệt hồ sơ Shipper dùng PostgreSQL. OTP điện thoại hiện chỉ có chế độ demo cấu hình rõ ràng, chưa có SMS provider thật.

Backend Customer đã bổ sung API địa chỉ, danh mục kiện hàng, báo giá, tạo/xem/hủy đơn, lịch sử trạng thái và integration events cho payment/matching. Hướng dẫn migration, cấu hình provider/giá, request/response và hợp đồng trạng thái nằm trong [docs/DELIVERY-API.md](docs/DELIVERY-API.md). Cấu hình mẫu: [docs/runtime.env.example](docs/runtime.env.example); PHP không tự nạp file này. API báo giá yêu cầu nguồn quãng đường/thời tiết tin cậy và chính sách đã cấu hình, không dùng giá demo.

Bảng giá được duyệt ngày 05/11/2026, phạm vi TP. Hồ Chí Minh, tuyến dưới 40 km và hàng lớn hơn 0 đến 30 kg. Công thức hiện tại: `(cơ bản + km vượt mức + kg vượt mức) × giờ × thời tiết`, làm tròn lên một lần. Cơ bản 20.000đ/99 cent gồm 3 km và 3 kg; mỗi km vượt mức 4.000đ/20 cent; mỗi kg vượt mức 2.500đ/10 cent. Xem [cấu hình đã duyệt](docs/DISTANCE-WEIGHT-PRICING-PROPOSAL.md). Trang Pricing có đầy đủ VI/EN và công cụ minh họa có trọng lượng; Customer demo dùng cùng cấu hình. Migration runner phát hành policy có version/audit và chạy lại an toàn. Provider trực tiếp cần GOOGLE_MAPS_API_KEY bật Routes/Geocoding và OPEN_METEO_API_KEY; chưa có key sẽ không giả lập báo giá thật.

Giao diện Customer/Shipper/Admin còn có dữ liệu demo và chưa nối các API đơn hàng mới. Payment/refund/payout, matching/giao nhận, chat/ticket và workers chưa hoàn thành; các thao tác demo không đại diện cho giao dịch thực.

## Kiểm thử backend Customer

Yêu cầu PHP có PDO PostgreSQL, mbstring, cURL và BCMath. Chạy:

```bash
php tests/customer_orders.php
```

Bộ kiểm thử dùng PostgreSQL TEMP tables và rollback toàn bộ, không thay dữ liệu nghiệp vụ trong schema ứng dụng. Không cần áp dụng migration thật để chạy bộ kiểm thử này.
