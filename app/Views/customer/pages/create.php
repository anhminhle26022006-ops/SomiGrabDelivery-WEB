<?php // Customer: Create delivery order (Gửi hàng) ?>
<section class="c-head">
  <div>
    <h1>Gửi một kiện hàng</h1>
    <p>Chọn điểm lấy và điểm giao. Xem báo giá trước khi xác nhận đơn hàng.</p>
  </div>
</section>

<div data-create-blocked hidden></div>

<div data-create-form-wrap>
  <ol class="c-stepper" data-stepper aria-label="Các bước tạo đơn">
    <li class="is-current"><span>1. Lộ trình</span></li>
    <li><span>2. Kiện hàng</span></li>
    <li><span>3. Thời gian</span></li>
    <li><span>4. Xác nhận</span></li>
  </ol>

  <div class="row g-4 align-items-start c-create-layout">
    <div class="col-lg-7"><form class="card card-body" data-create-form novalidate>
      <!-- Step 1: route -->
      <section data-step="1">
        <h2>Lấy hàng ở đâu, giao đến đâu?</h2>
        <p class="c-panel-sub">Chọn nhanh từ sổ địa chỉ hoặc nhập địa chỉ mới.</p>
        <div class="c-address-pick" data-side="pickup">
          <div class="c-address-pick-head"><span class="c-stop-mark c-stop-a">A</span><strong>Điểm lấy hàng</strong><a class="c-link" href="?page=customer&amp;view=addresses">Quản lý sổ địa chỉ</a></div>
          <button class="c-current-location" type="button" data-use-location><span class="c-location-dot" aria-hidden="true"></span><span><strong data-location-title>Vị trí hiện tại của bạn</strong><small data-current-location>Đang lấy vị trí…</small><b data-location-action>Dùng vị trí này làm điểm lấy →</b></span></button>
          <p class="c-hint" data-location-status role="status" aria-live="polite"></p>
          <div class="c-field"><label class="form-label" for="pickup-detail">Địa chỉ lấy hàng</label><input class="form-control" id="pickup-detail" name="pickup-detail" autocomplete="street-address" placeholder="Nhập địa chỉ lấy hàng, kể cả khi gửi hộ"></div>
          <div class="c-field" hidden><label class="form-label" for="pickup-saved">Dùng địa chỉ đã lưu</label><select class="form-select" id="pickup-saved" data-saved></select></div>
          <div class="row g-3">
            <div class="c-field col-md-6"><label class="form-label" for="pickup-name">Người gửi</label><input class="form-control" id="pickup-name" name="pickup-name" autocomplete="name" placeholder="Họ và tên"></div>
            <div class="c-field col-md-6"><label class="form-label" for="pickup-phone">Số điện thoại</label><input class="form-control" id="pickup-phone" name="pickup-phone" type="tel" inputmode="numeric" autocomplete="tel" placeholder="0903 128 456"></div>
            <div class="c-field col-md-6" hidden><label class="form-label" for="pickup-area">Khu vực</label><select class="form-select" id="pickup-area" name="pickup-area" data-area></select></div>
            <label class="form-check c-check col-md-6" data-save-wrap hidden style="align-self:end;padding-bottom:12px"><input class="form-check-input" type="checkbox" name="pickup-save"> <span class="form-check-label">Lưu vào sổ địa chỉ</span></label>
          </div>
        </div>
        <div class="c-address-pick" data-side="delivery">
          <div class="c-address-pick-head"><span class="c-stop-mark c-stop-b">B</span><strong>Điểm giao hàng</strong></div>
          <div class="c-field"><label class="form-label" for="delivery-detail">Địa chỉ giao hàng</label><input class="form-control" id="delivery-detail" name="delivery-detail" placeholder="Tìm và chọn địa chỉ người nhận"></div>
          <div class="c-field" hidden><label class="form-label" for="delivery-saved">Dùng địa chỉ đã lưu</label><select class="form-select" id="delivery-saved" data-saved></select></div>
          <div class="row g-3">
            <div class="c-field col-md-6"><label class="form-label" for="delivery-name">Người nhận</label><input class="form-control" id="delivery-name" name="delivery-name" placeholder="Họ và tên người nhận"></div>
            <div class="c-field col-md-6"><label class="form-label" for="delivery-phone">Số điện thoại người nhận</label><input class="form-control" id="delivery-phone" name="delivery-phone" type="tel" inputmode="numeric" placeholder="0987 654 321"></div>
            <div class="c-field col-md-6" hidden><label class="form-label" for="delivery-area">Khu vực</label><select class="form-select" id="delivery-area" name="delivery-area" data-area></select></div>
            <label class="form-check c-check col-md-6" data-save-wrap hidden style="align-self:end;padding-bottom:12px"><input class="form-check-input" type="checkbox" name="delivery-save"> <span class="form-check-label">Lưu vào sổ địa chỉ</span></label>
          </div>
        </div>
        <div class="c-pickup-distance" data-pickup-distance hidden role="status" aria-live="polite"></div>
      </section>

      <!-- Step 2: package -->
      <section data-step="2" hidden>
        <h2>Bạn gửi gì?</h2>
        <p class="c-panel-sub">Thông tin này giúp shipper chuẩn bị phương tiện và xử lý kiện hàng đúng cách.</p>
        <fieldset class="c-field" style="border:0;padding:0;margin:0 0 18px">
          <legend class="form-label c-label" style="margin-bottom:8px">Loại kiện hàng</legend>
          <div class="c-tiles" data-types></div>
        </fieldset>
        <div class="row g-3">
          <div class="c-field col-12"><label class="form-label" for="pkg-description">Mô tả kiện hàng</label><input class="form-control" id="pkg-description" name="pkg-description" maxlength="120" placeholder="Ví dụ: 1 hộp quà tặng đã bọc kín"></div>
          <div class="c-field col-md-6"><label class="form-label" for="pkg-weight">Khối lượng (kg)</label><input class="form-control" id="pkg-weight" name="pkg-weight" type="number" inputmode="decimal" min="0.1" step="0.1" placeholder="1.5"><small class="c-hint" data-weight-hint></small></div>
          <div class="c-field col-md-6"><label class="form-label" for="pkg-quantity">Số kiện</label><input class="form-control" id="pkg-quantity" name="pkg-quantity" type="number" inputmode="numeric" min="1" step="1" value="1"></div>
          <div class="c-field col-12"><label class="form-label" for="pkg-note">Ghi chú cho shipper (không bắt buộc)</label><textarea class="form-control" id="pkg-note" name="pkg-note" rows="2" maxlength="200" placeholder="Gọi trước khi đến, gửi bảo vệ nếu vắng nhà..."></textarea></div>
        </div>
      </section>

      <!-- Step 3: schedule -->
      <section data-step="3" hidden>
        <h2>Khi nào cần giao?</h2>
        <p class="c-panel-sub">Giao ngay sẽ tìm shipper từ lúc bạn thanh toán xong.</p>
        <div class="c-tiles" role="radiogroup" aria-label="Thời gian giao">
          <label class="c-tile"><input type="radio" name="schedule" value="now" checked><strong>Giao ngay</strong><small>Shipper đến lấy trong khoảng 15 đến 30 phút</small></label>
          <label class="c-tile"><input type="radio" name="schedule" value="later"><strong>Hẹn giờ lấy hàng</strong><small>Chọn thời điểm trong 7 ngày tới</small></label>
        </div>
        <div class="c-field" data-later hidden style="margin-top:16px"><label class="form-label" for="schedule-at">Thời điểm lấy hàng</label><input class="form-control" id="schedule-at" name="schedule-at" type="datetime-local"></div>
      </section>

      <!-- Step 4: review -->
      <section data-step="4" hidden>
        <h2>Kiểm tra lại đơn hàng</h2>
        <p class="c-panel-sub">Phí giao sẽ được chốt theo thông tin dưới đây.</p>
        <div class="c-summary" data-summary></div>
        <label class="form-check c-check" style="margin-top:18px"><input class="form-check-input" type="checkbox" name="agree" data-agree> <span class="form-check-label">Tôi xác nhận kiện hàng không chứa hàng cấm, chất dễ cháy nổ hoặc hàng hóa vi phạm pháp luật.</span></label>
      </section>

      <div class="c-form-actions">
        <button class="btn btn-outline-dark" type="button" data-prev hidden>Quay lại</button>
        <button class="btn btn-dark" type="submit" data-next>Tiếp tục</button>
      </div>
    </form></div>

    <div class="col-lg-5 c-sticky c-create-sidebar">
      <aside class="card card-body c-create-map-panel" data-create-map-panel>
        <div class="c-create-map-tabs" role="group" aria-label="Chọn điểm trên bản đồ"><button class="btn btn-outline-dark" type="button" data-map-side="pickup" aria-pressed="true">A · Điểm lấy</button><button class="btn btn-outline-dark" type="button" data-map-side="delivery" aria-pressed="false">B · Điểm giao</button></div>
        <div class="somi-leaflet-map" data-create-map></div>
        <div class="c-create-map-legend"><span><i class="c-location-dot" aria-hidden="true"></i><span data-user-location-label>Vị trí của bạn</span></span><span data-pickup-label>A · Điểm lấy</span><span data-delivery-label>B · Điểm giao</span></div>
        <button class="btn btn-outline-dark c-route-use-location" type="button" data-map-use-location hidden>Dùng vị trí hiện tại làm điểm lấy A</button>
        <div class="c-route-summary" data-route-summary hidden aria-live="polite"><strong data-route-distance></strong><span data-route-duration></span><small data-route-summary-label>Tuyến xe máy A → B</small></div>
        <p class="c-hint" data-route-status role="status" aria-live="polite"></p>
        <div class="c-create-map-detail"><strong data-active-point-title>Điểm lấy hàng</strong><p data-active-point-address>Chọn địa chỉ để xác định điểm lấy.</p><p class="c-hint" data-map-pick-hint>Nhấn trên bản đồ để điều chỉnh vị trí đã chọn.</p></div>
      </aside>
      <aside class="card card-body c-dark c-quote" data-quote aria-live="polite" hidden></aside>
    </div>
  </div>
  <dialog class="c-pickup-confirm" data-pickup-confirm aria-labelledby="pickup-confirm-title"><h2 id="pickup-confirm-title">Điểm lấy hàng khác vị trí của bạn</h2><p data-pickup-confirm-message></p><div class="c-pickup-confirm-address" data-pickup-confirm-address></div><p data-pickup-confirm-question>Bạn đang tạo đơn hộ hoặc muốn shipper lấy hàng tại địa chỉ này?</p><div class="c-actions"><button class="btn btn-outline-dark" type="button" data-pickup-cancel>Kiểm tra lại địa chỉ</button><button class="btn btn-dark" type="button" data-pickup-accept>Đúng, lấy tại địa chỉ này</button></div></dialog>
</div>
