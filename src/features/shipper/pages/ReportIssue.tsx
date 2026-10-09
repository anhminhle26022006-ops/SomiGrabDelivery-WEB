import { AlertTriangle, ArrowLeft } from "lucide-react";
import type { Language } from "../../../shared/i18n";
import { useShipperCopy } from "../i18n";

export function ReportIssue({ lang, onBack }: { lang: Language; onBack: () => void }) {
  const c = useShipperCopy(lang).issue;
  return <section className="mx-auto w-full max-w-3xl px-4 py-6 sm:px-5 sm:py-8 md:px-8 md:py-12">
    <button onClick={onBack} className="mb-5 flex items-center gap-2 text-sm font-semibold text-[#14284b]"><ArrowLeft className="h-4 w-4" />{c.back}</button>
    <div className="rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm sm:p-8">
      <div className="flex items-start gap-4"><span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-red-50 text-red-600"><AlertTriangle className="h-5 w-5" /></span><div><h1 className="text-2xl font-semibold text-[#14284b]">{c.title}</h1><p className="mt-1 text-sm text-slate-500">{c.description}</p></div></div>
      <div className="mt-7 grid gap-5">
        <label className="text-sm font-semibold text-[#14284b]">{c.reason}<select className="mt-2 h-12 w-full rounded-2xl border border-slate-200 bg-white px-4 text-sm font-normal text-slate-700 outline-none focus:border-[#14284b]"><option>{c.customerUnavailable}</option><option>{c.wrongAddress}</option><option>{c.packageIssue}</option><option>{c.vehicleProblem}</option><option>{c.safetyConcern}</option><option>{c.other}</option></select></label>
        <label className="text-sm font-semibold text-[#14284b]">{c.details}<textarea className="mt-2 min-h-32 w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm font-normal outline-none focus:border-[#14284b]" placeholder={c.placeholder} /></label>
        <button onClick={onBack} className="rounded-full bg-[#14284b] px-5 py-3 text-sm font-semibold text-white hover:bg-[#1d3968]">{c.submit}</button>
      </div>
    </div>
  </section>;
}
