# Hợp đồng tích hợp Người 1 / Người 2 / Người 3

> Historical snapshot. Current integration, approved pricing, SePay/payment/email APIs and ownership: [PERSON1-API.md](PERSON1-API.md), [BACKEND-SCOPE.md](BACKEND-SCOPE.md).


Đây là hợp đồng đề xuất đã có kiểm thử phía Người 1; chưa có xác nhận triển khai của Người 2/3. Không coi simulator trong test là matching/payment production.

## Phạm vi

- Người 1: nền tảng, identity/session/permission, hồ sơ Customer, địa chỉ, API đọc đơn và hủy trước nhận trong giới hạn an toàn, tra cứu/quản trị Admin, bản nháp cấu hình giá, lịch sử/audit, migrations.
- Người 2: matching, offers, nhận/từ chối, delivery, vị trí/evidence, state transitions giao nhận. Không thêm endpoint thuộc các phần này trong đợt này.
- Người 3: payment, callback, reconciliation, refund, earnings/payout. Không thêm giao dịch tài chính giả để mở luồng tạo đơn.

## Danh tính và truy cập

API nội bộ HTTP tái sử dụng `_person1.php::person1_identity`, role hiện tại và permission trong DB. Với offer/order của Shipper, Người 2 phải lấy shipper_id từ session và thêm predicate ownership trong SELECT/UPDATE; không chỉ tin offer_id trong body. Không cấp permission Admin để né kiểm tra. Các dịch vụ chạy nền dùng identity riêng; không giả session của người dùng. Cách xác thực worker/provider callback cần Người 2/3 xác nhận riêng.

## Trạng thái chuẩn PostgreSQL

`app/Services/OrderContract.php::STATUSES` là vocabulary hiện có trong database: PENDING_PAYMENT, SEARCHING_SHIPPER, NO_SHIPPER_FOUND, SHIPPER_ASSIGNED, PICKED_UP, IN_DELIVERY, DELIVERED, RETURNING, RETURNED, CANCELLED.

Frontend demo dùng PENDING/MATCHING/ASSIGNED/COMPLETED và nhiều trạng thái khác; không ghi trực tiếp các giá trị đó vào orders.status, không tự ánh xạ mất thông tin. API mới đọc vocabulary DB. Cần chốt đầy đủ transition graph với Người 2/3 trước khi thay demo create/payment/tracking. Người 1 chỉ triển khai cạnh từ BEFORE_ASSIGNMENT sang CANCELLED, với điều kiện chưa có assignment ACTIVE và chưa có payment record/paid_at.

## Thứ tự khóa và transaction đơn

1. Bắt đầu transaction và khóa `orders WHERE order_id=:id FOR UPDATE`.
2. Kiểm lại trạng thái sau khi lấy lock, kiểm owner/role; không dựa vào trạng thái client cũ.
3. Sau đó mới khóa/ghi offer, assignment, payment, refund liên quan theo thứ tự được thống nhất. Không giữ offer/payment lock rồi quay lại chờ order lock.
4. Thay đổi trạng thái và INSERT order_status_history trong cùng transaction; actor_id lấy từ identity thật. Chỉ trả thành công sau commit.
5. Nhận đơn phải kiểm SEARCHING_SHIPPER, offer còn hạn/đúng shipper/PENDING, chưa có ACTIVE assignment và không CANCELLED. Chọn một người thắng; bên thua trả409. Không dùng update client array làm bằng chứng.

CustomerService::cancel làm theo order lock này. Kiểm thử hai tiến trình xác nhận cancel chờ assignment đang giữ lock rồi trả409; hai cancel đồng thời chỉ ghi một history; matching contract simulator không nhận được đơn đã hủy. Việc Người 2 đã tuân thủ contract: NOT_VERIFIED, vì chưa có backend tương ứng.

## Hủy và thanh toán/refund

Cancellation hiện trả409 PAYMENT_INTEGRATION_REQUIRED nếu có paid_at hoặc bất kỳ payments record, kể cả PENDING/FAILED. Đây là giới hạn thận trọng chờ Người 3 xác định giao dịch có thể capture/callback muộn. Không tự trả tiền, không tự sửa payments/refunds hoặc phí. Người 3 phải dùng cùng order lock trước khi tạo payment record/capture-state transaction, kiểm CANCELLED và bảo đảm callback idempotency. Không cho payment thành công sau khi cancel đã commit nếu chưa có xử lý đối soát/refund được thống nhất.

Đơn đã CANCELLED retry trả cùng trạng thái, không thêm history. Order read không làm thay đổi price snapshot. Chưa có API tạo đơn vì thiếu server quotation contract, không tuyên bố đã bảo vệ snapshot tạo đơn end-to-end.

## Báo giá / tiền tệ — cần quyết định

Schema hiện ràng buộc vi→VND, en→USD; UI demo cho đổi độc lập. Không tự gỡ CHECK. Cần chốt lựa chọn nào là chuẩn, nguồn tuyến đường/cự ly, provider thời tiết và thời điểm dự báo, cách áp dụng từng price band và hệ số, quy tắc ceil theo từng bước hoặc cuối, hạn báo giá, idempotency tạo đơn và cách chụp snapshot chính sách/fee components. Chưa có quyết định thì quote/create trả409, không dùng giá client.

Admin có thể lưu cấu hình nháp theo VND/USD, phí hủy/hoàn, phần trăm thu nhập, bồi hoàn và điểm Shipper; bản nháp không tác động pricing_policies, payments, earnings hoặc orders. Activation/versioning và hợp đồng snapshot còn BLOCKED_BY_BUSINESS_DECISION.

## Identity/KYC và quốc tịch

Review Shipper khóa user trước profile/identity để phối hợp với switch-workspace. APPROVED/REJECTED/NEEDS_INFO cập nhật cả profile/identity và audit trong transaction. Cần Người 2 phối hợp việc nộp lại/chụp giấy tờ, file evidence và permission đọc tài liệu nhạy cảm. API không trả ciphertext/số định danh đầy đủ.

Quốc tịch: user gửi request; Admin tra cứu/từ chối có audit. Approve đang chặn tới khi chốt loại giấy tờ thay thế, thời điểm mất hiệu lực giấy cũ và tái xác minh. Không âm thầm sửa nationality rồi giữ giấy tờ không khớp.

## Migration và triển khai

Canonical baseline + migration runner đã chạy trên PostgreSQL test sạch và chạy lại không áp dụng trùng. Không áp migration trên Supabase trong đợt này; chỉ đọc metadata đủ23 bảng. Cần review diff/RBAC/RLS và backup theo quy trình triển khai trước khi apply. Không sửa secret môi trường, không push/merge/deploy tự động.
