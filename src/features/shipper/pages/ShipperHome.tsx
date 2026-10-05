import { BadgeCheck, Bike, Clock3 } from "lucide-react";
import type { DeliveryOfferData } from "../../../shared/domain/types";
import { DeliveryOffer } from "../components/DeliveryOffer";

interface ShipperHomeProps {
  offers: DeliveryOfferData[];
  onAccept: (id: string) => void;
  onReject: (id: string) => void;
}

export function ShipperHome({ offers, onAccept, onReject }: ShipperHomeProps) {
  return (
    <section className="mx-auto w-full max-w-6xl px-4 py-8 sm:px-5 sm:py-10 md:px-8 md:py-12">
      <div className="mb-8"><p className="text-xs font-medium uppercase tracking-[0.2em] text-[#14284b]/50">Shipper workspace</p><h2 className="mt-2 text-2xl leading-tight text-[#14284b] sm:text-3xl">Choose deliveries that fit your time</h2><p className="mt-2 text-slate-500">Review nearby offers and accept the trips you want.</p></div>
      <div className="mb-6 grid gap-3 sm:mb-8 md:grid-cols-3"><div className="rounded-2xl bg-[#14284b] p-4 sm:p-5 text-white"><Bike className="h-5 w-5 text-white/70" /><p className="mt-4 text-3xl">12</p><p className="text-sm text-white/60">Deliveries this week</p></div><div className="rounded-2xl border border-slate-200 bg-white p-4 sm:p-5"><Clock3 className="h-5 w-5 text-[#14284b]" /><p className="mt-4 text-2xl leading-tight text-[#14284b] sm:text-3xl">4.8h</p><p className="text-sm text-slate-500">Active hours</p></div><div className="rounded-2xl border border-slate-200 bg-white p-4 sm:p-5"><BadgeCheck className="h-5 w-5 text-[#14284b]" /><p className="mt-4 text-2xl leading-tight text-[#14284b] sm:text-3xl">98%</p><p className="text-sm text-slate-500">Acceptance reliability</p></div></div>
      <div className="grid gap-5 md:grid-cols-2">{offers.length ? offers.map((offer) => <DeliveryOffer key={offer.order.id} offer={offer} onAccept={() => onAccept(offer.order.id)} onReject={() => onReject(offer.order.id)} />) : <div className="md:col-span-2 rounded-[2rem] border border-dashed border-slate-300 bg-white p-10 text-center text-slate-500">No new delivery offers right now.</div>}</div>
    </section>
  );
}
