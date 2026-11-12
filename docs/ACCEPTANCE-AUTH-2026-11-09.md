# Nghiệm thu mục 1 — tài khoản

Ngày: 09/11/2026 (Asia/Saigon).

Kết quả: PASS — 32 kiểm tra HTTP riêng biệt.

## Môi trường

- Ứng dụng local: http://127.0.0.1:3071/.
- Database riêng: somi_acceptance_test tại 127.0.0.1:55432.
- PostgreSQL portable trong tests/.runtime/acceptance-local; không cài service hệ thống.
- Tài khoản và hồ sơ giả lập được tạo qua API đăng ký; không sửa DB để bỏ qua xác minh.
- Hộp thư test do người dùng chỉ định; Resend gửi thật, xác nhận thư xác minh đến Inbox bằng Gmail. Người dùng cung cấp OTP và link reset từ thư nhận được.
- Điện thoại dùng OTP DEMO theo chấp thuận; không gửi SMS thật.

## Kết quả

| Kiểm tra | Kết quả |
|---|---|
| registration uses real HTTP and explicit phone demo | PASS |
| unverified account cannot access protected profile | PASS |
| pending account resumes phone verification through login | PASS |
| incorrect phone OTP rejected | PASS |
| correct demo phone OTP verified | PASS |
| consumed phone OTP cannot be reused | PASS |
| phone verified before email request | PASS |
| verification email queued via real API | PASS |
| incorrect email OTP rejected | PASS |
| email code from test inbox activates account | PASS |
| consumed email OTP cannot be reused | PASS |
| logout succeeds | PASS |
| logged-out session denied | PASS |
| incorrect password rejected | PASS |
| verified customer signs in with original password | PASS |
| authenticated customer profile uses VND language | PASS |
| change password rejects incorrect current password | PASS |
| change password requires reauthentication | PASS |
| password change revokes old session | PASS |
| old password rejected after change | PASS |
| login with phone and new password succeeds | PASS |
| password reset requested via real API | PASS |
| invalid reset token rejected | PASS |
| inbox reset token changes password | PASS |
| consumed reset token cannot be reused | PASS |
| password reset revokes previous signed-in session | PASS |
| pre-reset password rejected | PASS |
| new reset password signs in successfully | PASS |
| expired phone OTP rejected (local fixture) | PASS |
| expired email OTP rejected (local fixture) | PASS |
| expired reset token rejected (local fixture) | PASS |
| expired credentials do not alter verified account or current session | PASS |

## Giới hạn và dữ liệu

- Kiểm tra API/HTTP; không tuyên bố đã nghiệm thu giao diện bằng browser ở lượt này.
- Các ca hết hạn dùng credential fixture đã hết hạn trong database test; không làm đổi trạng thái xác minh hoặc mật khẩu đã đạt.
- Worker chạy thủ công có giới hạn để gửi email xác minh/reset; chưa chứng minh scheduler định kỳ hoặc worker hosting.
- Không ghi dữ liệu vào database ứng dụng thật; không chỉnh .env gốc.
- Mật khẩu, OTP, reset token, cookie/session và credential database chỉ lưu tại runtime bị Git bỏ qua, không có trong báo cáo.
- Mục 2–7 chưa được đánh dấu hoàn thành.

## Bằng chứng cục bộ

- tests/.runtime/acceptance-local/auth-results.json
- tests/.runtime/acceptance-local/item1-progress.json
- tests/.runtime/acceptance-local/auth-check.py
- tests/.runtime/acceptance-local/manage.py
