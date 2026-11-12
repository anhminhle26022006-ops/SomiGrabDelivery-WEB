# Điểm, hủy/dừng đơn và chi trả theo từng đơn — 12/11/2026

Triển khai theo các mức đã được người dùng duyệt trong [quyết định policy](PERSON1-POLICY-DECISIONS-2026-11-12.md). Giữ nguyên Customer thanh toán → PAID → matching và hoàn tiền thủ công; authorize/capture chỉ là hướng phát triển.

## Hành vi đã nối

- Shipper tự hủy trước lấy hàng do lỗi của mình: kết thúc assignment CANCELLED_BEFORE_PICKUP, trừ 10 điểm, tìm Shipper khác trên cùng đơn đã thanh toán. Không hoàn tiền Customer và không trả lại offer cho chính Shipper đã hủy. Offer từ chối/hết hạn không trừ điểm.
- Sự cố khách quan trước lấy hàng và mọi yêu cầu dừng sau lấy hàng: tạo yêu cầu PENDING để Admin xác minh, chưa trừ điểm. Một assignment chỉ có một yêu cầu chờ; retry cùng client_key không tạo trùng. Thay nội dung dưới cùng key bị từ chối.
- Admin duyệt trước lấy hàng: giải phóng assignment và tìm lại; chỉ trừ 10 nếu đã xác minh lỗi Shipper. Duyệt sau lấy hàng: chuyển RETURNING, giữ assignment ACTIVE tới khi trả hàng đủ ảnh và hai bên xác nhận. Nếu có lỗi Shipper được Admin xác minh bằng chứng thì trừ 20, có audit và sự kiện finance review.
- Admin có thể xác minh vi phạm sau lấy hàng trực tiếp từ phân công trong chi tiết đơn. Client không gửi số điểm; backend đọc policy và chống trừ hai lần trên cùng assignment. Không lấy lý do Shipper tự khai làm kết luận lỗi sau lấy hàng.
- Nếu đơn đã kết thúc trong lúc yêu cầu còn chờ, Admin đóng yêu cầu bằng REJECTED, ghi kết luận lỗi/bằng chứng; không đảo trạng thái hoàn thành. Nếu không có lỗi, hệ thống xét lại phục hồi/thu nhập ngay. Nếu có lỗi, giữ kiểm tra tài chính cho bồi hoàn đơn hoàn.
- Giao thành công hợp lệ: +2 điểm, tối đa 100; dưới 60 tự tắt availability và không đủ điều kiện bật/nhận đơn. Đúng 60 vẫn đạt điều kiện điểm. Điểm không xuống dưới 0; không tự bật availability trở lại khi phục hồi. Admin khôi phục điểm phải trong ngưỡng và trần policy.
- Giao thành công: ghi nhận 80% tổng cước, làm tròn lên một lần bằng NUMERIC, tạo reservation chi trả VND ngay trong transaction hoàn tất đơn nếu ngân hàng đã xác minh và SOMI_PAYOUT_MODE=manual. Không đợi lịch hằng tuần. Chưa có ngân hàng thì giữ earning PENDING, worker thử lại sau khi có ngân hàng hợp lệ.
- Hoàn hàng: chỉ xét 10.000 VND sau hai bên xác nhận và refund Customer đã đối soát; đối soát refund gọi settlement ngay trong transaction. Chưa kết luận yêu cầu dừng hoặc đã xác minh lỗi Shipper thì không tự cấp bồi hoàn.
- Reservation là PROCESSING, earning vẫn PENDING; chỉ Admin đối soát chứng từ chuyển khoản mới SUCCEEDED/PAID. Không có API ngân hàng tự chuyển tiền, không gọi SePay tự hoàn. Refund và payout tiếp tục là hai luồng thủ công riêng.
- Worker settlement/event là cơ chế thử lại; UNIQUE theo đơn/earning và source_key của điểm chống lặp. Giữ snapshot earning và policy điểm, không tính lại lịch sử theo policy mới.

## API và giao diện

