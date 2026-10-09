import { Bell, ChevronRight, CircleUserRound, Languages, LifeBuoy, Truck, UserRoundX } from "lucide-react";
import type { Language } from "../../../shared/i18n";
interface Props { lang: Language; onRegistration: () => void; onExit: () => void; onSupport: () => void; onCancelAccount: () => void; }
export function Account({ lang, onRegistration, onExit, onSupport, onCancelAccount }: Props) {
  const vi = lang === "vi";
  const items = [
    { icon: CircleUserRound, label: vi ? "Thông tin cá nhân" : "Personal information", action: undefined },
    { icon: Truck, label: vi ? "Thông tin phương tiện" : "Vehicle information", action: undefined },
    { icon: Bell, label: vi ? "Thông báo" : "Notifications", action: undefined },
    { icon: Languages, label: vi ? "Ngôn ngữ" : "Language", action: undefined },
    { icon: LifeBuoy, label: vi ? "Trung tâm hỗ trợ" : "Help Center", action: onSupport },
    { icon: UserRoundX, label: vi ? "Hủy tài khoản" : "Delete account", action: onCancelAccount },
  ];
  return <section className="mx-auto w-full max-w-4xl px-4 py-6 sm:px-5 sm:py-8 md:px-8 md:py-12"><div className="mb-7"><h1 className="text-2xl font-semibold tracking-[-0.04em] text-[#14284b] sm:text-3xl">{vi ? "Tài khoản" : "Account"}</h1></div><div className="rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm sm:p-7"><div className="flex flex-col gap-4 sm:flex-row sm:items-center"><div className="flex h-16 w-16 items-center justify-center rounded-full bg-[#14284b]/10"><CircleUserRound className="h-8 w-8 text-[#14284b]"/></div><div><div className="flex flex-wrap items-center gap-2"><h2 className="text-lg font-semibold text-[#14284b]">Nguyễn Minh</h2><span className="rounded-full bg-emerald-50 px-2.5 py-1 text-[11px] font-semibold text-emerald-700">{vi ? "Đã xác minh" : "Verified"}</span></div><p className="mt-1 text-sm text-slate-500">090 123 4567</p></div></div><div className="mt-7 divide-y divide-slate-100 rounded-2xl border border-slate-100">{items.map(({ icon: Icon, label, action }, i) => <button key={label} onClick={action ?? (i === 1 ? onRegistration : undefined)} className="flex w-full items-center gap-3 p-4 text-left transition hover:bg-slate-50"><span className="flex h-9 w-9 items-center justify-center rounded-xl bg-slate-50 text-[#14284b]"><Icon className="h-4 w-4"/></span><span className={`flex-1 text-sm font-semibold ${i === 5 ? "text-red-600" : "text-[#14284b]"}`}>{label}</span><ChevronRight className="h-4 w-4 text-slate-400"/></button>)}</div><button onClick={onExit} className="mt-5 w-full rounded-full bg-[#14284b] px-4 py-3 text-sm font-semibold text-white hover:bg-[#1d3968]">{vi ? "Đăng xuất" : "Sign out"}</button></div></section>;
}
