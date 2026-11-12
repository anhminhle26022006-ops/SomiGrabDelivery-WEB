# Backend SOMI — Scope điều chỉnh và kế hoạch bàn giao

Cập nhật **06/11/2026**, căn cứ bản phân công điều chỉnh người dùng gửi; đối chiếu nền tại commit `aae675c` và phần triển khai Người 1 trong working tree. Đây là tài liệu phân công hiện hành. Báo cáo ngày cũ mô tả thời điểm kiểm tra, không thay scope này. Trạng thái triển khai mới nhất và contract: [PERSON1-API.md](PERSON1-API.md). Phân biệt source đã có với kiểm chứng provider thật.

## Phân công

| Chủ trì | Trách nhiệm | Không làm trùng |
|---|---|---|
| Người 1 | Nền tảng/DB/auth, Customer và đơn, công thức giá/chính sách, payment/callback/đối soát/refund, toàn bộ email, Admin cốt lõi/duyệt KYC, quyền/audit chung và tích hợp cuối | Không tích hợp Google hoặc triển khai CRUD địa chỉ Customer |
| Người 2 | Hồ sơ/phương tiện/KYC submission, eligibility/availability, matching/offer/assignment, giao nhận/hoàn hàng/bằng chứng, GPS và nghiệp vụ điểm | Không tích hợp Maps/Cloud Storage, email hoặc refund riêng |
| Người 3 | Google Cloud/Places/Geocoding/Routes, địa chỉ Customer, Storage nếu dùng, chat/ticket/Admin ticket, earnings/payout, notification in-app và worker/retention liên quan | Không triển khai payment/refund Customer, API hoặc worker email riêng |

Refund là hoàn tiền Customer của Người 1; payout là chi trả Shipper của Người 3. Email do Người 1 gửi; notification trong ứng dụng do Người 3 gửi. Quyền Admin/audit chung thuộc Người 1; API nghiệp vụ ticket và kiểm tra ticket liên quan trước khi đọc chat thuộc Người 3.

## Source bàn giao và hiện trạng

| Code hiện có | Owner tiếp tục | Hiện trạng / phần cần nối |
|---|---|---|
| Auth API, SessionGuard, RequestOrigin, bootstrap | Người 1 | Có auth/session/email verification/reset; phone OTP demo; đã thêm kernel ba vai trò, permission/ownership/audit và throttle đăng nhập |
| CustomerOrderService, OrderQuoteService, OrderLifecycleService và API quote/order/cancel/category | Người 1 | Có API, snapshot, idempotency, history, payment hook/outbox; UI luồng chính đã nối API; payment/refund xem contract SePay bên dưới |
| DistanceWeightPrice, cấu hình/release giá, migration runner | Người 1 | Công thức đã duyệt, VND/USD độc lập, TP.HCM, route <40 km, 0 < weight <=30 kg; đã có API Admin tạo/version/activate policy |
| CustomerAddressService, public/api/customer/addresses.php | **Người 3 tiếp quản** | Có CRUD/archive/default/ownership; thiếu Places/Geocoding chuẩn hóa và nối UI |
| GoogleMeteoPricingProvider, PricingContextProvider | **Người 3 tiếp quản phần Google** | Có reverse geocode/Routes và weather adapter; chưa có Places hay API route dùng chung cho Shipper; chưa chứng minh provider thật hoạt động trong báo cáo cũ |
| ResendMailer, EmailVerificationService, PasswordResetService | Người 1 | Có email tài khoản; đã có template VI/EN, encrypted queue, log, expiry, worker/retry |
| review-shipper.php, IdentityDocumentVault | Người 1, phối hợp Người 2 | Có APPROVED/REJECTED/NEEDS_INFO và audit; submission/upload ảnh thuộc Người 2/3 |
| Schema offer/assignment/delivery/GPS/chat/ticket/earnings/payout/notification | Theo nghiệp vụ | Là nền dữ liệu; chưa phải API/worker hoàn chỉnh |

Giữ URL và interface khi bàn giao. File nằm ở namespace customer/admin không quyết định owner. Người 1 review migration chung; không tự di chuyển source hoặc tạo API trùng.

## Checklist Người 1

