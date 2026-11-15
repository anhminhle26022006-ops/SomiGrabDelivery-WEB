"""Câu hỏi bổ sung theo đúng 15 nhóm chức năng trong phân công UI."""
sources.update({
 'addrcrud':('CustomerAddressService: all/get/save/archive/lockOwner','app/Services/CustomerAddressService.php'),
 'avatar':('AccountAvatarService: validate/save/content/remove','app/Services/AccountAvatarService.php'),
 'reset':('PasswordResetService: request/reset','app/Services/PasswordResetService.php'),
 'verify':('EmailVerificationService: sendCode/verifyCode','app/Services/EmailVerificationService.php'),
 'switch':('API switch-workspace','public/api/auth/switch-workspace.php'),
 'logout':('API logout: cookie và session_destroy','public/api/auth/logout.php'),
 'i18n':('Customer i18n: t/translateTree/setLanguage','public/assets/js/customer/i18n.js'),
 'publicview':('Public view: trang chủ và footer','app/Views/shared/public.php'),
 'bank':('ShipperBankService: request/confirm/current','app/Services/ShipperBankService.php'),
 'availability':('ShipperAvailabilityService: status/turnOn/turnOff','app/Services/ShipperAvailabilityService.php'),
 'application':('ShipperApplicationService: validate/assertReviewable','app/Services/ShipperApplicationService.php'),
 'matching':('MatchingService: dispatchNearby/candidates','app/Services/MatchingService.php'),
 'return':('DeliveryReturnService: fail/shipperReturned/customerConfirmed','app/Services/DeliveryReturnService.php'),
 'adminpolicy':('AdminPolicyService: createPricing/activate/earning/score','app/Services/AdminPolicyService.php'),
 'adminfront':('Admin UI: danh sách, bộ lọc và cursor','public/assets/js/admin-live.js'),
 'shipperfront':('Shipper UI: lịch sử và tải thêm','public/assets/js/shipper-active.js'),
 'receipt':('CustomerDeliveryReceiptService: detail','app/Services/CustomerDeliveryReceiptService.php'),
})

def dq(g, topic, title, short, code, test, refs, follow):
    q(g,title,short,refs,follow,'Chuyên sâu theo chức năng')
    qa[-1].update(topic=topic,code=code,test=test)

# Anh Thư — Public và dùng chung
dq(1,'Public và dùng chung','Đăng nhập thành công thì server lưu gì? Vì sao chỉ chuyển trang là chưa đủ?',
 'Server phải xác minh mật khẩu, tình trạng tài khoản và thiết lập session. Chuyển sang đường dẫn Customer chỉ là điều hướng giao diện; API vẫn kiểm session, role và permission ở mỗi lần gọi.',
 'Mở public/api/auth/login.php: truy vấn tài khoản → password_verify → session_regenerate_id(true) → thiết lập thông tin phiên. Mở _kernel.php để nối với bước somi_require_active_session và kiểm workspace.',
 'Thử URL workspace khi chưa đăng nhập; thử gọi API Admin bằng session Customer. Kết quả phải bị chặn, dù sửa HTML để hiện nút Admin.',
 'login kernel access','Nếu ai đó sửa localStorage thành role=admin thì có lên Admin được không? → Không được dùng dữ liệu browser làm căn cứ cấp quyền.')
dq(1,'Public và dùng chung','Quên mật khẩu khác đổi mật khẩu khi đang đăng nhập thế nào?',
 'Quên mật khẩu dùng token gửi qua email để chứng minh quyền truy cập tài khoản. Đổi mật khẩu trong tài khoản yêu cầu cơ chế xác thực hiện tại theo endpoint. Cả hai đều tạo hash mới; không gửi lại mật khẩu cũ cho người dùng.',
 'PasswordResetService::reset kiểm token 64 ký tự hex, mật khẩu 8–72 theo kiểm tra strlen trong code; tra SHA-256 của token, khóa user và token, kiểm chưa dùng/chưa hết hạn. Update password_changed_at và consume các token chưa dùng trong transaction.',
 'Thử token sai, token hết hạn, token dùng lần hai và hai request reset cùng token. Không được cập nhật mật khẩu hai lần từ một token đã consume.',
 'reset p1','Vì sao DB lưu token_hash thay vì token gốc? → Giảm khả năng dùng trực tiếp token nếu dữ liệu DB bị đọc lộ.')
dq(1,'Public và dùng chung','Chuyển Customer sang Shipper có phải tạo tài khoản mới không? Có chuyển sang Admin được không?',
 'Một user có thể có nhiều vai trò. Switch-workspace dùng cùng user hiện tại và tạo/dùng profile tương ứng; Shipper vẫn phải qua điều kiện ứng tuyển, xét duyệt và eligibility. Endpoint này chỉ chấp nhận CUSTOMER hoặc SHIPPER, không dùng để tự cấp Admin.',
 'switch-workspace.php lấy user từ session, kiểm target theo whitelist, khóa user ACTIVE; dùng INSERT ... ON CONFLICT cho role/profile. Nhánh Shipper kiểm hồ sơ cũ hoặc validate đơn ứng tuyển rồi đặt trạng thái xét duyệt phù hợp.',
 'Gửi target=ADMIN phải bị từ chối; chuyển workspace nhiều lần không được tạo user mới hoặc role trùng. Hồ sơ PENDING không được nhận việc chỉ vì đã vào workspace.',
 'switch application shipper','Vì sao switch role không đồng nghĩa shipper đã APPROVED? → Quyền truy cập workspace và quyền nhận giao hàng là hai lớp kiểm tra.')
dq(1,'Public và dùng chung','Đăng xuất, đa ngôn ngữ và footer nằm ở đâu; có phải chỉ xóa localStorage?',
 'Đăng xuất cần hủy phiên ở server và cookie phiên. Đa ngôn ngữ đổi nhãn/định dạng hiển thị; mã trạng thái trong DB vẫn giữ ổn định. Footer công khai có logo, copyright, Terms và Privacy; workspace không nhất thiết có cùng footer.',
 'logout.php yêu cầu POST, làm rỗng $_SESSION, hết hạn cookie và session_destroy. i18n.js có lookup/t/translateTree/setLanguage. shared/public.php có footer.site-footer; không nhầm với sidebar-footer Admin hoặc footer của popup.',
 'Logout rồi gọi API bảo vệ phải mất quyền; đổi Việt–Anh kiểm nút, lỗi và ngày/tiền; thử màn hình hẹp để footer/form không tràn.',
 'logout i18n publicview','Nếu bấm Back sau logout vẫn thấy giao diện cũ? → Kiểm API/session hiện tại; HTML cũ hoặc cache không chứng minh còn quyền truy cập dữ liệu.')

# Anh Thư — Tài khoản và địa chỉ
dq(1,'Tài khoản và địa chỉ','CustomerAddressService::save vừa thêm vừa sửa bằng cách nào?',
 'Hàm dùng id tùy chọn: không có id thì INSERT, có id thì kiểm địa chỉ thuộc khách rồi UPDATE. Dữ liệu được chuẩn hóa/kiểm bởi DeliveryInput::address trước khi ghi. Không lấy customer_id do browser tự khai.',
 'save(customerId,data,id=null) gọi lockOwner; nếu sửa thì get(customerId,id) lọc owner và archived_at. SQL dùng dữ liệu đã được validator giới hạn và bind giá trị; kết quả RETURNING trả bản ghi vừa lưu.',
 'Thử tạo mới, sửa đúng địa chỉ của mình, dùng ID người khác, ID đã archive và tọa độ ngoài miền. Nhánh sai ownership phải bị chặn, không cập nhật nhầm bản ghi.',
 'addrcrud kernel','Tại sao có customer_id trong cả câu kiểm tra và UPDATE? → Bảo vệ điều kiện ownership ngay tại thao tác ghi.')
dq(1,'Tài khoản và địa chỉ','Hai tab cùng đặt hai địa chỉ mặc định thì có tạo hai default không?',
 'Service tuần tự hóa ghi địa chỉ theo profile Customer, bỏ default cũ khi đặt default mới; constraint DB là lớp chặn cuối. Chỉ dùng checkbox trên UI không ngăn được hai request đồng thời.',
 'CustomerAddressService::lockOwner yêu cầu caller đang trong transaction và SELECT customer_profiles ... FOR UPDATE. save chỉ sau khóa mới đổi is_default của địa chỉ khác và lưu địa chỉ mới.',
 'Gửi đồng thời hai request đặt default cho cùng khách; sau commit kiểm dữ liệu active/default. Với hai khách khác nhau không cần khóa chung toàn bộ bảng.',
 'addrcrud lockdocs','Vì sao khóa profile thay vì chỉ địa chỉ mới? → Hai request có thể chọn hai address_id khác nhau, nhưng cùng tranh chấp default của một owner.')
