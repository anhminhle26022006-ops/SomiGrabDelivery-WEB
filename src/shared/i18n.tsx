import { createContext, useContext, useEffect, useMemo, useState, type ReactNode } from "react";

export type Language = "vi" | "en";

const translations = {
  en: {
    nav: { language: "Language", home: "Home", how: "How It Works", customers: "For Customers", shippers: "For Shippers", pricing: "Pricing", signIn: "Sign in", signOut: "Sign out", send: "Send a Package", become: "Become a Shipper" },
    hero: {
      badge: "Flexible Delivery Network",
      title1: "Deliver More.", title2: "Earn on Your Time.",
      description: "Somi connects customers with trusted part-time shippers, making every delivery simple, flexible, and transparent.",
      active: "ACTIVE SHIPPERS", network: "Delivery Network", explore: "Explore how Somi works",
    },
    pages: {
      how: { label: "HOW SOMI WORKS", title: "Delivery, made clear at every step.", desc: "One simple flow from pickup request to proof of delivery.", cta: "Ready to move something?", cards: [
        ["Create your delivery", "Tell us where, when, and what you need delivered."],
        ["Choose and pay", "See your fee upfront and select your preferred payment method."],
        ["Track with confidence", "Get a verified shipper and follow each delivery milestone."],
      ]},
      customers: { label: "FOR CUSTOMERS", title: "Every delivery under control.", desc: "From urgent documents to thoughtful packages, Somi keeps you informed.", cta: "Ready to move something?", cards: [
        ["Simple booking", "Create a delivery in a few focused steps."],
        ["Clear pricing", "No surprises. Review every fee before you confirm."],
        ["Human support", "Report an issue and follow its resolution."],
      ]},
      shippers: { label: "FOR SHIPPERS", title: "Your time. Your routes. Your earnings.", desc: "Go online when it suits you and accept only the offers that work.", cta: "Ready to earn on your time?", cards: [
        ["Choose your work", "Review distance, package, and earnings before accepting."],
        ["Earn transparently", "See daily and weekly earnings at a glance."],
        ["Grow your rating", "Deliver with care and build customer trust."],
      ]},
      pricing: { label: "SIMPLE PRICING", title: "Know the cost before you send.", desc: "Fees are based on route distance, package details, and timing.", cta: "Ready to move something?", cards: [
        ["Base delivery", "30,000₫ includes the first 3 km."],
        ["Distance", "5,000₫ for each additional kilometer."],
        ["Returns", "Return fees are shown before confirmation."],
      ]},
    },
auth: {
      welcome: "Welcome back", welcomeTitle: "Welcome to Somi.", title: "Delivery that fits your day.", desc: "Sign in to send a package or manage your delivery work with Somi.", simple: "Simple. Flexible. Transparent.",
      signIn: "Sign in", create: "Create account", start: "Create your Somi account.", choose: "Choose your experience, verify your phone, then complete your profile.", customer: "Customer", shipper: "Shipper",
      phoneOrEmail: "Phone number or email", placeholderPhoneOrEmail: "+84 90 123 4567 or you@example.com", forgotPassword: "Forgot password?",
      phone: "Phone number", placeholderPhone: "+84 90 123 4567", phoneSecurity: "We use your phone number to secure your account. A verification code will be sent by SMS.",
      verifyPhone: "Verify your phone", phoneStep: "Start with your phone number. We’ll send a one-time verification code before you enter your profile details.",
      otp: "Verification code", otpStep: "Enter the 6-digit code sent to your phone. In the connected backend, this step will be handled by SMS OTP.", otpHint: "Didn't receive the code?", resendOtp: "Resend code", changePhone: "Change number", otpInvalid: "Enter the 6-digit verification code.", phoneInvalid: "Enter a valid phone number.", phoneVerified: "Phone number verified",
      completeProfile: "Complete your profile", profileStep: "Add your contact and security details to finish the account setup.", identityTitle: "Verify your identity", identityStep: "Your phone is verified. Now confirm your legal identity with your 12-digit CCCD and VNeID/eKYC verification.", progressLabel: "Sign-up progress",
      fullName: "Full name", nationalId: "CCCD / National ID number", placeholderNationalId: "12-digit CCCD number", email: "Email", password: "Password", confirmPassword: "Confirm password", dateOfBirth: "Date of birth", vehicleType: "Vehicle type", vehicleNumber: "Vehicle number", motorbike: "Motorbike", car: "Car",
      placeholderName: "Your full name", placeholderEmail: "you@example.com", placeholderVehicle: "59A1-123.45", passwordHint: "Use at least 8 characters with a mix of letters and numbers.",
      vneidTitle: "VNeID identity verification", vneidDesc: "Use VNeID or a connected eKYC provider to confirm that the account belongs to a real person.", verifyVneid: "Start verification", vneidNotStarted: "Verification not started", vneidPending: "Verification requested", vneidProviderNote: "Production note: this button must connect to an approved VNeID/eKYC provider. Somi should only activate the account after a verified callback from the backend.", identityReady: "Identity verification submitted", verifyIdentity: "Verify identity & continue", submitCreate: "Create account", sendOtp: "Send verification code", verifyAndContinue: "Verify and continue", noAccount: "Don't have an account?", hasAccount: "Already have an account?", back: "Back",
      termsIntro: "I agree to Somi's", termsLink: "Terms of Service", and: "and", privacyLink: "Privacy Policy", terms: "I agree to Somi's Terms of Service and Privacy Policy.", secureNote: "Your information is protected and used only for account setup.", passwordMismatch: "Passwords do not match.", passwordTooShort: "Password must be at least 8 characters.", termsRequired: "Please accept the terms to create your account.", nationalIdInvalid: "Enter a valid 12-digit CCCD number.", vneidRequired: "Complete VNeID/eKYC verification before continuing."
    }
  },
  vi: {
    nav: { language: "Ngôn ngữ", home: "Trang chủ", how: "Cách hoạt động", customers: "Dành cho khách hàng", shippers: "Dành cho người giao", pricing: "Bảng giá", signIn: "Đăng nhập", signOut: "Đăng xuất", send: "Gửi hàng", become: "Trở thành người giao hàng" },
    hero: {
      badge: "Mạng lưới giao hàng linh hoạt",
      title1: "Giao hàng dễ dàng.", title2: "Kiếm thêm theo cách của bạn.",
      description: "Somi kết nối khách hàng với những người giao hàng bán thời gian đáng tin cậy, giúp mọi chuyến giao đơn giản, linh hoạt và minh bạch.",
      active: "NGƯỜI GIAO ĐANG HOẠT ĐỘNG", network: "Mạng lưới giao hàng", explore: "Khám phá cách Somi hoạt động",
    },
    pages: {
      how: { label: "CÁCH SOMI HOẠT ĐỘNG", title: "Giao hàng rõ ràng ở mọi bước.", desc: "Một quy trình đơn giản từ lúc tạo yêu cầu đến khi hoàn tất giao hàng.", cta: "Bạn sẵn sàng gửi hàng?", cards: [
        ["Tạo đơn giao hàng", "Cho chúng tôi biết nơi nhận, thời gian và món hàng cần giao."],
        ["Chọn và thanh toán", "Xem trước chi phí và chọn phương thức thanh toán phù hợp."],
        ["Theo dõi an tâm", "Nhận người giao đã xác minh và theo dõi từng mốc giao hàng."],
      ]},
      customers: { label: "DÀNH CHO KHÁCH HÀNG", title: "Mọi chuyến giao đều trong tầm kiểm soát.", desc: "Từ tài liệu cần giao gấp đến những gói hàng quan trọng, Somi luôn cập nhật cho bạn.", cta: "Bạn sẵn sàng gửi hàng?", cards: [
        ["Đặt đơn đơn giản", "Tạo yêu cầu giao hàng chỉ với vài bước rõ ràng."],
        ["Chi phí minh bạch", "Không phát sinh bất ngờ. Xem mọi khoản phí trước khi xác nhận."],
        ["Hỗ trợ trực tiếp", "Báo cáo vấn đề và theo dõi quá trình xử lý."],
      ]},
      shippers: { label: "DÀNH CHO NGƯỜI GIAO", title: "Thời gian của bạn. Tuyến đường của bạn. Thu nhập của bạn.", desc: "Online khi phù hợp và chỉ nhận những đơn giao phù hợp với bạn.", cta: "Sẵn sàng kiếm thêm theo thời gian của bạn?", cards: [
        ["Chọn công việc", "Xem khoảng cách, gói hàng và thu nhập trước khi nhận đơn."],
        ["Thu nhập minh bạch", "Theo dõi thu nhập theo ngày và theo tuần dễ dàng."],
        ["Nâng cao đánh giá", "Giao hàng tận tâm và xây dựng niềm tin với khách hàng."],
      ]},
      pricing: { label: "BẢNG GIÁ ĐƠN GIẢN", title: "Biết trước chi phí trước khi gửi.", desc: "Chi phí dựa trên khoảng cách, thông tin gói hàng và thời gian giao.", cta: "Bạn sẵn sàng gửi hàng?", cards: [
        ["Phí giao cơ bản", "30.000₫ bao gồm 3 km đầu tiên."],
        ["Khoảng cách", "5.000₫ cho mỗi km phát sinh."],
        ["Phí hoàn trả", "Phí hoàn trả luôn được hiển thị trước khi xác nhận."],
      ]},
    },
auth: {
      welcome: "Chào mừng trở lại", welcomeTitle: "Chào mừng đến với Somi.", title: "Giao hàng phù hợp với ngày của bạn.", desc: "Đăng nhập để gửi hàng hoặc quản lý công việc giao hàng cùng Somi.", simple: "Đơn giản. Linh hoạt. Minh bạch.",
      signIn: "Đăng nhập", create: "Tạo tài khoản", start: "Tạo tài khoản Somi.", choose: "Chọn vai trò, xác thực số điện thoại rồi hoàn thiện hồ sơ của bạn.", customer: "Khách hàng", shipper: "Người giao",
      phoneOrEmail: "Số điện thoại hoặc email", placeholderPhoneOrEmail: "090 123 4567 hoặc ban@example.com", forgotPassword: "Quên mật khẩu?",
      phone: "Số điện thoại", placeholderPhone: "090 123 4567", phoneSecurity: "Số điện thoại được dùng để bảo vệ tài khoản. Mã xác thực sẽ được gửi qua SMS.",
      verifyPhone: "Xác thực số điện thoại", phoneStep: "Bắt đầu bằng số điện thoại. Somi sẽ gửi mã xác thực một lần trước khi bạn nhập thông tin hồ sơ.",
      otp: "Mã xác thực", otpStep: "Nhập mã 6 số được gửi đến điện thoại. Khi nối backend, bước này sẽ được xử lý bằng OTP SMS.", otpHint: "Chưa nhận được mã?", resendOtp: "Gửi lại mã", changePhone: "Đổi số", otpInvalid: "Vui lòng nhập đúng mã xác thực 6 số.", phoneInvalid: "Vui lòng nhập số điện thoại hợp lệ.", phoneVerified: "Số điện thoại đã được xác thực",
      completeProfile: "Hoàn thiện hồ sơ", profileStep: "Bổ sung thông tin liên hệ và bảo mật để hoàn tất tài khoản.", identityTitle: "Xác thực danh tính", identityStep: "Số điện thoại đã xác thực. Tiếp theo, xác nhận danh tính bằng CCCD 12 số và xác thực VNeID/eKYC.", progressLabel: "Tiến trình đăng ký",
      fullName: "Họ và tên", nationalId: "Số CCCD", placeholderNationalId: "Nhập 12 số CCCD", email: "Email", password: "Mật khẩu", confirmPassword: "Xác nhận mật khẩu", dateOfBirth: "Ngày sinh", vehicleType: "Loại phương tiện", vehicleNumber: "Biển số xe", motorbike: "Xe máy", car: "Ô tô",
      placeholderName: "Họ và tên của bạn", placeholderEmail: "ban@example.com", placeholderVehicle: "59A1-123.45", passwordHint: "Dùng ít nhất 8 ký tự, kết hợp chữ và số.",
      vneidTitle: "Xác thực danh tính qua VNeID", vneidDesc: "Sử dụng VNeID hoặc nhà cung cấp eKYC được kết nối để xác nhận tài khoản thuộc về người thật.", verifyVneid: "Bắt đầu xác thực", vneidNotStarted: "Chưa bắt đầu xác thực", vneidPending: "Đã gửi yêu cầu xác thực", vneidProviderNote: "Khi triển khai thật: nút này phải kết nối với nhà cung cấp VNeID/eKYC được phê duyệt. Somi chỉ kích hoạt tài khoản sau khi backend nhận callback xác thực thành công.", identityReady: "Đã gửi xác thực danh tính", verifyIdentity: "Xác thực danh tính & tiếp tục", submitCreate: "Tạo tài khoản", sendOtp: "Gửi mã xác thực", verifyAndContinue: "Xác thực và tiếp tục", noAccount: "Chưa có tài khoản?", hasAccount: "Đã có tài khoản?", back: "Quay lại",
      termsIntro: "Tôi đồng ý với", termsLink: "Điều khoản sử dụng", and: "và", privacyLink: "Chính sách bảo mật", terms: "Tôi đồng ý với Điều khoản sử dụng và Chính sách bảo mật của Somi.", secureNote: "Thông tin của bạn được bảo vệ và chỉ dùng cho việc thiết lập tài khoản.", passwordMismatch: "Mật khẩu xác nhận không khớp.", passwordTooShort: "Mật khẩu phải có ít nhất 8 ký tự.", termsRequired: "Vui lòng đồng ý với điều khoản để tạo tài khoản.", nationalIdInvalid: "Vui lòng nhập đúng 12 số CCCD.", vneidRequired: "Vui lòng hoàn tất xác thực VNeID/eKYC trước khi tiếp tục."
    }
  }
};

type Dictionary = typeof translations.en;
const LanguageContext = createContext<{ lang: Language; setLang: (lang: Language) => void; t: Dictionary } | null>(null);

export function LanguageProvider({ children }: { children: ReactNode }) {
  const [lang, setLangState] = useState<Language>(() => (localStorage.getItem("somi-language") as Language) || "en");
  const setLang = (next: Language) => { setLangState(next); localStorage.setItem("somi-language", next); };
  useEffect(() => { document.documentElement.lang = lang; }, [lang]);
  const value = useMemo(() => ({ lang, setLang, t: translations[lang] }), [lang]);
  return <LanguageContext.Provider value={value}>{children}</LanguageContext.Provider>;
}

export function useLanguage() {
  const value = useContext(LanguageContext);
  if (!value) throw new Error("useLanguage must be used inside LanguageProvider");
  return value;
}
