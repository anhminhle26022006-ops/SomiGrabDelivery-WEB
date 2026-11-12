# Chính sách Người 1 — 12/11/2026

## Quyết định đã được người dùng chốt

**Hết lượt tìm Shipper → tự hủy và yêu cầu hoàn toàn phần.** Quyết định trực tiếp trong phiên làm việc ngày 12/11/2026, chưa áp dụng trên hosting trước khi deploy.

- Matching giữ giới hạn hiện hành: tối đa 3 vòng, mỗi vòng tối đa 3 Shipper, offer 60 giây; timeout tìm kiếm 300 giây. Không có ứng viên thì chờ đến timeout. Matching là owner Người 2, không đổi thuật toán/ranking trong đợt này.
- Sau sự kiện `NoShipperFound`, worker Người 1 khóa đơn và chỉ xử lý khi vẫn `NO_SHIPPER_FOUND`, không có assignment ACTIVE. Chuyển `CANCELLED`, phí hủy bằng 0, tạo một refund `NO_SHIPPER_FOUND` bằng toàn bộ số tiền đã thanh toán, giữ đúng currency. Lịch sử/audit/refund/job nằm cùng transaction; lỗi hoàn tiền rollback cả hủy.
- Worker quét lại các đơn NO_SHIPPER_FOUND từ trước bản phát hành, kể cả event đã có receipt. Đây là thay đổi có tác động lên đơn đang tồn tại sau deploy; không cần chỉnh tay DB hoặc gửi lại callback.
- Scheduler chạy Người 1 → matching → Người 1 → Người 3 mỗi chu kỳ khoảng 60 giây. Việc hủy diễn ra trong lượt xử lý worker, không bảo đảm tức thời đến từng giây khi có backlog/lỗi DB. Không tự tìm lại.
- Hủy bằng Customer/Admin khi đã NO_SHIPPER_FOUND cũng miễn phí, kể cả snapshot cũ có phí. Các trạng thái hủy khác giữ snapshot.
- Không sinh earning khi chưa có Shipper/giao nhận. Không trừ điểm bất kỳ Shipper nào chỉ vì hệ thống không ghép được người giao.
- Với SePay, refund vào hàng chờ rồi `PROCESSING / SEPAY_REFUND_REVIEW_REQUIRED`. Admin phải thực hiện chuyển hoàn về phương thức gốc và đối soát bằng chứng trước `SUCCEEDED`. Tự tạo refund không có nghĩa tiền đã về tài khoản Customer.

## Điểm và thu nhập — chưa được duyệt mức số

Database được kiểm tra chỉ đọc trong phiên này chưa có policy điểm/earning đang hiệu lực. Không seed hoặc kích hoạt tỷ lệ/điểm suy đoán.

| Trường | Ý nghĩa | Ví dụ minh họa, không phải mức đã duyệt |
|---|---|---|
| `percentage_rate` | Phần trăm tổng cước trả Shipper khi giao thành công; làm tròn lên đơn vị nhỏ nhất | Cước 30.000 VND, chọn 80% thì Shipper được 24.000 VND |
| `fixed_amount_minor` | Khoản cố định cho đơn giao thất bại nhưng hoàn hàng đủ hai bên xác nhận; chỉ accrue sau refund Customer đã đối soát | VND nhập đồng, USD nhập cent; phải xác định khoản này do phí hoàn hay nền tảng chi trả |
| `deduction_points` | Số điểm trừ cho một sự kiện vi phạm đã được duyệt | Cần phân biệt từ chối offer với hủy sau accept; không tự coi không nhận offer là vi phạm |
| `lock_threshold` | Điểm thấp hơn mức này thì không đủ điều kiện nhận đơn | Ví dụ ngưỡng 60 thì 59 không đủ điều kiện, 60 vẫn đạt |
| `recovery_points` | Số điểm phục hồi theo sự kiện đã được duyệt | Cần chốt điều kiện phục hồi, trần điểm và xử lý khi có khiếu nại |

Trước khi ban hành cần nhóm chọn tỷ lệ giao thành công, tiền bồi hoàn theo currency, các mức điểm và sự kiện áp dụng. Người 2 triển khai sự kiện trừ/khôi phục; Người 3 sử dụng policy earning/finance hold; Người 1 ban hành qua Admin có audit. UI Admin đã giải thích các trường và nhắc rằng **Lưu policy điểm/earning có hiệu lực ngay**, không phải lưu bản nháp. Chưa có căn cứ để tự kích hoạt.

Policy earning được chọn theo thời điểm `delivered_at` hoặc `customer_return_confirmed_at`. Policy mới không tự tạo thu nhập cho đơn hoàn thành trước ngày hiệu lực. Không thay snapshot giá/phí của đơn cũ ngoài ngoại lệ miễn phí hết lượt tìm đã được người dùng duyệt ở trên.