dq(1,'Tài khoản và địa chỉ','Xóa địa chỉ là DELETE vật lý hay archive? Đơn đã tạo có mất địa chỉ không?',
 'archive đặt archived_at và bỏ is_default, danh sách chỉ đọc địa chỉ chưa archive. Đơn giữ snapshot địa chỉ riêng nên không đổi theo việc sửa/xóa sổ địa chỉ. Đây là cách tách danh bạ đang dùng khỏi lịch sử giao hàng.',
 'Đọc all/get: có archived_at IS NULL. archive khóa owner, gọi get để kiểm quyền rồi UPDATE archived_at=now(), is_default=FALSE. Đọc CustomerOrderService để chỉ vị trí snapshot đơn.',
 'Tạo đơn rồi sửa/archived địa chỉ gốc; mở lại đơn phải giữ thông tin đã chốt. Không khẳng định hệ thống tự chọn default thay thế nếu chưa thấy nhánh đó trong code.',
 'addrcrud customer','Archive khác xóa dữ liệu theo retention/quyền riêng tư thế nào? → Archive chỉ ẩn trong nghiệp vụ đang dùng, không tự chứng minh đã xóa hoàn toàn dữ liệu.')
dq(1,'Tài khoản và địa chỉ','Avatar có dùng cùng giới hạn 5 MiB như bằng chứng giao hàng không?',
 'Không. AccountAvatarService có giới hạn riêng: tối đa 1 MiB, JPEG/PNG/WebP và chiều không quá 1024×1024. Avatar của tài khoản hiện tại được đọc theo user đã xác thực. Không suy giới hạn của một loại upload sang mọi file.',
 'validate(bytes) kiểm độ dài, finfo MIME và getimagesizefromstring. save dùng UPSERT account_avatars, random version và audit; metadata trả URL có v để phân biệt phiên bản; content đọc bytes theo user.',
 'Thử ảnh hợp lệ, PDF đổi đuôi .jpg, ảnh >1 MiB, ảnh vượt 1024 px và đổi avatar hai lần. Không chỉ kiểm thuộc tính accept của input file.',
 'avatar','Vì sao cần version trong URL ảnh? → Giúp URL thay đổi khi nội dung ảnh đổi; không phải token cấp quyền đọc ảnh.')

# Anh Thư — Báo giá và tạo đơn
dq(1,'Báo giá và tạo đơn','Chọn tọa độ trên bản đồ rồi có bỏ qua kiểm vùng phục vụ được không?',
 'Không. Tọa độ từ browser là đầu vào không tin cậy. Báo giá kiểm lại hai điểm qua reverse geocoding, vùng phục vụ và tuyến xe máy; địa chỉ hiển thị đẹp chưa đủ để xác nhận nằm trong vùng nhận đơn.',
 'GeoapifyMeteoPricingProvider::resolve gọi requireServiceArea cho pickup/delivery rồi routing mode motorcycle. Kiểm country_code, khu vực, distance/time và giới hạn trước khi trả context tính giá.',
 'Thử tọa độ ngoài TP.HCM nhưng sửa nhãn địa chỉ thành TP.HCM; thử hai điểm không có đường xe máy. Backend phải xử lý theo tọa độ/provider thay vì tin label.',
 'geo address quote','Khoảng cách Haversine dùng tìm shipper có thay route distance tính giá không? → Không, hai khoảng cách phục vụ hai mục tiêu khác nhau.')
dq(1,'Báo giá và tạo đơn','Khách sửa cân nặng hoặc địa chỉ sau khi đã lấy quote thì cần làm gì?',
 'Cần lấy lại quote phù hợp với dữ liệu mới trước khi tạo đơn. API tạo đơn nhận quote_id, không lấy giá hiển thị tùy ý. Dùng quote cũ có thể chốt thông tin cũ, nên UI phải thể hiện rõ quote đang áp dụng và yêu cầu tính lại khi đầu vào thay đổi.',
 'Mở orders.php: nhánh tạo chỉ cho quote_id và header Idempotency-Key. Đối chiếu OrderQuoteService và CustomerOrderService để chỉ điều kiện owner, expiry và dữ liệu lấy từ quote; không giả định browser sửa được snapshot bằng thêm trường total.',
 'Lấy quote A, đổi pickup/weight thành B, quan sát UI; tạo request thêm total_charge_minor phải bị whitelist từ chối. Thử dùng quote người khác hoặc hết hạn.',
 'orders quote customer','Nếu muốn sửa đơn sau khi đã trả tiền? → Cần nghiệp vụ điều chỉnh riêng; không sửa quote/snapshot cũ trực tiếp.')
dq(1,'Báo giá và tạo đơn','Tại sao tiền và ID trong JSON thường là chuỗi?',
 'ID lớn có thể vượt miền số nguyên chính xác của JavaScript; tiền/thập phân cần tính chính xác và có đơn vị rõ. Backend quy ước _id và _minor dạng chuỗi, BCMath/NUMERIC cho phép tính tiền chính xác. Chuỗi không tự mang ý nghĩa đúng đơn vị, vẫn phải kiểm currency.',
 'somi_wire chuyển các field hậu tố _id/_minor sang string. DistanceWeightPrice dùng bcsub/bcmul/bcadd/bccomp. VND minor là đồng; USD minor là cent; không cộng hai currency vào một tổng.',
 'Thử ID lớn, phí có phần lẻ và biên làm tròn. Ví dụ tiền 0.1+0.2 theo float không nên dùng để quyết toán. Giải thích ceil tại điểm kết toán thay vì làm tròn từng thành phần tùy ý.',
 'kernel price p1','Có thể dùng parseFloat để hiển thị rồi gửi ngược quyết toán không? → Giá quyết toán phải lấy server; chuyển số hiển thị không thay dữ liệu chuẩn.')
dq(1,'Báo giá và tạo đơn','Danh mục hàng cấm/hạn chế có phải chỉ là nhãn giao diện?',
 'Danh mục là cấu hình nghiệp vụ với mã, tên Việt/Anh, restriction_type và active. UI giúp người dùng chọn; backend phải kiểm danh mục đủ điều kiện trong luồng quote/order. Không coi input select là bằng chứng đã ngăn gửi hàng không hợp lệ.',
 'AdminService::category kiểm mã [A-Z0-9_], restriction ALLOWED/RESTRICTED/PROHIBITED và active boolean. Đối chiếu OrderQuoteService/DeliveryInput khi giải thích chính xác cách category được dùng; không tự thêm quy tắc hàng hạn chế chưa có trong code.',
 'Thử gửi category_id không tồn tại, danh mục inactive, danh mục PROHIBITED và request sửa tay. Đọc phản hồi thực tế rồi mô tả hành vi, không chỉ nhìn danh sách dropdown.',
 'admin quote p1','Vì sao dùng category_code ổn định bên cạnh tên Việt/Anh? → Đổi tên hiển thị không nên đổi định danh nghiệp vụ.')

# Anh Thư — Thanh toán và sau tạo đơn
dq(1,'Thanh toán và sau tạo đơn','Browser mở được success_url thì em có hiện thanh toán thành công ngay không?',
 'Chỉ hiển thị đã thanh toán khi dữ liệu server xác nhận. Redirect có thể bị truy cập trực tiếp hoặc xảy ra trước lúc IPN xử lý xong. Trạng thái chờ cần cho phép làm mới/đối soát, không đặt PAID từ URL query.',
 'SePayPaymentProvider::form tạo success/error/cancel URL; verifyCallback kiểm secret và paidEvent xác thực giao dịch. PaymentService xử lý ledger/order; UI phải đọc customer/payments.php hoặc trạng thái order.',
 'Mở return URL khi chưa trả tiền và làm chậm IPN: không được mở matching như đơn đã PAID. So sánh payment/order và giao dịch provider khi nghiệm thu thật.',
 'sepay payment p1','Nếu khách đóng tab ngay sau trả tiền? → IPN là server-to-server nên không phụ thuộc tab còn mở.')
