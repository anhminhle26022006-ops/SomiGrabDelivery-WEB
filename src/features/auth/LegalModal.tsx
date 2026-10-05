import { X } from "lucide-react";
import type { ReactNode } from "react";
import { useLanguage } from "../../shared/i18n";

type LegalDocument = "terms" | "privacy";

export function LegalModal({ document, onClose }: { document: LegalDocument; onClose: () => void }) {
  const { lang } = useLanguage();
  const isPrivacy = document === "privacy";
  const title = isPrivacy
    ? lang === "vi" ? "Chính sách bảo mật" : "Privacy Policy"
    : lang === "vi" ? "Điều khoản sử dụng" : "Terms of Service";

  return (
    <div className="fixed inset-0 z-[100] flex items-end justify-center bg-[#14284b]/45 p-0 backdrop-blur-sm sm:items-center sm:p-5" role="dialog" aria-modal="true" aria-label={title}>
      <div className="flex max-h-[92svh] w-full max-w-3xl flex-col overflow-hidden rounded-t-[2rem] bg-white shadow-2xl sm:max-h-[86svh] sm:rounded-[2rem]">
        <header className="flex shrink-0 items-center justify-between border-b border-slate-100 px-5 py-4 sm:px-7">
          <div>
            <p className="text-[9px] font-semibold tracking-[.2em] text-[#14284b]/45">SOMI DELIVERY</p>
            <h2 className="mt-1 text-xl font-semibold tracking-[-.03em] text-[#14284b]">{title}</h2>
          </div>
          <button type="button" onClick={onClose} className="flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 text-[#14284b]" aria-label={lang === "vi" ? "Đóng" : "Close"}>
            <X className="h-5 w-5" />
          </button>
        </header>

        <div className="overflow-y-auto px-5 py-6 text-sm leading-6 text-slate-600 sm:px-7 sm:py-7">
          {isPrivacy ? <PrivacyContent lang={lang} /> : <TermsContent lang={lang} />}
        </div>
      </div>
    </div>
  );
}

function PrivacyContent({ lang }: { lang: "vi" | "en" }) {
  if (lang === "vi") {
    return <div className="space-y-6">
      <p>Somi tôn trọng quyền riêng tư của người dùng. Chính sách này giải thích dữ liệu Somi thu thập, mục đích sử dụng và cách bảo vệ dữ liệu khi bạn sử dụng nền tảng giao hàng.</p>
      <Section title="1. Thông tin Somi thu thập"><p>Somi có thể thu thập họ tên, số điện thoại, email, ngày sinh, thông tin địa chỉ giao nhận, thông tin đơn hàng, thông tin phương tiện của người giao và dữ liệu cần thiết cho thanh toán.</p><p>Đối với xác minh danh tính, Somi có thể xử lý số CCCD và kết quả xác minh từ VNeID/eKYC thông qua nhà cung cấp được kết nối và được phép.</p></Section>
      <Section title="2. Dữ liệu vị trí"><p>Khi bạn cho phép, Somi có thể sử dụng vị trí của người giao để hỗ trợ ghép đơn, theo dõi trạng thái giao hàng và cải thiện độ chính xác của ETA. Somi không yêu cầu quyền vị trí nếu tính năng đó không cần thiết.</p></Section>
      <Section title="3. Mục đích sử dụng"><p>Dữ liệu được sử dụng để tạo và quản lý tài khoản, xác minh danh tính, ghép khách hàng với người giao, xử lý thanh toán, theo dõi đơn hàng, hỗ trợ khách hàng, phát hiện gian lận và bảo vệ an toàn của nền tảng.</p></Section>
      <Section title="4. Chia sẻ dữ liệu"><p>Somi chỉ chia sẻ dữ liệu cần thiết cho việc cung cấp dịch vụ với các bên liên quan như người giao, đơn vị thanh toán, nhà cung cấp xác minh danh tính, dịch vụ email hoặc hạ tầng kỹ thuật. Somi không bán dữ liệu cá nhân của người dùng.</p></Section>
      <Section title="5. Bảo mật và lưu trữ"><p>Somi áp dụng các biện pháp kỹ thuật và tổ chức phù hợp để bảo vệ dữ liệu khỏi truy cập, sử dụng, thay đổi hoặc tiết lộ trái phép. Dữ liệu được lưu giữ trong thời gian cần thiết cho mục đích cung cấp dịch vụ và theo yêu cầu pháp luật.</p></Section>
      <Section title="6. Quyền của người dùng"><p>Bạn có thể yêu cầu xem, cập nhật hoặc xóa dữ liệu cá nhân trong phạm vi pháp luật cho phép. Một số dữ liệu có thể cần được giữ lại để đáp ứng nghĩa vụ pháp lý, xử lý tranh chấp hoặc bảo vệ an toàn hệ thống.</p></Section>
      <Section title="7. Liên hệ"><p>Nếu có câu hỏi về dữ liệu cá nhân, vui lòng liên hệ bộ phận hỗ trợ Somi thông qua kênh hỗ trợ được công bố trên nền tảng.</p></Section>
    </div>;
  }
  return <div className="space-y-6">
    <p>Somi respects your privacy. This policy explains what data Somi collects, why it is used, and how it is protected when you use the delivery platform.</p>
    <Section title="1. Information we collect"><p>Somi may collect your name, phone number, email, date of birth, pickup and drop-off addresses, order information, shipper vehicle information, and data required for payment.</p><p>For identity verification, Somi may process your CCCD/national ID number and verification results returned by VNeID/eKYC through an approved connected provider.</p></Section>
    <Section title="2. Location data"><p>When you grant permission, Somi may use a shipper's location to support matching, delivery tracking, and ETA accuracy. Somi does not request location access when the feature does not require it.</p></Section>
    <Section title="3. How we use data"><p>Data is used to create and manage accounts, verify identity, match customers with shippers, process payments, track orders, provide support, detect fraud, and protect platform safety.</p></Section>
    <Section title="4. Data sharing"><p>Somi shares only the data necessary to provide the service with relevant parties such as shippers, payment providers, identity-verification providers, email services, or technical infrastructure providers. Somi does not sell users' personal data.</p></Section>
    <Section title="5. Security and retention"><p>Somi applies appropriate technical and organizational safeguards against unauthorized access, use, alteration, or disclosure. Data is retained only as necessary for service delivery and legal obligations.</p></Section>
    <Section title="6. Your rights"><p>You may request access to, correction of, or deletion of personal data where permitted by law. Some information may need to be retained for legal obligations, dispute resolution, or platform security.</p></Section>
    <Section title="7. Contact"><p>For privacy questions, contact Somi support through the support channel published on the platform.</p></Section>
  </div>;
}

