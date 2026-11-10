<section class="p3-panel" data-payout-panel>
 <h2>Chi trả Shipper / Shipper payouts</h2>
 <p>Chỉ đối soát khoản đã chuyển thành công, có mã giao dịch và chứng từ. / Reconcile completed transfers with a transaction reference and evidence.</p>
 <button type="button" data-payout-refresh>Làm mới / Refresh</button>
 <p data-payout-status role="status" aria-live="polite"></p>
 <div class="p3-scroll" data-payout-list></div>
 <form data-payout-form>
  <label>Payout ID<input name="payout_id" required pattern="[1-9][0-9]*" inputmode="numeric"></label>
  <label>Mã giao dịch ngân hàng / Bank transaction reference<input name="provider_payout_ref" required maxlength="180"></label>
  <label>Tham chiếu chứng từ / Evidence reference<input name="evidence_reference" required maxlength="1000"></label>
  <label>Số tiền VND đã chuyển / Transferred VND amount<input name="amount_minor" required pattern="[0-9]+" inputmode="numeric"></label>
  <button type="submit">Xác nhận đã chuyển và đối soát / Confirm transfer and reconcile</button>
 </form>
 <details><summary>Ghi nhận thất bại / Retry khoản chi trả</summary>
  <form data-payout-transition>
   <label>Khoản chi trả<input name="payout_id" required pattern="[1-9][0-9]*" inputmode="numeric"></label>
   <label>Thao tác<select name="action"><option value="FAILED">Ghi nhận chuyển khoản thất bại</option><option value="RETRY">Đưa khoản thất bại về chờ xử lý</option></select></label>
   <label>Lý do<textarea name="reason" required maxlength="2000"></textarea></label>
   <label>Chứng từ ngân hàng xác nhận chưa chuyển tiền<input name="evidence_reference" required maxlength="1000"></label>
   <label><span><input type="checkbox" name="transfer_not_completed" required> Đã kiểm tra ngân hàng: khoản này chưa chuyển thành công.</span></label>
   <button type="submit">Lưu trạng thái</button>
  </form>
 </details>
 <details><summary>Tỷ giá cho thu nhập USD / FX evidence</summary>
  <button type="button" data-fx-refresh>Tải thu nhập USD</button><div data-fx-list></div>
  <form data-payout-fx>
   <label>Khoản thu nhập USD<select name="earning_id" required><option value="">Chọn khoản thu nhập</option></select></label>
   <label>Tỷ giá VND cho 1 USD<input name="rate" required inputmode="decimal" pattern="[0-9]{1,9}(\.[0-9]{1,8})?"></label>
   <label>Ngân hàng / Nhà cung cấp tỷ giá<input name="provider" required maxlength="100"></label>
   <label>Mã tham chiếu tỷ giá<input name="reference" required maxlength="180"></label>
   <label>Chứng từ tỷ giá<input name="evidence_reference" required maxlength="1000"></label>
   <button type="submit">Lưu tỷ giá từ chứng từ</button>
  </form>
 </details>
</section>
<link rel="stylesheet" href="<?= e(assetUrl('public/assets/css/person3.css')) ?>">
<script defer src="<?= e(assetUrl('public/assets/js/admin-payouts.js')) ?>"></script>