dq(1,'Thanh toán và sau tạo đơn','Customer xác nhận nhận hàng hoàn trước Shipper được không?',
 'Không. customerConfirmed chỉ chấp nhận chủ đơn RETURNING và delivery đã có returned_at do shipper báo trả. Đơn chỉ hoàn tất RETURNED sau hai bên xác nhận. Xác nhận UI không đồng nghĩa tiền đã hoàn ngay.',
 'DeliveryReturnService::customerConfirmed kiểm ownership, khóa order và delivery; nếu thiếu returned_at trả RETURN_NOT_HANDED_OVER. finishIfBothConfirmed đặt RETURNED, closed_at, kết thúc assignment và phát OrderReturned.',
 'Khách xác nhận quá sớm, người khác xác nhận, retry sau RETURNED và hai request đồng thời. Kiểm event không phát trùng và refund chạy theo luồng riêng.',
 'return p1','Ai xác nhận tiền đã hoàn? → Trạng thái refund SUCCEEDED sau quy trình đối soát, không phải nút xác nhận nhận hàng.')
dq(1,'Thanh toán và sau tạo đơn','Danh sách đơn đã phân trang kiểu 1, 2, 3 chưa? Bộ lọc áp dụng ở đâu?',
 'Danh sách Customer hiện dùng cursor và nút Tải thêm đơn, chưa có nút số trang 1,2,3. Màn này tải 50 đơn/lần; thông báo UI nói bộ lọc áp dụng cho các đơn đã tải khi còn cursor. Không nên trả lời đã có trang đánh số hoặc lọc toàn DB trên mọi màn.',
 'server.js: allOrders là helper riêng; màn listOrders dùng firstPage limit=50, cursor next_before_id, data-more-orders và append/dedup. CustomerOrderService::list nhận before/limit/status, ORDER BY order_id DESC, LIMIT limit+1.',
 'Có >50 đơn: kiểm lần tải đầu, bấm tải thêm không trùng, hết cursor thì ẩn nút. Chọn filter khi đơn phù hợp nằm ở đợt chưa tải để giải thích đúng giới hạn UI.',
 'front customer','Nếu giảng viên bắt nút 1,2,3? → Đề xuất bổ sung phân trang đánh số/đếm tổng; không gọi nút Tải thêm là trang số.')
dq(1,'Thanh toán và sau tạo đơn','Có màn đánh giá là đã có backend gửi review thật chưa?',
 'Chưa thể kết luận chỉ từ màn review hoặc bảng order_reviews. Phải chứng minh được hành động submit gọi endpoint nào, endpoint kiểm chủ đơn/trạng thái/shipper/rating ra sao, ghi DB và phản hồi thế nào. Hiện đã thấy Admin đọc order_reviews; không lấy việc đọc bảng làm bằng chứng đã hoàn thiện toàn bộ luồng khách gửi đánh giá.',
 'Mở app/Views/customer/pages/review.php và tìm handler/call API tương ứng trong JS; AdminConsoleService::reviews đọc rating/comment. Khi bảo vệ chỉ trình bày đã hoàn chỉnh nếu truy được đủ chuỗi UI → API → service → INSERT/UPDATE.',
 'Kịch bản nghiệm thu cần tạo review, reload vẫn còn; gửi lại không tăng trùng; rating ngoài 1–5, đơn người khác hoặc chưa giao phải bị chặn theo policy. Đây là tiêu chí kiểm, không phải khẳng định các nhánh đã có.',
 'adminui readme','Nếu chỉ dùng dữ liệu demo/localStorage? → Nói rõ là giao diện/demo hoặc phần cần hoàn thiện, không nhận là API thật.')

# Anh Thư — Tương tác
dq(1,'Tương tác','Khách có thể chat với Shipper của bất kỳ đơn nào không?',
 'Chat gắn với order và quan hệ được phép. Customer phải là chủ đơn; Shipper đọc theo assignment được phép, nhưng gửi mới cần assignment hoạt động và đơn chưa đóng. Quen biết user_id hoặc đoán order_id không tạo quyền chat.',
 'InteractionService::chat khóa order, gọi requireConversation; nhánh gửi kiểm DELIVERED/RETURNED/CANCELLED và trả CHAT_CLOSED. requireConversation phân biệt quyền đọc lịch sử và quyền ghi của Shipper.',
 'Đổi order_id sang đơn người khác; shipper cũ gửi sau khi assignment kết thúc; gửi sau đóng đơn; đọc tin đã hết retention. Kết quả phải đúng quyền/trạng thái.',
 'chat access','Vì sao đọc được lịch sử không có nghĩa được tiếp tục gửi? → Quyền đọc và ghi có điều kiện riêng.')
dq(1,'Tương tác','Chat kèm file có thể gửi file_id bất kỳ không?',
 'Không. File phải do người gửi sở hữu, đúng order, purpose CHAT, chưa gắn message, chưa xóa/chưa hết hạn; mỗi tin tối đa 5 file. Upload thành công chưa có nghĩa người bên kia đã được cấp quyền xem draft.',
 'chat chuẩn hóa/sắp file_ids, chặn trùng; SELECT private_files ... FOR UPDATE kiểm owner/order/purpose/message_id. Sau tạo message mới gắn file với message và đổi hạn giữ theo luồng.',
 'Thử file_id người khác, file KYC, file của đơn khác, file đã dùng, gửi 6 file, lặp một file hai lần. Retry cùng client_key và đúng file list phải lấy lại kết quả cũ.',
 'chat storage p3','Tại sao kiểm owner/purpose ở server dù UI chỉ liệt kê file của mình? → Request có thể bị sửa ngoài UI.')
dq(1,'Tương tác','Ticket khác chat thế nào? Có bắt buộc phải có đơn không?',
 'Chat phục vụ trao đổi giữa các bên trong đơn; ticket là yêu cầu hỗ trợ có danh mục, chủ đề, mô tả và lịch sử xử lý. Ticket ACCOUNT có thể không gắn order. Gửi ticket dùng client_key để tránh trùng do retry.',
 'InteractionService::tickets kiểm category DELIVERY/PAYMENT/ACCOUNT/OTHER, subject tối đa 200 và description 8000; nếu có order_id thì kiểm quyền. Trả chi tiết theo opened_by và opened_role.',
 'Tạo ticket tài khoản không order, ticket đơn đúng/sai owner, subject quá dài, retry cùng key khác nội dung; mở ticket ID người khác phải bị chặn.',
 'chat p3','Ticket không gắn đơn có cho Admin xem mọi chat của người gửi không? → Không, quyền chat phải có ticket liên quan đúng order.')
dq(1,'Tương tác','Đánh dấu tất cả thông báo đã đọc có bỏ sót thông báo vừa đến không?',
 'API dùng through_id để chỉ cập nhật đến thông báo người dùng đã nhìn thấy. Thông báo mới hơn vẫn chưa đọc. Query còn ràng buộc user_id và recipient_role để workspace này không đọc nhầm thông báo workspace khác.',
 'InteractionService::notifications nhánh all yêu cầu all=true, read boolean, through_id hợp lệ; UPDATE notification_id<=through_id. Nhánh từng thông báo kiểm owner/role và trả 404 nếu không phù hợp.',
 'Tải đến ID 100, có ID 101 đến trước lúc bấm all-read: ID101 phải còn unread. Thử ID người khác và đổi workspace rồi kiểm unread_count.',
 'chat p3','Vì sao dedup_key thêm recipientRole? → Tránh gộp nhầm hai thông báo nghiệp vụ dành cho hai workspace của cùng user.')

# Nhi — Ứng tuyển và tài khoản
dq(2,'Ứng tuyển và tài khoản','KYC submission khác KYC approval ở đâu?',
 'Submission là người dùng gửi thông tin/tài liệu để xét; approval là Admin có quyền kiểm hồ sơ và ra quyết định. Shipper không được tự gửi verification_status=APPROVED để bỏ qua duyệt. Hồ sơ tồn tại không đồng nghĩa đủ điều kiện làm việc.',
 'ShipperApplicationService::validate chuẩn hóa thông tin, assertApplicant kiểm điều kiện nộp, save lưu hồ sơ. AdminService::reviewShipper gọi assertReviewable khi duyệt; ShipperEligibility/Domain Shipper xét trạng thái đã duyệt.',
 'Nộp thiếu giấy tờ, sai vehicle hoặc hồ sơ đang chờ; sửa request tự APPROVED; gửi lại sau NEEDS_INFO. Phải tách kết quả ứng tuyển với khả năng nhận offer.',
 'application admin shipper','Người dùng vừa là Customer vừa là Shipper có nộp trùng users không? → Cùng danh tính, các role/profile bổ sung.')
