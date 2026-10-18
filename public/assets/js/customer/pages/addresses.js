/* Customer: Saved addresses */
(() => {
  "use strict";
  const S = window.SomiStore;
  const U = window.SomiUI;
  const { t } = window.SomiI18n;
  const { $, $$, h, icon } = U;
  // Saved in Vietnamese and translated when shown; a custom label stays as typed.
  const LABELS = ["Nhà riêng", "Văn phòng", "Nhà người thân", "Khác"];

  function card(a) {
    const used = S.addressUsage(a.id);
    return `<article class="card card-body c-address">
      <div class="c-address-top"><strong>${h(t(a.label))}</strong>${a.isDefault ? `<span class="badge rounded-pill c-badge c-tone-blue">${t("Mặc định")}</span>` : ""}</div>
      <p>${h(a.detail)}, ${h(a.area)}</p>
      <small>${h(a.receiverName)} · ${h(U.prettyPhone(a.receiverPhone))}</small>
      ${used ? `<small>${t`Đã dùng trong ${used} đơn hàng`}</small>` : ""}
      <div class="c-address-actions">
        <button type="button" class="btn btn-outline-dark btn-sm" data-edit="${a.id}">${icon("edit", 15)} ${t("Sửa")}</button>
        ${a.isDefault ? "" : `<button type="button" class="btn btn-outline-dark btn-sm" data-default="${a.id}">${t("Đặt mặc định")}</button>`}
        <button type="button" class="btn btn-outline-dark btn-sm" data-delete="${a.id}" ${used ? `aria-disabled="true" title="${t("Địa chỉ đã dùng trong đơn hàng nên được giữ lại để bảo toàn lịch sử")}"` : ""}>${icon("trash", 15)} ${t("Xóa")}</button>
      </div>
    </article>`;
  }

  function render(el) {
    const list = S.listAddresses();
    el.innerHTML = list.length ? `<div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-3">${list.map((a) => `<div class="col">${card(a)}</div>`).join("")}</div>`
      : `<section class="card card-body">${U.empty({ title: t("Chưa có địa chỉ nào"), text: t("Lưu nhà riêng hoặc văn phòng để tạo đơn nhanh hơn."), action: `<button type="button" class="btn btn-dark" data-add-empty>${t("Thêm địa chỉ")}</button>` })}</section>`;
    $$("[data-edit]", el).forEach((b) => b.addEventListener("click", () => edit(list.find((a) => a.id === b.dataset.edit) || null)));
    $$("[data-default]", el).forEach((b) => b.addEventListener("click", () => { S.setDefaultAddress(b.dataset.default || ""); U.toast(t("Đã đổi địa chỉ mặc định.")); }));
    $$("[data-delete]", el).forEach((b) => b.addEventListener("click", async () => {
      if (b.getAttribute("aria-disabled") === "true") { U.toast(b.title); return; }
      const a = list.find((x) => x.id === b.dataset.delete);
      if (!a) return;
      if (await U.confirmBox({ title: t`Xóa "${t(a.label)}"?`, text: t`${a.detail}, ${a.area} sẽ bị xóa khỏi sổ địa chỉ.`, confirmLabel: t("Xóa địa chỉ"), danger: true })) {
        try { S.deleteAddress(a.id); U.toast(t("Đã xóa địa chỉ.")); } catch (err) { U.toast(U.errorMessage(err), "danger"); }
      }
    }));
    const addEmpty = $("[data-add-empty]", el);
    if (addEmpty) addEmpty.addEventListener("click", () => edit(null));
  }

  function edit(address) {
    const a = address || { label: "Nhà riêng", receiverName: S.getAccount().fullName, receiverPhone: S.getAccount().phone, detail: "", area: "", isDefault: S.listAddresses().length === 0 };
    U.modal({
      title: address ? t("Sửa địa chỉ") : t("Thêm địa chỉ"),
      size: "c-modal-wide",
      body: `<form class="row g-3" data-address-form novalidate>
        <div class="c-field col-md-6"><label class="form-label" for="ad-label">${t("Tên gợi nhớ")}</label><select class="form-select" id="ad-label">${LABELS.map((l) => `<option value="${h(l)}" ${l === a.label ? "selected" : ""}>${h(t(l))}</option>`).join("")}${LABELS.includes(a.label) ? "" : `<option selected>${h(a.label)}</option>`}</select></div>
        <div class="c-field col-md-6"><label class="form-label" for="ad-area">${t("Khu vực")}</label><select class="form-select" id="ad-area"><option value="">${t("Chọn khu vực")}</option>${S.AREAS.map((x) => `<option ${x.name === a.area ? "selected" : ""}>${h(x.name)}</option>`).join("")}</select></div>
        <div class="c-field col-12"><label class="form-label" for="ad-detail">${t("Số nhà, tên đường, phường")}</label><input class="form-control" id="ad-detail" value="${h(a.detail)}" placeholder="${t("45 Phan Đăng Lưu, Phường 3")}"></div>
        <div class="c-field col-md-6"><label class="form-label" for="ad-name">${t("Tên người liên hệ")}</label><input class="form-control" id="ad-name" value="${h(a.receiverName)}"></div>
        <div class="c-field col-md-6"><label class="form-label" for="ad-phone">${t("Số điện thoại")}</label><input class="form-control" id="ad-phone" type="tel" inputmode="numeric" value="${h(a.receiverPhone)}"></div>
        <label class="form-check c-check col-12"><input class="form-check-input" type="checkbox" id="ad-default" ${a.isDefault ? "checked" : ""} ${address && address.isDefault ? "disabled" : ""}> <span class="form-check-label">${t("Dùng làm điểm lấy hàng mặc định")}</span></label>
      </form>`,
      actions: [
        { label: t("Hủy"), variant: "btn-outline-dark" },
        {
          label: t("Lưu địa chỉ"),
          onClick: ({ close, el }) => {
            const f = (id) => $(`#${id}`, el);
            const ok = [
              U.setError(f("ad-area"), f("ad-area").value ? "" : t("Chọn khu vực.")),
              U.setError(f("ad-detail"), f("ad-detail").value.trim().length >= 5 ? "" : t("Nhập số nhà, tên đường và phường.")),
              U.setError(f("ad-name"), f("ad-name").value.trim().length >= 2 ? "" : t("Nhập tên người liên hệ.")),
              U.setError(f("ad-phone"), U.phoneOk(f("ad-phone").value) ? "" : t("Số điện thoại gồm 10 số, bắt đầu bằng 03, 05, 07, 08 hoặc 09."))
            ].every(Boolean);
            if (!ok) return;
            S.saveAddress({ id: address?.id, label: f("ad-label").value, area: f("ad-area").value, detail: f("ad-detail").value, receiverName: f("ad-name").value, receiverPhone: f("ad-phone").value, isDefault: $("#ad-default", el).checked });
            close();
            U.toast(address ? t("Đã cập nhật địa chỉ.") : t("Đã thêm địa chỉ."));
          }
        }
      ],
      onOpen: ({ el }) => $$("input, select", el).forEach((i) => i.addEventListener("input", () => U.setError(i, "")))
    });
  }

  document.addEventListener("DOMContentLoaded", () => {
    $("[data-add]").addEventListener("click", () => edit(null));
    U.load($("[data-addresses]"), render, { lines: 3 });
  });
  document.addEventListener("somi:change", () => render($("[data-addresses]")));
})();
