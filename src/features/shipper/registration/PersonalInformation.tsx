import { CalendarDays, Mail, MapPin, Phone, UserRound } from "lucide-react";
import type { ReactNode } from "react";
import { useShipperCopy } from "../i18n";
import type { Language } from "../../../shared/i18n";

interface Props { lang: Language; onNext: () => void; onBack?: () => void; }

export function PersonalInformation({ lang, onNext }: Props) {
  const c = useShipperCopy(lang).registration;
  return <div className="space-y-6">
    <div><h2 className="text-xl font-semibold tracking-[-0.02em] text-[#14284b] sm:text-2xl">{c.personalTitle}</h2><p className="mt-1 text-sm leading-6 text-slate-500">{c.personalDescription}</p></div>
    <div className="grid gap-5 sm:grid-cols-2">
      <Field label={c.fullName} placeholder={c.fullNamePlaceholder} icon={<UserRound />} required />
      <Field label={c.phone} placeholder={c.phonePlaceholder} icon={<Phone />} required type="tel" />
      <Field label={c.email} placeholder={c.emailPlaceholder} icon={<Mail />} required type="email" />
      <Field label={c.dateOfBirth} icon={<CalendarDays />} required type="date" />
      <div className="sm:col-span-2"><Field label={c.address} placeholder={c.addressPlaceholder} icon={<MapPin />} required /></div>
    </div>
    <div className="flex justify-end border-t border-slate-100 pt-5"><button onClick={onNext} className="w-full rounded-full bg-[#14284b] px-6 py-3 text-sm font-semibold text-white transition hover:bg-[#1d3968] sm:w-auto">{c.continue}</button></div>
  </div>;
}

function Field({ label, placeholder, icon, required, type = "text" }: { label: string; placeholder?: string; icon: ReactNode; required?: boolean; type?: string }) {
  return <label className="block"><span className="mb-2 flex items-center gap-2 text-sm font-medium text-[#14284b]">{icon}<span>{label}</span>{required && <span className="text-slate-400">*</span>}</span><input type={type} placeholder={placeholder} className="h-12 w-full rounded-2xl border border-slate-200 bg-white px-4 text-sm text-[#14284b] outline-none transition placeholder:text-slate-400 focus:border-[#14284b] focus:ring-4 focus:ring-[#14284b]/5" /></label>;
}
