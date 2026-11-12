# Đối chiếu scope và phần việc còn lại — 12/11/2026

Mã đối chiếu: `cc82c7a` trên local và `origin/dev` (đã fetch, hai đầu trùng nhau). Đây là rà soát source/contract, không phải chứng nhận toàn bộ hosting đã nghiệm thu. Không sửa nghiệp vụ hay database trong đợt rà soát này.

## Căn cứ và cách đọc

- `TEAM-OWNERSHIP.md`, `docs/BACKEND-SCOPE.md`, `docs/PERSON1-API.md`, `docs/PERSON2-API.md`, `docs/PERSON3-API.md` và mã gọi thực tế.
- Áp dụng điều chỉnh ngày 09/11: **Người 1 phụ trách địa chỉ/Geoapify/Leaflet và Admin ticket/chat**. Các dòng phân công Google/địa chỉ cho Người 3 trong bảng cũ không còn là đầu việc mới của Người 3.
- Storage đã được chọn là **Supabase Storage**. Payout đã chọn **chuyển khoản thủ công + đối soát**. Không tiếp tục coi hai quyết định này là chưa chốt. Refund Customer thuộc Người 1, payout Shipper thuộc Người 3.
- P0: cần hoàn tất trước nghiệm thu end-to-end. P1: hoàn thiện chức năng, vận hành hoặc chốt chính sách trước sử dụng rộng rãi.
- “Đã có source” không đồng nghĩa đã xác minh provider thật, lịch worker hosting hay tất cả màn hình trình duyệt.

## Những phần đã có, không giao làm lại

| Owner | Source đã có |
|---|---|
| Người 1 | Auth/session/RBAC/audit, Customer quote/order/cancel/payment/refund, snapshot/idempotency, chính sách giá, Admin core/KYC/ticket/chat, queue email; địa chỉ/autocomplete/route Geoapify, GPS lựa chọn điểm lấy; chọn VND/USD độc lập ngôn ngữ; tối ưu tải Customer. |
| Người 2 | Shipper profile/vehicle, backend KYC resubmit, eligibility/availability, matching/offer/accept nguyên tử, giao/hoàn hàng, GPS và CLI retention; UI offer/đơn đang giao/lịch sử/phương tiện gọi API. |
| Người 3 | Chat/ticket/reply/notifications, worker/receipt/retry, earnings/manual payout/FX evidence, bank-account OTP, upload/download private files; adapter Supabase đã tích hợp và bucket đã được kiểm tra thật trong phiên trước. |

## Người 1 — phần còn phải làm

| ID | Ưu tiên | Loại | Việc còn lại và tiêu chí hoàn tất |
|---|---|---|---|
| N1-01 | P0 | Nghiệm thu provider | Hoàn tất một giao dịch SePay thực từ web → IPN 200 → payment PAID → ledger đúng một lần → OrderReadyForMatching. Kiểm tra source/khóa, mở được QR và IPN auth đã làm; chưa có bằng chứng round trip thanh toán thành công. Lần kiểm tra gần nhất của đơn `SOMI-8507791CD9A73F8B172B` vẫn PROCESSING; SePay trả AUTHENTICATION_NOT_NEEDED. Không tự sửa PAID để nghiệm thu. |
| N1-02 | P0 | Code UI / tích hợp Người 2 | Nối Customer xác nhận đã nhận hàng hoàn vào `customer/confirm-return.php` và hiển thị vị trí Shipper từ `customer/shipper-location.php`. Endpoint có nhưng controller Customer đang chạy `server.js` chưa gọi hai API này. Hoàn hàng phải có hành động Customer thực, không cập nhật tay DB. |
| N1-03 | P0 | Tích hợp / vận hành | Phối hợp Người 2 đưa matching worker + GPS retention vào lịch triển khai. Scheduler hiện chỉ gọi person1-worker và person3-worker. Xác nhận worker thực chạy trên hosting, dùng cùng DB/env với web và không chạy trùng ngoài ý muốn. |
| N1-04 | P0 | Tích hợp Admin | Nối/kiểm tra Admin xem được file KYC, ảnh giao/hoàn từ Storage theo quyền sau khi Người 2 đăng ký file thật; duyệt KYC và xem chứng từ phải có audit. Không coi duyệt trạng thái KYC là đã nghiệm thu việc xem tài liệu thật. |
| N1-05 | P1 | Nghiệm thu email / cấu hình | Kiểm tra inbox thật cho verification, reset, email giao dịch và OTP đổi ngân hàng; kiểm tra queue/retry/expiry trên hosting. SMS hiện chỉ có demo/adapter, cần chốt nhà cung cấp nếu yêu cầu SMS thật; không tính đây là tích hợp SMS đã xong. |
| N1-06 | P1 | Chính sách / tích hợp | Chốt chính sách điểm và thu nhập/bồi hoàn cùng nhóm, ban hành qua Admin; chốt xử lý NO_SHIPPER_FOUND (tìm lại hoặc hủy/hoàn). Không seed tỷ lệ, phí hoặc điểm giả định. |
| N1-07 | P1 | Hosting / hiệu năng | Xác nhận hosting đã phục vụ bản tối ưu cc82c7a; nghiệm thu lại lỗi danh sách đơn trắng bằng session thật. Kiểm tra vùng Render/Supabase và ngân sách connection trước khi bật persistent. Lịch sử hiện tải hết cursor trước khi render, nên cần phân trang/tải dần khi số đơn lớn. |