dq(2,'Ứng tuyển và tài khoản','turnOn và turnOff thay đổi dữ liệu gì? Tắt availability có bỏ đơn đang giao không?',
 'turnOn kiểm tọa độ và eligibility rồi lưu availability cùng vị trí/thời điểm mới. turnOff tắt nhận việc mới và hủy offer đang chờ; không phải API hoàn thành/hủy assignment đang giao. Shipper còn phải xử lý đơn hiện tại theo quy trình riêng.',
 'ShipperAvailabilityService::status trả can_work, blockers, location_fresh. turnOn gọi assertCanWork rồi UPDATE profile; turnOff gọi cancelPendingOffers, tắt cờ và audit khi có thay đổi.',
 'Bật khi KYC chưa duyệt/điểm thấp, bật thiếu GPS, tắt khi có offer pending, tắt khi đang giao. Kiểm assignment hiện tại không tự biến mất chỉ vì availability=false.',
 'availability shipper','Availability=true nhưng location_fresh=false thì có chắc được chọn matching không? → Không, matching còn kiểm độ mới vị trí và nhiều điều kiện khác.')
dq(2,'Ứng tuyển và tài khoản','Đổi tài khoản ngân hàng tại sao cần mật khẩu và OTP email?',
 'Đây là dữ liệu nhận tiền nhạy cảm. Request kiểm mật khẩu hiện tại, email đã xác minh, rate limit và tạo mã có hạn. Confirm đúng OTP mới tạo bản ngân hàng mới; xác minh OTP không đồng nghĩa Admin đã duyệt thông tin ngân hàng.',
 'ShipperBankService::request có password_verify, client_key/request_hash, cooldown 60 giây, giới hạn yêu cầu; OTP 6 số lưu hash, hạn 5 phút. confirm khóa yêu cầu, kiểm expiry/attempts, thay tài khoản và tạo review_status=PENDING.',
 'Sai mật khẩu, OTP sai 5 lần, mã hết hạn, retry confirm sau thành công, đổi payload với key cũ, OTP của request khác. Không in OTP thật vào log hay response.',
 'bank p3','Vì sao một số nhánh trả object lỗi thay vì throw? → Để bộ đếm thử sai được commit thay vì rollback cùng lỗi.')
dq(2,'Ứng tuyển và tài khoản','Email OTP ngân hàng thành công thì ngân hàng đã kiểm đúng chủ tài khoản chưa?',
 'Không. OTP chứng minh người đang thao tác kiểm soát kênh xác minh của SOMI; không phải API xác minh tên chủ tài khoản của ngân hàng. Code tạo bản ngân hàng PENDING để xét duyệt tiếp. Cần tách confirmed/verified_at và review_status.',
 'Trong confirm, token_hash dùng password_verify; payload được giải mã server rồi ghi account_number_enc bằng EncryptedPayload. Không sửa tên chủ tài khoản thành APPROVED ở frontend chỉ vì confirmed=true.',
 'Xác nhận OTP rồi mở lại màn ngân hàng và kiểm review_status. Thử tài khoản demo bị chặn thao tác chuyển và dữ liệu cũ không giải mã được; phải yêu cầu nhập lại theo luồng hiện tại.',
 'bank finance','Có được khẳng định số tài khoản luôn che khi trả về cho chủ tài khoản không? → Đọc endpoint cụ thể; current() service có cả số đầy đủ và masked, không suy từ nhãn UI.')

# Nhi — Nhận việc
dq(2,'Nhận việc','Matching chọn shipper theo tiêu chí nào? Có phải ai online cũng thấy mọi đơn?',
 'Matching xét account/KYC/giấy tờ/điểm, availability, vị trí còn mới, trong bán kính, không có assignment ACTIVE và không phải Customer của đơn. Lọc ứng viên gần điểm lấy hàng trước; thời điểm pickup cũng cần phù hợp theo schedule.',
 'MatchingService::dispatchNearby tìm đơn đã PAID/SEARCHING_SHIPPER và dùng PickupSchedule::dueSql. candidates dùng điều kiện eligibility và khoảng cách; đọc config thay vì học thuộc bán kính không kiểm chứng.',
 'Hai shipper: một gần nhưng location cũ, một mới vị trí nhưng ngoài bán kính; kiểm ai được mời. Thử cùng user vừa tạo đơn vừa online shipper.',
 'matching accept','Vì sao khoảng cách tìm ứng viên có thể là đường chim bay còn báo giá dùng route? → Matching cần lọc gần; giá cần quãng đường đi thực tế.')
dq(2,'Nhận việc','Reject offer có hủy đơn của Customer hoặc trừ điểm không?',
 'Reject chỉ từ chối lời mời của shipper đó. Đơn có thể tiếp tục tìm người khác theo matching; không đồng nghĩa hủy đơn/refund. Theo release hiện tại, từ chối hoặc để offer hết hạn không bị trừ điểm như hủy assignment do lỗi.',
 'offers.php gọi OfferService::reject với offer_id/note; nhánh nhận chính thức nằm ở accept-offer.php. Matching tránh mời lại một số trạng thái đã phản hồi; policy nêu rõ phân biệt reject/expiry với cancellation.',
 'Từ chối một offer, kiểm đơn còn trạng thái tìm người và điểm không đổi. Thử từ chối offer người khác hoặc offer đã được nhận; kiểm trạng thái/error thật.',
 'offers matching policy','Vì sao lưu REJECTED thay vì xóa offer? → Cần lịch sử phản hồi và tránh mời lại sai quy tắc.')
dq(2,'Nhận việc','201 và 200 của accept-offer khác nhau thế nào? Retry sau đã giao xong có luôn 200 không?',
 '201 là vừa tạo assignment; 200 là lấy lại kết quả accept thành công khi assignment còn ACTIVE. Không hứa mọi retry về sau đều 200: result() tìm assignment ACTIVE, nếu đã kết thúc thì trả xung đột. Idempotency vẫn phụ thuộc nghiệp vụ hiện tại.',
 'OfferAcceptService::accept nếu offer ACCEPTED gọi result(...,false). result WHERE assignment_status=ACTIVE; không thấy thì OFFER_NOT_PENDING. Đây là nhánh cần đọc vượt qua comment “gọi lại thành công”.',
 'Accept mới → retry ngay → hoàn thành đơn → retry cùng offer_id. Phân biệt kết quả từng thời điểm và cách UI tải lại trạng thái thay vì hiện nhận đơn lần nữa.',
 'accept acceptapi','Nếu request đầu commit nhưng mất response, request thứ hai cần làm gì? → Dùng offer_id cũ và xử lý kết quả/trạng thái server.')
dq(2,'Nhận việc','Một shipper nhận hai đơn khác nhau cùng lúc thì khóa mỗi order có đủ không?',
 'Khóa mỗi order chưa bảo vệ tài nguyên chung là shipper. Service còn khóa profile shipper và kiểm assignment ACTIVE. Vì vậy hai request nhận hai đơn khác nhau vẫn phải tuần tự hóa điều kiện bận/rảnh của cùng người.',
 'accept khóa order rồi ShipperEligibility::profile(...,true), khóa offer và kiểm order_assignments trước khi insert. Sau nhận còn hủy các pending offer khác của chính shipper để giải phóng lời mời.',
 'Cùng shipper gửi accept hai offer thuộc hai order đồng thời. Sau commit chỉ được có một assignment ACTIVE; request còn lại phải thất bại đúng điều kiện.',
 'accept lockdocs','Tại sao cần đọc lại offer sau lock? → Dữ liệu đọc trước lock có thể đã bị request khác thay đổi.')

# Nhi — Giao nhận
dq(2,'Giao nhận','PICKUP, START_DELIVERY, ARRIVED và COMPLETE có phải cùng một update status không?',
 'Không. PICKUP và START_DELIVERY chuyển trạng thái nghiệp vụ theo thứ tự. ARRIVED ghi mốc đến nơi trong delivery. COMPLETE dùng endpoint riêng và yêu cầu trạng thái, arrival, bằng chứng. Tách action giúp server áp điều kiện cho từng bước.',
 'deliveries.php match action PICKUP/START_DELIVERY/ARRIVED sang DeliveryService. complete-delivery.php gọi DeliveryCompletionService::delivered. Order::TRANSITIONS không có status ARRIVED.',
 'Bấm COMPLETE trước PICKUP, gửi ARRIVED sai đơn, START_DELIVERY trước nhận hàng, gọi action không thuộc whitelist. Không cho browser gửi status tùy ý để đi tắt.',
 'deliveryapi complete order','Nếu nhãn giao diện “Đã đến nơi” xuất hiện thì DB order.status là gì? → Có thể vẫn IN_DELIVERY, arrival là timestamp riêng.')
