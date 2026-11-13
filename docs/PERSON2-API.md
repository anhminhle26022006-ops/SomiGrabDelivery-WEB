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
| `shipper/profile.php` | GET | `profile.read_own` | `{profile, eligibility:{can_work, blockers}, has_active_delivery, account, application}` |
| `shipper/profile.php` | PUT | `profile.update_own` | `{vehicle_type, vehicle_registration}`. Hồ sơ đang APPROVED sẽ về PENDING và tắt nhận đơn |
| `shipper/dashboard.php` | GET | `profile.read_own` | `?range=today\|yesterday\|week\|month`. Số liệu trang Tổng quan: `{current, previous, deliveries_change_percent, rating, reliability_score}` |
| `shipper/kyc.php` | GET | `profile.read_own` | Trạng thái duyệt, lý do bị trả về, tài liệu đã nộp kèm `file_id` để xem/tải qua storage có session |
| `shipper/kyc.php` | POST | `profile.update_own` | `{identity?, documents?}`. Chỉ khi NEEDS_INFO hoặc REJECTED |
| `shipper/availability.php` | GET, POST | `offer.read_own`, `offer.respond_own` | `{available:true, latitude, longitude}` hoặc `{available:false}` |
| `shipper/offers.php` | GET | `offer.read_own` | `{items}` các offer còn hạn, chưa có thông tin liên hệ |
| `shipper/offers.php` | POST | `offer.respond_own` | `{offer_id, note?}` từ chối |
| `shipper/offers.php` | POST | `offer.respond_own` | `{action:"refresh"}` ghép tối đa 5 đơn đã thanh toán gần shipper và trả offer còn hạn; giữ nguyên điều kiện GPS/KYC/không nhận đơn của mình |
| `shipper/accept-offer.php` | POST | `offer.respond_own` | `{offer_id}`. 201 khi vừa nhận, 200 khi gọi lại |
| `shipper/deliveries.php` | GET | `delivery.update_assigned` | Đơn đang giao kèm `next_actions`; `?history=1` cho lịch sử |
| `shipper/deliveries.php` | POST | `delivery.update_assigned` | `{order_id, action:"PICKUP"\|"START_DELIVERY"}` |
| `shipper/evidence.php` | POST | `delivery.upload_evidence` | `{order_id, evidence_type, reference_file_id}` after private Storage upload |
| `shipper/complete-delivery.php` | POST | `delivery.update_assigned` | `{order_id}`. Cần ít nhất một `DELIVERY_PHOTO` |
| `shipper/return-delivery.php` | POST | `delivery.update_assigned` | `{order_id, action:"FAIL", reason_code, note?}` hoặc `{order_id, action:"RETURNED"}` |
| `shipper/location.php` | POST, GET | `delivery.update_assigned` | `{latitude, longitude, accuracy_meters?}`; GET `?order_id=` |
| `customer/shipper-location.php` | GET | `order.read_own` | `?order_id=`. Vị trí mới nhất của Shipper trên đơn của Customer |
| `customer/confirm-return.php` | POST | `order.read_own` | `{order_id}`. Customer xác nhận đã nhận lại hàng hoàn |

Mã lỗi riêng: `SHIPPER_NOT_ELIGIBLE` (403), `OFFER_EXPIRED`, `OFFER_LOST_RACE`, `OFFER_NOT_PENDING`, `ORDER_NOT_AVAILABLE`, `PAYMENT_NOT_CONFIRMED`, `AVAILABILITY_OFF`, `ACTIVE_DELIVERY_EXISTS`, `INVALID_ORDER_STATE`, `EVIDENCE_REQUIRED`, `EVIDENCE_LIMIT_REACHED`, `RETURN_NOT_HANDED_OVER`, `NO_ACTIVE_DELIVERY`, `KYC_NOT_RESUBMITTABLE`, `VEHICLE_REGISTRATION_TAKEN` (đều 409) và `LOCATION_RATE_LIMITED` (429).