- [x] Shared kernel ba vai trò, permission, ownership đơn/offer, audit và lỗi JSON; auth cũ giữ contract scalar tương thích.
- [x] UI Customer dùng API profile/category/quote/create/list/detail/cancel/payment/refund; VI/EN, giữ snapshot và idempotency. Địa chỉ/Google do Người 3 cung cấp.
- [x] API Admin danh mục và policy giá/phí/thu nhập/điểm có version/audit, không seed giá trị chưa duyệt.
- [x] Interface và SePay signed checkout/IPN/đối soát; callback receipt/ledger; payment hợp lệ mới mở matching; late payment không mở lại đơn hủy.
- [x] API và worker refund, reserve một lần, phí theo snapshot; SePay refund chờ operator hoàn về phương thức gốc và Admin đối soát chứng từ. Không có tự động chuyển tiền refund Gateway trong bản này.
- [x] Email verification/reset/nghiệp vụ, template VI/EN theo tài khoản, encrypted queue/log/TTL/retry/worker; interface Người 2/3.
- [x] Admin tra cứu/khóa/mở/quốc tịch/KYC approve/reject/needs-info, lịch sử và audit.
- [ ] Nghiệm thu SePay sandbox bằng credentials merchant và IPN HTTPS thực; email provider/inbox; SMS thật chưa có nhà cung cấp. Chạy worker theo lịch ở môi trường triển khai.
- [ ] Tích hợp đầu cuối với matching/giao nhận/Google/in-app/payout sau khi Người 2/3 bàn giao.

Người dùng chốt SePay và tạm khóa thanh toán USD. SMS chỉ demo có cấu hình cho môi trường phát triển. Chi tiết endpoint, cách chạy, giới hạn provider và kiểm thử: [PERSON1-API.md](PERSON1-API.md).

## Checklist Người 2

- [ ] API profile/vehicle/KYC submit/resubmit/status; phối hợp storage Người 3 nếu dùng, không tự duyệt KYC hoặc ghi đè số giấy tờ nhạy cảm.
- [ ] API availability; kiểm tra account/KYC/eligibility tại backend cả lúc gửi offer và accept.
- [ ] Matching từ OrderReadyForMatching, nhiều offer hạn 2 phút; API list/detail/accept/reject/assigned, expire khi hủy hoặc người khác nhận.
- [ ] Accept nguyên tử: khóa order trước, kiểm tra payment/status/offer/shipper, tạo một active assignment, đổi history và vô hiệu hóa offer còn lại cùng transaction.
- [ ] API pickup/in-delivery/delivered/failure/return: ảnh thành công, lý do thất bại, ảnh + Shipper xác nhận hoàn + Customer xác nhận. Hủy trước lấy hàng ghi sự kiện điểm; sau lấy hàng tạo yêu cầu Admin.
- [ ] GPS chỉ nhận khi có assignment hợp lệ; tracking theo quyền, retention và phối hợp xóa với Người 3.
- [ ] Sự kiện giao nhận cho Người 1 order/refund/email và Người 3 earnings/payout/in-app; nối UI Shipper vào API. Không dùng mảng JS hoặc phí demo 10% làm chính sách thật.

Nghiệm thu lượt đầu: hai Shipper accept chỉ một người thắng; cancel/accept không cùng thành công; không cập nhật đơn người khác; giao/hoàn đủ bằng chứng và xác nhận.

## Checklist Người 3

- [ ] Tiếp quản CRUD địa chỉ; thêm Places Autocomplete/Geocoding/chuẩn hóa, ownership/default/archive; bàn giao địa chỉ hợp lệ cho tạo đơn, không sửa snapshot cũ.
- [ ] Google project/key/quota và bảo vệ key theo loại API; service/API route dùng chung, lỗi tọa độ/no-route/timeout; tài liệu local/deploy. Không trả key server về browser.
- [ ] Cung cấp distance/duration/route cho Người 1/2; không đặt công thức tiền trong Google provider. Adapter hiện gộp phạm vi và quy tắc thời tiết: phối hợp Người 1 khi tách, không làm mất kiểm tra hiện có.
- [ ] Nếu chọn Cloud Storage: bucket/quyền, upload/download, loại/dung lượng/owner, tham chiếu file và retention/deletion cho KYC/bằng chứng. Không coi lựa chọn này đã chốt chỉ vì xuất hiện trong scope.
- [ ] Chat theo đơn, trạng thái đọc, ticket account/order, history và API Admin ticket; quyền Customer/Shipper/assignment, Admin cần ticket liên quan và audit chung Người 1.
- [ ] Earnings/payout theo policy Người 1, delivery Người 2 và payment/refund hợp lệ; active bank account, payout theo đơn, idempotency/retry/đối soát, nguồn tỷ giá và làm tròn payout VND từ nguồn USD.
- [ ] Notification list/read/unread, template VI/EN, history/worker; nhận sự kiện, không tự đổi trạng thái payment/delivery. Email gọi service Người 1, không có worker email riêng.

Nghiệm thu lượt đầu: địa chỉ chuẩn hóa và route thật, chat/ticket đúng quyền, notification có consumer receipt/idempotency, payout không trùng và đối soát nguồn tiền.

## Hợp đồng phối hợp

