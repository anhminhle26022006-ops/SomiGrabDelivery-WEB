# Hiệu năng và kiểm thử giao diện

Cập nhật 2026-11-07.

## Điểm nghẽn chính

Đo từ máy dev tới Supabase hiện tại (chỉ connect + `SELECT 1`): **mở kết nối ~775 ms, mỗi round-trip ~300 ms**. Vì vậy số round-trip DB quyết định tốc độ, quan trọng hơn CPU.

Trước tối ưu, một API cần đăng nhập (ví dụ `auth/profile.php`) tốn: kết nối mới + `SET search_path` + kiểm tra session + kiểm tra quyền + truy vấn chính. Thêm vào đó, PDO pgsql với prepared statement thật tốn 2 round-trip cho mỗi truy vấn (PREPARE + EXECUTE). Tổng cộng ước tính ~3,5 giây trên Supabase. Sau tối ưu chỉ còn 2 round-trip (~0,6 giây).

## Đã thay đổi

| Phần | Thay đổi |
|---|---|
| `app/Config/database.php` | Persistent mặc định tắt; chỉ bật sau khi tính ngân sách kết nối. `search_path` gửi trong gói khởi tạo kết nối thay cho lệnh `SET`. Dùng `PGSQL_ATTR_DISABLE_PREPARES`: 1 round-trip mỗi truy vấn, tham số vẫn bind phía server (không phải emulate). |
| `app/Services/SessionGuard.php`, `public/api/_person1.php` | Gộp kiểm tra quyền vào truy vấn session. Nhả khoá file session ngay sau khi xác thực, để các request song song của cùng một người dùng không phải xếp hàng chờ nhau. |
| `public/api/auth/profile.php` | Dùng lại danh sách role đã tải khi kiểm tra session. |
| `index.php` (`somi_asset()`) | URL asset tự gắn `?v=<mtime>`, không cần sửa version bằng tay. Thêm favicon inline để bỏ request 404. |
| `.htaccess` | Asset có `?v=` được cache 1 năm (`immutable`), asset không có version cache 10 phút. Nén gzip cho HTML/CSS/JS/JSON/SVG. Chặn thêm `scripts/`, `docker/`, `*.ps1`. |
| `Dockerfile` (cấu hình được nhúng trực tiếp) | Chuyển từ `mod_php` + prefork sang **Apache event MPM + PHP-FPM**, dùng `php.ini-production` (tắt `display_errors`), bật OPcache và bỏ kiểm tra timestamp. |
| `public/assets/css/modules/customer.css` | Sửa lỗi tràn ngang của Customer ở 761–1024px: thanh menu co lại và cuộn ngang bên trong. |

## Biến môi trường mới

| Biến | Mặc định | Ý nghĩa |
|---|---|---|
| `SOMI_DB_PERSISTENT` | `0` | Chỉ giá trị `1` mới bật persistent cho web. |
| `SOMI_DB_SET_SEARCH_PATH` | tắt | Đặt `1` khi dùng pooler bỏ qua startup options (PgBouncer, hoặc Supavisor transaction mode cổng 6543). |
| `SOMI_MAX_WORKERS` | `6` | Giới hạn worker mỗi container; pool `ondemand` thoát sau 10 giây rảnh. Tổng worker của mọi instance, worker nền và công cụ quản trị phải nằm trong ngân sách kết nối DB. |
| `PHP_OPCACHE_VALIDATE_TIMESTAMPS` | `0` | Đặt `1` nếu mount source code vào container lúc dev. |

Khi cần chạy nhiều container: tổng `SOMI_MAX_WORKERS` × số container phải nằm dưới giới hạn kết nối DB. Nếu vượt, chuyển sang Supavisor transaction mode (app đã tương thích nhờ không dùng named prepared statement) và đặt `SOMI_DB_SET_SEARCH_PATH=1`. Session hiện lưu bằng file trong container, nên chạy nhiều container sau load balancer sẽ cần sticky session hoặc một session store dùng chung (Redis).

## Kết quả đo (Docker, 4 CPU, DB test cô lập)

50 người dùng, mỗi người một session riêng, mỗi kịch bản chạy 10 giây (`tests/load_test.cjs`):

| Kịch bản | Trước | Sau |
|---|---|---|
| Trang workspace Customer | 538 req/s, p95 183 ms | 1666–2191 req/s, p95 27–44 ms |
| API profile | 500 req/s, p95 194 ms | 1811–1962 req/s, p95 35–46 ms |
| 4 API song song mỗi người dùng | p95 2367 ms, p99 5190 ms | p95 160 ms, p99 209 ms |
| CSS gửi đi (`app.css`) | 29 KB, không có Cache-Control | 7,4 KB gzip, cache 1 năm |

Có thêm độ trễ DB giả lập 50 ms RTT (20 người dùng); Supabase thật còn trễ hơn, nên mức chênh lệch thực tế lớn hơn:

| Kịch bản | Trước | Sau |
|---|---|---|
| Trang workspace | p50 354 ms, 57 req/s | p50 78 ms, 248 req/s |
| API profile | p50 908 ms, 22 req/s | p50 141 ms, 142 req/s |
| 4 API song song | p50 2980 ms, p99 8802 ms | p50 419 ms, p99 594 ms |

Ở kịch bản đột biến 200 kết nối mở cùng lúc, khoảng 1,5% request bị đóng kết nối keep-alive giữa chừng (`other side closed`). Trình duyệt tự gửi lại các request GET trong trường hợp này. Load test không retry nên vẫn đếm là lỗi.

## Chạy kiểm thử

```powershell
# Terminal 1 (DB test cô lập, xem PERSON1_BACKEND.md)
./scripts/test-person1.ps1 -ServeOnly
# Terminal 2
./scripts/test-person1.ps1          # backend: 34 + 58 + 7 + schema + 13 checks
node tests/ui_e2e.cjs               # giao diện: 615 checks, 3 kích thước màn hình
```

`tests/ui_e2e.cjs` tự mở Chrome/Edge headless và chạy qua 7 trang công khai, 14 view Customer, 7 view Shipper, 12 view Admin ở 1440/768/375px. Mỗi trang được kiểm tra: render đúng trang, không tràn ngang, không có lỗi JS/console/HTTP, không có ảnh hỏng, tải dưới 3 giây. Ngoài ra còn kiểm tra chuyển hướng khi chưa đăng nhập, chặn sai workspace, menu avatar, điều hướng Shipper, bộ lọc/tìm kiếm/modal Admin, chống bấm đăng nhập nhiều lần và đăng nhập thật qua form. Kết quả ghi vào `tests/.runtime/ui-e2e-results.json`.

Load test chỉ chạy trên DB test, không chạy trên Supabase:

```powershell
docker build -t somi-app .
docker run -d -p 127.0.0.1:8091:80 -e SOMI_DB_HOST=... -e SOMI_DB_SSLMODE=disable ... somi-app
$env:SOMI_LOAD_BASE='http://127.0.0.1:8091'; node tests/load_test.cjs optimized
```
