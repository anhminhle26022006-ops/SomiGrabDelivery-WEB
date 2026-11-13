"use strict";
// Thanh đầu trang của Shipper: hiển thị tên thật và chữ viết tắt của người đang đăng nhập
// thay cho tên mẫu "Nguyễn Văn A" và chữ "NA" viết cứng trong giao diện.
(() => {
  const api = window.SomiShipper;
  if (!api) return;

  // "Nguyễn Đinh Thảo Nhi" thành "NN": chữ đầu của họ và chữ đầu của tên.
  function initials(fullName) {
    const words = String(fullName || "").trim().split(/\s+/).filter(Boolean);
    if (!words.length) return "?";
    const first = words[0][0];
    const last = words.length > 1 ? words[words.length - 1][0] : "";
    return (first + last).toLocaleUpperCase("vi-VN");
  }

  function paint(fullName) {
    const name = document.querySelector("#profileDropdown .drophead strong");
    if (name) name.textContent = fullName;
    for (const id of ["avatar", "accountAvatar"]) {
      const avatar = document.getElementById(id);
      // Nếu người dùng vừa chọn ảnh đại diện thì giữ ảnh, không ghi đè bằng chữ.
      if (avatar && !avatar.querySelector("img")) {
        const label = document.createElement("span");
        label.textContent = initials(fullName);
        avatar.replaceChildren(label);
      }
    }
  }

  api.profile().then((data) => paint(data.profile.full_name)).catch(() => {
    // Không tải được hồ sơ thì bỏ tên mẫu đi, tránh hiển thị tên của người khác.
    paint("");
  });
})();
