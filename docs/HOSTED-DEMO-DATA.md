# Dữ liệu DEMO hosting — 13/11/2026

Mục đích: chụp giao diện và thực hành luồng Customer/Shipper/Admin trên website đã triển khai. Đây là dữ liệu giả lập, không chứng minh KYC, chuyển tiền, giao nhận, GPS hay email thực tế.

## Bộ dữ liệu

8 tài khoản CUSTOMER/SHIPPER riêng với email thuộc miền không nhận thư `demo.somi.invalid`; không tạo Admin mới, không đổi tài khoản hiện có. 33 đơn `DEMO-*`: 20 giao thành công, 3 hoàn hàng, 4 hủy, 2 chờ thanh toán, 1 đã phân công, 1 đã lấy, 1 đang giao và 1 đang hoàn. Lịch sử phân bổ nhiều ngày để biểu đồ, bộ lọc và thu nhập có dữ liệu. Có địa chỉ công cộng TP.HCM, ảnh KYC/bằng chứng có watermark minh họa trong private Supabase Storage, đánh giá, thông báo, chat/ticket và các bản ghi tài chính giả lập.

- Customer Mai và Customer Alex: xem tổng quan, danh sách/chi tiết đơn, thanh toán DEMO, chat/ticket; Alex dùng tiếng Anh, các đơn seed vẫn VND.
- Shipper An: có lịch sử/thu nhập, chưa vướng đơn đang chạy; dùng cho nhận đơn mới.
- Bình/Chi/Dũng/Hạnh: đang giao/đang hoàn/đã nhận/đã lấy, dùng cho chụp từng trạng thái. Đơn của Chi có ảnh hoàn và Shipper đã bàn giao, chờ Customer xác nhận.
- Hồ sơ Linh: cần bổ sung KYC để xem luồng Admin duyệt tài liệu.
- Admin hiện có: xem tổng quan có nhãn số liệu DEMO, tài khoản, đơn, thanh toán, refund, earning/payout và ticket/bằng chứng.

## Rào chắn

Chỉ danh tính miền DEMO được giả lập thanh toán PAID; giao dịch ghi provider=DEMO và audit DEMO_PAYMENT_SIMULATED, không gọi SePay. Matching/accept kiểm tra miền để không ghép tài khoản DEMO với đơn/người giao thật. Email/OTP không được đưa vào queue của nhà cung cấp cho danh tính DEMO. Tài khoản DEMO không được đổi ngân hàng thật hoặc tạo reservation chuyển tiền mới. Chứng từ payout seed ghi rõ không có chuyển tiền; Admin bị chặn xem đích chuyển khoản/đối soát/retry chuyển tiền cho payout DEMO. Refund DEMO chỉ vào PROCESSING/DEMO_NO_MONEY_MOVED, không gọi provider. Không dùng tài khoản DEMO để nghiệm thu inbox, thanh toán hoặc định danh thật.

Dữ liệu được nhập trực tiếp dưới nhãn fixture; các mốc lịch sử, earning PAID/payout SUCCEEDED/refund SUCCEEDED là mô phỏng cho màn hình. Sau nhập, các thao tác nhận/lấy/giao/hoàn mới đi qua API/service thật, vẫn có các rào chắn DEMO. Thống kê Admin có thể gồm DEMO nên không dùng để đối soát tiền thật.

## Chạy

Triển khai guard DEMO và migrations trước khi seed. Công cụ CLI mặc định chỉ dry-run:

```powershell
php bin/seed-hosted-demo.php
php bin/seed-hosted-demo.php --apply --target-url=https://somigrabdelivery.online --batch=20261113
php bin/seed-hosted-demo.php --apply --target-url=https://somigrabdelivery.online --batch=20261113 --fresh-offers
```

Import chính chạy một transaction, advisory lock và audit theo batch, không tạo trùng khi chạy lại. Ảnh chỉ tải từ resources/demo, tạo key ngẫu nhiên và hash/dung lượng từ bytes thật; lỗi DB rollback sẽ dọn các object mới tạo. Không dùng ảnh giấy tờ của người thật. Script generate-demo-assets.cjs tạo lại ảnh minh họa khi cần.

Credentials/manifest/object list được lưu trong tests/.runtime/hosted-demo-20261113/, bị Git bỏ qua; không in mật khẩu ra log hoặc commit vào repo. Mật khẩu ngẫu nhiên chỉ dùng cho tài khoản DEMO, không phải mật khẩu Admin hiện có. Khi đổi/reset mật khẩu demo phải lưu lại credential riêng; email DEMO không nhận thư reset.

`--fresh-offers` tạo 3 đơn mới, bật availability và tọa độ demo ở Quận 1 cho An; TTL offer vẫn giữ giới hạn thật (60 giây). Nhận đơn ngay sau khi làm mới. Nếu An đang có assignment ACTIVE, công cụ trả lại ID đó, không hủy/reset. Hết lượt tìm thì đơn DEMO được tự hủy/yêu cầu hoàn giả lập theo policy. Không kéo dài TTL hoặc sửa trạng thái PAID để giả nghiệm thu SePay. Khi trình diễn xong, tắt nhận đơn ở tài khoản DEMO.

Trên browser có thể cấp vị trí giả lập tại 10.7769,106.7009 cho tài khoản DEMO để chụp bản đồ; phải ghi là GPS mô phỏng. Browser kiểm thử lưu ảnh vào tests/.runtime, không lưu cookie/password/session vào repo.
