# Minh Anh — Người 3

Triển khai trên nhánh MINH-ANH: chat/ticket, notification, private Storage tùy chọn, earnings, payout chuyển khoản thủ công và đối soát. Dùng PostgreSQL, auth/session/RBAC và API kernel hiện có. Không thay Geoapify/Leaflet hoặc email/payment Customer.

## Cài đặt

```powershell
php database/migrate-customer-orders.php --apply
php bin/person3-worker.php --limit=20
```

Migration `20261111_person3` chỉ bổ sung bảng/cột/index/quyền; runner có transaction và version ledger. Chạy trên môi trường test trước hosting. Lập lịch CLI worker mỗi phút cùng môi trường web. Worker Người 1 tiếp tục chạy riêng. Không mở worker qua HTTP. Source mới không tự chạy migration lên Supabase.

Payout được người dùng chọn: **Admin chuyển khoản và đối soát chứng từ**. Đặt `SOMI_PAYOUT_MODE=manual` để worker tạo một khoản PROCESSING cho mỗi earning đủ điều kiện. Không đặt biến này thì vẫn tính earnings nhưng không tạo payout. PROCESSING không có nghĩa đã chuyển tiền. Admin mở Thanh toán để nhập chứng từ sau khi chuyển khoản thực tế, dùng mã khoản chi trả làm tham chiếu nội bộ khi thao tác tại ngân hàng.

Không gọi API ngân hàng/chuyển tiền tự động; không gửi lại lệnh chuyển tiền khi retry. Retry API đối soát với cùng tham chiếu là an toàn; một tham chiếu ngân hàng chỉ dùng cho một payout. Không đổi tài khoản ngân hàng của payout đã tạo, kể cả khi thay ngân hàng/OTP hoặc retry. FAILED và RETRY chỉ là trạng thái xử lý manual; mỗi thao tác yêu cầu client_key, reason, evidence_reference và transfer_not_completed:true. Không retry giao dịch chưa rõ đã ghi nợ hay chưa; không tạo payout thứ hai. Tài khoản ngân hàng phải được xác minh trong `shipper_bank_accounts`; luồng thêm/đổi dùng `shipper/bank-account.php` với mật khẩu hiện tại và OTP gửi qua email queue Người 1. OTP 5 phút, tối đa 5 lần sai, cooldown 60 giây và giới hạn theo user. OTP xác nhận quyền thay đổi nơi nhận tiền, không thay thế kiểm tra chủ tài khoản tại ngân hàng. Contract `account_number_enc`: bytea chứa UTF-8 của `EncryptedPayload::seal(["account_number"=>"..."])`, giữ số 0 đầu. Khóa dùng SOMI_MESSAGE_ENCRYPTION_KEY hoặc fallback identity key hiện có. Admin dùng `GET admin/payouts.php?payout_id=...` xem ngân hàng của đúng reservation; mỗi lần xem có audit PAYOUT_BANK_READ, không ghi số tài khoản trong audit. Dữ liệu mã hóa định dạng khác trả lỗi rõ ràng, không đoán hoặc hiển thị ciphertext.

## API

Base `/public/api/`; session, workspace CUSTOMER/SHIPPER, permission `person3.use`. POST JSON yêu cầu same-origin theo kernel; IDs/money trả dạng chuỗi. Các danh sách tối đa 50 bản ghi, phân trang giảm dần bằng `before_id` và `next_cursor`.