dq(2,'Giao nhận','Ảnh giao hàng có cần đúng đơn, đúng loại và đúng thời điểm?',
 'Có. DELIVERY_PHOTO chỉ gắn khi IN_DELIVERY và đã ARRIVED; RETURN_PHOTO chỉ khi RETURNING. File phải qua PrivateStorageService::reference đúng owner/purpose/order. Service giới hạn ảnh JPEG/PNG/WebP, 5 MiB và tối đa 5 mỗi loại.',
 'DeliveryCompletionService::addEvidence khóa active context, lấy metadata file từ server rồi ghi delivery_evidence. Các tham số metadata browser truyền không được tin; service thay bằng mime/size/checksum từ file đã kiểm.',
 'Gắn ảnh đơn khác, ảnh trước arrival, PDF, file hết hạn, ảnh thứ 6 hoặc retry cùng object. Kiểm trạng thái và số evidence thực tế.',
 'complete storage','Vì sao checksum khác tên file? → Checksum nhận diện nội dung để kiểm toàn vẹn; tên file người dùng có thể trùng hoặc bị đổi.')
dq(2,'Giao nhận','Hoàn thành giao hàng cập nhật những gì ngoài orders.status?',
 'Đặt thời điểm completed/closed, delivery.delivered_at, assignment COMPLETED/ended_at, phát OrderDelivered, xử lý phục hồi điểm và settlement. Các thay đổi liên quan cần cùng transaction để tránh đơn đã giao nhưng assignment vẫn bận hoặc thiếu căn cứ tài chính.',
 'Mở delivered(): lockActive → điều kiện IN_DELIVERY/arrived/ảnh → transition → UPDATE orders/deliveries/assignment → event → ShipperScoreService::recovery → ShipperFinanceService::settleOrder.',
 'Tạo lỗi có kiểm soát trong DB test trước commit để kiểm rollback; retry complete sau commit cần được xử lý như trạng thái đã đổi. Không thử gây lỗi trên production.',
 'complete finance policy','Email có gửi trực tiếp bên trong hàm delivered không? → Event bàn giao cho consumer/worker email, không thấy gọi Resend trực tiếp ở đây.')
dq(2,'Giao nhận','Vị trí GPS cũ hoặc người dùng không cấp quyền vị trí thì xử lý sao?',
 'UI cần báo không lấy được vị trí, không dựng tọa độ giả. Availability/matching đánh giá độ mới GPS; backend kiểm tọa độ và quan hệ được phép. Theo dõi bản đồ không đồng nghĩa vị trí chính xác tuyệt đối hoặc luôn thời gian thực.',
 'ShipperAvailabilityService::status có location_fresh và last_location_at; turnOn nhận latitude/longitude. ShipperLocationService giữ logic cập nhật/đọc riêng; ngưỡng tuổi vị trí lấy từ config.',
 'Từ chối quyền GPS, mất mạng, tọa độ ngoài miền, gửi location quá cũ và Customer truy cập vị trí đơn không thuộc mình. UI phải phản ánh thiếu dữ liệu thay vì giữ điểm cũ như vị trí mới.',
 'availability gps matching','Có WebSocket không? → Chỉ gọi là realtime/WebSocket khi chỉ được implementation; cập nhật qua API không tự là WebSocket.')

# Nhi — Sự cố và hoàn hàng
dq(2,'Sự cố và hoàn hàng','Không giao được do người nhận vắng thì phải làm gì?',
 'Dùng nhánh giao thất bại/hoàn hàng, ghi reason_code và note phù hợp; không tự nhảy DELIVERED hoặc coi là Customer đã nhận. DeliveryReturnService có các lý do chuẩn và nhánh OTHER bắt buộc ghi chú.',
 'DeliveryReturnService::FAILURE_REASONS gồm NO_ANSWER/REFUSED/WRONG_ADDRESS/RECIPIENT_ABSENT/PACKAGE_ISSUE/OTHER. fail khóa active context, chuyển RETURNING, lưu lý do và return_started_at, phát OrderReturning.',
 'Thử lý do OTHER thiếu note, lý do ngoài danh sách và gọi từ trạng thái không hợp lệ. Phân biệt endpoint hoàn hàng với yêu cầu dừng cần Admin duyệt theo policy.',
 'return policy','Người nhận vắng có tự chứng minh lỗi Shipper không? → Không; kết luận lỗi/điểm phải có quy trình và bằng chứng.')
dq(2,'Sự cố và hoàn hàng','Shipper báo đã trả hàng rồi có giải phóng assignment ngay không?',
 'Chưa. RETURNING còn giữ assignment ACTIVE cho tới khi cả hai phía đủ xác nhận. Shipper cần ảnh RETURN_PHOTO; Customer chỉ xác nhận sau khi shipper đã ghi returned_at. Khi đủ, hệ thống mới RETURNED và kết thúc assignment.',
 'shipperReturned kiểm status/ảnh, ghi returned_at bằng COALESCE; finishIfBothConfirmed kiểm hai timestamp rồi chuyển trạng thái, đặt closed_at và assignment RETURNED, phát OrderReturned.',
 'Chỉ shipper xác nhận → vẫn RETURNING; khách quá sớm → lỗi; đủ hai phía → RETURNED; retry không tạo thêm hoàn tất. Nếu thiếu ảnh phải EVIDENCE_REQUIRED.',
 'return complete','Vì sao không phát OrderReturned ngay khi shipper bấm? → Event có thể kích hoạt refund/finance trước khi khách xác nhận nhận lại hàng.')
dq(2,'Sự cố và hoàn hàng','Tự khai “khách quan” có tránh bị trừ điểm sau lấy hàng không?',
 'Không. Lý do shipper khai là đầu vào để xét; Admin kiểm chứng và kết luận lỗi. Mọi yêu cầu dừng sau lấy hàng cần quy trình chờ/duyệt theo release. Không để client gửi deduction_points hoặc shipper_at_fault để tự quyết điểm của mình.',
 'Xem shipper/cancellation.php và contract policy: request dùng reason_code/reason/client_key; Admin quyết định APPROVED/REJECTED cùng evidence và shipper_at_fault. Điểm được service đọc policy và chống lặp theo assignment.',
 'Retry cùng request/key; đổi nội dung dưới key cũ; gửi hai yêu cầu chờ cho cùng assignment; Admin duyệt thiếu chứng cứ. Kiểm không trừ hai lần.',
 'policy','Nếu phát hiện lỗi sau khi đã payout? → Giữ audit/finance review; không khẳng định tự thu hồi tiền đã chuyển.')
dq(2,'Sự cố và hoàn hàng','Tìm lại shipper sau hủy trước pickup có cần khách trả tiền lại không?',
 'Theo release được mô tả, giải phóng assignment trước pickup và matching lại trên cùng đơn đã thanh toán; không tự tạo refund hoặc charge lần hai. Shipper đã hủy không được nhận lại cùng đơn theo quy tắc hiện tại.',
 'SHIPPER-POLICY-WORKFLOWS mô tả CANCELLED_BEFORE_PICKUP, rematch và điểm nếu lỗi shipper. Đối chiếu payment vẫn được giữ và order trở lại luồng tìm shipper; không nhân bản order để tìm người khác.',
 'Hủy trước pickup rồi kiểm order_id/payment_id giữ nguyên, assignment cũ kết thúc, lời mời mới đúng ứng viên. Thử shipper cũ nhận lại; kiểm phí không bị thu hai lần.',
 'policy accept matching','Nếu sau pickup thì có rematch tương tự không? → Không suy diễn; quy trình sau pickup phải bảo vệ việc ai đang giữ hàng và nhánh RETURNING.')

