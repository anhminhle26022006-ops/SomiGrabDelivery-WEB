<?php
// Shared by the customer avatar dialog and Shipper resubmission page.
$applicationField = static function (string $name, string $vi, string $en, string $type='text', int $max=100, string $extra=''): void {
    $id = 'shipper-application-'.$name;
    ?><label class="sa-field" for="<?= e($id) ?>"><span data-copy-vi="<?= e($vi) ?>" data-copy-en="<?= e($en) ?>"><?= e($vi) ?> <span aria-hidden="true">*</span></span><input id="<?= e($id) ?>" name="<?= e($name) ?>" type="<?= e($type) ?>" maxlength="<?= $max ?>" required <?= $extra ?>></label><?php
};
?>
<link rel="stylesheet" href="<?= e(assetUrl('public/assets/css/modules/shipper-application.css')) ?>">
<form class="sa-form" data-shipper-application-form>
  <p class="sa-intro" data-copy-vi="Chuẩn bị giấy tờ còn hiệu lực, chụp rõ đủ bốn góc. Hồ sơ được Admin kiểm tra trước khi bạn có thể nhận đơn." data-copy-en="Prepare valid documents with all four corners visible. Admin must review your application before you can accept deliveries.">Chuẩn bị giấy tờ còn hiệu lực, chụp rõ đủ bốn góc. Hồ sơ được Admin kiểm tra trước khi bạn có thể nhận đơn.</p>
  <p class="sa-note" data-copy-vi="Bạn cần đủ 18 tuổi, đã xác thực email và điện thoại. Họ tên, ngày sinh và số định danh được lấy từ tài khoản. Các trường có * là bắt buộc." data-copy-en="You must be 18 or older with a verified email and phone. Your name, birth date and identity number come from your account. Fields marked * are required.">Bạn cần đủ 18 tuổi, đã xác thực email và điện thoại. Họ tên, ngày sinh và số định danh được lấy từ tài khoản. Các trường có * là bắt buộc.</p>
  <fieldset>
    <legend data-copy-vi="01 · Liên hệ & khu vực hoạt động" data-copy-en="01 · Contact & service area">01 · Liên hệ & khu vực hoạt động</legend>
    <div class="sa-grid">
      <?php $applicationField('residential_address','Địa chỉ cư trú đầy đủ','Full residential address','text',255,'autocomplete="street-address"'); ?>
      <label class="sa-field" for="shipper-application-operating_area"><span data-copy-vi="Tỉnh/thành, khu vực nhận đơn *" data-copy-en="City and service area *">Tỉnh/thành, khu vực nhận đơn *</span><select id="shipper-application-operating_area" name="operating_area" required autocomplete="address-level1">
        <option value="" data-copy-vi="Chọn tỉnh/thành" data-copy-en="Select a province/city">Chọn tỉnh/thành</option>
        <?php foreach (['TP. Hà Nội','TP. Hải Phòng','TP. Huế','TP. Đà Nẵng','TP. Hồ Chí Minh','TP. Cần Thơ','An Giang','Bắc Ninh','Cà Mau','Cao Bằng','Đắk Lắk','Điện Biên','Đồng Nai','Đồng Tháp','Gia Lai','Hà Tĩnh','Hưng Yên','Khánh Hòa','Lai Châu','Lâm Đồng','Lạng Sơn','Lào Cai','Nghệ An','Ninh Bình','Phú Thọ','Quảng Ngãi','Quảng Ninh','Quảng Trị','Sơn La','Tây Ninh','Thái Nguyên','Thanh Hóa','Tuyên Quang','Vĩnh Long'] as $province): ?>
          <option value="<?= e($province) ?>"><?= e($province) ?></option>
        <?php endforeach; ?>
      </select></label>
      <?php $applicationField('emergency_contact_name','Họ tên người liên hệ khẩn cấp','Emergency contact name'); ?>
      <?php $applicationField('emergency_contact_phone','Điện thoại liên hệ khẩn cấp','Emergency contact phone','tel',20,'pattern="[+]?[0-9]{8,15}" inputmode="tel"'); ?>
      <?php $applicationField('emergency_contact_relationship','Mối quan hệ với bạn','Relationship to you','text',60); ?>
    </div>
  </fieldset>
  <fieldset>
    <legend data-copy-vi="02 · Phương tiện giao hàng" data-copy-en="02 · Delivery vehicle">02 · Phương tiện giao hàng</legend>
    <div class="sa-grid">
      <label class="sa-field" for="shipper-application-vehicle_type"><span data-copy-vi="Loại phương tiện *" data-copy-en="Vehicle type *">Loại phương tiện *</span><select id="shipper-application-vehicle_type" name="vehicle_type" required><option value="MOTORBIKE" data-copy-vi="Xe máy" data-copy-en="Motorbike">Xe máy</option><option value="CAR" data-copy-vi="Ô tô" data-copy-en="Car">Ô tô</option><option value="BICYCLE" data-copy-vi="Xe đạp" data-copy-en="Bicycle">Xe đạp</option></select></label>
      <div data-sa-motorized><?php $applicationField('vehicle_registration','Biển số xe','Licence plate','text',40); ?></div>
      <?php $applicationField('vehicle_brand','Hãng xe','Vehicle brand','text',80); ?>
      <?php $applicationField('vehicle_model','Dòng xe','Vehicle model','text',80); ?>
      <?php $applicationField('vehicle_color','Màu xe','Vehicle colour','text',40); ?>
      <?php $applicationField('vehicle_year','Năm sản xuất','Manufacture year','number',4,'min="1980" max="'.((int)date('Y')+1).'"'); ?>
      <?php $applicationField('vehicle_owner_name','Họ tên chủ xe','Registered owner name'); ?>
      <label class="sa-field" for="shipper-application-vehicle_ownership"><span data-copy-vi="Quyền sử dụng xe *" data-copy-en="Vehicle ownership *">Quyền sử dụng xe *</span><select id="shipper-application-vehicle_ownership" name="vehicle_ownership" required><option value="OWNED" data-copy-vi="Xe chính chủ" data-copy-en="I own this vehicle">Xe chính chủ</option><option value="BORROWED" data-copy-vi="Xe mượn / thuê / được ủy quyền" data-copy-en="Borrowed / rented / authorised vehicle">Xe mượn / thuê / được ủy quyền</option></select></label>
    </div>
  </fieldset>
  <fieldset data-sa-motorized>
    <legend data-copy-vi="03 · Giấy phép lái xe & bảo hiểm" data-copy-en="03 · Driving licence & insurance">03 · Giấy phép lái xe & bảo hiểm</legend>
    <div class="sa-grid">
      <?php $applicationField('license_number','Số giấy phép lái xe','Driving licence number','text',40,'pattern="[A-Za-z0-9-]{4,40}" autocomplete="off"'); ?>
      <?php $applicationField('license_class','Hạng GPLX phù hợp với xe','Licence class for your vehicle','text',20); ?>
      <?php $applicationField('license_issued_on','Ngày cấp GPLX','Licence issue date','date'); ?>
      <div data-sa-expiry><?php $applicationField('license_expires_on','Ngày hết hạn GPLX','Licence expiry date','date'); ?></div>
      <label class="sa-check"><input type="checkbox" name="license_no_expiry"><span data-copy-vi="GPLX không có thời hạn (theo giấy tờ)" data-copy-en="Licence has no expiry date (as printed)">GPLX không có thời hạn (theo giấy tờ)</span></label>
      <?php $applicationField('insurance_expires_on','Ngày hết hạn bảo hiểm TNDS','Liability insurance expiry date','date'); ?>
    </div>
    <p class="sa-note" data-copy-vi="Thông tin phải khớp giấy tờ tải lên. Admin đối chiếu hạng GPLX và quyền điều khiển loại xe đăng ký." data-copy-en="Details must match your documents. Admin checks the licence class and entitlement for your vehicle.">Thông tin phải khớp giấy tờ tải lên. Admin đối chiếu hạng GPLX và quyền điều khiển loại xe đăng ký.</p>
  </fieldset>
  <fieldset>
    <legend data-copy-vi="04 · Giấy tờ xác minh" data-copy-en="04 · Verification documents">04 · Giấy tờ xác minh</legend>
    <p class="sa-note" data-copy-vi="JPG, PNG, WebP hoặc PDF, tối đa 5 MB mỗi file. Tải từng giấy tờ riêng; không dùng cùng một file cho nhiều mục." data-copy-en="JPG, PNG, WebP or PDF, up to 5 MB per file. Upload each document separately; do not reuse one file across categories.">JPG, PNG, WebP hoặc PDF, tối đa 5 MB mỗi file. Tải từng giấy tờ riêng; không dùng cùng một file cho nhiều mục.</p>
    <div class="sa-grid sa-documents">
      <?php foreach ([
        'IDENTITY_FRONT'=>['Định danh · mặt trước / trang thông tin','Identity · front / photo page',false],
        'IDENTITY_BACK'=>['Định danh · mặt sau / trang bổ sung','Identity · back / additional page',false],
        'PORTRAIT'=>['Ảnh chân dung rõ mặt','Clear portrait photo',false],
        'DRIVER_LICENSE'=>['GPLX · mặt trước','Driving licence · front',true],
        'DRIVER_LICENSE_BACK'=>['GPLX · mặt sau','Driving licence · back',true],
        'VEHICLE_REGISTRATION'=>['Giấy đăng ký xe','Vehicle registration certificate',true],
        'VEHICLE_INSURANCE'=>['Bảo hiểm TNDS còn hiệu lực','Valid liability insurance',true],
        'VEHICLE_AUTHORIZATION'=>['Giấy ủy quyền / hợp đồng thuê xe','Vehicle authorisation / rental agreement',false],
      ] as $kind=>[$vi,$en,$motorized]): ?>
      <label class="sa-field sa-document" for="shipper-file-<?= e($kind) ?>"<?= $motorized?' data-sa-motorized':'' ?><?= $kind==='VEHICLE_AUTHORIZATION'?' data-sa-authorization hidden':'' ?>><span data-copy-vi="<?= e($vi) ?> *" data-copy-en="<?= e($en) ?> *"><?= e($vi) ?> *</span><input id="shipper-file-<?= e($kind) ?>" type="file" data-sa-document="<?= e($kind) ?>" accept="<?= $kind==='PORTRAIT'?'image/jpeg,image/png,image/webp':'image/jpeg,image/png,image/webp,application/pdf' ?>"<?= $kind==='VEHICLE_AUTHORIZATION'?' disabled':' required' ?>><small data-sa-upload-status aria-live="polite"></small></label>
      <?php endforeach; ?>
    </div>
  </fieldset>
  <label class="sa-check sa-consent"><input type="checkbox" name="consent" required><span data-copy-vi="Tôi xác nhận thông tin và giấy tờ là thật, còn hiệu lực; tôi có quyền sử dụng phương tiện và đã thông báo cho người liên hệ khẩn cấp. Tôi đồng ý để SOMI lưu và đối chiếu hồ sơ nhằm xét duyệt đăng ký Shipper." data-copy-en="I confirm my details and documents are authentic and valid, I am authorised to use this vehicle and have informed my emergency contact. I agree to SOMI storing and checking this application for Shipper verification.">Tôi xác nhận thông tin và giấy tờ là thật, còn hiệu lực; tôi có quyền sử dụng phương tiện và đã thông báo cho người liên hệ khẩn cấp. Tôi đồng ý để SOMI lưu và đối chiếu hồ sơ nhằm xét duyệt đăng ký Shipper.</span></label>
  <p class="sa-note" data-copy-vi="Giấy tờ được lưu riêng tư, chỉ bạn và Admin có quyền xác minh được truy cập. Hồ sơ chưa được duyệt sẽ chưa thể nhận đơn." data-copy-en="Documents are private and accessible only to you and authorised verification admins. You cannot accept deliveries until approved.">Giấy tờ được lưu riêng tư, chỉ bạn và Admin có quyền xác minh được truy cập. Hồ sơ chưa được duyệt sẽ chưa thể nhận đơn.</p>
  <p class="sa-error" data-switch-error role="alert" tabindex="-1" hidden></p>
  <div class="sa-actions"><span data-sa-progress role="status" aria-live="polite"></span><button class="button button-dark btn btn-dark" type="submit" data-copy-en="Submit application for review" data-copy-vi="Gửi hồ sơ xét duyệt">Gửi hồ sơ xét duyệt</button></div>
</form>
<script defer src="<?= e(assetUrl('public/assets/js/shipper-application.js')) ?>"></script>
