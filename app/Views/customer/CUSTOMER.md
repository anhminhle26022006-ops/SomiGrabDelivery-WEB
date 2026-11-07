# Customer module — tài liệu hiện hành

> Update 06/11/2026: Person 1 payment/email/Admin APIs and live Customer flow are implemented. SePay is selected; USD checkout is disabled. Current contracts, setup, tests and live-provider limitations: [PERSON1-API.md](../../../docs/PERSON1-API.md).


Cập nhật 06/11/2026 sau điều chỉnh scope.

- [Kế hoạch nối UI Customer và endpoint thực tế](../../../docs/CUSTOMER-INTEGRATION.md).
- [Scope/checklist ba người](../../../docs/BACKEND-SCOPE.md).
- [API contract](../../../docs/DELIVERY-API.md).
- [File ownership](../../../TEAM-OWNERSHIP.md).

Đơn hàng/giá/payment/refund/email thuộc Người 1; matching/giao nhận/GPS thuộc Người 2; địa chỉ/Google/chat/ticket/in-app thuộc Người 3. Luồng chính dùng server.js/API; các view legacy ngoài scope Người 1 vẫn có demo store và không đại diện giao dịch thật.

[Bản mô tả và 17 kịch bản demo cũ](../../../docs/archive/CUSTOMER-DEMO-LEGACY.md) được lưu lịch sử, không dùng cho giá, trạng thái hoặc endpoint hiện hành.
