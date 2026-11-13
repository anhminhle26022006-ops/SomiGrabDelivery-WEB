(() => {
 'use strict';const root=document.querySelector('[data-person3-jobs]');if(!root)return;const list=root.querySelector('[data-p3-jobs-list]'),status=root.querySelector('[data-p3-jobs-status]');
 // Ngôn ngữ dùng chung với admin-live.js: đọc lựa chọn đã lưu và dựng lại khi người dùng đổi.
 let vi=false;try{vi=localStorage.getItem('somi-language')==='vi';}catch{}
 const t=(a,b)=>vi?a:b;
 async function api(path,body){const r=await fetch('public/api/admin/person3-jobs.php'+path,{credentials:'same-origin',method:body?'POST':'GET',headers:body?{'Content-Type':'application/json'}:{},body:body?JSON.stringify(body):undefined});const d=await r.json();if(!r.ok)throw Error(d.error||t('Yêu cầu thất bại','Request failed'));return d;}
 async function load(before){const d=await api(before?'?before_id='+before:'');if(!before)list.replaceChildren();if(!d.items.length&&!before)list.textContent=t('Không có tác vụ lỗi.','No event failures.');
  for(const job of d.items){const article=document.createElement('article');const p=document.createElement('p');p.textContent='#'+job.event_id+' · '+job.event_type+' · '+job.status+' · '+job.attempts+' '+t('lần','attempts')+' · '+job.last_error_code;article.append(p);if(job.status==='FAILED'){const f=document.createElement('form');f.innerHTML='<label>'+t('Lý do thử lại','Retry reason')+'<input name="reason" required maxlength="2000"></label><button type="submit">'+t('Thử lại sự kiện','Retry event')+'</button>';f.onsubmit=async e=>{e.preventDefault();const b=f.querySelector('button');b.disabled=true;try{await api('',{event_id:job.event_id,reason:f.elements.reason.value});await load();status.textContent=t('Đã đưa vào hàng đợi.','Queued for processing.');}catch(e){status.textContent=e.message;}finally{b.disabled=false;}};article.append(f);}list.append(article);}
  if(d.next_cursor){const b=document.createElement('button');b.type='button';b.textContent=t('Xem thêm','Load more');b.onclick=()=>{b.remove();load(d.next_cursor).catch(e=>status.textContent=e.message);};list.append(b);}}
 root.querySelector('[data-p3-jobs-refresh]').onclick=()=>load().catch(e=>status.textContent=e.message);
 document.addEventListener('somi:language-change',event=>{const next=event.detail.language==='vi';if(next===vi)return;vi=next;status.textContent='';load().catch(e=>status.textContent=e.message);});
 load().catch(e=>status.textContent=e.message);
})();
