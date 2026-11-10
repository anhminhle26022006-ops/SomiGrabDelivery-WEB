# Người 2: API Shipper, ghép đơn, giao nhận và GPS

Cập nhật 10/11/2026. Tài liệu bàn giao phần Shipper cho Người 1 và Người 3. Quy ước chung (session, JSON, định dạng lỗi, ID và tiền dạng chuỗi) giữ nguyên như [PERSON1-API.md](PERSON1-API.md); mọi endpoint dưới đây chạy qua `somi_run_api()` nên không có cơ chế xác thực riêng.

## Cài đặt và vận hành

```powershell
php database/migrate-customer-orders.php --apply   # áp dụng thêm 20261110_shipper_delivery
php bin/shipper-worker.php --seconds=55            # lên lịch mỗi phút
php bin/shipper-retention.php                      # lên lịch mỗi ngày
```

Migration chỉ thêm ba cột vị trí gần nhất vào `shipper_profiles` và ba index. Không seed chính sách điểm, thu nhập hay tài khoản. Tham số vận hành nằm ở `app/Config/shipper-matching.php`: offer 60 giây, mỗi vòng 3 Shipper, tối đa 3 vòng, bán kính 5 km, tìm tối đa 5 phút, GPS cách nhau ít nhất 5 giây và lưu 7 ngày, ảnh bằng chứng lưu 90 ngày. Hai mốc thời gian lưu là giá trị tạm, nhóm cần chốt lại.

## Endpoint

Base `/public/api/`. Workspace phải là SHIPPER trừ hai endpoint Customer ở cuối bảng.

| Endpoint | Method | Quyền | Nội dung |
|---|---|---|---|
| `shipper/profile.php` | GET | `profile.read_own` | `{profile, eligibility:{can_work, blockers}, has_active_delivery}` |
| `shipper/profile.php` | PUT | `profile.update_own` | `{vehicle_type, vehicle_registration}`. Hồ sơ đang APPROVED sẽ về PENDING và tắt nhận đơn |
| `shipper/kyc.php` | GET | `profile.read_own` | Trạng thái duyệt, lý do bị trả về, tài liệu đã nộp |
| `shipper/kyc.php` | POST | `profile.update_own` | `{identity?, documents?}`. Chỉ khi NEEDS_INFO hoặc REJECTED |
| `shipper/availability.php` | GET, POST | `offer.read_own`, `offer.respond_own` | `{available:true, latitude, longitude}` hoặc `{available:false}` |
| `shipper/offers.php` | GET | `offer.read_own` | `{items}` các offer còn hạn, chưa có thông tin liên hệ |
| `shipper/offers.php` | POST | `offer.respond_own` | `{offer_id, note?}` từ chối |
| `shipper/accept-offer.php` | POST | `offer.respond_own` | `{offer_id}`. 201 khi vừa nhận, 200 khi gọi lại |
| `shipper/deliveries.php` | GET | `delivery.update_assigned` | Đơn đang giao kèm `next_actions`; `?history=1` cho lịch sử |
| `shipper/deliveries.php` | POST | `delivery.update_assigned` | `{order_id, action:"PICKUP"\|"START_DELIVERY"}` |
| `shipper/evidence.php` | POST | `delivery.upload_evidence` | `{order_id, evidence_type, file_reference, mime_type, byte_size, checksum_sha256?}` |
| `shipper/complete-delivery.php` | POST | `delivery.update_assigned` | `{order_id}`. Cần ít nhất một `DELIVERY_PHOTO` |
| `shipper/return-delivery.php` | POST | `delivery.update_assigned` | `{order_id, action:"FAIL", reason_code, note?}` hoặc `{order_id, action:"RETURNED"}` |
| `shipper/location.php` | POST, GET | `delivery.update_assigned` | `{latitude, longitude, accuracy_meters?}`; GET `?order_id=` |
| `customer/shipper-location.php` | GET | `order.read_own` | `?order_id=`. Vị trí mới nhất của Shipper trên đơn của Customer |
| `customer/confirm-return.php` | POST | `order.read_own` | `{order_id}`. Customer xác nhận đã nhận lại hàng hoàn |

Mã lỗi riêng: `SHIPPER_NOT_ELIGIBLE` (403), `OFFER_EXPIRED`, `OFFER_LOST_RACE`, `OFFER_NOT_PENDING`, `ORDER_NOT_AVAILABLE`, `PAYMENT_NOT_CONFIRMED`, `AVAILABILITY_OFF`, `ACTIVE_DELIVERY_EXISTS`, `INVALID_ORDER_STATE`, `EVIDENCE_REQUIRED`, `EVIDENCE_LIMIT_REACHED`, `RETURN_NOT_HANDED_OVER`, `NO_ACTIVE_DELIVERY`, `KYC_NOT_RESUBMITTABLE`, `VEHICLE_REGISTRATION_TAKEN` (đều 409) và `LOCATION_RATE_LIMITED` (429).

## Quy tắc nghiệp vụ

