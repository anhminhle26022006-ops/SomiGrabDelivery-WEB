import { useState } from "react";
import { Check } from "lucide-react";
import type { Language } from "../../../shared/i18n";
import { useShipperCopy } from "../i18n";
import { PersonalInformation } from "../registration/PersonalInformation";
import { VehicleInformation } from "../registration/VehicleInformation";
import { IdentityVerification } from "../registration/IdentityVerification";
import { VerificationStatus } from "../registration/VerificationStatus";

interface Props { lang: Language; onFinish: () => void; }
export function ShipperRegistration({ lang, onFinish }: Props) {
  const c = useShipperCopy(lang).registration;
  const [step, setStep] = useState(0);
  const content = [
    <PersonalInformation key="personal" lang={lang} onNext={() => setStep(1)} />,
    <VehicleInformation key="vehicle" lang={lang} onBack={() => setStep(0)} onNext={() => setStep(2)} />,
    <IdentityVerification key="identity" lang={lang} onBack={() => setStep(1)} onNext={() => setStep(3)} />,
    <VerificationStatus key="status" lang={lang} onBack={() => setStep(2)} onFinish={onFinish} />,
  ];
  return <section className="mx-auto w-full max-w-5xl px-4 py-6 sm:px-5 sm:py-8 md:px-8 md:py-12">
    <div className="mb-6 sm:mb-8"><p className="text-xs font-semibold uppercase tracking-[0.2em] text-[#14284b]/50">SOMI SHIPPER</p><h1 className="mt-2 text-2xl font-semibold tracking-[-0.04em] text-[#14284b] sm:text-3xl">{c.title}</h1><p className="mt-2 max-w-2xl text-sm leading-6 text-slate-500">{c.description}</p></div>
    <div className="mb-6 overflow-x-auto pb-1"><div className="flex min-w-[620px] items-center">{c.steps.map((label, index) => <div key={label} className="flex flex-1 items-center"><div className="flex items-center gap-2"><span className={`flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-xs font-bold ${index < step ? "bg-emerald-100 text-emerald-700" : index === step ? "bg-[#14284b] text-white" : "bg-white text-slate-400 ring-1 ring-slate-200"}`}>{index < step ? <Check className="h-4 w-4" /> : index + 1}</span><span className={`whitespace-nowrap text-xs font-semibold ${index <= step ? "text-[#14284b]" : "text-slate-400"}`}>{label}</span></div>{index < c.steps.length - 1 && <div className={`mx-3 h-px flex-1 ${index < step ? "bg-emerald-200" : "bg-slate-200"}`} />}</div>)}</div></div>
    <div className="rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm sm:p-7 md:p-9">{content[step]}</div>
  </section>;
}
