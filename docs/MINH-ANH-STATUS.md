# Tiến độ Minh Anh — Thành viên 3

Cập nhật: 11/11/2026. Nhánh làm việc: `MINH-ANH`.

## Kết luận

Đã triển khai phần lớn frontend, backend và API Người 3 trong workspace. **Chưa nghiệm thu hoàn tất toàn bộ trên hosting thật.** Các thay đổi vẫn chưa commit/push tại thời điểm lập báo cáo.

Website cần triển khai: https://somigrabdelivery.online

## Đã triển khai trong code

### Địa lý và địa chỉ

- Giữ lại Geoapify/Leaflet, tìm địa chỉ, chọn ghim và CRUD/default địa chỉ hiện có.
- Bổ sung API địa lý dùng chung Customer/Shipper: `public/api/geo/places.php`.
- Tuyến xe máy trả geometry, khoảng cách và thời gian; không viết lại công thức giá Customer.

### Chat và Storage

- Chat theo đơn, phân trang, trạng thái đã đọc và kiểm tra quyền Customer/Shipper.
- Shipper đã hoàn tất/hoàn hàng có thể đọc lịch sử còn thời hạn lưu.
- File gắn với từng tin nhắn; kiểm tra owner, order, purpose và chống dùng lại file.
- Upload riêng tư, kiểm MIME/dung lượng, SHA-256 và liên kết tải có chữ ký.
- Admin đọc file theo quyền/ticket liên quan và có audit.
- Retention cho chat, ticket, KYC, bằng chứng; cleanup upload chưa dùng và orphan theo cấu hình.

### Ticket và thông báo

- Customer/Shipper tạo ticket theo đơn hoặc tài khoản, xem danh sách và lịch sử.
- Phản hồi ticket hai chiều với Admin; chống gửi trùng.
- Danh sách thông báo, số chưa đọc, đánh dấu đọc/chưa đọc và đánh dấu tất cả đến một mốc ID.
- Consumer sự kiện riêng, chống tạo thông báo trùng, backoff và giới hạn số lần retry.
- API Admin tra cứu và đưa sự kiện FAILED về hàng đợi.

### Ngân hàng, thu nhập và payout

- Tính thu nhập theo policy có hiệu lực, kiểm tra payment, assignment, giao/hoàn và refund.
- Lưu snapshot policy và tính tiền chính xác, không dùng float cho quyết toán.
- Thêm/đổi tài khoản nhận tiền bằng mật khẩu hiện tại và email OTP qua email queue chung.
- OTP có thời hạn, cooldown và giới hạn thử; thông tin ngân hàng được mã hóa.
- Payout theo phương án người dùng đã chọn: **Admin chuyển khoản và đối soát chứng từ**.
- Phân biệt PROCESSING với SUCCEEDED; chống tạo khoản chi trùng.
- Ghi nhận thất bại/retry có chứng từ xác nhận chưa chuyển thành công.
- Nguồn USD dùng tỷ giá có chứng từ do Admin ghi nhận; lưu nguồn/tham chiếu và làm tròn lên khi chuyển sang VND.
- Xem ngân hàng của đúng khoản payout và đối soát đều có audit.

### Frontend

- Customer/Shipper có giao diện chat, tệp đính kèm, ticket, phản hồi và thông báo nối API.
- Shipper có giao diện thu nhập và tài khoản ngân hàng/OTP.
- Admin có giao diện đối soát, xử lý payout thất bại/retry và ghi tỷ giá.
- Admin có giao diện phản hồi ticket và quản lý sự kiện lỗi Người 3.

### Vận hành và tài liệu

- Migration: `20261111_person3` và `20261111_person3_complete`.
- Worker Người 3 và scheduler chạy email/Người 3 trong cùng container khi được bật.
- Cấu hình Docker/Supervisor để worker dùng cùng private volume với web.
- Lệnh kiểm tra cấu hình: `php bin/person3-check.php`.
- Hướng dẫn API: [PERSON3-API.md](PERSON3-API.md).
- OpenAPI 3.1: [person3-openapi.json](person3-openapi.json).

## Kết quả kiểm thử đã ghi nhận

