# Người 1 — tích hợp và bằng chứng ngày 12/11/2026

Đọc thay cho danh sách tồn ở bản rà soát `cc82c7a`. Source đầu vào đã pull `bed4549`; thay đổi/báo cáo trước đó đã commit `0584c88`. Tài liệu này phân biệt code đã kiểm thử với tính năng đang chạy trên hosting.

## Đã triển khai cục bộ

- Customer: màn chi tiết và tracking gọi `customer/shipper-location.php` cho SHIPPER_ASSIGNED/PICKED_UP/IN_DELIVERY/RETURNING. Poll mỗi 15 giây sau khi request trước kết thúc; không gọi khi tab ẩn, dừng khi rời trang/đơn kết thúc/lỗi quyền. Hiện marker, thời điểm cập nhật và cảnh báo vị trí quá 2 phút. Không có GPS thì không giả lập marker. Màn tracking vẫn có tuyến Geoapify.
- Customer: RETURNING có checkbox đã nhận hàng thực và POST `customer/confirm-return.php`. Lỗi Shipper chưa bàn giao được giải thích; nút khóa trong khi gửi. Backend hiện hữu vẫn yêu cầu ảnh thật và hai bên xác nhận trước RETURNED/refund.
- Customer: danh sách đơn tải trang đầu 50 bản ghi, có nút tải thêm theo cursor; bộ lọc áp dụng trên phần đã tải và UI ghi rõ. Dashboard thống kê toàn bộ vẫn dùng luồng hiện hành; chưa tuyên bố tối ưu dashboard với hàng nghìn đơn.
- Admin: từ hồ sơ Shipper mở tài liệu KYC, từ đơn mở bằng chứng. API `admin/files.php` chỉ liệt kê file đã gắn vào shipper_documents/delivery_evidence và chưa hết retention, có audit. KYC yêu cầu storage.read_support + shipper.verify. Bằng chứng giữ yêu cầu ticket hỗ trợ, order.read_all và support.manage_all theo contract Storage. Ảnh đi qua signed download có xác thực và audit, rồi preview blob; URL bị thu hồi khi đóng/thay modal. PDF dùng tải xuống.
- IPN: callback mới ghi `SEPAY_IPN_ACCEPTED` trong transaction với kết quả xử lý, gồm cờ duplicate, không ghi payload/secret. Giúp phân biệt IPN với GET reconciliation từ lần deploy sau.
- Chính sách không có Shipper: tự hủy, không phí và reserve hoàn toàn phần; có sweep các đơn tồn và migration lý do refund. Chi tiết và phần điểm/earning chờ duyệt: [quyết định policy](PERSON1-POLICY-DECISIONS-2026-11-12.md).
- Scheduler: thêm Người 1 sau matching để xử lý hết lượt tìm cùng chu kỳ; thêm GPS retention khi khởi động rồi mỗi 24 giờ, retry chu kỳ sau nếu lỗi. Có log timestamp/duration mỗi chu kỳ. Retention chạy lại khi container restart là an toàn; không cần cron thứ hai cho các worker này trên cùng container.

## SePay thật: phần đã xác minh

Đối chiếu chỉ đọc đơn `SOMI-D54D4DDAC257C00C598E` qua DB cấu hình local và GET SePay merchant API:

| Bằng chứng | Kết quả |
|---|---|
| SePay giao dịch APPROVED / đơn CAPTURED | Đạt, đúng amount và currency |
| Payment ứng dụng | PAID, 24.432 VND; paid_at 22:33:15 ngày 11/11/2026 GMT+7 |
| payment_transactions | Đúng 1 dòng CALLBACK / PAYMENT:PAID |
| PaymentConfirmed / OrderReadyForMatching | Mỗi loại đúng 1 event |
| Matching consumer | Có receipt shipper-matching; hiện NO_SHIPPER_FOUND, chưa có offer |
| Worker Người 1 / Người 3 | Mỗi consumer đã xử lý 4 event của đơn; receipt gần nhất 03:27 / 03:26 ngày 12/11 GMT+7 |
| IPN HTTP 200 do SePay gửi | **Chưa đủ bằng chứng nguồn**; bản cũ dùng chung event ID giữa IPN và reconciliation, không có audit nguồn |

`php bin/person1-acceptance-check.php SOMI-D54D4DDAC257C00C598E --provider` chạy READ ONLY, GET SePay, không applyVerified/checkout/hoàn tiền. Chỉ in trạng thái/tổng hợp, không email/token/khóa. DB local config chưa tự chứng minh cấu hình hay lịch chạy hosting.

