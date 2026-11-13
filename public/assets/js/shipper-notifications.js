(() => {
  'use strict';
  const popup=document.getElementById('shipperNotificationPopup'),bell=document.getElementById('bellBtn');
  if(!popup||!bell)return;
  const list=popup.querySelector('[data-notification-list]'),status=popup.querySelector('[data-notification-status]');
  const esc=window.SomiShipper.esc;
  const t=(vi,en)=>document.body.dataset.shipperLang==='en'?en:vi;
  let items=[],cursor=null,unread=0,filter='all',busy=false,timer=null,stopped=false;
  const endpoint='public/api/interactions.php?resource=notifications';
  async function api(body,before){
    const response=await fetch(endpoint+(before?'&before_id='+encodeURIComponent(before):''),{method:body?'POST':'GET',credentials:'same-origin',cache:'no-store',headers:body?{'Content-Type':'application/json',Accept:'application/json'}:{Accept:'application/json'},...(body?{body:JSON.stringify(body)}:{}),signal:AbortSignal.timeout(20000)});
    const data=await response.json();
    if(!response.ok){if(response.status===401){stopped=true;clearTimeout(timer);}throw new Error(response.status===401?t('Phiên đăng nhập hết hạn. Vui lòng đăng nhập lại.','Session expired. Please sign in again.'):t('Chưa tải được thông báo. Vui lòng thử lại.','Unable to load notifications. Please try again.'));}
    return data;
  }
  function message(text){status.textContent=text;status.hidden=!text;}
  function render(){
    popup.querySelector('[data-notification-title]').textContent=t('Thông báo','Notifications');
    popup.querySelector('[data-notification-count]').textContent=unread?`${unread} `+t('thông báo chưa đọc','unread notifications'):t('Bạn đã đọc tất cả thông báo','You are all caught up');
    const labels={all:t('Tất cả','All'),unread:t('Chưa đọc','Unread')};
    popup.querySelectorAll('[data-notification-filter]').forEach(b=>{b.textContent=labels[b.dataset.notificationFilter];b.setAttribute('aria-pressed',String(filter===b.dataset.notificationFilter));});
    popup.querySelector('[data-notification-read-all]').textContent=t('Đọc tất cả','Mark all read');
    popup.querySelector('[data-notification-read-all]').disabled=busy||!unread||!items.length;
    popup.querySelector('[data-notification-refresh]').textContent=t('Làm mới','Refresh');
    popup.querySelector('[data-notification-refresh]').disabled=busy;
    const more=popup.querySelector('[data-notification-more]');more.textContent=t('Xem thêm','Load more');more.hidden=!cursor;more.disabled=busy;
    const visible=items.filter(n=>filter!=='unread'||!n.read_at);
    list.innerHTML=visible.length?visible.map(n=>`<article class="notification-popup-item ${n.read_at?'':'is-unread'}"><span class="notification-item-icon" aria-hidden="true">${n.order_id?'↗':'●'}</span><div><strong>${esc(t(n.title_vi,n.title_en))}</strong><p>${esc(t(n.message_vi,n.message_en))}</p><time datetime="${esc(n.created_at)}">${esc(new Date(n.created_at).toLocaleString(document.body.dataset.shipperLang==='en'?'en-GB':'vi-VN',{day:'2-digit',month:'2-digit',hour:'2-digit',minute:'2-digit'}))}</time>${!n.read_at?`<button type="button" data-notification-read="${esc(n.notification_id)}" ${busy?'disabled':''}>${esc(t('Đánh dấu đã đọc','Mark as read'))}</button>`:''}</div></article>`).join(''):`<div class="notification-popup-empty"><span aria-hidden="true">♧</span><strong>${t(filter==='unread'?'Không có thông báo chưa đọc':'Chưa có thông báo',filter==='unread'?'No unread notifications':'No notifications yet')}</strong><p>${t('Cập nhật đơn hàng và tài khoản sẽ xuất hiện ở đây.','Delivery and account updates will appear here.')}</p></div>`;
    document.querySelectorAll('[data-unread]').forEach(n=>{n.textContent=unread>99?'99+':String(unread);n.hidden=!unread;});
    const overview=document.getElementById('overviewNotices');
    if(overview)overview.innerHTML=items.slice(0,3).map(n=>`<button class="notification-overview-item" type="button" data-open-notifications><strong>${esc(t(n.title_vi,n.title_en))}</strong><span>${esc(t(n.message_vi,n.message_en))}</span></button>`).join('')||t('Chưa có thông báo.','No notifications yet.');
  }
  async function load(more=false){
    if(busy||stopped)return;
    busy=true;render();if(!popup.hidden)message(t('Đang tải thông báo…','Loading notifications…'));
    try{
      const data=await api(null,more?cursor:null);
      if(more){const ids=new Set(items.map(n=>String(n.notification_id)));items.push(...data.items.filter(n=>!ids.has(String(n.notification_id))));}
      else items=data.items;
      unread=data.unread_count;cursor=data.next_cursor;message('');
    }catch(e){message(e.name==='TimeoutError'?t('Kết nối chậm. Nhấn “Làm mới” để thử lại.','Connection is slow. Select Refresh to retry.'):e.message);}
    finally{busy=false;render();}
  }
  async function mark(id){
    if(busy||!items.length)return;
    busy=true;render();
    try{
      await api(id?{notification_id:id,read:true}:{all:true,read:true,through_id:String(items[0].notification_id)});
      if(id){const n=items.find(n=>String(n.notification_id)===String(id));if(n&&!n.read_at){n.read_at=new Date().toISOString();unread=Math.max(0,unread-1);}}
      else{items.forEach(n=>n.read_at||=new Date().toISOString());unread=0;}
      message('');
    }catch(e){message(t('Chưa đánh dấu được thông báo. Vui lòng thử lại.','Could not mark notifications as read. Please try again.'));}
    finally{busy=false;render();}
    await load();
  }
  function position(){
    const rect=bell.getBoundingClientRect();popup.style.top=Math.min(rect.bottom+12,window.innerHeight-160)+'px';popup.style.right=Math.max(12,window.innerWidth-rect.right)+'px';
  }
  function open(){popup.hidden=false;bell.setAttribute('aria-expanded','true');position();popup.focus({preventScroll:true});load();}
  function close(restore=false){popup.hidden=true;bell.setAttribute('aria-expanded','false');if(restore)bell.focus();}
  window.SomiNotifications={open,close,refresh:()=>load()};
  bell.addEventListener('click',()=>popup.hidden?open():close(true));
  popup.querySelector('[data-notification-close]').onclick=()=>close(true);
  popup.querySelector('[data-notification-refresh]').onclick=()=>load();
  popup.querySelector('[data-notification-more]').onclick=()=>load(true);
  popup.querySelector('[data-notification-read-all]').onclick=()=>mark();
  popup.addEventListener('click',e=>{const f=e.target.closest('[data-notification-filter]');if(f){filter=f.dataset.notificationFilter;render();}const read=e.target.closest('[data-notification-read]');if(read)mark(read.dataset.notificationRead);});
  document.addEventListener('click',e=>{if(e.target.closest('[data-open-notifications]'))open();});
  document.addEventListener('pointerdown',e=>{if(!popup.hidden&&!popup.contains(e.target)&&!bell.contains(e.target))close();});
  document.addEventListener('keydown',e=>{if(e.key==='Escape'&&!popup.hidden){e.preventDefault();close(true);}});
  window.addEventListener('resize',()=>{if(!popup.hidden)position();});
  document.addEventListener('somi:language-change',render);
  async function poll(){if(!document.hidden)await load();if(!stopped)timer=setTimeout(poll,15000);}
  document.addEventListener('DOMContentLoaded',()=>{poll();if(new URLSearchParams(location.search).get('view')==='notifications')open();});
  document.addEventListener('visibilitychange',()=>{if(!document.hidden)load();});
  window.addEventListener('pagehide',()=>{stopped=true;clearTimeout(timer);});
  window.addEventListener('pageshow',e=>{if(e.persisted){stopped=false;poll();}});
})();
