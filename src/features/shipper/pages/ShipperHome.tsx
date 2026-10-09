import { useState, type ReactNode } from "react";
import { BadgeCheck, Bike, Clock3, WalletCards } from "lucide-react";
import type { DeliveryOfferData } from "../../../shared/domain/types";
import type { Language } from "../../../shared/i18n";
import { DeliveryOffer } from "../components/DeliveryOffer";
import { useShipperCopy } from "../i18n";
interface ShipperHomeProps { lang: Language; offers: DeliveryOfferData[]; onAccept: (id: string) => void; onReject: (id: string) => void; onViewDetails: (id: string) => void; }
type Period = "day" | "week" | "month";
const stats: Record<Period, { orders: string; income: string; hours: string; reliability: string }> = {
  day: { orders: "12", income: "₫420,000", hours: "4.8h", reliability: "98%" },
  week: { orders: "68", income: "₫2,180,000", hours: "28.5h", reliability: "97%" },
  month: { orders: "245", income: "₫7,420,000", hours: "112h", reliability: "98%" },
};
export function ShipperHome({ lang, offers, onAccept, onReject, onViewDetails }: ShipperHomeProps) {
  const c = useShipperCopy(lang).dashboard;
  const [period, setPeriod] = useState<Period>("day");
  const [available, setAvailable] = useState(true);
  const s = stats[period];
  const labels = lang === "vi" ? { day: "Ngày", week: "Tuần", month: "Tháng", orders: "Đơn hoàn tất", income: "Thu nhập", hours: "Giờ hoạt động", reliability: "Tỷ lệ nhận đơn", available: "Đang sẵn sàng nhận đơn", offline: "Đang ngoại tuyến", onlineHint: "Bạn có thể tắt trạng thái này để ngừng nhận đơn mới.", offers: "Đơn giao gần bạn" } : { day: "Day", week: "Week", month: "Month", orders: "Completed deliveries", income: "Earnings", hours: "Active hours", reliability: "Acceptance rate", available: "Available for new offers", offline: "You are offline", onlineHint: "Turn this off to stop receiving new offers.", offers: "Delivery offers near you" };
  return <section className="mx-auto w-full max-w-6xl px-4 py-5 sm:px-5 sm:py-8 md:px-8 md:py-10">
    <div className="mb-6 flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between"><div className="min-w-0"><p className="text-xs font-medium uppercase tracking-[.2em] text-[#14284b]/50">{c.eyebrow}</p><h2 className="mt-2 text-2xl leading-tight text-[#14284b] sm:text-3xl">{lang === "vi" ? "Tổng quan Shipper" : "Shipper Dashboard"}</h2><p className="mt-2 text-slate-500">{lang === "vi" ? "Theo dõi đơn giao và thu nhập của bạn." : "Track your deliveries and earnings."}</p></div>
      <div className="flex w-full items-center justify-between gap-4 rounded-2xl border border-slate-200 bg-white px-4 py-4 shadow-sm sm:w-auto sm:min-w-[280px]"><div className="min-w-0"><p className="text-sm font-bold uppercase tracking-wide text-[#14284b]">{available ? labels.available : labels.offline}</p><p className="mt-1 text-xs text-slate-500">{available ? (lang === "vi" ? "Đang online · nhận đơn mới" : "Online · receiving new offers") : labels.onlineHint}</p></div><button type="button" role="switch" aria-checked={available} onClick={() => setAvailable(v => !v)} className={`relative h-12 w-[60px] shrink-0 rounded-full transition ${available ? "bg-emerald-500" : "bg-slate-300"}`}><span className={`absolute top-1 h-10 w-10 rounded-full bg-white shadow-sm transition ${available ? "left-[16px]" : "left-1"}`} /></button></div>
    </div>
    <div className="mb-4 flex w-fit gap-1 rounded-full bg-slate-100 p-1">{(["day", "week", "month"] as Period[]).map(p => <button key={p} onClick={() => setPeriod(p)} className={`rounded-full px-5 py-2 text-sm font-semibold ${period === p ? "bg-[#14284b] text-white shadow-sm" : "text-slate-600 hover:bg-white"}`}>{labels[p]}</button>)}</div>
    <div className="mb-7 grid gap-3 sm:grid-cols-2 lg:grid-cols-4"><Stat dark icon={<Bike/>} value={s.orders} label={labels.orders}/><Stat icon={<WalletCards/>} value={s.income} label={labels.income}/><Stat icon={<Clock3/>} value={s.hours} label={labels.hours}/><Stat icon={<BadgeCheck/>} value={s.reliability} label={labels.reliability}/></div>
    <div className="mb-4 flex items-center justify-between"><h3 className="text-lg font-semibold text-[#14284b]">{labels.offers}</h3><span className="text-sm text-slate-500">{offers.length} {lang === "vi" ? "đơn" : "offers"}</span></div>
    <div className="grid gap-5 md:grid-cols-2">{offers.length ? offers.map(offer => <DeliveryOffer key={offer.order.id} offer={offer} onAccept={() => onAccept(offer.order.id)} onReject={() => onReject(offer.order.id)} onViewDetails={() => onViewDetails(offer.order.id)} />) : <div className="md:col-span-2 rounded-[2rem] border border-dashed border-slate-300 bg-white p-10 text-center text-slate-500">{c.noOffers}</div>}</div>
  </section>;
}
function Stat({ icon, value, label, dark = false }: { icon: ReactNode; value: string; label: string; dark?: boolean }) { return <div className={`rounded-2xl p-4 sm:p-5 ${dark ? "bg-[#14284b] text-white" : "border border-slate-200 bg-white"}`}><span className={dark ? "text-white/70" : "text-[#14284b]"}>{icon}</span><p className="mt-4 text-xl font-semibold sm:text-2xl">{value}</p><p className={`text-sm ${dark ? "text-white/60" : "text-slate-500"}`}>{label}</p></div> }
