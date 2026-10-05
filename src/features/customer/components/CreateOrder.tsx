import { useMemo, useState } from "react";
import { Calculator, MapPin, Package, Zap } from "lucide-react";
import { calculateDeliveryFee, formatVnd } from "../../../shared/utils";
import type { Order, ServiceType } from "../../../shared/domain/types";

interface CreateOrderProps {
  onCreate: (order: Order) => void;
}

export function CreateOrder({ onCreate }: CreateOrderProps) {
  const [pickup, setPickup] = useState("District 1");
  const [dropoff, setDropoff] = useState("Binh Thanh");
  const [distanceKm, setDistanceKm] = useState(4);
  const [weightKg, setWeightKg] = useState(2);
  const [service, setService] = useState<ServiceType>("STANDARD");

  const fee = useMemo(
    () => calculateDeliveryFee(distanceKm, weightKg, service === "EXPRESS"),
    [distanceKm, weightKg, service],
  );

  function submit() {
    const order: Order = {
      id: `SOMI-${Date.now().toString().slice(-6)}`,
      pickup,
      dropoff,
      distanceKm,
      weightKg,
      fee,
      service,
      status: "PENDING",
      customer: "Demo Customer",
      createdAt: new Date().toLocaleTimeString("vi-VN", { hour: "2-digit", minute: "2-digit" }),
    };

    onCreate(order);
  }

  return (
    <div className="grid gap-5 lg:grid-cols-[1.4fr_.8fr]">
      <div className="rounded-[2rem] border border-[#14284b]/10 bg-white p-5 md:p-6 shadow-sm">
        <div className="mb-5 flex items-center gap-3">
          <div className="rounded-2xl bg-[#14284b]/8 p-3 text-[#14284b]"><Package className="h-5 w-5" /></div>
          <div><h3 className="text-lg font-medium text-[#14284b]">Create delivery order</h3><p className="text-sm text-slate-500">Fee is calculated instantly before matching.</p></div>
        </div>

        <div className="grid gap-4 md:grid-cols-2">
          <label className="text-sm text-slate-600">Pickup
            <div className="mt-2 flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-3"><MapPin className="h-4 w-4 text-[#14284b]" /><input value={pickup} onChange={(e) => setPickup(e.target.value)} className="w-full outline-none" /></div>
          </label>
          <label className="text-sm text-slate-600">Drop-off
            <div className="mt-2 flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-3"><MapPin className="h-4 w-4 text-[#14284b]" /><input value={dropoff} onChange={(e) => setDropoff(e.target.value)} className="w-full outline-none" /></div>
          </label>
          <label className="text-sm text-slate-600">Distance (km)
            <input type="number" min="1" value={distanceKm} onChange={(e) => setDistanceKm(Number(e.target.value))} className="mt-2 w-full rounded-xl border border-slate-200 px-3 py-3 outline-none" />
          </label>
          <label className="text-sm text-slate-600">Weight (kg)
            <input type="number" min="0.5" step="0.5" value={weightKg} onChange={(e) => setWeightKg(Number(e.target.value))} className="mt-2 w-full rounded-xl border border-slate-200 px-3 py-3 outline-none" />
          </label>
        </div>

        <div className="mt-5 grid gap-3 sm:grid-cols-2">
          {(["STANDARD", "EXPRESS"] as ServiceType[]).map((item) => (
            <button key={item} onClick={() => setService(item)} className={`rounded-xl border px-4 py-3 text-left transition ${service === item ? "border-[#14284b] bg-[#14284b] text-white" : "border-slate-200 bg-white text-[#14284b]"}`}>
              <div className="flex items-center gap-2">{item === "EXPRESS" ? <Zap className="h-4 w-4" /> : <Package className="h-4 w-4" />}<span className="font-medium">{item === "EXPRESS" ? "Express" : "Standard"}</span></div>
              <p className={`mt-1 text-xs ${service === item ? "text-white/70" : "text-slate-500"}`}>{item === "EXPRESS" ? "Priority matching" : "Flexible delivery"}</p>
            </button>
          ))}
        </div>

        <button onClick={submit} className="mt-5 flex w-full items-center justify-center gap-2 rounded-full bg-[#14284b] px-5 py-3 text-white transition hover:bg-[#1d3968]"><Package className="h-4 w-4" />Create order & start matching</button>
      </div>

      <div className="rounded-[2rem] bg-[#14284b] p-6 text-white">
        <Calculator className="h-6 w-6 text-white/70" />
        <p className="mt-6 text-sm text-white/60">Estimated delivery fee</p>
        <div className="mt-1 text-3xl font-medium">{formatVnd(fee)}</div>
        <div className="mt-6 space-y-3 text-sm text-white/70">
          <div className="flex justify-between"><span>Base + distance</span><span>{formatVnd(30000 + Math.max(distanceKm - 3, 0) * 5000)}</span></div>
          <div className="flex justify-between"><span>Weight</span><span>{formatVnd(Math.max(weightKg - 2, 0) * 2500)}</span></div>
          <div className="flex justify-between"><span>Service</span><span>{service === "EXPRESS" ? formatVnd(12000) : "Included"}</span></div>
        </div>
      </div>
    </div>
  );
}