| Luồng | Nguồn | Consumer / trách nhiệm |
|---|---|---|
| Địa chỉ/tọa độ/route | Người 3 | Người 1 snapshot/giá; Người 2 giao nhận |
| OrderCreated | Người 1 | Payment/email Người 1; in-app Người 3 |
| OrderReadyForMatching | Người 1 sau payment hợp lệ | Người 2 matching; kiểm tra lại order dưới lock |
| OrderCancelled | Người 1 | Người 2 dừng offer; Người 1 payment/refund/email; Người 3 in-app và điều chỉnh tài chính theo kết quả tiền hợp lệ |
| RefundRequested | Người 1 | Module refund Người 1 thực hiện; không coi yêu cầu là refund thành công |
| Kết quả payment/refund | Người 1 | Người 3 notification/tài chính Shipper; PaymentConfirmed và RefundSucceeded đã có, xem PERSON1-API.md |
| Offer/assignment/delivery/return | Người 2 | Người 1 order/refund/email; Người 3 in-app/earnings/payout |
| Ticket/chat | Người 3 | In-app Người 3; yêu cầu email Người 1 khi cần |

Code phát OrderCreated, OrderReadyForMatching, OrderCancelled, RefundRequested, PaymentConfirmed và RefundSucceeded vào order_events. Có consumer receipt riêng Người 1 và worker; Người 2 phát OrderDelivered/OrderReturning/OrderReturned theo contract sau khi đủ điều kiện giao nhận. Người 1 chốt envelope/version/idempotency và cơ chế chung; mỗi chủ module làm consumer/retry. Không đánh dấu event đã xử lý chung cho mọi consumer.

Mọi thao tác tranh chấp khóa order trước rồi payment/offer/assignment. TRANSITIONS là whitelist chung, chưa phải engine tự kiểm tra actor/bằng chứng; từng API phải kiểm tra điều kiện trước khi đổi trạng thái. Payment/refund dựa trên provider đã xác minh, không dựa browser demo.

## Điểm chưa chốt / cần migration

1. Ticket DB dùng NEW/IN_PROGRESS/RESOLVED/CLOSED; scope gọi Open/In Progress/Resolved/Closed. Chốt mapping NEW ↔ nhãn Open hoặc migration trước implement; không tự ghi OPEN vào constraint hiện tại. `support_tickets.order_id` NOT NULL: ticket chỉ liên quan tài khoản cần migration review.
2. notifications chứa cả email_status/email_language/email_sent_at và in-app fields. Phân định schema/send log/worker với Người 1; không để hai worker cùng sở hữu trạng thái email.
3. Open-Meteo chưa được giao riêng: đề xuất Người 3 transport/key, Người 1 policy/hệ số; cần xác nhận. GPS thuộc Người 2, bản đồ thuộc Người 3.
4. Provider payment đã chọn SePay; payout, chính sách thu nhập/điểm/bồi hoàn chưa duyệt, retention và có dùng GCS vẫn cần chốt; không seed số liệu giả định.
5. Payment/email/Admin Người 1 xem PERSON1-API.md; Shipper/chat/ticket/notification/payout chưa được xem là đã triển khai chỉ vì schema có sẵn.

## Trình tự tích hợp

1. Cả nhóm chốt scope/contract. Người 1 guard/error/audit, state, interface payment/email; Người 2 profile/KYC/availability; Người 3 tiếp quản address/Google và chuẩn hóa.
2. Người 3 bàn giao address/route; Người 1 nối UI/payment/email: chọn địa chỉ → quote → tạo đơn bằng quote_id → payment hợp lệ → OrderReadyForMatching. Không đảo thứ tự API để khớp mô tả tổng quát trong kế hoạch.
3. Người 2 matching/assignment/delivery; Người 3 chat/ticket/in-app và hiển thị GPS; Người 1 quyền/audit/email.
4. Người 2 hoàn hàng/bằng chứng; Người 1 refund/đối soát; Người 3 earnings/payout/retention; hoàn thiện Admin theo owner nghiệp vụ.
5. Kiểm thử đầu cuối ba vai trò, callback lặp, cancel/accept đồng thời, late payment/refund, email/in-app, ticket-gated chat, payout, lỗi provider/quota, Docker/staging trước hosting.

## Tài liệu liên quan

- [Ownership](../TEAM-OWNERSHIP.md), [API hiện có](DELIVERY-API.md), [cấu hình giá đã duyệt](DISTANCE-WEIGHT-PRICING-PROPOSAL.md).
- [Kiểm thử đợt triển khai trước](CUSTOMER-API-VALIDATION.md); sửa tài liệu ngày 06/11 không chạy lại kiểm thử nghiệp vụ/provider hoặc đổi DB.
- [Nối UI Customer](../app/Views/customer/CUSTOMER.md), [mục lục hiện hành/lịch sử](README.md).
