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

## Điểm và thu nhập — người dùng đã duyệt trong phiên 12/11/2026

Quyết định nghiệp vụ dưới đây được người dùng chốt sau khi xem đề xuất; không đồng nghĩa đã cấu hình DB hoặc triển khai đầy đủ trên hosting.

| Nội dung | Mức đã duyệt |
|---|---|
| Giao thành công | Shipper hưởng 80% tổng cước, làm tròn lên đơn vị nhỏ nhất |
| Giao thất bại, hoàn hàng hợp lệ | Shipper nhận 10.000 VND sau khi hai bên xác nhận và khoản hoàn Customer đã đối soát |
| Thời điểm chi trả | Ghi nhận thu nhập và tạo yêu cầu chi trả ngay khi đủ điều kiện hoàn thành từng đơn; không gom lịch hằng tuần |
| Cách chi trả hiện tại | Chuyển khoản thủ công; chỉ PAID sau chứng từ và đối soát, không cam kết tiền tự về ngay khi tạo yêu cầu |
| Điểm khởi đầu / trần | 100 / 100 |
| Hủy trước lấy hàng do lỗi Shipper | Trừ 10 điểm |
| Vi phạm sau lấy hàng được Admin xác minh | Trừ 20 điểm |
| Giao thành công hợp lệ | Cộng 2 điểm, tối đa 100 |
| Ngưỡng nhận đơn | Dưới 60 điểm thì ngừng nhận đơn mới; đúng 60 vẫn đủ điều kiện về điểm |
| Từ chối/hết hạn offer, lỗi Customer/hệ thống, không ghép được Shipper | Không trừ điểm |

Chưa đặt mức bồi hoàn USD vì checkout USD vẫn khóa. Sự cố khách quan/khiếu nại chưa kết luận không tự trừ điểm. Code đã bổ sung `post_pickup_deduction_points`, `max_score`, sự kiện điểm có khóa chống trùng và snapshot policy. Mỗi assignment chỉ áp dụng một lần cho cùng loại vi phạm để tránh trừ hai lần khi Admin thao tác lặp. Thu nhập và yêu cầu chi trả được tạo trong transaction hoàn tất giao hàng hoặc đối soát hoàn tiền; worker tiếp tục làm cơ chế thử lại. Yêu cầu dừng/hủy chưa được duyệt sẽ giữ cả phục hồi và thu nhập cho tới khi Admin kết luận. Hoàn hàng có lỗi Shipper đã xác minh được giữ để kiểm tra tài chính, không tự cấp bồi hoàn.

Migration `20261112_shipper_policy_workflows` và `ApprovedShipperPolicyRelease` sẽ ban hành các mức đã duyệt đúng một lần, đóng các policy hiện hành trước đó và ghi audit SYSTEM. **Chưa chạy migration trên DB hosting trong đợt triển khai code này.** Sau deploy, Admin có thể sửa policy có audit; Lưu policy điểm/earning có hiệu lực ngay, không phải bản nháp. Đơn đã hoàn thành trước thời điểm hiệu lực vẫn dùng policy tại thời điểm hoàn thành, không tính lại theo mức mới.

Policy earning được chọn theo thời điểm `delivered_at` hoặc `customer_return_confirmed_at`. Policy mới không tự tạo thu nhập cho đơn hoàn thành trước ngày hiệu lực. Không thay snapshot giá/phí của đơn cũ ngoài ngoại lệ miễn phí hết lượt tìm đã được người dùng duyệt ở trên.

## Thanh toán và hoàn tiền hiện tại — xác nhận giữ nguyên

Người dùng xác nhận tiếp tục luồng hiện hành: Customer thanh toán qua SePay, IPN/đối soát hợp lệ ghi PAID rồi mở matching. Nếu hết lượt tìm, tự hủy và tạo yêu cầu hoàn toàn phần; Admin thực hiện chuyển hoàn và đối soát chứng từ thủ công. Chưa phát triển SePay tự hoàn, không gọi chuyển tiền tự động. Tạo yêu cầu hoặc PROCESSING chưa phải đã hoàn tiền.

## Hướng phát triển để đưa vào báo cáo: tạm giữ tiền, thu hoặc giải phóng sau

Đoạn có thể dùng trong phần hướng phát triển:

> Trong giai đoạn tiếp theo, hệ thống dự kiến tích hợp cơ chế ủy quyền thanh toán và tạm giữ tiền (authorization), chỉ xác nhận thu tiền (capture) khi ghép được Shipper. Nếu không ghép được trong thời hạn, hệ thống hủy ủy quyền (void/cancel) để giải phóng khoản tiền tạm giữ cho Customer. Cơ chế này giúp giảm số giao dịch phải hoàn tiền thủ công. Thời gian giải phóng khoản giữ phụ thuộc ngân hàng hoặc nhà cung cấp thanh toán.

Cách gọi “giữ rồi hoàn sau” trong trao đổi được hiểu là hướng phát triển trên: khi chưa capture, thao tác là giải phóng khoản giữ; nếu đã capture thì mới cần refund. Cần lựa chọn/xác nhận nhà cung cấp và phương thức hỗ trợ authorize/capture/void; chưa khẳng định QR chuyển khoản SePay hiện tại hỗ trợ. Khi triển khai phải bổ sung trạng thái AUTHORIZED, thời hạn giữ, xử lý capture thất bại, tranh chấp nhận đơn/hết hạn và idempotency. Không đổi luồng hiện tại hoặc đánh dấu tính năng này đã hoàn thành.
