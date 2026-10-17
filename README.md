# SOMI Grab Delivery — Complete Frontend Demo

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
Đây là demo frontend. Đăng nhập, OTP, eKYC, thanh toán, lưu trữ chat, phân quyền Admin và theo dõi giao hàng chưa kết nối backend/database. Các thao tác demo không đại diện cho giao dịch thực.
