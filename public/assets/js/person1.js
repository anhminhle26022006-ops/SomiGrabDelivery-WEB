(() => {
  'use strict';
  const root = document.querySelector('[data-person1-workspace]');
  if (!root) return;
  const admin = root.dataset.role === 'admin';
  const content = root.querySelector('[data-person1-content]');
  const status = root.querySelector('[data-person1-status]');
  let current = admin ? 'accounts' : 'profile';
  let pending = false;
  let generation = 0;
  const h = value => String(value ?? '').replace(/[&<>"']/g, x => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[x]));
  async function api(path, body) {
    const [endpoint, query] = path.split('?');
    const response = await fetch(`public/api/${endpoint}.php${query ? '?' + query : ''}`, { method:body?'POST':'GET', credentials:'same-origin',
      headers:body?{'Content-Type':'application/json'}:{}, body:body?JSON.stringify(body):undefined, signal:AbortSignal.timeout(30000) });
    const result = await response.json();
    if (!response.ok) throw new Error(result.error || 'Không thể hoàn tất yêu cầu.');
    return result.data ?? result;
  }
  const field = (name, label, value='', type='text', extra='') => `<label>${h(label)}<input name="${name}" type="${type}" value="${h(value)}" ${extra}></label>`;
  const button = (label, action, id) => `<button class="button button-light" type="button" data-action="${action}" data-id="${h(id)}">${h(label)}</button>`;
  const submit = '<button class="button button-dark" type="submit">Lưu</button>';
  function table(headers, rows) {
    return rows.length ? `<div class="person1-table"><table><thead><tr>${headers.map(x=>`<th>${h(x)}</th>`).join('')}</tr></thead><tbody>${rows.map(row=>`<tr>${row.map(x=>`<td>${x}</td>`).join('')}</tr>`).join('')}</tbody></table></div>` : '<p>Chưa có dữ liệu.</p>';
  }
  function orderDetail(order) {
    const amount = new Intl.NumberFormat('vi-VN',{style:'currency',currency:order.currency_code}).format(Number(order.total_charge_minor)/(order.currency_code==='USD'?100:1));
    const labels={PENDING_PAYMENT:'Chờ thanh toán',SEARCHING_SHIPPER:'Đang tìm Shipper',NO_SHIPPER_FOUND:'Chưa tìm được Shipper',SHIPPER_ASSIGNED:'Shipper đã nhận',PICKED_UP:'Đã lấy hàng',IN_DELIVERY:'Đang giao',DELIVERED:'Đã giao',RETURNING:'Đang hoàn hàng',RETURNED:'Đã hoàn hàng',CANCELLED:'Đã hủy'};
    return `<h2>${h(order.order_code)}</h2><p>${h(labels[order.status]||order.status)} · ${h(amount)}</p><h3>Điểm lấy hàng</h3><p>${h(order.pickup_address)}<br>${h(order.sender_name)} · ${h(order.sender_phone)}</p><h3>Điểm giao hàng</h3><p>${h(order.delivery_address)}<br>${h(order.receiver_name)} · ${h(order.receiver_phone)}</p><h3>Lịch sử đơn</h3>`+
      table(['Thời gian','Trạng thái','Ghi chú'],order.history.map(item=>[h(new Date(item.created_at).toLocaleString('vi-VN')),h(labels[item.new_status]||item.new_status),h(item.note)]))+
      (!admin&&['PENDING_PAYMENT','SEARCHING_SHIPPER','NO_SHIPPER_FOUND'].includes(order.status)?button('Hủy đơn','cancel',order.order_id):'');
  }
  function addressForm(address={}) {
    return `<form data-action="address-save">${address.address_id?`<input name="address_id" type="hidden" value="${h(address.address_id)}">`:''}
      ${field('label','Tên gợi nhớ',address.label,'text','maxlength="60"')}
      ${field('address_detail','Địa chỉ',address.address_detail,'text','required maxlength="255"')}
      ${['province','district','ward'].map((x,i)=>field(x,['Tỉnh/thành','Quận/huyện','Phường/xã'][i],address[x],'text','maxlength="120"')).join('')}
      ${field('latitude','Vĩ độ',address.latitude,'number','required min="-90" max="90" step="0.0000001"')}
      ${field('longitude','Kinh độ',address.longitude,'number','required min="-180" max="180" step="0.0000001"')}
      <label><input name="is_default" type="checkbox" ${address.is_default?'checked':''}>Địa chỉ mặc định</label>${submit}</form>`;
  }
  let records = [];
  async function render() {
    const requestGeneration = ++generation;
    status.textContent = 'Đang tải…';
    root.querySelectorAll('[data-person1-tab]').forEach(b=>b.setAttribute('aria-current',String(b.dataset.person1Tab===current)));
    try {
      let html = '';
      if (current==='profile') {
        const p=await api('auth/profile');
        document.querySelectorAll('[data-account-name]').forEach(el=>el.textContent=p.full_name);
        document.querySelectorAll('[data-account-initials]').forEach(el=>el.textContent=p.full_name.split(/\s+/).map(x=>x[0]).slice(0,2).join(''));
        html=`<p>${h(p.email)} · ${h(p.phone)} · ${h(p.account_status)}</p><form data-action="profile">${field('full_name','Họ tên',p.full_name,'text','required maxlength="150"')}<label>Ngôn ngữ<select name="app_language"><option value="vi" ${p.app_language==='vi'?'selected':''}>Tiếng Việt</option><option value="en" ${p.app_language==='en'?'selected':''}>English</option></select></label>${submit}</form>`;
      } else if (current==='addresses') {
        const data=await api('customer/addresses'); records=data;
        html=table(['Địa chỉ','Tọa độ','Thao tác'],data.map(a=>[`${h(a.label)}${a.is_default?' (mặc định)':''}<br>${h(a.address_detail)}`,`${h(a.latitude)}, ${h(a.longitude)}`,button('Sửa','address-edit',a.address_id)+button('Xóa','address-archive',a.address_id)]))+addressForm();
      } else if (current==='orders') {
        const data=await api(`${admin?'admin':'customer'}/orders`);
        html=table(['Mã đơn','Trạng thái','Tổng tiền (đơn vị nhỏ nhất)','Chi tiết'],data.map(o=>[h(o.order_code),h(o.status),`${h(o.total_charge_minor)} ${h(o.currency_code)}`,button('Xem','order',o.order_id)]));
      } else if (current==='accounts') {
        const data=await api('admin/accounts');
        html='<p>Hiển thị tối đa 100 tài khoản mới nhất.</p>'+table(['ID','Tên / Email','Trạng thái','Thao tác'],data.map(u=>[h(u.user_id),`${h(u.full_name)}<br>${h(u.email)}`,h(u.account_status),button('Khóa','suspend',u.user_id)+button('Mở khóa','activate',u.user_id)+(u.verification_status?button('Xét duyệt Shipper','kyc',u.user_id):'')]));
      } else if (current==='categories') {
        const data=await api('admin/categories');
        html=table(['ID','Tên','Hạn chế','Cập nhật'],data.map(c=>[h(c.category_id),h(c.name_vi),h(c.restriction_type),`<form data-action="category"><input type="hidden" name="category_id" value="${h(c.category_id)}"><select name="restriction_type">${['ALLOWED','RESTRICTED','PROHIBITED'].map(x=>`<option ${c.restriction_type===x?'selected':''}>${x}</option>`).join('')}</select><label><input name="active" type="checkbox" ${c.active?'checked':''}>Hoạt động</label>${submit}</form>`]));
      } else if (current==='pricing') {
        const response=await api('admin/pricing');
        html='<p>Cấu hình chỉ được lưu nháp; chưa áp dụng để tính tiền. Số tiền nhập theo đồng (VND) hoặc cent (USD). Để trống mục chưa được thống nhất.</p><form data-action="pricing"><label>Tiền tệ<select name="currency_code"><option>VND</option><option>USD</option></select></label>'+
          field('return_fee_minor','Phí hoàn hàng','','number','min="0" step="1"')+
          field('successful_delivery_percentage','Thu nhập Shipper (%)','','number','min="0" max="100" step="0.0001"')+
          field('failed_return_fixed_minor','Bồi hoàn khi hoàn hàng thất bại','','number','min="0" step="1"')+
          '<h3>Giá theo khoảng cách</h3>'+field('min_km','Từ km','','number','min="0" step="0.001"')+field('max_km','Đến km (để trống nếu không giới hạn)','','number','min="0" step="0.001"')+field('amount_minor','Số tiền của khoảng cách','','number','min="0" step="1"')+
          '<h3>Phụ thu theo giờ</h3>'+field('starts_at_local','Từ giờ','','time')+field('ends_at_local','Đến giờ','','time')+field('time_multiplier','Hệ số theo giờ','','number','min="0" step="0.0001"')+
          '<h3>Phụ thu thời tiết</h3>'+field('provider_condition_code','Mã thời tiết đã thống nhất')+field('weather_multiplier','Hệ số thời tiết','','number','min="0" step="0.0001"')+
          '<h3>Phí hủy</h3><label>Áp dụng sau trạng thái<select name="after_status"><option value="">Chưa chọn</option><option>PENDING_PAYMENT</option><option>SEARCHING_SHIPPER</option><option>SHIPPER_ASSIGNED</option><option>PICKED_UP</option><option>IN_DELIVERY</option></select></label>'+field('fee_minor','Phí hủy','','number','min="0" step="1"')+
          '<h3>Điểm Shipper</h3>'+field('deduction_points','Điểm trừ','','number','min="0" step="0.01"')+field('lock_threshold','Ngưỡng khóa','','number','min="0" step="0.01"')+field('recovery_points','Điểm khôi phục','','number','min="0" step="0.01"')+submit+'</form>'+
          table(['Bản nháp','Tiền tệ','Ngày tạo','Trạng thái'],response.map(d=>[h(d.draft_id),h(d.currency_code),h(new Date(d.created_at).toLocaleString('vi-VN')),'Chờ phê duyệt quy tắc áp dụng']));
      } else if (current==='nationality') {
        const data=await api(admin?'admin/nationality-requests':'auth/nationality-request');
        html=table(['ID','Quốc tịch yêu cầu','Trạng thái','Ghi chú'],data.map(r=>[h(r.request_id),h(r.requested_country_code),h(r.status),admin?button('Từ chối','nationality-reject',r.request_id):h(r.review_note)]));
        if(!admin)html+='<form data-action="nationality">'+field('requested_country_code','Mã quốc gia (2 chữ in hoa)','','text','required pattern="[A-Z]{2}"')+field('reason','Lý do','','text','required maxlength="1000"')+submit+'</form>';
        else html+='<p>Phê duyệt đổi quốc tịch đang chờ quy trình thay thế và xác minh giấy tờ.</p>';
      }
      if (requestGeneration!==generation) return;
      content.innerHTML=html;status.textContent='';
    } catch(error) { if(requestGeneration===generation) {content.replaceChildren();status.textContent=error.message;} }
  }
  async function mutate(operation) {
    if(pending)return;
    pending=true;root.setAttribute('aria-busy','true');
    root.querySelectorAll('button').forEach(b=>b.disabled=true);
    try {await operation();await render();status.textContent='Đã lưu.';}
    catch(error){status.textContent=error.message;}
    finally{pending=false;root.removeAttribute('aria-busy');root.querySelectorAll('button').forEach(b=>b.disabled=false);}
  }
  root.addEventListener('click', async event=>{
    const tab=event.target.closest('[data-person1-tab]');if(tab){current=tab.dataset.person1Tab;render();return;}
    const b=event.target.closest('button[data-action]');if(!b)return;
    const id=b.dataset.id;
    if(b.dataset.action==='address-edit'){content.querySelector('form[data-action="address-save"]').outerHTML=addressForm(records.find(x=>String(x.address_id)===id));return;}
    if(b.dataset.action==='order') {
      const requestGeneration=++generation;
      try {const order=await api(`${admin?'admin':'customer'}/orders?order_id=${encodeURIComponent(id)}`);if(requestGeneration===generation)content.innerHTML=orderDetail(order);}catch(e){if(requestGeneration===generation)status.textContent=e.message;}return;
    }
    if(b.dataset.action==='kyc') {
      const decision=prompt('Quyết định: APPROVED, REJECTED hoặc NEEDS_INFO');if(!decision)return;
      const note=prompt('Ghi chú xét duyệt');if(note===null)return;
      return mutate(()=>api('admin/review-shipper',{shipper_id:id,decision,note}));
    }
    const reason=prompt('Nhập lý do xác nhận thao tác');if(!reason?.trim())return;
    if(b.dataset.action==='address-archive')return mutate(()=>api('customer/addresses',{action:'archive',address_id:id}));
    if(b.dataset.action==='cancel')return mutate(()=>api('customer/orders',{action:'cancel',order_id:id,reason}));
    if(b.dataset.action==='nationality-reject')return mutate(()=>api('admin/nationality-requests',{request_id:id,decision:'REJECTED',note:reason}));
    if(['suspend','activate'].includes(b.dataset.action))return mutate(()=>api('admin/accounts',{user_id:id,account_status:b.dataset.action==='suspend'?'SUSPENDED':'ACTIVE',reason}));
  });
  root.addEventListener('submit',event=>{
    event.preventDefault();const form=event.target;const data=Object.fromEntries(new FormData(form));
    mutate(()=>{
      switch(form.dataset.action){
        case 'profile':return api('auth/profile',data);
        case 'address-save':return api('customer/addresses',{...data,action:'save',latitude:Number(data.latitude),longitude:Number(data.longitude),is_default:form.elements.is_default.checked});
        case 'category':return api('admin/categories',{...data,active:form.elements.active.checked});
        case 'pricing': {
          const config={};
          const numeric=['return_fee_minor','successful_delivery_percentage','failed_return_fixed_minor'];
          numeric.forEach(key=>{if(data[key]!=='')config[key]=Number(data[key]);});
          if(data.min_km!==''||data.amount_minor!==''||data.max_km!=='') {
            if(data.min_km===''||data.amount_minor==='')throw new Error('Nhập đủ khoảng cách bắt đầu và số tiền.');
            config.distance_bands=[{min_km:Number(data.min_km),max_km:data.max_km===''?null:Number(data.max_km),amount_minor:Number(data.amount_minor)}];
          }
          if(data.starts_at_local||data.ends_at_local||data.time_multiplier!=='') {
            if(!data.starts_at_local||!data.ends_at_local||data.time_multiplier==='')throw new Error('Nhập đủ giờ bắt đầu, kết thúc và hệ số.');
            config.time_bands=[{day_of_week:null,starts_at_local:data.starts_at_local,ends_at_local:data.ends_at_local,multiplier:Number(data.time_multiplier)}];
          }
          if(data.provider_condition_code||data.weather_multiplier!=='') {
            if(!data.provider_condition_code||data.weather_multiplier==='')throw new Error('Nhập đủ mã thời tiết và hệ số.');
            config.weather_bands=[{provider_condition_code:data.provider_condition_code,multiplier:Number(data.weather_multiplier)}];
          }
          if(data.after_status||data.fee_minor!=='') {
            if(!data.after_status||data.fee_minor==='')throw new Error('Chọn trạng thái và nhập phí hủy.');
            config.cancellation_rules=[{after_status:data.after_status,fee_minor:Number(data.fee_minor)}];
          }
          if(['deduction_points','lock_threshold','recovery_points'].some(key=>data[key]!=='')) {
            if(['deduction_points','lock_threshold','recovery_points'].some(key=>data[key]===''))throw new Error('Nhập đủ ba thông số điểm Shipper.');
            config.score_policy=Object.fromEntries(['deduction_points','lock_threshold','recovery_points'].map(key=>[key,Number(data[key])]));
          }
          return api('admin/pricing',{currency_code:data.currency_code,configuration:config});
        }
        case 'nationality':return api('auth/nationality-request',data);
      }
    });
  });
  render();
})();
