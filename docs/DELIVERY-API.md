# Customer Delivery API v1

> Update 06/11/2026: Person 1 payment/email/Admin APIs and live Customer flow are implemented. SePay is selected; USD checkout is disabled. Current contracts, setup, tests and live-provider limitations: [PERSON1-API.md](PERSON1-API.md).


Cập nhật scope/contract: **06/11/2026**, đối chiếu source commit `aae675c`. PHP 8+, PDO PostgreSQL, mbstring, cURL, **BCMath**. API dùng session hiện có, không cần frontend build. UI Customer vẫn dùng demo store. Phân công hiện hành: [BACKEND-SCOPE.md](BACKEND-SCOPE.md), [TEAM-OWNERSHIP.md](../TEAM-OWNERSHIP.md).

**Hiện trạng 06/11/2026:** Customer/order/payment/email/Admin Người 1 đã triển khai và migration đã áp dụng; contract mới tại [PERSON1-API.md](PERSON1-API.md). Policy đã duyệt vẫn giữ VND/USD; USD checkout tạm khóa. SePay sandbox/live, SMS thật và tích hợp Người 2/3 cần nghiệm thu riêng. Source nền commit `aae675c`; không suy ra đã push/deploy hosting.

| Module trong contract | Owner hiện hành |
|---|---|
| Quote/create/list/detail/cancel, snapshot, công thức giá và state/history | Người 1 |
| Payment/callback/đối soát/refund và toàn bộ email | Người 1 |
| Matching/offer/assignment/giao nhận/hoàn/GPS | Người 2 |
| CRUD địa chỉ Customer, Places/Geocoding/Routes, key/quota | Người 3 |
| Chat/ticket, earnings/payout và notification in-app | Người 3 |

Chuyển ownership không đổi endpoint hay tạo API trùng. Người 3 tiếp quản code địa chỉ/Google đã có; Người 1 sử dụng dữ liệu địa lý qua interface để chụp snapshot/tính giá. Owner riêng của Open-Meteo cần nhóm chốt theo BACKEND-SCOPE.md.

**Cấu hình hiện hành:** người dùng đã duyệt **BASE_PLUS_DISTANCE_PLUS_WEIGHT**, áp dụng migration `20261105_distance_weight_pricing` sau release giới hạn 40 km. VND/USD version 3 active. Cơ bản 20.000đ/99 cent gồm 3 km và 3 kg; mỗi km vượt 3 km là 4.000đ/20 cent; mỗi kg vượt 3 kg là 2.500đ/10 cent. Tính theo mét và gram, tổng `(base + extra_distance × per_km + extra_weight × per_kg) × time × weather`, chỉ làm tròn lên một lần ở tổng cuối. Cả hai điểm thuộc TP.HCM, route <40 km, 0 < weight <=30 kg. Không còn khoảng 20–40 km thiếu giá. Mô tả band và giới hạn 20 km bên dưới là contract legacy cho các snapshot cũ, được thay thế đối với đơn mới bởi cập nhật này.

Quote/order snapshot mới lưu base_fee_minor, included_distance_km, included_weight_kg, per_km_minor, per_kg_minor, extra_distance_km, extra_weight_kg, distance_component_minor, weight_component_minor và subtotal_minor. Các component có thể là số thập phân minor-unit; không tự làm tròn từng component. total_charge_minor là số nguyên sau khi nhân hệ số. `orders.distance_fee_minor` là trường legacy BIGINT lưu ceil(base + distance); dùng pricing_snapshot để đối chiếu chi tiết và total_charge_minor để thanh toán. Trọng lượng vượt 30 kg trả 422 WEIGHT_NOT_SUPPORTED trước khi gọi provider; weight 0 hoặc quá 3 decimals trả 422 VALIDATION_ERROR.

Chi tiết hiện hành: [DISTANCE-WEIGHT-PRICING-PROPOSAL.md](DISTANCE-WEIGHT-PRICING-PROPOSAL.md). Migration không sửa policy/snapshot cũ; quote legacy chưa hết hạn vẫn được tạo thành đơn với snapshot đã chốt. Customer demo vẫn dùng tuyến/thời tiết mô phỏng, không thay thế API provider thật.

