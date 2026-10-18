# Customer module (SOMI Grab Delivery)

Owner: Thảo Nhi. Scope: `app/Views/customer/**`, `public/assets/css/modules/customer.css`, `public/assets/js/customer/**`.
No shared file (index.php, app.css, app.js, shared views) was changed.

Stack: **PHP + HTML + CSS + JavaScript thuần + Bootstrap 5.3**, đúng README của nhóm: không `npm install`, không bước build. Sửa thẳng file `.js` rồi tải lại trang.

## Bootstrap

- Nạp từ CDN (jsDelivr, có `integrity`) ngay trong `app/Views/customer/index.php`, nên **chỉ trang Customer** dùng Bootstrap; Shipper, Admin, trang public không bị ảnh hưởng. Cần có mạng khi chạy; nếu CDN lỗi, `ui.js` có bản dự phòng nhỏ để modal, toast, dropdown vẫn mở được.
- Thứ tự CSS trên trang Customer: `app.css` → Bootstrap → `customer.css` (nạp lại sau Bootstrap) để màu navy, font Be Vietnam Pro và nút bo tròn của SOMI đè lên mặc định của Bootstrap.
- Thành phần Bootstrap đang dùng: lưới `container/row/col-lg-*`, `vstack`, `card card-body`, `btn btn-dark / btn-outline-dark / btn-danger / btn-sm / w-100`, `form-label form-control form-select form-check is-invalid invalid-feedback`, `alert`, `badge rounded-pill`, `nav nav-pills`, `navbar`, `dropdown` (menu avatar, menu ngôn ngữ), `btn-group`, `modal`, `toast`, `placeholder-glow`, `visually-hidden`, `d-md-none`.
- Thành phần riêng của SOMI (tiền tố `c-`): lộ trình A → B, dòng thời gian, bảng phí, ô chọn loại hàng/phương thức thanh toán, bản đồ theo dõi, hiệu ứng tìm shipper, bảng Mô phỏng demo.
- `app.css` của nhóm cũng có class `.modal`, `.modal-backdrop` cho trang public; `customer.css` đặt lại các thuộc tính đó trên trang Customer để modal của Bootstrap hiển thị đúng.

## Chạy thử

```bash
php -S localhost:3000 index.php
```

Mở `http://localhost:3000/?page=customer`. Dữ liệu mẫu lưu trong localStorage (khóa `somi-customer-v2`). Nút **Mô phỏng demo** ở góc trái dưới thay cho app Shipper, cổng thanh toán và Admin khi chưa có backend: cho shipper nhận/từ chối đơn, đẩy trạng thái giao, đổi trạng thái tài khoản, giả lập lỗi tải, khôi phục dữ liệu mẫu.

## Màn hình (`?page=customer&view=...`)

| view | Màn hình | Sprint |
|---|---|---|
| `dashboard` | Tổng quan: đơn đang giao, việc cần xử lý, số liệu tháng | 1 |
| `create` | Tạo đơn 4 bước, phí tính trực tiếp | 1 |
| `orders` | Lịch sử đơn: tab trạng thái, tìm kiếm, lọc ngày | 1 |
| `order&code=` | Chi tiết đơn: lộ trình, hành trình, phí, nút theo trạng thái | 1 |
| `account` | Thông tin cá nhân, xác thực SĐT bằng OTP, đổi mật khẩu | 1 |
| `addresses` | Sổ địa chỉ | 1 |
| `payment&code=` | COD, MoMo, VNPAY, ZaloPay, thẻ quốc tế (đơn USD chỉ thẻ), trả phần còn nợ | 2 |
| `matching&code=` | Tìm shipper, không tìm được shipper | 2 |
| `tracking&code=` | Theo dõi đơn, chờ người nhận, hẹn giao lại, sai địa chỉ, hoàn hàng | 2 |
| `review&code=` | Đánh giá shipper | 2 |
| `report[&code=]` | Báo cáo sự cố, đính kèm bằng chứng | 2 |
| `reports[&id=]` | Báo cáo của tôi và chi tiết xử lý | 2 |
| `notifications` | Thông báo | 2 |

## Cấu trúc code