# Nhi — Sau giao và hỗ trợ
dq(2,'Sau giao và hỗ trợ','Thu nhập 80% tính ở frontend hay backend? Làm tròn thế nào?',
 'Backend tính theo policy hiệu lực, không lấy số UI nhân 0.8 làm sổ cái. Finance dùng PostgreSQL NUMERIC và ceil một lần; lưu policy_snapshot để giải thích lịch sử. 80% là release được duyệt, vẫn phải có policy hiệu lực phù hợp trên DB.',
 'ShipperFinanceService::accrue chọn delivered_at hoặc customer_return_confirmed_at, policy theo mốc đó, kiểm payment/assignment/refundHold rồi INSERT shipper_earnings ON CONFLICT(order_id).',
 'Đơn có tổng cước tạo phần lẻ khi nhân %, policy thay giữa hai ngày, thiếu policy và gọi accrue hai lần. Kiểm không ghi hai earning cho một order.',
 'finance policy','Nếu tổng 26.001 VND với rate 80% thì minh họa ceil(20.800,8)=20.801; không ceil từng phép nhân trung gian.')
dq(2,'Sau giao và hỗ trợ','Lịch sử giao và earning có cùng một nguồn dữ liệu không?',
 'Không. Lịch sử dựa assignment/delivery đã kết thúc; earning là sổ thu nhập chỉ phát sinh khi đủ payment/policy/hoàn/tranh chấp. Một đơn có lịch sử không đồng nghĩa đã có earning hoặc payout thành công.',
 'DeliveryService::history trả items và next_before_id theo assignment_id; shipper-active.js append/dedup khi tải thêm. Finance::listing đọc shipper_earnings, join payout và totals nhóm currency/payout_status.',
 'Đơn DELIVERED thiếu bank hoặc policy: so sánh lịch sử với màn thu nhập. Thử >50 bản ghi; không báo đã có nút số trang khi UI mới có tải thêm.',
 'shipperfront finance p2','Vì sao không cộng VND và USD thành một số? → Khác đơn vị tiền; muốn quy đổi cần quote/tỷ giá có nguồn và snapshot.')
dq(2,'Sau giao và hỗ trợ','Điểm uy tín có giống rating sao từ Customer không?',
 'Không. Reliability score dùng policy điểm cho eligibility; customer_rating_average/count là dữ liệu đánh giá khác. Không lấy một đánh giá 1 sao để tự trừ 20 điểm nếu không có policy/implementation quy định. Cần chỉ đúng trường và service khi giải thích.',
 'Domain Shipper đọc reliability_score cho workBlockers; AdminConsoleService có cả reliability_score và customer_rating_average/count. Release policy mô tả điểm theo sự kiện giao/hủy, không đồng nhất với số sao.',
 'Mở profile có cả điểm và rating; giải thích từng đơn vị. Kiểm biên 59.99/60 theo kiểu dữ liệu và policy, trần 100, không tự bật availability sau phục hồi.',
 'shipper adminui policy','Có màn đánh giá không chứng minh đã có đầy đủ endpoint cập nhật rating; cần truy đúng luồng submit.')
dq(2,'Sau giao và hỗ trợ','Shipper đã giao xong còn được đọc chat/tạo ticket không?',
 'Có thể được đọc lịch sử chưa hết retention theo assignment COMPLETED/RETURNED; gửi chat mới thì cần điều kiện active/đơn chưa đóng. Shipper có thể mở ticket cho assignment đã kết thúc để phản ánh vấn đề sau giao theo contract.',
 'InteractionService::requireConversation nhánh đọc cho phép ACTIVE/COMPLETED/RETURNED; nhánh ghi theo AccessControl. tickets cho Shipper kiểm assignment lịch sử; replies kiểm ticket owner/role và trạng thái CLOSED.',
 'Sau complete: thử đọc tin cũ, gửi mới và mở ticket theo đơn; mỗi hành động có quyền khác. Ticket CLOSED không nhận phản hồi mới; không mất ticket chỉ vì giao đã xong.',
 'chat p3','Một shipper khác chưa từng nhận đơn có đọc được chat lịch sử không? → Không; phải có quan hệ assignment đúng quyền.')

# Minh Anh — Tổng quan vận hành
dq(3,'Tổng quan vận hành','Dashboard là dữ liệu thật hay số cố định? Em chứng minh thế nào?',
 'Phải đi từ widget đến JS fetch, endpoint rồi query DB; số xuất hiện trên màn hình không tự chứng minh dữ liệu thật. Khi có filter/kỳ thống kê cần nêu rõ phạm vi. Nên phân biệt dashboard đang chạy với các view/demo cũ còn trong repo.',
 'Bắt đầu từ admin-live.js và AdminConsoleService, tìm hàm render/read tương ứng. Kiểm route entry thực tế ở app/Views/admin/live.php hoặc index.php; không chỉ mở file cùng tên dashboard rồi kết luận nó đang được dùng.',
 'So số liệu một trạng thái/đơn cụ thể giữa danh sách và dashboard trên cùng kỳ; đổi filter, refresh. Chỉ dùng dữ liệu test được phép, không sửa production để làm đẹp số.',
 'adminfront adminui boot','Nếu số hai màn khác nhau? → Kiểm filter, giới hạn, mốc thời gian và định nghĩa chỉ tiêu trước khi kết luận query sai.')
dq(3,'Tổng quan vận hành','Admin tìm tài khoản theo tên/email/role thế nào? Dữ liệu có được bind không?',
 'Tìm kiếm là query có filter được kiểm hợp lệ; value bind vào SQL. Role phải thuộc CUSTOMER/SHIPPER/ADMIN. Người dùng có thể nhiều role nên lọc theo quan hệ user_roles thay vì mặc định một cột role duy nhất.',
 'AdminService::users dùng EXISTS với user_roles/roles, ILIKE cho tên/email/phone, before_id và LIMIT 100. Đọc endpoint đang dùng để biết có bổ sung priority/cursor nào; UI không tự quyết quyền đọc.',
 'Tìm tên có dấu, chuỗi rỗng, dấu nháy, role không hợp lệ, nhiều role và >100 user. Query không lộ password_hash hoặc dữ liệu nhạy cảm ngoài contract.',
 'admin adminfront','ILIKE khác LIKE ra sao trong PostgreSQL? → ILIKE so khớp không phân biệt hoa/thường theo ngữ cảnh collation, không tự bỏ dấu tiếng Việt.')
dq(3,'Tổng quan vận hành','Admin có nút trang 1,2,3 chưa? Trang tiếp dựa vào gì?',
 'UI hiện có Trang tiếp và Trang đầu theo cursor; chưa phải thanh số trang. Backend trả next_before_id hoặc trường cursor tương ứng, frontend gửi lại before_id. Khi thay filter cần reset cursor để không tiếp tục trang cũ trong bộ dữ liệu mới.',
 'admin-live.js page(count) dựng nút first/next. Nhánh users/orders/complaints/reviews lấy next từ API. Một số danh sách còn có before_priority/next_priority, nên không giản lược mọi màn thành order_id duy nhất.',
 'Danh sách nhiều đợt, đổi keyword/status giữa lúc đang trang sau, về trang đầu, hết dữ liệu. Kiểm filter/cursor nhất quán và không lặp bản ghi.',
 'adminfront adminui','Có nên cam kết cursor luôn cho phép nhảy trực tiếp trang 20? → Không; muốn nhảy theo số cần cơ chế bổ sung.')
dq(3,'Tổng quan vận hành','Chi tiết đơn cần hiển thị những mảng dữ liệu nào để hỗ trợ vận hành?',
 'Không chỉ status: cần chủ đơn, địa chỉ snapshot, phí/payment, lịch sử assignment, mốc giao, bằng chứng, refund/payout và yêu cầu hỗ trợ nếu đủ quyền. Mỗi phần giúp giải thích ai đang giữ hàng và tiền đang ở đâu.',
 'Đọc AdminConsoleService/AdminFinanceService qua các endpoint đang dùng ở admin-live.js. Tách order_status, payment_status, refund.status, assignment_status và payout.status; không dùng một badge “thành công” đại diện tất cả.',
 'Một đơn giao xong nhưng payout PROCESSING, một đơn CANCELLED có payment đến muộn, một đơn RETURNING chưa khách xác nhận. Màn chi tiết phải diễn giải đúng từng lớp trạng thái.',
 'adminui adminfront p1 p3','Vì sao order DELIVERED không đồng nghĩa shipper đã nhận chuyển khoản? → Hoàn tất giao và đối soát chi tiền là hai mốc khác nhau.')