## Cài đặt

1. Dùng database đã có `database/SOMI_database.sql` và migrations auth hiện có. Không chạy lại schema nền lên database đang sử dụng.
2. Set biến môi trường theo `docs/runtime.env.example`. PHP tự nạp `.env` ở root; xem `.env.example`. Biến môi trường server/process được ưu tiên; file mẫu trong docs không được tự nạp.
3. Chạy `php database/migrate-customer-orders.php --apply`. Runner Người 1 có transaction/advisory lock/ledger, áp dụng customer_orders → approved_pricing → service_range_40km → distance_weight_pricing, thêm quotes/events/idempotency và tham số giá. Không sửa snapshot cũ. Không trộn SQL trực tiếp với runner vì SQL trực tiếp không ghi ledger.
4. Người 1 hoàn thiện API Admin danh mục/giá và cấu hình loại hàng được phép, không seed loại chưa duyệt. Người 3 cấu hình Google key/địa lý; email/payment thuộc Người 1. API Admin đã có theo PERSON1-API.md; nhập danh mục và các số policy chưa chốt bằng quyết định nghiệp vụ, không lấy dữ liệu demo. Tình trạng danh mục/key phải kiểm tra lại tại môi trường chạy.
5. Chạy `php -S 127.0.0.1:3000 index.php`. Docker đã bổ sung BCMath; image cần build lại trước triển khai.

## Quy tắc chung

- Base: `public/api/customer/` tương đối với thư mục cài ứng dụng. Session cookie HttpOnly, SameSite=Lax; fetch cùng origin dùng `credentials: 'same-origin'`.
- Tài khoản phải ACTIVE, session còn hạn và workspace đang chọn là `customer`. Role CUSTOMER và permission của từng endpoint được đọc từ DB; có profile Customer. Không nhận `customer_id` từ client.
- Request ghi cần Origin hợp lệ hoặc `Sec-Fetch-Site: same-origin`; cross-site luôn bị chặn. Client HTTP ngoài browser phải gửi Origin hợp lệ và cookie. POST/PUT JSON object UTF-8, tối đa 64 KiB, sâu tối đa 32 cấp. DELETE địa chỉ không cần body. HTTPS dùng theo cấu hình origin hiện có.
- Không hỗ trợ PATCH. PUT địa chỉ thay toàn bộ các trường địa chỉ; trường tùy chọn bỏ qua được đặt null/false. Truyền trường ngoài contract bị 422.
- Response ID và tiền có hậu tố `_id`/`_minor` là chuỗi (tránh mất chính xác JavaScript); decimal tọa độ, km, kg, multipliers cũng là chuỗi. Tiền VND: đồng, USD: cent. Timestamps ISO 8601 hoặc định dạng PostgreSQL có timezone. Không gửi float cho tiền.
- Lỗi: `{"code":"ORDER_NOT_FOUND","error":"Order not found."}`. 400 JSON sai; 401 chưa xác thực/session hết hạn; 403 role/permission/origin; 404 không tồn tại hoặc không thuộc người dùng; 409 xung đột trạng thái/idempotency/quote hết hạn; 413 body quá lớn; 415 sai Content-Type; 422 dữ liệu không hợp lệ; 503 thiếu migration/cấu hình/provider hoặc DB không sẵn sàng. Lỗi cross-site từ bootstrap cũ có thể chỉ có trường `error`.

## Endpoints