Bằng chứng chính: `public/assets/js/customer/server.js`, `public/api/customer/confirm-return.php`, `public/api/customer/shipper-location.php`, `app/Services/SePayPaymentProvider.php`, `bin/person3-scheduler.php`, `public/assets/js/admin-live.js`.

## Người 2 — phần còn phải làm

| ID | Ưu tiên | Loại | Việc còn lại và tiêu chí hoàn tất |
|---|---|---|---|
| N2-01 | P0 | Code / tích hợp Storage | **Thay ảnh bằng chứng giả bằng upload thật.** `shipper-active.js` đang tạo `pending-upload/evidence/...` rồi báo đã ghi nhận ảnh. Phải upload multipart vào storage.php trước, lấy file ID/object metadata, sau đó đăng ký bằng chứng. Backend `DeliveryCompletionService::addEvidence()` hiện chỉ kiểm tra chuỗi tham chiếu/MIME/size, chưa xác thực file tồn tại, owner, purpose và order trong private_files. Không cho DELIVERED/RETURNED chỉ nhờ một chuỗi tham chiếu tự gửi. |
| N2-02 | P0 | Code UI / KYC | Hoàn thiện UI nộp/bổ sung KYC thật: upload tài liệu, gọi shipper/kyc.php POST, hiển thị trạng thái/lý do và retry an toàn. Backend đã có resubmit; shipper-account.js hiện chủ yếu đọc KYC và lưu phương tiện. KYC backend cũng cần kiểm chứng file đã upload thay vì chỉ nhận chuỗi. |
| N2-03 | P0 | Worker / phối hợp Người 1 | Đưa `bin/shipper-worker.php --seconds=55` vào lịch mỗi phút và `bin/shipper-retention.php` vào lịch hằng ngày. Test payment thật phát sự kiện xong thì offer thực xuất hiện. Có script CLI chưa đủ để matching tự chạy trên Render. |
| N2-04 | P0 | Code sự kiện | Phát `OfferCreated` sau insert offer và `OrderDelivering` khi START_DELIVERY, cùng transaction và dedup key phù hợp. MatchingService hiện insert offer rồi return; DeliveryService::startDelivery chuyển trạng thái nhưng chưa phát OrderDelivering. Người 3 đã có consumer hai event này. |
| N2-05 | P1 | Code nghiệp vụ / chốt policy | Hoàn thiện Shipper hủy trước lấy hàng, yêu cầu Admin sau lấy hàng và sự kiện/trừ/khôi phục điểm theo policy được duyệt. Source đã có eligibility theo điểm và Admin chỉnh điểm; chưa thấy luồng tự động đầy đủ theo scope. |
| N2-06 | P1 | Đồng bộ contract | Offer TTL hiện **60 giây**, scope cũ mô tả **2 phút**: đồng bộ với scope hoặc ghi nhận quyết định thay đổi. Đồng bộ giới hạn file **10 MiB** của luồng bằng chứng với Storage **5 MiB** trước khi nối upload. |
| N2-07 | P1 | UI / nghiệm thu | Rà soát số liệu Tổng quan, avatar/các nút demo và VI/EN; nối đúng API hoặc ghi rõ chưa hỗ trợ. Test thực trên mobile: availability → offer → accept → GPS → giao thành công và nhánh hoàn hàng. Không giao lại chat/notification/earnings vốn đã có API của Người 3. |

Bằng chứng chính: `public/assets/js/shipper-active.js:145`, `app/Services/DeliveryCompletionService.php:20`, `app/Services/ShipperKycService.php:86`, `public/assets/js/shipper-account.js`, `app/Services/MatchingService.php:50`, `app/Services/DeliveryService.php:79`, `app/Config/shipper-matching.php`.

## Người 3 — phần còn phải làm

