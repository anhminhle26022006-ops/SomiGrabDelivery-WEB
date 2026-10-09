import { CheckCircle2, Clock3 } from "lucide-react";
import type { Language } from "../../../shared/i18n";
import { useShipperCopy } from "../i18n";
interface Props { lang: Language; onFinish: () => void; onBack: () => void; }
export function VerificationStatus({ lang, onFinish, onBack }: Props) {
  const c = useShipperCopy(lang).registration;
  return <div className="mx-auto max-w-2xl text-center">
    <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-amber-50"><Clock3 className="h-8 w-8 text-amber-600" /></div>
    <h2 className="mt-5 text-2xl font-semibold tracking-[-0.03em] text-[#14284b] sm:text-3xl">{c.statusTitle}</h2>
    <p className="mx-auto mt-2 max-w-lg text-sm leading-6 text-slate-500">{c.statusDescription}</p>
    <div className="mt-8 overflow-hidden rounded-[2rem] border border-slate-200 bg-white text-left shadow-sm"><div className="flex gap-4 border-b border-slate-100 p-5"><div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-emerald-50"><CheckCircle2 className="h-5 w-5 text-emerald-600" /></div><div><p className="text-sm font-semibold text-[#14284b]">{c.submitted}</p><p className="mt-1 text-sm leading-6 text-slate-500">{c.submittedDescription}</p></div></div><div className="flex gap-4 p-5"><div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-amber-50"><Clock3 className="h-5 w-5 text-amber-600" /></div><div><p className="text-sm font-semibold text-[#14284b]">{c.pending}</p><p className="mt-1 text-sm leading-6 text-slate-500">{c.pendingDescription}</p></div></div></div>
    <div className="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-center"><button onClick={onBack} className="rounded-full border border-slate-200 px-6 py-3 text-sm font-semibold text-slate-600 hover:bg-slate-50">{c.review}</button><button onClick={onFinish} className="rounded-full bg-[#14284b] px-6 py-3 text-sm font-semibold text-white hover:bg-[#1d3968]">{c.finish}</button></div>
  </div>;
}
