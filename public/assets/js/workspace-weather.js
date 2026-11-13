(() => {
  'use strict';
  const panel=document.querySelector('[data-workspace-weather]');if(!panel)return;
  const $=s=>panel.querySelector(s),shipper=document.body.dataset.page==='shipper';
  const vi=()=>{try{return (localStorage.getItem('somi-language')||document.documentElement.lang)!=='en';}catch{return document.documentElement.lang!=='en';}};
  const t=(a,b)=>vi()?a:b,esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const num=v=>typeof v==='number'&&Number.isFinite(v)?Math.round(v):null;
  const value=(v,unit='')=>num(v)===null?'—':num(v)+unit;
  let data=null,selected=-1,version=0,controller=null,timer=null,stopped=false;
  const condition=code=>code>=95?t('Có dông','Thunderstorms'):code>=51?t('Có mưa hoặc thời tiết xấu','Rain or adverse weather'):code>=45?t('Có sương mù','Foggy'):code>=2?t('Có mây','Cloudy'):t('Trời quang','Clear skies');
  const symbol=code=>code>=95?'⛈':code>=51?'☂':code>=2?'☁':'☀';
  function advice(code,rain){
    if(code>=95||[56,57,66,67,71,73,75,77,85,86].includes(code))return shipper?t('Thời tiết bất lợi. Ưu tiên dừng ở nơi an toàn; liên hệ hỗ trợ từ đơn đang giao nếu không thể tiếp tục.','Adverse weather. Stop somewhere safe and contact support from your delivery if you cannot continue.'):t('Thời tiết bất lợi có thể ảnh hưởng chuyến giao. Kiểm tra trạng thái đơn và giữ liên lạc với shipper.','Adverse weather may affect deliveries. Check your order and stay in touch with your shipper.');
    if(code>=51||rain>=50)return shipper?t('Có khả năng mưa. Chuẩn bị áo mưa, bọc chống nước cho hàng và giữ khoảng cách khi di chuyển.','Rain is possible. Prepare rainwear, protect parcels from water and keep a safe following distance.'):t('Có khả năng mưa. Bọc chống nước cho kiện hàng và ghi chú điểm bàn giao có mái che.','Rain is possible. Waterproof your parcel and add a sheltered handover location.');
    return shipper?t('Chuẩn bị nước uống và đồ dùng cho ca giao. Dừng xe an toàn trước khi xem thông báo hoặc cập nhật đơn.','Prepare water and your delivery equipment. Stop safely before checking messages or updating an order.'):t('Kiểm tra số điện thoại người nhận và đóng gói chắc chắn trước khi gửi.','Check the recipient’s phone number and secure the packaging before sending.');
  }
  function render(){
    panel.querySelectorAll('[data-w-vi]').forEach(el=>el.textContent=vi()?el.dataset.wVi:el.dataset.wEn);
    $('h2').textContent=shipper?t('Thời tiết trên đường','Weather on your route'):t('Thời tiết gửi hàng','Delivery weather');
    if(!data)return;
    const w=data.weather,hour=selected>=0?w.hours[selected]:null,code=hour?.weather_code??w.weather_code;
    $('[data-weather-temperature]').textContent=value(hour?.temperature_c??w.temperature_c,'°');
    $('[data-weather-condition]').textContent=condition(code);$('[data-weather-symbol]').textContent=symbol(code);
    $('[data-weather-meta]').textContent=[t('Cảm giác ','Feels like ')+value(w.feels_like_c,'°'),t('Độ ẩm ','Humidity ')+value(w.humidity_percent,'%'),t('Gió ','Wind ')+value(w.wind_kmh,' km/h')].join(' · ');
    $('[data-weather-hours]').innerHTML=(w.hours||[]).map((h,i)=>'<button type="button" class="ww-hour" data-weather-hour="'+i+'" aria-pressed="'+(i===selected)+'"><span>'+esc(new Date(h.time).toLocaleTimeString(vi()?'vi-VN':'en-GB',{timeZone:'Asia/Ho_Chi_Minh',hour:'2-digit',minute:'2-digit'}))+'</span><span aria-hidden="true">'+symbol(h.weather_code)+'</span><b>'+value(h.temperature_c,'°')+'</b><span>'+esc(t('Mưa ','Rain '))+value(h.rain_probability,'%')+'</span></button>').join('');
    $('[data-weather-advice]').textContent=advice(code,hour?.rain_probability??w.hours?.[0]?.rain_probability);
    $('[data-weather-updated]').textContent=t('Cập nhật ','Updated ')+new Date(w.observed_at).toLocaleTimeString(vi()?'vi-VN':'en-GB',{timeZone:'Asia/Ho_Chi_Minh',hour:'2-digit',minute:'2-digit'});
  }
  async function load(){
    const current=++version;controller?.abort();controller=new AbortController();const active=controller,timeout=setTimeout(()=>active.abort(),15000);
    const status=$('[data-weather-status]');status.hidden=false;status.textContent=t('Đang tải thời tiết…','Loading weather…');$('[data-weather-refresh]').disabled=true;
    try{
      const response=await fetch('public/api/account/weather.php?'+new URLSearchParams({area:$('[data-weather-area]').value}),{credentials:'same-origin',headers:{Accept:'application/json'},signal:active.signal});
      const result=await response.json();if(!response.ok)throw new Error('weather');
      if(current!==version||stopped)return;data=result;selected=-1;$('.ww-body').hidden=false;status.hidden=true;render();
    }catch(e){if(current!==version||stopped)return;data=null;$('.ww-body').hidden=true;$('[data-weather-updated]').textContent='';status.textContent=t('Chưa tải được thời tiết. Bạn vẫn có thể sử dụng các chức năng khác; nhấn Làm mới để thử lại.','Weather could not be loaded. Other features remain available; select Refresh to retry.');}
    finally{clearTimeout(timeout);if(current===version)$('[data-weather-refresh]').disabled=false;}
  }
  try{const area=localStorage.getItem('somi-weather-area');if(['centre','east','south'].includes(area))$('[data-weather-area]').value=area;}catch{}
  $('[data-weather-area]').onchange=()=>{try{localStorage.setItem('somi-weather-area',$('[data-weather-area]').value);}catch{}load();};
  $('[data-weather-refresh]').onclick=load;
  panel.addEventListener('click',e=>{const hour=e.target.closest('[data-weather-hour]');if(hour){selected=Number(hour.dataset.weatherHour);render();}});
  document.addEventListener('somi:language-change',render);
  async function poll(){if(stopped)return;if(!document.hidden)await load();if(!stopped)timer=setTimeout(poll,300000);}
  window.addEventListener('pagehide',()=>{stopped=true;clearTimeout(timer);controller?.abort();});
  window.addEventListener('pageshow',event=>{if(event.persisted){stopped=false;poll();}});
  render();poll();
})();