```
app/Views/customer/index.php                 khung trang, điều hướng, nạp đúng page theo view
app/Views/customer/pages/<view>.php          khung HTML từng màn
public/assets/css/modules/customer.css       giao diện, mọi selector có tiền tố c-

public/assets/js/customer/
├── lang/en.js                               từ điển tiếng Anh (khóa = câu tiếng Việt)
├── i18n.js                                  ngôn ngữ VI/EN, tiền tệ VND/USD, định dạng tiền và ngày
├── store.js                                 dữ liệu + luật nghiệp vụ (đóng vai backend)
├── ui.js                                    thành phần dùng chung: badge, lộ trình, timeline, bảng phí, modal hủy, menu ngôn ngữ
└── pages/<view>.js                          điều khiển từng màn
```

Thứ tự nạp (trong `index.php` của Customer): `lang/en.js` → `i18n.js` → `store.js` → `ui.js` → `pages/<view>.js`.

## Ngôn ngữ (VI/EN) và tiền tệ (VND/USD)

- Ngôn ngữ lưu ở localStorage `somi-language`, **dùng chung với `app.js`** nên chọn ở trang index (public) thì vào Customer vẫn đúng ngôn ngữ, và ngược lại. Mặc định `en` giống `app.js`.
- Trong Customer: nút `VI · VND` trên thanh đầu trang và khung "Ngôn ngữ và tiền tệ" ở trang Tài khoản. Đổi xong trang tải lại.
- Tiếng Việt là ngôn ngữ gốc: code viết `t("Đơn hàng")`, `t\`Đơn ${code}\``. Bản tiếng Anh nằm trong `lang/en.js`. Thiếu khóa thì hiện tiếng Việt, nên khi thêm câu mới nhớ thêm vào `en.js`.
- Chữ tĩnh trong file PHP được `i18n.js` dịch khi trang mở (so khớp nguyên câu), không cần sửa PHP.
- Thông báo, ghi chú lịch sử, lý do hủy... lưu bằng tiếng Việt kèm giá trị (`msg(mẫu, giá trị)`) và dịch lúc hiển thị, nên đổi ngôn ngữ thì dữ liệu cũ cũng đổi theo. Nội dung người dùng tự gõ (mô tả hàng, tên, địa chỉ) giữ nguyên.
- Tiền tệ lưu ở `somi-currency`. Mỗi tiền tệ có **bảng giá riêng** (`PRICING.VND`, `PRICING.USD` trong `store.js`), số tiền là số nguyên theo đơn vị nhỏ nhất (VND: đồng, USD: cent) và **làm tròn lên**.
- Đơn **chụp lại tiền tệ lúc tạo** (`order.currency`): đổi tiền tệ chỉ áp dụng cho báo giá và đơn mới, đơn cũ giữ giá đã chốt.
- Đơn USD chỉ trả bằng thẻ quốc tế (`CARD`); COD, MoMo, VNPAY, ZaloPay chỉ nhận VND (`PAYMENT_METHODS[...].currencies`).
- Tỷ giá tham chiếu 1 USD = 25.000 ₫ (theo cấu hình mẫu bên Admin) chỉ để hiển thị.

## Luật nghiệp vụ đã cài (đối chiếu tài liệu phân tích thiết kế)

| Luật | Ở đâu |
|---|---|
| Máy trạng thái đơn, không chuyển trạng thái sai (BR-59 đến BR-61, BR-244 đến BR-261) | `store.js` `TRANSITIONS`, `setStatus()` |
| Phải đủ 2 địa chỉ, khối lượng > 0 và tối đa 30 kg (BR-07, BR-50, BR-55, BR-56) | Tạo đơn bước 1, 2 |
| Phí theo quãng đường, khối lượng, loại hàng, số kiện; chốt khi xác nhận (BR-62 đến BR-64) | `quote()` |
| Chỉ ACTIVE và đã xác thực SĐT mới được tạo đơn (BR-05, BR-20) | `createOrder()`, banner tài khoản |
| OTP có hạn 5 phút, dùng một lần, sai 5 lần thì khóa, gửi lại sau 60 giây, tối đa 5 lần gửi mỗi giờ (BR-16 đến BR-19, BR-21) | Tài khoản |
| Không xóa địa chỉ đã dùng trong đơn (BR-09, BR-10) | Sổ địa chỉ |
| Thanh toán idempotent, chặn giao dịch trùng, thất bại 3 lần thì hủy đơn (BR-97 đến BR-102, kịch bản 11) | `beginOnlinePayment()`, `resolveOnlinePayment()` |
| Hủy miễn phí trước khi có shipper, có phí hủy sau khi shipper nhận, đang giao thì hoàn hàng (BR-110 đến BR-118) | `cancelPolicy()`, `cancelOrder()` |
| Shipper phải chủ động nhận, từ chối/hết hạn thì tìm tiếp, không có thì NO_SHIPPER_FOUND (BR-78 đến BR-89) | Tìm shipper |
| Chờ người nhận, hẹn giao lại, giao thất bại, hoàn hàng, phí hoàn hàng tính riêng (BR-132 đến BR-155) | Theo dõi đơn |
| Chỉ đánh giá đơn COMPLETED, mỗi đơn một lần, 1 đến 5 sao (BR-159 đến BR-166) | `reviewEligibility()` |
| Báo cáo cần lý do và mô tả, khách không tự đổi trạng thái; hàng hư/mất thì DISPUTED (BR-168 đến BR-176) | Báo cáo |
| Thông báo khi trạng thái quan trọng thay đổi (BR-231 đến BR-237) | `setStatus()` tự tạo thông báo |