| Nhóm | Kết quả |
|---|---:|
| Backend Người 3 | 112 kiểm tra đạt |
| HTTP guard/API chưa đăng nhập | 24 kiểm tra đạt |
| Storage multipart thật tại local | 23 kiểm tra đạt |
| Giao diện Chrome sau bổ sung màn hình | 23 kiểm tra đạt |
| Hồi quy Người 1 | 74 kiểm tra đạt |
| Hồi quy Admin | 37 kiểm tra đạt |

Backend dùng TEMP tables cô lập, không ghi fixture vào schema nghiệp vụ. Storage multipart dùng thư mục tạm riêng. Browser dùng API fixture cục bộ. **Các kết quả trên không chứng minh email, chuyển tiền hoặc toàn bộ luồng hosting thật đã được nghiệm thu.**

Các thay đổi vận hành Docker/Supervisor mới chưa được xác minh bằng một lần build/deploy container thực tế trong phiên làm việc này.

## Hiện trạng hosting đã kiểm tra

- Trang chủ `https://somigrabdelivery.online` trả HTTP 200.
- Các endpoint Người 3 mới được kiểm tra vẫn trả HTTP 404:
  - `/public/api/interactions.php?resource=notifications`
  - `/public/api/shipper/bank-account.php`
  - `/public/api/admin/payouts.php`
- Vì vậy, hosting chưa phục vụ bản API mới tại thời điểm kiểm tra.
- Máy local có cấu hình DB và Resend; chưa có cấu hình Storage root, Storage signing key, payout mode hoặc Render API key trong môi trường được kiểm tra. Không suy ra cấu hình hosting từ cấu hình local.
- Có cửa sổ Render Dashboard trên máy, nhưng Computer Use bị buộc dừng do không xác định chắc chắn URL trình duyệt để kiểm tra chính sách. Chưa thực hiện thay đổi trên Render.

## Việc còn lại để đánh dấu hoàn tất

- [ ] Rà soát diff cuối cùng và kiểm thử cấu hình Docker/Supervisor mới.
- [ ] Commit/push phần thay đổi Người 3 lên `MINH-ANH`.
- [ ] Xác định đúng service và nhánh deploy trên Render; tích hợp source theo quy trình của nhóm.
- [ ] Áp dụng migration trên database hosting bằng runner chuẩn.
- [ ] Cấu hình private Storage bền vững và secret ký liên kết tải.
- [ ] Bật payout mode manual và worker; xác nhận worker thực sự chạy định kỳ.
- [ ] Nghiệm thu OTP ngân hàng đến email test, bao gồm mã sai, hết hạn và gửi lại.
- [ ] Nghiệm thu upload/download, quyền truy cập và retention trên hosting.
- [ ] Nghiệm thu map/địa chỉ trên điện thoại, domain key và lỗi mạng/quota.
- [ ] Tích hợp đầu cuối với assignment, bằng chứng và sự kiện giao/hoàn của Thảo Nhi — Người 2.
- [ ] Nghiệm thu một khoản Admin chuyển tiền và đối soát thực tế; chỉ đánh dấu thành công khi có chứng từ.

## Thông tin còn cần

- Email test nhận OTP và tài khoản test phù hợp.
- Quyền truy cập Render có thể sử dụng để kiểm tra/cấu hình đúng service.
- Cấu hình Storage bền vững được nhóm chọn cho hosting.

Không ghi mật khẩu, OTP, API key hoặc thông tin ngân hàng thật vào báo cáo, Git hay tài liệu công khai.

## Lệnh triển khai tham khảo

Chỉ chạy trong đúng môi trường đã cấu hình và được chọn để triển khai:

```bash
php database/migrate-customer-orders.php --apply
php bin/person3-check.php
php bin/person3-worker.php --limit=20
```

Tùy chọn Docker:

```dotenv
SOMI_PAYOUT_MODE=manual
SOMI_STORAGE_ROOT=/var/lib/somi-files
SOMI_STORAGE_SIGNING_KEY=<secret-ngau-nhien-toi-thieu-32-ky-tu>
SOMI_RUN_WORKERS=1
SOMI_MIGRATE_ON_START=1
SOMI_STORAGE_PRUNE_ORPHANS=0
```

Storage cần volume bền vững ở đúng mount path. Không bật cleanup orphan trên thư mục dùng chung với ứng dụng khác. Không thay khóa mã hóa hiện có khi triển khai vì còn dữ liệu đã mã hóa cần đọc.