## Quy tắc nghiệp vụ

**Tổng quan.** Bộ lọc ngày dùng múi giờ `Asia/Ho_Chi_Minh`, tuần bắt đầu thứ Hai và khoảng thời gian `[start, end)`. Số đơn hoàn thành đếm toàn bộ assignment `COMPLETED` theo `delivered_at`, không bị giới hạn 50 dòng của lịch sử. `delivery_seconds` là tổng thời gian từ nhận đến kết thúc phân công giao nhau với khoảng đã chọn, không phải thời gian bật nhận đơn. Đơn đang giao và đánh giá lấy từ `deliveries.php` và `profile.php`; không hiển thị số liệu mẫu khi API lỗi. Hồ sơ cá nhân đọc `account/profile.php`; PUT chỉ cập nhật `full_name`. Các trường chưa có API cập nhật được khóa trên giao diện.

**Trung tâm hỗ trợ.** Trang riêng `?page=shipper&view=support` chứa ticket, phản hồi, tệp và tài khoản nhận tiền. Mục `tab=chat&id=<order_id>` mở hội thoại đúng đơn; alias `view=chat` cũng dẫn đến trang hỗ trợ. Trang khác không hiển thị form hỗ trợ hoặc tự tải danh sách ticket. Chat dùng API `interactions.php`, cập nhật mỗi 8 giây khi trang đang hiển thị, giữ nguyên bản nháp khi nhận tin mới và giữ `client_key`/file đã upload khi gửi lại sau lỗi. Đây là chat theo đơn với các thành viên được API cấp quyền; trao đổi với bộ phận hỗ trợ nằm trong ticket. Mỗi tệp đính kèm tối đa 5 MiB, tối đa 5 tệp/tin nhắn. Kiểm thử: `node tests/shipper_support_browser.cjs`; ảnh desktop/mobile tạo tại `tests/.runtime/support/`.

