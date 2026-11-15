(() => {
  'use strict';
  const root = document.querySelector('[data-customer-communication]');
  if (!root) return;
  const content = root.querySelector('[data-cc-content]'), notice = root.querySelector('[data-cc-status]');
  let language = document.documentElement.lang;
  try { language = localStorage.getItem('somi-language') || language; } catch {}
  const en = language === 'en', t = (vi, english) => en ? english : vi;
  const h = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const key = () => crypto.randomUUID?.() || Array.from(crypto.getRandomValues(new Uint8Array(20)), b => b.toString(16).padStart(2,'0')).join('');
  const params = new URLSearchParams(location.search);
  let scope = params.get('id') || params.get('order_id') || '';
  let timer, alive = true, generation = 0;
  const closed = status => ['CANCELLED', 'DELIVERED', 'RETURNED'].includes(status);
  const canChat = status => ['SHIPPER_ASSIGNED','PICKED_UP','IN_DELIVERY','RETURNING'].includes(status);
  const date = value => { const d = new Date(value); return Number.isNaN(d.getTime()) ? '' : d.toLocaleString(en ? 'en-GB' : 'vi-VN', {timeZone:'Asia/Ho_Chi_Minh'}); };
  const state = value => ({NEW:t('Chờ hỗ trợ','Awaiting support'),IN_PROGRESS:t('Đang xử lý','In progress'),RESOLVED:t('Đã giải quyết','Resolved'),CLOSED:t('Đã đóng','Closed'),CANCELLED:t('Đã hủy','Cancelled'),DELIVERED:t('Đã giao','Delivered'),RETURNED:t('Đã hoàn hàng','Returned'),SHIPPER_ASSIGNED:t('Đã có Shipper','Shipper assigned'),PICKED_UP:t('Đã lấy hàng','Picked up'),IN_DELIVERY:t('Đang giao','In delivery'),RETURNING:t('Đang hoàn','Returning'),SEARCHING_SHIPPER:t('Đang tìm Shipper','Finding a shipper'),PENDING_PAYMENT:t('Chờ thanh toán','Awaiting payment'),NO_SHIPPER_FOUND:t('Chưa có Shipper','No shipper found')})[value] || value;
  const link = (view, id = '') => '?page=customer&view=' + view + (id ? '&id=' + encodeURIComponent(id) : '');
  const heading = (title, copy) => `<header class="cc-heading"><span class="cc-kicker">SOMI / ${root.dataset.mode === 'chat' ? 'MESSAGES' : 'SUPPORT'}</span><h1>${h(title)}</h1><p>${h(copy)}</p></header>`;
  const empty = text => `<div class="cc-empty"><span aria-hidden="true">◇</span><p>${h(text)}</p></div>`;
  async function api(path, body, method = body === undefined ? 'GET' : 'POST') {
    const controller = new AbortController(), timeout = setTimeout(() => controller.abort(), 20000);
    try {
      const response = await fetch('public/api/' + path, {method, credentials:'same-origin',cache:'no-store',signal:controller.signal,headers:{Accept:'application/json',...(body === undefined ? {} : {'Content-Type':'application/json'})},body:body === undefined ? undefined : JSON.stringify(body)});
      const data = await response.json();
      if (!response.ok) throw new Error(data.error || t('Không thể xử lý yêu cầu.','Request failed.'));
      return data;
    } catch (error) { if (error.name === 'AbortError') throw new Error(t('Chưa xác nhận được kết quả do hết thời gian chờ. Hãy thử lại với yêu cầu hiện tại.','Confirmation timed out. Retry the current request.')); throw error; }
    finally { clearTimeout(timeout); }
  }
  const orderDetail = async id => (await api('customer/orders.php?id=' + encodeURIComponent(id))).order;
  async function orders() {
    const items = []; let cursor;
    do { const data = await api('interactions.php?resource=orders' + (cursor ? '&before_id=' + encodeURIComponent(cursor) : '')); items.push(...data.items); cursor = data.next_cursor; } while (cursor && alive);
    return items;
  }
  const orderOptions = items => items.map(o => `<option value="${h(o.order_id)}">${h(o.order_code)} · ${h(state(o.status))}</option>`).join('');
  function pickedFiles(form) {
    const files = Array.from(form.elements.attachments.files);
    if (files.length > 5 || files.some(f => !f.size || f.size > 5 * 1024 * 1024 || !['image/jpeg','image/png','image/webp','application/pdf'].includes(f.type))) throw new Error(t('Chọn tối đa 5 ảnh/PDF, mỗi tệp từ 1 byte đến 5 MiB.','Choose up to 5 images/PDFs, each between 1 byte and 5 MiB.'));
    return files;
  }
  const attachmentField = () => `<label class="cc-attach"><span aria-hidden="true">＋</span> ${h(t('Đính kèm ảnh / PDF','Attach images / PDFs'))}<input type="file" name="attachments" multiple accept="image/jpeg,image/png,image/webp,application/pdf"></label><div class="cc-selected" data-selected aria-live="polite"></div>`;
  function bindPicker(form) {
    form.elements.attachments.addEventListener('change', () => {
      form.querySelector('[data-selected]').textContent = Array.from(form.elements.attachments.files).map(f => f.name).join(' · ') + (form.elements.attachments.files.length ? ' — ' + t('Chưa gửi','Not sent yet') : '');
    });
  }
  async function upload(file, fields) {
    const body = new FormData(); body.append('file', file);
    Object.entries(fields).forEach(([k,v]) => body.append(k,v));
    const response = await fetch('public/api/storage.php', {method:'POST',credentials:'same-origin',body});
    const saved = await response.json();
    if (!response.ok || !/^[1-9][0-9]*$/.test(String(saved.file_id || ''))) throw new Error(saved.error || t('Máy chủ chưa xác nhận lưu tệp.','The server did not confirm a saved file.'));
    return saved.file_id;
  }
  async function ticketFiles(id) { return (await api('storage.php?ticket_id=' + encodeURIComponent(id))).items; }
  async function uploadTicketFiles(id, files, uploaded) {
    for (let i = uploaded.length; i < files.length; i++) uploaded.push(await upload(files[i], {purpose:'TICKET',ticket_id:id}));
    if (!uploaded.length) return;
    const saved = await ticketFiles(id);
    if (!uploaded.every(id => saved.some(file => String(file.file_id) === String(id)))) throw new Error(t('Chưa xác minh được tệp trong ticket này. Thử lại để kiểm tra; không tạo ticket mới.','Files could not be verified on this ticket. Retry to check; do not create a new ticket.'));
  }
  async function download(id) {
    const signed = await api('storage.php?file_id=' + encodeURIComponent(id));
    const a = document.createElement('a'); a.href = 'public/api/storage.php?' + signed.download_query; a.download = ''; a.click();
  }
  function fileButtons(files, target) {
    target.innerHTML = files.map(f => `<button type="button" class="cc-file" data-file="${h(f.file_id)}"><span aria-hidden="true">↧</span> ${h(f.original_name)}</button>`).join('');
    target.querySelectorAll('[data-file]').forEach(b => b.onclick = () => download(b.dataset.file).catch(error => { notice.textContent = error.message; }));
  }
  function lock(form, locked) { form.querySelectorAll('input,textarea,select,button').forEach(el => { el.disabled = locked; }); }

  async function chat() {
    const embedded = ['order','tracking'].includes(document.body.dataset.cView);
    const items = scope ? [await orderDetail(scope)] : await orders();
    content.innerHTML = (embedded ? '' : heading(t('Chat với Shipper','Chat with your Shipper'),t('Trao đổi giao nhận theo từng đơn. Nếu cần hỗ trợ từ Admin, vào mục Hỗ trợ.','Talk about your delivery here. For help from Admin, open Support.'))) +
      `<div class="cc-chat-layout ${embedded ? 'cc-embedded' : ''}"><aside class="cc-picker"><h2>${h(t('Đơn hàng của bạn','Your deliveries'))}</h2><form data-open><label>${h(t('Chọn đơn','Select delivery'))}<select name="order_id">${orderOptions(items)}</select></label><button class="cc-primary" ${items.length ? '' : 'disabled'}>${h(t('Mở trò chuyện','Open conversation'))}</button></form><a class="cc-support-link" href="${h(link('reports'))}">${h(t('Cần Admin hỗ trợ?','Need help from Admin?'))} ↗</a></aside>
      <section class="cc-thread"><header class="cc-thread-head"><span class="cc-avatar" aria-hidden="true">S</span><div><h2>${h(t('Shipper của đơn hàng','Your delivery Shipper'))}</h2><p data-thread-label>${h(t('Chọn đơn để bắt đầu','Select a delivery to start'))}</p></div><button type="button" class="cc-secondary" data-refresh>${h(t('Làm mới','Refresh'))}</button></header><div class="cc-messages" data-messages role="log" aria-live="polite">${empty(t('Chưa có hội thoại được chọn.','No conversation selected.'))}</div><div data-compose></div></section></div>`;
    const form = content.querySelector('[data-open]'), messages = content.querySelector('[data-messages]'), compose = content.querySelector('[data-compose]');
    let current = '', token = 0, sending = false, refreshing = false, latestState = '', historyCursor;
    const drafts = new Map();
    async function load(before, automatic = false) {
      if (!current || refreshing) return;
      const id = current, version = token; refreshing = true;
      try {
        const [data, order] = await Promise.all([api('interactions.php?resource=chat&order_id=' + encodeURIComponent(id) + (before ? '&before_id=' + encodeURIComponent(before) : '')), orderDetail(id)]);
        if (version !== token || !alive) return;
        content.querySelector('[data-thread-label]').textContent = order.order_code + ' · ' + state(order.status);
        if (latestState !== order.status) { latestState = order.status; renderComposer(order); }
        const atBottom = messages.scrollHeight - messages.scrollTop - messages.clientHeight < 80;
        const previousHeight = messages.scrollHeight;
        if (!before) { messages.replaceChildren(); historyCursor = data.next_cursor; }
        const fragment = document.createDocumentFragment();
        for (const message of [...data.items].reverse()) {
          const mine = String(message.sender_id) === String(document.body.dataset.userId);
          const row = document.createElement('article'); row.className = 'cc-message' + (mine ? ' cc-mine' : '');
          row.innerHTML = `<small>${h(mine ? t('Bạn','You') : t('Shipper','Shipper'))}</small><div class="cc-bubble"><p>${h(message.body)}</p><div data-files></div></div><time>${h(date(message.sent_at))}</time>`;
          fileButtons(message.attachments || [], row.querySelector('[data-files]')); fragment.append(row);
        }
        if (before) messages.prepend(fragment); else messages.append(fragment);
        if (!data.items.length && !before) messages.innerHTML = empty(t('Chưa có tin nhắn. Nội dung trao đổi sẽ xuất hiện tại đây.','No messages yet. Your conversation will appear here.'));
        messages.querySelector('[data-older]')?.remove();
        const next = before ? data.next_cursor : historyCursor;
        if (next) { const b = document.createElement('button'); b.type = 'button'; b.className = 'cc-older'; b.dataset.older = ''; b.textContent = t('Tin nhắn cũ hơn','Older messages'); b.onclick = () => load(next).catch(showError); messages.prepend(b); }
        if (before) messages.scrollTop += messages.scrollHeight - previousHeight;
        else if (!automatic || atBottom) messages.scrollTop = messages.scrollHeight;
        if (data.items.length && !before) await api('interactions.php?resource=chat', {order_id:id,read_through_id:data.items[0].message_id});
      } finally { refreshing = false; }
    }
    function showError(error) { notice.textContent = error.message; }
    function renderComposer(order) {
      const id = current;
      if (!canChat(order.status)) {
        compose.innerHTML = `<div class="cc-closed"><p>${h(closed(order.status) ? t('Đơn đã kết thúc. Bạn có thể xem lại cuộc trò chuyện.','This delivery has ended. You can still read its conversation.') : t('Chưa có Shipper nhận đơn. Bạn có thể nhắn khi đơn được phân công.','No Shipper assigned yet. Messaging is available once a Shipper accepts.'))}</p><a href="${h(link('reports',id))}">${h(t('Liên hệ Admin về đơn này','Contact Admin about this delivery'))} ↗</a></div>`; return;
      }
      const draft = drafts.get(id) || {body:'', files:[], uploaded:[], key:key(), signature:''}; drafts.set(id,draft);
      compose.innerHTML = `<form class="cc-composer"><label class="cc-sr">${h(t('Tin nhắn cho Shipper','Message to Shipper'))}</label><textarea name="body" maxlength="4000" rows="2" aria-label="${h(t('Tin nhắn cho Shipper','Message to Shipper'))}" placeholder="${h(t('Nhắn cho Shipper về đơn hàng…','Message your Shipper about this delivery…'))}">${h(draft.body)}</textarea><div class="cc-compose-bar">${attachmentField()}<button type="submit" class="cc-primary">${h(t('Gửi tin nhắn','Send message'))} ↗</button></div><p data-feedback class="cc-feedback" role="status"></p></form>`;
      const composer = compose.querySelector('form'); bindPicker(composer);
      if (draft.files.length) { const transfer = new DataTransfer(); draft.files.forEach(f => transfer.items.add(f)); composer.elements.attachments.files = transfer.files; composer.elements.attachments.dispatchEvent(new Event('change')); }
      composer.elements.body.oninput = () => { draft.body = composer.elements.body.value; };
      composer.elements.attachments.addEventListener('change', () => { draft.files = Array.from(composer.elements.attachments.files); });
      composer.onsubmit = async event => {
        event.preventDefault(); if (sending) return;
        const feedback = composer.querySelector('[data-feedback]'); feedback.textContent = '';
        try {
          const files = pickedFiles(composer), body = composer.elements.body.value.trim();
          if (!body && !files.length) throw new Error(t('Nhập tin nhắn hoặc chọn tệp.','Write a message or choose a file.'));
          const signature = JSON.stringify([body,files.map(f => [f.name,f.size,f.lastModified])]);
          if (draft.signature && draft.signature !== signature) { draft.key = key(); draft.uploaded = []; } draft.signature = signature;
          sending = true; lock(composer,true); form.querySelector('button').disabled = true;
          for (let i = draft.uploaded.length; i < files.length; i++) draft.uploaded.push(await upload(files[i],{purpose:'CHAT',order_id:id}));
          await api('interactions.php?resource=chat',{order_id:id,body,client_key:draft.key,...(draft.uploaded.length ? {file_ids:draft.uploaded} : {})});
          drafts.delete(id); latestState = ''; while(refreshing && alive) await new Promise(resolve=>setTimeout(resolve,30)); await load();
        } catch (error) { feedback.textContent = error.message; }
        finally { sending = false; lock(composer,false); form.querySelector('button').disabled = false; }
      };
    }
    async function open() {
      if (sending) return;
      clearInterval(timer); token++; current = form.elements.order_id.value; latestState = ''; notice.textContent = '';
      const version = token;
      while (refreshing) { await new Promise(resolve => setTimeout(resolve,30)); if (version !== token || !alive) return; }
      await load();
      if (!alive || version !== token) return;
      timer = setInterval(() => { if (!document.hidden && !sending) load(null,true).catch(showError); },8000);
    }
    form.onsubmit = event => { event.preventDefault(); open().catch(showError); };
    content.querySelector('[data-refresh]').onclick = () => load().catch(showError);
    if (scope && items.length) await open();
  }

  async function support() {
    const scopedOrder = scope ? await orderDetail(scope) : null;
    const items = scopedOrder ? [scopedOrder] : await orders();
    const filter = scope ? '&order_id=' + encodeURIComponent(scope) : '';
    content.innerHTML = heading(t('Hỗ trợ từ SOMI','SOMI support'),t('Gửi yêu cầu và trao đổi với Admin. Chat giao nhận với Shipper nằm ở mục riêng.','Send requests and talk with Admin. Delivery chat with your Shipper has its own space.')) +
      `<div class="cc-scope">${h(scopedOrder ? scopedOrder.order_code : t('Tất cả yêu cầu của bạn','All your requests'))}<a href="${h(link(scopedOrder ? 'reports' : 'chat'))}">${h(scopedOrder ? t('Xem tất cả yêu cầu','All requests') : t('Chat với Shipper','Chat with Shipper'))} ↗</a></div><div class="cc-support-layout"><section class="cc-card"><h2>${h(t('Tạo yêu cầu hỗ trợ','Create a support request'))}</h2><p class="cc-muted">${h(t('Gửi ảnh và chứng từ kèm yêu cầu để SOMI hiểu rõ vấn đề.','Include images or documents to help SOMI understand your request.'))}</p>
      <form data-create><label>${h(t('Đơn hàng liên quan','Related delivery'))}<select name="order_id" ${scopedOrder ? 'disabled' : ''}>${scopedOrder ? '' : `<option value="">${h(t('Tài khoản / vấn đề chung','Account / general issue'))}</option>`}${orderOptions(items)}</select></label><label>${h(t('Bạn cần hỗ trợ về','Category'))}<select name="category"><option value="DELIVERY">${h(t('Giao nhận','Delivery'))}</option><option value="PAYMENT">${h(t('Thanh toán / hoàn tiền','Payment / refund'))}</option><option value="ACCOUNT">${h(t('Tài khoản','Account'))}</option><option value="OTHER">${h(t('Khác','Other'))}</option></select></label><label>${h(t('Tiêu đề','Subject'))}<input name="subject" required maxlength="200" placeholder="${h(t('Bạn cần SOMI giúp điều gì?','How can SOMI help?'))}"></label><label>${h(t('Nội dung','Description'))}<textarea name="description" required rows="5" maxlength="8000" placeholder="${h(t('Mô tả sự việc và điều bạn cần hỗ trợ…','Describe what happened and how we can help…'))}"></textarea></label>${attachmentField()}<p class="cc-hint">${h(t('Tối đa 5 ảnh/PDF, mỗi tệp 5 MiB. Tệp được gửi cùng thao tác gửi yêu cầu.','Up to 5 images/PDFs, 5 MiB each. Files are sent when you submit the request.'))}</p><button type="submit" class="cc-primary">${h(t('Gửi yêu cầu','Submit request'))} ↗</button><p class="cc-feedback" data-feedback role="status"></p></form></section><section class="cc-tickets"><header class="cc-list-head"><h2>${h(t('Yêu cầu của bạn','Your requests'))}</h2><button class="cc-secondary" data-refresh type="button">${h(t('Làm mới','Refresh'))}</button></header><div data-tickets></div></section></div>`;
    const list = content.querySelector('[data-tickets]'), form = content.querySelector('[data-create]'); bindPicker(form);
    let creating = false, creation = null, clientKey = key(), previous = '', uploaded = [];
    async function loadTickets(before) {
      const data = await api('interactions.php?resource=tickets' + filter + (before ? '&before_id=' + encodeURIComponent(before) : ''));
      if (!alive) return;
      if (!before) list.replaceChildren();
      if (!data.items.length && !before) list.innerHTML = empty(t('Chưa có yêu cầu hỗ trợ trong phạm vi này.','No support requests in this view yet.'));
      for (const item of data.items) {
        const card = document.createElement('article'); card.className = 'cc-ticket';
        card.innerHTML = `<header><small>${h(item.ticket_code)}</small><span class="cc-badge">${h(state(item.status))}</span></header><button class="cc-ticket-open" type="button" aria-expanded="false">${h(item.subject)} <span aria-hidden="true">↗</span></button><p class="cc-muted">${h(item.order_code || (item.order_id ? '#' + item.order_id : t('Hỗ trợ tài khoản','Account support')))} · ${h(date(item.created_at))}</p><div data-detail hidden></div>`;
        const open = card.querySelector('.cc-ticket-open'), detail = card.querySelector('[data-detail]');
        open.onclick = async () => {
          if (!detail.hidden) { detail.hidden = true; open.setAttribute('aria-expanded','false'); return; }
          open.disabled = true; detail.hidden = false; open.setAttribute('aria-expanded','true');
          try { await conversation(item,detail); } catch (error) { detail.textContent = error.message; } finally { open.disabled = false; }
        }; list.append(card);
      }
      list.querySelector('[data-more]')?.remove();
      if (data.next_cursor) { const b = document.createElement('button'); b.type = 'button'; b.dataset.more = ''; b.className = 'cc-secondary'; b.textContent = t('Yêu cầu cũ hơn','Older requests'); b.onclick = async () => { b.disabled = true; try { await loadTickets(data.next_cursor); } catch (error) { notice.textContent = error.message; b.disabled = false; } }; list.append(b); }
    }
    form.onsubmit = async event => {
      event.preventDefault(); if (creating || !form.reportValidity()) return;
      const feedback = form.querySelector('[data-feedback]'); feedback.textContent = '';
      try {
        const files = pickedFiles(form);
        const body = {category:form.elements.category.value,subject:form.elements.subject.value.trim(),description:form.elements.description.value.trim()};
        const id = scopedOrder ? scope : form.elements.order_id.value; if (id) body.order_id = id;
        if (!body.subject || !body.description) throw new Error(t('Nhập tiêu đề và nội dung.','Enter a subject and description.'));
        const signature = JSON.stringify(body);
        if (!creation && previous && signature !== previous) clientKey = key(); previous = signature;
        creating = true; lock(form,true); feedback.textContent = t('Đang gửi yêu cầu và tệp đính kèm…','Sending request and attachments…');
        if (!creation) creation = (await api('interactions.php?resource=tickets',{...body,client_key:clientKey})).ticket;
        await uploadTicketFiles(creation.ticket_id,files,uploaded);
        const code = creation.ticket_code || '#' + creation.ticket_id;
        form.reset(); form.querySelector('[data-selected]').textContent = ''; creation = null; uploaded = []; clientKey = key(); previous = '';
        feedback.textContent = t('Đã lưu yêu cầu và các tệp đính kèm: ','Request and attachments saved: ') + code; await loadTickets().catch(error=>{notice.textContent=error.message;});
      } catch (error) {
        feedback.textContent = (creation ? (creation.ticket_code || '#'+creation.ticket_id)+' — '+t('Ticket đã được tạo; tệp chưa xác nhận đủ. Bấm thử lại để tiếp tục trên cùng ticket. ','The ticket exists; attachments are not fully confirmed. Retry to continue on the same ticket. ') : '') + error.message;
      } finally {
        creating = false;
        if (creation) { lock(form,true); form.querySelector('[type=submit]').disabled = false; form.querySelector('[type=submit]').textContent = t('Thử lại đính kèm cho ticket này','Retry attachments for this ticket'); }
        else { lock(form,false); if (scopedOrder) form.elements.order_id.disabled = true; form.querySelector('[type=submit]').textContent = t('Gửi yêu cầu','Submit request') + ' ↗'; }
      }
    };
    async function conversation(item, target) {
      const data = await api('interactions.php?resource=tickets&id=' + encodeURIComponent(item.ticket_id)), ticket = data.ticket;
      if (scope && String(ticket.order_id) !== String(scope)) throw new Error(t('Ticket không thuộc đơn đang mở.','This ticket does not belong to the selected delivery.'));
      target.innerHTML = `<div class="cc-original"><small>${h(t('Nội dung yêu cầu ban đầu','Original request'))}</small><p>${h(ticket.description)}</p></div><h3>${h(t('Trao đổi với Admin','Conversation with Admin'))}</h3><div data-replies></div><div class="cc-ticket-files"><h4>${h(t('Tệp đã lưu trong yêu cầu','Files saved to this request'))}</h4><div data-files></div></div><div data-reply-form></div>`;
      const replies = target.querySelector('[data-replies]'), filesBox = target.querySelector('[data-files]');
      async function refreshFiles() { const files = await ticketFiles(item.ticket_id); fileButtons(files,filesBox); if (!files.length) filesBox.textContent = t('Chưa có tệp đính kèm.','No attachments yet.'); }
      async function refreshReplies(before) {
        const response = await api('support/replies.php?ticket_id=' + encodeURIComponent(item.ticket_id) + (before ? '&before_id=' + encodeURIComponent(before) : ''));
        if (!before) replies.replaceChildren();
        for (const r of [...response.items].reverse()) { const a = document.createElement('article'); a.className = 'cc-reply'; a.innerHTML = `<small>${h(r.sender_role === 'ADMIN' ? t('Admin · SOMI hỗ trợ','Admin · SOMI support') : t('Bạn','You'))} · ${h(date(r.created_at))}</small><p>${h(r.body)}</p>`; if (before) replies.prepend(a); else replies.append(a); }
        replies.querySelector('[data-older]')?.remove();
        if (!response.items.length && !before) replies.innerHTML = `<p class="cc-waiting">${h(t('Yêu cầu đã được ghi nhận. Đang chờ Admin phản hồi.','Your request has been received. Waiting for Admin to reply.'))}</p>`;
        if (response.next_cursor) { const b = document.createElement('button'); b.type='button'; b.className='cc-older'; b.dataset.older=''; b.textContent=t('Phản hồi cũ hơn','Older replies'); b.onclick=()=>refreshReplies(response.next_cursor).catch(error=>{notice.textContent=error.message;}); replies.prepend(b); }
      }
      await Promise.all([refreshReplies(),refreshFiles()]);
      if (ticket.status === 'CLOSED') { target.querySelector('[data-reply-form]').textContent = t('Yêu cầu đã đóng.','This request is closed.'); return; }
      target.querySelector('[data-reply-form]').innerHTML = `<form class="cc-reply-form"><label>${h(t('Nhắn thêm cho Admin','Send a follow-up to Admin'))}<textarea name="body" required rows="3" maxlength="8000" placeholder="${h(t('Bổ sung thông tin hoặc trả lời Admin…','Add details or reply to Admin…'))}"></textarea></label>${attachmentField()}<button type="submit" class="cc-primary">${h(t('Gửi cho Admin','Send to Admin'))} ↗</button><p data-feedback class="cc-feedback" role="status"></p></form>`;
      const reply = target.querySelector('form'); bindPicker(reply); let busy=false,replyKey=key(),last='',ids=[];
      reply.onsubmit = async event => {
        event.preventDefault(); if (busy || !reply.reportValidity()) return;
        const feedback = reply.querySelector('[data-feedback]'); feedback.textContent='';
        try {
          const files=pickedFiles(reply),body=reply.elements.body.value.trim(); if(!body) throw new Error(t('Nhập nội dung gửi Admin.','Enter your message to Admin.'));
          const signature=JSON.stringify([body,files.map(f=>[f.name,f.size,f.lastModified])]); if(last && signature!==last){replyKey=key();ids=[];}last=signature;
          busy=true;lock(reply,true);await uploadTicketFiles(item.ticket_id,files,ids);
          await api('support/replies.php',{ticket_id:item.ticket_id,body,client_key:replyKey});
          reply.reset();reply.querySelector('[data-selected]').textContent='';replyKey=key();last='';ids=[];
          await Promise.all([refreshReplies(),refreshFiles()]);feedback.textContent=t('Đã gửi cho Admin.','Sent to Admin.');
        } catch(error){feedback.textContent=error.message;}finally{busy=false;lock(reply,false);}
      };
    }
    content.querySelector('[data-refresh]').onclick = () => loadTickets().catch(error => { notice.textContent=error.message; });
    await loadTickets();
  }
  window.addEventListener('pagehide', () => { alive=false;generation++;clearInterval(timer); });
  window.somiPerson3Open = tab => { location.href = link(tab === 'chat' ? 'chat' : tab === 'notifications' ? 'notifications' : 'reports', scope); };
  (async () => {
    const version=++generation;
    try { if(root.dataset.mode==='chat') await chat(); else await support(); }
    catch(error) { if(version===generation) { notice.textContent=error.message; const retry=document.createElement('button');retry.className='cc-secondary';retry.textContent=t('Tải lại','Reload');retry.onclick=()=>location.reload();content.replaceChildren(retry); } }
    finally { content.setAttribute('aria-busy','false'); }
  })();
})();
