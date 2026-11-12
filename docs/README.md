# Tài liệu SOMI

> Update 06/11/2026: Person 1 payment/email/Admin APIs and live Customer flow are implemented. SePay is selected; USD checkout is disabled. Current contracts, setup, tests and live-provider limitations: [PERSON1-API.md](PERSON1-API.md).


Cập nhật 06/11/2026. Đọc tài liệu hiện hành trước các báo cáo lịch sử.

## Hiện hành

Ưu tiên tích hợp thanh toán: [Hướng dẫn SePay sandbox](SEPAY-SETUP.md) — lấy merchant credentials, đặt biến môi trường, IPN và kiểm tra kết nối.

| Tài liệu | Nội dung |
|---|---|
| [BACKEND-SCOPE.md](BACKEND-SCOPE.md) | Scope điều chỉnh, checklist ba người, bàn giao và điểm cần chốt |
| [TEAM-OWNERSHIP.md](../TEAM-OWNERSHIP.md) | Ownership theo nghiệp vụ, file bàn giao và review |
| [DELIVERY-API.md](DELIVERY-API.md) | API hiện có, giá/trạng thái/event và phần chưa triển khai |
| [DISTANCE-WEIGHT-PRICING-PROPOSAL.md](DISTANCE-WEIGHT-PRICING-PROPOSAL.md) | Giá hiện hành đã duyệt: TP.HCM, dưới 40 km, tối đa 30 kg |
| [runtime.env.example](runtime.env.example) | Biến môi trường, không có key thật; PHP không tự nạp file này |
| [Customer integration](../app/Views/customer/CUSTOMER.md) | Nối UI Customer với API theo scope mới |

## Kiểm chứng và lịch sử

- [CUSTOMER-API-VALIDATION.md](CUSTOMER-API-VALIDATION.md): kiểm thử đợt triển khai API/pricing, không chứng minh các module mới trong scope đã xong.
- [PROGRESS-2026-11-05.md](PROGRESS-2026-11-05.md): snapshot trước backend đơn hàng và trước scope điều chỉnh, không dùng giao việc hiện tại.
- [PRICING-PROPOSAL.md](PRICING-PROPOSAL.md): bảng giá band cũ, đã thay bằng rule distance/weight.
- [FULL-PROJECT-AUDIT.md](FULL-PROJECT-AUDIT.md), [RE-AUDIT-LOGIN-UI.md](RE-AUDIT-LOGIN-UI.md), [ORIGIN-PROXY-FIX.md](ORIGIN-PROXY-FIX.md): kết quả tại ngày ghi trong báo cáo, không viết lại kiểm chứng quá khứ.
- [CUSTOMER-DEMO-LEGACY.md](archive/CUSTOMER-DEMO-LEGACY.md): kịch bản/endpoint đề xuất của UI demo cũ, không phải contract backend hay chính sách tiền hiện hành.