function TermsContent({ lang }: { lang: "vi" | "en" }) {
  if (lang === "vi") {
    return <div className="space-y-6">
      <p>Bằng việc tạo tài khoản hoặc sử dụng Somi, bạn đồng ý tuân thủ các Điều khoản sử dụng này và sử dụng dịch vụ đúng mục đích, trung thực và an toàn.</p>
      <Section title="1. Tài khoản"><p>Bạn phải cung cấp thông tin chính xác, bảo vệ thông tin đăng nhập và chịu trách nhiệm đối với hoạt động phát sinh từ tài khoản của mình.</p></Section>
      <Section title="2. Dịch vụ giao hàng"><p>Somi kết nối khách hàng với người giao hàng bán thời gian. Người giao nhận đơn sau khi xem thông tin đơn và chủ động chấp nhận; việc ghép đơn không đảm bảo mọi yêu cầu đều có người giao.</p></Section>
      <Section title="3. Đơn hàng và thanh toán"><p>Khách hàng chịu trách nhiệm về thông tin nhận và giao hàng, nội dung gói hàng và các khoản phí hiển thị trước khi xác nhận. Các khoản hoàn trả, phụ phí hoặc điều chỉnh hợp lệ có thể phát sinh theo tình trạng thực tế của đơn.</p></Section>
      <Section title="4. Người giao hàng"><p>Người giao phải cung cấp thông tin xác minh cần thiết, tuân thủ quy tắc an toàn, chỉ nhận đơn phù hợp và cập nhật trạng thái giao hàng trung thực.</p></Section>
      <Section title="5. Hủy, giao thất bại và tranh chấp"><p>Đơn có thể bị hủy hoặc chuyển sang quy trình hoàn trả khi không thể giao, người nhận không có mặt, địa chỉ không hợp lệ hoặc có sự cố. Khi phát sinh tranh chấp, Somi có thể yêu cầu bằng chứng và xem xét lịch sử đơn hàng.</p></Section>
      <Section title="6. Hành vi bị cấm"><p>Không được sử dụng Somi cho hoạt động bất hợp pháp, gian lận, giả mạo danh tính, can thiệp hệ thống, gây nguy hiểm cho người khác hoặc gửi các hàng hóa bị cấm.</p></Section>
      <Section title="7. Tài khoản bị hạn chế"><p>Somi có thể tạm hạn chế hoặc khóa tài khoản khi phát hiện vi phạm, rủi ro gian lận, thông tin xác minh không hợp lệ hoặc hành vi gây ảnh hưởng đến an toàn nền tảng.</p></Section>
      <Section title="8. Thay đổi điều khoản"><p>Somi có thể cập nhật Điều khoản khi dịch vụ thay đổi. Phiên bản mới sẽ được công bố trên nền tảng trước hoặc khi có hiệu lực theo quy định áp dụng.</p></Section>
    </div>;
  }
  return <div className="space-y-6">
    <p>By creating an account or using Somi, you agree to these Terms of Service and to use the service lawfully, honestly, and safely.</p>
    <Section title="1. Accounts"><p>You must provide accurate information, protect your credentials, and remain responsible for activity performed through your account.</p></Section>
    <Section title="2. Delivery service"><p>Somi connects customers with part-time shippers. A shipper reviews an offer and actively accepts it; matching does not guarantee that every request will find a shipper.</p></Section>
    <Section title="3. Orders and payments"><p>Customers are responsible for pickup and drop-off information, package contents, and fees shown before confirmation. Valid return fees, surcharges, or adjustments may apply based on the actual delivery situation.</p></Section>
    <Section title="4. Shippers"><p>Shippers must provide required verification information, follow safety rules, accept only suitable orders, and report delivery status accurately.</p></Section>
    <Section title="5. Cancellation, failed delivery and disputes"><p>An order may be cancelled or moved to a return process when delivery is not possible, the recipient is unavailable, the address is invalid, or another delivery issue occurs. Somi may request evidence and review order history when handling disputes.</p></Section>
    <Section title="6. Prohibited conduct"><p>You may not use Somi for unlawful activity, fraud, identity impersonation, system abuse, conduct that endangers others, or prohibited goods.</p></Section>
    <Section title="7. Account restrictions"><p>Somi may restrict or suspend an account when it detects violations, fraud risk, invalid verification information, or conduct that threatens platform safety.</p></Section>
    <Section title="8. Changes to these terms"><p>Somi may update these Terms as the service changes. The updated version will be published on the platform before or when it becomes effective as required by applicable rules.</p></Section>
  </div>;
}

function Section({ title, children }: { title: string; children: ReactNode }) {
  return <section><h3 className="text-sm font-semibold text-[#14284b]">{title}</h3><div className="mt-2 space-y-2">{children}</div></section>;
}
