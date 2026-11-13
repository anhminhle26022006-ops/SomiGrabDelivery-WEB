"use strict";
// Offer, trạng thái nhận đơn, đơn đang giao và lịch sử đã chuyển sang API thật:
// xem shipper-offers.js và shipper-active.js. File này chỉ còn phần chưa có backend
// và các tiện ích giao diện dùng chung; chat/thông báo/hỗ trợ dùng person3.js.

const notices=[]; // Notifications are loaded by person3.js from the authenticated API.
// Shared VND formatter defined by the Shipper page script (app/Views/shipper/index.php).
function money(n){return window.formatShipperMoney(n)}
function noticeCard(n){return `<div class="notice ${n.unread?'unread':''}" data-id="${n.id}"><div class="noticeicon ${n.tone}">${n.icon}</div><div style="flex:1;min-width:0"><div class="noticetitle">${n.title}</div><div class="noticedesc">${n.desc}</div><div class="noticetime">${n.time}</div></div><button class="outline" style="padding:6px 9px;font-size:10px;align-self:center" onclick="openNotice(${n.id})">Xem</button></div>`}
function renderNotices(){let unread=notices.filter(n=>n.unread).length;const unreadCount=document.getElementById('unreadFilterCount');if(unreadCount)unreadCount.textContent=unread;const overview=document.getElementById('overviewNotices');if(overview)overview.innerHTML=notices.slice(0,3).map(noticeCard).join('');filterNotices(document.querySelector('[data-notice-filter].selected')?.dataset.noticeFilter||'all')}
function filterNotices(type){document.querySelectorAll('[data-notice-filter]').forEach(b=>b.classList.toggle('selected',b.dataset.noticeFilter===type));let list=notices.filter(n=>type==='all'||(type==='unread'?n.unread:n.kind===type));const target=document.getElementById('allNotices');if(target)target.innerHTML=list.length?list.map(noticeCard).join(''):'<div class="empty"><strong>Không có thông báo</strong>Bạn đã xem hết các thông báo trong mục này.</div>'}
function openNotice(id){let n=notices.find(x=>x.id===id);if(!n)return;n.unread=false;renderNotices();if(n.target==='profile'){go('account')}else if(n.target==='policy'){openProfile();toast('Chính sách & điều khoản nằm trong menu avatar')}else go(n.target); }

function closeOrderDetail(){document.getElementById('orderDetailModal')?.classList.add('hidden')}
function toast(s){let el=document.getElementById('toast');el.textContent=s;el.style.display='block';clearTimeout(window.toastTimer);window.toastTimer=setTimeout(()=>el.style.display='none',2800)}
function showSupport(){window.somiPerson3Open?.('tickets');}
function openProfile(){document.getElementById('profileDropdown').classList.add('open');document.getElementById('profileBtn').setAttribute('aria-expanded','true')}
// Sidebar navigation is handled once by shipper-navigation.js.
// Header branding/navigation may also carry data-page; do not let it hide every view.
document.getElementById('bellBtn')?.addEventListener('click',()=>go('notifications'));
document.getElementById('chatForm')?.addEventListener('submit',e=>{e.preventDefault();window.somiPerson3Open?.('chat');});
document.getElementById('supportForm')?.addEventListener('submit',e=>{e.preventDefault();window.somiPerson3Open?.('tickets');});

document.getElementById('profileBtn')?.addEventListener('click',e=>{e.stopPropagation();let d=document.getElementById('profileDropdown');d.classList.toggle('open');e.currentTarget.setAttribute('aria-expanded',d.classList.contains('open')?'true':'false')});
document.addEventListener('click',e=>{if(!e.target.closest('.profile')){document.getElementById('profileDropdown')?.classList.remove('open');document.getElementById('profileBtn')?.setAttribute('aria-expanded','false')}});
document.getElementById('avatarUpload')?.addEventListener('change',e=>{let f=e.target.files[0];if(!f)return;if(!f.type.startsWith('image/')){toast('Vui lòng chọn file hình ảnh.');return}let reader=new FileReader();reader.onload=ev=>{document.getElementById('avatar').innerHTML=`<img src="${ev.target.result}" alt="Ảnh đại diện">`;document.getElementById('accountAvatar').innerHTML=`<img src="${ev.target.result}" alt="Ảnh đại diện">`;toast('Đã cập nhật ảnh đại diện trong bản demo. Ảnh chưa được lưu lên máy chủ.');};reader.readAsDataURL(f)});
// Hỗ trợ, chính sách và đăng xuất được xử lý trong app/Views/shipper/index.php và app.js; ở đây chỉ còn mục Tài khoản.
document.querySelectorAll('[data-profile-action="account"]').forEach(b=>b.addEventListener('click',()=>{document.getElementById('profileDropdown').classList.remove('open');go('account')}));
document.querySelectorAll('[data-notice-filter]').forEach(b=>b.addEventListener('click',()=>filterNotices(b.dataset.noticeFilter)));
document.getElementById('markAll')?.addEventListener('click',()=>{notices.forEach(n=>n.unread=false);renderNotices();toast('Đã đánh dấu tất cả thông báo là đã đọc.')});
function openContactChat(){window.somiPerson3Open?.('chat');}
function closeContactChat(){document.getElementById('contactModal').classList.add('hidden');}

// Offers/history load through shipper-offers.js and shipper-active.js; notifications use person3.js.

// Keep inline actions callable even if deployment wraps scripts differently.
Object.assign(window,{closeOrderDetail,openNotice,showSupport,openProfile,openContactChat,closeContactChat,toast});
