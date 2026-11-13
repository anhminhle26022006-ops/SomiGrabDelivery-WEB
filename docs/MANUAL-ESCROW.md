# Thanh toán giữ tiền và hoàn tiền thủ công

- Nhánh MINH-ANH đã cập nhật origin/dev tại d70aaeb.
- Cấu hình SOMI_BANK_NAME, SOMI_BANK_ACCOUNT, SOMI_BANK_ACCOUNT_NAME cho tài khoản admin nhận tiền; SOMI_PAYOUT_MODE=manual cho chi trả shipper. Khóa SOMI_MESSAGE_ENCRYPTION_KEY dùng mã hóa tài khoản nhận hoàn tiền.
- Chạy `php -d extension=pdo_pgsql database/migrate-customer-orders.php --apply` để thêm migration 20261113_manual_escrow. Migration đã áp dụng thành công trên database cấu hình trong phiên làm việc này. Khi triển khai môi trường khác cần chạy lại lệnh.
- Customer chọn BANK_TRANSFER: chuyển đúng số tiền và nội dung SOMI-PAY-{payment_id}. Admin Payments đối soát bằng sao kê, mã giao dịch và số tiền chính xác. Đơn chỉ bắt đầu tìm shipper sau xác nhận nhận tiền.
- Customer lưu tài khoản ngân hàng ở Tài khoản, xác thực mật khẩu hiện tại; lưu từng phiên bản mã hóa. Shipper giữ luồng ngân hàng có OTP email hiện có.
- Matching hết lượt: hệ thống hủy và yêu cầu hoàn toàn bộ tiền, không khấu trừ phí. Worker xử lý Order events và REFUND phải hoạt động; không có chuyển tiền tự động.
- Admin Refunds chọn ghi nhận bằng chứng: xem customer, trạng thái đơn, số tiền và tài khoản ngân hàng; thực hiện chuyển tiền ở ngân hàng, sau đó nhập mã giao dịch, tham chiếu gốc và chứng từ. Tài khoản đã chọn được khóa vào khoản hoàn, trạng thái SUCCEEDED chỉ có sau đối soát. Tài khoản ngân hàng là thông tin customer khai báo, admin cần kiểm tra chủ tài khoản trước khi chuyển.
- Admin Payouts giữ luồng dựa trên kết quả DELIVERED / RETURNED và chính sách thu nhập; chờ giải quyết hoàn tiền trước khi chi, lưu ngân hàng tại thời điểm tạo payout, đối soát chứng từ trước khi đánh dấu thành công. Customer xem refunds trong chi tiết đơn; shipper xem trạng thái chi trả trong Thu nhập.
- Admin Payments hiển thị trạng thái đơn và giữ/trả tiền khi mở chi tiết. SePay CARD/GATEWAY vẫn dùng luồng hiện có, hoàn tiền SePay qua đối soát phương thức gốc.
- Bản đồ đã có: shipper watchPosition, gửi GPS 15 giây, customer polling 15 giây, cảnh báo vị trí quá 2 phút và ngừng chia sẻ khi đơn kết thúc. Đã thêm thông báo thiếu quyền/lỗi GPS. Cần HTTPS hoặc localhost và cấp quyền vị trí; trình duyệt phải còn chạy.

## Kiểm thử

`php -d extension=pdo_pgsql tests/manual_escrow.php` tạo toàn bộ bảng TEMP, không ghi dữ liệu thật: nhận tiền thủ công, idempotency, chặn matching trước xác nhận, hoàn 100% khi không có shipper, từ chối sai số tiền, khóa đích nhận và chống hoàn lặp.

Kết quả phiên triển khai: manual_escrow 11/11, SePay 40/40, shipper_delivery 58/58, person3_backend 122/122; PHP lint, Node syntax và git diff --check đều qua. Thông tin ngân hàng admin đã được cấu hình trong .env local (gitignored). Chưa thử chuyển tiền thật hay kiểm thử toàn bộ giao diện bằng trình duyệt.