**Tách workspace.** Migration `20261113_workspace_notifications` gắn `notifications.recipient_role` và `support_tickets.opened_role`. API lấy workspace từ session đã xác thực, không từ tham số client. Feed, badge, phân trang, đánh dấu từng thông báo và đánh dấu tất cả đều lọc cả `user_id` và `recipient_role`. Sự kiện tạo đơn, xác nhận thanh toán, hoàn tiền và chưa tìm được người giao chỉ gửi CUSTOMER; offer và chi trả chỉ gửi SHIPPER. Ticket, phản hồi và tệp liên quan cũng giới hạn theo workspace. Dữ liệu cũ được phân loại bằng đơn hàng, phân công và vai trò đã lưu; bản ghi không xác định chắc chắn vẫn được giữ với role NULL và không xuất hiện trong feed cá nhân. Kiểm thử tài khoản có hai vai trò: `php tests/workspace_notifications.php` (TEMP TABLE).

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
php tests/shipper_dashboard.php    # ranh giới ngày và thống kê PostgreSQL bằng TEMP TABLE
node tests/shipper_dashboard_browser.cjs # tổng quan, hồ sơ, lỗi tải, đổi khoảng ngày và mobile qua HTTP fixtures
php tests/shipper_accept_race.php  # 40 kiểm tra tranh chấp bằng tiến trình song song
```

Hai file đầu dùng TEMP TABLE nên không ghi vào dữ liệu thật. File thứ ba cần bảng thật nên chỉ chạy khi `SOMI_DB_NAME=somi_shipper_test` và `SOMI_DB_HOST=127.0.0.1`.

## Phần còn chờ và điểm cần nhóm thống nhất

- **Private Storage integration:** upload multipart to `storage.php` with `purpose=KYC` or `EVIDENCE` (evidence also requires `order_id`). Submit the returned `file_id` as `reference_file_id`. KYC documents contain `{document_type,reference_file_id}`. Backend checks ownership, purpose and order inside the business transaction, and derives key/MIME/size/checksum from stored metadata. Arbitrary paths and client metadata are rejected. JPEG/PNG/WebP evidence and JPEG/PNG/WebP/PDF KYC are limited to 5 MiB. Evidence is retained for 90 days after order closure; GPS remains 7 days. Legacy placeholder evidence cannot satisfy delivery completion.
- **Thu nhập và điểm.** Module này chỉ phát sự kiện; ghi `shipper_earnings` thuộc Người 3. Chưa có luồng Shipper tự hủy đơn trước khi lấy hàng vì chính sách trừ điểm chưa chốt.
- **Giao diện.** Offer, nhận đơn, đơn đang giao, lịch sử, phương tiện, hồ sơ cá nhân và Tổng quan đã nối API. Thông báo, chat và thu nhập dùng module `person3.js`. Một số chuỗi mới chưa có bản tiếng Anh trong bảng dịch của `app/Views/shipper/index.php`.
- **Test trình duyệt cũ.** `tests/ui_e2e.cjs` và `tests/reaudit_browser.cjs` có bước bấm nút chi tiết của offer demo; danh sách offer giờ lấy từ database nên hai bước đó cần fixture offer hoặc cập nhật selector.
- **File dùng chung đã chạm.** `database/migrate-customer-orders.php` (thêm tên migration) và `app/Views/shipper/index.php` (nạp module JS). Cần Người 1 xem qua khi merge.

Trạng thái nhận đơn được lưu trong database và khôi phục sau khi tải lại trang. Khi bật nhận đơn, giao diện kiểm tra offer mỗi 5 giây, cập nhật GPS mỗi 2 phút và làm mới GPS khi quay lại tab. POST refresh bổ sung phân phối đơn khi worker chưa chạy liên tục; worker vẫn cần cho việc quét toàn hệ thống.

## Trang Tổng quan (cập nhật 13/11/2026)

`ShipperDashboardService` tính số liệu từ `order_assignments` và `order_match_offers`, không lưu bảng tổng hợp riêng. Mỗi kỳ là khoảng nửa mở tính theo giờ Việt Nam và được so với kỳ liền trước cùng loại; tuần bắt đầu từ thứ Hai.

- `deliveries` đếm assignment `COMPLETED`, `returns` đếm assignment `RETURNED`, cả hai theo `ended_at`.
- `acceptance_rate_percent` là số offer `ACCEPTED` chia cho số offer đã có kết quả trong kỳ. Offer còn `PENDING` không tính. Giá trị là `null` khi chưa có offer nào.
- `deliveries_change_percent` là `null` khi kỳ trước không có đơn.
- `rating.average` là `null` khi chưa có lượt đánh giá.

API hồ sơ (`shipper/profile.php`) trả thêm khối `account` gồm `code`, `email`, `phone`, `joined_at` để trang Tài khoản hiển thị dữ liệu thật. Các trường này chỉ đọc.

Giao diện: `shipper-dashboard.js` điền bốn thẻ số liệu và thẻ Đơn đang giao, `shipper-header.js` điền tên và chữ viết tắt trên thanh đầu trang. Thẻ thu nhập vẫn do `person3.js` của Người 3 điền. Thẻ "Thời gian hoạt động" đã bỏ vì hệ thống không ghi nhận thời gian trực tuyến; thay bằng tỷ lệ nhận đơn.

Các trường khu vực, địa chỉ, dòng xe và màu xe chưa hỗ trợ lưu được khóa trên trang Tài khoản. Họ tên được cập nhật qua account/profile.php. API Tổng quan cũng trả delivery_seconds (thời gian xử lý phân công trong kỳ), completed_deliveries, timezone, start và end.

`application` trong GET hồ sơ chỉ trả các trường hiển thị của người đang đăng nhập (địa chỉ, khu vực, liên hệ khẩn cấp, hãng/dòng/màu xe, hạn giấy tờ); không trả số GPLX hoặc ciphertext. Trang Tài khoản hiển thị dữ liệu này ở chế độ chỉ đọc và danh sách KYC có liên kết xem/tải thật.
