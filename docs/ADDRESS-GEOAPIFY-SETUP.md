# SOMI — Leaflet + Geoapify và Supabase

Cập nhật 09/11/2026: theo yêu cầu mới, Geoapify thay Google trong autocomplete, geocoding và routing; Leaflet hiển thị Geoapify tiles. Không cần bật billing Google hoặc điền key Google. Dữ liệu địa chỉ Supabase, API CRUD, phân quyền và snapshot đơn cũ giữ nguyên.

## Điền .env

```env
GEOAPIFY_API_KEY=""
GEOAPIFY_BROWSER_API_KEY=""
```

Đăng ký tại [Geoapify MyProjects](https://myprojects.geoapify.com/), tạo project và lấy API key. Nên tạo hai key cùng project: key backend cho autocomplete/geocoding/routing, key browser cho Map Tiles. Giới hạn key backend theo IP egress nếu cố định; key browser theo origin/referrer của localhost và domain triển khai. Không đặt giới hạn browser cho key dùng từ PHP. Key browser xuất hiện trong request tiles theo thiết kế; không xuất key backend ra HTML. Không dùng fallback từ key browser sang key backend.

File .env local đã được bổ sung hai dòng trống, không ghi đè bí mật đã có. Các dòng key Google cũ trong .env local nếu có không còn được luồng hiện tại sử dụng; có thể thu hồi trên Google Cloud nếu không còn ứng dụng khác dùng. Mẫu .env.example chỉ còn cấu hình Geoapify.

Gói Free hiện có 3.000 credits/ngày dùng chung dịch vụ và giới hạn 5 requests/giây theo [bảng giá](https://www.geoapify.com/pricing/). Credits không phải số đơn hàng. Autocomplete debounce 350 ms, chỉ gọi khi có ít nhất 3 ký tự; chọn kết quả không gọi thêm Place Details. Mở/kéo/zoom bản đồ tải thêm tiles; theo dõi usage và quota project. Chạy thử các địa chỉ thật tại Việt Nam để đánh giá độ đầy đủ dữ liệu.

## Luồng đã tích hợp

- Sổ địa chỉ và hai điểm lấy/giao: autocomplete Geoapify, tự điền địa chỉ và tọa độ; sửa text xóa vị trí cũ. Bản đồ Leaflet cho phép chọn ghim, reverse geocoding giữ đúng tọa độ ghim thay vì đổi sang tâm phố gần nhất. Không có browser key thì tìm địa chỉ vẫn dùng được.
- Trang tracking: hiển thị tuyến xe máy từ tọa độ đơn; không giả lập vị trí shipper. Module Shipper cần gửi GPS thật trước khi thêm marker trực tiếp. watchPosition là API trình duyệt, không cần Google/Geoapify Geolocation API; cần HTTPS, quyền vị trí và trang hoạt động; cập nhật chạy nền không được bảo đảm.
- Báo giá: backend xác minh hai điểm thuộc TP.HCM bằng reverse geocoding, gọi routing mode motorcycle; giữ giới hạn tuyến dưới 40 km và công thức tiền hiện tại. Không dùng đường chim bay hoặc khoảng cách frontend để tính tiền. Snapshot cũ không thay đổi.
- Attribution Geoapify/OpenStreetMap nằm trên bản đồ; gợi ý có link Powered by Geoapify. Không bỏ nguồn khi sử dụng gói Free.

API public/api/customer/places.php vẫn dùng auth/permission/same-origin và giới hạn 60 requests/phút/customer. POST JSON action=autocomplete hoặc geocode với input và language=vi/en trả items gồm text và address. action=reverse nhận latitude/longitude trả address. action=route nhận pickup/delivery gồm latitude/longitude trả route GeoJSON để vẽ; endpoint này không quyết định giá. action=details của adapter Google cũ được thay bởi address ngay trong items; caller hiện tại đã cập nhật. session_token nếu được caller cũ gửi sẽ bị bỏ qua, không có Google billing session.

## Supabase và thời tiết

Giữ SOMI_DB_HOST=aws-0-ap-south-1.pooler.supabase.com, port=5432, database=postgres, user=postgres.uxkvzimseqgaodddbtjj và sslmode=require. Điền mật khẩu riêng vào SOMI_DB_PASSWORD. Không đưa vào JavaScript hoặc Git.

Báo giá tích hợp Open-Meteo qua OpenMeteoWeatherService. Môi trường đồ án cấu hình OPEN_METEO_MODE="free": gọi https://api.open-meteo.com/v1/forecast, không cần đăng ký hoặc API key. Giữ OPEN_METEO_API_KEY="". Gói Free chỉ dành cho mục đích phi thương mại theo [Open-Meteo](https://open-meteo.com/en/docs); nếu triển khai thương mại, đặt OPEN_METEO_MODE="commercial" và điền OPEN_METEO_API_KEY để dùng customer-api.open-meteo.com. Không tự chuyển từ commercial sang free khi key lỗi.

Dữ liệu current.weather_code tại tọa độ pickup được kiểm tra timestamp (không cũ quá 30 phút, không vượt tương lai quá 60 giây). Mã WMO dùng policy bình thường/mưa nhẹ/mưa; thời tiết nguy hiểm trả WEATHER_SERVICE_PAUSED. Thiếu dữ liệu hoặc lỗi mạng/quota thì báo PRICING_CONTEXT_UNAVAILABLE; không giả lập thời tiết tốt. Tổng timeout tuyến đường + thời tiết vẫn giới hạn 10 giây. Giao diện báo giá hiển thị điều kiện thời tiết kèm nguồn Open-Meteo.

Endpoint POST public/api/customer/weather.php nhận JSON latitude/longitude, dùng auth Customer, quyền order.create, kiểm tra same-origin, giới hạn 20 lần/phút/customer, trả weather (điều kiện, mã WMO, thời điểm và nguồn). Không giữ transaction khi gọi API. Báo giá gọi service trực tiếp, không tin dữ liệu thời tiết từ frontend.

Kiểm tra thời tiết thật tại tọa độ công khai TP.HCM: php bin/weather-check.php. Lệnh không cần mật khẩu Supabase hoặc key Geoapify; không ghi database. Kiểm thử tự động: php tests/open_meteo.php.

Chạy local: powershell -ExecutionPolicy Bypass -File scripts/serve.ps1. Kiểm tra cấu hình: php bin/address-check.php (chỉ in OK/MISSING và đọc schema). PHP cần pdo_pgsql, curl, bcmath, mbstring. Kết nối Supabase thật cần mật khẩu; gọi Geoapify thật cần key. Chưa có các giá trị này thì chỉ chạy được fixture tests.

## Kiểm tra

php tests/geoapify_addresses.php; php tests/pricing_provider.php; node tests/geoapify_frontend.cjs; node tests/geoapify_maps.cjs; php tests/environment.php. Kiểm thử key/quota/network lỗi không làm lộ provider response hoặc secret.

[Autocomplete](https://apidocs.geoapify.com/docs/geocoding/address-autocomplete/), [Reverse geocoding](https://apidocs.geoapify.com/docs/geocoding/reverse-geocoding/), [Routing](https://apidocs.geoapify.com/docs/routing/), [Tiles](https://apidocs.geoapify.com/docs/maps/map-tiles/).

Kiểm tra tại máy ngày 09/11/2026: PDO Supabase kết nối thành công, bảng somi.customer_addresses có sẵn (truy vấn chỉ đọc). Hai key Geoapify chưa được điền, nên chưa kiểm tra trực tiếp autocomplete/tiles/routing. Fixture tests backend, autocomplete và map helper đều qua; chưa xác minh giao diện bằng trình duyệt với key thật.
