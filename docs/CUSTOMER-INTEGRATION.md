# Customer UI — kế hoạch nối backend theo scope mới

> Update 06/11/2026: Person 1 payment/email/Admin APIs and live Customer flow are implemented. SePay is selected; USD checkout is disabled. Current contracts, setup, tests and live-provider limitations: [PERSON1-API.md](PERSON1-API.md).


Cập nhật **06/11/2026**, theo [BACKEND-SCOPE.md](BACKEND-SCOPE.md), [ownership](../TEAM-OWNERSHIP.md) và [API hiện có](DELIVERY-API.md). UI gốc ghi người phụ trách Thảo Nhi; không tự đồng nhất tên này với số Người 1/2/3. Backend chia theo nghiệp vụ, không theo folder màn hình.

## Hiện trạng

PHP/HTML/CSS/JavaScript thuần, Bootstrap 5.3 CDN, không npm/build. UI ở app/Views/customer và public/assets/js/customer. Dashboard/create/orders/order/payment/account/matching/tracking dùng server.js và API thật. Các view địa chỉ/chat/report/notification ngoài scope Người 1 còn dùng demo; Người 2/3 tiếp tục nối. Simulator/payment/offer/notification trong browser không tạo giao dịch thật.

Đăng nhập Customer hợp lệ rồi mở `?page=customer&view=...`. Demo key là `somi-customer-v2:<user_id>`, tách tài khoản. Không dùng session bypass/simulator làm cách chạy production.

Dependency: index.php inject approved-pricing-config và nạp delivery-price.js; sau đó lang/en.js → i18n.js → server.js cho các view thật; chỉ các view legacy nạp store.js → ui.js → pages/<view>.js. Bootstrap chỉ nạp cho Customer, customer.css sau Bootstrap để giữ style SOMI.

## Phối hợp owner

| Mảng UI | Backend phụ trách |
|---|---|
| Auth/profile/email, quote/order/payment/refund | Người 1 |
| Địa chỉ/Places/Geocoding/Routes | Người 3 |
| Matching/tracking/giao/hoàn và Customer xác nhận hoàn | Người 2; Người 1 điều phối trạng thái đơn |
| Chat/ticket/in-app | Người 3, guard/audit chung Người 1 |

Các view hiện có: dashboard, create, orders, order, payment, matching, tracking, review, report, reports, notifications, account, addresses. Có view không đồng nghĩa đã có API tương ứng.

## Giá, ngôn ngữ và trạng thái

- VI/EN dùng somi-language; Customer dịch qua lang/en.js. Thêm câu mới phải có bản tiếng Anh; nội dung người dùng không tự dịch.
- Giá dùng app/Config/delivery-pricing.php và delivery-price.js: cơ bản 20.000đ/99 cent gồm 3 km + 3 kg; mỗi km vượt mức 4.000đ/20 cent, mỗi kg vượt mức 2.500đ/10 cent; nhân hệ số giờ/thời tiết, ceil tổng một lần. TP.HCM, route <40 km, 0 < weight <=30 kg. Phí hoàn cố định 10.000đ/50 cent tách riêng, không dùng tỷ giá tham chiếu hoặc phí hoàn 50%.
- Demo cho chọn currency bằng somi-currency; API quote lấy users.app_language (vi → VND, en → USD). UI phải hiển thị currency response, không tự gửi currency/giá hoặc coi đổi ngôn ngữ browser là đổi currency tài khoản. Thay hành vi này cần contract/migration riêng.
- Đơn cũ giữ snapshot/giá. Route/weather demo còn mô phỏng; báo giá thật lấy từ API server, không dùng client estimate xác nhận thanh toán.
- Demo có MATCHING/PICKING_UP/IN_TRANSIT/COMPLETED/DISPUTED; backend có PENDING_PAYMENT/SEARCHING_SHIPPER/NO_SHIPPER_FOUND/SHIPPER_ASSIGNED/PICKED_UP/IN_DELIVERY/DELIVERED/RETURNING/RETURNED/CANCELLED. Render theo enum backend, chốt mapping nhãn; không gửi enum demo vào DB.
- COD/MoMo/VNPAY/ZaloPay và retry limit trong simulator không phải provider/payment policy đã chốt. Payment/refund thật do Người 1 xác minh và triển khai.

## Endpoint đang có