| Endpoint | Phương thức | Contract |
|---|---|---|
| `interactions.php?resource=orders` | GET | Danh sách đơn của Customer hoặc assignment ACTIVE/COMPLETED/RETURNED của Shipper cho bộ chọn đơn. |
| `interactions.php?resource=chat&order_id=...` | GET | `{items,next_cursor}`; Customer chủ đơn hoặc Shipper assignment ACTIVE/COMPLETED/RETURNED. Chỉ nội dung chưa hết retention. Gửi tin mới yêu cầu assignment ACTIVE và đơn chưa đóng. |
| `interactions.php?resource=chat` | POST | `{order_id,body,client_key,file_ids?:[id,...]}` hoặc `{order_id,read_through_id}`. Body ≤4.000 ký tự. Khóa 8–100 ký tự `[A-Za-z0-9_.:-]`; retry dùng cùng khóa và nội dung. Đổi nội dung với khóa cũ →409. Không gửi tin mới sau khi đóng đơn. |
| `interactions.php?resource=tickets` | GET, POST | GET danh sách của người mở; GET `&id=...` trả `{ticket,events,next_cursor}`; cursor chi tiết áp dụng cho lịch sử. POST `{order_id?,category,subject,description,client_key}`. category DELIVERY/PAYMENT/ACCOUNT/OTHER; subject ≤200, description ≤8.000. Bỏ order_id cho ticket tài khoản. Shipper được tạo ticket cho assignment đã hoàn tất. |
| `interactions.php?resource=notifications` | GET, POST | GET `{items,unread_count,next_cursor}`. POST `{notification_id,read:boolean}` hoặc `{all:true,read:boolean,through_id}` để cập nhật tất cả đến ID đã nhìn thấy, không tác động thông báo mới hơn. Không sửa thông báo của người khác. |
| `shipper/earnings.php` | GET | `{items,totals,next_cursor}` của Shipper hiện tại; totals tách currency và payout_status. Không cộng lẫn VND/USD. |
| `admin/payouts.php` | GET, POST | ADMIN + `payout.manage`. GET danh sách. POST `{payout_id,provider_payout_ref,evidence_reference,amount_minor,currency_code:"VND"}` đối soát khoản đã chuyển. Exact amount + unique reference + audit; không phải lệnh chuyển tiền. |
| `storage.php` | GET, POST | GET danh sách file của mình; GET `?file_id=` cấp `{download_query,expires_at}`. POST multipart `{file,purpose,order_id?,ticket_id?}`. Purpose CHAT/TICKET/KYC/EVIDENCE. |
| `storage.php?<download_query>` | GET | Signed link 5 phút, ràng buộc session user; quyền được kiểm tra lại. Nội dung tải xuống qua attachment + nosniff + no-store. |

API Admin ticket/chat giữ nguyên `admin/console.php` và `admin/actions.php`; hỗ trợ ticket tài khoản bằng LEFT JOIN. Ticket tài khoản không có chat theo đơn. Admin đọc chat phải có ticket liên quan và được ghi audit bởi AccessControl.

## Frontend

Customer: report/reports/notifications dùng dữ liệu API thật; màn order/tracking có panel chat và hỗ trợ. Shipper: earnings thay số tiền demo bằng ledger; panel dùng chung cho chat, ticket, thông báo, file, các nút liên hệ/hỗ trợ chuyển tới panel thật. Chức năng giao nhận, availability và matching vẫn là phạm vi Người 2.

Biểu mẫu giữ draft và khóa retry khi gửi lỗi; nội dung người dùng được escape trước khi render. Chọn đơn từ API theo quyền thay vì nhập mã nội bộ. Storage chưa cấu hình trả lỗi rõ ràng, không báo upload thành công giả. UI không polling liên tục; chọn lại tab để làm mới.

## Earnings và payout

Worker tính earnings từ trạng thái DELIVERED/RETURNED đã lưu, payment đủ số tiền/currency, assignment COMPLETED/RETURNED và delivery timestamp; hoàn hàng cần Customer xác nhận. Chọn policy có hiệu lực tại thời điểm hoàn thành; snapshot policy và tiền. Tính NUMERIC tại DB, làm tròn lên một lần, không dùng float. Unique order_id chống ghi sổ hai lần.

