(() => {
  'use strict';
  if(!['customer','shipper'].includes(document.body.dataset.page))return;
  const editor=document.querySelector('[data-avatar-editor]'),$=s=>editor?.querySelector(s);
  const endpoint='public/api/account/avatar.php';
  let url=null,pending=null,preview=null,busy=false,paintQueued=false,version=0;
  const fallback=new WeakMap();
  const vi=()=>{try{return (localStorage.getItem('somi-language')||document.documentElement.lang)!=='en';}catch{return true;}};
  const t=(a,b)=>vi()?a:b;
  function message(text){if(editor)$('[data-avatar-status]').textContent=text;}
  function controls(){if(!editor)return;editor.querySelectorAll('[data-w-vi]').forEach(el=>el.textContent=vi()?el.dataset.wVi:el.dataset.wEn);$('[data-avatar-save]').hidden=!pending;$('[data-avatar-cancel]').hidden=!pending;$('[data-avatar-remove]').hidden=!url;editor.querySelectorAll('button,input').forEach(el=>el.disabled=busy);}
  function paint(){
    for(const host of document.querySelectorAll('[data-account-initials],[data-profile] .c-avatar,#avatar,#accountAvatar')){
      if(!fallback.has(host)&&!host.querySelector('img'))fallback.set(host,host.textContent.trim()||'?');
      host.classList.add('workspace-avatar-host');
      if(url){if(host.dataset.avatarFailed===url)continue;if(host.querySelector('img')?.getAttribute('src')===url)continue;const img=document.createElement('img');img.className='workspace-avatar-image';img.alt=t('Ảnh đại diện','Profile photo');img.src=url;img.onerror=()=>{host.dataset.avatarFailed=url;host.textContent=fallback.get(host)||'?';};host.replaceChildren(img);}
      else if(host.querySelector('img.workspace-avatar-image'))host.textContent=fallback.get(host)||'?';
    }
  }
  async function request(method='GET',body){const response=await fetch(endpoint,{method,credentials:'same-origin',headers:{Accept:'application/json'},body,signal:AbortSignal.timeout(20000)});const data=await response.json();if(!response.ok)throw new Error(data.error||t('Không thể lưu ảnh.','Unable to save photo.'));return data;}
  function clear(){pending=null;if(preview)URL.revokeObjectURL(preview);preview=null;if(editor){$('[data-avatar-preview]').hidden=true;$('[data-avatar-preview]').removeAttribute('src');$('[data-avatar-input]').value='';}controls();}
  async function prepare(file){
    if(!['image/jpeg','image/png','image/webp'].includes(file.type)||file.size>5*1024*1024)throw new Error(t('Chọn JPEG, PNG hoặc WebP tối đa 5 MiB.','Choose JPEG, PNG or WebP up to 5 MiB.'));
    const bitmap=await createImageBitmap(file);try{if(bitmap.width*bitmap.height>40000000)throw new Error(t('Ảnh quá lớn. Chọn ảnh có độ phân giải nhỏ hơn.','Image dimensions are too large. Choose a smaller image.'));const canvas=document.createElement('canvas');canvas.width=512;canvas.height=512;const ctx=canvas.getContext('2d');ctx.fillStyle='#ffffff';ctx.fillRect(0,0,512,512);const side=Math.min(bitmap.width,bitmap.height);ctx.drawImage(bitmap,(bitmap.width-side)/2,(bitmap.height-side)/2,side,side,0,0,512,512);return await new Promise((resolve,reject)=>canvas.toBlob(blob=>blob?resolve(blob):reject(new Error(t('Không thể xử lý ảnh.','Unable to process image.'))),'image/jpeg',0.88));}finally{bitmap.close();}
  }
  if(editor){
    $('[data-avatar-input]').addEventListener('change',async event=>{const file=event.target.files[0];if(!file)return;const current=++version;busy=true;controls();message(t('Đang chuẩn bị ảnh…','Preparing photo…'));try{const blob=await prepare(file);if(current!==version)return;clear();pending=blob;preview=URL.createObjectURL(blob);$('[data-avatar-preview]').src=preview;$('[data-avatar-preview]').hidden=false;message(t('Ảnh xem trước. Nhấn Lưu ảnh để cập nhật hồ sơ.','Preview ready. Select Save photo to update your profile.'));}catch(e){clear();message(e.message);}finally{busy=false;controls();}});
    $('[data-avatar-cancel]').onclick=()=>{version++;clear();message('');};
    $('[data-avatar-save]').onclick=async()=>{if(!pending||busy)return;busy=true;controls();message(t('Đang lưu ảnh…','Saving photo…'));try{const body=new FormData();body.set('avatar',pending,'avatar.jpg');const data=await request('POST',body);url=data.avatar_url;clear();paint();message(t('Đã lưu ảnh đại diện.','Profile photo saved.'));}catch(e){message(e.message);}finally{busy=false;controls();}};
    $('[data-avatar-remove]').onclick=async()=>{if(busy)return;busy=true;controls();try{await request('DELETE');url=null;clear();paint();message(t('Đã xóa ảnh đại diện.','Profile photo removed.'));}catch(e){message(e.message);}finally{busy=false;controls();}};
  }
  const observer=new MutationObserver(()=>{if(paintQueued)return;paintQueued=true;queueMicrotask(()=>{paintQueued=false;paint();});});
  observer.observe(document.body,{childList:true,subtree:true});
  request().then(data=>{url=data.avatar_url;paint();controls();}).catch(()=>message(t('Chưa tải được ảnh đại diện. Bạn có thể chọn ảnh và thử lưu lại.','Profile photo could not be loaded. Choose a photo and try saving again.')));
  document.addEventListener('somi:language-change',()=>{controls();paint();});
  window.addEventListener('pagehide',()=>{observer.disconnect();if(preview)URL.revokeObjectURL(preview);});
  window.addEventListener('pageshow',event=>{if(event.persisted){observer.observe(document.body,{childList:true,subtree:true});request().then(data=>{url=data.avatar_url;paint();controls();}).catch(()=>{});}});
  controls();
})();
