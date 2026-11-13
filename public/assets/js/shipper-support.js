"use strict";
(() => {
  let timer;
  const paths = {
    ticket: 'M4 5h16v5a2 2 0 0 0 0 4v5H4v-5a2 2 0 0 0 0-4V5Zm8 1v2m0 3v2m0 3v2',
    chat: 'M21 11.5a8.5 8.5 0 0 1-8.5 8.5H4l-2 2V11.5a9.5 9.5 0 0 1 19 0ZM7 10h9m-9 4h6',
    file: 'M14 2H5v20h14V7l-5-5Zm0 0v6h5M8 13h8m-8 4h5',
    bank: 'm3 9 9-6 9 6H3Zm2 3v6m7-6v6m7-6v6M3 21h18',
  };
  const icon = type => `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${paths[type] ? `<path d="${paths[type]}"/>` : '<circle cx="12" cy="12" r="8"/><path d="M12 8v5m0 3h.01"/>'}</svg>`;
  const empty = (title, copy, type = 'ticket') => `<div class="support-empty">${icon(type)}<h3>${title}</h3><p>${copy}</p></div>`;
  const date = (value, en) => {
    const parsed = new Date(value);
    return Number.isNaN(parsed.getTime()) ? '' : new Intl.DateTimeFormat(en ? 'en-GB' : 'vi-VN', {day:'2-digit',month:'2-digit',hour:'2-digit',minute:'2-digit'}).format(parsed);
  };
  function stop() { clearInterval(timer); timer = null; }
  const orderStatus = (state,t) => ({CREATED:t('Đã tạo đơn','Created'),PENDING_PAYMENT:t('Chờ thanh toán','Awaiting payment'),SEARCHING_SHIPPER:t('Đang tìm người giao','Finding a shipper'),SHIPPER_ASSIGNED:t('Đã nhận đơn','Assigned'),PICKED_UP:t('Đã lấy hàng','Picked up'),IN_DELIVERY:t('Đang giao hàng','In delivery'),DELIVERED:t('Đã giao hàng','Delivered'),RETURNING:t('Đang hoàn hàng','Returning'),RETURNED:t('Đã hoàn hàng','Returned'),CANCELLED:t('Đã hủy','Cancelled'),NO_SHIPPER_FOUND:t('Chưa tìm được người giao','No shipper found')})[state] || state;

  async function render(tab, ctx) {
    stop();
    const {content, status, api, esc, t, key, uploadFile, downloadFile, ticketConversation, isCurrent} = ctx;
    const en = document.body.dataset.shipperLang === 'en';
    const fail = error => { if (isCurrent()) ctx.error(error); };
    const heading = (title, copy) => `<header class="support-section-head"><h2>${title}</h2><p>${copy}</p></header>`;
    const stateLabel = state => ({NEW:t('Mới tiếp nhận','New'),OPEN:t('Đang mở','Open'),IN_PROGRESS:t('Đang xử lý','In progress'),RESOLVED:t('Đã giải quyết','Resolved'),CLOSED:t('Đã đóng','Closed')})[state] || state;
    async function orders() {
      const result = []; let cursor;
      do {
        const data = await api('interactions.php?resource=orders' + (cursor ? '&before_id=' + encodeURIComponent(cursor) : ''));
        if (!isCurrent()) return [];
        result.push(...data.items); cursor = data.next_cursor;
      } while (cursor);
      return result;
    }
    const options = items => items.map(o => `<option value="${esc(o.order_id)}" data-support-order-code="${esc(o.order_code)}" data-support-order-status="${esc(o.status)}">${esc(o.order_code)} · ${esc(orderStatus(o.status,t))}</option>`).join('');

    if (tab === 'tickets') {
      const [data, items] = await Promise.all([api('interactions.php?resource=tickets'), orders()]);
      if (!isCurrent()) return;
      content.innerHTML = heading(t('Yêu cầu hỗ trợ','Support requests'), t('Mô tả vấn đề của bạn. Theo dõi mọi phản hồi ngay tại đây.','Tell us what happened and follow every response here.')) + `
        <div class="support-ticket-layout">
          <section class="support-create" aria-labelledby="supportCreateTitle">
            <div class="support-card-heading"><span class="support-icon">${icon('ticket')}</span><div><h3 id="supportCreateTitle">${t('Tạo yêu cầu mới','Create a request')}</h3><p>${t('Thông tin chi tiết giúp chúng tôi hỗ trợ tốt hơn.','Details help us understand your request.')}</p></div></div>
            <form data-support-create>
              <label>${t('Đơn hàng liên quan','Related order')} <span class="support-optional">${t('Không bắt buộc','Optional')}</span><select name="order_id"><option value="">${t('Hỗ trợ tài khoản / vấn đề chung','Account / general support')}</option>${options(items)}</select></label>
              <label>${t('Bạn cần hỗ trợ về','What do you need help with?')}<select name="category"><option value="DELIVERY">${t('Giao nhận hàng','Delivery')}</option><option value="PAYMENT">${t('Thu nhập & thanh toán','Earnings & payments')}</option><option value="ACCOUNT">${t('Tài khoản & hồ sơ','Account & profile')}</option><option value="OTHER">${t('Vấn đề khác','Something else')}</option></select></label>
              <label>${t('Tiêu đề','Subject')}<input name="subject" required maxlength="200" placeholder="${t('Ví dụ: Cần kiểm tra khoản chi trả của đơn hàng','For example: Check a delivery payout')}" autocomplete="off"></label>
              <label>${t('Mô tả chi tiết','Description')}<textarea name="description" required maxlength="8000" rows="5" placeholder="${t('Mô tả sự việc, thời điểm xảy ra và điều bạn cần hỗ trợ…','Describe what happened, when it happened and how we can help…')}"></textarea></label>
              <p class="support-form-note">${icon('file')}${t('Bạn có thể bổ sung tệp và phản hồi sau khi tạo yêu cầu.','You can add files and replies after creating the request.')}</p>
              <button class="support-primary" type="submit">${t('Gửi yêu cầu','Submit request')} <span aria-hidden="true">↗</span></button>
              <p class="support-form-feedback" data-create-feedback role="status" aria-live="polite"></p>
            </form>
          </section>
          <section class="support-ticket-history" aria-labelledby="supportHistoryTitle"><div class="support-list-heading"><h3 id="supportHistoryTitle">${t('Yêu cầu của bạn','Your requests')}</h3><span class="support-count">${data.items.length}${data.next_cursor ? '+' : ''}</span></div><div data-support-ticket-list></div></section>
        </div>`;
      const form = content.querySelector('[data-support-create]');
      const selected = new URLSearchParams(location.search).get('id');
      if (items.some(item => String(item.order_id) === selected)) form.elements.order_id.value = selected;
      let clientKey = key(), lastPayload = '', submitting = false;
      form.addEventListener('submit', async event => {
        event.preventDefault(); if (submitting || !form.reportValidity()) return;
        const body = Object.fromEntries(new FormData(form));
        body.subject = body.subject.trim(); body.description = body.description.trim();
        const feedback = form.querySelector('[data-create-feedback]');
        if (!body.subject || !body.description) { feedback.textContent = t('Vui lòng nhập tiêu đề và nội dung.','Enter a subject and description.'); return; }
        if (!body.order_id) delete body.order_id;
        const signature = JSON.stringify(body);
        if (lastPayload && signature !== lastPayload) clientKey = key(); lastPayload = signature;
        submitting = true; const button = form.querySelector('[type=submit]'); button.disabled = true;
        feedback.textContent = t('Đang gửi yêu cầu…','Sending request…');
        try {
          await api('interactions.php?resource=tickets', {...body, client_key:clientKey});
          if (!isCurrent()) return;
          form.reset(); clientKey = key(); lastPayload = '';
          feedback.textContent = t('Đã gửi yêu cầu. Bạn có thể theo dõi và bổ sung thông tin ở danh sách bên cạnh.','Request sent. Follow it and add details in your request list.');
          const next = await api('interactions.php?resource=tickets');
          if (isCurrent()) { list.replaceChildren(); content.querySelector('.support-ticket-history .support-count').textContent=next.items.length+(next.next_cursor?'+':''); appendTickets(next); }
        } catch (error) { if (isCurrent()) feedback.textContent = error.message; }
        finally { submitting = false; button.disabled = false; }
      });
      const list = content.querySelector('[data-support-ticket-list]');
      function appendTickets(data) {
        if (!list.children.length && !data.items.length) list.innerHTML = empty(t('Chưa có yêu cầu nào','No requests yet'), t('Các yêu cầu bạn gửi và phản hồi từ SOMI sẽ xuất hiện tại đây.','Your requests and responses from SOMI will appear here.'));
        for (const item of data.items) {
          const card = document.createElement('article'); card.className = 'support-ticket';
          card.innerHTML = `<div class="support-ticket-meta"><span>${esc(item.ticket_code)}</span><span class="support-status" data-state="${esc(item.status)}">${esc(stateLabel(item.status))}</span></div><button class="support-ticket-open" type="button" aria-expanded="false"><span data-support-user-content>${esc(item.subject)}</span><span aria-hidden="true">↗</span></button><small>${esc(date(item.created_at, en))}</small><div class="support-ticket-detail" hidden></div>`;
          const button = card.querySelector('button'), box = card.querySelector('.support-ticket-detail');
          button.addEventListener('click', async () => {
            if (button.disabled) return;
            if (box.dataset.loaded) { box.hidden = !box.hidden; button.setAttribute('aria-expanded', String(!box.hidden)); return; }
            button.disabled = true; box.hidden = false; box.textContent = t('Đang tải trao đổi…','Loading conversation…'); button.setAttribute('aria-expanded','true');
            try {
              const detail = await api('interactions.php?resource=tickets&id=' + encodeURIComponent(item.ticket_id));
              if (!isCurrent() || !box.isConnected) return;
              box.innerHTML = `<p data-support-user-content>${esc(detail.ticket.description)}</p><div class="support-ticket-timeline">${detail.events.map(e => `<p><small>${esc(date(e.created_at,en))} · ${esc(stateLabel(e.new_status))}</small><br><span data-support-user-content>${esc(e.note)}</span></p>`).join('')}</div>`;
              await ticketConversation(box, item.ticket_id, detail.ticket.status); box.dataset.loaded = 'true';
            } catch (error) { box.textContent = error.message; }
            finally { button.disabled = false; }
          });
          list.append(card);
        }
        if (data.next_cursor) {
          const more = document.createElement('button'); more.className = 'support-secondary'; more.type='button'; more.textContent=t('Xem yêu cầu trước','Load older requests');
          more.onclick = async () => {
            more.disabled = true;
            try { const next = await api('interactions.php?resource=tickets&before_id=' + encodeURIComponent(data.next_cursor)); if (isCurrent()) { more.remove(); appendTickets(next); } }
            catch (error) { fail(error); more.disabled = false; }
          }; list.append(more);
        }
      }
      appendTickets(data);
      return;
    }

    if (tab === 'chat') {
      const [items, account] = await Promise.all([ctx.order ? Promise.resolve([ctx.order]) : orders(), api('account/profile.php')]); if (!isCurrent()) return;
      content.innerHTML = heading(t('Trò chuyện theo đơn','Order conversations'), t('Trao đổi với khách hàng và lưu lại thông tin giao nhận.','Keep delivery conversations with your customers in one place.')) + `
        <div class="support-chat-layout"><aside class="support-chat-picker"><div class="support-list-heading"><h3>${t('Đơn hàng','Orders')}</h3><span class="support-count">${items.length}</span></div><form data-open><label>${t('Chọn đơn để trò chuyện','Select an order')}<select name="order_id" required>${items.length ? options(items) : `<option value="">${t('Chưa có đơn hàng','No orders yet')}</option>`}</select></label><button type="submit" class="support-primary" ${items.length ? '' : 'disabled'}>${t('Mở hội thoại','Open conversation')}</button></form><div class="support-chat-note">${icon('chat')}<p>${t('Chỉ trao đổi thông tin liên quan đến đơn hàng. Không chia sẻ mật khẩu hoặc mã OTP.','Keep conversations related to the delivery. Never share passwords or OTP codes.')}</p></div></aside>
        <section class="support-chat-thread" aria-label="${t('Hội thoại đơn hàng','Order conversation')}"><header class="support-thread-head"><span class="support-icon">${icon('chat')}</span><div><h3 data-chat-title>${t('Hội thoại đơn hàng','Order conversation')}</h3><p data-chat-subtitle>${t('Chọn một đơn hàng để bắt đầu','Select an order to get started')}</p></div><button class="support-refresh" type="button" data-chat-refresh hidden>${t('Làm mới','Refresh')}</button></header><div data-messages class="support-messages" role="log" aria-live="polite" aria-relevant="additions">${empty(t(items.length ? 'Sẵn sàng kết nối' : 'Chưa có hội thoại', items.length ? 'Ready to connect' : 'No conversations yet'), t('Chọn đơn hàng ở bên cạnh để xem và gửi tin nhắn.','Select an order to view and send messages.'),'chat')}</div><div data-compose hidden></div></section></div>`;
      const openForm = content.querySelector('[data-open]'), box = content.querySelector('[data-messages]'), compose = content.querySelector('[data-compose]');
      const selected = ctx.order ? String(ctx.order.order_id) : new URLSearchParams(location.search).get('id');
      if (ctx.order) {
        content.querySelector('.support-chat-picker').hidden = true;
        content.querySelector('.support-chat-layout').style.gridTemplateColumns = 'minmax(0, 1fr)';
      }
      if (items.some(item => String(item.order_id) === selected)) openForm.elements.order_id.value = selected;
      let order = '', messageIds = '', loading = false, conversationVersion = 0;
      const userId = String(account.profile.user_id);
      const current = () => isCurrent() && box.isConnected;
      async function loadMessages(before, automatic = false) {
        if (!order || !current() || loading) return;
        const capturedOrder = order, token = conversationVersion;
        loading = true;
        try {
          const data = await api('interactions.php?resource=chat&order_id=' + encodeURIComponent(order) + (before ? '&before_id=' + encodeURIComponent(before) : ''));
          if (!current() || token !== conversationVersion) return;
          status.textContent = '';
          const signature = JSON.stringify(data.items);
          // Do not replace the message log on every heartbeat or disturb a draft.
          if (!before && automatic && messageIds === signature) return;
          const atBottom = box.scrollHeight - box.scrollTop - box.clientHeight < 70;
          if (!before) { box.replaceChildren(); messageIds = signature; }
          const fragment = document.createDocumentFragment();
          for (const m of [...data.items].reverse()) {
            const article = document.createElement('article'); article.className = 'support-message' + (String(m.sender_id) === userId ? ' is-mine' : '');
            article.innerHTML = `<small><span>${String(m.sender_id) === userId ? t('Bạn','You') : t('Thành viên đơn hàng','Order participant')}</span> · ${esc(date(m.sent_at,en))}</small><p data-support-user-content>${esc(m.body)}</p><span class="support-read-state">${m.read_at ? t('Đã đọc','Read') : t('Đã gửi','Sent')}</span>`;
            for (const file of m.attachments || []) {
              const button = document.createElement('button'); button.type='button'; button.className='support-file-link'; button.textContent=file.original_name; button.dataset.supportUserContent='true';
              button.onclick=()=>downloadFile(file.file_id).catch(fail); article.append(button);
            }
            fragment.append(article);
          }
          const previousHeight = box.scrollHeight;
          if (before) box.prepend(fragment); else box.append(fragment);
          if (!data.items.length && !before) box.innerHTML = empty(t('Bắt đầu cuộc trò chuyện','Start a conversation'), t('Gửi tin nhắn đầu tiên để trao đổi về đơn hàng này.','Send the first message about this order.'),'chat');
          if (data.next_cursor) {
            const more = document.createElement('button'); more.type='button'; more.className='support-older'; more.textContent=t('Xem tin nhắn trước','Older messages');
            more.onclick=()=>{more.remove(); loadMessages(data.next_cursor).catch(fail);}; box.prepend(more);
          }
          if (before) box.scrollTop += box.scrollHeight - previousHeight;
          else if (!automatic || atBottom) box.scrollTop = box.scrollHeight;
          if (data.items.length && !before) await api('interactions.php?resource=chat',{order_id:capturedOrder,read_through_id:data.items[0].message_id}).catch(fail);
        } finally { loading = false; }
      }
      async function openOrder() {
        stop(); conversationVersion++; order=openForm.elements.order_id.value; messageIds='';
        const item = items.find(item => String(item.order_id) === order);
        if (!item) return;
        if (!ctx.order) { const url = new URL(location.href); url.searchParams.set('id',order); history.replaceState(null,'',url); }
        content.querySelector('[data-chat-title]').textContent=item.order_code;
        content.querySelector('[data-chat-subtitle]').textContent=t('Tin nhắn trong đơn hàng','Messages for this delivery');
        box.innerHTML=empty(t('Đang tải hội thoại…','Loading conversation…'),t('Vui lòng đợi trong giây lát.','Please wait a moment.'),'chat');
        compose.hidden=true;
        // Finish any prior request before opening a new order; its response is version guarded.
        const token = conversationVersion;
        const threadOrder = order;
        while (loading) { await new Promise(resolve=>setTimeout(resolve,50)); if (!current() || token !== conversationVersion) return; }
        await loadMessages(); if (!current() || token !== conversationVersion) return;
        content.querySelector('[data-chat-refresh]').hidden=false;
        compose.hidden=false;
        compose.innerHTML=`<form class="support-composer"><label class="support-sr-only" for="supportMessageBody">${t('Tin nhắn','Message')}</label><textarea id="supportMessageBody" name="body" maxlength="4000" rows="2" placeholder="${t('Nhập tin nhắn về đơn hàng…','Write a message about this order…')}"></textarea><div class="support-compose-actions"><label class="support-attachment">${icon('file')}<span>${t('Đính kèm','Attach files')}</span><input type="file" name="attachments" multiple accept="image/jpeg,image/png,image/webp,application/pdf"></label><button type="submit" class="support-primary">${t('Gửi tin nhắn','Send message')} <span aria-hidden="true">↗</span></button></div><span data-selected-files data-support-user-content class="support-selected-files"></span><p data-chat-feedback class="support-form-feedback" role="status"></p></form>`;
        const form=compose.querySelector('form'); let clientKey=key(),last='',uploaded=[],sending=false;
        form.elements.attachments.onchange=()=>{form.querySelector('[data-selected-files]').textContent=Array.from(form.elements.attachments.files).map(f=>f.name).join(', ');};
        form.addEventListener('submit',async event=>{
          event.preventDefault(); if(sending)return;
          const files=Array.from(form.elements.attachments.files),body=form.elements.body.value.trim(),feedback=form.querySelector('[data-chat-feedback]');
          if (!body && !files.length) {feedback.textContent=t('Nhập tin nhắn hoặc chọn tệp.','Enter a message or select a file.'); return;}
          if (files.length>5 || files.some(f=>f.size>5*1024*1024 || !['image/jpeg','image/png','image/webp','application/pdf'].includes(f.type))) {feedback.textContent=t('Tối đa 5 tệp ảnh hoặc PDF, mỗi tệp không quá 5 MiB.','Attach up to 5 images or PDFs, at most 5 MiB each.');return;}
          const signature=JSON.stringify([body,files.map(f=>[f.name,f.size,f.lastModified])]);
          if(last && signature!==last){clientKey=key();uploaded=[];} last=signature;
          sending=true; form.elements.body.readOnly=true; form.elements.attachments.disabled=true; form.querySelector('[type=submit]').disabled=true; feedback.textContent=t('Đang gửi…','Sending…');
          try {
            for(let i=uploaded.length;i<files.length;i++) uploaded.push((await uploadFile(files[i],{purpose:'CHAT',order_id:threadOrder})).file_id);
            await api('interactions.php?resource=chat',{order_id:threadOrder,body,client_key:clientKey,...(uploaded.length?{file_ids:uploaded}:{})});
            form.reset(); clientKey=key(); last=''; uploaded=[]; form.querySelector('[data-selected-files]').textContent=''; feedback.textContent='';
            if(current() && token===conversationVersion)await loadMessages();
          } catch(error){feedback.textContent=error.message;}
          finally{sending=false;form.elements.body.readOnly=false;form.elements.attachments.disabled=false;form.querySelector('[type=submit]').disabled=false;}
        });
        timer=setInterval(()=>{if(!document.hidden && current())loadMessages(null,true).catch(fail);},8000);
      }
      openForm.addEventListener('submit',event=>{event.preventDefault();if(openForm.reportValidity())openOrder().catch(fail);});
      content.querySelector('[data-chat-refresh]').onclick=()=>loadMessages().catch(fail);
      if(selected && items.some(item=>String(item.order_id)===selected))await openOrder();
    }
  }
  window.addEventListener('pagehide',stop);
  window.SomiSupport = {render,stop,icon,orderStatus};
})();
