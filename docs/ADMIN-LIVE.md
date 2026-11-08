# Admin trên nhánh MINH-ANH

Cập nhật 09/11/2026. Admin dùng PHP + JavaScript hiện có và cùng PostgreSQL Supabase với Customer/Shipper. Không cần chạy migration mới cho thay đổi này. Mật khẩu/key chỉ đọc từ môi trường server hoặc .env đã bị Git bỏ qua.

## Mở và đăng nhập

Chạy powershell -ExecutionPolicy Bypass -File scripts/start-local.ps1 để mở server nền, rồi mở http://127.0.0.1:3000/admin hoặc http://127.0.0.1:3000/index.php?page=admin. Nếu muốn xem log trong terminal và dừng bằng Ctrl+C, dùng scripts/serve.ps1. Không mở trực tiếp các fragment trong app/Views/admin. Cần tài khoản ACTIVE có role ADMIN được cấp sẵn trong database. Username postgres.<project-ref> là tài khoản PostgreSQL, không phải tài khoản đăng nhập ứng dụng. Không tạo Admin mặc định và không có mật khẩu mặc định.

## Màn hình và nghiệp vụ

| Màn hình | Dữ liệu/thao tác |
|---|---|
| Tổng quan | Số tài khoản, đơn, đơn đang xử lý, KYC chờ duyệt, ticket mở, job lỗi; biểu đồ 7 ngày theo giờ Việt Nam; tiền đã thu tách VND/USD, trước hoàn tiền |
| Tài khoản / Khách hàng / Shipper | Tìm theo tên/email/điện thoại, lọc trạng thái/role/KYC, phân trang 50 bản ghi, xem hồ sơ, khóa/mở có lý do và audit |
| KYC | Xem metadata giấy tờ, phương tiện, điểm tin cậy; duyệt/từ chối/yêu cầu bổ sung qua AdminService hiện có |
| Điểm tin cậy | Admin khôi phục điểm, kiểm tra policy/ngưỡng, lưu score event và audit; không tự đặt shipper online |
| Đơn hàng | Lọc trạng thái, phân trang, xem snapshot và lịch sử; hủy trước pickup theo phí đã chốt; kết thúc assignment, hủy offer và phát sự kiện refund khi đã thanh toán |
| Thanh toán / Hoàn tiền | Đọc dữ liệu giao dịch, yêu cầu worker đối soát, xác nhận bằng chứng chuyển khoản/hoàn tiền qua các service hiện có; không tự chuyển tiền thành công từ UI |
| Ticket | NEW → IN_PROGRESS → RESOLVED → CLOSED; bắt buộc ghi chú, lưu resolution, event, audit và notification cho người gửi; đóng ticket cập nhật lịch xóa attachment sau 3 tháng |
| Chat của ticket | Chỉ đọc khi có ticket cùng đơn và quyền chat.read_for_support; mỗi lần đọc ghi audit; tối đa 100 tin còn trong hạn lưu trữ |
| Đánh giá | Đọc đánh giá thực, lọc số sao và phân trang; không sửa/xóa đánh giá vì schema và policy hiện tại chưa có luồng moderation |
| Loại kiện hàng | Tạo/sửa tên VI/EN, mô tả, allowed/restricted/prohibited và active; dùng parcel.manage, có audit |
| Giá & chính sách | Đọc bảng giá/khung giờ/thời tiết, tạo hoặc clone bản nháp rồi kích hoạt riêng; cấu hình thu nhập/điểm qua AdminPolicyService; không dùng JSON kỹ thuật làm biểu mẫu người dùng |
| Quốc tịch | Duyệt/từ chối yêu cầu, bắt buộc ghi chú, dùng AdminService và audit hiện có |
| Thông báo | Chỉ thông báo của Admin đang đăng nhập, đánh dấu đã đọc bằng API có owner check |
| Audit / Email & tác vụ | Xem nhật ký và chi tiết before/after, lịch sử email không chứa nội dung mã hóa; retry chỉ job FAILED |

Giao diện hỗ trợ VI/EN, dùng language picker chung, sidebar luôn dọc bên trái theo giao diện gốc; thu gọn chiều rộng ở màn hình nhỏ và cuộn theo chiều dọc, đăng xuất nằm cuối sidebar, bảng cuộn ngang trong vùng riêng. Các thao tác thay đổi có biểu mẫu xác nhận và chống gửi lặp khi đang xử lý. CSV chỉ xuất trang hiện tại và chặn công thức bắt đầu bằng =,+,-,@,tab/CR.

