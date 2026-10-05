import { PackageCheck, Route, ShieldCheck } from "lucide-react";
import type { Order } from "../../../shared/domain/types";
import { formatVnd } from "../../../shared/utils";
import { CreateOrder } from "../components/CreateOrder";

interface CustomerHomeProps {
  orders: Order[];
  onCreate: (order: Order) => void;
}

export function CustomerHome({ orders, onCreate }: CustomerHomeProps) {
  return (
    <section className="mx-auto w-full max-w-6xl px-4 py-8 sm:px-5 sm:py-10 md:px-8 md:py-12">
      <div className="mb-8"><p className="text-xs font-medium uppercase tracking-[0.2em] text-[#14284b]/50">Customer workspace</p><h2 className="mt-2 text-2xl leading-tight text-[#14284b] sm:text-3xl">Send a package</h2><p className="mt-2 text-slate-500">Create an order, preview the fee, then send it to the matching network.</p></div>
      <CreateOrder onCreate={onCreate} />

      <div className="mt-10 grid gap-4 md:grid-cols-3">
        {[{ icon: Route, title: "Smart matching", text: "Offers are sent to nearby verified shippers." }, { icon: PackageCheck, title: "Live status", text: "Track the order from matching to completed." }, { icon: ShieldCheck, title: "Transparent fee", text: "The estimated fee is shown before confirmation." }].map(({ icon: Icon, title, text }) => <div key={title} className="rounded-2xl border border-slate-200 bg-white p-4 sm:p-5"><Icon className="h-5 w-5 text-[#14284b]" /><h3 className="mt-4 font-medium text-[#14284b]">{title}</h3><p className="mt-1 text-sm text-slate-500">{text}</p></div>)}
      </div>

      <div className="mt-10 rounded-[2rem] border border-slate-200 bg-white p-5 md:p-6">
        <div className="flex items-center justify-between"><h3 className="text-lg font-medium text-[#14284b]">Recent orders</h3><span className="rounded-full bg-slate-100 px-3 py-1 text-xs text-slate-500">{orders.length} orders</span></div>
        <div className="mt-4 space-y-3">{orders.length === 0 ? <p className="py-6 text-sm text-slate-500">No orders yet. Create your first delivery above.</p> : orders.map((order) => <div key={order.id} className="flex flex-col gap-3 rounded-2xl border border-slate-100 p-4 sm:flex-row sm:items-center sm:justify-between"><div><p className="font-medium text-[#14284b]">{order.id}</p><p className="text-sm text-slate-500">{order.pickup} → {order.dropoff}</p></div><div className="flex items-center gap-4"><span className="rounded-full bg-[#14284b]/8 px-3 py-1 text-xs text-[#14284b]">{order.status}</span><span className="text-sm font-medium text-[#14284b]">{formatVnd(order.fee)}</span></div></div>)}</div>
      </div>
    </section>
  );
}