Không seed tỷ lệ thu nhập/bồi hoàn; Admin phải tạo policy. Không có policy hoặc chưa đủ dữ liệu giao nhận thì chưa có earning. Refund chưa được chốt quy tắc điều chỉnh thu nhập: giữ khoản bị ảnh hưởng để review, ghi `shipper_finance_reviews` theo event, không tự trừ tiền đã trả. Đơn DELIVERED có refund khác FAILED thì chặn accrual/reserve/reconcile để review. Đơn RETURNED chỉ tính bồi hoàn fixed theo policy sau khi refund RETURN_FEE_DEDUCTION đã SUCCEEDED, đúng gross/currency/phí hoàn snapshot và không còn refund khác chưa xử lý; không chi trước worker refund Người 1. USD dùng `FX_QUOTE` do Admin nhập từ chứng từ ngân hàng/provider: rate VND cho 1 USD, provider, reference, evidence_reference. Quote bất biến theo earning; quy đổi USD cent × rate / 100, ceil một lần bằng NUMERIC. Chưa có quote thì giữ earning, không đoán tỷ giá. Thanh toán USD hiện đã khóa ở Người 1.

Manual adapter dùng reservation unique earning_id; PROCESSING chờ thao tác ngân hàng. Đối soát atomically cập nhật SUCCEEDED + PAID, audit và notification. Lock order → payment → earning/payout. Email không nằm trong worker Người 3.

## Events và worker

Consumer riêng `person3` trong `event_consumer_receipts`, transaction + SKIP LOCKED; notification unique `(user_id,dedup_key)`. Không dùng processed flag của Người 1. Hỗ trợ OrderCreated, PaymentConfirmed, OrderCancelled, ShipperAssigned, OrderPickedUp, OrderDelivering, OrderDelivered, OrderReturning, OrderReturned, RefundSucceeded. `OfferCreated` đọc recipient từ offer PENDING chưa hết hạn trong DB; Người 2 cần phát event này sau insert offer. `RefundRequested/RefundSucceeded` tạo finance review.

Consumer không quyết định trạng thái đơn/thanh toán. Earnings được quét lại nên event đến trước policy hoặc delivery data vẫn có thể được xử lý sau. Worker lưu cursor quét vòng cho từng batch để đơn thiếu policy hoặc bank không chặn các đơn sau; các lần chạy sau thử lại dữ liệu chưa đủ điều kiện. Chưa có hàng đợi payout provider ngoài manual.

## Private Storage tùy chọn

Đặt `SOMI_STORAGE_ROOT` thành đường dẫn tuyệt đối **ngoài project/web root**, có quyền ghi cho PHP và volume bền vững; `SOMI_STORAGE_SIGNING_KEY` là secret ngẫu nhiên ≥32 ký tự. Không commit secret. Adapter filesystem riêng tư không yêu cầu chọn GCS/Supabase Storage; trên hosting nhiều instance phải mount chung volume hoặc thay adapter bằng object storage trước khi bật.

Chấp nhận JPEG/PNG/WebP/PDF, kiểm MIME bằng finfo, tối đa 5 MiB, object key ngẫu nhiên và SHA-256; không phục vụ HTML/SVG. CHAT chỉ người có quyền theo đơn; TICKET/KYC/EVIDENCE mặc định owner-only. Bàn giao Người 2 dùng file_id làm tham chiếu, phải kiểm owner/purpose/order ở service giao nhận/KYC trước liên kết. Admin có quyền storage.read_support có thể đọc file ticket/bằng chứng khi có ticket liên quan; file chat dùng auditAdminChatRead, KYC thêm quyền shipper.verify. Mỗi lần cấp link/tải đều audit. Dialog ticket Admin có danh sách/tải file liên quan. File CHAT được gắn với từng message qua file_ids, tối đa 5 file, phải đúng owner/order/purpose, chưa dùng và còn hạn. Peer chỉ tải được sau khi file đã được chia sẻ trong tin nhắn. Upload CHAT chưa dùng hết hạn sau 24 giờ; DELETE chỉ được hủy upload CHAT chưa dùng. Không tự mở file công khai. Upload filesystem và commit DB không phải distributed transaction: có cleanup orphan >24 giờ khi bật SOMI_STORAGE_PRUNE_ORPHANS=1 trên volume dành riêng cho SOMI, không theo symlink hoặc xóa tên file khác định dạng object key.