| Method và URL | Permission | Request / response |
|---|---|---|
| GET `addresses.php` | address.manage_own | `{items: [...]}`; chỉ địa chỉ chưa archive của mình |
| GET `addresses.php?id=123` | address.manage_own | `{address: {...}}` |
| POST `addresses.php` | address.manage_own | Body Address; 201 `{address: {...}}` |
| PUT `addresses.php?id=123` | address.manage_own | Body Address; 200 `{address: {...}}` |
| DELETE `addresses.php?id=123` | address.manage_own | 200 `{archived: true}`; lặp lại trả 404 |
| GET `parcel-categories.php` | order.create | `{items: [...]}` gồm active categories và restriction_type |
| POST `quotes.php` | order.create | Body QuoteRequest; 201 `{quote: {quote_id, expires_at, snapshot}}` |
| POST `orders.php` | order.create | Body `{quote_id}` và header `Idempotency-Key`; 201 `{created:true,order}` hoặc replay 200 `{created:false,order}` |
| GET `orders.php` | order.read_own | `{items: [...], next_before_id: string\|null}` |
| GET `orders.php?id=123` | order.read_own | `{order: {..., pricing_snapshot, status_history: [...]}}` |
| POST `cancel-order.php?id=123` | order.cancel_before_assignment | Body `{reason: "..."}`; 200 `{order: {...}}` |

Address:

```json
{
  "label": "Nhà", "country_code": "VN",
  "province": "TP.HCM", "district": "", "ward": "",
  "address_detail": "Địa chỉ cụ thể",
  "latitude": "10.7769000", "longitude": "106.7009000", "is_default": true
}
```

`address_detail` bắt buộc, tối đa 255 ký tự; label 60; province/district/ward 120; latitude [-90,90], longitude [-180,180], tối đa 7 chữ số thập phân. Chỉ country_code VN. is_default là boolean thật. Ghi địa chỉ khóa profile Customer để giữ tối đa một địa chỉ mặc định khi có request đồng thời. DELETE là archive; không sửa snapshot trên đơn cũ.

QuoteRequest (ID dưới đây minh họa, phải lấy ID thật từ API; không có category seed tự động):

```json
{
  "pickup": {
    "saved_address_id": "123",
    "contact_name": "Người gửi", "contact_phone": "0900000001"
  },
  "delivery": {
    "address": {
      "country_code": "VN", "address_detail": "Địa chỉ nhận",
      "latitude": "10.7800000", "longitude": "106.7100000"
    },
    "contact_name": "Người nhận", "contact_phone": "0900000002"
  },
  "parcel": {
    "category_id": "1", "weight_kg": "1.250",
    "description": "Mô tả kiện hàng",
    "length_cm": "20.00", "width_cm": "15.00", "height_cm": "10.00"
  },
  "currency_code": "VND",
  "customer_note": "Ghi chú"
}
```

Mỗi point chọn **một** `saved_address_id` thuộc mình hoặc `address` inline; không truyền cả hai. Contact bắt buộc, tên tối đa 150; số điện thoại 8–15 chữ số, cho phép dấu + đầu. category phải active và ALLOWED; RESTRICTED/PROHIBITED bị từ chối. Với policy hiện hành, khối lượng >0, tối đa 30.000 kg/3 decimals; kích thước tùy chọn >0, tối đa 9999999.99 cm/2 decimals. Description và customer_note tối đa 2000 ký tự. Client có thể chọn `currency_code` là `VND` hoặc `USD` cho mỗi báo giá; bỏ trống trường này thì mặc định theo `users.app_language` (vi → VND, en → USD) để tương thích client cũ. Ngôn ngữ email vẫn theo hồ sơ, độc lập tiền tệ. Quãng đường, thời tiết, giá và status do client gửi bị từ chối. Server chọn bảng giá độc lập của tiền tệ đã chọn; không quy đổi ngoại tệ. Đơn đã tạo giữ nguyên snapshot và tiền tệ. Migration `20261111_customer_currency_choice` bỏ ràng buộc ngôn ngữ/tiền tệ cũ; cần áp dụng trước khi triển khai frontend/backend mới. Thanh toán USD qua SePay vẫn tạm khóa.

## Báo giá hiện hành — Người 1

Rule **BASE_PLUS_DISTANCE_PLUS_WEIGHT**:

`total_charge_minor = ceil((base_fee_minor + max(0, distance_km - included_distance_km) * per_km_minor + max(0, weight_kg - included_weight_kg) * per_kg_minor) * time_multiplier * weather_multiplier)`.