# Minh Anh — Tài khoản và duyệt
dq(3,'Tài khoản và duyệt','Admin khóa tài khoản có những rào nghiệp vụ nào?',
 'Phải có permission account.suspend, trạng thái mục tiêu hợp lệ và lý do. Service chặn tự đổi trạng thái bản thân, mở lại CLOSED tại đây, ACTIVE khi chưa đủ xác minh và tình huống làm mất Admin hoạt động cuối cùng.',
 'AdminService::accountStatus lấy advisory lock, khóa user FOR UPDATE; ghi users, account_status_history và AuditService khi trạng thái thực sự đổi. Đây không phải UPDATE users đơn lẻ từ form.',
 'Tự khóa mình, khóa Admin cuối, mở tài khoản chưa verify email/phone, mở CLOSED, lặp trạng thái hiện có và hai thao tác đồng thời. Kiểm history/audit tương ứng.',
 'admin access','Ẩn nút khóa bản thân trên UI đã đủ chưa? → Chưa, request thủ công vẫn phải bị backend chặn.')
dq(3,'Tài khoản và duyệt','Duyệt KYC chạm những bảng nào? APPROVED có tự bật online không?',
 'Duyệt cập nhật hồ sơ shipper, giấy tờ và tài liệu liên quan; ghi người/thời điểm xét và audit. Availability được đặt FALSE, nên approval không tự bật nhận đơn. Shipper phải chủ động bật và thỏa các điều kiện còn lại.',
 'AdminService::reviewShipper kiểm PENDING/NEEDS_INFO, identity tồn tại, assertReviewable khi APPROVED; UPDATE shipper_documents, shipper_profiles và user_identity_documents. REJECTED/NEEDS_INFO cần note.',
 'Duyệt hồ sơ thiếu identity, duyệt lần hai khi không pending, reject thiếu note, approved nhưng điểm thấp và hồ sơ bank chưa duyệt. Không suy KYC approval thành bank approval.',
 'admin application availability','Vì sao cần transaction cho nhiều bảng? → Tránh profile APPROVED nhưng tài liệu/identity còn trạng thái không nhất quán.')
dq(3,'Tài khoản và duyệt','Duyệt quốc tịch có ảnh hưởng ngôn ngữ và đơn cũ không?',
 'Khi APPROVED, service cập nhật nationality_code và app_language VN→vi, quốc gia hỗ trợ khác→en cho user. Không cập nhật lại snapshot đơn lịch sử. Request đã được xét không được xét lại bằng cùng thao tác.',
 'AdminService::reviewNationality khóa request, yêu cầu PENDING, khóa user rồi update theo decision; ghi reviewed_by/reviewed_at và audit. country() đối chiếu resources/countries.json.',
 'Approve VN và một mã khác, mã quốc gia không hỗ trợ, xét request đã REJECTED, hai Admin xét đồng thời. Kiểm đơn cũ giữ currency/snapshot theo dữ liệu đã chốt.',
 'admin p1','Ngôn ngữ giao diện có phải bằng quốc tịch ở mọi thời điểm không? → Phân biệt app_language được lưu và lựa chọn i18n UI theo implementation.')
dq(3,'Tài khoản và duyệt','Khóa/mở tài khoản khác xóa tài khoản ở điểm nào?',
 'SUSPENDED giữ dữ liệu, lịch sử và quan hệ giao dịch nhưng chặn hoạt động theo guard; ACTIVE cho phép trở lại nếu đủ điều kiện. CLOSED là trạng thái khác, accountStatus không dùng để mở lại. Không được gọi khóa là đã xóa dữ liệu cá nhân.',
 'accountStatus chỉ chấp nhận ACTIVE/SUSPENDED và chặn CLOSED; SessionGuard/AccessControl kiểm account ACTIVE. Dữ liệu orders/payments/audit không bị DELETE theo thao tác khóa.',
 'Khóa user đã có session rồi gọi API bảo vệ, xem lịch sử giao dịch có giữ; mở lại nhưng KYC/điểm chưa đạt thì không nhận việc được.',
 'admin kernel access','Có cần xóa cascade toàn bộ payment khi khóa user không? → Không; đó sẽ phá lịch sử/đối soát, ngoài nghiệp vụ khóa.')

# Minh Anh — Cấu hình nghiệp vụ
dq(3,'Cấu hình nghiệp vụ','Tạo policy giá xong có áp dụng ngay không?',
 'Không. createPricing tạo phiên bản inactive; activate là thao tác riêng kiểm hiệu lực. Điều này tách khâu soạn cấu hình khỏi đưa vào tính giá. Đơn cũ vẫn giữ snapshot; policy giá và policy earning là các bộ quy tắc riêng.',
 'AdminPolicyService::createPricing kiểm fields, currency, limits/bands; lấy advisory lock để tạo config_version. activate khóa và kiểm effective, tắt policy active cùng currency rồi bật policy chọn và audit.',
 'Tạo bản mới nhưng chưa activate, activate bản ngoài hiệu lực, hai Admin activate đồng thời và activate bản USD. Kiểm không dùng policy VND cho quote USD.',
 'adminpolicy quote','Vì sao không UPDATE policy đang dùng cho mọi đơn? → Làm mất khả năng truy vết phiên bản và giá lịch sử.')
dq(3,'Cấu hình nghiệp vụ','Khung giờ giá qua nửa đêm được kiểm tra thế nào?',
 'Phải phủ đủ tuần mà không chồng lấn hoặc bỏ trống. Khoảng qua nửa đêm được tách/chuẩn hóa theo giây của tuần. Nếu start=end, giờ không hợp lệ hoặc khoảng phủ không liên tục thì validator từ chối.',
 'AdminPolicyService::validateTimeBands đổi HH:MM:SS thành giây, day=null áp cho cả tuần; end<=start kéo sang ngày sau, tách khi vượt cuối tuần; sort segments rồi kiểm start bằng cursor và cuối đúng 604800 giây.',
 'Thử 22:00–06:00, hai khoảng đè nhau, trống 1 giây, Chủ nhật qua thứ Hai và start=end. Không chỉ kiểm từng ô giờ độc lập.',
 'adminpolicy','Vì sao kiểm thiếu khung giờ cũng quan trọng như trùng khung giờ? → Báo giá ở thời điểm không có hệ số sẽ không có cấu hình rõ ràng.')
dq(3,'Cấu hình nghiệp vụ','Admin đặt hệ số mưa hoặc giới hạn cân nặng tùy ý được không?',
 'API có ràng buộc: weather bands cần các trạng thái NORMAL/LIGHT_RAIN/RAIN, không trùng condition; giới hạn policy không vượt phạm vi dịch vụ 40 km/30 kg. Điều kiện thời tiết nguy hiểm còn có nhánh pause riêng, không chỉ nhân giá cao hơn.',
 'createPricing kiểm max_distance/max_weight và quan hệ included với max; validate multiplier và required weather codes. OpenMeteoWeatherService::current chặn unsafe trước khi quote hoàn tất.',
 'Thử max 31 kg/41 km, thiếu NORMAL, trùng RAIN, multiplier sai kiểu và unsafe weather. Nêu dữ liệu policy nào được phép giảm giới hạn chứ không tự mở rộng vùng.',
 'adminpolicy weather','Có thể đặt multiplier để nhận đơn khi bão không? → Không suy thế; nhánh unsafe/pause độc lập với hệ số giá.')
dq(3,'Cấu hình nghiệp vụ','Đổi policy điểm/thu nhập có reset lịch sử không?',
 'Không. Tạo khoảng hiệu lực mới và đóng khoảng cũ, giữ sự kiện/snapshot đã ghi. Policy điểm có ngưỡng, trần, mức trừ/phục hồi; policy earning có loại tỷ lệ hoặc số cố định theo currency. Không tự hồi tố mọi đơn lịch sử.',
 'AdminPolicyService::earning và score dùng advisory locks khác nhau, kết thúc effective_until bản hiện hành rồi INSERT bản mới. score kiểm lock_threshold không lớn hơn max_score và giữ giá trị tương thích khi caller cũ thiếu field mở rộng.',
 'Đổi rate trước/sau completed_at của hai đơn; kiểm earning chọn đúng thời điểm. Đổi max_score thấp hơn threshold phải bị từ chối; đọc snapshot lịch sử sau chỉnh policy.',
 'adminpolicy finance policy','Vì sao không chỉ có một hàng config rồi ghi đè? → Cần biết quy tắc nào có hiệu lực lúc nghiệp vụ xảy ra.')

