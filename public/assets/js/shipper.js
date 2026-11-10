"use strict";
// Offer, trạng thái nhận đơn, đơn đang giao và lịch sử đã chuyển sang API thật:
// xem shipper-offers.js và shipper-active.js. File này chỉ còn phần chưa có backend
// (thông báo, chat, hỗ trợ) và các tiện ích giao diện dùng chung.

const notices=[
 {id:1,kind:'orders',icon:'▣',tone:'',title:'Có đơn giao mới phù hợp với bạn',desc:'Phú Nhuận → Quận 3 · Thu nhập dự kiến 38.000 ₫',time:'5 phút trước',unread:true,target:'offers'},
 {id:2,kind:'orders',icon:'✓',tone:'green',title:'Đơn SOMI-240126 đã được giao thành công',desc:'Bạn đã nhận được 42.000 ₫',time:'10 phút trước',unread:true,target:'history'},
 {id:3,kind:'account',icon:'!',tone:'orange',title:'Yêu cầu xác minh tài khoản',desc:'Vui lòng cập nhật giấy tờ tùy thân để hoàn tất hồ sơ.',time:'1 giờ trước',unread:true,target:'profile'},
 {id:4,kind:'account',icon:'↗',tone:'',title:'Cập nhật chính sách phí giao hàng',desc:'Xem các thay đổi có hiệu lực trong tuần này.',time:'2 giờ trước',unread:false,target:'policy'},
 {id:5,kind:'orders',icon:'▤',tone:'green',title:'Tin nhắn từ khách hàng',desc:'“Vui lòng giao giúp trước 17h nhé.”',time:'3 giờ trước',unread:false,target:'active'}
];
// Shared VND formatter defined by the Shipper page script (app/Views/shipper/index.php).
function money(n){return window.formatShipperMoney(n)}
function noticeCard(n){return `<div class="notice ${n.unread?'unread':''}" data-id="${n.id}"><div class="noticeicon ${n.tone}">${n.icon}</div><div style="flex:1;min-width:0"><div class="noticetitle">${n.title}</div><div class="noticedesc">${n.desc}</div><div class="noticetime">${n.time}</div></div><button class="outline" style="padding:6px 9px;font-size:10px;align-self:center" onclick="openNotice(${n.id})">Xem</button></div>`}
function renderNotices(){let unread=notices.filter(n=>n.unread).length;const unreadCount=document.getElementById('unreadFilterCount');if(unreadCount)unreadCount.textContent=unread;const overview=document.getElementById('overviewNotices');if(overview)overview.innerHTML=notices.slice(0,3).map(noticeCard).join('');filterNotices(document.querySelector('[data-notice-filter].selected')?.dataset.noticeFilter||'all')}
function filterNotices(type){document.querySelectorAll('[data-notice-filter]').forEach(b=>b.classList.toggle('selected',b.dataset.noticeFilter===type));let list=notices.filter(n=>type==='all'||(type==='unread'?n.unread:n.kind===type));const target=document.getElementById('allNotices');if(target)target.innerHTML=list.length?list.map(noticeCard).join(''):'<div class="empty"><strong>Không có thông báo</strong>Bạn đã xem hết các thông báo trong mục này.</div>'}
function openNotice(id){let n=notices.find(x=>x.id===id);if(!n)return;n.unread=false;renderNotices();if(n.target==='profile'){go('account')}else if(n.target==='policy'){openProfile();toast('Chính sách & điều khoản nằm trong menu avatar')}else go(n.target); }

function closeOrderDetail(){document.getElementById('orderDetailModal')?.classList.add('hidden')}
function toast(s){let el=document.getElementById('toast');el.textContent=s;el.style.display='block';clearTimeout(window.toastTimer);window.toastTimer=setTimeout(()=>el.style.display='none',2800)}
function demoAction(s){toast(s+' — thao tác minh họa trong demo HTML.')}
function showSupport(){document.getElementById('supportPanel').classList.remove('hidden');document.getElementById('supportPanel').scrollIntoView({behavior:'smooth',block:'nearest'});}
function openProfile(){document.getElementById('profileDropdown').classList.add('open');document.getElementById('profileBtn').setAttribute('aria-expanded','true')}
// Sidebar navigation is handled once by shipper-navigation.js.
// Header branding/navigation may also carry data-page; do not let it hide every view.
document.getElementById('bellBtn')?.addEventListener('click',()=>go('notifications'));
document.getElementById('chatForm')?.addEventListener('submit',e=>{e.preventDefault();const input=document.getElementById('chatInput');const text=input.value.trim();if(!text)return;const bubble=document.createElement('div');bubble.className='bubble mine';bubble.textContent=text;document.getElementById('chatMessages').appendChild(bubble);input.value='';document.getElementById('chatMessages').scrollTop=document.getElementById('chatMessages').scrollHeight;toast('Tin nhắn được thêm vào khung chat demo.');});
document.getElementById('supportForm')?.addEventListener('submit',e=>{e.preventDefault();const type=document.getElementById('supportType').selectedOptions[0].text;const note=document.getElementById('supportMessage').value.trim();if(!note)return;document.getElementById('supportForm').reset();document.getElementById('supportPanel').classList.add('hidden');toast(`Đã ghi nhận yêu cầu “${type}” cho SOMI-240126 (demo).`);});
document.getElementById('profileBtn')?.addEventListener('click',e=>{e.stopPropagation();let d=document.getElementById('profileDropdown');d.classList.toggle('open');e.currentTarget.setAttribute('aria-expanded',d.classList.contains('open')?'true':'false')});
document.addEventListener('click',e=>{if(!e.target.closest('.profile')){document.getElementById('profileDropdown')?.classList.remove('open');document.getElementById('profileBtn')?.setAttribute('aria-expanded','false')}});
document.getElementById('avatarUpload')?.addEventListener('change',e=>{let f=e.target.files[0];if(!f)return;if(!f.type.startsWith('image/')){toast('Vui lòng chọn file hình ảnh.');return}let reader=new FileReader();reader.onload=ev=>{document.getElementById('avatar').innerHTML=`<img src="${ev.target.result}" alt="Ảnh đại diện">`;document.getElementById('accountAvatar').innerHTML=`<img src="${ev.target.result}" alt="Ảnh đại diện">`;toast('Đã cập nhật ảnh đại diện trong bản demo. Ảnh chưa được lưu lên máy chủ.');};reader.readAsDataURL(f)});
document.querySelectorAll('[data-profile-action]').forEach(b=>b.addEventListener('click',()=>{let action=b.dataset.profileAction;let actions={account:'Tài khoản & hồ sơ',support:'Trung tâm hỗ trợ',policy:'Chính sách & điều khoản',logout:'Đăng xuất'};document.getElementById('profileDropdown').classList.remove('open');if(action==='account'){go('account');return}toast('Mở mục '+actions[action]+' — màn hình minh họa.')})); 
document.querySelectorAll('[data-notice-filter]').forEach(b=>b.addEventListener('click',()=>filterNotices(b.dataset.noticeFilter)));
document.getElementById('markAll')?.addEventListener('click',()=>{notices.forEach(n=>n.unread=false);renderNotices();toast('Đã đánh dấu tất cả thông báo là đã đọc.')});
function openContactChat(){document.getElementById('contactModal').classList.remove('hidden');}
function closeContactChat(){document.getElementById('contactModal').classList.add('hidden');}

renderNotices();

// Keep inline actions callable even if deployment wraps scripts differently.
Object.assign(window,{closeOrderDetail,openNotice,demoAction,showSupport,openProfile,openContactChat,closeContactChat,toast});