## 17 kịch bản trong tài liệu và cách chạy thử

Mở đơn tương ứng, bấm **Mô phỏng demo** để đóng vai shipper, cổng thanh toán, Admin.

| # | Kịch bản | Cách chạy trên giao diện |
|---|---|---|
| 1 | Giao thành công | Tạo đơn → MoMo Duyệt → Shipper nhận → lấy hàng → đến nơi → Giao thành công → Hoàn tất → Đánh giá |
| 2 | Hủy trước khi shipper nhận | Đơn đang tìm shipper → Hủy đơn miễn phí → hoàn đủ nếu đã trả |
| 3 | Hủy sau khi shipper nhận, chưa lấy hàng | Đơn ASSIGNED hoặc PICKING_UP → Hủy đơn → trừ phí hủy, hoàn phần còn lại |
| 4 | Hủy sau khi shipper đã lấy hàng | Đơn IN_TRANSIT → Hủy và hoàn hàng → RETURNING → RETURNED, tính phí hủy + phí hoàn |
| 5 | Shipper từ chối | Đang tìm shipper → Shipper từ chối → vẫn tìm tiếp |
| 6 | Shipper hủy sau khi nhận | ASSIGNED → Shipper hủy nhận đơn → tự quay lại tìm shipper |
| 7 | Không tìm được shipper | Từ chối, không phản hồi → Không tìm được shipper → Tìm lại hoặc Hủy miễn phí |
| 8 | Không có người nhận | Đến nơi → Không có người nhận → Hết thời gian chờ → giao thất bại → hoàn hàng |
| 9 | Giao lại | Đang chờ người nhận → Hẹn giao lại (+phí) → Bắt đầu giao lại → giao thành công |
| 10 | Sai địa chỉ | Đang giao → Không tìm thấy địa chỉ → Cập nhật địa chỉ (phí phát sinh) hoặc Hoàn hàng về |
| 11 | Thanh toán thất bại | Từ chối 3 lần → đơn tự hủy. COD: Đến nơi → Người nhận từ chối trả tiền → hoàn hàng |
| 12 | Hàng hư, thất lạc | Báo cáo sự cố loại Hàng hư hỏng/Mất kiện → đơn DISPUTED → Admin xử lý |
| 13 | Khách báo cáo shipper | Báo cáo sự cố → chọn "Shipper" (chỉ mở khi đơn có shipper) |
| 14 | Shipper báo cáo khách | Thuộc module Shipper, không nằm trong phần Customer |
| 15 | Khách không nhận và yêu cầu hủy | Đang chờ người nhận → Không nhận được, hoàn hàng về → tính phí hoàn |
| 16 | Shipper gặp sự cố | Đang giao → "Sự cố: đổi shipper" (giao tiếp) hoặc "Sự cố: không giao được" (hoàn hàng, miễn phí giao, hoàn tiền) |
| 17 | Phí khi giao thất bại | Phí hoàn = 50% phí giao; đã trả 30.000, phí hoàn 15.000 thì còn phải trả 15.000 |

## Điểm đã chốt khi tài liệu chưa rõ hoặc tự mâu thuẫn

