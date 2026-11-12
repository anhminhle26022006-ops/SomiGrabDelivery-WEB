# SOMI — Phân công và quyền sở hữu module

Cập nhật **06/11/2026**, theo bản phân công backend điều chỉnh người dùng cung cấp. Tài liệu này thay cách chia backend theo UI Customer/Shipper/Admin trước đây. Chi tiết checklist và bàn giao: [docs/BACKEND-SCOPE.md](docs/BACKEND-SCOPE.md).

> Điều chỉnh theo yêu cầu trực tiếp ngày 09/11/2026: Người 1 tiếp quản module địa chỉ Customer/Google, cấu hình key và Autocomplete. Các mục Người 3 về địa chỉ/Google bên dưới mô tả phân công trước điều chỉnh; chat/ticket, notification và payout giữ owner cũ. Nhà cung cấp hiện hành chuyển sang Leaflet + Geoapify. Triển khai và bàn giao: [ADDRESS-GEOAPIFY-SETUP.md](docs/ADDRESS-GEOAPIFY-SETUP.md).

> Theo yêu cầu Admin ngày 09/11/2026 trên MINH-ANH: Người 1 tích hợp giao diện quản trị thật, gồm API xử lý ticket/Admin chat có kiểm tra ticket + audit. Luồng Customer/Shipper tạo ticket/chat, GPS và payout giữ phần module tương ứng; bàn giao API tại [ADMIN-LIVE.md](docs/ADMIN-LIVE.md).

> Cập nhật tích hợp theo yêu cầu người dùng ngày 12/11/2026: điểm/thu nhập đã được duyệt, hoàn tiền Customer và payout Shipper giữ thủ công. Code các luồng liên module và bản release policy: [SHIPPER-POLICY-WORKFLOWS.md](docs/SHIPPER-POLICY-WORKFLOWS.md). Các ghi chú “chưa chốt” bên dưới mô tả trạng thái cũ.

## Ownership hiện hành

| Phạm vi | Chủ trì | Phối hợp và ranh giới |
|---|---|---|
| Nền tảng, PostgreSQL/migrations, auth, role/permission, Admin guard và audit chung | Người 1 | Người 2/3 sử dụng cơ chế chung |
| Customer, danh mục hàng, báo giá, tạo/xem/hủy đơn, snapshot, trạng thái, giá/phí/chính sách | Người 1 | Dùng địa chỉ và tuyến đường Người 3; không tích hợp Google hoặc viết CRUD địa chỉ |
| Payment Customer, callback, đối soát, refund và worker liên quan | Người 1 | Refund Customer tách khỏi payout Shipper |
| Toàn bộ email: verification/OTP/reset, email giao dịch, template, lịch sử, worker/retry | Người 1 | Người 2/3 yêu cầu qua interface/sự kiện chung |
| Admin cốt lõi, tài khoản/quốc tịch và duyệt KYC | Người 1 | API nghiệp vụ Admin ticket thuộc Người 3 |
| Hồ sơ/phương tiện/KYC submission, eligibility/availability, matching/offer/assignment | Người 2 | Người 1 duyệt KYC, Người 3 hỗ trợ lưu file nếu dùng |
| Giao nhận/hoàn hàng, bằng chứng, sự kiện điểm, GPS và retention vị trí | Người 2 | Không tích hợp Maps/Cloud Storage hoặc email/refund riêng |
| Google Cloud, Places/Geocoding/Routes, key/quota; Storage nếu chọn dùng | Người 3 | Cung cấp dữ liệu địa lý và tham chiếu file; không quyết định công thức giá |
| CRUD địa chỉ Customer, địa chỉ mặc định và chuẩn hóa tọa độ | Người 3 | Không sửa snapshot địa chỉ trên đơn cũ |
| Chat, ticket, API Admin ticket và notification in-app | Người 3 | Dùng quyền/audit Người 1; Admin đọc chat phải có ticket hợp lệ |
| Earnings/payout Shipper, tỷ giá, worker retry/đối soát và retention thuộc module | Người 3 | Đọc chính sách Người 1 và trạng thái giao nhận Người 2; không làm payment/refund Customer hoặc worker email riêng |

## Bàn giao source

- **Người 3:** tiếp quản `CustomerAddressService.php`, `public/api/customer/addresses.php`, phần Google trong `GoogleMeteoPricingProvider.php` và transport `PricingContextProvider.php`. Giữ endpoint/interface, không tạo bản sao.
- **Người 1:** giữ `CustomerOrderService`, `OrderQuoteService`, `OrderLifecycleService`, `DistanceWeightPrice`, cấu hình/release/migration giá, API quote/order/cancel/category, auth/email services, API duyệt KYC và nền shared.
- **Người 2:** tiếp tục từ phần thông tin ứng tuyển hiện có trong register/switch-workspace; phối hợp Người 1 khi sửa auth. `IdentityDocumentVault` bảo vệ số giấy tờ, không phải storage ảnh KYC.
- Người 1 đã triển khai payment/email/Admin và UI đơn chính; xem [contract và giới hạn SePay](docs/PERSON1-API.md). Shipper, chat/ticket, notification in-app và payout vẫn thuộc phần tiếp theo của Người 2/3. Tên file/thư mục tương lai phải thống nhất, không xem thư mục/schema dự kiến là code đã có.
- Open-Meteo chưa có owner riêng trong bản mới. Đề xuất Người 3 quản lý transport/key và Người 1 quản lý policy/hệ số; cần nhóm xác nhận. Adapter hiện gộp Google và thời tiết, chưa tự tách module chỉ bằng việc sửa tài liệu.

## File dùng chung và UI

Người 1 là đầu mối tích hợp `index.php`, `.htaccess`, `Dockerfile`, DB/config, migrations, shared views/assets và định dạng lỗi. Chủ module soạn migration; Người 1 review/tích hợp. UI vẫn chia folder Customer/Shipper/Admin và assets tương ứng, người phụ trách UI hiện tại phối hợp theo nghiệp vụ. Không tự gán tên người phụ trách UI sang số Người 1/2/3.

API địa chỉ nằm trong `customer` nhưng thuộc Người 3; API Admin ticket thuộc Người 3 nhưng quyền Admin/audit chung thuộc Người 1. Folder không thay thế ownership nghiệp vụ.

## Quy tắc làm việc

1. Dùng chung auth/permission/ownership/error/DB; không tạo cơ chế riêng cho từng module. Giữ style/script riêng theo module, tránh đưa handler riêng vào file chung.
2. Chuyển owner không tự đổi URL, schema hoặc snapshot. Thay đổi phá tương thích phải có kế hoạch cập nhật caller/migration.
3. Nhánh mới dùng tiền tố `codex/`, ví dụ `codex/payment-refund`, `codex/shipper-matching`, `codex/address-routes`. Không cần đổi tên nhánh đang tồn tại chỉ để sửa tài liệu.
4. Phối hợp với owner trước khi sửa file dùng chung; merge qua PR và review tích hợp. Không commit secrets, runtime session/cookie hoặc file sinh không liên quan.
5. Chạy kiểm tra phù hợp phần đổi; tích hợp kiểm tra cả ba vai trò, state/event và các nhánh tranh chấp. Kiểm thử module không thay kiểm thử đầu cuối.
6. Payment đã chọn SePay, USD checkout tạm khóa; provider payout và chính sách tiền/điểm chưa chốt, retention và có dùng Storage hay không cần thống nhất; không lấy giá trị/phương thức thanh toán demo làm nghiệp vụ thật.
