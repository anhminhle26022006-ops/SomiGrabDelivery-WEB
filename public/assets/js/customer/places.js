(() => {
  'use strict';
  let serial=0;
  window.SomiPlaces={attach(input,{api,language='vi',onSelect,onInvalidate}) {
    const vi=language==='vi', say=(a,b)=>vi?a:b;
    const box=document.createElement('div'), list=document.createElement('div'), status=document.createElement('small');
    box.className='somi-places';list.className='list-group mt-1';list.id='places-'+(++serial);list.setAttribute('role','listbox');
    status.className='c-hint d-block';status.setAttribute('role','status');status.setAttribute('aria-live','polite');
    input.after(box);box.append(list,status);
    input.setAttribute('role','combobox');input.setAttribute('aria-autocomplete','list');input.setAttribute('aria-controls',list.id);input.setAttribute('aria-expanded','false');input.autocomplete='off';
    let timer,version=0,token=null,items=[],active=-1,disposed=false;
    const clear=()=>{list.replaceChildren();items=[];active=-1;input.setAttribute('aria-expanded','false');input.removeAttribute('aria-activedescendant');};
    const unavailable=()=>say('Không thể tìm địa chỉ lúc này. Thử lại hoặc nhập tọa độ thủ công.','Address search is unavailable. Retry or enter coordinates manually.');
    const highlight=()=>{[...list.querySelectorAll('[role=option]')].forEach((b,i)=>{b.classList.toggle('active',i===active);b.setAttribute('aria-selected',String(i===active));});if(active>=0)input.setAttribute('aria-activedescendant',list.children[active].id);else input.removeAttribute('aria-activedescendant');};
    async function select(item){
      const revision=++version;clearTimeout(timer);clear();status.textContent=say('Đang lấy vị trí…','Loading location…');
      try{const data={address:item.address};
        if(disposed||revision!==version||!input.isConnected)return;
        input.value=data.address.address_detail;onSelect(data.address);token=null;status.textContent=say('Đã xác định vị trí.','Location selected.');
      }catch(e){if(revision===version)status.textContent=unavailable();}
    }
    const change=()=>{
      const revision=++version;clearTimeout(timer);clear();onInvalidate();status.textContent='';
      if(input.readOnly||input.value.trim().length<3)return;
      token??=crypto.randomUUID();
      timer=setTimeout(async()=>{
        status.textContent=say('Đang tìm địa chỉ…','Searching addresses…');
        try{const data=await api('customer/places.php','POST',{action:'autocomplete',input:input.value.trim(),session_token:token,language});
          if(disposed||revision!==version||!input.isConnected)return;
          items=data.items;status.textContent=items.length?'':say('Không tìm thấy địa chỉ. Thử nhập cụ thể hơn.','No results. Try a more specific address.');
          for(const [i,item] of items.entries()){const b=document.createElement('button');b.type='button';b.className='list-group-item list-group-item-action';b.id=list.id+'-'+i;b.setAttribute('role','option');b.setAttribute('aria-selected','false');b.tabIndex=-1;b.textContent=item.text;b.addEventListener('mousedown',e=>e.preventDefault());b.onclick=()=>select(item);list.append(b);}
          if(items.length){const attribution=document.createElement('small');attribution.className='text-end text-muted p-1';const credit=document.createElement('a');credit.href='https://www.geoapify.com/';credit.textContent='Powered by Geoapify';attribution.append(credit);list.append(attribution);input.setAttribute('aria-expanded','true');}
        }catch(e){if(revision===version)status.textContent=unavailable();}
      },350);
    };
    const keydown=e=>{if(e.key==='Escape'){++version;clearTimeout(timer);clear();status.textContent='';}else if(items.length&&['ArrowDown','ArrowUp'].includes(e.key)){e.preventDefault();active=(active+(e.key==='ArrowDown'?1:-1)+items.length)%items.length;highlight();}else if(e.key==='Enter'&&active>=0){e.preventDefault();select(items[active]);}};
    const blur=()=>{++version;clearTimeout(timer);clear();};
    input.addEventListener('input',change);input.addEventListener('keydown',keydown);input.addEventListener('blur',blur);
    return {reset(){++version;clearTimeout(timer);clear();token=null;status.textContent='';},destroy(){disposed=true;++version;clearTimeout(timer);input.removeEventListener('input',change);input.removeEventListener('keydown',keydown);input.removeEventListener('blur',blur);box.remove();}};
  }};
})();
