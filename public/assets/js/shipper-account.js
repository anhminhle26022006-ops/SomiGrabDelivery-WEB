"use strict";
// Trang Tài khoản và hồ sơ: hiển thị hồ sơ, trạng thái duyệt KYC và lưu thông tin phương tiện qua API.
(() => {
  const api = window.SomiShipper;
  const STATUS = {
    PENDING: "Đang chờ Admin duyệt", APPROVED: "Đã được duyệt",
    NEEDS_INFO: "Admin yêu cầu bổ sung thông tin", REJECTED: "Hồ sơ chưa được chấp nhận",
  };

  function fill(data, kyc) {
    document.getElementById('kycResubmitForm').hidden=!kyc.can_resubmit;
    const profile = data.profile;
    const name = document.getElementById("shipperName");
    if (name) { name.value = profile.full_name; name.readOnly = true; } // đổi họ tên đi qua account/profile.php của nền tảng
    // Thông tin liên hệ thật của tài khoản, chỉ để xem. Đổi email hay số điện thoại cần xác minh lại
    // nên đi qua luồng của nền tảng chứ không sửa trực tiếp ở đây.
    const account = data.account || {};
    const setText = (id, value) => { const el = document.getElementById(id); if (el) el.textContent = value || "—"; };
    const setValue = (id, value) => { const el = document.getElementById(id); if (el) { el.value = value || ""; el.readOnly = true; } };
    setText("accountName", profile.full_name);
    setText("accountCode", account.code);
    setValue("shipperPhone", account.phone);
    setValue("shipperEmail", account.email);
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
    document.getElementById('kycResubmitForm')?.addEventListener('submit',async event=>{
      event.preventDefault();const form=event.currentTarget,button=form.querySelector('button'),status=document.getElementById('kycUploadStatus');button.disabled=true;
      try{const file=form.elements.document_file.files[0];if(!file||file.size>5*1024*1024)throw new Error('Chọn file tối đa 5 MiB.');
        status.textContent='Đang tải giấy tờ…';const uploaded=await api.uploadPrivate(file,'KYC');
        await api.resubmitKyc({documents:[{document_type:form.elements.document_type.value,reference_file_id:uploaded.file_id}]});
        form.reset();status.textContent='Đã nộp hồ sơ. Đang chờ Admin duyệt.';await load();
      }catch(error){status.textContent=error.message;}finally{button.disabled=false;}
    });
    load();
  });
})();