Worker đặt retention: chat và file chat một tháng sau `orders.closed_at`; evidence 7 ngày sau đóng đơn; ticket 3 tháng sau `support_tickets.closed_at`; KYC 6 tháng từ quyết định APPROVED/REJECTED trong audit. Cần Người 2 ghi timestamp đóng đơn/quyết định đúng contract. Chat hết hạn bị xóa body; object hết hạn được xóa vật lý và đánh dấu metadata. Metadata tài chính không bị xóa. Retention GPS thuộc Người 2, không xóa dữ liệu GPS tại đây.

## Địa lý bàn giao

Giữ `customer/places.php` (search/reverse), `customer/addresses.php` (CRUD/default) và GeoapifyMeteoPricingProvider (route xe máy cho quote). Xem [ADDRESS-GEOAPIFY-SETUP.md](ADDRESS-GEOAPIFY-SETUP.md). Không nhân đôi geocoding/routing trong module chat. Các key server/browser tách biệt, key browser giới hạn domain hosting. Nghiệm thu hosting thật cần điện thoại: tiles, tìm địa chỉ, ghim, back/forward, mất mạng/quota, domain key; Chrome fixture local không chứng minh quota/key hosting.

## Kiểm thử

`php tests/person3_backend.php`: schema session-local TEMP, search_path pg_temp/pg_catalog, không fixture vào schema somi, không gọi ngân hàng/email. PHP cần PDO PostgreSQL, BCMath, mbstring, finfo, OpenSSL.

`node tests/person3_browser.cjs`: Playwright Chrome, fixture HTTP hoàn toàn cục bộ; kiểm XSS, read/unread, account ticket, draft/retry key và responsive 390px. Ảnh trong tests/.runtime (ignored). Cần Playwright trong NODE_PATH.

`python tests/person3_http.py`: khởi động/dừng PHP trên loopback, kiểm tra 401/403/405; không đăng nhập hoặc ghi DB. Đặt SOMI_TEST_PHP nếu PHP không nằm tại C:/xampp/php/php.exe.

Kết quả kiểm tra workspace 11/11/2026: 112 backend Người 3, 15 Chrome fixture, 24 HTTP và 23 kiểm tra Storage multipart thật (local); hồi quy 37 Admin và 74 Người 1 đạt. Kiểm tra Geoapify autocomplete/maps và auth frontend hiện có cũng đạt. PHP/JS thay đổi không có lỗi cú pháp; git diff --check đạt. Kết quả không thay thế nghiệm thu hosting/provider thật.

Chưa áp dụng migration thật hoặc gửi tiền/file lên hosting. Nghiệm thu thực tế phải chạy sau migration, cấu hình volume, scheduler, policy và phối hợp Người 2. Không dùng kết quả fixture để khẳng định chuyển tiền hoặc upload thật đã nghiệm thu.

## API hoàn thiện thêm ngày 11/11/2026