## API bổ sung

- GET public/api/admin/console.php?section=overview|accounts|account|tickets|chat|reviews|notifications. account nhận id; chat nhận ticket_id; danh sách mới hỗ trợ before_id và next_before_id. accounts hỗ trợ role, q, status, verification; tickets hỗ trợ q/status; reviews hỗ trợ rating.
- POST public/api/admin/actions.php. action TICKET_STATUS (ticket_id/status/note), MARK_READ (notification_id), CANCEL_ORDER (order_id/reason), RESTORE_SCORE (shipper_id/score/reason).
- Các API accounts/review-shipper/orders/parcel-categories/policies/nationality-requests/payments/refunds/bank-reconciliation/refund-reconciliation/jobs/email-logs/audit-logs được dùng lại.

Tất cả API xác thực session, role ADMIN và permission trong database; POST kiểm tra same-origin. Mutation, history và audit chạy cùng transaction. Không gửi password hash, ciphertext hoặc API key xuống Admin. Khi request timeout, người dùng nên làm mới để xác nhận trạng thái trước khi thử lại.

Các URL cũ view=backend chuyển tới chính sách, view=disputes chuyển tới ticket hỗ trợ vì database hiện dùng support_tickets làm workflow thống nhất. Các template Admin mẫu cũ được giữ để tham chiếu; router hiện render app/Views/admin/live.php, admin.js bỏ qua preview handler khi thấy console thật.

## Ranh giới tích hợp

Theo yêu cầu mới của người dùng, phần UI/API quản trị ticket được nối tại Admin; Customer/Shipper vẫn sở hữu tạo ticket/chat và gửi dữ liệu nghiệp vụ theo module của nhóm. Lần này không tạo lại module matching/GPS hoặc payout. Giữ Leaflet/Geoapify và Open-Meteo đã cấu hình.

Không hiển thị link tải ảnh KYC/attachment khi chưa có adapter object storage/signed URL. UI KYC hiện hiển thị metadata định danh và phương tiện từ DB; không tự công khai số định danh đã mã hóa hoặc object_key. Không cho hủy đơn đã pickup: các quyết định sau pickup cần quy trình xử lý từng trường hợp, không có hành động hủy tắt trong giao diện.

Figma Make được cung cấp chỉ trả về source Customer, không có màn Admin trong danh sách source. Admin kế thừa bố cục, màu, sidebar, font/icon và language picker sẵn có của nhánh; không tuyên bố đã đối chiếu pixel với màn Admin trong Figma.

## Kiểm tra đã chạy

- php tests/admin_console.php: 37 checks với bảng TEMP và search_path pg_temp, pg_catalog; không sửa bảng ứng dụng, gửi email hoặc chuyển tiền thật.
- node tests/admin_browser.cjs: 145 checks bằng browser fixtures, 15 màn hình × desktop/tablet/mobile (1440/768/375), cộng KYC, ticket transition, xác nhận hủy, lỗi permission, clone policy; không gọi mutation thật.
- node tests/admin_http.cjs: 31 kiểm tra HTTP, gồm mở template trực tiếp, /admin và /admin/ không lặp chuyển hướng, CSS tải được, anonymous/method/origin, redirect đăng nhập, .env và đường dẫn tests không truy cập được qua production router.
- php tests/admin_live_smoke.php: 6 nhóm truy vấn Admin đọc thành công trên Supabase thật, toàn bộ transaction rollback và không in dữ liệu cá nhân.
- Kiểm tra PHP/JS syntax và git diff --check. Ảnh fixture dashboard/shippers được render và xem tại tests/.runtime/admin-live; thư mục runtime bị Git bỏ qua.

Chạy PHP trên máy này bằng C:/xampp/php/php.exe -d extension=pdo_pgsql khi php chưa có trong PATH. Browser tests tự chạy Chrome/Edge headless bằng profile test riêng và server localhost, không dùng profile người dùng.

Sửa đường mở Admin: /admin và /admin/ chuyển về front controller index.php trước khi kiểm tra phiên, tránh redirect ?page=auth bị ép lại Admin. Các template cũ có guard chuyển qua front controller khi chạy độc lập, không còn count() biến chưa khởi tạo. Server nền có log/PID trong tests/.runtime và không khởi động lại nếu cổng đã có dịch vụ.
