/*
 * SOMI Customer: data store and business rules (frontend demo).
 *
 * This file plays the role the backend will play later (OrderService,
 * FeeCalculationService, PaymentService, MatchingService). Every page calls
 * the functions exposed on window.SomiStore, so when the PHP API is ready
 * each function body can be swapped for a fetch() call without touching
 * the pages. Field names follow the database design (orders, order_details,
 * payments, payment_transactions, order_status_history, reviews, reports,
 * notifications, addresses).
 *
 * Data is kept in localStorage under STORAGE_KEY so the demo survives reloads.
 *
 * Text: labels and error messages go through t() (see i18n.js). Text that is
 * saved with an order (notifications, history notes, reasons) is saved in
 * Vietnamese, or as msg(template, values), and translated when it is shown.
 * Money: integers in the smallest unit of the order currency (VND đồng, USD cent).
 */
function createSomiStore() {
  "use strict";
  const I18N = window.SomiI18n;
  const { t, msg } = I18N;

  const STORAGE_KEY = "somi-customer-v2";
  const DATA_VERSION = 2;
  const MAX_WEIGHT_KG = 30;
  const MAX_QUANTITY = 20;
  const MAX_PAYMENT_ATTEMPTS = 3;
  const OTP_MAX_ATTEMPTS = 5;
  const OTP_TTL_MS = 5 * 60 * 1000;

  /* ---------- Price tables, one per currency (Admin > Giá và chính sách) ----------
   * Same structure for both currencies; amounts are in the smallest unit
   * (VND: đồng, USD: cent). Admin will own these numbers once the pricing API
   * exists; the USD table is kept separate on purpose instead of converting
   * every quote, as the design asks for separate VND/USD price tables. */
  const PRICING = {
    VND: {
      baseFee: 18000,          // covers the first baseKm km
      baseKm: 2,
      pricePerKm: 5000,
      freeWeightKg: 3,
      pricePerExtraKg: 2500,
      extraItem: 3000,         // each item after the first
      cancellation: 10000,     // customer cancels after a shipper accepted
      reschedule: 10000,
      returnRate: 0.5,         // return fee = 50% of delivery fee
      surcharge: { DOCUMENT: 0, SMALL: 0, MEDIUM: 5000, FRAGILE: 10000 }
    },
    USD: {
      baseFee: 72,
      baseKm: 2,
      pricePerKm: 20,
      freeWeightKg: 3,
      pricePerExtraKg: 10,
      extraItem: 12,
      cancellation: 40,
      reschedule: 40,
      returnRate: 0.5,
      surcharge: { DOCUMENT: 0, SMALL: 0, MEDIUM: 20, FRAGILE: 40 }
    }
  };
  // Reference rate shown to the customer next to USD prices (Admin config: 1 USD = 25,000 VND).
  const USD_RATE = 25000;
  const fee = (order) => PRICING[order.currency] || PRICING.VND;
  // Every computed amount is rounded UP to the smallest unit of its currency.
  const roundUp = (n) => Math.ceil(n - 1e-9);

  const PACKAGE_TYPES = [
    { value: "DOCUMENT", label: t("Tài liệu"), hint: t("Giấy tờ, hồ sơ, phong bì") },
    { value: "SMALL", label: t("Bưu kiện nhỏ"), hint: t("Vừa một hộp giày") },
    { value: "MEDIUM", label: t("Bưu kiện vừa"), hint: t("Vừa thùng sau xe máy") },
    { value: "FRAGILE", label: t("Hàng dễ vỡ"), hint: t("Thủy tinh, gốm, đồ điện tử") }
  ];

  const AREAS = [
    { name: "Quận 1", lat: 10.7757, lng: 106.7004 },
    { name: "Quận 3", lat: 10.7843, lng: 106.6844 },
    { name: "Quận 4", lat: 10.7579, lng: 106.7013 },
    { name: "Quận 5", lat: 10.7540, lng: 106.6634 },
    { name: "Quận 7", lat: 10.7340, lng: 106.7216 },
    { name: "Quận 10", lat: 10.7727, lng: 106.6677 },
    { name: "Bình Thạnh", lat: 10.8106, lng: 106.7091 },
    { name: "Phú Nhuận", lat: 10.7991, lng: 106.6802 },
    { name: "Tân Bình", lat: 10.8015, lng: 106.6527 },
    { name: "Gò Vấp", lat: 10.8387, lng: 106.6653 },
    { name: "Bình Tân", lat: 10.7652, lng: 106.6038 },
    { name: "Thủ Đức", lat: 10.8494, lng: 106.7537 }
  ];

  // currencies: which order currencies each method can collect.
  const PAYMENT_METHODS = {
    COD: { label: t("Thanh toán khi giao"), short: t("Tiền mặt (COD)"), online: false, currencies: ["VND"] },
    MOMO: { label: t("Ví MoMo"), short: "MoMo", online: true, currencies: ["VND"] },
    VNPAY: { label: "VNPAY", short: "VNPAY", online: true, currencies: ["VND"] },
    ZALOPAY: { label: "ZaloPay", short: "ZaloPay", online: true, currencies: ["VND"] },
    CARD: { label: t("Thẻ quốc tế Visa/Mastercard"), short: t("Thẻ quốc tế"), online: true, currencies: ["VND", "USD"] }
  };
  function methodsFor(currency) {
    return Object.keys(PAYMENT_METHODS).filter((key) => PAYMENT_METHODS[key].currencies.includes(currency));
  }

  /* ---------- Order status (labels, tone, groups) ---------- */
  const STATUS = {
    PENDING: { label: t("Mới tạo"), tone: "gray", step: t("Đơn hàng được tạo") },
    PAYMENT_PENDING: { label: t("Chờ thanh toán"), tone: "amber", step: t("Chờ thanh toán") },
    PAID: { label: t("Đã thanh toán"), tone: "blue", step: t("Thanh toán thành công") },
    MATCHING: { label: t("Đang tìm shipper"), tone: "blue", step: t("Đang tìm shipper") },
    NO_SHIPPER_FOUND: { label: t("Chưa có shipper"), tone: "red", step: t("Chưa tìm được shipper") },
    ASSIGNED: { label: t("Shipper đã nhận"), tone: "blue", step: t("Shipper đã nhận đơn") },
    SHIPPER_CANCELLED: { label: t("Shipper đã hủy"), tone: "amber", step: t("Shipper hủy nhận đơn") },
    PICKING_UP: { label: t("Đang lấy hàng"), tone: "blue", step: t("Shipper đang đến lấy hàng") },
    IN_TRANSIT: { label: t("Đang giao"), tone: "blue", step: t("Đã lấy hàng, đang giao") },
    ARRIVED: { label: t("Đã đến nơi"), tone: "blue", step: t("Shipper đã đến điểm giao") },
    WAITING_FOR_RECIPIENT: { label: t("Chờ người nhận"), tone: "amber", step: t("Đang chờ người nhận") },
    RESCHEDULED: { label: t("Hẹn giao lại"), tone: "amber", step: t("Hẹn giao lại") },
    DELIVERED: { label: t("Đã giao"), tone: "green", step: t("Giao hàng thành công") },
    COMPLETED: { label: t("Hoàn tất"), tone: "green", step: t("Đơn hàng hoàn tất") },
    CANCELLED: { label: t("Đã hủy"), tone: "gray", step: t("Đơn hàng đã hủy") },
    DELIVERY_FAILED: { label: t("Giao thất bại"), tone: "red", step: t("Giao hàng không thành công") },
    RETURNING: { label: t("Đang hoàn hàng"), tone: "amber", step: t("Đang hoàn hàng về người gửi") },
    RETURNED: { label: t("Đã hoàn hàng"), tone: "gray", step: t("Đã hoàn hàng về người gửi") },
    DISPUTED: { label: t("Đang khiếu nại"), tone: "red", step: t("Đang xử lý khiếu nại") }
  };

  const GROUPS = {
    active: ["PENDING", "PAYMENT_PENDING", "PAID", "MATCHING", "ASSIGNED", "SHIPPER_CANCELLED", "PICKING_UP", "IN_TRANSIT", "ARRIVED", "WAITING_FOR_RECIPIENT", "RESCHEDULED"],
    done: ["DELIVERED", "COMPLETED"],
    cancelled: ["CANCELLED"],
    problem: ["NO_SHIPPER_FOUND", "DELIVERY_FAILED", "RETURNING", "RETURNED", "DISPUTED"]
  };

  // Shipper is physically on the job (used for the "live" card and tracking).
  const ON_THE_ROAD = ["ASSIGNED", "PICKING_UP", "IN_TRANSIT", "ARRIVED", "WAITING_FOR_RECIPIENT", "RESCHEDULED", "RETURNING"];

  // State machine: BR-244 to BR-261 in the analysis document.
  // Extra edges taken from the scenarios: ARRIVED -> DELIVERY_FAILED (scenario 11, COD refused),
  // IN_TRANSIT -> DISPUTED (scenario 12), PICKING_UP -> SHIPPER_CANCELLED (scenario 6),
  // RESCHEDULED -> RETURNING (customer cancels while the shipper still holds the package, BR-116/117).
  const TRANSITIONS = {
    PENDING: ["PAYMENT_PENDING", "MATCHING", "CANCELLED"],
    PAYMENT_PENDING: ["PAID", "CANCELLED"],
    PAID: ["MATCHING", "CANCELLED"],
    MATCHING: ["ASSIGNED", "NO_SHIPPER_FOUND", "CANCELLED"],
    NO_SHIPPER_FOUND: ["MATCHING", "CANCELLED"],
    ASSIGNED: ["PICKING_UP", "SHIPPER_CANCELLED", "CANCELLED"],
    SHIPPER_CANCELLED: ["MATCHING"],
    PICKING_UP: ["IN_TRANSIT", "SHIPPER_CANCELLED", "CANCELLED"],
    IN_TRANSIT: ["ARRIVED", "DELIVERY_FAILED", "RETURNING", "DISPUTED"],
    ARRIVED: ["WAITING_FOR_RECIPIENT", "DELIVERED", "DELIVERY_FAILED"],
    WAITING_FOR_RECIPIENT: ["DELIVERED", "RESCHEDULED", "DELIVERY_FAILED"],
    RESCHEDULED: ["IN_TRANSIT", "RETURNING"],
    DELIVERED: ["COMPLETED"],
    DELIVERY_FAILED: ["RETURNING", "DISPUTED"],
    RETURNING: ["RETURNED", "DISPUTED"],
    RETURNED: ["COMPLETED", "DISPUTED"],
    DISPUTED: ["COMPLETED", "RETURNING", "RETURNED", "CANCELLED"],
    COMPLETED: [],
    CANCELLED: []
  };

  const REPORT_TYPES = [
    { value: "WRONG_ADDRESS", label: t("Sai địa chỉ") },
    { value: "DAMAGED_PACKAGE", label: t("Hàng bị hư hỏng") },
    { value: "LOST_PACKAGE", label: t("Mất kiện hàng") },
    { value: "SHIPPER_PROBLEM", label: t("Vấn đề với shipper") },
    { value: "PAYMENT_PROBLEM", label: t("Vấn đề thanh toán") },
    { value: "DELIVERY_PROBLEM", label: t("Không giao được hàng") },
    { value: "OTHER", label: t("Khác") }
  ];
  const REPORT_STATUS = {
    OPEN: { label: t("Đã tiếp nhận"), tone: "blue" },
    UNDER_REVIEW: { label: t("Đang xem xét"), tone: "amber" },
    RESOLVED: { label: t("Đã giải quyết"), tone: "green" },
    REJECTED: { label: t("Từ chối"), tone: "gray" }
  };

  const CANCEL_REASONS = [
    "Tôi đổi ý, không cần gửi nữa",
    "Tôi nhập sai thông tin đơn hàng",
    "Tôi đã tìm được cách giao khác",
    "Chờ tìm shipper quá lâu",
    "Lý do khác"
  ];

  const SHIPPERS = [
    { id: "SHP-1284", name: "Trần Minh Khôi", phone: "0938 412 765", vehicle: "Honda Wave Alpha", plate: "59X2-418.27", rating: 4.9, trips: 428 },
    { id: "SHP-1283", name: "Lê Hoàng Phúc", phone: "0907 233 819", vehicle: "Yamaha Sirius", plate: "59P1-233.81", rating: 4.8, trips: 312 },
    { id: "SHP-1279", name: "Nguyễn Thanh Tâm", phone: "0912 902 154", vehicle: "Honda Vision", plate: "51G1-902.15", rating: 4.7, trips: 186 },
    { id: "SHP-1271", name: "Phạm Gia Huy", phone: "0976 551 042", vehicle: "Honda Air Blade", plate: "59T1-551.04", rating: 4.9, trips: 507 }
  ];

  /* ---------- Small helpers ---------- */
  const now = () => Date.now();
  const iso = (ms) => new Date(ms).toISOString();
  const MIN = 60 * 1000;
  const HOUR = 60 * MIN;
  const DAY = 24 * HOUR;
  const clone = (value) => JSON.parse(JSON.stringify(value));
  const uid = (prefix) => `${prefix}-${Math.random().toString(36).slice(2, 8).toUpperCase()}`;

  function hash(text) {
    let h = 0;
    for (const ch of String(text)) h = (h * 31 + ch.charCodeAt(0)) >>> 0;
    return h;
  }

  function area(name) {
    return AREAS.find((a) => a.name === name) || AREAS[0];
  }

  /* MapService stand-in: road distance ≈ straight line × 1.35. */
  function routeDistance(from, to) {
    const a = area(from.area);
    const b = area(to.area);
    const rad = (d) => (d * Math.PI) / 180;
    const dLat = rad(b.lat - a.lat);
    const dLng = rad(b.lng - a.lng);
    const h = Math.sin(dLat / 2) ** 2 + Math.cos(rad(a.lat)) * Math.cos(rad(b.lat)) * Math.sin(dLng / 2) ** 2;
    const straight = 6371 * 2 * Math.asin(Math.sqrt(h));
    const local = 0.8 + (hash(`${from.detail}|${to.detail}`) % 18) / 10; // street-level variation
    const km = Math.max(1, straight * 1.35 + local);
    return Math.round(km * 10) / 10;
  }

  /* FeeCalculationService stand-in. Returns every line so the UI can show it.
   * Line labels are msg() objects because they are saved on the order. */
  function quote(pickup, delivery, pkg = {}, currency = I18N.currency) {
    const FEE = PRICING[currency] || PRICING.VND;
    const ready = Boolean(pickup && delivery && pickup.area && delivery.area);
    const distanceKm = ready && pickup && delivery ? routeDistance(pickup, delivery) : 0;
    const durationMin = ready ? Math.round(8 + (distanceKm / 22) * 60) : 0;
    const type = PACKAGE_TYPES.find((x) => x.value === pkg.type);
    const weight = Number(pkg.weightKg) || 0;
    const quantity = Math.max(1, Math.floor(Number(pkg.quantity) || 1));
    const lines = [];
    if (ready) {
      const distanceFee = roundUp(FEE.baseFee + Math.max(0, distanceKm - FEE.baseKm) * FEE.pricePerKm);
      lines.push({ key: "distance", label: msg("Cước quãng đường ({km} km)", { km: distanceKm }), amount: distanceFee });
    }
    const extraKg = Math.max(0, Math.ceil(weight - FEE.freeWeightKg));
    if (extraKg > 0) lines.push({ key: "weight", label: msg("Phụ phí khối lượng (+{kg} kg)", { kg: extraKg }), amount: extraKg * FEE.pricePerExtraKg });
    const surcharge = type ? FEE.surcharge[type.value] || 0 : 0;
    if (surcharge) lines.push({ key: "type", label: msg(TYPE_SURCHARGE[type.value]), amount: surcharge });
    if (quantity > 1) lines.push({ key: "items", label: msg("Thêm {n} kiện", { n: quantity - 1 }), amount: (quantity - 1) * FEE.extraItem });
    const deliveryFee = lines.reduce((sum, line) => sum + line.amount, 0);
    return { ready, currency, distanceKm, durationMin, lines, deliveryFee, returnFee: roundUp(deliveryFee * FEE.returnRate) };
  }

  const TYPE_SURCHARGE = { MEDIUM: "Phụ phí bưu kiện vừa", FRAGILE: "Phụ phí hàng dễ vỡ" };
  function surchargeFor(type, currency = I18N.currency) {
    return (PRICING[currency] || PRICING.VND).surcharge[type] || 0;
  }
  function returnFeeOf(order) {
    return roundUp(order.fees.delivery * fee(order).returnRate);
  }

  /* ---------- Money owed / refunded ---------- */
  function charges(order) {
    const f = order.fees;
    if (order.status === "CANCELLED") return f.cancellation;
    // waived: delivery fee not charged because the failure was not the customer's fault (scenario 16)
    return Math.max(0, f.delivery + f.additional + f.reschedule + f.return + f.cancellation - (f.waived || 0));
  }
  function balance(order) {
    return charges(order) - order.payment.paid;
  }
  /* What the customer must still pay online right now (0 when COD will collect it). */
  const NO_OUTSTANDING = ["PENDING", "PAYMENT_PENDING", "NO_SHIPPER_FOUND", "DISPUTED", "COMPLETED"];
  function outstanding(order) {
    const owed = balance(order);
    if (owed <= 0 || NO_OUTSTANDING.includes(order.status)) return 0;
    // COD: the shipper collects delivery + extra fees from the recipient on delivery.
    if (order.payment.method === "COD" && (GROUPS.active.includes(order.status) || order.status === "DELIVERED")) return 0;
    return owed;
  }

  /* ---------- Persistence ---------- */
  let state = null;

  function seed() {
    const t0 = now();
    const addresses = [
      { id: "ADR-1", label: "Nhà riêng", receiverName: "Lê Ngọc Hân", receiverPhone: "0903128456", detail: "45 Phan Đăng Lưu, Phường 3", area: "Phú Nhuận", isDefault: true, createdAt: iso(t0 - 90 * DAY) },
      { id: "ADR-2", label: "Văn phòng", receiverName: "Lê Ngọc Hân", receiverPhone: "0903128456", detail: "15 Lê Lợi, Phường Bến Nghé", area: "Quận 1", isDefault: false, createdAt: iso(t0 - 60 * DAY) },
      { id: "ADR-3", label: "Nhà mẹ", receiverName: "Trần Thị Mai", receiverPhone: "0918223344", detail: "200 Tôn Thất Thuyết, Phường 4", area: "Quận 4", isDefault: false, createdAt: iso(t0 - 30 * DAY) }
    ];
    const ref = (a) => ({ addressId: a.id, label: a.label, name: a.receiverName, phone: a.receiverPhone, detail: a.detail, area: a.area });
    const home = ref(addresses[0]);
    const office = ref(addresses[1]);
    const mom = ref(addresses[2]);
    const pkg = (type, description, weightKg, quantity = 1, note = "") => ({ type, description, weightKg, quantity, note });
    const person = (name, phone, detail, areaName) => ({ addressId: null, label: "", name, phone, detail, area: areaName });

    function make(code, minsAgo, status, opts) {
      const created = t0 - minsAgo * MIN;
      const currency = opts.currency || "VND";
      const q = quote(opts.pickup, opts.delivery, opts.pkg, currency);
      const order = {
        code,
        status,
        currency,
        createdAt: iso(created),
        updatedAt: iso(created),
        pickup: opts.pickup,
        delivery: opts.delivery,
        pkg: opts.pkg,
        schedule: { mode: "now", at: null },
        distanceKm: q.distanceKm,
        durationMin: q.durationMin,
        feeLines: q.lines,
        fees: { delivery: q.deliveryFee, additional: 0, reschedule: 0, cancellation: 0, return: 0 },
        payment: { method: opts.method, status: "PENDING", paid: 0, paidAt: null, attempts: [], refund: null },
        shipper: opts.shipper || null,
        history: [],
        matchLog: [],
        cancel: null,
        failureReason: null,
        rescheduleAt: null,
        addressIssue: false,
        review: null,
        reportIds: [],
        location: { updatedAt: iso(t0 - 20 * 1000) }
      };
      let at = created;
      opts.path.forEach(([s, gap, actor, note]) => {
        at += gap * MIN;
        order.history.push({ status: s, at: iso(at), actor, note: note || "" });
      });
      order.updatedAt = iso(at);
      const paidStep = order.history.find((h) => h.status === "PAID");
      if (PAYMENT_METHODS[opts.method].online && paidStep) {
        order.payment.status = "PAID";
        order.payment.paid = q.deliveryFee;
        order.payment.paidAt = paidStep.at;
        order.payment.attempts.push({ txn: `TXN${String(hash(code)).slice(0, 8)}`, method: opts.method, amount: q.deliveryFee, status: "SUCCESS", at: paidStep.at, purpose: "order" });
      }
      return order;
    }

    const orders = [
      make("SOMI-240141", 6, "MATCHING", {
        method: "COD", pickup: home, delivery: person("Võ Quốc Bảo", "0987654321", "12 Nguyễn Văn Trỗi, Phường 11", "Quận 3"),
        pkg: pkg("DOCUMENT", "Hợp đồng thuê nhà, 1 phong bì A4", 0.3),
        path: [["PENDING", 0, "customer"], ["MATCHING", 1, "system"]]
      }),
      make("SOMI-240140", 18, "PAYMENT_PENDING", {
        method: "VNPAY", pickup: office, delivery: person("Đỗ Thị Lan", "0911002233", "88 Cách Mạng Tháng 8, Phường 6", "Quận 3"),
        pkg: pkg("SMALL", "Hộp mỹ phẩm, đã bọc kín", 1.2),
        path: [["PENDING", 0, "customer"], ["PAYMENT_PENDING", 1, "customer"]]
      }),
      make("SOMI-240139", 95, "IN_TRANSIT", {
        method: "MOMO", pickup: home, delivery: mom, shipper: SHIPPERS[0],
        pkg: pkg("FRAGILE", "Bộ chén sứ quà tặng, có hộp xốp", 2.4, 1, "Xin nhẹ tay, hàng dễ vỡ"),
        path: [["PENDING", 0, "customer"], ["PAYMENT_PENDING", 1, "customer"], ["PAID", 1, "system", "MoMo"], ["MATCHING", 0, "system"], ["ASSIGNED", 3, "shipper"], ["PICKING_UP", 2, "shipper"], ["IN_TRANSIT", 14, "shipper"]]
      }),
      make("SOMI-240137", 70, "NO_SHIPPER_FOUND", {
        method: "COD", pickup: office, delivery: person("Huỳnh Tấn Phát", "0934556677", "21 Kha Vạn Cân, Phường Linh Chiểu", "Thủ Đức"),
        pkg: pkg("MEDIUM", "Thùng sách cũ", 8.5),
        path: [["PENDING", 0, "customer"], ["MATCHING", 1, "system"], ["NO_SHIPPER_FOUND", 6, "system", "Đã gửi cho 5 shipper gần nhất"]]
      }),
      make("SOMI-240133", 26 * 60, "COMPLETED", {
        method: "ZALOPAY", pickup: home, delivery: person("Phan Minh Đức", "0965123789", "102 Hoàng Văn Thụ, Phường 9", "Tân Bình"), shipper: SHIPPERS[1],
        pkg: pkg("SMALL", "Tai nghe bluetooth, hộp nguyên seal", 0.5),
        path: [["PENDING", 0, "customer"], ["PAYMENT_PENDING", 1, "customer"], ["PAID", 1, "system", "ZaloPay"], ["MATCHING", 0, "system"], ["ASSIGNED", 2, "shipper"], ["PICKING_UP", 1, "shipper"], ["IN_TRANSIT", 12, "shipper"], ["ARRIVED", 18, "shipper"], ["DELIVERED", 3, "shipper"], ["COMPLETED", 1, "system"]]
      }),
      make("SOMI-240128", 3 * 24 * 60, "COMPLETED", {
        method: "COD", pickup: office, delivery: mom, shipper: SHIPPERS[2],
        pkg: pkg("DOCUMENT", "Sổ tiết kiệm và giấy tờ nhà", 0.4),
        path: [["PENDING", 0, "customer"], ["MATCHING", 1, "system"], ["ASSIGNED", 2, "shipper"], ["PICKING_UP", 1, "shipper"], ["IN_TRANSIT", 10, "shipper"], ["ARRIVED", 15, "shipper"], ["DELIVERED", 2, "shipper"], ["COMPLETED", 1, "system"]]
      }),
      make("SOMI-240121", 5 * 24 * 60, "CANCELLED", {
        method: "MOMO", pickup: home, delivery: person("Lý Gia Hân", "0909888777", "300 Nguyễn Thị Thập, Phường Tân Phú", "Quận 7"),
        pkg: pkg("SMALL", "Áo khoác, túi giấy", 0.8),
        path: [["PENDING", 0, "customer"], ["PAYMENT_PENDING", 1, "customer"], ["PAID", 1, "system", "MoMo"], ["MATCHING", 0, "system"], ["CANCELLED", 4, "customer", "Tôi đã tìm được cách giao khác"]]
      }),
      make("SOMI-240117", 6 * 24 * 60, "RETURNED", {
        method: "MOMO", pickup: home, delivery: person("Trịnh Văn Long", "0977112233", "45 Lê Văn Việt, Phường Hiệp Phú", "Thủ Đức"), shipper: SHIPPERS[3],
        pkg: pkg("MEDIUM", "Nồi chiên không dầu", 4.2),
        path: [["PENDING", 0, "customer"], ["PAYMENT_PENDING", 1, "customer"], ["PAID", 1, "system", "MoMo"], ["MATCHING", 0, "system"], ["ASSIGNED", 2, "shipper"], ["PICKING_UP", 1, "shipper"], ["IN_TRANSIT", 15, "shipper"], ["ARRIVED", 30, "shipper"], ["WAITING_FOR_RECIPIENT", 1, "shipper"], ["DELIVERY_FAILED", 10, "shipper", "Không liên lạc được người nhận"], ["RETURNING", 1, "system"], ["RETURNED", 40, "shipper"]]
      }),
      make("SOMI-240109", 9 * 24 * 60, "DISPUTED", {
        method: "VNPAY", pickup: office, delivery: person("Mai Anh Thư", "0938777666", "9 Trần Hưng Đạo, Phường 1", "Quận 5"), shipper: SHIPPERS[2],
        pkg: pkg("FRAGILE", "Bình hoa gốm Bát Tràng", 3.1),
        path: [["PENDING", 0, "customer"], ["PAYMENT_PENDING", 1, "customer"], ["PAID", 1, "system", "VNPAY"], ["MATCHING", 0, "system"], ["ASSIGNED", 2, "shipper"], ["PICKING_UP", 1, "shipper"], ["IN_TRANSIT", 12, "shipper"], ["DELIVERY_FAILED", 20, "shipper", "Hàng bị vỡ trong lúc vận chuyển"], ["DISPUTED", 30, "customer", msg("Báo cáo {id}", { id: "RP-1042" })]]
      })
    ];

    // Details that the path alone cannot express.
    const byCode = (c) => {
      const found = orders.find((o) => o.code === c);
      if (!found) throw new Error(`Seed order ${c} missing`);
      return found;
    };
    const cod = byCode("SOMI-240128");
    cod.payment.status = "PAID";
    cod.payment.paid = cod.fees.delivery;
    cod.payment.paidAt = (cod.history.find((h) => h.status === "DELIVERED") || cod.history[0]).at;
    cod.review = { rating: 5, tags: ["Đúng giờ", "Cẩn thận với hàng"], comment: "Anh Tâm giao nhanh, gọi trước khi đến.", at: iso(t0 - 3 * DAY + 2 * HOUR) };

    const cancelled = byCode("SOMI-240121");
    cancelled.cancel = { by: "customer", reason: "Tôi đã tìm được cách giao khác", at: cancelled.history[cancelled.history.length - 1].at };
    cancelled.payment.status = "REFUNDED";
    cancelled.payment.refund = { amount: cancelled.payment.paid, status: "DONE", at: iso(t0 - 4 * DAY) };

    const returned = byCode("SOMI-240117");
    returned.fees.return = returnFeeOf(returned);
    returned.failureReason = "Không liên lạc được người nhận sau 10 phút chờ";

    const disputed = byCode("SOMI-240109");
    disputed.failureReason = "Hàng bị vỡ trong lúc vận chuyển";
    disputed.reportIds = ["RP-1042"];

    const transit = byCode("SOMI-240139");
    transit.eta = iso(t0 + 18 * MIN);

    byCode("SOMI-240141").matchLog = [{ at: iso(t0 - 5 * MIN), text: "Đã gửi yêu cầu cho 3 shipper gần điểm lấy hàng" }];
    byCode("SOMI-240121").matchLog = [{ at: iso(t0 - 5 * DAY + 3 * MIN), text: "Đã đóng mọi yêu cầu đang gửi cho shipper" }];
    byCode("SOMI-240137").matchLog = [
      { at: iso(t0 - 69 * MIN), text: "Đã gửi yêu cầu cho 3 shipper gần điểm lấy hàng" },
      { at: iso(t0 - 67 * MIN), text: "Shipper thứ nhất từ chối" },
      { at: iso(t0 - 66 * MIN), text: "Shipper thứ hai không phản hồi kịp" },
      { at: iso(t0 - 64 * MIN), text: "Đã mở rộng phạm vi tìm kiếm" },
      { at: iso(t0 - 63 * MIN), text: "Chưa có shipper nhận đơn" }
    ];

    const reports = [
      {
        id: "RP-1042", orderCode: "SOMI-240109", about: "ORDER", type: "DAMAGED_PACKAGE",
        description: "Bình hoa bị nứt một đường dài ở thân, hộp ngoài bị móp một góc. Tôi đã chụp ảnh lúc shipper báo sự cố.",
        files: [{ name: "binh-hoa-nut.jpg", size: 842000, kind: "image" }],
        status: "UNDER_REVIEW", adminNote: null, createdAt: iso(t0 - 9 * DAY + 2 * HOUR),
        history: [{ status: "OPEN", at: iso(t0 - 9 * DAY + 2 * HOUR) }, { status: "UNDER_REVIEW", at: iso(t0 - 8 * DAY) }]
      },
      {
        id: "RP-1031", orderCode: "SOMI-240117", about: "SHIPPER", type: "DELIVERY_PROBLEM",
        description: "Người nhận nói không thấy cuộc gọi nào, mong SOMI kiểm tra lại lịch sử liên lạc.",
        files: [], status: "RESOLVED",
        adminNote: "Đã kiểm tra nhật ký cuộc gọi: shipper gọi 3 lần, không có người nghe. Phí hoàn hàng giữ nguyên theo chính sách.",
        createdAt: iso(t0 - 6 * DAY + 5 * HOUR),
        history: [{ status: "OPEN", at: iso(t0 - 6 * DAY + 5 * HOUR) }, { status: "UNDER_REVIEW", at: iso(t0 - 5 * DAY) }, { status: "RESOLVED", at: iso(t0 - 4 * DAY) }]
      }
    ];
    returned.reportIds = ["RP-1031"];

    const N = (type, title, body, minsAgo, read, link) => ({ id: uid("NT"), type, title, body, at: iso(t0 - minsAgo * MIN), read, link });
    const notifications = [
      N("order", "Shipper đã lấy hàng", msg("Đơn {code} đang được giao đến {area}.", { code: "SOMI-240139", area: "Quận 4" }), 64, false, { view: "tracking", code: "SOMI-240139" }),
      N("order", "Chưa tìm được shipper", msg("Đơn {code} chưa có shipper nhận. Bạn có thể tìm lại hoặc hủy đơn miễn phí.", { code: "SOMI-240137" }), 63, false, { view: "matching", code: "SOMI-240137" }),
      N("payment", "Còn phí hoàn hàng chưa thanh toán", msg("Đơn {code} còn {amount} phí hoàn hàng.", { code: "SOMI-240117", amount: { m: returned.fees.return, c: "VND" } }), 6 * 24 * 60 - 180, false, { view: "order", code: "SOMI-240117" }),
      N("order", "Đơn hàng hoàn tất", msg("Đơn {code} đã hoàn tất. Hãy đánh giá shipper.", { code: "SOMI-240133" }), 26 * 60 - 40, true, { view: "review", code: "SOMI-240133" }),
      N("report", "Báo cáo đang được xem xét", msg("{id} đã được chuyển cho bộ phận xử lý khiếu nại.", { id: "RP-1042" }), 8 * 24 * 60, true, { view: "reports", id: "RP-1042" }),
      N("payment", "Đã hoàn tiền", msg("{amount} của đơn {code} đã về {method}.", { amount: { m: cancelled.payment.refund.amount, c: "VND" }, code: "SOMI-240121", method: msg("Ví MoMo") }), 4 * 24 * 60, true, { view: "order", code: "SOMI-240121" })
    ];

    return {
      version: DATA_VERSION,
      account: {
        id: "CUS-7102", fullName: "Lê Ngọc Hân", email: "ngochan@example.com", phone: "0903128456",
        emailVerified: true, phoneVerified: true, status: "ACTIVE", memberSince: iso(t0 - 210 * DAY),
        passwordChangedAt: iso(t0 - 120 * DAY)
      },
      addresses,
      orders,
      reports,
      notifications,
      sim: { loadError: false, location: "live" },
      otp: null
    };
  }

  function load() {
    if (state) return state;
    try {
      const saved = JSON.parse(localStorage.getItem(STORAGE_KEY) || "null");
      state = saved && saved.version === DATA_VERSION ? saved : seed();
    } catch {
      state = seed();
    }
    return state;
  }
  function save() {
    try { localStorage.setItem(STORAGE_KEY, JSON.stringify(state)); } catch { /* private mode: keep in memory */ }
    document.dispatchEvent(new CustomEvent("somi:change"));
  }
  function reset() {
    state = seed();
    save();
  }

  /* ---------- Formatting shared with the UI ---------- */
  // Vietnamese source names, for messages saved in data (translated when shown).
  const METHOD_NAMES = { COD: "Thanh toán khi giao", MOMO: "Ví MoMo", VNPAY: "VNPAY", ZALOPAY: "ZaloPay", CARD: "Thẻ quốc tế Visa/Mastercard" };
  function methodMsg(order) {
    return msg(order.payment.method ? METHOD_NAMES[order.payment.method] : "phương thức đã dùng");
  }
  // A money value inside a saved message: formatted in the order currency when shown.
  const cash = (amount, order) => ({ m: amount, c: order.currency });
  function formatMoney(amount, currency = I18N.currency) {
    return I18N.money(amount, currency);
  }

  /* ---------- Orders ---------- */
  function listOrders() {
    return clone(load().orders).sort((a, b) => b.createdAt.localeCompare(a.createdAt));
  }
  function getOrder(code) {
    const found = load().orders.find((o) => o.code === code);
    return found ? clone(found) : null;
  }
  function raw(code) {
    const found = load().orders.find((o) => o.code === code);
    if (!found) throw new Error(t`Không tìm thấy đơn ${code}`);
    return found;
  }

  function notify(type, title, body, link) {
    load().notifications.unshift({ id: uid("NT"), type, title, body, at: iso(now()), read: false, link });
  }

  // Saved in Vietnamese with values; the notification list translates them when shown.
  const STATUS_NOTICE = {
    MATCHING: ["Đang tìm shipper", "Đơn {code} đã được gửi đến các shipper gần điểm lấy hàng.", "matching"],
    ASSIGNED: ["Shipper đã nhận đơn", "{shipper} sẽ đến lấy hàng cho đơn {code}.", "tracking"],
    PICKING_UP: ["Shipper đang đến lấy hàng", "Hãy chuẩn bị kiện hàng cho đơn {code}.", "tracking"],
    IN_TRANSIT: ["Shipper đã lấy hàng", "Đơn {code} đang được giao.", "tracking"],
    ARRIVED: ["Shipper đã đến nơi", "Shipper đang ở điểm giao của đơn {code}.", "tracking"],
    WAITING_FOR_RECIPIENT: ["Shipper đang chờ người nhận", "Người nhận của đơn {code} chưa xuất hiện. Bạn có thể hẹn giao lại hoặc yêu cầu hoàn hàng.", "tracking"],
    DELIVERED: ["Giao hàng thành công", "Đơn {code} đã đến tay người nhận.", "order"],
    COMPLETED: ["Đơn hàng hoàn tất", "Đơn {code} đã hoàn tất. Hãy đánh giá shipper.", "review"],
    NO_SHIPPER_FOUND: ["Chưa tìm được shipper", "Đơn {code} chưa có shipper nhận. Bạn có thể tìm lại hoặc hủy đơn miễn phí.", "matching"],
    DELIVERY_FAILED: ["Giao hàng không thành công", "Đơn {code}: {reason}", "order"],
    RETURNING: ["Hàng đang được hoàn về", "Shipper đang mang kiện hàng của đơn {code} về cho bạn.", "tracking"],
    RETURNED: ["Đã hoàn hàng", "Kiện hàng của đơn {code} đã được trả lại cho bạn.", "order"],
    RESCHEDULED: ["Đã hẹn giao lại", "Đơn {code} sẽ được giao lại vào {when}.", "tracking"],
    CANCELLED: ["Đơn hàng đã hủy", "Đơn {code} đã được hủy.", "order"],
    SHIPPER_CANCELLED: ["Shipper đã hủy nhận đơn", "SOMI đang tìm shipper khác cho đơn {code}.", "matching"],
    DISPUTED: ["Đơn đang được xử lý khiếu nại", "Đơn {code} được tạm giữ trong lúc SOMI kiểm tra.", "order"]
  };

  function canTransition(from, to) {
    return (TRANSITIONS[from] || []).includes(to);
  }

  /* Single entry point for status changes: validates, records history, notifies. */
  function setStatus(order, to, actor, note = "") {
    if (!canTransition(order.status, to)) {
      throw new Error(t`Không thể chuyển đơn từ ${STATUS[order.status].label} sang ${STATUS[to].label}.`);
    }
    order.status = to;
    order.updatedAt = iso(now());
    order.history.push({ status: to, at: order.updatedAt, actor, note });
    const message = STATUS_NOTICE[to];
    if (message) {
      const body = msg(message[1], {
        code: order.code,
        shipper: order.shipper ? order.shipper.name : "Shipper",
        reason: msg(order.failureReason || "Giao không thành công"),
        when: order.rescheduleAt ? { d: order.rescheduleAt } : msg("thời gian đã hẹn")
      });
      notify("order", message[0], body, { view: message[2], code: order.code });
    }
  }

  function nextCode() {
    const numbers = load().orders.map((o) => Number(o.code.split("-")[1]) || 0);
    return `SOMI-${Math.max(240141, ...numbers) + 1}`;
  }

  function createOrder(draft) {
    const s = load();
    if (s.account.status !== "ACTIVE") throw new Error(t("Tài khoản đang bị khóa nên chưa thể tạo đơn."));
    if (!s.account.phoneVerified) throw new Error(t("Bạn cần xác thực số điện thoại trước khi tạo đơn."));
    const currency = PRICING[draft.currency] ? draft.currency : I18N.currency;
    const q = quote(draft.pickup, draft.delivery, draft.pkg, currency);
    const created = iso(now());
    const order = {
      code: nextCode(),
      status: "PENDING",
      currency,
      createdAt: created,
      updatedAt: created,
      pickup: draft.pickup,
      delivery: draft.delivery,
      pkg: draft.pkg,
      schedule: draft.schedule,
      distanceKm: q.distanceKm,
      durationMin: q.durationMin,
      feeLines: q.lines,
      fees: { delivery: q.deliveryFee, additional: 0, reschedule: 0, cancellation: 0, return: 0 },
      payment: { method: null, status: "PENDING", paid: 0, paidAt: null, attempts: [], refund: null },
      shipper: null,
      history: [{ status: "PENDING", at: created, actor: "customer", note: "" }],
      matchLog: [],
      cancel: null,
      failureReason: null,
      rescheduleAt: null,
      addressIssue: false,
      review: null,
      reportIds: [],
      location: { updatedAt: created }
    };
    s.orders.unshift(order);
    notify("order", "Đã tạo đơn hàng", msg("Đơn {code} đã được tạo. Chọn phương thức thanh toán để bắt đầu tìm shipper.", { code: order.code }), { view: "payment", code: order.code });
    save();
    return clone(order);
  }

  function startMatching(order) {
    setStatus(order, "MATCHING", "system");
    order.matchLog.push({ at: iso(now()), text: "Đã gửi yêu cầu cho 3 shipper gần điểm lấy hàng" });
  }

  /* ---------- Payment (PaymentService stand-in) ---------- */
  function paymentPurpose(order) {
    if (order.status === "PENDING" || order.status === "PAYMENT_PENDING") return "order";
    if (outstanding(order) > 0) return "balance";
    return null;
  }
  function payableAmount(order) {
    const purpose = paymentPurpose(order);
    if (purpose === "order") return order.fees.delivery + order.fees.additional;
    if (purpose === "balance") return outstanding(order);
    return 0;
  }

  function chooseCod(code) {
    const order = raw(code);
    if (order.status !== "PENDING") throw new Error(t("Chỉ chọn COD khi đơn vừa được tạo."));
    if (!PAYMENT_METHODS.COD.currencies.includes(order.currency)) throw new Error(t("COD chỉ thu bằng VND. Đơn tính bằng USD cần thanh toán bằng thẻ quốc tế."));
    order.payment.method = "COD";
    startMatching(order);
    save();
  }

  /* Transaction ids must be unique: callbacks are matched by id (idempotency). */
  function newTxnId(prefix) {
    const used = new Set(load().orders.flatMap((o) => o.payment.attempts.map((a) => a.txn)));
    const base = `${prefix}${Date.now().toString().slice(-9)}`;
    let id = base;
    for (let n = 1; used.has(id); n++) id = `${base}-${n}`;
    return id;
  }

  /* Creates one payment_transactions row. Returns the transaction. */
  function beginOnlinePayment(code, method) {
    const order = raw(code);
    const purpose = paymentPurpose(order);
    if (!purpose) throw new Error(t("Đơn này không có khoản nào cần thanh toán."));
    if (!PAYMENT_METHODS[method] || !PAYMENT_METHODS[method].online) throw new Error(t("Phương thức thanh toán không hợp lệ."));
    if (!PAYMENT_METHODS[method].currencies.includes(order.currency)) throw new Error(t("Phương thức này không hỗ trợ tiền tệ của đơn."));
    if (order.payment.attempts.some((a) => a.status === "PROCESSING")) {
      throw new Error(t("Đang có một giao dịch chờ xử lý. Vui lòng hoàn tất giao dịch đó trước."));
    }
    if (purpose === "order") {
      order.payment.method = method;
      if (order.status === "PENDING") setStatus(order, "PAYMENT_PENDING", "customer");
    }
    const txn = {
      txn: newTxnId("TXN"),
      method,
      amount: payableAmount(order),
      status: "PROCESSING",
      at: iso(now()),
      purpose
    };
    order.payment.attempts.push(txn);
    save();
    return clone(txn);
  }

  /* Gateway callback. Idempotent: a transaction that is no longer PROCESSING is ignored. */
  function resolveOnlinePayment(code, txnId, outcome) {
    const order = raw(code);
    const txn = order.payment.attempts.find((a) => a.txn === txnId);
    if (!txn || txn.status !== "PROCESSING") return { order: clone(order), duplicate: true };
    txn.resolvedAt = iso(now());
    if (outcome === "approve") {
      txn.status = "SUCCESS";
      order.payment.paid += txn.amount;
      order.payment.paidAt = txn.resolvedAt;
      order.payment.status = balance(order) <= 0 ? "PAID" : order.payment.status;
      if (txn.purpose === "order") {
        setStatus(order, "PAID", "system", msg(METHOD_NAMES[txn.method || "MOMO"]));
        startMatching(order);
      }
      notify("payment", "Thanh toán thành công", msg("{amount} cho đơn {code} qua {method}.", { amount: cash(txn.amount, order), code: order.code, method: msg(METHOD_NAMES[txn.method || "MOMO"]) }), { view: "order", code: order.code });
    } else {
      txn.status = outcome === "cancel" ? "CANCELLED" : "FAILED";
      txn.reason = outcome === "cancel" ? "Bạn đã hủy giao dịch trên cổng thanh toán" : "Ngân hàng hoặc ví từ chối giao dịch";
      const failedOrderAttempts = order.payment.attempts.filter((a) => a.purpose === "order" && a.status !== "SUCCESS").length;
      notify("payment", "Thanh toán chưa thành công", msg("Đơn {code}: {reason}", { code: order.code, reason: msg(txn.reason) }), { view: "payment", code: order.code });
      if (txn.purpose === "order" && failedOrderAttempts >= MAX_PAYMENT_ATTEMPTS) {
        order.payment.status = "FAILED";
        order.cancel = { by: "system", reason: msg("Thanh toán thất bại {n} lần", { n: MAX_PAYMENT_ATTEMPTS }), at: iso(now()) };
        setStatus(order, "CANCELLED", "system", order.cancel.reason);
      }
    }
    save();
    return { order: clone(order), duplicate: false };
  }

  function remainingPaymentAttempts(order) {
    const failed = order.payment.attempts.filter((a) => a.purpose === "order" && (a.status === "FAILED" || a.status === "CANCELLED")).length;
    return Math.max(0, MAX_PAYMENT_ATTEMPTS - failed);
  }

  /* ---------- Cancellation (BR-110 to BR-118) ---------- */
  const FREE_CANCEL = ["PENDING", "PAYMENT_PENDING", "PAID", "MATCHING", "NO_SHIPPER_FOUND"];
  const FEE_CANCEL = ["ASSIGNED", "PICKING_UP"];
  const RETURN_CANCEL = ["IN_TRANSIT", "RESCHEDULED"]; // shipper is holding the package (BR-116, BR-117)

  function cancelPolicy(order) {
    const paid = order.payment.paid;
    if (FREE_CANCEL.includes(order.status)) {
      return { allowed: true, kind: "free", result: "CANCELLED", cancellationFee: 0, returnFee: 0, charges: 0, refund: paid, owe: 0 };
    }
    if (FEE_CANCEL.includes(order.status)) {
      const cancelFee = fee(order).cancellation;
      return { allowed: true, kind: "fee", result: "CANCELLED", cancellationFee: cancelFee, returnFee: 0, charges: cancelFee, refund: Math.max(0, paid - cancelFee), owe: Math.max(0, cancelFee - paid) };
    }
    if (RETURN_CANCEL.includes(order.status)) {
      const returnFee = returnFeeOf(order);
      const cancelFee = fee(order).cancellation;
      const total = order.fees.delivery + order.fees.additional + order.fees.reschedule + cancelFee + returnFee;
      return { allowed: true, kind: "return", result: "RETURNING", cancellationFee: cancelFee, returnFee, charges: total, refund: Math.max(0, paid - total), owe: Math.max(0, total - paid) };
    }
    const reasons = {
      ARRIVED: t("Shipper đã đến điểm giao. Hãy dùng các lựa chọn khi shipper chờ người nhận."),
      WAITING_FOR_RECIPIENT: t("Shipper đang chờ người nhận. Bạn có thể hẹn giao lại hoặc yêu cầu hoàn hàng."),
      COMPLETED: t("Đơn đã hoàn tất nên không thể hủy."),
      DELIVERED: t("Đơn đã giao thành công nên không thể hủy."),
      CANCELLED: t("Đơn đã được hủy trước đó.")
    };
    return { allowed: false, reason: reasons[order.status] || t("Đơn ở trạng thái này không thể hủy.") };
  }

  function cancelOrder(code, reason) {
    const order = raw(code);
    const policy = cancelPolicy(order);
    if (!policy.allowed) throw new Error(policy.reason);
    if (!reason || !reason.trim()) throw new Error(t("Vui lòng chọn lý do hủy."));
    order.cancel = { by: "customer", reason: reason.trim(), at: iso(now()) };
    order.fees.cancellation = policy.cancellationFee;
    order.payment.attempts.forEach((a) => {
      if (a.status === "PROCESSING") { a.status = "CANCELLED"; a.reason = "Đơn đã được hủy"; a.resolvedAt = iso(now()); }
    });
    if (policy.kind === "return") {
      order.fees.return = policy.returnFee;
      order.failureReason = "Người gửi hủy khi hàng đang giao";
      setStatus(order, "RETURNING", "customer", reason.trim());
    } else {
      setStatus(order, "CANCELLED", "customer", reason.trim());
      if (policy.refund > 0) {
        order.payment.refund = { amount: policy.refund, status: "PROCESSING", at: iso(now()) };
        notify("payment", "Đang hoàn tiền", msg("{amount} cho đơn {code} sẽ về {method} trong 1 đến 3 ngày làm việc.", { amount: cash(policy.refund, order), code: order.code, method: methodMsg(order) }), { view: "order", code: order.code });
      }
    }
    order.matchLog.push({ at: iso(now()), text: "Đã đóng mọi yêu cầu đang gửi cho shipper" });
    save();
    return clone(order);
  }

  /* ---------- Delivery exceptions ---------- */
  function retryMatching(code) {
    const order = raw(code);
    order.matchLog.push({ at: iso(now()), text: "Bạn yêu cầu tìm lại, đã mở rộng bán kính tìm kiếm" });
    setStatus(order, "MATCHING", "customer", "Tìm lại shipper");
    save();
  }

  function reschedule(code, atIso) {
    const order = raw(code);
    if (order.status !== "WAITING_FOR_RECIPIENT") throw new Error(t("Chỉ hẹn giao lại khi shipper đang chờ người nhận."));
    const when = new Date(atIso).getTime();
    if (!when || when < now() + 30 * MIN) throw new Error(t("Thời gian giao lại phải sau hiện tại ít nhất 30 phút."));
    order.rescheduleAt = new Date(when).toISOString();
    order.fees.reschedule += fee(order).reschedule;
    setStatus(order, "RESCHEDULED", "customer", "Người gửi hẹn giao lại");
    save();
  }

  function refuseDelivery(code) {
    const order = raw(code);
    if (order.status !== "WAITING_FOR_RECIPIENT") throw new Error(t("Chỉ dùng khi shipper đang chờ người nhận."));
    order.failureReason = "Người gửi xác nhận người nhận không thể nhận hàng";
    order.fees.return = returnFeeOf(order);
    setStatus(order, "DELIVERY_FAILED", "customer", order.failureReason);
    setStatus(order, "RETURNING", "system");
    save();
  }

  function quoteAddressChange(code, newDelivery) {
    const order = raw(code);
    const newKm = routeDistance(order.pickup, newDelivery);
    const extraKm = Math.max(0, Math.round((newKm - order.distanceKm) * 10) / 10);
    return { newKm, extraKm, additionalFee: roundUp(extraKm * fee(order).pricePerKm), currency: order.currency };
  }
  function approveAddressChange(code, newDelivery) {
    const order = raw(code);
    if (!order.addressIssue) throw new Error(t("Đơn này không có yêu cầu đổi địa chỉ."));
    const q = quoteAddressChange(code, newDelivery);
    order.history.push({ status: order.status, at: iso(now()), actor: "customer", note: msg("Đổi địa chỉ giao sang {address}", { address: `${newDelivery.detail}, ${newDelivery.area}` }) });
    order.delivery = { ...newDelivery, addressId: null };
    order.distanceKm = q.newKm;
    order.fees.additional += q.additionalFee;
    order.addressIssue = false;
    notify("payment", "Có phí phát sinh do đổi địa chỉ", msg("Đơn {code} phát sinh {amount}.", { code: order.code, amount: cash(q.additionalFee, order) }), { view: "order", code: order.code });
    save();
    return { order: clone(order), needsPayment: outstanding(order) > 0 };
  }
  function declineAddressChange(code) {
    const order = raw(code);
    order.addressIssue = false;
    order.failureReason = "Sai địa chỉ giao, người gửi không cập nhật địa chỉ mới";
    order.fees.return = returnFeeOf(order);
    setStatus(order, "DELIVERY_FAILED", "customer", order.failureReason);
    setStatus(order, "RETURNING", "system");
    save();
  }

  /* ---------- Review (BR-159 to BR-167) ---------- */
  function reviewEligibility(order) {
    if (!order) return { ok: false, reason: t("Không tìm thấy đơn hàng.") };
    if (order.review) return { ok: false, reason: t("Bạn đã đánh giá đơn này rồi. Mỗi đơn chỉ được đánh giá một lần.") };
    if (order.status !== "COMPLETED") return { ok: false, reason: t("Chỉ đánh giá được đơn đã hoàn tất.") };
    if (!order.shipper) return { ok: false, reason: t("Đơn này không có shipper để đánh giá.") };
    return { ok: true };
  }
  function submitReview(code, review) {
    const order = raw(code);
    const check = reviewEligibility(order);
    if (!check.ok) throw new Error(check.reason);
    const rating = Number(review.rating);
    if (!(rating >= 1 && rating <= 5)) throw new Error(t("Số sao phải từ 1 đến 5."));
    order.review = { rating, tags: review.tags || [], comment: (review.comment || "").slice(0, 500), at: iso(now()) };
    save();
  }

  /* ---------- Reports (BR-168 to BR-174) ---------- */
  const DISPUTE_TYPES = ["DAMAGED_PACKAGE", "LOST_PACKAGE"];
  const DISPUTE_FROM = ["IN_TRANSIT", "DELIVERY_FAILED", "RETURNING", "RETURNED"];

  function reportableOrders() {
    return listOrders().filter((o) => o.status !== "PENDING");
  }
  function submitReport(input) {
    const s = load();
    const order = raw(input.orderCode);
    if (order.status === "PENDING") throw new Error(t("Đơn vừa tạo chưa thể báo cáo."));
    if (input.about === "SHIPPER" && !order.shipper) throw new Error(t("Đơn này chưa có shipper nhận nên chưa thể báo cáo shipper. Hãy chọn báo về đơn hàng."));
    if (!REPORT_TYPES.some((x) => x.value === input.type)) throw new Error(t("Chọn loại sự cố."));
    const description = (input.description || "").trim();
    if (description.length < 10) throw new Error(t("Mô tả cần ít nhất 10 ký tự để bộ phận hỗ trợ hiểu vấn đề."));
    const id = `RP-${Math.max(1042, ...s.reports.map((r) => Number(r.id.split("-")[1]))) + 1}`;
    const report = {
      id, orderCode: order.code, about: input.about, type: input.type, description: description.slice(0, 1000),
      files: input.files || [], status: "OPEN", adminNote: null, createdAt: iso(now()),
      history: [{ status: "OPEN", at: iso(now()) }]
    };
    s.reports.unshift(report);
    order.reportIds.push(id);
    let disputed = false;
    if (DISPUTE_TYPES.includes(input.type) && DISPUTE_FROM.includes(order.status)) {
      setStatus(order, "DISPUTED", "customer", msg("Báo cáo {id}", { id }));
      disputed = true;
    }
    notify("report", "Đã tiếp nhận báo cáo", msg("{id} cho đơn {code} đang chờ bộ phận hỗ trợ xem xét.", { id, code: order.code }), { view: "reports", id });
    save();
    return { report: clone(report), disputed };
  }
  function listReports() { return clone(load().reports); }
  function getReport(id) { const r = load().reports.find((x) => x.id === id); return r ? clone(r) : null; }

  /* ---------- Notifications ---------- */
  function listNotifications() {
    return clone(load().notifications).sort((a, b) => b.at.localeCompare(a.at));
  }
  function unreadCount() { return load().notifications.filter((n) => !n.read).length; }
  function markRead(id) {
    const n = load().notifications.find((x) => x.id === id);
    if (n && !n.read) { n.read = true; save(); }
  }
  function markAllRead() { load().notifications.forEach((n) => { n.read = true; }); save(); }

  /* ---------- Account ---------- */
  function getAccount() { return clone(load().account); }
  function updateProfile(fields) {
    const a = load().account;
    const emailChanged = fields.email.trim().toLowerCase() !== a.email.toLowerCase();
    a.fullName = fields.fullName.trim();
    if (emailChanged) { a.email = fields.email.trim(); a.emailVerified = false; }
    save();
    return { emailChanged };
  }
  function verifyEmailDemo() { load().account.emailVerified = true; save(); }

  function requestOtp(phone) {
    const s = load();
    if (s.otp && s.otp.lockedUntil && Date.parse(s.otp.lockedUntil) > now()) {
      throw new Error(t("Bạn đã nhập sai quá nhiều lần. Vui lòng thử lại sau 15 phút."));
    }
    if (s.otp && s.otp.phone === phone && Date.parse(s.otp.resendAt) > now()) {
      throw new Error(t("Vui lòng chờ hết thời gian đếm ngược rồi gửi lại mã."));
    }
    s.otpSends = (s.otpSends || []).filter((x) => Date.parse(x) > now() - 60 * MIN);
    if (s.otpSends.length >= 5) throw new Error(t("Bạn đã yêu cầu mã 5 lần trong 1 giờ. Vui lòng thử lại sau."));
    s.otpSends.push(iso(now()));
    const code = String(Math.floor(100000 + Math.random() * 900000));
    s.otp = { phone, code, attempts: 0, expiresAt: iso(now() + OTP_TTL_MS), resendAt: iso(now() + 60 * 1000), lockedUntil: null };
    save();
    return { code, resendAt: s.otp.resendAt, expiresAt: s.otp.expiresAt };
  }
  function verifyOtp(input) {
    const s = load();
    const otp = s.otp;
    if (!otp) throw new Error(t("Hãy gửi mã xác thực trước."));
    if (otp.lockedUntil && Date.parse(otp.lockedUntil) > now()) throw new Error(t("Đã khóa nhập mã do sai quá 5 lần. Thử lại sau 15 phút."));
    if (Date.parse(otp.expiresAt) < now()) throw new Error(t("Mã đã hết hạn. Vui lòng gửi lại mã mới."));
    if (String(input).trim() !== otp.code) {
      otp.attempts += 1;
      if (otp.attempts >= OTP_MAX_ATTEMPTS) otp.lockedUntil = iso(now() + 15 * MIN);
      save();
      const left = OTP_MAX_ATTEMPTS - otp.attempts;
      throw new Error(left > 0 ? t`Mã không đúng. Bạn còn ${left} lần thử.` : t("Mã không đúng. Đã khóa nhập mã trong 15 phút."));
    }
    s.account.phone = otp.phone;
    s.account.phoneVerified = true;
    s.otp = null;
    notify("account", "Đã xác thực số điện thoại", msg("Số {phone} đã được xác thực.", { phone: otp.phone }), { view: "account" });
    save();
  }
  function otpLocked() {
    const otp = load().otp;
    return Boolean(otp && otp.lockedUntil && Date.parse(otp.lockedUntil) > now());
  }
  function changePassword(current, next) {
    if (!current) throw new Error(t("Nhập mật khẩu hiện tại."));
    if (next.length < 8) throw new Error(t("Mật khẩu mới cần ít nhất 8 ký tự."));
    if (current === next) throw new Error(t("Mật khẩu mới phải khác mật khẩu hiện tại."));
    load().account.passwordChangedAt = iso(now());
    save();
  }

  /* ---------- Addresses (BR-08 to BR-10) ---------- */
  function listAddresses() {
    return clone(load().addresses).sort((a, b) => Number(b.isDefault) - Number(a.isDefault) || a.createdAt.localeCompare(b.createdAt));
  }
  function addressUsage(id) {
    return load().orders.filter((o) => o.pickup.addressId === id || o.delivery.addressId === id).length;
  }
  function saveAddress(input) {
    const s = load();
    const fields = {
      label: input.label, receiverName: input.receiverName.trim(), receiverPhone: input.receiverPhone.replace(/\s/g, ""),
      detail: input.detail.trim(), area: input.area
    };
    let address = input.id ? s.addresses.find((a) => a.id === input.id) : undefined;
    if (address) Object.assign(address, fields);
    else {
      address = { id: uid("ADR"), createdAt: iso(now()), isDefault: false, ...fields };
      s.addresses.push(address);
    }
    if (input.isDefault || s.addresses.length === 1) setDefaultAddress(address.id, false);
    save();
    return clone(address);
  }
  function setDefaultAddress(id, persist = true) {
    load().addresses.forEach((a) => { a.isDefault = a.id === id; });
    if (persist) save();
  }
  function deleteAddress(id) {
    const s = load();
    if (addressUsage(id) > 0) throw new Error(t("Địa chỉ đã dùng trong đơn hàng nên cần được giữ lại để bảo toàn lịch sử."));
    const wasDefault = s.addresses.find((a) => a.id === id)?.isDefault;
    s.addresses = s.addresses.filter((a) => a.id !== id);
    if (wasDefault && s.addresses[0]) s.addresses[0].isDefault = true;
    save();
  }

  /* ---------- Simulation: events that come from shippers, gateways and admins ---------- */
  function pickShipper(order) {
    return SHIPPERS[hash(order.code + order.history.length) % SHIPPERS.length];
  }
  const SIM = {
    accept(order) {
      order.shipper = pickShipper(order);
      order.eta = iso(now() + (order.durationMin + 10) * MIN);
      order.matchLog.push({ at: iso(now()), text: msg("{name} đã nhận đơn", { name: order.shipper.name }) });
      setStatus(order, "ASSIGNED", "shipper");
    },
    reject(order) { order.matchLog.push({ at: iso(now()), text: "Một shipper đã từ chối, đang gửi cho shipper khác" }); },
    timeout(order) { order.matchLog.push({ at: iso(now()), text: "Một shipper không phản hồi kịp, đang gửi cho shipper khác" }); },
    noShipper(order) {
      order.matchLog.push({ at: iso(now()), text: "Đã mở rộng phạm vi nhưng chưa có shipper nhận đơn" });
      setStatus(order, "NO_SHIPPER_FOUND", "system");
    },
    pickup(order) { setStatus(order, "PICKING_UP", "shipper"); },
    pickedUp(order) { order.location.updatedAt = iso(now()); setStatus(order, "IN_TRANSIT", "shipper"); },
    shipperCancel(order) {
      order.matchLog.push({ at: iso(now()), text: msg("{name} hủy nhận đơn, đang tìm shipper khác", { name: order.shipper ? order.shipper.name : "Shipper" }) });
      setStatus(order, "SHIPPER_CANCELLED", "shipper", "Xe gặp sự cố");
      order.shipper = null;
      setStatus(order, "MATCHING", "system");
    },
    arrive(order) { setStatus(order, "ARRIVED", "shipper"); },
    wait(order) { setStatus(order, "WAITING_FOR_RECIPIENT", "shipper"); },
    deliver(order) {
      setStatus(order, "DELIVERED", "shipper");
      if (order.payment.method === "COD") {
        const collect = order.fees.delivery + order.fees.additional + order.fees.reschedule;
        order.payment.paid += collect;
        order.payment.paidAt = iso(now());
        order.payment.status = "PAID";
        order.payment.attempts.push({ txn: newTxnId("COD"), method: "COD", amount: collect, status: "SUCCESS", at: iso(now()), purpose: "order" });
      }
    },
    complete(order) { setStatus(order, "COMPLETED", "system"); },
    codRefused(order) {
      order.failureReason = "Người nhận từ chối thanh toán";
      order.fees.return = returnFeeOf(order);
      setStatus(order, "DELIVERY_FAILED", "shipper", order.failureReason);
      setStatus(order, "RETURNING", "system");
    },
    noRecipient(order) {
      order.failureReason = "Không liên lạc được người nhận sau thời gian chờ";
      order.fees.return = returnFeeOf(order);
      setStatus(order, "DELIVERY_FAILED", "shipper", order.failureReason);
      setStatus(order, "RETURNING", "system");
    },
    // Scenario 16, branch 1: the trip can continue with a replacement shipper.
    replaceShipper(order) {
      const old = order.shipper;
      if (!old) return;
      const next = SHIPPERS.filter((x) => x.id !== old.id)[hash(order.code) % (SHIPPERS.length - 1)];
      order.history.push({ status: order.status, at: iso(now()), actor: "shipper", note: msg("{old} gặp sự cố, bàn giao kiện hàng cho {next}", { old: old.name, next: next.name }) });
      order.matchLog.push({ at: iso(now()), text: msg("{next} nhận tiếp đơn thay {old}", { old: old.name, next: next.name }) });
      order.shipper = next;
      order.eta = iso(now() + (order.durationMin + 15) * MIN);
      notify("order", "Đổi shipper giao tiếp", msg("{old} gặp sự cố. {next} ({plate}) đã nhận tiếp đơn {code}.", { old: old.name, next: next.name, plate: next.plate, code: order.code }), { view: "tracking", code: order.code });
    },
    // Scenario 16, branch 2: the trip cannot continue. Not the customer's fault, so no
    // return fee and the delivery fee is waived (refunded once the package is back).
    incident(order) {
      order.failureReason = "Shipper gặp sự cố trên đường giao và không thể tiếp tục";
      order.fees.return = 0;
      order.fees.waived = order.fees.delivery + order.fees.additional + order.fees.reschedule;
      setStatus(order, "DELIVERY_FAILED", "shipper", order.failureReason);
      setStatus(order, "RETURNING", "system");
    },
    addressIssue(order) {
      order.addressIssue = true;
      order.history.push({ status: order.status, at: iso(now()), actor: "shipper", note: "Không tìm thấy người nhận tại địa chỉ giao" });
      notify("order", "Shipper không tìm thấy địa chỉ", msg("Đơn {code}: hãy cập nhật địa chỉ giao hoặc chọn hoàn hàng.", { code: order.code }), { view: "tracking", code: order.code });
    },
    resumeAfterReschedule(order) { setStatus(order, "IN_TRANSIT", "shipper", "Giao lại theo lịch hẹn"); },
    returned(order) {
      setStatus(order, "RETURNED", "shipper");
      const surplus = order.payment.paid - charges(order);
      if (surplus > 0 && !order.payment.refund) {
        order.payment.refund = { amount: surplus, status: "PROCESSING", at: iso(now()) };
        notify("payment", "Đang hoàn tiền", msg("{amount} cho đơn {code} sẽ về {method} trong 1 đến 3 ngày làm việc.", { amount: cash(surplus, order), code: order.code, method: methodMsg(order) }), { view: "order", code: order.code });
      }
    },
    refundDone(order) {
      if (order.payment.refund) {
        order.payment.refund.status = "DONE";
        order.payment.refund.at = iso(now());
        order.payment.status = "REFUNDED";
        notify("payment", "Đã hoàn tiền", msg("{amount} của đơn {code} đã về {method}.", { amount: cash(order.payment.refund.amount, order), code: order.code, method: methodMsg(order) }), { view: "order", code: order.code });
      }
    }
  };

  function simulate(code, action) {
    const order = raw(code);
    if (!SIM[action]) throw new Error(`Unknown action ${action}`);
    SIM[action](order);
    save();
    return clone(order);
  }

  /* Which simulated events make sense for an order right now. */
  function simulationsFor(order) {
    const list = {
      MATCHING: [["accept", t("Shipper nhận đơn")], ["reject", t("Shipper từ chối")], ["timeout", t("Shipper không phản hồi")], ["noShipper", t("Không tìm được shipper")]],
      ASSIGNED: [["pickup", t("Shipper đi lấy hàng")], ["shipperCancel", t("Shipper hủy nhận đơn")]],
      PICKING_UP: [["pickedUp", t("Shipper đã lấy hàng")], ["shipperCancel", t("Shipper hủy nhận đơn")]],
      IN_TRANSIT: order.addressIssue ? [] : [["arrive", t("Shipper đến nơi")], ["addressIssue", t("Không tìm thấy địa chỉ")], ["replaceShipper", t("Sự cố: đổi shipper")], ["incident", t("Sự cố: không giao được")]],
      ARRIVED: [["deliver", t("Giao thành công")], ["wait", t("Không có người nhận")], ...(order.payment.method === "COD" ? [["codRefused", t("Người nhận từ chối trả tiền")] ] : [])],
      WAITING_FOR_RECIPIENT: [["deliver", t("Người nhận xuất hiện")], ["noRecipient", t("Hết thời gian chờ")]],
      RESCHEDULED: [["resumeAfterReschedule", t("Bắt đầu giao lại")]],
      DELIVERED: [["complete", t("Hệ thống hoàn tất đơn")]],
      RETURNING: [["returned", t("Đã trả hàng cho người gửi")]],
      CANCELLED: order.payment.refund && order.payment.refund.status === "PROCESSING" ? [["refundDone", t("Hoàn tiền xong")]] : [],
      RETURNED: order.payment.refund && order.payment.refund.status === "PROCESSING" ? [["refundDone", t("Hoàn tiền xong")]] : []
    };
    return (list[order.status] || []).map(([action, label]) => ({ action, label }));
  }

  const REPORT_NOTICE = {
    UNDER_REVIEW: "Báo cáo {id} đang được xem xét",
    RESOLVED: "Báo cáo {id} đã được giải quyết",
    REJECTED: "Báo cáo {id} bị từ chối"
  };
  function simulateReportReview(id, outcome) {
    const r = load().reports.find((x) => x.id === id);
    if (!r) return;
    if (r.status === "OPEN") {
      r.status = "UNDER_REVIEW";
    } else if (r.status === "UNDER_REVIEW") {
      r.status = outcome === "reject" ? "REJECTED" : "RESOLVED";
      r.adminNote = outcome === "reject"
        ? "Bằng chứng chưa đủ để xác định lỗi trong quá trình giao. Bạn có thể gửi báo cáo mới kèm hình ảnh rõ hơn."
        : "SOMI đã xác minh sự cố và hoàn 100% phí giao hàng cho đơn này.";
    } else return;
    r.history.push({ status: r.status, at: iso(now()) });
    notify("report", msg(REPORT_NOTICE[r.status], { id: r.id }), msg("Báo cáo cho đơn {code} vừa được cập nhật.", { code: r.orderCode }), { view: "reports", id: r.id });
    save();
  }

  function setAccountState(stateName) {
    const a = load().account;
    if (stateName === "active") { a.status = "ACTIVE"; a.phoneVerified = true; }
    if (stateName === "unverified") { a.status = "ACTIVE"; a.phoneVerified = false; }
    if (stateName === "suspended") { a.status = "SUSPENDED"; }
    save();
  }
  function setSim(key, value) { load().sim[key] = value; save(); }
  function getSim() { return clone(load().sim); }

  return {
    // constants
    STATUS, GROUPS, ON_THE_ROAD, TRANSITIONS, PACKAGE_TYPES, AREAS, PAYMENT_METHODS, REPORT_TYPES, REPORT_STATUS,
    CANCEL_REASONS, PRICING, USD_RATE, MAX_WEIGHT_KG, MAX_QUANTITY, MAX_PAYMENT_ATTEMPTS, OTP_MAX_ATTEMPTS,
    // formatting
    formatMoney,
    // orders
    listOrders, getOrder, quote, surchargeFor, returnFeeOf, createOrder, charges, balance, outstanding, canTransition,
    // payment
    methodsFor, paymentPurpose, payableAmount, chooseCod, beginOnlinePayment, resolveOnlinePayment, remainingPaymentAttempts,
    // cancellation & exceptions
    cancelPolicy, cancelOrder, retryMatching, reschedule, refuseDelivery, quoteAddressChange, approveAddressChange, declineAddressChange,
    // review & reports
    reviewEligibility, submitReview, reportableOrders, submitReport, listReports, getReport,
    // notifications
    listNotifications, unreadCount, markRead, markAllRead,
    // account & addresses
    getAccount, updateProfile, verifyEmailDemo, requestOtp, verifyOtp, otpLocked, changePassword,
    listAddresses, addressUsage, saveAddress, setDefaultAddress, deleteAddress,
    // simulation (demo only)
    simulate, simulationsFor, simulateReportReview, setAccountState, setSim, getSim, reset
  };
}

window.SomiStore = createSomiStore();