Base tương đối root project: `public/api/customer/`. Body/permission/lỗi/idempotency: [DELIVERY-API.md](DELIVERY-API.md).

| Tương tác | Endpoint thực tế | Owner |
|---|---|---|
| List/detail địa chỉ | GET addresses.php hoặc addresses.php?id= | Người 3 |
| Add/update/archive | POST addresses.php; PUT/DELETE addresses.php?id= | Người 3 |
| Chọn mặc định | PUT đầy đủ địa chỉ với is_default=true; chưa có endpoint default riêng | Người 3 |
| Danh mục hàng | GET parcel-categories.php | Người 1 |
| Quote | POST quotes.php | Người 1, dữ liệu địa lý Người 3 |
| Tạo đơn | POST orders.php, chỉ quote_id; header Idempotency-Key | Người 1 |
| List/detail đơn | GET orders.php hoặc orders.php?id=; cursor before_id | Người 1 |
| Hủy trước assignment | POST cancel-order.php?id=, body reason | Người 1 |

DELETE địa chỉ là archive; snapshot đơn không đổi. API dùng order_id, không lấy code demo thay ID. REST endpoint dự kiến trong bản demo cũ chưa được triển khai; không dùng thay URL .php hiện có.

## Checklist tích hợp

1. Thiết kế async data layer: store hiện chủ yếu đồng bộ, không chỉ thay thân hàm bằng fetch rồi giữ nguyên caller. Cập nhật async/await, loading/empty/error/401/403, khóa submit lặp ở các trang dùng; giữ VI/EN.
2. Người 3 nối addresses/Autocomplete/normalization; Người 1 đã nối category/quote/create/list/detail/cancel. Chọn địa chỉ → quote → tạo order; quote hết hạn 409 thì xin quote mới và hiển thị lại phí trước xác nhận. Cùng lần tạo order giữ idempotency key khi retry.
3. Đọc status/history/currency/amount từ server; tách render khỏi lệnh đổi trạng thái. Không để simulate/setAccountState/reset ghi backend thật.
4. Payment/refund/email đã có interface/API Người 1 theo PERSON1-API.md; matching/tracking/return chờ Người 2; chat/ticket/in-app chờ Người 3. Chỉ sử dụng endpoint đã triển khai; không báo thành công từ simulator.
5. Customer chỉ hủy trước assignment theo contract hiện có. Phí hủy sau assignment, hẹn giao lại, đổi địa chỉ sau lấy hàng và auto-cancel sau 3 lần payment demo không tự trở thành luật backend.
6. Kiểm thử sở hữu địa chỉ/quote/đơn, chuyển tài khoản, currency response, quote hết hạn, gửi lặp, lỗi provider và late payment sau hủy; chạy đầu cuối ba vai trò sau tích hợp.

Kịch bản và các quyết định demo cũ lưu ở [CUSTOMER-DEMO-LEGACY.md](archive/CUSTOMER-DEMO-LEGACY.md); không dùng làm chính sách tiền/state/endpoint hiện hành.

## Customer layout restoration — 07/11/2026

The PHP page templates and Customer CSS use `aae675c` as the layout baseline. The Customer shell renders those templates directly; `server.js` binds API data to their existing hooks. It no longer replaces the whole page with generic forms/tables. Dashboard columns, order cards/search/date/status filters, the four-step create form, account panels and the payment chooser/summary layout remain in place.

The technical `backend` tab is removed from Customer navigation. An existing `view=backend` link resolves to the real Account page. Admin's backend console remains unchanged. The live Customer views do not load the demo store or local mutation controllers.

Server quote ID, revision invalidation, expiry and a stable creation key control order submission. Total weight includes all parcels. Scheduled pickup is disabled until supported by the backend. New inline addresses require real coordinates; saved addresses retain their server coordinates. No demo distance, GPS, payment success, matching assignment or refund success is fabricated. Address CRUD, profile update/password/nationality requests, payment checkout and cancellation call the existing authenticated APIs. Reports/notifications outside the Person 1 API scope remain legacy views.

Validation: nine restored PHP templates match the `aae675c` baseline; 47 browser fixture checks cover VI/EN, original layout hooks, order filters, quote expiry/revision, stable retry keys and SePay checkout binding. 38 read-only HTTP checks and 85 TEMP-table order checks pass. Desktop/mobile screenshots were reviewed; no payment-provider requests or persistent test business writes were made.
