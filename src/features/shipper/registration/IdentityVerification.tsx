import { useState } from "react";
import { CheckCircle2, FileText, UploadCloud, X } from "lucide-react";
import type { Language } from "../../../shared/i18n";
import { useShipperCopy } from "../i18n";

interface Props { lang: Language; onNext: () => void; onBack: () => void; }
export function IdentityVerification({ lang, onNext, onBack }: Props) {
  const c = useShipperCopy(lang).registration;
  const [files, setFiles] = useState<Record<string, string>>({});
  const [confirmed, setConfirmed] = useState(false);
  const docs = [["front", c.idFront], ["back", c.idBack], ["license", c.driverLicense]];
  const upload = (id: string, file?: File) => file && setFiles(value => ({ ...value, [id]: file.name }));
  return <div className="space-y-6">
    <div><h2 className="text-xl font-semibold tracking-[-0.02em] text-[#14284b] sm:text-2xl">{c.identityTitle}</h2><p className="mt-1 text-sm leading-6 text-slate-500">{c.identityDescription}</p></div>
    <div className="grid gap-4 md:grid-cols-3">{docs.map(([id, label]) => <div key={id} className="rounded-2xl border border-slate-200 bg-white p-4"><p className="mb-3 text-sm font-semibold text-[#14284b]">{label}</p>{files[id] ? <div className="rounded-xl bg-emerald-50 p-3"><div className="flex items-start gap-2"><CheckCircle2 className="mt-0.5 h-4 w-4 shrink-0 text-emerald-600" /><p className="min-w-0 flex-1 truncate text-xs text-emerald-700">{files[id]}</p><button type="button" onClick={() => setFiles(value => { const next = { ...value }; delete next[id]; return next; })} aria-label={c.remove} className="text-emerald-700"><X className="h-4 w-4" /></button></div></div> : <label className="flex min-h-32 cursor-pointer flex-col items-center justify-center rounded-xl border border-dashed border-slate-300 bg-slate-50/70 text-center hover:border-[#14284b]/40 hover:bg-slate-50"><UploadCloud className="h-5 w-5 text-[#14284b]" /><span className="mt-2 text-xs font-semibold text-[#14284b]">{c.upload}</span><input className="sr-only" type="file" accept="image/*,.pdf" onChange={event => upload(id, event.target.files?.[0])} /></label>}</div>)}</div>
    <label className="flex gap-3 rounded-2xl border border-slate-200 bg-white p-4"><input type="checkbox" checked={confirmed} onChange={event => setConfirmed(event.target.checked)} className="mt-0.5 h-4 w-4 rounded border-slate-300 accent-[#14284b]" /><span className="text-sm leading-6 text-slate-600">{c.confirmation}</span></label>
    <div className="flex flex-col-reverse gap-3 border-t border-slate-100 pt-5 sm:flex-row sm:justify-between"><button onClick={onBack} className="rounded-full border border-slate-200 px-6 py-3 text-sm font-semibold text-slate-600 hover:bg-slate-50">{c.back}</button><button disabled={!confirmed || docs.some(([id]) => !files[id])} onClick={onNext} className="rounded-full bg-[#14284b] px-6 py-3 text-sm font-semibold text-white transition hover:bg-[#1d3968] disabled:cursor-not-allowed disabled:opacity-40"><FileText className="mr-2 inline h-4 w-4" />{c.submit}</button></div>
  </div>;
}
