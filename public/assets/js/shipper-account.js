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
    const application = data.application || {};
    const fields = {shipperArea:'operating_area',shipperAddress:'residential_address',vehicleColor:'vehicle_color',licenseExpiry:'license_expires_on',insuranceExpiry:'insurance_expires_on',serviceArea:'operating_area',emergencyName:'emergency_contact_name',emergencyPhone:'emergency_contact_phone'};
    for (const [id,key] of Object.entries(fields)) {
      const input = document.getElementById(id);if (!input) continue;
      input.disabled=false;input.readOnly=true;input.value=application[key] || '';input.placeholder='Chưa có thông tin';
    }
    const brand=document.getElementById('vehicleBrand');if(brand){brand.disabled=false;brand.readOnly=true;brand.value=[application.vehicle_brand,application.vehicle_model].filter(Boolean).join(' ');brand.placeholder='Chưa có thông tin';}
    const documents=document.getElementById('kycDocuments');
    if(documents)documents.innerHTML=(kyc.documents||[]).map(file=>{
      const url='public/api/storage.php?'+new URLSearchParams({file_id:file.file_id,content:'1'});
      return '<p>'+api.esc(file.document_type)+' · '+api.esc(file.original_name||'')+' '+(file.file_id?'<a target="_blank" rel="noopener" href="'+api.esc(url+'&disposition=inline')+'">Xem tài liệu</a> · <a href="'+api.esc(url)+'" download>Tải xuống</a>':'Tệp không còn khả dụng')+'</p>';
    }).join('') || '<p>Chưa có tài liệu đã nộp.</p>';
    api.paintProfile?.(data);
    const name = document.getElementById("shipperName");
    if (name) name.value = profile.full_name;
    document.querySelectorAll('[data-shipper-name]').forEach(el => { el.textContent = profile.full_name; });
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
      const account = data.account ? {profile:{...data.account,user_id:data.account.code}} : await api.accountProfile();
      fill(data, kyc);
      document.getElementById('shipperPhone').value = account.profile.phone || '';
      document.getElementById('shipperEmail').value = account.profile.email || '';
      document.querySelector('[data-shipper-account-id]').textContent = 'Shipper · #' + account.profile.user_id;
      document.getElementById('saveProfileBtn').disabled = false;
      document.getElementById('saveVehicleBtn').disabled = false;
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
    document.getElementById('saveProfileBtn')?.addEventListener('click', async event => {
      const button = event.currentTarget;
      const name = document.getElementById('shipperName');
      if (!name.reportValidity()) return;
      button.disabled = true;
      try {
        await api.updateName(name.value.trim());
        await load();
        api.toast('Đã lưu họ tên.');
      } catch (error) { api.toast(error.message); }
      finally { button.disabled = false; }
    });
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