`php bin/sepay-connection-check.php` đã chạy lại: merchant API 200; hosting callback GET 405, thiếu khóa 401, đúng khóa + JSON lỗi 400; ready=true, valid_ipn_sent=false. Không tạo giao dịch hay gửi IPN giả hợp lệ. Cần log IPN 200 từ SePay cho đơn trên để đóng đủ chuỗi nghiệm thu; không cần chuyển thêm tiền chỉ để có PASS.

## Inbox và vận hành hosting

Gmail kết nối có thư tạo đơn và Payment confirmed của đơn trên ở INBOX, thư payment lúc 03:03:30 ngày 12/11 GMT+7. Có thư verification/reset ngày 09/11; các thư ngày 09/11 thuộc nghiệm thu local trước đó và link reset là localhost, không phải bằng chứng reset trên hosting. Không đọc/ghi OTP vào tài liệu, không gửi thêm email trong lượt này. Chưa có bằng chứng OTP đổi ngân hàng; chưa thử lại inbox end-to-end trên bản mới.

DB có một job PAYMENT_RECONCILE FAILED và một DONE; chưa đối chiếu nguyên nhân job FAILED theo đơn và không tự retry để lấy kết quả PASS. Các email hiện ghi ACCEPTED được đối chiếu riêng với INBOX, không suy ra từ số jobs_attempted. Thư payment đến muộn khoảng 4 giờ 30 phút so với paid_at; cần kiểm tra backlog/lịch worker và độ trễ với thư mới sau deploy, không dùng lần này để chứng minh email tức thời.

Hosting public `https://somigrabdelivery.online/` trả 200, đo một lượt GET: trang chủ 629 ms; customer/server.js 908 ms; admin-live.js 428 ms. Hai JS remote khớp source bed4549 sau chuẩn hóa CRLF/LF, chưa khớp phần sửa cục bộ. Đây chỉ là phép đo public, không load test, không phải p95 API có đăng nhập. Bằng chứng lịch worker lịch sử có trong MINH-ANH-STATUS; chưa truy cập log Render mới trong lượt này.

## Kiểm thử

- Backend cách ly PostgreSQL/TEMP tables: person1_scope 25, person1_backend 74, admin_console 37, customer_orders 113 kiểm tra PASS. shipper_matching 53, shipper_delivery 58 và sepay 40 kiểm tra PASS.
- Browser Customer: 76 kiểm tra PASS; tải dần, GPS marker/no-location/lỗi phiên, xác nhận hoàn sai trạng thái/thành công và VI/EN; API/GPS dùng fixture.
- Browser Admin: 241 kiểm tra PASS; 15 màn hình, 1440/768/375 px, preview ảnh qua signed bytes fixture, KYC/bằng chứng và từ chối thiếu ticket.
- Không ghi DB ứng dụng, không tạo payment/refund thật, không chuyển tiền hoặc tự ban hành điểm/earning.

## Đưa lên hosting và đóng nghiệm thu còn lại

1. Deploy source sau review; bật SOMI_MIGRATE_ON_START=1 hoặc chạy `php database/migrate-customer-orders.php --apply` trước worker. Migration mới: 20261112_no_shipper_refund. Chưa chạy migration DB ứng dụng trong lượt này.
2. Một scheduler cho mỗi cấu hình vận hành đã chọn, SOMI_RUN_WORKERS=1; tránh thêm cron trùng cho cùng worker. Giới hạn số replica/connection phải theo ngân sách DB. Supervisor www-data giữ HOME=/var/www như bản đã pull.
3. Xác minh asset mới; log có scheduler_cycle_finished_at, GPS retention và Người 1/matching/Người 3 thành công. Sweep sau deploy sẽ tự xử lý đơn NO_SHIPPER_FOUND đang tồn tại; refund SePay chỉ reserve/chờ operator.
4. Lấy log IPN SePay 200 cho giao dịch đã có, đối chiếu ledger/event chỉ đọc. Sau bản mới có thêm audit nguồn cho callback tiếp theo.
5. Phiên thật ba vai trò: Shipper upload KYC/ảnh, Admin xem/duyệt; Customer xem GPS và xác nhận hàng hoàn; kiểm tra cross-account, mobile và Inbox OTP ngân hàng. Không coi browser fixture thay thế các bước này.
6. Đo API Customer có session thật sau deploy, thời gian tải trang đầu và tải thêm. Kiểm tra log DB connection/timeout; không bật persistent chỉ dựa trên GET public.
7. Nhóm chọn mức điểm/earning trong tài liệu policy rồi Admin ban hành. Chưa có quyết định số thì giữ chưa cấu hình.
