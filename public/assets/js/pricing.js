(() => {
  'use strict';
  const config = JSON.parse(document.getElementById('approved-pricing-config').textContent);
  const form = document.querySelector('[data-price-estimator]');
  const money = (amount, currency) => new Intl.NumberFormat(document.documentElement.lang === 'vi' ? 'vi-VN' : 'en-US', {
    style: 'currency', currency, minimumFractionDigits: currency === 'VND' ? 0 : 2,
    maximumFractionDigits: currency === 'VND' ? 0 : 2
  }).format(amount / (currency === 'USD' ? 100 : 1));
  function render() {
    document.querySelectorAll('[data-price-minor]').forEach(el => el.textContent = money(Number(el.dataset.priceMinor), el.dataset.currency));
    const vi = document.documentElement.lang === 'vi';
    const distance = Number(form.elements.distance.value);
    const weight = Number(form.elements.weight.value);
    const output = form.querySelector('[data-price-result]');
    if (!form.elements.distance.value.trim() || !Number.isFinite(distance) || !form.elements.distance.validity.valid || distance >= Number(config.max_distance_km)) {
      output.dataset.invalid = 'true';
      output.textContent = vi ? `Nhập quãng đường từ 0 đến dưới ${Number(config.max_distance_km)} km.` : `Enter a distance from 0 to under ${Number(config.max_distance_km)} km.`;
      return;
    }
    if (!form.elements.weight.value.trim() || !Number.isFinite(weight) || !form.elements.weight.validity.valid) {
      output.dataset.invalid = 'true';
      output.textContent = vi ? `Khối lượng phải lớn hơn 0 và không quá ${Number(config.max_weight_kg)} kg.` : `Weight must be greater than 0 and at most ${Number(config.max_weight_kg)} kg.`;
      return;
    }
    const currency = form.elements.currency.value;
    output.dataset.invalid = 'false';
    const time = form.elements.peak.checked ? 12000 : 10000;
    const weather = Math.round(Number(config.weather[form.elements.weather.value]) * 10000);
    const price = window.SomiDeliveryPrice.calculate(config, form.elements.distance.value, form.elements.weight.value, currency, time, weather);
    output.textContent = money(price.total, currency);
  }
  form.addEventListener('submit', e => e.preventDefault());
  form.addEventListener('input', render);
  document.addEventListener('somi:language-change', render);
  render();
})();
