# Hồ sơ đăng ký Shipper

Khách hàng mở **Avatar → Đăng ký làm Shipper**. Tài khoản đã có vai trò Shipper mở **Avatar → Chuyển sang Shipper**. Hồ sơ bị trả về hoặc hồ sơ cũ chưa đầy đủ được bổ sung tại trang xác minh Shipper.

## Thông tin bắt buộc

- Tài khoản đủ 18 tuổi, đã xác thực email và điện thoại; sử dụng họ tên, ngày sinh và thông tin định danh đã đăng ký.
- Địa chỉ cư trú, khu vực hoạt động, người liên hệ khẩn cấp, điện thoại và mối quan hệ.
- Loại xe, hãng, dòng xe, màu, năm sản xuất, chủ xe và quyền sử dụng xe.
- Xe máy/ô tô: biển số, số và hạng GPLX, ngày cấp, hạn GPLX hoặc xác nhận không thời hạn, hạn bảo hiểm TNDS.
- Ảnh định danh hai mặt/trang tương ứng, ảnh chân dung; xe máy/ô tô cần thêm GPLX hai mặt, đăng ký xe và bảo hiểm. Xe mượn/thuê cần giấy ủy quyền hoặc hợp đồng.
- Xác nhận thông tin và đồng ý lưu, đối chiếu hồ sơ; lưu phiên bản xác nhận và thời điểm phía server.

## Kiểm tra và duyệt

API kiểm tra dữ liệu, ngày hợp lệ, tuổi, tài khoản đã xác thực, bộ giấy tờ theo phương tiện và quyền sở hữu từng file. File KYC giới hạn 5 MiB, kiểm tra MIME tại server; ảnh chân dung phải là ảnh. Không sử dụng cùng một file/nội dung cho nhiều loại giấy tờ. Số GPLX được mã hóa bằng `IdentityDocumentVault`, không trả về trong API xem hồ sơ và không ghi vào audit.

Admin vào **Shippers → Chi tiết → Thông tin đăng ký / Xem tài liệu KYC**. Admin cần đối chiếu tên, ảnh, số định danh, số GPLX, hạng GPLX phù hợp phương tiện, ngày hết hạn và quyền sử dụng xe. Hệ thống kiểm tra độ đầy đủ; việc xác minh tính thật của giấy tờ và quyền lái xe do Admin thực hiện, chưa tích hợp OCR hoặc nhà cung cấp xác minh danh tính.

Hồ sơ thiếu, giấy tờ hết hạn, file bị xóa/hết thời gian lưu hoặc phương tiện không khớp sẽ không được duyệt. Hồ sơ cũ thiếu dữ liệu cần yêu cầu bổ sung. Tài khoản Shipper đã được duyệt trước bản cập nhật vẫn chuyển workspace như trước.

## Triển khai

Chạy lệnh migration của dự án: `php database/migrate-customer-orders.php --apply`. Version `20261113_shipper_application` thêm `application_details` và `application_submitted_at` vào `shipper_profiles` bằng release `ShipperApplicationSchema`, không ghi lại hồ sơ cũ. Hai schema gốc cũng đã có các cột này.

Sử dụng cấu hình Storage riêng tư và khóa mã hóa/ký hiện có. Với Supabase, bucket phải là private và secret chỉ ở server. Với filesystem, thư mục lưu phải ở ngoài thư mục web. Giới hạn PHP `upload_max_filesize` và `post_max_size` cần cho phép file 5 MiB và phần multipart.

## Kiểm thử

- `php tests/shipper_application.php`: validation, quyền file, mã hóa, hết hạn, tuổi, phân quyền Admin và duyệt; chỉ ghi vào bảng PostgreSQL tạm.
- `node tests/shipper_application_browser.cjs`: cần Playwright; kiểm tra avatar, modal desktop/mobile, trường điều kiện, gửi file và thử lại bằng API giả lập.
