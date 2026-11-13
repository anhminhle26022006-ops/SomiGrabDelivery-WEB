<?php // Customer: saved address book ?>
<section class="c-addressbook">
<a class="c-back" href="?page=customer&amp;view=account"><svg class="c-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m15 5-7 7 7 7"/></svg>Tài khoản</a>
<header class="c-head c-address-heading">
 <div><span class="c-eyebrow">ĐIỂM ĐẾN QUEN THUỘC</span><h1>Sổ địa chỉ</h1><p>Lưu một lần, gửi hàng nhanh hơn mỗi ngày.</p></div>
 <button type="button" class="btn btn-dark c-add-address" data-add><span aria-hidden="true">＋</span> Thêm địa chỉ</button>
</header>
<div class="c-address-layout">
 <div><div class="c-address-list-heading"><h2>Địa chỉ của bạn</h2><span data-address-total></span></div><div data-addresses aria-live="polite"></div></div>
 <aside class="c-address-help"><span class="c-address-help-icon" aria-hidden="true">↗</span><h2>Sẵn sàng cho lần gửi tiếp theo</h2><p>Địa chỉ mặc định được điền sẵn làm điểm lấy hàng khi bạn tạo đơn.</p><div class="c-address-help-tip"><span aria-hidden="true">✓</span><div><strong>Dễ tìm, dễ giao</strong><p>Thêm số nhà, tên đường và hướng dẫn đến điểm lấy hàng.</p></div></div><a href="?page=customer&amp;view=create" class="c-link">Tạo đơn gửi hàng <span aria-hidden="true">→</span></a></aside>
</div>
</section>
