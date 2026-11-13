(() => {
  'use strict';
  const panel = document.querySelector('[data-order-chat]');
  if (!panel) return;
  panel.id = 'order-chat';
  const content = panel.querySelector('[data-order-chat-content]');
  const status = panel.querySelector('[data-order-chat-status]');
  let orderId = null, version = 0, focusPending = false;
  function focusChat() {
    panel.scrollIntoView({behavior:'smooth',block:'start'});
    const input=content.querySelector('.support-composer textarea');
    if(input){input.focus({preventScroll:true});focusPending=false;}else focusPending=true;
  }
  document.addEventListener('click',event=>{if(!event.target.closest('[data-open-order-chat],[href="#order-chat"]'))return;event.preventDefault();if(!panel.hidden)focusChat();});
  const t = (vi, en) => (window.SomiI18n?.lang || document.documentElement.lang) === 'en' ? en : vi;
  async function api(path, body) {
    const response = await fetch('public/api/' + path, {
      method: body ? 'POST' : 'GET', credentials: 'same-origin',
      headers: body ? {'Content-Type':'application/json'} : {},
      ...(body ? {body:JSON.stringify(body)} : {}), signal:AbortSignal.timeout(30000)
    });
    const result = await response.json();
    if (!response.ok) {
      const messages={CHAT_CLOSED:t('Đơn đã kết thúc. Bạn chỉ có thể xem lại tin nhắn.','This delivery has ended. Messages are read-only.'),AUTHENTICATION_REQUIRED:t('Phiên đăng nhập hết hạn. Vui lòng đăng nhập lại.','Session expired. Please sign in again.'),SERVICE_UNAVAILABLE:t('Kết nối tạm gián đoạn. Tin nhắn chưa gửi vẫn được giữ lại.','Connection unavailable. Your unsent message is preserved.')};
      throw Object.assign(new Error(messages[result.code]||result.error||t('Không tải được hội thoại.','Could not load conversation.')),{code:result.code,status:response.status});
    }
    return result;
  }
  document.addEventListener('somi:delivery-chat', async event => {
    const order = event.detail;
    const nextId = order ? String(order.order_id) : null;
    if (nextId === orderId) return; // Preserve the draft when delivery details refresh.
    orderId = nextId;
    const token = ++version;
    window.SomiSupport.stop();
    panel.hidden = !order;
    content.replaceChildren();
    status.textContent = '';
    if (!order) return;
    const isCurrent = () => token === version && panel.isConnected;
    const error = cause => {
      if (!isCurrent()) return;
      status.textContent = cause.message;
    };
    try {
      await window.SomiSupport.render('chat', {
        order, content, status, api, t, esc:window.SomiShipper.esc,
        key:() => crypto.randomUUID(), isCurrent, error,
        uploadFile:(file, fields) => window.SomiShipper.uploadPrivate(file, fields.purpose, fields.order_id),
        downloadFile:async id => {
          const signed = await api('storage.php?file_id=' + encodeURIComponent(id));
          const link = document.createElement('a');
          link.href = 'public/api/storage.php?' + signed.download_query;
          link.download = ''; link.click();
        }
      });
      if(focusPending)focusChat();
    } catch (cause) {
      error(cause);
      if (!isCurrent()) return;
      const retry = document.createElement('button');
      retry.type = 'button'; retry.className = 'outline';
      retry.textContent = t('Tải lại hội thoại', 'Retry conversation');
      retry.onclick = () => {
        orderId = null;
        document.dispatchEvent(new CustomEvent('somi:delivery-chat', {detail:order}));
      };
      content.replaceChildren(retry);
    }
  });
})();
