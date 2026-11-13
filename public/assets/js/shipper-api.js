"use strict";
// Lớp gọi API dùng chung cho không gian Shipper. Các module giao diện (offers, active, account)
// chỉ gọi qua window.SomiShipper, không tự viết fetch, để lỗi và phiên đăng nhập xử lý một chỗ.
(() => {
  const BASE = "public/api/shipper/";

  // Thông báo tiếng Việt cho các mã lỗi backend trả về.
  const MESSAGES = {
    AUTHENTICATION_REQUIRED: "Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.",
    SHIPPER_NOT_ELIGIBLE: "Tài khoản chưa đủ điều kiện nhận đơn.",
    AVAILABILITY_OFF: "Bạn cần bật trạng thái nhận đơn trước.",
    ORDER_SEARCH_EXPIRED: "Đơn đã hết 5 phút tìm shipper. Hệ thống sẽ hủy và xử lý hoàn tiền cho khách.",
    OFFER_EXPIRED: "Đơn này đã hết thời gian nhận.",
    OFFER_LOST_RACE: "Đơn đã được người giao khác nhận trước.",
    OFFER_NOT_PENDING: "Đơn này không còn mở để nhận.",
    ORDER_NOT_AVAILABLE: "Đơn không còn tìm người giao.",
    ACTIVE_DELIVERY_EXISTS: "Bạn cần hoàn tất đơn đang giao trước.",
    ARRIVAL_REQUIRED: "Cần ghi nhận đã đến điểm giao trước khi chụp ảnh và hoàn tất đơn.",
    ARRIVAL_LOCATION_REQUIRED: "Cần GPS mới, độ chính xác dưới 200 m và vị trí trong 200 m quanh điểm giao. Hãy bật GPS và thử lại.",
    SCHEDULE_NOT_DUE: "Đơn hẹn giờ chưa đến thời điểm tìm shipper.",
    PICKUP_TOO_EARLY: "Chưa đến giờ lấy hàng đã hẹn. Vui lòng chờ đến giờ để xác nhận.",
    EVIDENCE_REQUIRED: "Cần thêm ảnh bằng chứng trước khi xác nhận.",
    INVALID_ORDER_STATE: "Thao tác không phù hợp với trạng thái hiện tại của đơn.",
    VEHICLE_REGISTRATION_TAKEN: "Biển số này đã thuộc về một hồ sơ khác.",
    KYC_NOT_RESUBMITTABLE: "Hồ sơ chỉ nộp lại được khi Admin yêu cầu bổ sung.",
    CANCELLATION_PENDING: "Yêu cầu đang chờ Admin xem xét. Vui lòng tải lại đơn.",
    IDEMPOTENCY_CONFLICT: "Nội dung yêu cầu đã thay đổi. Vui lòng tải lại và kiểm tra trạng thái.",
    CONCURRENT_CHANGE: "Dữ liệu vừa thay đổi. Vui lòng thử lại.",
    VALIDATION_ERROR: "Thông tin gửi lên chưa hợp lệ.",
  };

  async function request(path, method = "GET", body = null) {
    const options = { method, credentials: "same-origin", headers: { Accept: "application/json" } };
    if (body !== null) {
      options.headers["Content-Type"] = "application/json";
      options.body = JSON.stringify(body);
    }
    let response;
    try {
      response = await fetch(BASE + path, options);
    } catch (networkError) {
      throw Object.assign(new Error("Không kết nối được máy chủ. Kiểm tra mạng rồi thử lại."), { code: "NETWORK_ERROR" });
    }
    const data = await response.json().catch(() => ({}));
    if (!response.ok) {
      const error = new Error(MESSAGES[data.code] || data.error || "Yêu cầu không thực hiện được.");
      error.code = data.code || "REQUEST_FAILED";
      error.status = response.status;
      throw error;
    }
    return data;
  }

  // Mọi chuỗi lấy từ API đều phải qua esc() trước khi đưa vào innerHTML để tránh XSS.
  function esc(value) {
    return String(value ?? "").replace(/[&<>"']/g, (ch) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[ch]));
  }

  function money(minor) {
    return typeof window.formatShipperMoney === "function" ? window.formatShipperMoney(Number(minor)) : Number(minor).toLocaleString("vi-VN") + " ₫";
  }

  function toast(message) {
    if (typeof window.toast === "function") window.toast(message);
  }

  // Quãng đường hiển thị theo ngôn ngữ đang chọn: tiếng Việt dùng dấu phẩy thập phân.
  // API trả chuỗi dạng "7.650" nên nếu in thẳng sẽ bị đọc nhầm thành 7650 km.
  function distance(value) {
    const number = Number(value);
    if (!Number.isFinite(number)) return String(value ?? "");
    const locale = document.documentElement.lang === "vi" ? "vi-VN" : "en-US";
    return number.toLocaleString(locale, { maximumFractionDigits: 1 });
  }

  // Vị trí hiện tại của thiết bị. Trình duyệt chỉ cấp khi trang chạy HTTPS hoặc localhost.
  function position() {
    return new Promise((resolve, reject) => {
      if (!navigator.geolocation) return reject(new Error("Thiết bị không hỗ trợ định vị."));
      navigator.geolocation.getCurrentPosition(
        (p) => resolve({ latitude: p.coords.latitude, longitude: p.coords.longitude, accuracy_meters: Math.round(p.coords.accuracy) }),
        () => reject(new Error("Cần cho phép truy cập vị trí để nhận đơn gần bạn.")),
        { enableHighAccuracy: true, timeout: 10000, maximumAge: 15000 }
      );
    });
  }

  window.SomiShipper = {
    esc, money, distance, toast, position,
    profile: () => request("profile.php"),
    accountProfile: () => request("../account/profile.php"),
    updateName: (fullName) => request("../account/profile.php", "PUT", { full_name: fullName }),
    dashboard: (range) => request("dashboard.php?range=" + encodeURIComponent(range)),
    updateVehicle: (vehicle) => request("profile.php", "PUT", vehicle),
    kyc: () => request("kyc.php"),
    resubmitKyc: (body) => request("kyc.php", "POST", body),
    uploadPrivate: async (file, purpose, orderId) => {
      const body=new FormData();body.append('file',file);body.append('purpose',purpose);if(orderId)body.append('order_id',String(orderId));
      const response=await fetch('public/api/storage.php',{method:'POST',credentials:'same-origin',body,headers:{Accept:'application/json'}});
      const result=await response.json().catch(()=>({}));if(!response.ok)throw new Error(result.error||'Không tải được file.');return result;
    },
    availability: () => request("availability.php"),
    setAvailability: (body) => request("availability.php", "POST", body),
    offers: () => request("offers.php"),
    refreshOffers: () => request("offers.php", "POST", { action: "refresh" }),
    rejectOffer: (offerId) => request("offers.php", "POST", { offer_id: String(offerId) }),
    acceptOffer: (offerId) => request("accept-offer.php", "POST", { offer_id: String(offerId) }),
    currentDelivery: () => request("deliveries.php"),
    requestCancellation: (body) => request("cancellation.php", "POST", body),
    history: () => request("deliveries.php?history=1&limit=50"),
    historyPage: before => request('deliveries.php?history=1&limit=50&before_id='+encodeURIComponent(before)),
    historyDetail: id => request('deliveries.php?assignment_id='+encodeURIComponent(id)),
    deliveryAction: (orderId, action) => request("deliveries.php", "POST", { order_id: String(orderId), action }),
    addEvidence: (evidence) => request("evidence.php", "POST", evidence),
    completeDelivery: (orderId) => request("complete-delivery.php", "POST", { order_id: String(orderId) }),
    failDelivery: (orderId, reasonCode, note) => request("return-delivery.php", "POST", { order_id: String(orderId), action: "FAIL", reason_code: reasonCode, ...(note ? { note } : {}) }),
    confirmReturned: (orderId) => request("return-delivery.php", "POST", { order_id: String(orderId), action: "RETURNED" }),
    sendLocation: (point) => request("location.php", "POST", point),
  };
})();