| Tham số đã duyệt | VND | USD |
|---|---:|---:|
| Cơ bản, gồm 3 km và 3 kg | 20.000 đồng | 99 cent |
| Mỗi km vượt mức | 4.000 đồng | 20 cent |
| Mỗi kg vượt mức | 2.500 đồng | 10 cent |
| Phí hoàn tách riêng | 10.000 đồng | 50 cent |

Tính theo mét/gram thực tế, không làm tròn từng component; BCMath và ceil tổng cuối tới đồng/cent. Policy phải active/effective đúng currency, time/weather phải có cấu hình không trùng; không fallback multiplier=1 khi thiếu. Route <40 km, 0 < weight <=30 kg, cả hai điểm ở TP.HCM. Snapshot lưu đủ base/rate/included/extra km/kg/components/policy/version; không sửa giá đơn cũ. Phí hoàn tách riêng, Customer hủy trước assignment phí 0. Chi tiết: [cấu hình đã duyệt](DISTANCE-WEIGHT-PRICING-PROPOSAL.md).

## Tương thích policy band cũ — không dùng cho đơn mới

Người dùng đã duyệt rule **DISTANCE_BAND_X_TIME_X_WEATHER** và hai bảng giá độc lập. Release tạo policy version mới, giữ nguyên policy/snapshot đơn cũ:

`total_charge_minor = ceil(distance_band.amount_minor × time_band.multiplier × weather_band.multiplier)`.

- `amount_minor` là **giá cố định cho khoảng km**, không phải giá mỗi km. Chỉ dùng rule này khi nhóm đã duyệt cách hiểu đó; công thức khác cần rule riêng trước khi kích hoạt. Không có phụ phí khối lượng/loại hàng tự suy đoán.
- Chọn đúng một policy active của currency, effective_from <= thời điểm server < effective_until; đúng một distance band `[min_km,max_km)`, max null là không giới hạn; đúng một time band; weather code phải có trong bảng giá. Thiếu hoặc trùng band → 503 PRICING_NOT_CONFIGURED. Không dùng mặc định multiplier=1 khi thiếu cấu hình.
- Time tính theo Asia/Ho_Chi_Minh, đầu bao gồm/cuối loại trừ. Band qua nửa đêm dùng ngày bắt đầu band. Không có ưu tiên ngầm giữa day_of_week null và band theo ngày; cấu hình trùng bị từ chối.
- Nhân và làm tròn chính xác bằng BCMath, không float. Return fee lấy nguyên `pricing_policies.return_fee_minor`, lưu riêng, không cộng vào cước giao ban đầu. Hủy Customer trước assignment phí 0; không áp dụng mức phí sau assignment ở endpoint này.
- Báo giá lưu snapshot server gồm địa chỉ/liên hệ, parcel, policy/version, từng component và nguồn context. Hiệu lực 5 phút. Tạo đơn dùng snapshot đó kể cả địa chỉ hoặc policy đổi sau báo giá, nhưng kiểm tra lại loại hàng còn được phép. Một quote chỉ tạo một đơn; đổi nội dung phải lấy quote mới. Không sửa giá của đơn đã tạo/đã thanh toán.
- Quote giữ PII; chưa có worker xóa quote hết hạn. Nhóm phải chốt retention trước khi triển khai worker; không tự xóa quote được orders tham chiếu.

## Địa chỉ/routing — Người 3 cung cấp, Người 1/2 sử dụng

CRUD địa chỉ thuộc Người 3 dù URL nằm trong customer. Places/Geocoding chuẩn hóa và route API dùng chung là việc Người 3 cần bổ sung. Validation inline address hiện có chưa phải Places normalization. Không đổi URL, bỏ inline support hoặc đổi kết quả provider khi chưa phối hợp caller Người 1/2. Người 1 sở hữu công thức/hệ số và snapshot; adapter hiện gộp transport Google và quy tắc thời tiết/phạm vi, cần phối hợp khi tách module.

Interface PHP `PricingContextProvider::resolve(pickup, delivery, at)` được inject vào OrderQuoteService. Nếu cấu hình SOMI_PRICING_CONTEXT_URL, HTTP adapter gọi server do nhóm quản lý bằng Bearer SOMI_PRICING_CONTEXT_TOKEN. URL phải HTTPS, không theo redirect; timeout kết nối 3s/tổng 10s, response tối đa 64 KiB. Adapter nội bộ phải thực thi cùng giới hạn TP.HCM, thời tiết và quãng đường; không dùng fallback khoảng cách giả.