**Điều kiện hoạt động.** `ShipperEligibility` là nơi duy nhất quyết định Shipper có được làm việc hay không: tài khoản ACTIVE, hồ sơ APPROVED, giấy tờ APPROVED và điểm tin cậy không thấp hơn `lock_threshold` của chính sách điểm đang hiệu lực (nếu Admin đã ban hành). Availability, matching và accept đều gọi cùng class này.

**Ghép đơn.** Worker đọc `OrderReadyForMatching` bằng biên nhận riêng `consumer = 'shipper-matching'`, rồi gửi offer cho các Shipper đang bật nhận đơn, đang rảnh, có vị trí cập nhật trong 10 phút và nằm trong bán kính, ưu tiên người gần điểm lấy hàng nhất. Hết hạn hoặc bị từ chối hết thì gửi vòng kế tiếp cho những người chưa được mời. Hết 3 vòng hoặc quá 5 phút thì đơn sang `NO_SHIPPER_FOUND` và phát sự kiện `NoShipperFound`.

**Nhận đơn nguyên tử.** `OfferAcceptService::accept()` khóa theo thứ tự chung của dự án: order, offer, rồi hồ sơ Shipper. Sau khi giữ khóa mới kiểm tra hạn offer, trạng thái đơn, thanh toán và điều kiện Shipper. Người thắng nhận assignment và bản ghi `deliveries`; offer của người khác thành `LOST_RACE`. Hai unique index (`ux_order_single_active_assignment`, `ux_shipper_single_active_assignment`) là lớp chặn cuối ở database. Mỗi Shipper chỉ chạy một đơn tại một thời điểm.

**Giao nhận.** Trạng thái đi theo `OrderLifecycleService::TRANSITIONS`. Giao thành công bắt buộc có `DELIVERY_PHOTO`. Giao thất bại chuyển sang `RETURNING`; đơn chỉ thành `RETURNED` khi Shipper báo đã trả hàng (kèm `RETURN_PHOTO`) và Customer xác nhận đã nhận lại.

**GPS.** Điểm GPS luôn gắn vào assignment đang hoạt động của Shipper, trình duyệt không gửi `order_id`. Sau khi đơn kết thúc, API không trả vị trí nữa. Dữ liệu quá hạn bị xóa hẳn bằng `bin/shipper-retention.php`.

## Sự kiện phát ra

Ghi vào `order_events` bằng `OrderLifecycleService::event()` trong cùng transaction với thay đổi trạng thái. Payload có thêm `assignment_id` và `shipper_id` dạng chuỗi.

| Sự kiện | Khóa chống trùng | Ai dùng |
|---|---|---|
| `ShipperAssigned` | `assigned:<assignment_id>` | Người 3 (thông báo) |
| `OrderPickedUp` | `picked-up:<assignment_id>` | Người 3 (thông báo) |
| `OrderDelivered` | `delivered:<order_id>` | Người 1 (email), Người 3 (thu nhập, thông báo) |
| `OrderReturning` | `returning:<order_id>` | Người 1 (email), Người 3 |
| `OrderReturned` | `returned:<order_id>` | Người 1 (email, hoàn tiền), Người 3 (thu nhập hoàn hàng) |
| `NoShipperFound` | `no-shipper:<order_id>:<lần>` | Người 1 quyết định cho tìm lại hoặc hoàn tiền |

## Kiểm thử

```powershell
php tests/shipper_matching.php     # 53 kiểm tra: hồ sơ, KYC, availability, matching, offer, accept
php tests/shipper_delivery.php     # 44 kiểm tra: giao nhận, bằng chứng, hoàn hàng, GPS
php tests/shipper_accept_race.php  # 40 kiểm tra tranh chấp bằng tiến trình song song
```

Hai file đầu dùng TEMP TABLE nên không ghi vào dữ liệu thật. File thứ ba cần bảng thật nên chỉ chạy khi `SOMI_DB_NAME=somi_shipper_test` và `SOMI_DB_HOST=127.0.0.1`.

## Phần còn chờ và điểm cần nhóm thống nhất

- **Upload file thật.** KYC và bằng chứng hiện lưu tham chiếu dạng `pending-upload/...`. Khi Storage của Người 3 sẵn sàng, giao diện upload trước rồi gửi object key thật vào đúng các trường `file_reference` hiện có.
- **Thu nhập và điểm.** Module này chỉ phát sự kiện; ghi `shipper_earnings` thuộc Người 3. Chưa có luồng Shipper tự hủy đơn trước khi lấy hàng vì chính sách trừ điểm chưa chốt.
- **Giao diện.** Offer, nhận đơn, đơn đang giao, lịch sử và phương tiện đã nối API. Thông báo, chat, thu nhập và số liệu trang Tổng quan vẫn là demo. Chuỗi mới chưa có bản tiếng Anh trong bảng dịch của `app/Views/shipper/index.php`.
- **Test trình duyệt cũ.** `tests/ui_e2e.cjs` và `tests/reaudit_browser.cjs` có bước bấm nút chi tiết của offer demo; danh sách offer giờ lấy từ database nên hai bước đó cần fixture offer hoặc cập nhật selector.
- **File dùng chung đã chạm.** `database/migrate-customer-orders.php` (thêm tên migration) và `app/Views/shipper/index.php` (nạp module JS). Cần Người 1 xem qua khi merge.
