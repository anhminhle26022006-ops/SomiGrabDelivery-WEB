import { Bike, CarFront, Palette, RectangleHorizontal, Truck } from "lucide-react";
import type { ReactNode } from "react";
import type { Language } from "../../../shared/i18n";
import { useShipperCopy } from "../i18n";

interface Props { lang: Language; onNext: () => void; onBack: () => void; }
export function VehicleInformation({ lang, onNext, onBack }: Props) {
  const c = useShipperCopy(lang).registration;
  const types = [["motorbike", c.motorbike, Bike], ["car", c.car, CarFront], ["van", c.van, Truck]] as const;
  return <div className="space-y-6">
    <div><h2 className="text-xl font-semibold tracking-[-0.02em] text-[#14284b] sm:text-2xl">{c.vehicleTitle}</h2><p className="mt-1 text-sm leading-6 text-slate-500">{c.vehicleDescription}</p></div>
    <div><p className="mb-3 text-sm font-medium text-[#14284b]">{c.vehicleType}</p><div className="grid gap-3 sm:grid-cols-3">{types.map(([id, label, Icon], index) => <label key={id} className={`cursor-pointer rounded-2xl border p-4 transition ${index === 0 ? "border-[#14284b] bg-[#14284b]/[0.04]" : "border-slate-200 bg-white hover:border-slate-300"}`}><input className="sr-only" type="radio" name="vehicle" defaultChecked={index === 0} /><Icon className="h-5 w-5 text-[#14284b]" /><span className="mt-3 block text-sm font-semibold text-[#14284b]">{label}</span></label>)}</div></div>
    <div className="grid gap-5 sm:grid-cols-3"><Field label={c.plate} placeholder={c.platePlaceholder} icon={<RectangleHorizontal />} /><Field label={c.model} placeholder={c.modelPlaceholder} icon={<Bike />} /><Field label={c.color} placeholder={c.colorPlaceholder} icon={<Palette />} /></div>
    <div className="flex flex-col-reverse gap-3 border-t border-slate-100 pt-5 sm:flex-row sm:justify-between"><button onClick={onBack} className="rounded-full border border-slate-200 px-6 py-3 text-sm font-semibold text-slate-600 hover:bg-slate-50">{c.back}</button><button onClick={onNext} className="rounded-full bg-[#14284b] px-6 py-3 text-sm font-semibold text-white hover:bg-[#1d3968]">{c.continue}</button></div>
  </div>;
}
function Field({ label, placeholder, icon }: { label: string; placeholder: string; icon: ReactNode }) { return <label><span className="mb-2 flex items-center gap-2 text-sm font-medium text-[#14284b]">{icon}{label}</span><input placeholder={placeholder} className="h-12 w-full rounded-2xl border border-slate-200 bg-white px-4 text-sm text-[#14284b] outline-none transition placeholder:text-slate-400 focus:border-[#14284b] focus:ring-4 focus:ring-[#14284b]/5" /></label>; }
