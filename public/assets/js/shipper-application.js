(() => {
  'use strict';
  const t = (vi, en) => document.documentElement.lang === 'en' ? en : vi;
  document.querySelectorAll('[data-shipper-application-form]').forEach(form => {
    const uploads = new WeakMap();
    const error = form.querySelector('[data-switch-error]');
    const progress = form.querySelector('[data-sa-progress]');
    const today = new Intl.DateTimeFormat('en-CA', {timeZone:'Asia/Ho_Chi_Minh', year:'numeric',month:'2-digit',day:'2-digit'}).format(new Date());
    form.elements.license_issued_on.max = today;
    form.elements.license_expires_on.min = today;
    form.elements.insurance_expires_on.min = today;
    function sync() {
      const motorized = form.elements.vehicle_type.value !== 'BICYCLE';
      form.querySelectorAll('[data-sa-motorized]').forEach(group => {
        group.hidden = !motorized;
        group.querySelectorAll('input').forEach(input => {input.disabled = !motorized; input.required = motorized && input.type !== 'checkbox';});
      });
      const expiry = form.querySelector('[data-sa-expiry]');
      expiry.hidden = !motorized || form.elements.license_no_expiry.checked;
      form.elements.license_expires_on.disabled = expiry.hidden;
      form.elements.license_expires_on.required = !expiry.hidden;
      const authorization = form.querySelector('[data-sa-authorization]');
      authorization.hidden = form.elements.vehicle_ownership.value !== 'BORROWED';
      const file = authorization.querySelector('input');file.disabled = authorization.hidden;file.required = !authorization.hidden;
    }
    form.addEventListener('change', event => {
      sync(); error.hidden = true;
      if (event.target.matches('[data-sa-document]')) {
        uploads.delete(event.target);
        event.target.closest('label').querySelector('[data-sa-upload-status]').textContent = '';
      }
    });
    sync();
    // Prefill authorised demo accounts only; documents and consent stay manual.
    const edited = new Set();
    form.addEventListener('input', event => edited.add(event.target.name));
    form.addEventListener('change', event => edited.add(event.target.name));
    fetch('public/api/account/profile.php', {credentials:'same-origin'})
      .then(async response => response.ok ? response.json() : null)
      .then(result => {
        const profile = result?.profile;
        const demoEmails = ['dinhthianhthu9998@gmail.com', 'sunheenguyenn@gmail.com'];
        if (!profile || !demoEmails.includes(profile.email?.toLowerCase())) return;
        const nextYear = Number(today.slice(0,4)) + 1;
        const defaults = {
          residential_address:'Địa chỉ demo, TP. Hồ Chí Minh',
          operating_area:'TP. Hồ Chí Minh',
          emergency_contact_name:'Người liên hệ demo',
          emergency_contact_phone:'0900000000',
          emergency_contact_relationship:'Người thân (demo)',
          vehicle_type:'MOTORBIKE',
          vehicle_registration:'DEMO-0001',
          vehicle_brand:'Honda', vehicle_model:'Vision', vehicle_color:'Trắng',
          vehicle_year:'2023', vehicle_owner_name:profile.full_name,
          vehicle_ownership:'OWNED', license_number:'DEMO123456', license_class:'A1',
          license_issued_on:'2023-01-01', license_expires_on:`${nextYear}-12-31`,
          insurance_expires_on:`${nextYear}-12-31`
        };
        for (const [name,value] of Object.entries(defaults)) {
          const field = form.elements.namedItem(name);
          if (field && !edited.has(name) && !field.value) field.value = value;
        }
        const note = document.createElement('p');note.className = 'sa-note';
        note.dataset.copyVi = 'Thông tin demo đã được điền sẵn. Hãy thay bằng thông tin thật và tải giấy tờ hợp lệ trước khi gửi hồ sơ.';
        note.dataset.copyEn = 'Demo details are prefilled. Replace them with real information and upload valid documents before submitting.';
        note.textContent = t(note.dataset.copyVi, note.dataset.copyEn);
        form.querySelector('.sa-intro').after(note);
        sync();
      }).catch(() => {});
    form.addEventListener('submit', async event => {
      event.preventDefault();
      const button = form.querySelector('[type=submit]');
      if (button.disabled || !form.reportValidity()) return;
      error.hidden = true;
      const fields = [...form.querySelectorAll('input,select,button')];
      const states = fields.map(field=>field.disabled);
      try {
        const inputs = [...form.querySelectorAll('[data-sa-document]')].filter(input=>!input.disabled);
        // Validate every selected file before any network upload.
        for (const input of inputs) {
          const file = input.files[0];
          const types = input.accept.split(',');
          if (!file || file.size < 1 || file.size > 5*1024*1024 || !types.includes(file.type)) {
            throw new Error(t('File phải là ảnh/PDF hợp lệ, tối đa 5 MB: ', 'Choose a valid image/PDF up to 5 MB: ') + input.closest('label').querySelector('span').textContent);
          }
        }
        const data = Object.fromEntries(new FormData(form));
        data.license_no_expiry = form.elements.license_no_expiry.checked;
        data.consent = form.elements.consent.checked;
        data.target = 'SHIPPER';
        data.documents = [];
        fields.forEach(field=>field.disabled = true);
        form.setAttribute('aria-busy', 'true');
        for (const [index,input] of inputs.entries()) {
          progress.textContent = t(`Đang tải giấy tờ ${index+1}/${inputs.length}…`,`Uploading document ${index+1}/${inputs.length}…`);
          let uploaded = uploads.get(input);
          if (!uploaded) {
            const body = new FormData();body.set('purpose','KYC');body.set('file',input.files[0]);
            const response = await fetch('public/api/storage.php', {method:'POST',body,credentials:'same-origin',signal:AbortSignal.timeout(60000)});
            const result = await response.json();
            if (!response.ok) throw new Error(result.error || t('Không thể tải giấy tờ.','Document upload failed.'));
            uploaded = result;uploads.set(input,uploaded);
          }
          input.closest('label').querySelector('[data-sa-upload-status]').textContent = t('Đã tải lên riêng tư','Uploaded privately');
          data.documents.push({document_type:input.dataset.saDocument,reference_file_id:uploaded.file_id});
        }
        progress.textContent = t('Đang gửi hồ sơ…','Submitting application…');
        const response = await fetch('public/api/auth/switch-workspace.php', {method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json'},body:JSON.stringify(data),signal:AbortSignal.timeout(60000)});
        const result = await response.json();
        if (!response.ok) throw new Error(result.error || t('Không thể gửi hồ sơ.','Application submission failed.'));
        window.location.href = '?page=shipper';
      } catch (cause) {
        error.textContent = cause.name === 'TimeoutError' ? t('Yêu cầu quá thời gian. Hãy thử lại; giấy tờ đã tải sẽ được giữ trong lần thử này.','Request timed out. Retry; already uploaded documents will be reused.') : cause.message;
        error.hidden = false;error.focus();progress.textContent = '';
      } finally {
        fields.forEach((field,index)=>field.disabled = states[index]);
        form.removeAttribute('aria-busy');
      }
    });
  });
})();
