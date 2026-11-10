# SOMI Grab Delivery

Người 3 (Minh Anh): chat/ticket, notification, private Storage tùy chọn, earnings và payout Admin chuyển khoản/đối soát: [API, cấu hình và bàn giao](docs/PERSON3-API.md). Chạy migration trước API mới; worker CLI riêng `bin/person3-worker.php`.

> Update 06/11/2026: Person 1 payment/email/Admin APIs and live Customer flow are implemented. SePay is selected; USD checkout is disabled. Current contracts, setup, tests and live-provider limitations: [PERSON1-API.md](docs/PERSON1-API.md).


Admin trực tiếp trên nhánh MINH-ANH: [chức năng, API và kiểm thử](docs/ADMIN-LIVE.md). Mở `/admin` bằng tài khoản có role ADMIN; các trang quản trị đọc dữ liệu thật từ Supabase.

## Stack thống nhất
- **PHP 8+**: render trang và điều hướng theo `?page=...`.
- **HTML**: cấu trúc giao diện.
- **CSS**: giao diện responsive và style riêng cho từng module.
- **JavaScript thuần (Vanilla JS)**: tương tác trình duyệt.

Dự án không cần framework frontend, `npm install` hay build frontend.

## Cấu hình key và môi trường

File `.env` tại root được PHP tự nạp cho web/API và worker. Dùng [.env.example](.env.example) làm mẫu khi clone mới; điền key tại máy, không commit `.env`. Biến môi trường server/process được ưu tiên, kể cả khi được đặt rỗng. Giá trị trong file là literal, không nội suy `${VARIABLE}`. Giữ khóa mã hóa cũ để đọc được dữ liệu đã mã hóa. Docker không đóng gói file này; truyền cấu hình khi chạy container bằng `--env-file .env` hoặc hệ thống Secrets của hosting.

Địa chỉ, bản đồ Leaflet + Geoapify và Supabase: [hướng dẫn cấu hình](docs/ADDRESS-GEOAPIFY-SETUP.md). Điền GEOAPIFY_API_KEY cho backend và GEOAPIFY_BROWSER_API_KEY cho tiles trình duyệt. Kiểm tra bằng `php bin/address-check.php`; luồng hiện tại không cần key Google.

Kiểm tra SePay: `php bin/sepay-check.php`. Hướng dẫn [SePay sandbox](docs/SEPAY-SETUP.md).

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
- `/?page=auth`: đăng nhập/đăng ký có backend tài khoản; phone OTP vẫn demo khi chưa có SMS provider.
- `/?page=customer`: giao diện Customer.
- `/?page=shipper`: giao diện Shipper.
- `/?page=shipper&view=offers`, `active`, `history`, `earnings`, `account`: mở trực tiếp từng màn hình Shipper.
- `/?page=admin`: giao diện Admin.

## Cấu trúc
- `app/Views/shared/`: website công khai và xác thực dùng chung.
- `app/Views/customer/`: giao diện Customer.
- `app/Views/shipper/`: giao diện Shipper.
- `app/Views/shipper/pages/dashboard.php`: trang Tổng quan/Dashboard riêng.
- `app/Views/shipper/pages/`: section Shipper tách thành PHP riêng; shell hiện nạp các section để điều hướng client theo `view`.
- `app/Views/admin/`: giao diện Admin.
- `public/assets/css/`: CSS dùng chung và CSS riêng từng module.
- `public/assets/js/app.js`: tương tác dùng chung.
- `public/assets/js/shipper.js`: tương tác riêng Shipper.
- `public/assets/js/shipper-navigation.js`: điều hướng Shipper, hỗ trợ URL `view` và nút back/forward của trình duyệt.

## Phân công backend hiện hành — 06/11/2026

- **Người 1:** nền tảng/DB/auth, Customer và đơn, công thức giá/chính sách, payment/callback/đối soát/refund, toàn bộ email, Admin cốt lõi/duyệt KYC, guard/audit chung và tích hợp.
- **Người 2:** profile/vehicle/KYC submission, availability, matching/offer/assignment, giao nhận/hoàn/bằng chứng và GPS.
- **Người 3:** Google Cloud/Places/Geocoding/Routes, CRUD địa chỉ Customer, Storage nếu dùng, chat/ticket, earnings/payout và in-app notification.

Code địa chỉ và adapter Google đã có để Người 3 tiếp quản, không viết lại endpoint trùng. Refund Customer thuộc Người 1, payout Shipper thuộc Người 3; email Người 1 tách khỏi in-app Người 3. Owner riêng Open-Meteo chưa ghi trong bản mới, cần nhóm chốt trước khi tách adapter.

Chi tiết: [scope/checklist/bàn giao](docs/BACKEND-SCOPE.md), [file ownership](TEAM-OWNERSHIP.md), [mục lục tài liệu](docs/README.md), [nối UI Customer](docs/CUSTOMER-INTEGRATION.md). Báo cáo lịch sử được gắn nhãn riêng, không dùng thay phân công hiện hành.

