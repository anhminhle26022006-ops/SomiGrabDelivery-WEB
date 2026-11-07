<?php $person1Admin = $page === 'admin'; ?>
<link rel="stylesheet" href="<?= e(somi_asset('public/assets/css/person1.css')) ?>">
<section class="panel person1-workspace" data-person1-workspace data-role="<?= $person1Admin ? 'admin' : 'customer' ?>">
  <h1><?= $person1Admin ? 'Quản trị dữ liệu' : 'Dữ liệu của tôi' ?></h1>
  <p>Dữ liệu được tải từ hệ thống. Các thao tác tạo đơn và báo giá đang chờ chính sách giá được phê duyệt.</p>
  <p role="status" aria-live="polite" data-person1-status></p>
  <div class="person1-tabs" data-person1-tabs>
    <?php foreach (($person1Admin ? ['accounts'=>'Tài khoản','orders'=>'Đơn hàng','categories'=>'Loại hàng','pricing'=>'Cấu hình giá nháp','nationality'=>'Yêu cầu quốc tịch'] : ['profile'=>'Hồ sơ','addresses'=>'Sổ địa chỉ','orders'=>'Đơn hàng','nationality'=>'Yêu cầu quốc tịch']) as $key=>$label): ?>
      <button class="button button-light" type="button" data-person1-tab="<?= $key ?>"><?= $label ?></button>
    <?php endforeach; ?>
  </div>
  <div data-person1-content></div>
</section>
<script defer src="<?= e(somi_asset('public/assets/js/person1.js')) ?>"></script>
