(() => {
  "use strict";

  const $ = (selector, root = document) => root.querySelector(selector);
  const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];
  const money = (amount) => new Intl.NumberFormat("vi-VN", { style: "currency", currency: "VND", maximumFractionDigits: 0 }).format(amount);
  const navigate = (page, params = {}) => {
    const query = new URLSearchParams({ page, ...params });
    window.location.href = `?${query.toString()}`;
  };

  const translations = {
    en: {
      signin: "Sign in", send: "Send a Package", become: "Become a Shipper",
      badge: "Flexible Delivery Network", hero1: "Deliver More.", hero2: "Earn on Your Time.",
      herodesc: "Somi connects customers with trusted part-time shippers, making every delivery simple, flexible, and transparent.",
      explore: "Explore how Somi works", network: "Delivery Network",
      navHow: "How It Works", navCustomers: "For Customers", navShippers: "For Shippers", navPricing: "Pricing",
      mobileAuth: "Sign in / Create account",
      shipperRole: "Shipper account", personalInfo: "Personal information", vehicleInfo: "Vehicle information",
      supportCenter: "Support center", chat: "Chat", signout: "Sign out",
      accountLabel: "Account", cancelAccount: "Cancel account"
    },
    vi: {
      signin: "Đăng nhập", send: "Gửi hàng", become: "Trở thành người giao hàng",
      badge: "Mạng lưới giao hàng linh hoạt", hero1: "Giao hàng dễ dàng.", hero2: "Kiếm thêm theo cách của bạn.",
      herodesc: "Somi kết nối khách hàng với người giao hàng bán thời gian đáng tin cậy, giúp mọi chuyến giao đơn giản, linh hoạt và minh bạch.",
      explore: "Khám phá cách Somi hoạt động", network: "Mạng lưới giao hàng",
      navHow: "Cách hoạt động", navCustomers: "Dành cho khách hàng", navShippers: "Dành cho người giao", navPricing: "Bảng giá",
      mobileAuth: "Đăng nhập / Tạo tài khoản",
      shipperRole: "Tài khoản Shipper", personalInfo: "Thông tin cá nhân", vehicleInfo: "Thông tin phương tiện",
      supportCenter: "Trung tâm hỗ trợ", chat: "Trò chuyện", signout: "Đăng xuất",
      accountLabel: "Tài khoản", cancelAccount: "Hủy tài khoản"
    }
  };


  // Translate visible frontend copy only when Vietnamese is selected.
  // This layer updates leaf text nodes, labels, placeholders and accessible names;
  // the English interface remains the original source copy.
  const viText = {
    "Welcome to Somi": "Chào mừng đến với SOMI",
    "Delivery that fits your day.": "Giao hàng phù hợp với nhịp sống của bạn.",
    "Sign in to send a package or manage your delivery work with Somi.": "Đăng nhập để gửi hàng hoặc quản lý công việc giao hàng cùng Somi.",
    "Simple. Flexible. Transparent.": "Đơn giản. Linh hoạt. Minh bạch.",
    "← Back to website": "← Quay lại trang chủ",
    "Sign in": "Đăng nhập",
    "Create account": "Tạo tài khoản",
    "Choose a workspace": "Chọn không gian đăng nhập",
    "Send and track deliveries": "Gửi và theo dõi đơn hàng",
    "Manage delivery work": "Quản lý công việc giao hàng",
    "Open admin console": "Mở bảng điều hành",
    "Admin": "Quản trị viên",
    "Admin access key": "Key quản trị",
    "Enter your admin key": "Nhập key quản trị",
    "Demo key:": "Key demo:",
    "Welcome back": "Chào mừng bạn quay trở lại",
    "Create your Somi account": "Tạo tài khoản Somi",
    "Enter your phone number or email to continue.": "Nhập số điện thoại hoặc email để tiếp tục.",
    "Complete the demo steps to preview account setup.": "Hoàn thành các bước demo để xem quy trình tạo tài khoản.",
    "I want to continue as": "Tôi muốn tiếp tục với vai trò",
    "Customer": "Khách hàng",
    "Shipper": "Người giao hàng",
    "Phone number or email": "Số điện thoại hoặc email",
    "Password": "Mật khẩu",
    "Forgot password?": "Quên mật khẩu?",
    "Enter your password": "Nhập mật khẩu",
    "STEP 1 OF 5": "BƯỚC 1/5",
    "Full name": "Họ và tên",
    "Your full name": "Nhập họ và tên",
    "Phone number": "Số điện thoại",
    "Phone number with country code": "Số điện thoại kèm mã quốc gia",
    "Nationality": "Quốc tịch",
    "Date of birth": "Ngày sinh",
    "Gender": "Giới tính",
    "Select": "Chọn",
    "Male": "Nam",
    "Female": "Nữ",
    "Identity document type": "Loại giấy tờ định danh",
    "National ID": "CCCD / Căn cước công dân",
    "Passport": "Hộ chiếu",
    "Other foreign identity document": "Giấy tờ định danh nước ngoài khác",
    "Identity document number": "Số giấy tờ định danh",
    "Letters and numbers": "Chữ và số",
    "The number is encrypted before it is stored. Shipper documents require Admin review.": "Số giấy tờ được mã hóa trước khi lưu. Giấy tờ Shipper cần Admin duyệt.",
    "Phone verification code": "Mã xác minh điện thoại",
    "For the local demo, find the code in C:\\xampp\\apache\\logs\\error.log.": "Trong bản demo local, xem mã trong C:\\xampp\\apache\\logs\\error.log.",
    "Email verification code": "Mã xác minh email",
    "Check your inbox and spam folder. The code expires in 5 minutes.": "Kiểm tra hộp thư đến và thư rác. Mã hết hạn sau 5 phút.",
    "New accounts must verify both their phone number and email address.": "Tài khoản mới cần xác minh cả số điện thoại và email.",
    "Enter your full name.": "Vui lòng nhập họ tên.",
    "Enter the phone number with country code, for example +84901234567.": "Nhập số điện thoại kèm mã quốc gia, ví dụ +84901234567.",
    "Enter a valid date of birth.": "Vui lòng nhập ngày sinh hợp lệ.",
    "Choose a gender.": "Vui lòng chọn giới tính.",
    "Identity document number must contain 4–40 letters or digits.": "Số giấy tờ phải gồm 4–40 chữ cái hoặc chữ số.",
    "Enter a valid email address.": "Vui lòng nhập email hợp lệ.",
    "Password must be 8–72 characters.": "Mật khẩu phải dài 8–72 ký tự.",
    "Passwords do not match.": "Mật khẩu xác nhận không khớp.",
    "Accept the Terms of Service and Privacy Policy to continue.": "Vui lòng đồng ý Điều khoản dịch vụ và Chính sách quyền riêng tư.",
    "Enter the six-digit phone code.": "Nhập mã xác minh điện thoại gồm 6 chữ số.",
    "Enter the six-digit email code.": "Nhập mã xác minh email gồm 6 chữ số.",
    "Email or phone number is already registered.": "Email hoặc số điện thoại đã được đăng ký.",
    "Email code is incorrect.": "Mã email không chính xác.",
    "Phone code is incorrect.": "Mã điện thoại không chính xác.",
    "Email code has expired. Request a new code.": "Mã email đã hết hạn. Hãy yêu cầu mã mới.",
    "Phone code has expired. Request a new code.": "Mã điện thoại đã hết hạn. Hãy yêu cầu mã mới.",
    "Please wait before requesting another email code.": "Vui lòng chờ trước khi yêu cầu mã email mới.",
    "Please wait before requesting another phone code.": "Vui lòng chờ trước khi yêu cầu mã điện thoại mới.",
    "The login details are incorrect or the account is not active.": "Thông tin đăng nhập không đúng hoặc tài khoản chưa được kích hoạt.",
    "Email": "Email",
    "At least 8 characters": "Tối thiểu 8 ký tự",
    "Confirm password": "Xác nhận mật khẩu",
    "Repeat password": "Nhập lại mật khẩu",
    "I agree to the Terms of Service and Privacy Policy.": "Tôi đồng ý với Điều khoản dịch vụ và Chính sách quyền riêng tư.",
    "Back": "Quay lại",
    "Continue →": "Tiếp tục →",
    "Create account →": "Tạo tài khoản →",
    "Verify phone →": "Xác minh điện thoại →",
    "Verify email →": "Xác minh email →",
    "SHIPPER WORKSPACE": "KHÔNG GIAN NGƯỜI GIAO",
    "Dashboard": "Tổng quan",
    "Delivery offers": "Đơn giao phù hợp",
    "Active delivery": "Đơn đang giao",
    "Delivery history": "Lịch sử giao hàng",
    "Earnings": "Thu nhập",
    "Account": "Tài khoản",
    "Sign out": "Đăng xuất",
    "Shipper account": "Tài khoản người giao",
    "Personal information": "Thông tin cá nhân",
    "Vehicle information": "Thông tin phương tiện",
    "Support center": "Trung tâm hỗ trợ",
    "Chat": "Trò chuyện",
    "Your delivery day, at a glance.": "Theo dõi nhanh hoạt động giao hàng của bạn.",
    "Review nearby delivery requests and choose the offers that work for you.": "Xem các yêu cầu giao gần bạn và chọn đơn phù hợp.",
    "Review the route, package information and estimated earnings before accepting.": "Xem tuyến đường, thông tin kiện hàng và thu nhập dự kiến trước khi nhận.",
    "Follow the current delivery and update its progress.": "Theo dõi đơn hiện tại và cập nhật tiến độ giao hàng.",
    "Review completed and previous deliveries.": "Xem các đơn đã hoàn thành và đơn trước đây.",
    "Review your demo earnings by day, week or month.": "Xem thu nhập demo theo ngày, tuần hoặc tháng.",
    "Manage your personal and vehicle information.": "Quản lý thông tin cá nhân và phương tiện.",
    "Complete the steps required for your shipper profile.": "Hoàn thành các bước đăng ký hồ sơ người giao hàng.",
    "Message support about a delivery.": "Nhắn tin cho bộ phận hỗ trợ về đơn giao.",
    "Find help for your delivery and account.": "Tìm trợ giúp về đơn giao và tài khoản.",
    "Review the account cancellation information.": "Xem thông tin về việc hủy tài khoản.",
    "Review the demo withdrawal form.": "Xem biểu mẫu rút tiền demo.",
    "Tell us what happened with a delivery.": "Cho chúng tôi biết vấn đề đã xảy ra với đơn giao.",
    "Day": "Ngày",
    "Week": "Tuần",
    "Month": "Tháng",
    "Completed deliveries": "Đơn hoàn tất",
    "Across selected period": "Trong khoảng thời gian đã chọn",
    "Demo figures": "Số liệu minh họa",
    "Active hours": "Giờ hoạt động",
    "Acceptance rate": "Tỷ lệ nhận đơn",
    "NEARBY NETWORK": "ĐƠN GIAO GẦN BẠN",
    "AVAILABLE": "SẴN SÀNG",
    "Pickup": "Lấy hàng",
    "Drop-off": "Giao hàng",
    "Skip": "Bỏ qua",
    "Accept": "Nhận đơn",
    "No more demo offers available.": "Không còn đơn demo nào.",
    "Delivery offer details": "Chi tiết đơn giao",
    "Current delivery": "Đơn giao hiện tại",
    "Distance": "Khoảng cách",
    "Estimated earnings": "Thu nhập dự kiến",
    "Reject offer": "Từ chối đơn",
    "Accept delivery →": "Nhận giao đơn →",
    "Mark picked up": "Đã lấy hàng",
    "Mark in transit": "Bắt đầu giao",
    "Mark delivered": "Đã giao hàng",
    "Previous deliveries": "Các đơn giao trước đây",
    "Order": "Mã đơn",
    "Route": "Tuyến đường",
    "Date": "Ngày",
    "Status": "Trạng thái",
    "PROFILE": "HỒ SƠ",
    "DEMO PROFILE": "HỒ SƠ DEMO",
    "Vehicle type": "Loại phương tiện",
    "Vehicle number": "Biển số xe",
    "12-digit number": "Số gồm 12 chữ số",
    "Save demo changes": "Lưu thay đổi demo",
    "SUPPORT & ACCOUNT": "HỖ TRỢ & TÀI KHOẢN",
    "Subject": "Tiêu đề",
    "Briefly describe your request": "Mô tả ngắn gọn yêu cầu",
    "Details": "Chi tiết",
    "Add more information": "Bổ sung thông tin",
    "Withdrawal amount (₫)": "Số tiền muốn rút (₫)",
    "Submit demo request": "Gửi yêu cầu demo",
    "Registration": "Đăng ký người giao",
    "Report issue": "Báo cáo sự cố",
    "Withdraw": "Rút tiền",
    "Cancel account": "Hủy tài khoản",
    "CUSTOMER WORKSPACE": "KHÔNG GIAN KHÁCH HÀNG",
    "Send a package": "Gửi hàng",
    "Create an order, preview the fee, then send it to the matching network.": "Tạo đơn, xem trước phí rồi gửi yêu cầu đến mạng lưới người giao.",
    "NEW DELIVERY": "ĐƠN GIAO MỚI",
    "Create delivery order": "Tạo đơn giao hàng",
    "Pickup address": "Địa chỉ lấy hàng",
    "Drop-off address": "Địa chỉ giao hàng",
    "Street, district, city": "Số nhà, phường/xã, quận/huyện, tỉnh/thành",
    "Estimated distance (km)": "Khoảng cách dự kiến (km)",
    "Package weight (kg)": "Khối lượng kiện hàng (kg)",
    "Delivery service": "Dịch vụ giao hàng",
    "Standard delivery": "Giao hàng tiêu chuẩn",
    "Express delivery": "Giao hàng nhanh",
    "Estimated delivery fee": "Phí giao hàng dự kiến",
    "Demo estimate: base fee ₫30,000 for first 3 km + ₫5,000 per additional km.": "Phí demo: 30.000₫ cho 3 km đầu + 5.000₫ mỗi km tiếp theo.",
    "Send delivery request ↗": "Gửi yêu cầu giao hàng ↗",
    "Smart matching": "Ghép đơn thông minh",
    "Offers are sent to nearby verified shippers.": "Đơn được gửi đến người giao đã xác minh ở gần.",
    "Live status": "Trạng thái đơn",
    "Track the order from matching to completed.": "Theo dõi đơn từ lúc ghép người giao đến khi hoàn tất.",
    "Transparent fee": "Phí minh bạch",
    "The estimated fee is shown before confirmation.": "Phí dự kiến được hiển thị trước khi xác nhận.",
    "DELIVERY ACTIVITY": "HOẠT ĐỘNG GIAO HÀNG",
    "Recent orders": "Đơn hàng gần đây",
    "orders": "đơn",
    "Order ID": "Mã đơn",
    "Pickup": "Điểm lấy",
    "Drop-off": "Điểm giao",
    "Fee": "Phí",
    "IN TRANSIT": "ĐANG GIAO",
    "MATCHING": "ĐANG TÌM NGƯỜI GIAO",
    "INTERNAL CONSOLE": "BẢNG ĐIỀU HÀNH NỘI BỘ",
    "Operations overview": "Tổng quan vận hành",
    "Monitor demo delivery activity, accounts and reported issues.": "Theo dõi hoạt động giao hàng demo, tài khoản và sự cố được báo cáo.",
    "Total orders": "Tổng số đơn",
    "All demo orders": "Tất cả đơn demo",
    "Active shippers": "Người giao đang hoạt động",
    "Customers": "Khách hàng",
    "Demo accounts": "Tài khoản demo",
    "Open reports": "Báo cáo đang mở",
    "Needs review": "Cần xem xét",
    "ORDER OPERATIONS": "VẬN HÀNH ĐƠN HÀNG",
    "Order management": "Quản lý đơn hàng",
    "Search orders...": "Tìm đơn hàng...",
    "Customer": "Khách hàng",
    "Update status": "Cập nhật trạng thái",
    "Shipper accounts": "Tài khoản người giao",
    "VERIFIED": "ĐÃ XÁC MINH",
    "ONLINE": "ĐANG TRỰC TUYẾN",
    "REVIEW": "CẦN XEM XÉT",
    "Recent reports": "Báo cáo gần đây",
    "3 OPEN": "3 ĐANG MỞ",
    "Demo only: changes are not sent to a server.": "Bản demo: thay đổi chưa được gửi đến máy chủ.",
    "Password reset is a frontend demo and is not connected to email or SMS.": "Đặt lại mật khẩu chỉ là demo, chưa kết nối email hoặc SMS.",
    "Enter a valid phone number.": "Vui lòng nhập số điện thoại hợp lệ.",
    "Enter a 6-digit demo verification code.": "Vui lòng nhập mã demo gồm 6 chữ số.",
    "Enter a valid 12-digit national ID number.": "Vui lòng nhập số CCCD gồm 12 chữ số hợp lệ.",
    "Password must be at least 8 characters.": "Mật khẩu phải có ít nhất 8 ký tự.",
    "Passwords do not match.": "Mật khẩu xác nhận không khớp.",
    "Please accept the Terms of Service and Privacy Policy.": "Vui lòng đồng ý với Điều khoản dịch vụ và Chính sách quyền riêng tư.",
    "Enter your phone/email and password to continue.": "Vui lòng nhập số điện thoại/email và mật khẩu để tiếp tục.",
    "Enter your admin access key.": "Vui lòng nhập key quản trị.",
    "Invalid admin access key.": "Key quản trị không hợp lệ.",
    "Demo only: changes are not sent to a server.": "Bản demo: thay đổi chưa được gửi đến máy chủ.",
    "A MORE FLEXIBLE WAY TO DELIVER": "GIAO HÀNG LINH HOẠT HƠN",
    "Every delivery.": "Mỗi đơn hàng.",
    "A little simpler.": "Đơn giản hơn một chút.",
    "One connected network for people who need to send a package and people who want to deliver on their own schedule.": "Một mạng lưới kết nối người cần gửi hàng với người muốn giao hàng theo lịch trình riêng.",
    "Simple booking": "Đặt đơn đơn giản",
    "Create a delivery request with clear pickup and drop-off details.": "Tạo yêu cầu giao hàng với thông tin lấy và giao rõ ràng.",
    "For customers": "Dành cho khách hàng",
    "Flexible earning": "Thu nhập linh hoạt",
    "Choose the offers that fit your time, route, and delivery preferences.": "Chọn đơn phù hợp với thời gian, tuyến đường và mong muốn của bạn.",
    "For shippers": "Dành cho người giao",
    "Transparent pricing": "Giá cước minh bạch",
    "Review estimated delivery fees before you confirm an order.": "Xem phí giao hàng dự kiến trước khi xác nhận đơn.",
    "View pricing": "Xem bảng giá",
    "HOW SOMI WORKS": "CÁCH SOMI HOẠT ĐỘNG",
    "Delivery, made clear at every step.": "Rõ ràng trong từng bước giao hàng.",
    "One simple flow from pickup request to proof of delivery.": "Quy trình đơn giản từ yêu cầu lấy hàng đến xác nhận giao hàng.",
    "Create your delivery": "Tạo đơn giao hàng",
    "Choose and pay": "Chọn và thanh toán",
    "Track with confidence": "Theo dõi an tâm",
    "Tell us where, when, and what you need delivered.": "Cho biết địa điểm, thời gian và món hàng bạn cần giao.",
    "See your fee upfront and select your preferred payment method.": "Xem trước phí và chọn phương thức thanh toán phù hợp.",
    "Get a verified shipper and follow each delivery milestone.": "Kết nối với người giao đã xác minh và theo dõi từng mốc giao hàng.",
    "FOR CUSTOMERS": "DÀNH CHO KHÁCH HÀNG",
    "Every delivery under control.": "Kiểm soát mọi đơn giao hàng.",
    "From urgent documents to thoughtful packages, Somi keeps you informed.": "Từ tài liệu cần giao gấp đến những kiện hàng quan trọng, Somi luôn cập nhật cho bạn.",
    "Clear pricing": "Chi phí minh bạch",
    "Human support": "Hỗ trợ trực tiếp",
    "Create a delivery in a few focused steps.": "Tạo đơn giao hàng qua vài bước đơn giản.",
    "Review every fee before you confirm.": "Kiểm tra mọi khoản phí trước khi xác nhận.",
    "Report an issue and follow its resolution.": "Báo cáo sự cố và theo dõi quá trình xử lý.",
    "FOR SHIPPERS": "DÀNH CHO NGƯỜI GIAO",
    "Your time. Your routes. Your earnings.": "Thời gian của bạn. Tuyến đường của bạn. Thu nhập của bạn.",
    "Go online when it suits you and accept only the offers that work.": "Bật trạng thái trực tuyến khi phù hợp và chỉ nhận đơn bạn muốn.",
    "Choose your work": "Chọn đơn phù hợp",
    "Earn transparently": "Thu nhập minh bạch",
    "Grow your rating": "Nâng cao đánh giá",
    "Review distance, package, and earnings before accepting.": "Xem khoảng cách, kiện hàng và thu nhập trước khi nhận.",
    "See daily and weekly earnings at a glance.": "Theo dõi nhanh thu nhập theo ngày và tuần.",
    "Deliver with care and build customer trust.": "Giao hàng cẩn thận và xây dựng niềm tin với khách hàng.",
    "SIMPLE PRICING": "BẢNG GIÁ MINH BẠCH",
    "Know the cost before you send.": "Biết trước chi phí khi gửi hàng.",
    "Fees are based on route distance, package details, and timing.": "Phí dựa trên khoảng cách, thông tin kiện hàng và thời gian giao.",
    "Base delivery": "Phí giao cơ bản",
    "Returns": "Phí hoàn trả",
    "30,000₫ includes the first 3 km.": "30.000₫ bao gồm 3 km đầu tiên.",
    "5,000₫ for each additional kilometer.": "5.000₫ cho mỗi km tiếp theo.",
    "Return fees are shown before confirmation.": "Phí hoàn trả được hiển thị trước khi xác nhận.",
    "Terms of Service": "Điều khoản dịch vụ",
    "Privacy Policy": "Chính sách quyền riêng tư",
    "Close": "Đóng",
    "AVAILABLE": "SẴN SÀNG NHẬN ĐƠN",
    "Save demo changes": "Lưu thay đổi demo",
    "Personal information": "Thông tin cá nhân",
    "Vehicle information": "Thông tin phương tiện",
    "Account": "Tài khoản",
    "Cancel account": "Hủy tài khoản",
    "SHIPPER WORKSPACE": "KHÔNG GIAN NGƯỜI GIAO",
    "Dashboard": "Tổng quan",
    "Your delivery day, at a glance.": "Theo dõi nhanh hoạt động giao hàng của bạn.",
    "Delivery offers": "Đơn giao phù hợp",
    "Review nearby delivery requests and choose the offers that work for you.": "Xem các yêu cầu giao gần bạn và chọn đơn phù hợp.",
    "Offer detail": "Chi tiết đơn giao",
    "Review the route, package information and estimated earnings before accepting.": "Xem tuyến đường, thông tin kiện hàng và thu nhập dự kiến trước khi nhận.",
    "Active delivery": "Đơn đang giao",
    "Follow the current delivery and update its progress.": "Theo dõi đơn hiện tại và cập nhật tiến độ giao hàng.",
    "Delivery history": "Lịch sử giao hàng",
    "Review completed and previous deliveries.": "Xem các đơn đã hoàn thành và đơn trước đây.",
    "Earnings": "Thu nhập",
    "Review your demo earnings by day, week or month.": "Xem thu nhập demo theo ngày, tuần hoặc tháng.",
    "Shipper registration": "Đăng ký người giao",
    "Complete the steps required for your shipper profile.": "Hoàn thành các bước đăng ký hồ sơ người giao hàng.",
    "Chat": "Trò chuyện",
    "Message support about a delivery.": "Nhắn tin hỗ trợ về đơn giao.",
    "Support center": "Trung tâm hỗ trợ",
    "Find help for your delivery and account.": "Tìm trợ giúp về đơn giao và tài khoản.",
    "Cancel account": "Hủy tài khoản",
    "Review the account cancellation information.": "Xem thông tin về việc hủy tài khoản.",
    "Withdraw earnings": "Rút thu nhập",
    "Review the demo withdrawal form.": "Xem biểu mẫu rút tiền demo.",
    "Report an issue": "Báo cáo sự cố",
    "Tell us what happened with a delivery.": "Cho chúng tôi biết vấn đề đã xảy ra với đơn giao.",
    "◉ Shipper": "◉ Người giao hàng",
    "Sign out": "Đăng xuất"
  };

  function translateVietnameseDocument() {
    const toVietnamese = document.documentElement.lang === "vi";
    const reverseText = Object.fromEntries(Object.entries(viText).map(([en, vi]) => [vi, en]));
    const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
    const nodes = [];
    while (walker.nextNode()) nodes.push(walker.currentNode);
    nodes.forEach((node) => {
      const current = node.nodeValue.trim();
      const translated = toVietnamese ? viText[current] : reverseText[current];
      if (translated) node.nodeValue = node.nodeValue.replace(current, translated);
    });
    const placeholders = {
      "Phone number or email": "Số điện thoại hoặc email",
      "Enter your admin key": "Nhập key quản trị",
      "+84 90 123 4567 or you@example.com": "+84 90 123 4567 hoặc ban@example.com",
      "Enter your password": "Nhập mật khẩu", "Your full name": "Nhập họ và tên",
      "6-digit demo code": "Mã demo gồm 6 chữ số", "12-digit CCCD number": "Nhập số CCCD gồm 12 chữ số",
      "At least 8 characters": "Tối thiểu 8 ký tự", "Repeat password": "Nhập lại mật khẩu",
      "Street, district, city": "Số nhà, phường/xã, quận/huyện, tỉnh/thành",
      "Search orders...": "Tìm đơn hàng...", "Briefly describe your request": "Mô tả ngắn gọn yêu cầu",
      "Add more information": "Bổ sung thông tin"
    };
    const reversePlaceholders = Object.fromEntries(Object.entries(placeholders).map(([en, vi]) => [vi, en]));
    $$("input[placeholder], textarea[placeholder]").forEach((element) => {
      const current = element.getAttribute("placeholder");
      const translated = toVietnamese ? placeholders[current] : reversePlaceholders[current];
      if (translated) element.setAttribute("placeholder", translated);
    });
  }

  function setupLanguage() {
    const pickers = $$("[data-language-picker]");
    const options = $$("[data-language-option]");
    const saved = localStorage.getItem("somi-language") || "en";
    const apply = (language) => {
      const copy = translations[language] || translations.en;
      $$("[data-i18n]").forEach((element) => {
        const key = element.dataset.i18n;
        if (copy[key]) element.textContent = copy[key];
      });
      const navKeys = { how: "navHow", customers: "navCustomers", shippers: "navShippers", pricing: "navPricing" };
      $$("[data-nav]").forEach((element) => {
        const key = navKeys[element.dataset.nav];
        if (key && copy[key]) element.textContent = copy[key];
      });
      document.documentElement.lang = language;
      pickers.forEach((picker) => {
        const current = $("[data-language-current]", picker);
        const trigger = $("[data-language-trigger]", picker);
        const menu = $("[data-language-menu]", picker);
        if (current) current.textContent = language.toUpperCase();
        $$("[data-language-option]", picker).forEach((option) => {
          option.classList.toggle("is-active", option.dataset.languageOption === language);
          option.setAttribute("aria-checked", String(option.dataset.languageOption === language));
        });
        if (trigger) trigger.setAttribute("aria-expanded", String(menu && !menu.hidden));
      });
      localStorage.setItem("somi-language", language);
      document.documentElement.lang = language;
      translateVietnameseDocument();
      document.dispatchEvent(new CustomEvent("somi:language-change", { detail: { language } }));
    };
    pickers.forEach((picker) => {
      const trigger = $("[data-language-trigger]", picker);
      const menu = $("[data-language-menu]", picker);
      if (trigger && menu) trigger.addEventListener("click", () => {
        const open = menu.hidden;
        menu.hidden = !open;
        trigger.setAttribute("aria-expanded", String(open));
      });
      $$("[data-language-option]", picker).forEach((option) => option.addEventListener("click", () => {
        apply(option.dataset.languageOption);
        if (menu) menu.hidden = true;
        if (trigger) trigger.setAttribute("aria-expanded", "false");
      }));
      document.addEventListener("click", (event) => {
        if (!picker.contains(event.target)) {
          if (menu) menu.hidden = true;
          if (trigger) trigger.setAttribute("aria-expanded", "false");
        }
      });
    });
    apply(saved);
  }

  function setupPublicNavigation() {
    const homeHeader = $(".header-home");
    if (homeHeader) {
      const updateHeader = () => {
        if (document.documentElement.lang !== "vi") return;
        homeHeader.classList.toggle("is-scrolled", window.scrollY > 12);
      };
      window.addEventListener("scroll", updateHeader, { passive: true });
      updateHeader();
    }
    const toggle = $("[data-menu-toggle]");
    const menu = $("[data-mobile-nav]");
    if (toggle && menu) toggle.addEventListener("click", () => {
      const open = menu.hidden;
      menu.hidden = !open;
      toggle.setAttribute("aria-expanded", String(open));
      toggle.setAttribute("aria-label", open ? "Close menu" : "Open menu");
      toggle.textContent = open ? "×" : "☰";
    });

    const modal = $("[data-legal-modal]");
    $$("[data-legal]").forEach((button) => button.addEventListener("click", () => {
      if (!modal) return;
      const privacy = button.dataset.legal === "privacy";
      $("[data-legal-title]", modal).textContent = privacy ? "Privacy Policy" : "Terms of Service";
      modal.hidden = false;
    }));
    $$("[data-modal-close]").forEach((button) => button.addEventListener("click", () => { if (modal) modal.hidden = true; }));
    if (modal) modal.addEventListener("click", (event) => { if (event.target === modal) modal.hidden = true; });
  }

  function setupAuth() {
    const form = $("[data-auth-form]");
    if (!form) return;
    const modeButtons = $$("[data-auth-mode]");
    const signinFields = $("[data-signin-fields]");
    const signupFields = $("[data-signup-fields]");
    const submitButton = $("[data-auth-submit]");
    const error = $("[data-form-error]");
    const stepNext = $("[data-step-next]");
    const stepBack = $("[data-step-back]");
    let mode = "signin";
    let step = 1;
    let registrationRole = "customer";
    let currentPhoneDemoCode = "";

    const renderPhoneDemoCode = () => {
      const note = $("[data-phone-demo-code]");
      if (!note) return;
      const vi = document.documentElement.lang === "vi";
      note.hidden = !currentPhoneDemoCode;
      note.textContent = currentPhoneDemoCode
        ? (vi ? `Mã OTP demo (không gửi SMS): ${currentPhoneDemoCode}` : `Demo OTP (no SMS sent): ${currentPhoneDemoCode}`)
        : "";
    };

    const apiPost = async (path, payload = {}) => {
      const response = await fetch(`public/api/auth/${path}`, {
        method: "POST",
        credentials: "same-origin",
        headers: { "Content-Type": "application/json", "Accept": "application/json" },
        body: JSON.stringify(payload)
      });
      const result = await response.json().catch(() => ({}));
      if (!response.ok) throw new Error(result.error || "The request could not be completed.");
      return result;
    };

    const showError = (message) => {
      error.textContent = document.documentElement.lang === "vi" ? (viText[message] || message) : message;
      error.hidden = !message;
    };
    const render = () => {
      const signup = mode === "signup";
      const role = $('input[name="role"]:checked', form)?.value || "customer";
      const isAdmin = role === "admin";
      signinFields.hidden = signup;
      signupFields.hidden = !signup || isAdmin;
      submitButton.hidden = signup;
      const signupTab = $('[data-auth-mode="signup"]');
      signupTab.hidden = isAdmin;
      document.querySelector(".auth-shell")?.classList.toggle("auth-admin-mode", isAdmin);
      modeButtons.forEach((button) => button.classList.toggle("active", button.dataset.authMode === mode));
      const vi = document.documentElement.lang === "vi";
      $("[data-auth-title]").textContent = isAdmin
        ? (vi ? "Đăng nhập Admin" : "Admin sign in")
        : (vi ? (signup ? "Tạo tài khoản Somi" : "Chào mừng bạn quay trở lại") : (signup ? "Create your Somi account" : "Welcome back"));
      $("[data-auth-subtitle]").textContent = isAdmin
        ? (vi ? "Đăng nhập bằng tài khoản Admin được cấp." : "Sign in with an administrator account provisioned by the project owner.")
        : (vi ? (signup ? "Hoàn thành các bước demo để xem quy trình tạo tài khoản." : "Nhập số điện thoại hoặc email để tiếp tục.") : (signup ? "Complete the demo steps to preview account setup." : "Enter your phone number or email to continue."));
      const asideEyebrow = $("[data-auth-aside-eyebrow]");
      const asideHeading = $("[data-auth-heading]");
      const asideDescription = $("[data-auth-aside-description]");
      const asideFooter = $("[data-auth-aside-footer]");
      if (asideEyebrow) asideEyebrow.textContent = isAdmin ? (vi ? "KHÔNG GIAN QUẢN TRỊ" : "ADMIN WORKSPACE") : (vi ? "CHÀO MỪNG ĐẾN VỚI SOMI" : "Welcome to Somi");
      if (asideHeading) asideHeading.textContent = isAdmin ? (vi ? "Vận hành SOMI an toàn." : "Secure access for Somi operations.") : (vi ? "Giao hàng phù hợp với nhịp sống của bạn." : "Delivery that fits your day.");
      if (asideDescription) asideDescription.textContent = isAdmin ? (vi ? "Chỉ tài khoản Admin được cấp quyền mới vào được bảng điều hành." : "Only provisioned Admin accounts can access the operations console.") : (vi ? "Đăng nhập để gửi hàng hoặc quản lý công việc giao hàng cùng Somi." : "Sign in to send a package or manage your delivery work with Somi.");
      if (asideFooter) asideFooter.textContent = isAdmin ? (vi ? "Chỉ dành cho quản trị viên được ủy quyền." : "Authorized administrators only.") : (vi ? "Đơn giản. Linh hoạt. Minh bạch." : "Simple. Flexible. Transparent.");
      $("[data-auth-submit-text]").textContent = isAdmin ? (vi ? "Vào bảng điều hành" : "Open Admin Console") : (vi ? "Đăng nhập" : "Sign in");
      $("[data-step-label]").textContent = vi ? `BƯỚC ${step}/5` : `STEP ${step} OF 5`;
      $("[data-step-progress]").style.width = `${step * 20}%`;
      $$("[data-signup-step]").forEach((section) => { section.hidden = Number(section.dataset.signupStep) !== step; });
      stepBack.hidden = step === 1 || step >= 4;
      stepNext.textContent = step === 3 ? (vi ? "Tạo tài khoản →" : "Create account →")
        : step === 4 ? (vi ? "Xác minh điện thoại →" : "Verify phone →")
          : step === 5 ? (vi ? "Xác minh email →" : "Verify email →")
            : (vi ? "Tiếp tục →" : "Continue →");
      const countrySelect = form.elements.nationalityCode;
      if (countrySelect) {
        const displayNames = new Intl.DisplayNames([vi ? "vi" : "en"], { type: "region" });
        [...countrySelect.options].forEach((option) => {
          if (option.value) option.textContent = displayNames.of(option.value) || option.value;
        });
      }
      if (form.elements.documentType && countrySelect) {
        const vietnamese = countrySelect.value === "VN";
        const nationalId = form.elements.documentType.querySelector('option[value="NATIONAL_ID"]');
        nationalId.hidden = !vietnamese;
        if (vietnamese) form.elements.documentType.value = "NATIONAL_ID";
        else if (form.elements.documentType.value === "NATIONAL_ID") form.elements.documentType.value = "PASSPORT";
      }
      renderPhoneDemoCode();
      showError("");
    };
    document.addEventListener("somi:language-change", render);
    modeButtons.forEach((button) => button.addEventListener("click", () => { mode = button.dataset.authMode; step = 1; render(); }));
    form.elements.nationalityCode.addEventListener("change", render);
    $$('input[name="role"]', form).forEach((input) => input.addEventListener("change", () => {
      if (input.value === "admin") mode = "signin";
      render();
    }));
    stepBack.addEventListener("click", () => { step = Math.max(1, step - 1); render(); });
    stepNext.addEventListener("click", async () => {
      if (mode !== "signup") return;
      showError("");
      const nationality = form.elements.nationalityCode.value;
      if (step === 1) {
        const phone = form.elements.phone.value.trim();
        if (!form.elements.fullName.value.trim()) return showError("Enter your full name.");
        if (!/^\+[1-9]\d{7,14}$/.test(phone.replace(/[\s().-]+/g, ""))) return showError("Enter the phone number with country code, for example +84901234567.");
        if (!form.elements.dateOfBirth.value || new Date(form.elements.dateOfBirth.value) > new Date()) return showError("Enter a valid date of birth.");
        if (!form.elements.gender.value) return showError("Choose a gender.");
        step += 1;
        render();
        return;
      }
      if (step === 2) {
        if (!/^[A-Za-z0-9]{4,40}$/.test(form.elements.documentNumber.value.trim())) return showError("Identity document number must contain 4–40 letters or digits.");
        step += 1;
        render();
        return;
      }
      if (step === 3) {
        if (!form.elements.email.value.trim()) return showError("Enter a valid email address.");
        if (form.elements.newPassword.value.length < 8 || form.elements.newPassword.value.length > 72) return showError("Password must be 8–72 characters.");
        if (form.elements.newPassword.value !== form.elements.confirmPassword.value) return showError("Passwords do not match.");
        if (!form.elements.terms.checked) return showError("Accept the Terms of Service and Privacy Policy to continue.");
        stepNext.disabled = true;
        try {
          registrationRole = $('input[name="role"]:checked', form)?.value || "customer";
          const registration = await apiPost("register.php", {
            full_name: form.elements.fullName.value.trim(),
            email: form.elements.email.value.trim(),
            phone: form.elements.phone.value.trim(),
            password: form.elements.newPassword.value,
            nationality_code: nationality,
            date_of_birth: form.elements.dateOfBirth.value,
            gender: form.elements.gender.value,
            app_language: document.documentElement.lang === "vi" ? "vi" : "en",
            role: registrationRole,
            document_type: form.elements.documentType.value,
            document_country_code: nationality,
            document_number: form.elements.documentNumber.value.trim(),
            terms_accepted: form.elements.terms.checked
          });
          currentPhoneDemoCode = registration.demo_code || "";
          step = 4;
          render();
        } catch (requestError) {
          showError(requestError.message);
        } finally {
          stepNext.disabled = false;
        }
        return;
      }
      if (step === 4) {
        if (!/^\d{6}$/.test(form.elements.phoneOtp.value.trim())) return showError("Enter the six-digit phone code.");
        stepNext.disabled = true;
        try {
          await apiPost("verify-phone.php", { code: form.elements.phoneOtp.value.trim() });
          step = 5;
          render();
          try {
            await apiPost("send-email-code.php");
          } catch (sendError) {
            showError(sendError.message);
          }
        } catch (requestError) {
          showError(requestError.message);
        } finally {
          stepNext.disabled = false;
        }
        return;
      }
      if (step === 5) {
        if (!/^\d{6}$/.test(form.elements.emailOtp.value.trim())) return showError("Enter the six-digit email code.");
        stepNext.disabled = true;
        try {
          await apiPost("verify-email-code.php", { code: form.elements.emailOtp.value.trim() });
          finishAuth(registrationRole);
        } catch (requestError) {
          showError(requestError.message);
        } finally {
          stepNext.disabled = false;
        }
      }
    });
    form.addEventListener("submit", (event) => {
      event.preventDefault();
      if (mode === "signup") return;
      const role = $('input[name="role"]:checked', form)?.value || "customer";
      if (!form.elements.identifier.value.trim() || !form.elements.password.value) return showError("Enter your phone/email and password to continue.");
      submitButton.disabled = true;
      apiPost("login.php", {
        identifier: form.elements.identifier.value.trim(),
        password: form.elements.password.value,
        role
      }).then(() => finishAuth(role))
        .catch((requestError) => showError(requestError.message))
        .finally(() => { submitButton.disabled = false; });
    });
    const resendPhone = $(`[data-resend-phone]`);
    const resendEmail = $(`[data-resend-email]`);
    if (resendPhone) resendPhone.addEventListener("click", async () => {
      resendPhone.disabled = true;
      try {
        const result = await apiPost("resend-phone-code.php");
        currentPhoneDemoCode = result.demo_code || "";
        renderPhoneDemoCode();
        showError(document.documentElement.lang === "vi" ? "Đã tạo mã demo mới; hệ thống chưa gửi SMS." : "A new demo code was created; no SMS was sent.");
      } catch (requestError) { showError(requestError.message); }
      finally { resendPhone.disabled = false; }
    });
    if (resendEmail) resendEmail.addEventListener("click", async () => {
      resendEmail.disabled = true;
      try {
        await apiPost("send-email-code.php");
        showError(document.documentElement.lang === "vi" ? "Đã gửi mã email mới." : "A new email code was sent.");
      } catch (requestError) { showError(requestError.message); }
      finally { resendEmail.disabled = false; }
    });
    function finishAuth(role = $('input[name="role"]:checked', form)?.value || "customer") {
      localStorage.setItem("somi-demo-role", role);
      if (role === "admin") { navigate("admin"); return; }
      const params = new URLSearchParams(window.location.search);
      const next = params.get("next");
      navigate(role === "shipper" || next === "shipper" ? "shipper" : "customer");
    }
    const forgot = $("[data-forgot]");
    if (forgot) forgot.addEventListener("click", () => showError("Password reset is a frontend demo and is not connected to email or SMS."));
    render();
  }

  const initialOrders = [
    { id: "SOMI-240125", pickup: "District 1", dropoff: "Binh Thanh", distance: 4, weight: 2, fee: 35000, service: "STANDARD", status: "IN_TRANSIT" },
    { id: "SOMI-240126", pickup: "Phu Nhuan", dropoff: "District 3", distance: 3, weight: 1.5, fee: 42000, service: "EXPRESS", status: "MATCHING" }
  ];
  function getOrders() {
    try {
      const stored = JSON.parse(localStorage.getItem("somi-demo-orders") || "null");
      return Array.isArray(stored) ? stored : initialOrders;
    } catch { return initialOrders; }
  }
  function saveOrders(orders) { localStorage.setItem("somi-demo-orders", JSON.stringify(orders)); }
  function renderOrders() {
    const target = $("[data-orders]");
    if (!target) return;
    const orders = getOrders();
    target.innerHTML = orders.map((order) => `<tr><td>${escapeHtml(order.id)}</td><td>${escapeHtml(order.pickup)}</td><td>${escapeHtml(order.dropoff)}</td><td>${money(order.fee)}</td><td><span class="status ${order.status === "MATCHING" ? "status-amber" : "status-blue"}">${escapeHtml(order.status.replaceAll("_", " "))}</span></td></tr>`).join("");
    const count = $("[data-order-count]");
    if (count) count.textContent = `${orders.length} orders`;
  }
  function escapeHtml(value) {
    return String(value).replace(/[&<>"']/g, (character) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" })[character]);
  }
  function setupCustomer() {
    const form = $("[data-order-form]");
    if (!form) return;
    const distance = form.elements.distance;
    const fee = $("[data-fee]");
    const estimate = () => {
      const km = Math.max(1, Number(distance.value) || 1);
      const amount = 30000 + Math.max(0, km - 3) * 5000 + (form.elements.service.value === "EXPRESS" ? 7000 : 0);
      fee.textContent = money(amount);
    };
    distance.addEventListener("input", estimate);
    form.elements.service.addEventListener("change", estimate);
    estimate();
    form.addEventListener("submit", (event) => {
      event.preventDefault();
      if (!form.reportValidity()) return;
      const orders = getOrders();
      const nextId = `SOMI-${String(orders.length + 240127).padStart(6, "0")}`;
      const km = Number(distance.value);
      const amount = 30000 + Math.max(0, km - 3) * 5000 + (form.elements.service.value === "EXPRESS" ? 7000 : 0);
      orders.unshift({ id: nextId, pickup: form.elements.pickup.value.trim(), dropoff: form.elements.dropoff.value.trim(), distance: km, weight: Number(form.elements.weight.value), fee: amount, service: form.elements.service.value, status: "MATCHING" });
      saveOrders(orders);
      renderOrders();
      form.reset();
      distance.value = "4";
      estimate();
    });
    renderOrders();
  }

  function setupShipper() {
    const statValues = {
      day: { orders: "12", income: "₫420,000", hours: "4.8h", rate: "98%" },
      week: { orders: "68", income: "₫2,180,000", hours: "28.5h", rate: "97%" },
      month: { orders: "245", income: "₫7,420,000", hours: "112h", rate: "98%" }
    };
    $$("[data-period]").forEach((button) => button.addEventListener("click", () => {
      $$("[data-period]").forEach((item) => item.classList.toggle("active", item === button));
      const values = statValues[button.dataset.period];
      Object.entries(values).forEach(([key, value]) => {
        const target = $(`[data-stat="${key}"]`);
        if (target) target.textContent = value;
      });
    }));
    const offerList = $("[data-offers]");
    const noOffers = $("[data-no-offers]");
    $$("[data-offer-action]").forEach((button) => button.addEventListener("click", () => {
      const action = button.dataset.offerAction;
      if (action === "reject") {
        const row = button.closest(".offer-row");
        if (row) row.remove();
        if (offerList && !$(".offer-row", offerList) && noOffers) noOffers.hidden = false;
        return;
      }
      const status = $("[data-active-status]");
      if (status) { status.textContent = "ASSIGNED"; status.className = "status status-blue"; }
      const progress = $("[data-delivery-progress]");
      if (progress) progress.hidden = false;
      const row = button.closest(".offer-row");
      if (row) row.innerHTML = '<strong>Offer accepted</strong><span class="muted">Your demo delivery is now assigned.</span>';
    }));
    const progress = $("[data-delivery-progress]");
    if (progress) progress.addEventListener("click", () => {
      const status = $("[data-active-status]");
      if (status && status.textContent === "ASSIGNED") { status.textContent = "PICKING UP"; progress.textContent = "Mark in transit"; }
      else if (status && status.textContent === "PICKING UP") { status.textContent = "IN TRANSIT"; progress.textContent = "Mark delivered"; }
      else if (status && status.textContent === "IN TRANSIT") { status.textContent = "DELIVERED"; progress.hidden = true; }
    });
  }

  function setupAdmin() {
    const search = $("[data-table-search]");
    const body = $("[data-admin-orders]");
    if (search && body) search.addEventListener("input", () => {
      const query = search.value.toLowerCase();
      $$("tr", body).forEach((row) => { row.hidden = !row.textContent.toLowerCase().includes(query); });
    });
    $$("[data-status-select]").forEach((select) => select.addEventListener("change", () => {
      const row = select.closest("tr");
      const badge = row && $(".status", row);
      if (!badge) return;
      badge.textContent = select.value.replaceAll("_", " ");
      badge.className = `status ${["DELIVERED", "COMPLETED"].includes(select.value) ? "status-green" : select.value === "CANCELLED" ? "status-red" : "status-blue"}`;
    }));
  }

  function setupAvatarMenu() {
    const toggle = $("[data-avatar-toggle]");
    const menu = $("[data-avatar-menu]");
    if (!toggle || !menu) return;
    toggle.addEventListener("click", () => {
      const open = menu.hidden;
      menu.hidden = !open;
      toggle.setAttribute("aria-expanded", String(open));
    });
    document.addEventListener("click", (event) => {
      if (!event.target.closest(".shipper-avatar-menu")) {
        menu.hidden = true;
        toggle.setAttribute("aria-expanded", "false");
      }
    });
  }

  function setupDemoForms() {
    $$("[data-demo-form]").forEach((form) => form.addEventListener("submit", (event) => {
      event.preventDefault();
      const feedback = $("[data-form-feedback]", form);
      if (feedback) feedback.textContent = "Demo only: changes are not sent to a server.";
    }));
    $$("[data-signout]").forEach((button) => button.addEventListener("click", () => {
      localStorage.removeItem("somi-demo-role");
      navigate("home");
    }));
  }

  document.addEventListener("DOMContentLoaded", () => {
    setupLanguage();
    setupPublicNavigation();
    setupAuth();
    setupCustomer();
    setupShipper();
    setupAvatarMenu();
    setupAdmin();
    setupDemoForms();
  });
})();