## Phạm vi
Project có backend tài khoản/session, email verification/reset và duyệt hồ sơ Shipper dùng PostgreSQL. OTP điện thoại hiện chỉ có chế độ demo cấu hình rõ ràng, chưa có SMS provider thật.

Backend Customer đã bổ sung API địa chỉ, danh mục kiện hàng, báo giá, tạo/xem/hủy đơn, lịch sử trạng thái và integration events cho payment/matching. Hướng dẫn migration, cấu hình provider/giá, request/response và hợp đồng trạng thái nằm trong [docs/DELIVERY-API.md](docs/DELIVERY-API.md). Cấu hình mẫu: [docs/runtime.env.example](docs/runtime.env.example); PHP không tự nạp file này. API báo giá yêu cầu nguồn quãng đường/thời tiết tin cậy và chính sách đã cấu hình, không dùng giá demo.

Bảng giá được duyệt ngày 05/11/2026, phạm vi TP. Hồ Chí Minh, tuyến dưới 40 km và hàng lớn hơn 0 đến 30 kg. Công thức hiện tại: `(cơ bản + km vượt mức + kg vượt mức) × giờ × thời tiết`, làm tròn lên một lần. Cơ bản 20.000đ/99 cent gồm 3 km và 3 kg; mỗi km vượt mức 4.000đ/20 cent; mỗi kg vượt mức 2.500đ/10 cent. Xem [cấu hình đã duyệt](docs/DISTANCE-WEIGHT-PRICING-PROPOSAL.md). Trang Pricing có đầy đủ VI/EN và công cụ minh họa có trọng lượng; Pricing minh họa dùng cấu hình đã duyệt; báo giá xác nhận lấy policy active tại server. Migration runner phát hành policy có version/audit và chạy lại an toàn. Provider trực tiếp cần GEOAPIFY_API_KEY cho reverse geocoding/routing xe máy và Open-Meteo thời tiết. Với đồ án, `OPEN_METEO_MODE="free"` không cần key thời tiết; chế độ `commercial` cần `OPEN_METEO_API_KEY`. Kiểm tra kết nối thời tiết bằng `php bin/weather-check.php`; không giả lập báo giá thật khi provider lỗi.

Luồng Customer chính đã nối API thật qua server.js; payment SePay, refund ledger/đối soát, email queue và API Admin Người 1 đã có. SePay refund cần operator xử lý về phương thức gốc rồi đối soát chứng từ; chưa có tự động chuyển tiền refund Gateway. Shipper, chat/ticket/in-app/payout và một số view legacy vẫn cần Người 2/3 nối backend. Các thao tác demo không đại diện giao dịch thực.

## Kiểm thử backend Customer

Yêu cầu PHP có PDO PostgreSQL, mbstring, cURL và BCMath. Chạy:

```bash
php tests/customer_orders.php
php tests/person1_backend.php
```

Bộ kiểm thử dùng PostgreSQL TEMP tables và rollback toàn bộ, không thay dữ liệu nghiệp vụ trong schema ứng dụng. Không cần áp dụng migration thật để chạy bộ kiểm thử này.

## Tích hợp bản mới — 07/11/2026

Đã giữ các cập nhật cờ ngôn ngữ, bố cục Shipper, định dạng VND và tối ưu PostgreSQL/Apache event/PHP-FPM từ nhánh dev. Docker bổ sung BCMath cho tính giá và đối soát chính xác. Hiệu năng: [PERFORMANCE.md](docs/PERFORMANCE.md).

`dev` là nhánh tích hợp; `main` vẫn là bản cũ và không được ghi đè tự động. Render cần theo dõi đúng nhánh `dev`. Key `.env` local không lên GitHub/Docker: đặt các biến tương ứng trong Render Environment. API IPN: `/public/api/payments/callback.php`.

Runner migration thống nhất: `php database/migrate-customer-orders.php --apply` (alias `php scripts/migrate.php`). Ledger mới `somi.backend_migrations` giữ lại cả hai dạng ledger cũ, nhập lịch sử version hiện có, không xóa ledger cũ. Migration tương thích giữ yêu cầu quốc tịch cũ và màn hình giá nháp; nháp không thay policy đã duyệt. Chạy runner trước khi nghiệm thu API mới trên hosting.

API và checklist hiện hành: [PERSON1-API.md](docs/PERSON1-API.md), [BACKEND-SCOPE.md](docs/BACKEND-SCOPE.md). Các báo cáo `PERSON1_*` và kết quả test trước đợt tích hợp mô tả phiên bản tại ngày ghi trong báo cáo, không thay contract hiện hành. Suite hiện tại: `./scripts/test-person1.ps1 -Php C:/xampp/php/php.exe`; các kịch bản local-db cũ cần cập nhật theo contract mới trước khi dùng lại.
