# Kiểm thử local — 11/11/2026

Source được kiểm tra: `abe4959`.

## Kết quả

**30/30 suite PASS. Không có suite FAIL/TIMEOUT trong nhóm đã chạy.**

Môi trường: PHP/XAMPP, Node.js, Chrome/Playwright và PostgreSQL portable riêng tại `127.0.0.1:55433`, database `somi_shipper_test`.

Các suite service dùng TEMP tables; test tranh chấp dùng schema của DB test riêng. Server fixture chỉ lắng nghe loopback. Server và PostgreSQL test đã được tắt sau khi chạy. Không sửa `.env`, không ghi vào Supabase ứng dụng, không gửi email/chuyển tiền hoặc gọi API provider thật, không đổi cấu hình hosting.

| Nhóm | Suite | Kết quả |
|---|---|---|
| Cấu hình | environment, database_diagnostics | PASS |
| Payment và giá | sepay, pricing_provider, open_meteo, customer_pricing | PASS |
| Địa chỉ và bản đồ | geoapify_addresses, geoapify_frontend, geoapify_maps | PASS |
| Customer và Người 1 | customer_orders, person1_backend | PASS |
| Admin và migration | admin_console, migration_compat, parcel_categories | PASS |
| Auth frontend/origin | reaudit_auth_frontend, request_origin, request_origin_http | PASS |
| Shipper | shipper_matching, shipper_delivery, shipper_accept_race | PASS |
| Person 3 backend | person3_backend | PASS |
| HTTP | person3_http, person3_upload_http, deployment_smoke, admin_http | PASS |
| Browser Customer | customer_orders_browser, customer_addresses_browser | PASS |
| Browser Admin/Person 3 | admin_browser, person3_browser | PASS |
| Browser Pricing | pricing_browser | PASS — 44 kiểm tra, không có runtime exception |

Kiểm tra upload/download dùng multipart và file thật trong storage fixture local, gồm hash, loại/dung lượng file, ownership và retention. Đây không phải nghiệm thu storage đang phục vụ người dùng.

## Checklist cần test thủ công

Các mục dưới đây chưa được chứng minh bằng lần chạy tự động này; không phải lỗi được phát hiện từ 30 suite.

- [ ] **GPS và Geoapify thật:** cấp/từ chối quyền vị trí; chọn GPS tự điền địa chỉ; nhập địa chỉ khác tự hiện gợi ý; chọn đủ A/B vẽ tuyến, hiện km/phút; điểm lấy cách vị trí hiện tại hơn 1 km cần xác nhận. Browser suite dùng GPS/provider fixture.
- [ ] **Báo giá và đơn hàng thật trên môi trường test:** Customer test VND → địa chỉ TP.HCM → kiện hàng → báo giá Geoapify/Open-Meteo → tạo/xem/hủy đơn. Kiểm tra tuyến dưới 40 km và tổng khối lượng không quá 30 kg.
- [ ] **SePay sandbox và IPN:** checkout VND → hoàn tất thanh toán sandbox → IPN HTTPS → payment PAID → mở matching. Thử callback lặp/sai amount và tiền đến sau hủy. Localhost đơn thuần không nhận IPN từ Internet. Không chuyển tiền thật chỉ để hoàn tất test.
- [ ] **Email thực tế:** dùng tài khoản/hộp thư test được chỉ định; nhận thư xác minh/reset/OTP ngân hàng; thử sai, hết hạn và dùng lại; đổi/reset mật khẩu phải thu hồi phiên cũ. OTP điện thoại vẫn demo nếu chưa cấu hình SMS provider.
- [ ] **Worker và một đơn xuyên suốt ba vai trò:** Customer tạo/thanh toán → Shipper nhận/lấy/giao → Customer thấy trạng thái → email/in-app/earnings. Cần kiểm tra tiến trình worker và lịch chạy thật, không suy ra scheduler hoạt động từ test service.
- [ ] **KYC/bằng chứng qua UI:** cấu hình storage private ngoài web root và signing key; upload hợp lệ/sai loại/quá dung lượng; kiểm tra quyền tải file; nộp → Admin duyệt → Shipper thấy kết quả. Việc nối upload vào giao nhận cần phối hợp Người 2/3.
- [ ] **Refund/payout thủ công:** kiểm tra phí, reservation, chứng từ và đối soát trên dữ liệu test/sandbox. Chỉ SUCCEEDED khi có chứng từ xác nhận; PROCESSING không có nghĩa đã chuyển tiền. Cần `SOMI_PAYOUT_MODE=manual` ở môi trường test để nghiệm thu tạo payout.
- [ ] **Toàn bộ màn hình ba vai trò với dữ liệu thực tế:** Admin fixture đã kiểm tra 15 view ở 3 kích thước; Customer có create/orders/detail/addresses và Pricing; Person 3 có panel và payout UI. Cần duyệt thêm toàn bộ màn Shipper, các màn Customer còn lại, VI/EN và mobile bằng tài khoản test.

## Điều kiện local còn thiếu

Đã kiểm tra lại trong lượt này: `SOMI_STORAGE_ROOT`, `SOMI_STORAGE_SIGNING_KEY`, `SOMI_PAYOUT_MODE`, `SOMI_RUN_WORKERS`, `SOMI_MIGRATE_ON_START` chưa có giá trị. Biến test chỉ được đặt cho process test, không ghi vào `.env`.

Web local hiện vẫn dùng cấu hình Supabase của ứng dụng. Khi mở web local để test manual, cần dùng tài khoản/dữ liệu test đã thống nhất. DB cách ly của các suite không tự thay cấu hình DB cho web local. Cấu hình local không xác nhận cấu hình hosting.

## Bằng chứng và giới hạn

- Log từng suite: `tests/.runtime/local-validation/`. JSON tổng hợp và ảnh browser nằm trong runtime này hoặc thư mục fixture tương ứng. Runtime bị Git bỏ qua.
- Không chạy `tests/ui_e2e.cjs` legacy trong lượt này: runner cần `person1-fixtures.json`, DB `somi_person1_test` và session test tương ứng; danh sách màn/selector offer demo cũ cần cập nhật theo module Shipper đã tích hợp.
- Không chạy load test trên Supabase/hosting. Không thực hiện thao tác nghiệp vụ thật để lấy kết quả PASS.
