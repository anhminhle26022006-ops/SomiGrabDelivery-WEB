# Thanh toán giữ tiền thủ công

Hướng dẫn giao diện và quy trình hiện tại: [Admin — tiền theo đơn và duyệt ngân hàng](ADMIN-FINANCE-FLOW.md).

- Cấu hình `SOMI_BANK_NAME`, `SOMI_BANK_ACCOUNT`, `SOMI_BANK_ACCOUNT_NAME` cho ngân hàng admin nhận tiền. Thông tin đã lưu trong `.env` local, không đưa vào git.
- `SOMI_PAYOUT_MODE=manual`; dữ liệu ngân hàng và ảnh biên lai dùng khóa `SOMI_MESSAGE_ENCRYPTION_KEY` hoặc khóa dự phòng `SOMI_IDENTITY_ENCRYPTION_KEY`.
- Chạy `php -d extension=pdo_pgsql database/migrate-customer-orders.php --apply` ở môi trường triển khai. Migration của phiên này đã áp dụng thành công.
- Customer chuyển theo nội dung `SOMI-PAY-{payment_id}`; admin xác nhận bằng ảnh biên lai trong hồ sơ đơn. Luồng này không gửi lệnh chuyển tiền ngân hàng.
- Customer và shipper xem/chỉnh ngân hàng ngay trong Tài khoản. Thay đổi ngân hàng shipper có mật khẩu và OTP; tài khoản mới/thay đổi chờ admin duyệt trước khi dùng để trả tiền.
- Không tìm được shipper: yêu cầu hoàn 100%, không khấu trừ. Worker sự kiện/matching vẫn cần chạy theo cấu hình dự án.
- Bản đồ đã có shipper gửi GPS và customer tải vị trí mỗi 15 giây, cảnh báo vị trí quá 2 phút, ngừng chia sẻ khi đơn kết thúc. Cần HTTPS hoặc localhost và cấp quyền vị trí; trình duyệt phải còn chạy.

Các bài kiểm thử database dùng bảng TEMP, không ghi dữ liệu thật. Chưa thử chuyển tiền thật.
