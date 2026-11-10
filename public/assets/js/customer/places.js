(() => {
  'use strict';
  let serial = 0, running = false, rateLimitedUntil = 0;
  const waiting = new Map(), cache = new Map();
  // Keep only the latest queued search per field and one request in flight.
  async function drain() {
    if (running) return;
    running = true;
    try {
      while (waiting.size) {
        const [owner, job] = waiting.entries().next().value;
        waiting.delete(owner);
        await job();
      }
    } finally { running = false; }
  }
  window.SomiPlaces = {attach(input, {api, language = 'vi', onSelect, onInvalidate}) {
    const owner = ++serial, vi = language === 'vi', say = (a,b) => vi ? a : b;
    const box = document.createElement('div'), list = document.createElement('div'), status = document.createElement('small'), search = document.createElement('button');
    box.className = 'somi-places'; list.className = 'list-group mt-1'; list.id = 'places-'+owner; list.setAttribute('role','listbox');
    status.className = 'c-hint d-block'; status.setAttribute('role','status'); status.setAttribute('aria-live','polite');
    search.type = 'button'; search.className = 'btn btn-outline-dark btn-sm mt-2'; search.textContent = say('Tìm địa chỉ đầy đủ', 'Search full address'); search.hidden = true;
    input.after(box); box.append(list,status,search);
    input.setAttribute('role','combobox'); input.setAttribute('aria-autocomplete','list'); input.setAttribute('aria-controls',list.id); input.setAttribute('aria-expanded','false'); input.autocomplete = 'off';
    let timer, version = 0, token = null, items = [], active = -1, disposed = false, composing = false;
    const clear = () => {list.replaceChildren(); items=[]; active=-1; input.setAttribute('aria-expanded','false'); input.removeAttribute('aria-activedescendant');};
    const cancel = () => {++version; clearTimeout(timer); waiting.delete(owner); clear(); input.setAttribute('aria-busy','false'); search.hidden=true;};
    const unavailable = () => say('Chưa tải được gợi ý. Bấm tìm lại hoặc chọn ghim trên bản đồ.', 'Suggestions could not be loaded. Retry the search or select a map pin.');
    const highlight = () => {
      [...list.querySelectorAll('[role=option]')].forEach((b,i) => {b.classList.toggle('active',i===active); b.setAttribute('aria-selected',String(i===active));});
      if (active>=0) input.setAttribute('aria-activedescendant',list.children[active].id); else input.removeAttribute('aria-activedescendant');
    };
    function select(item) {
      cancel();
      if (disposed || !input.isConnected) return;
      input.value = item.address.address_detail;
      onSelect(item.address); token = null;
      status.textContent = say('Đã xác định vị trí. Kiểm tra ghim trên bản đồ.', 'Location selected. Check the pin on the map.');
    }
    function display(data, action) {
      clear(); items = Array.isArray(data.items) ? data.items : [];
      input.setAttribute('aria-busy','false');
      search.hidden = items.length>0;
      status.textContent = items.length ? say('Chọn một địa chỉ bên dưới để xác nhận vị trí.', 'Select an address below to confirm the location.') : action==='geocode'
        ? say('Không tìm thấy địa chỉ. Thử chỉ nhập tên đường và thành phố, rồi chỉnh ghim đến số nhà.', 'No address found. Try the street and city, then adjust the pin to the building.')
        : say('Chưa có gợi ý phù hợp. Thử thêm tên đường và thành phố.', 'No matching suggestions yet. Add the street and city.');
      for (const [i,item] of items.entries()) {
        const b = document.createElement('button'); b.type='button'; b.className='list-group-item list-group-item-action'; b.id=list.id+'-'+i;
        b.setAttribute('role','option'); b.setAttribute('aria-selected','false'); b.tabIndex=-1; b.textContent=item.text;
        b.addEventListener('pointerdown',e => e.preventDefault()); b.addEventListener('mousedown',e => e.preventDefault()); b.onclick=() => select(item); list.append(b);
      }
      if (items.length) {
        const attribution=document.createElement('small'); attribution.className='text-end text-muted p-1';
        const credit=document.createElement('a'); credit.href='https://www.geoapify.com/'; credit.textContent='Powered by Geoapify'; attribution.append(credit); list.append(attribution);
        input.setAttribute('aria-expanded','true');
      }
    }
    function schedule(action='autocomplete', immediate=false) {
      cancel();
      if (composing || input.readOnly || input.value.trim().length<3) {status.textContent=''; return;}
      const revision=version, text=input.value.trim(), cacheKey=JSON.stringify([language,action,text.toLocaleLowerCase()]);
      const cached=cache.get(cacheKey);
      if (cached && cached.expires>Date.now()) {display(cached.data,action); return;}
      token ??= crypto.randomUUID();
      status.textContent=say('Đang tìm địa chỉ…','Searching addresses…'); input.setAttribute('aria-busy','true');
      const enqueue = () => {
        waiting.set(owner, async () => {
          if (disposed || revision!==version || !input.isConnected) return;
          try {
            if (Date.now()<rateLimitedUntil) throw {code:'RATE_LIMITED'};
            let data=await api('customer/places.php','POST',{action,input:text,session_token:token,language});
            // A complete pasted address may have no prefix suggestions. Expand
            // automatically; Enter is an optional shortcut, never a requirement.
            if (action==='autocomplete' && !data.items?.length && text.length>=5 && !disposed && revision===version && input.isConnected) {
              status.textContent=say('Đang tìm thêm địa chỉ phù hợp…', 'Looking for more matching addresses…');
              data=await api('customer/places.php','POST',{action:'geocode',input:text,session_token:token,language});
            }
            if (data.items?.length) {
              cache.delete(cacheKey); cache.set(cacheKey,{data,expires:Date.now()+120000});
              if (cache.size>30) cache.delete(cache.keys().next().value);
            }
            if (!disposed && revision===version && input.isConnected) display(data,action);
          } catch (error) {
            if (error.code==='RATE_LIMITED' || error.status===429) rateLimitedUntil=Date.now()+60000;
            if (!disposed && revision===version) {
              input.setAttribute('aria-busy','false'); search.hidden=false;
              status.textContent=Date.now()<rateLimitedUntil ? say('Bạn đang tìm quá nhanh. Vui lòng chờ một phút rồi thử lại.', 'Too many searches. Please wait a minute and retry.') : error.status===401 ? say('Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.', 'Your session expired. Please sign in again.') : unavailable();
            }
          }
        });
        return drain();
      };
      if (immediate) enqueue(); else timer=setTimeout(enqueue,400);
    }
    const change = () => {onInvalidate(); schedule();};
    const keydown = e => {
      if (e.isComposing || composing) return;
      if (e.key==='Escape') {cancel(); status.textContent='';}
      else if (items.length && ['ArrowDown','ArrowUp'].includes(e.key)) {e.preventDefault(); active=(active+(e.key==='ArrowDown'?1:-1)+items.length)%items.length; highlight();}
      else if (e.key==='Enter') {
        e.preventDefault(); e.stopPropagation();
        if (active>=0) select(items[active]); else {onInvalidate(); schedule('geocode',true);}
      }
    };
    const blur = () => {cancel(); status.textContent='';};
    const focus = () => {if(!disposed && input.value.trim().length>=3) schedule();};
    const compositionStart = () => {composing=true; cancel();};
    const compositionEnd = () => {composing=false; change();};
    search.addEventListener('mousedown',e => e.preventDefault()); search.onclick=() => {onInvalidate(); schedule('geocode',true);};
    input.addEventListener('input',change); input.addEventListener('keydown',keydown); input.addEventListener('blur',blur);
    input.addEventListener('focus',focus);
    input.addEventListener('compositionstart',compositionStart); input.addEventListener('compositionend',compositionEnd);
    return {
      reset() {cancel(); token=null; status.textContent='';},
      destroy() {disposed=true; cancel(); input.removeEventListener('input',change); input.removeEventListener('keydown',keydown); input.removeEventListener('blur',blur); input.removeEventListener('focus',focus); input.removeEventListener('compositionstart',compositionStart); input.removeEventListener('compositionend',compositionEnd); box.remove();}
    };
  }};
})();
