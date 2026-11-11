"use strict";
// Trang Tài khoản và hồ sơ: hiển thị hồ sơ, trạng thái duyệt KYC và lưu thông tin phương tiện qua API.
(() => {
  const api = window.SomiShipper;
  const STATUS = {
    PENDING: "Đang chờ Admin duyệt", APPROVED: "Đã được duyệt",
    NEEDS_INFO: "Admin yêu cầu bổ sung thông tin", REJECTED: "Hồ sơ chưa được chấp nhận",
  };

  function fill(data, kyc) {
    const profile = data.profile;
    const name = document.getElementById("shipperName");
    if (name) { name.value = profile.full_name; name.readOnly = true; } // đổi họ tên đi qua account/profile.php của nền tảng
    const plate = document.getElementById("vehiclePlate");
    if (plate) plate.value = profile.vehicle_registration;
    const type = document.getElementById("vehicleType");
    if (type) {
      // Loại xe lưu dạng chữ tự do từ lúc đăng ký, nên thêm lựa chọn nếu danh sách chưa có.
      if (![...type.options].some((option) => option.value === profile.vehicle_type)) type.add(new Option(profile.vehicle_type, profile.vehicle_type));
      type.value = profile.vehicle_type;
    }
    const line = document.getElementById("kycStatusLine");
    if (line) line.textContent = STATUS[profile.verification_status] || profile.verification_status;
    const hint = document.getElementById("kycHint");
    if (hint) {
      hint.textContent = kyc.can_resubmit
        ? "Lý do: " + (kyc.rejection_reason || "chưa ghi rõ") + ". Vui lòng cập nhật giấy tờ và nộp lại hồ sơ."
        : profile.verification_status === "APPROVED" ? "Lưu ý: đổi thông tin phương tiện sẽ đưa hồ sơ về trạng thái chờ duyệt lại." : "";
    }
  }

  async function load() {
    try {
      const [data, kyc] = await Promise.all([api.profile(), api.kyc()]);
      fill(data, kyc);
    } catch (error) {
      api.toast(error.message);
    }
  }

  async function saveVehicle(event) {
    const button = event.currentTarget;
    button.disabled = true;
    try {
      await api.updateVehicle({
        vehicle_type: document.getElementById("vehicleType").value,
        vehicle_registration: document.getElementById("vehiclePlate").value,
      });
      api.toast("Đã lưu thông tin phương tiện.");
      await load();
    } catch (error) {
      api.toast(error.message);
    } finally {
      button.disabled = false;
    }
  }

  document.addEventListener("DOMContentLoaded", () => {
    if (!document.getElementById("page-account")) return;
    document.getElementById("saveVehicleBtn")?.addEventListener("click", saveVehicle);
    load();
  });
})();