| Endpoint | Phương thức | Contract |
|---|---|---|
| `shipper/bank-account.php` | GET | Tài khoản hiện hành, số tài khoản che bớt; không trả ciphertext hay đầy đủ số tài khoản. |
| `shipper/bank-account.php` | POST | REQUEST: `{action:"REQUEST",bank_code,bank_name,account_holder,account_number,current_password,client_key}` → `{request_id,expires_at,delivery_mode:"EMAIL_QUEUE"}`. CONFIRM: `{action:"CONFIRM",request_id,code}` → `{bank_account_id,confirmed:true}`. OTP/mật khẩu sai trả lỗi và vẫn lưu bộ đếm. |
| `support/replies.php` | GET, POST | GET `?ticket_id=&before_id=`; POST `{ticket_id,body,client_key}`. Người mở ticket hoặc ADMIN + support.manage_all; ticket CLOSED không nhận phản hồi. Admin đọc/ghi có audit; thông báo gửi theo bên còn lại. |
| `geo/places.php` | POST | CUSTOMER/SHIPPER, quyền person3.use, same-origin, 60 request/phút/user. Dùng cùng GeoapifyAddressService như Customer, không giữ transaction khi gọi provider. `autocomplete/geocode`: `{action,input,language?}`; `reverse`: `{action,latitude,longitude,language?}`; `route`: `{action,pickup:{latitude,longitude},delivery:{latitude,longitude}}` → geometry, distance_meters, duration_seconds, provider, mode. |
| `admin/payouts.php` | POST | FX_QUOTE: `{action:"FX_QUOTE",earning_id,rate,provider,reference,evidence_reference}`; rate dương, 8 chữ số thập phân, nguồn VND/USD, bất biến, kiểm overflow. |
| `admin/payouts.php` | POST | FAILED/RETRY: `{action:"FAILED"|"RETRY",payout_id,client_key,reason,evidence_reference,transfer_not_completed:true}`. Chỉ PROCESSING→FAILED→PROCESSING; SUCCEEDED không được retry. |
| `admin/payouts.php?resource=earnings&currency_code=USD` | GET | Earning chưa có payout vẫn tra được để ghi chứng từ FX; cursor before_id theo earning_id. |
| `storage.php?reference_file_id=&purpose=KYC|EVIDENCE&order_id=` | GET | Shipper lấy tham chiếu file của mình để Người 2 liên kết nghiệp vụ. EVIDENCE bắt buộc đúng order và assignment. Service Người 2 gọi PrivateStorageService::reference trong transaction của họ, không tin reference do browser gửi tự do. |
| `storage.php?file_id=` | DELETE | Owner hủy CHAT upload chưa gắn message; worker xóa vật lý. Không xóa bằng chứng/KYC/file đã dùng tùy ý. |

Migration bổ sung `20261111_person3_complete` được runner xếp sau migration Người 3 ban đầu, nên không sửa lịch sử database đã áp dụng. Template BANK_ACCOUNT_CHANGE được thêm vào EmailService chung, không tạo worker email riêng.

Kiểm thử upload thật (local, không phải hosting): `python tests/person3_upload_http.py`. Server loopback dùng TEMP tables, private volume tempfile tự xóa; kiểm multipart, MIME, kích thước, ownership, download hash, retention và orphan cleanup. Không gửi file lên dịch vụ bên ngoài. Bộ browser đã chạy lại thành công ngoài sandbox sau một lần Chrome sandbox timeout.

### Vận hành sự kiện lỗi

`GET admin/person3-jobs.php?before_id=` trả metadata lỗi theo event, không lộ payload. ADMIN cần `person3.manage_jobs`. `POST {event_id,reason}` đưa FAILED về PENDING, ghi audit; gọi lại khi DONE/PENDING không tạo công việc trùng. Worker backoff 30 giây tăng dần đến 1 giờ, tối đa 8 lần rồi FAILED, không chặn sự kiện tiếp theo. KYC retention lấy mốc quyết định APPROVED/REJECTED từ audit, không dùng thời điểm sửa hồ sơ về sau.

Đặc tả import được: [person3-openapi.json](person3-openapi.json), OpenAPI 3.1. Unsafe requests yêu cầu same-origin; JSON write mặc định 64 KiB; file multipart tối đa 5 MiB (hosting cần upload_max_filesize/post_max_size phù hợp). Phiên workspace Admin và user được tách quyền ở API kernel, mọi ID từ browser đều phải kiểm quyền lại.
