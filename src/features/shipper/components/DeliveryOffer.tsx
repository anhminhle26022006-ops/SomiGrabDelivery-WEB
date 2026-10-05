import { Check, Clock3, MapPin, X } from "lucide-react";
import type { DeliveryOfferData } from "../../../shared/domain/types";
import { formatVnd } from "../../../shared/utils";

interface DeliveryOfferProps {
  offer: DeliveryOfferData;
  onAccept: () => void;
  onReject: () => void;
}

export function DeliveryOffer({ offer, onAccept, onReject }: DeliveryOfferProps) {
  return (
    <div className="rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm">
      <div className="flex items-start justify-between gap-4"><div><span className="rounded-full bg-emerald-50 px-3 py-1 text-xs font-medium text-emerald-700">New offer</span><h3 className="mt-3 text-lg font-medium text-[#14284b]">{offer.order.id}</h3></div><div className="text-right"><p className="text-xs text-slate-400">Estimated earnings</p><p className="text-xl font-medium text-[#14284b]">{formatVnd(offer.order.fee * 0.8)}</p></div></div>
      <div className="mt-5 space-y-3 text-sm text-slate-600"><div className="flex items-center gap-2"><MapPin className="h-4 w-4 text-[#14284b]" />{offer.order.pickup} → {offer.order.dropoff}</div><div className="flex items-center gap-2"><Clock3 className="h-4 w-4 text-[#14284b]" />{offer.order.distanceKm} km · ETA {offer.etaMinutes} min</div></div>
      <div className="mt-5 grid grid-cols-2 gap-3"><button onClick={onReject} className="flex items-center justify-center gap-2 rounded-full border border-slate-200 px-4 py-3 text-sm text-slate-600 hover:bg-slate-50"><X className="h-4 w-4" />Reject</button><button onClick={onAccept} className="flex items-center justify-center gap-2 rounded-full bg-[#14284b] px-4 py-3 text-sm text-white hover:bg-[#1d3968]"><Check className="h-4 w-4" />Accept</button></div>
    </div>
  );
}