| ID | Ưu tiên | Loại | Việc còn lại và tiêu chí hoàn tất |
|---|---|---|---|
| N3-01 | P0 | Tích hợp Storage / Người 2 | Bàn giao và nối contract file thật vào KYC/bằng chứng: file ID, owner, purpose, order, MIME/size/checksum. `PrivateStorageService::reference()` đã có nhưng luồng Người 2 chưa dùng để xác minh. Không cần viết lại adapter hoặc tạo bucket/provider mới. |
| N3-02 | P0 | Retention / chốt policy | Thống nhất vòng đời ảnh: Người 2 ghi evidence_retention_days=90, còn Person3Worker đặt private_files EVIDENCE hết hạn 7 ngày sau khi đơn đóng. Hai bảng/module hiện không bảo đảm cùng thời hạn. Chốt một chính sách rồi bảo đảm object và metadata được giữ/xóa thống nhất, kể cả ticket/tranh chấp. |
| N3-03 | P0 | Nghiệm thu liên module | Test notification từ offer/assignment/pickup/delivery/return với event thật của Người 2; worker receipt/idempotency/retry phải đúng. Nhận bàn giao OfferCreated/OrderDelivering sau khi Người 2 bổ sung; không phát sự kiện thay chủ module. |
| N3-04 | P0 | Nghiệm thu tài chính / email | Chạy luồng giao thành công → earning theo policy → OTP ngân hàng → reservation PROCESSING → Admin đối soát SUCCEEDED/PAID. Nghiệm thu chứng từ và chống trùng; PROCESSING không phải đã chuyển tiền. Refund phải giữ đúng finance-review hold; cần chốt cách xử lý trường hợp tranh chấp/refund ảnh hưởng earning. |
| N3-05 | P1 | UI / tích hợp Người 2 | Loại bỏ hoặc chuyển hướng biểu mẫu ngân hàng demo trong Shipper Account sang panel bank-account thật của person3.js. Backend + form OTP thật đã có; tránh hai điểm vào với hành vi khác nhau. Rà soát unread, cursor, chat/file/ticket và refresh trên VI/EN/mobile. |
| N3-06 | P1 | Vận hành / nghiệm thu Storage | Xác nhận Render đã nhập đúng biến Supabase và worker retention chạy, test upload/download quyền thật từ UI KYC/evidence/chat/ticket. Kiểm tra Supabase từ CLI đã thành công trong phiên trước, nhưng chưa chứng minh mọi luồng UI đang dùng nó. Payout/FX thực hiện thủ công theo scope hiện hành, không cần tích hợp chuyển tiền tự động. |

Bằng chứng chính: `app/Services/PrivateStorageService.php`, `app/Services/Person3Worker.php:63`, `app/Services/ShipperFinanceService.php`, `public/assets/js/person3.js:44`, `app/Views/shipper/pages/account.php:36`, `docs/SUPABASE-STORAGE.md`.

## Trình tự làm tiếp đề xuất

1. Người 1 xác minh thanh toán thật và IPN; Người 2 + Người 1 đưa matching worker vào lịch.
2. Người 2 + Người 3 nối file thật và chặn tham chiếu giả trước khi nghiệm thu giao hàng/KYC.
3. Người 2 bổ sung event còn thiếu; Người 1 nối Customer xác nhận hoàn/GPS; Người 3 nghiệm thu notifications.
4. Cả nhóm chốt giới hạn file, TTL offer, retention, điểm/thu nhập; Người 1 ban hành policy, Người 2/3 dùng chung.
5. Chạy một kịch bản thực ba vai trò: tạo đơn → trả tiền → offer/accept → ảnh thật/GPS → giao thành công → notification/email → earnings → payout manual. Chạy thêm nhánh hoàn/refund, tranh chấp accept/cancel và callback lặp.
6. Rà soát mọi màn VI/EN và mobile, sau đó cập nhật checklist cũ theo bằng chứng. Các công cụ/báo cáo kiểm tra local không tự push nếu người dùng chưa yêu cầu.

## Giới hạn bằng chứng kiểm thử

Đợt test gần nhất ngày 11/11/2026: 29 bộ local PASS, 30 kiểm tra template PASS; Customer browser 68 kiểm tra PASS. Các test dùng fixture/TEMP tables và provider giả; không chứng minh giao dịch LIVE, inbox thật, lịch worker hosting hoặc toàn bộ màn hình ba tài khoản. Hai test UI cũ dùng selector/offer demo vẫn cần cập nhật hoặc thay bằng test E2E phù hợp source hiện hành.

Tài liệu BACKEND-SCOPE và PERSON2/PERSON3 còn các mô tả lịch sử (Google, filesystem, chưa có API, demo của module đã được thay). Không dùng ô unchecked trong tài liệu cũ làm bằng chứng chưa code; dùng bảng việc còn lại ở trên và kiểm tra source tương ứng.
