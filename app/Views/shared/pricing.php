<?php
$pricing = require __DIR__ . '/../../Config/delivery-pricing.php';
$pc = static function (string $en, string $vi): void {
    echo '<span data-copy-en="' . e($en) . '" data-copy-vi="' . e($vi) . '">' . e($en) . '</span>';
};
?>
<main class="info-page pricing-page">
  <section class="info-hero pricing-hero">
    <p class="eyebrow dark-eyebrow"><?php $pc('SIMPLE, UPFRONT PRICING', 'BẢNG GIÁ RÕ RÀNG'); ?></p>
    <h1><?php $pc('Know the cost before you send.', 'Biết trước chi phí trước khi gửi.'); ?></h1>
    <p><?php $pc('Pay for the distance and weight of your parcel. Clear adjustments for peak hours and weather, shown before payment.', 'Cước tính theo quãng đường và trọng lượng hàng. Hệ số giờ cao điểm và thời tiết rõ ràng, hiển thị trước khi thanh toán.'); ?></p>
    <a class="button button-dark" href="<?= linkTo('auth', ['next' => 'customer']) ?>"><?php $pc('Send a Package', 'Gửi hàng'); ?> <span aria-hidden="true">↗</span></a>
  </section>

  <section class="pricing-layout" aria-labelledby="pricing-table-title">
    <article class="pricing-panel">
      <p class="eyebrow dark-eyebrow"><?php $pc('01 · DISTANCE & WEIGHT', '01 · QUÃNG ĐƯỜNG & TRỌNG LƯỢNG'); ?></p>
      <h2 id="pricing-table-title"><?php $pc('A fee that follows your delivery', 'Cước tính theo chuyến giao của bạn'); ?></h2>
      <p><?php $pc('Independent VND and USD price tables. All amounts below are before peak-hour and weather adjustments.', 'Bảng giá VND và USD độc lập. Các mức dưới đây chưa nhân hệ số giờ cao điểm và thời tiết.'); ?></p>
      <div class="pricing-table-wrap"><table class="pricing-table">
        <thead><tr><th scope="col"><?php $pc('Fee component', 'Thành phần cước'); ?></th><th scope="col">VND</th><th scope="col">USD</th></tr></thead>
        <tbody><?php foreach ([['base_fee_minor','Base · first 3 km & 3 kg','Cơ bản · 3 km & 3 kg đầu'],['per_km_minor','Per km beyond 3 km','Mỗi km vượt 3 km'],['per_kg_minor','Per kg beyond 3 kg','Mỗi kg vượt 3 kg']] as [$key,$en,$vi]): ?><tr>
          <th scope="row"><?php $pc($en,$vi); ?></th>
          <td><span data-price-minor="<?= $pricing[$key]['VND'] ?>" data-currency="VND"><?= number_format($pricing[$key]['VND']) ?> ₫</span></td>
          <td><span data-price-minor="<?= $pricing[$key]['USD'] ?>" data-currency="USD">$<?= number_format($pricing[$key]['USD']/100,2) ?></span></td>
        </tr><?php endforeach; ?></tbody>
      </table></div>
      <p class="pricing-note"><?php $pc('Calculated using actual meters and grams, with one final rounding up. Both locations must be in Ho Chi Minh City. Routes must be shorter than 40 km; parcels must weigh more than 0 and at most 30 kg.', 'Tính theo mét và gram thực tế, chỉ làm tròn lên ở tổng cuối. Cả hai điểm phải thuộc TP. Hồ Chí Minh. Tuyến đường dưới 40 km; khối lượng lớn hơn 0 và tối đa 30 kg.'); ?></p>
      <p class="pricing-note"><?php $pc('For a 1 kg parcel in normal conditions: 15 km costs 68,000 ₫ / $3.39; 20 km costs 88,000 ₫ / $4.39.', 'Với kiện 1 kg trong điều kiện bình thường: 15 km có cước 68.000đ / 3,39 USD; 20 km có cước 88.000đ / 4,39 USD.'); ?></p>
    </article>

    <article class="pricing-panel pricing-estimator" aria-labelledby="estimate-title">
      <p class="eyebrow dark-eyebrow"><?php $pc('TRY THE PRICE', 'ƯỚC TÍNH CHI PHÍ'); ?></p>
      <h2 id="estimate-title"><?php $pc('See how your fee adds up', 'Xem cách tính cước của bạn'); ?></h2>
      <form data-price-estimator>
        <label for="price-distance"><?php $pc('Road distance (km)', 'Quãng đường bộ (km)'); ?></label>
        <input id="price-distance" name="distance" type="number" min="0" max="<?= e(bcsub($pricing['max_distance_km'], '0.001', 3)) ?>" step="0.001" value="4" required>
        <label for="price-weight"><?php $pc('Parcel weight (kg)', 'Trọng lượng hàng (kg)'); ?></label>
        <input id="price-weight" name="weight" type="number" min="0.001" max="<?= e($pricing['max_weight_kg']) ?>" step="0.001" value="1.25" required>
        <label for="price-currency"><?php $pc('Price table', 'Bảng tiền tệ'); ?></label>
        <select id="price-currency" name="currency"><option value="VND">VND</option><option value="USD">USD</option></select>
        <label for="price-weather"><?php $pc('Weather at pickup', 'Thời tiết tại điểm lấy'); ?></label>
        <select id="price-weather" name="weather">
          <option value="NORMAL" data-copy-en="Normal · ×1.00" data-copy-vi="Bình thường · ×1,00">Normal · ×1.00</option>
          <option value="LIGHT_RAIN" data-copy-en="Light rain · ×1.10" data-copy-vi="Mưa nhẹ · ×1,10">Light rain · ×1.10</option>
          <option value="RAIN" data-copy-en="Moderate / heavy rain · ×1.20" data-copy-vi="Mưa vừa / nặng · ×1,20">Moderate / heavy rain · ×1.20</option>
        </select>
        <label class="pricing-check"><input name="peak" type="checkbox"><?php $pc('Peak hours · ×1.20', 'Giờ cao điểm · ×1,20'); ?></label>
        <p class="pricing-formula"><?php $pc('(Base + extra distance + extra weight) × time × weather, rounded up once.', '(Cơ bản + quãng đường vượt mức + trọng lượng vượt mức) × giờ × thời tiết, làm tròn lên một lần.'); ?></p>
        <output class="pricing-result" data-price-result aria-live="polite">24,000 ₫</output>
        <p class="pricing-note"><?php $pc('An illustration, not a booking quote. Your actual route and weather are verified when you request a delivery.', 'Đây là công cụ minh họa, chưa phải báo giá đặt đơn. Quãng đường và thời tiết thực tế được kiểm tra khi bạn yêu cầu giao hàng.'); ?></p>
      </form>
    </article>
  </section>

  <section class="info-cards pricing-details">
    <article class="info-card"><span class="feature-number">02</span><h2><?php $pc('Time & weather', 'Giờ & thời tiết'); ?></h2>
      <p><?php $pc('Peak hours: 07:00–09:00 and 17:00–20:00 daily, Vietnam time (UTC+7): ×1.20. All other hours: ×1.00.', 'Cao điểm: 07:00–09:00 và 17:00–20:00 hằng ngày, giờ Việt Nam (UTC+7): ×1,20. Các giờ khác: ×1,00.'); ?></p>
      <p><?php $pc('Normal weather: ×1.00. Light rain: ×1.10. Moderate or heavy rain: ×1.20. New deliveries may pause in unsafe weather.', 'Bình thường: ×1,00. Mưa nhẹ: ×1,10. Mưa vừa hoặc nặng: ×1,20. Có thể tạm ngưng nhận đơn khi thời tiết nguy hiểm.'); ?></p>
    </article>
    <article class="info-card"><span class="feature-number">03</span><h2><?php $pc('Returns & cancellations', 'Hoàn hàng & hủy đơn'); ?></h2>
      <p><?php $pc('Return fee: 10,000 ₫ for VND orders or $0.50 for USD orders. Shown separately in your quote; charged only when the return policy applies.', 'Phí hoàn: 10.000đ với đơn VND hoặc 0,50 USD với đơn USD. Hiển thị riêng trong báo giá; chỉ thu khi áp dụng chính sách hoàn hàng.'); ?></p>
      <p><?php $pc('Free cancellation before a shipper accepts. Once assigned, contact support for the applicable process.', 'Miễn phí hủy trước khi người giao nhận đơn. Khi đã phân công, liên hệ hỗ trợ để được xử lý theo quy trình.'); ?></p>
    </article>
    <article class="info-card"><span class="feature-number">04</span><h2><?php $pc('No surprises', 'Minh bạch trước khi trả tiền'); ?></h2>
      <p><?php $pc('Quotes are valid for 5 minutes. Your confirmed order keeps its price even if the price table changes later.', 'Báo giá có hiệu lực 5 phút. Đơn đã tạo giữ nguyên giá dù bảng giá thay đổi sau đó.'); ?></p>
      <p><?php $pc('VND and USD are priced independently, without a reference exchange rate. Weight above 3 kg is charged proportionally; there is no separate parcel-type surcharge.', 'VND và USD có bảng giá riêng, không dùng tỷ giá tham chiếu. Phần trọng lượng vượt 3 kg được tính theo thực tế; không có phụ phí riêng theo loại kiện hàng.'); ?></p>
    </article>
  </section>
</main>
<script defer src="<?= e(assetUrl('public/assets/js/pricing.js')) ?>"></script>
