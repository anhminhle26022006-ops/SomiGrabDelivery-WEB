"use strict";
(() => {
  const api = window.SomiShipper;
  const ranges = ['today', 'yesterday', 'week', 'month'];
  const text = (vi, en) => document.body.dataset.shipperLang === 'en' ? en : vi;
  let version = 0;
  let profileData;
  let deliveryMinutes;
  function paintDuration() {
    if (deliveryMinutes === undefined) return;
    const value = text(`${Math.floor(deliveryMinutes / 60)} giờ ${deliveryMinutes % 60} phút`, `${Math.floor(deliveryMinutes / 60)}h ${deliveryMinutes % 60}m`);
    document.querySelectorAll('[data-period-value="hours"]').forEach(el => { el.textContent = value; });
  }

  api.paintProfile = ({ profile }) => {
    profileData = { profile };
    document.querySelectorAll('[data-shipper-name]').forEach(el => { el.textContent = profile.full_name; });
    const initials = profile.full_name.trim().split(/\s+/).slice(-2).map(part => part[0]).join('').toUpperCase();
    document.querySelectorAll('#avatar > span,#accountAvatar > span').forEach(el => { el.textContent = initials; });
    document.querySelectorAll('[data-shipper-rating]').forEach(el => {
      el.textContent = profile.customer_rating_count ? `${Number(profile.customer_rating_average).toFixed(1)} / 5` : '—';
    });
    document.querySelectorAll('[data-shipper-rating-count]').forEach(el => {
      el.textContent = `${profile.customer_rating_count} ${text('đánh giá', 'reviews')}`;
    });
  };

  async function load(range) {
    if (!document.getElementById('page-dashboard')) return;
    range = ranges.includes(range) ? range : 'today';
    const token = ++version;
    const periods = {today: ['hôm nay', 'today'], yesterday: ['hôm qua', 'yesterday'], week: ['tuần này', 'this week'], month: ['tháng này', 'this month']};
    document.querySelectorAll('[data-period-stat]').forEach(el => {
      el.textContent = (el.dataset.periodStat === 'deliveries' ? text('Đơn hoàn thành ', 'Deliveries completed ') : text('Thời gian xử lý đơn ', 'Delivery time ')) + text(...periods[range]);
    });
    const status = document.querySelector('[data-dashboard-status]');
    const set = (kind, value) => document.querySelectorAll(`[data-period-value="${kind}"]`).forEach(el => { el.textContent = value; });
    set('deliveries', '…'); set('hours', '…');
    if (status) { status.hidden = false; status.textContent = text('Đang tải thống kê…', 'Loading statistics…'); }
    try {
      const data = await api.dashboard(range);
      if (token !== version) return;
      const dateValue = document.querySelector('[data-date-value]');
      if (dateValue) {
        const format = new Intl.DateTimeFormat(document.body.dataset.shipperLang === 'en' ? 'en-GB' : 'vi-VN', {timeZone: data.timezone, day:'2-digit', month:'2-digit', year:'numeric'});
        const start = format.format(new Date(data.start));
        const end = format.format(new Date(new Date(data.end).getTime() - 1));
        dateValue.textContent = start === end ? `(${start})` : `(${start} – ${end})`;
      }
      set('deliveries', String(data.completed_deliveries));
      const minutes = Math.floor(data.delivery_seconds / 60);
      deliveryMinutes = minutes;
      paintDuration();
      if (status) { status.hidden = false; status.textContent = text('Thời gian tính từ lúc nhận đến lúc kết thúc phân công, theo giờ Việt Nam.', 'Time from assignment to closure, in Vietnam time.'); }
    } catch (error) {
      if (token !== version) return;
      set('deliveries', '—'); set('hours', '—');
      if (status) { status.hidden = false; status.textContent = error.message; }
    }
  }

  window.addEventListener('somi:shipper-period', event => load(event.detail.range));
  document.addEventListener('somi:language-change', () => { if (profileData) api.paintProfile(profileData); paintDuration(); });
  document.addEventListener('DOMContentLoaded', () => {
    load(localStorage.getItem('somi-shipper-date-range') || 'today');
    if (!document.getElementById('page-account')) api.profile().then(api.paintProfile).catch(error => api.toast(error.message));
  });
})();