**Provider trực tiếp đã triển khai:** khi không đặt SOMI_PRICING_CONTEXT_URL, endpoint dùng GoogleMeteoPricingProvider. Cần GOOGLE_MAPS_API_KEY bật Routes API và Geocoding API; OPEN_METEO_API_KEY dùng endpoint thương mại customer-api.open-meteo.com. Reverse geocode cả hai tọa độ, yêu cầu country VN và administrative_area_level_1 là Ho Chi Minh City; không dựa vào tên tỉnh do client nhập hoặc bounding box xấp xỉ. Sau đó gọi Routes với TWO_WHEELER, không dùng fallback route; từ 40 km trả 422 OUTSIDE_SERVICE_AREA. Các request dùng chung ngân sách 10s, không log URL có key hay tọa độ.

WMO mapping: 0/1/2/3/45/48 → NORMAL (1.00); 51/53/61/80 → LIGHT_RAIN (1.10); 55/63/65/81/82 → RAIN (1.20). Mã băng/tuyết/dông 56/57/66/67/71/73/75/77/85/86/95/96/99 → 422 WEATHER_SERVICE_PAUSED. Mã không biết hoặc weather source quá 30 phút → 503, không tự coi là thời tiết bình thường. observed_at là lúc context được tạo, weather_observed_at là thời điểm dữ liệu weather; không đánh đồng hai timestamp. Không phát hiện được ngập đường chỉ bằng weather_code.