| Chỗ trong tài liệu | Cách module xử lý | Lý do |
|---|---|---|
| Kịch bản 4 ghi "PICKING_UP / IN_TRANSIT" là đã lấy hàng, nhưng BR-258 cho PICKING_UP chuyển thẳng CANCELLED | PICKING_UP hủy có phí, IN_TRANSIT mới hoàn hàng | PICKING_UP là shipper đang đến lấy, hàng chưa trong tay (BR-129, BR-130) |
| Kịch bản 11 ghi thanh toán thất bại là hủy đơn ngay | Cho thử lại tối đa 3 lần, quá 3 lần mới hủy | BR-279 cho phép nhiều giao dịch thử lại cho một đơn |
| Kịch bản 9 ghi giao lại từ ARRIVED, BR-253 chỉ cho từ WAITING_FOR_RECIPIENT | Theo BR-253 | Luật chuyển trạng thái là nguồn chuẩn |
| Hủy khi đang hẹn giao lại: tài liệu không nói | Hoàn hàng như hủy lúc đang giao | Shipper vẫn giữ hàng (BR-116, BR-117) |
| Shipper gặp sự cố không giao được: tài liệu không nói về phí | Không tính phí hoàn, miễn phí giao, hoàn tiền đã trả | BR-151 chỉ tính phí hoàn khi lỗi do khách |
| Trạng thái báo cáo: BR-172 dùng OPEN, bảng reports mặc định PENDING | Dùng OPEN | Theo luật nghiệp vụ |
| ARRIVED → DELIVERY_FAILED không có trong BR-252 | Có, cho trường hợp COD người nhận từ chối trả tiền | Kịch bản 11 nhánh COD |

## Nối backend PHP (giai đoạn 4 đến 6)

Mỗi hàm trong `store.js` (đưa ra qua `window.SomiStore`) tương ứng một endpoint. Khi backend xong, thay thân hàm bằng `fetch()`; các file trang giữ nguyên. Backend vẫn phải kiểm tra lại mọi luật (BR-283, BR-284), không tin dữ liệu trạng thái hay số tiền từ client.

| Hàm trong store.js | Endpoint đề xuất | Controller / Service |
|---|---|---|
| `listOrders`, `getOrder` | `GET /api/customer/orders`, `GET /api/customer/orders/{code}` | OrderController |
| `quote` | `POST /api/orders/quote` | MapService + FeeCalculationService |
| `createOrder` | `POST /api/customer/orders` | OrderController |
| `chooseCod`, `beginOnlinePayment` | `POST /api/customer/orders/{code}/payments` | PaymentController |
| `resolveOnlinePayment` | `POST /api/payments/callback` (cổng gọi về, kiểm chữ ký) | PaymentService |
| `cancelPolicy`, `cancelOrder` | `GET/POST /api/customer/orders/{code}/cancel` | OrderController |
| `retryMatching` | `POST /api/customer/orders/{code}/retry-matching` | MatchingService |
| `reschedule`, `refuseDelivery` | `POST /api/customer/orders/{code}/reschedule`, `/refuse` | DeliveryController |
| `quoteAddressChange`, `approveAddressChange`, `declineAddressChange` | `POST /api/customer/orders/{code}/address-change` | DeliveryController |
| `submitReview` | `POST /api/customer/orders/{code}/review` | ReviewController |
| `listReports`, `getReport`, `submitReport` | `GET/POST /api/customer/reports` | ReportController |
| `listNotifications`, `markRead`, `markAllRead` | `GET /api/notifications`, `POST /api/notifications/read` | NotificationService |
| `getAccount`, `updateProfile`, `changePassword` | `GET/PUT /api/customer/profile`, `POST /api/customer/password` | AuthController / CustomerController |
| `requestOtp`, `verifyOtp` | `POST /api/otp/request`, `POST /api/otp/verify` | OTPService |
| `listAddresses`, `saveAddress`, `setDefaultAddress`, `deleteAddress` | `GET/POST/PUT/DELETE /api/customer/addresses` | CustomerController |
| `simulate*`, `setAccountState`, `reset` | Bỏ khi có backend (chỉ dùng cho demo) | |

## Việc cần nhóm thống nhất

1. `app.js` (dùng chung) tự dịch chữ tiếng Việt sang tiếng Anh khi site đang để EN. Module Customer có đoạn chặn riêng trong `ui.js`; nên sửa gốc trong `app.js` để bỏ qua `[data-customer-app]`.
2. Hằng số trạng thái đơn và màu badge nên chuyển sang thư mục dùng chung để Shipper và Admin hiển thị giống nhau.
3. Tài liệu ghi trạng thái báo cáo mở là `OPEN` (BR-172) nhưng bảng `reports` mặc định `PENDING`; module này dùng `OPEN`.
