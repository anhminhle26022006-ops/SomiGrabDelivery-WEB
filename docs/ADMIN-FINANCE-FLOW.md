# Admin: xử lý đơn, ngân hàng và tiền

## Điểm vào giao diện

- **Tiền theo đơn**: tìm kiếm tự động khi gõ; mở đơn để xem cùng lúc trạng thái giao hàng, tiền nhận, khoản hoàn customer, khoản chi shipper và ảnh biên lai. Link Hoàn tiền cũ chuyển về trang này.
- **Duyệt ngân hàng**: xem tên người dùng, vai trò, ngân hàng, chủ tài khoản, số tài khoản. Chấp thuận hoặc yêu cầu sửa với ghi chú. Customer và shipper xem được kết quả trong Tài khoản. Thay đổi ngân hàng mới luôn chờ duyệt. Các tài khoản cũ được đưa về chờ kiểm tra trong migration.
- **Khách hàng / Shipper & KYC → Chi tiết**: xem thông tin ngân hàng hiện tại và đường dẫn sang duyệt ngân hàng.
- **Đơn hàng → Chi tiết**: xem hành trình, ảnh giao/hoàn trực tiếp và mở hồ sơ Tiền theo đơn. Ảnh bằng chứng không yêu cầu tạo ticket, nhưng vẫn kiểm tra quyền admin và ghi audit. Chat riêng vẫn cần ticket liên quan.
- **Ticket hỗ trợ**: đọc chi tiết, chuyển trạng thái và trao đổi với người gửi. Sửa lỗi so sánh role admin thường/hoa ở API hỗ trợ và storage gây 403.

## Quy trình thủ công

1. Customer chuyển tiền vào ngân hàng admin với nội dung đã cấp.
2. Admin kiểm tra sao kê, chọn **Xác nhận đã nhận tiền**, tải ảnh biên lai và xác nhận đúng số tiền. Backend tự lấy số tiền/loại tiền/đơn, tạo mã nội bộ, lưu ảnh mã hóa và đối soát trong một transaction. Matching chỉ bắt đầu sau khi tiền được xác nhận.
3. Đơn giao xong mới ghi thu nhập shipper. Ngân hàng shipper cần được duyệt trước khi chuẩn bị khoản trả. Admin chuyển ngân hàng bên ngoài rồi tải biên lai để xác nhận đã trả. Khoản trả giữ nguyên ngân hàng đã chốt.
4. Đơn không có shipper tạo yêu cầu hoàn toàn bộ. Đơn hủy hoặc hoàn hàng dùng chính sách phí trên đơn. Admin xem số tiền hoàn, phí giữ lại và ngân hàng customer đã duyệt; chuyển tiền bên ngoài rồi tải ảnh biên lai. Thanh toán gốc cập nhật REFUNDED / PARTIALLY_REFUNDED cùng khoản hoàn SUCCEEDED trong cùng transaction.
5. Thanh toán SePay hoàn qua phương thức gốc; ảnh biên lai xác nhận phương thức gốc được lưu cùng hồ sơ. Không tự chuyển sang ngân hàng khác.
6. Mỗi thao tác PAYMENT / REFUND / PAYOUT có một ảnh biên lai; thao tác lặp không tạo khoản mới. Đây là sổ xác nhận, không gửi lệnh chuyển tiền ngân hàng.

Nếu một ngân hàng cũ không giải mã được, các tài khoản khác vẫn tải bình thường; tài khoản lỗi hiện yêu cầu người dùng lưu lại và không được duyệt. Không suy đoán số tài khoản hay sửa khóa mã hóa để bỏ qua lỗi.

## Triển khai và kiểm tra

Migration `20261113_admin_finance` đã áp dụng bằng transaction trên database cấu hình. Khi triển khai môi trường khác chạy `php -d extension=pdo_pgsql database/migrate-customer-orders.php --apply`.

Kiểm thử: `tests/admin_finance.php`, `tests/admin_finance_upload.cjs`, `tests/admin_flow_browser.cjs`, `tests/bank_account_browser.cjs`, `tests/person1_scope.php`, `tests/admin_console.php`, `tests/person3_backend.php`. Các bài database dùng bảng TEMP; bài kiểm tra dữ liệu đang dùng rollback toàn bộ audit. Chưa thử chuyển tiền thật.