| API | Quyền / mục đích |
|---|---|
| POST shipper/cancellation.php | SHIPPER + delivery.update_assigned; {order_id, reason_code: SHIPPER_FAULT hoặc EXTERNAL_ISSUE, reason, client_key} |
| GET admin/shipper-cancellations.php | order.cancel_admin; mặc định hàng chờ, hoặc ?order_id=, cursor before_id |
| POST admin/shipper-cancellations.php | order.cancel_admin; {request_id, decision: APPROVED hoặc REJECTED, shipper_at_fault: boolean, note, evidence_reference}; xác nhận lỗi còn cần shipper.verify |
| POST admin/shipper-violation.php | shipper.verify; {assignment_id, shipper_at_fault:true, reason, evidence_reference}; phải có pickup thật trong deliveries |
| POST admin/policies.php / SCORE | Thêm post_pickup_deduction_points và max_score; caller cũ bỏ hai trường thì giữ giá trị hiện hành |

UI Shipper Đơn đang giao có form yêu cầu và xác nhận đã đọc điều kiện, lý do, phân loại lỗi; giữ client_key khi retry không chắc kết quả. UI Admin Đơn hàng có hàng chờ, chi tiết đơn có yêu cầu liên quan và các phân công để xác minh vi phạm. Form điểm hỗ trợ hai mức trừ và trần.

## Migration và rollout

Chạy `php database/migrate-customer-orders.php --apply` trong môi trường triển khai hoặc SOMI_MIGRATE_ON_START=1 trước phục vụ code mới. Migration mới mở rộng cột assignment_status từ VARCHAR(20) lên VARCHAR(30), vì enum CANCELLED_BEFORE_PICKUP vốn đã có nhưng không vừa cột cũ. Thêm schema điểm và yêu cầu hủy; bản release policy được chạy trong cùng transaction, có khóa và audit chống áp dụng hai lần.

Bản release đặt VND 80% / 10.000đ, điểm 10/20/60/+2/trần100 đúng quyết định đã duyệt. Không seed earning USD, không reset điểm hiện có, không đổi snapshot đơn cũ. Policy mới áp dụng theo ngày hiệu lực; không tự tạo thu nhập cho các đơn cũ vốn không có policy tại thời điểm hoàn thành. Readiness kiểm tra cả migration no-shipper-refund và shipper-policy-workflows.

Chưa migrate/deploy DB ứng dụng hoặc kích hoạt trên hosting trong lượt này. Khi deploy cần kiểm tra phiên thật Shipper → Admin → Customer, receipt/earning/payout và log scheduler; PROCESSING không phải chứng nhận đã chuyển tiền. Nếu lỗi Shipper chỉ được phát hiện sau khi đã chi trả, hệ thống giữ audit/finance review, không tự thu hồi tiền đã chuyển; phần xử lý tài chính tiếp theo cần Admin đối soát.

## Kiểm thử

- tests/shipper_policy_workflows.php: release một lần, trừ/khôi phục và trần/sàn, lỗi khách quan/chờ duyệt, chống trùng, hủy/rematch không nhận lại cùng đơn, manual refund → earning → payout, OTP ngân hàng cách ly, pending review được đóng sau hoàn thành, thiếu policy rollback.
- tests/shipper_policy_browser.cjs: form Shipper, bắt buộc lý do/xác nhận, khóa double submit, client_key giữ qua retry, trạng thái chờ và thông báo chi trả thủ công; HTTP fixtures.
- tests/admin_browser.cjs: hàng chờ, duyệt có chứng cứ, xác minh theo assignment, form hai mức điểm; 15 view và 3 kích thước màn hình.
- tests/person3_http.py: kiểm tra xác thực/origin/method của API mới trên loopback, không truy cập DB ứng dụng.

Kết quả hoàn tất kiểm tra ngày 13/11/2026 (Asia/Saigon):

| Kiểm tra | Số kiểm tra đạt |
|---|---:|
| shipper_policy_workflows | 62 |
| shipper_matching | 53 |
| shipper_delivery | 58 |
| person3_backend | 122 |
| person1_backend | 74 |
| admin_console | 37 |
| person1_scope | 25 |
| shipper_accept_race | 40 |
| admin_browser | 247 |
| shipper_policy_browser | 9 |
| person3_http | 33 |

Tổng 760 kiểm tra đạt: 471 backend, 256 browser và 33 HTTP. Race test xác nhận cả trường hợp accept thắng và cancel thắng. Service tests dùng TEMP tables; race test dùng schema riêng trong PostgreSQL test cách ly và dọn sau khi chạy. Browser dùng API fixture. Không gửi email, không gọi provider, không chuyển tiền hoặc ghi DB ứng dụng. Cú pháp PHP/JS thay đổi và git diff --check đều đạt. Đây là kiểm thử code cục bộ, chưa thay thế nghiệm thu có đăng nhập trên hosting.
