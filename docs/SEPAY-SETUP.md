# Kết nối SePay sandbox / Connect SePay sandbox

SOMI dùng **Cổng thanh toán SePay (Payment Gateway)** cho GATEWAY, CARD và BANK_TRANSFER. Đây là bộ Merchant ID/Secret Key của Cổng thanh toán, không phải API token của SePay Bank API. USD checkout vẫn tạm khóa theo quyết định đã duyệt.

## 1. Lấy thông tin thử nghiệm

Đăng nhập [my.sepay.vn](https://my.sepay.vn). Chọn **Cổng thanh toán → Đăng ký → Bắt đầu ngay → Bắt đầu với Sandbox → Bắt đầu hướng dẫn tích hợp**. Sao chép Merchant ID và Secret Key của sandbox vào nơi lưu bí mật; Secret Key chỉ hiển thị một lần khi tạo. Sandbox tách khỏi production và không cần liên kết ngân hàng thật để thử thanh toán. [Hướng dẫn SePay](https://developer.sepay.vn/vi/cong-thanh-toan/sandbox).

- **Merchant ID:** mã định danh SOMI với nhà cung cấp, dùng trong form checkout và xác thực API.
- **Secret Key:** khóa bí mật của merchant, server dùng ký form và gọi API đối soát. Không gửi vào chat, commit Git hoặc đưa vào JavaScript.
- **IPN Secret:** một khóa độc lập do bên SOMI đặt để xác thực thông báo thanh toán gửi về. Đặt ít nhất 32 ký tự ngẫu nhiên, khác merchant Secret Key.

English: obtain the **Gateway sandbox merchant credentials**, not a Bank API token. The merchant secret signs checkout requests and authenticates reconciliation; the independent IPN secret authenticates incoming notifications. Keep both in server-side secrets configuration.

## 2. Cấu hình môi trường server

Đặt các biến sau trong phần Environment/Secrets của nơi chạy PHP. Khi chạy local, lưu chúng vào `.env` tại thư mục gốc project; PHP web/API/worker và CLI SePay tự nạp file này. Có thể dùng [.env.example](../.env.example) làm mẫu. File `.env` được loại khỏi Git/Docker và chặn truy cập web; biến môi trường đã đặt trên server/terminal được ưu tiên, kể cả giá trị rỗng. Giá trị được đọc nguyên văn, không nội suy `${VARIABLE}`. File [runtime.env.example](runtime.env.example) vẫn là tài liệu tham khảo, không được tự nạp.

```dotenv
SEPAY_ENVIRONMENT=sandbox
SEPAY_MERCHANT_ID=<Merchant ID sandbox>
SEPAY_SECRET_KEY=<Secret Key sandbox>
SEPAY_IPN_SECRET=<khóa IPN riêng ít nhất 32 ký tự>
SOMI_PUBLIC_URL=https://<domain-hoac-tunnel-cua-SOMI>
```

`SOMI_PUBLIC_URL` là URL ứng dụng truy cập được từ Internet, không phải URL SePay. Nếu SOMI chạy trong thư mục con, giữ thư mục đó trong URL, ví dụ `https://example.com/somi`. Không thêm query, fragment hoặc thông tin đăng nhập. Dùng HTTPS công khai để SePay gọi IPN; localhost đơn thuần chưa nhận được IPN. Khởi động lại PHP/Apache và worker sau khi đặt biến. Web server và worker phải dùng cùng cấu hình và database.

Run this **from the same runtime/environment as the application**:

```powershell
php bin/sepay-check.php
```

Lệnh chỉ kiểm tra định dạng cấu hình và PHP extension, không in giá trị khóa, không gọi SePay, không đọc/ghi database, không tạo giao dịch. `configuration_ready:true` chỉ có nghĩa cấu hình đủ để bắt đầu thử; `live_connection_verified:false` chưa xác minh merchant hoặc khả năng SePay gọi về. Nếu CLI chạy được nhưng PHP web vẫn thiếu biến, kiểm tra môi trường của Apache/container thay vì chỉ terminal.

## 3. Đặt IPN trên SePay

Trong trang quản lý SePay, **Cổng thanh toán → Cấu hình → IPN**:

- URL: `<SOMI_PUBLIC_URL>/public/api/payments/callback.php`.
- Auth Type: **SECRET_KEY**.
- Secret: đúng giá trị `SEPAY_IPN_SECRET` đã đặt ở server.

SePay gửi secret qua header `X-Secret-Key`; SOMI trả HTTP 200 với `success:true` sau khi lưu kết quả hợp lệ. Merchant signing secret và IPN secret là hai khóa khác nhau. [Tài liệu IPN chính thức](https://developer.sepay.vn/vi/cong-thanh-toan/IPN).

## 4. Thử luồng trong SOMI

1. Áp dụng migration và cấu hình DB theo [PERSON1-API.md](PERSON1-API.md). Chọn một đơn VND chưa thanh toán. Nếu cần tạo đơn mới, danh mục ALLOWED phải active và nguồn route/weather Người 3 phải hoạt động; không tạo dữ liệu/giá giả để vượt điều kiện.
2. Customer mở đơn → chọn phương thức thanh toán → tạo payment → mở checkout → tiếp tục sang SePay sandbox. Form được ký ở server và gửi đến `https://pay-sandbox.sepay.vn/v1/checkout/init`.
3. Hoàn tất giao dịch bằng công cụ thử nghiệm của SePay sandbox. Browser quay lại SOMI chưa đủ để xác nhận tiền; bấm tải trạng thái mới sau khi IPN được xử lý.
4. Xác nhận payment PAID, đơn SEARCHING_SHIPPER và một OrderReadyForMatching. Gửi lại IPN cùng giao dịch phải trả thành công mà không nhân ledger/history/matching event. Không sửa database bằng tay để giả PAID.
5. Nếu IPN bị gián đoạn, Admin dùng API đối soát và worker gọi SePay order-detail bằng invoice. Chạy `php bin/person1-worker.php --limit=20` trong môi trường đã cấu hình. Worker còn xử lý email đang chờ: kiểm tra queue/người nhận trước khi chạy trên database dùng chung. Tham khảo [API đối soát SePay](https://developer.sepay.vn/vi/cong-thanh-toan/API/don-hang/chi-tiet-don-hang).

Before production, verify the complete sandbox round trip, duplicate notifications and late payments on cancelled orders. Do not switch an in-flight payment between environments. Production uses a separate merchant key set and provider hosts; this guide does not enable real-money processing.

## 5. Kiểm thử source không cần khóa thật

```powershell
php tests/sepay.php
php tests/person1_backend.php
```

`tests/sepay.php` kiểm tra cấu hình, host môi trường, thứ tự ký form, ngôn ngữ và payload/xác thực IPN bằng khóa fixture; không network/database. Bộ Người 1 kiểm tra callback/ledger/order/refund bằng TEMP tables và provider giả. Các test này không chứng minh giao dịch sandbox thực đã thành công.

Refund Gateway hiện cần operator xử lý về phương thức gốc và Admin đối soát chứng từ; đây không phải tích hợp tự động chuyển tiền hoàn. Chi tiết API và quyền: [PERSON1-API.md](PERSON1-API.md).