Tài liệu provider: [Google Routes](https://developers.google.com/maps/documentation/routes/reference/rest/v2/TopLevel/computeRoutes), [Google reverse geocoding](https://developers.google.com/maps/documentation/geocoding/guides-v3/requests-reverse-geocoding), [Open-Meteo weather](https://open-meteo.com/en/docs), [commercial use](https://open-meteo.com/en/pricing).

Request server-to-server chỉ gồm tọa độ pickup/delivery và requested_at. Response 200 JSON:

```json
{
  "distance_km": "4.125", "duration_minutes": 20,
  "weather_condition": "PROVIDER_CONDITION_CODE",
  "provider": "routing-and-weather-adapter",
  "observed_at": "2026-11-05T08:00:00+07:00"
}
```

Timestamp minh họa phải thay bằng thời điểm thật; observed_at lệch server tối đa 300s. Distance tối đa 3 decimals, không âm; duration nguyên dương. Adapter chịu trách nhiệm quãng đường giao thực tế và dữ liệu thời tiết; client không chọn weather code. Chưa cấu hình/provider sai/hết hạn → 503 PRICING_CONTEXT_UNAVAILABLE.

## Tạo, xem và hủy đơn

- POST tạo đơn chỉ nhận quote_id. Idempotency-Key bắt buộc 8–100 ký tự `[A-Za-z0-9._:-]`. Cùng Customer/key/quote trả lại cùng đơn (trạng thái hiện tại); cùng key khác quote → 409 IDEMPOTENCY_CONFLICT; quote đã dùng với key khác → 409 QUOTE_ALREADY_USED. Quote khác chủ sở hữu → 404; hết hạn → 409 QUOTE_EXPIRED. Khóa profile, khóa quote và unique indexes bảo vệ request đồng thời.
- Đơn mới PENDING_PAYMENT; có history khởi tạo và event OrderCreated trong cùng transaction. Không coi client redirect/click thanh toán là tiền đã nhận.
- List: limit mặc định 20, 1–100; before_id là cursor exclusive theo order_id giảm dần; status tùy chọn thuộc danh sách bên dưới. next_before_id null khi hết trang. Chỉ trả đơn của mình. Detail có snapshot và history theo status_history_id.
- Hủy chỉ ở PENDING_PAYMENT, SEARCHING_SHIPPER, NO_SHIPPER_FOUND và không có ACTIVE assignment. Khóa order trước, đổi CANCELLED, ghi timestamps/history, hủy pending offers và phát OrderCancelled trong cùng transaction. Hủy lặp trả cùng đơn, không lặp history/event. Khi đã trả tiền, thêm RefundRequested cho module refund Người 1, **không phải refund đã thành công**.

## Hợp đồng trạng thái chung — Người 1 chốt, Người 2 triển khai giao nhận

Không thêm status ngoài enum DB. State machine:

| Từ | Sang | Chủ trì và điều kiện |
|---|---|---|
| PENDING_PAYMENT | SEARCHING_SHIPPER | Người 1: payment hợp lệ, đủ tiền và đúng currency |
| PENDING_PAYMENT / SEARCHING_SHIPPER / NO_SHIPPER_FOUND | CANCELLED | Customer: trước assignment, endpoint đã triển khai |
| SEARCHING_SHIPPER | SHIPPER_ASSIGNED | Người 2: offer PENDING/chưa hết 2 phút, shipper đủ điều kiện, accept nguyên tử |
| SEARCHING_SHIPPER | NO_SHIPPER_FOUND | Người 2: đợt matching kết thúc |
| NO_SHIPPER_FOUND | SEARCHING_SHIPPER | Người 2: retry matching khi đơn đã paid |
| SHIPPER_ASSIGNED | SEARCHING_SHIPPER | Người 2: hủy trước lấy hàng, đóng assignment, ghi sự kiện giảm điểm; không refund tự động |
| SHIPPER_ASSIGNED | PICKED_UP | Người 2: Shipper đang được phân công |
| PICKED_UP | IN_DELIVERY | Người 2: Shipper đang được phân công |
| PICKED_UP / IN_DELIVERY | RETURNING | Người 2: lý do giao thất bại và quyết định vận hành phù hợp |
| IN_DELIVERY | DELIVERED | Người 2: ảnh giao thành công bắt buộc |
| RETURNING | RETURNED | Người 2: Shipper xác nhận + ảnh và Customer xác nhận |

DELIVERED, RETURNED, CANCELLED là terminal. Giao thất bại lưu reason trong deliveries/history, không tạo enum DELIVERY_FAILED. Hủy sau lấy hàng là cancellation request chờ Admin; không được gọi Customer cancel hoặc tự đổi CANCELLED. Các endpoint delivery/Admin ngoại lệ thuộc đợt sau, không được xem bảng trên là đã triển khai chúng. `OrderLifecycleService::TRANSITIONS` là whitelist dùng chung; các module sau còn phải kiểm tra actor/evidence trước từng chuyển trạng thái.

**Quy tắc khóa bắt buộc:** mọi callback, matching accept, cancel và delivery transition khóa order bằng `SELECT ... FOR UPDATE` trước; sau đó payment / offer / assignment. Accept phải kiểm tra lại status SEARCHING_SHIPPER, paid_at, offer và điều kiện shipper sau khi khóa; tạo assignment rồi đổi status/history và vô hiệu hóa offer còn lại trong cùng transaction. Unique active assignment đã có trong schema. Nếu module khác bỏ quy tắc khóa order, không thể bảo đảm cancel/accept loại trừ lẫn nhau.

### Hook payment cho module Người 1 (hàm nội bộ đã có)

`OrderLifecycleService::paymentConfirmed(PDO $db, int $orderId, int $paymentId): string` là hàm nội bộ, không phải endpoint HTTP. Caller phải đang trong transaction, đã xác minh chữ ký callback/đối soát và chống giao dịch lặp bằng provider reference. Không cấp endpoint công khai cho client tự xác nhận thanh toán.

Thứ tự caller:

1. Begin transaction; `OrderLifecycleService::lock($db, $orderId)`.
2. Khóa payment, xác minh provider, amount/currency và ledger; cập nhật PAID/paid_at trong transaction đó.
3. Gọi paymentConfirmed. Hàm kiểm tra payment thuộc order, status PAID, paid_at có giá trị, amount và currency khớp snapshot. PENDING_PAYMENT → SEARCHING_SHIPPER, ghi paid_at/history và OrderReadyForMatching; callback lặp không phát event lần nữa.
4. Commit rồi mới trả callback thành công. Lỗi rollback toàn bộ.

Nếu tiền về sau CANCELLED, hook vẫn ghi paid_at, giữ CANCELLED và phát RefundRequested đúng một lần, không matching. Chữ ký provider, ledger/idempotency và refund thực tế thuộc Người 1; payout Shipper thuộc Người 3. Payment/email/refund/Admin đã triển khai theo PERSON1-API.md; SePay refund yêu cầu operator và chứng từ, payout thuộc Người 3.

### Integration event outbox

`order_events`: event_id, order_id, event_type, deduplication_key, payload JSONB, created_at. Được ghi cùng transaction đơn. Payload có schema_version=1, order_id/order_code/currency_code/total_charge_minor; không chứa địa chỉ hoặc số điện thoại.

| Event | Consumer |
|---|---|
| OrderCreated | Người 1 payment/email theo snapshot; Người 3 in-app; không coi là đã paid |
| OrderReadyForMatching | Người 2 bắt đầu matching, kiểm tra lại đơn chưa bị hủy |
| OrderCancelled | Người 2 dừng matching; Người 1 đối soát payment/refund/email; Người 3 in-app |
| RefundRequested | Người 1 refund về phương thức ban đầu; reason CUSTOMER_CANCELLATION, deducted_fee_minor=0 |

Worker payment/refund/email Người 1 đã có; delivery/payout worker thuộc Người 2/3. Mỗi consumer phải lưu receipt `(consumer,event_id)` bền vững và retry idempotent; không xóa event/đánh consumed chung. Event trả về theo thứ tự không thay kiểm tra trạng thái order dưới lock. Refund dùng khóa nghiệp vụ order/payment, tránh hoàn lặp kể cả khi nhận cả OrderCancelled và RefundRequested. Khi xử lý thanh toán sau cancel bắt buộc gọi hook trên. Không báo refund thành công trước provider xác nhận.

## Kiểm thử

Phân công đọc kết quả: Người 1 phụ trách quote/order/pricing/payment hook, Người 3 tiếp quản kiểm tra địa chỉ/Google; phần Shipper/GPS Người 2 cần bổ sung. Kết quả trong CUSTOMER-API-VALIDATION.md thuộc đợt trước, không phải kiểm thử mới ngày 06/11/2026.

`php tests/customer_orders.php`: dựng schema bằng TEMP tables trong connection riêng, search_path chỉ pg_temp/pg_catalog, tất cả rollback; không seed/chỉnh dữ liệu nghiệp vụ thật. Provider là fixture chỉ tồn tại trong test. Kiểm tra sở hữu, default address, snapshot, giá làm tròn chính xác, thiếu/trùng giá, quote hết hạn, create retry, pagination, payment replay/late capture, cancel trước/sau assignment và outbox. Đây là integration test service với PostgreSQL, chưa thay kiểm thử provider thật, browser UI, hoặc stress nhiều process tranh chấp đồng thời.

## Email, in-app và contract cần bổ sung

- Người 1 sở hữu toàn bộ API/service/template/send log/worker email tài khoản và giao dịch. Người 2/3 yêu cầu qua interface chung; không xây API/worker email riêng. EmailService/Person1Worker cung cấp encrypted queue, send log, TTL và retry.
- Người 3 sở hữu in-app, chat/ticket và earnings/payout, nhận dữ liệu/trạng thái hợp lệ từ Người 1/2, không tự xác nhận payment hoặc delivery.
- Bốn event trong bảng trên đã có trong code. PaymentConfirmed/RefundSucceeded và consumer receipt Người 1 đã có; contract delivery/email theo PERSON1-API.md. Offer/assignment/payout do Người 2/3 tiếp tục.
- Admin ticket Người 3 dùng guard/audit chung Người 1. NEW vs nhãn Open, ticket account với order_id bắt buộc và email fields trong notifications cần thống nhất/migration theo BACKEND-SCOPE.md. Sửa tài liệu không tự đổi DB.