# Minh Anh — Tài chính
dq(3,'Tài chính','Đối soát refund cần kiểm những dữ liệu nào để tránh hoàn sai hoặc trùng?',
 'Cần đúng refund đã reserve, payment gốc, phương thức/đích theo quy trình, số tiền, currency, reference và chứng từ hoàn đã thực hiện. Client không tự chọn số tiền trả bất kỳ. Refund không tự thành công chỉ vì Admin gõ một ghi chú.',
 'Đọc admin/refund-reconciliation.php và PaymentService qua contract: original_payment_reference, provider_refund_id, evidence_reference, original_method_confirmed, amount_minor/currency phải khớp; unique references và audit bảo vệ lặp.',
 'Sai 1 đồng, sai currency/payment, chứng từ thiếu, dùng lại bank/provider reference và gửi đồng thời hai lần. Không thử transfer thật chỉ để kiểm UI.',
 'p1 payment','Nút reconcile có tự gọi ngân hàng chuyển tiền không? → Contract hiện tại ghi nhận khoản hoàn đã làm, không khởi tạo chuyển tiền.')
dq(3,'Tài chính','Vì sao earning chưa tạo được payout dù đơn DELIVERED?',
 'Có thể thiếu policy hiệu lực, payment chưa đủ/khác currency, assignment/timestamp không phù hợp, refund/tranh chấp đang hold, ngân hàng chưa đạt hoặc manual mode chưa cấu hình. Đơn đã giao chỉ là một trong nhiều điều kiện.',
 'ShipperFinanceService::accrue kiểm order/payment/assignment/policy/refundHold; settleOrder gọi reserve chỉ khi SOMI_PAYOUT_MODE=manual. Dùng dữ liệu trả về và trạng thái thực tế để tìm nguyên nhân, không sửa PAID bằng tay.',
 'Thiếu bank, pending cancellation, payment mismatch, refund chưa SUCCEEDED và policy không phủ thời điểm hoàn tất. Kiểm worker có thể retry khi dữ liệu đủ mà không tạo trùng earning.',
 'finance policy p3','Tại sao reservation PROCESSING nhưng earning vẫn PENDING? → Chưa có đối soát chứng minh người nhận đã được trả.')
dq(3,'Tài chính','Payout thất bại thì retry thế nào để tránh chuyển hai lần?',
 'Phải xác minh tiền chưa chuyển rồi mới đánh FAILED/RETRY theo contract và evidence. Không retry khoản SUCCEEDED. Mạng timeout của thao tác ngân hàng là trạng thái chưa chắc chắn, cần đối chiếu trước khi chuyển lại.',
 'Contract admin/payouts có action FAILED/RETRY, transfer_not_completed=true, reason/evidence/client_key; tuyến PROCESSING→FAILED→PROCESSING. reconcile cần tham chiếu duy nhất, exact amount/currency và audit.',
 'Retry SUCCEEDED, lặp reference, sai amount, gửi thiếu xác nhận chưa chuyển và timeout sau ngân hàng đã ghi nhận. Tách retry record nội bộ khỏi chuyển tiền bên ngoài.',
 'p3 finance','Có thể bấm retry vô hạn cho nhanh không? → Không; mỗi lần phải theo trạng thái và bằng chứng để không chi trùng.')
dq(3,'Tài chính','Tỷ giá USD/VND lấy từ đâu? Có tự dùng tỷ giá Google không?',
 'Luồng FX contract dùng quote do Admin nhập từ chứng từ provider/ngân hàng với nguồn/reference/evidence, bất biến theo earning. Không tự đoán tỷ giá hoặc cộng lẫn currency. USD checkout hiện khóa; khả năng xử lý FX không chứng minh đã mở thanh toán USD.',
 'ShipperFinanceService::fxQuote kiểm rate dương, precision, earning và nguồn chứng từ. Quy đổi USD cent × rate /100 và ceil theo contract; giữ quote gắn khoản thu nhập để truy vết.',
 'Rate 0/âm, thiếu evidence, quote lại earning đã chốt, overflow và số USD cent nhỏ. Kiểm minor unit trước khi nhân, không lẫn đô với cent.',
 'finance p3 p1','Vì sao không cập nhật mọi khoản theo rate mới mỗi ngày? → Khoản đã chốt phải giữ căn cứ tỷ giá lịch sử.')

# Minh Anh — Sự cố và giám sát
dq(3,'Sự cố và giám sát','Quy trình xử lý ticket từ NEW đến CLOSED cần lưu gì?',
 'Lưu trạng thái, phản hồi/note, người xử lý, thời gian và lịch sử sự kiện để biết ai làm gì. Thực hiện theo action/transition được endpoint hỗ trợ; không tự đặt một status chưa có trong contract. CLOSED không nhận phản hồi mới.',
 'Admin UI gọi console/actions và support/replies.php. InteractionService::replies khóa ticket, kiểm support.manage_all cho Admin, chặn CLOSED khi viết, audit và gửi notification đúng bên còn lại.',
 'User thường gọi Admin ticket API, Admin thiếu permission, phản hồi ticket đóng, retry cùng client_key và nội dung khác. Ticket ACCOUNT không order vẫn xử lý hỗ trợ được.',
 'chat adminfront p3','Trạng thái RESOLVED và CLOSED có giống nhau không? → Không nên gộp; kiểm contract để biết điều kiện cho phản hồi và retention.')
dq(3,'Sự cố và giám sát','Admin xem file KYC/ảnh giao hàng có được dùng URL public không?',
 'File nhạy cảm cần kiểm quyền, mục đích và quan hệ ticket/đơn; KYC có quyền xét duyệt riêng. Không mở bucket public để giải quyết lỗi tải ảnh. Link có hạn không thay kiểm authorization; SOMI kiểm lại trước trả nội dung.',
 'PrivateStorageService và AccessControl quản lý truy cập; admin/files.php liệt kê theo phạm vi, admin-live.js mở preview/download. Inline chỉ loại ảnh hợp lệ, các file khác attachment theo contract; no-store/nosniff.',
 'Copy link sang user khác/hết hạn, file không thuộc ticket, KYC thiếu shipper.verify và chat draft chưa gửi. Không coi có file_id là được tải.',
 'storage access p3 adminfront','Signed URL khác public URL thế nào? → Link có điều kiện/hạn; không phải công khai vĩnh viễn cho mọi người.')
dq(3,'Sự cố và giám sát','Job gửi email ACCEPTED/DONE có chắc người dùng nhận inbox chưa?',
 'Không. Trạng thái nội bộ hoặc provider accepted chỉ nói request được tiếp nhận ở một bước. Email còn có thể chậm/bounce/spam; worker có hạn OTP/reset và không được gửi mã đã hết hạn chỉ để chuyển job xanh.',
 'Xem JobQueue/EmailService và Person1 worker contract về lease, fencing, retry/backoff và payload expiry. Metadata email log không được lộ code, reset URL hoặc encrypted payload của người nhận.',
 'Provider timeout, worker crash sau provider accepted, retry cùng idempotency key và email hết TTL. Tách trạng thái job khỏi delivery cuối cùng trong inbox.',
 'jobs p1 email','Nếu gửi lại sau 30 phút một OTP hạn 5 phút? → Không nên phát lại code cũ; cần yêu cầu xác minh mới theo luồng.')
dq(3,'Sự cố và giám sát','Rà audit/log thế nào khi khách nói đã trả tiền nhưng hệ thống chưa matching?',
 'Theo order→payment→provider invoice→IPN receipt/ledger→event/consumer→matching. Không chỉnh order sang SEARCHING_SHIPPER trước khi xác minh tiền. Mỗi bước cần căn cứ riêng; audit thiếu ở bản cũ cũng chưa đủ kết luận provider không gọi.',
 'Dùng contract PaymentService/SePay, các công cụ acceptance read-only trong docs và Admin logs. Kiểm môi trường sandbox/live, currency/amount, invoice namespace, callback auth và worker; không in secret ra báo cáo.',
 'Các tình huống: redirect sớm hơn IPN, callback sai secret, provider PAID nhưng response mất, event pending và đơn đã CANCELLED trước tiền đến. Nêu cách đối chiếu từng trường hợp.',
 'p1 sepay payment jobs','Nếu tiền đến sau khi đơn hủy? → Giữ đơn hủy và xử lý refund, không tự mở lại giao hàng.')
