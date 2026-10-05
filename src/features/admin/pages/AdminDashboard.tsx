import { useMemo, useState } from "react";
import {
  Activity,
  AlertTriangle,
  ArrowUpRight,
  Bell,
  CircleDollarSign,
  ClipboardList,
  LayoutDashboard,
  LogOut,
  Menu,
  PackageSearch,
  Search,
  Settings,
  ShieldCheck,
  Truck,
  UserRound,
  Users,
  X,
} from "lucide-react";
import type { Order } from "../../../shared/domain/types";
import { formatVnd } from "../../../shared/utils";
import { OrderManagement } from "../components/OrderManagement";

type AdminSection = "overview" | "orders" | "shippers" | "customers" | "complaints";

interface AdminDashboardProps {
  orders: Order[];
  onStatusChange: (id: string, status: Order["status"]) => void;
  onExit: () => void;
}

const complaints = [
  { id: "RP-001", order: "SOMI-240125", type: "Delivery issue", reporter: "Customer", status: "OPEN", time: "10 min ago" },
  { id: "RP-002", order: "SOMI-240119", type: "Damaged package", reporter: "Customer", status: "IN_REVIEW", time: "42 min ago" },
  { id: "RP-003", order: "SOMI-240104", type: "Payment problem", reporter: "Shipper", status: "RESOLVED", time: "2 hr ago" },
];

const shippers = [
  { name: "Minh Nguyen", status: "ONLINE", rating: "4.9", completed: 128, verified: true },
  { name: "An Tran", status: "ONLINE", rating: "4.8", completed: 96, verified: true },
  { name: "Linh Pham", status: "OFFLINE", rating: "4.7", completed: 74, verified: true },
  { name: "Khoa Le", status: "REVIEW", rating: "4.5", completed: 31, verified: false },
];

const customers = [
  { name: "Demo Customer", email: "customer@somi.demo", orders: 18, status: "ACTIVE" },
  { name: "Mai Tran", email: "mai@somi.demo", orders: 11, status: "ACTIVE" },
  { name: "Huy Nguyen", email: "huy@somi.demo", orders: 7, status: "ACTIVE" },
];

function StatCard({ icon: Icon, label, value, dark = false, note }: { icon: typeof PackageSearch; label: string; value: string; dark?: boolean; note?: string }) {
  return (
    <div className={`rounded-[1.5rem] border p-5 ${dark ? "border-[#14284b] bg-[#14284b] text-white" : "border-slate-200 bg-white text-[#14284b]"}`}>
      <div className="flex items-center justify-between">
        <div className={`flex h-10 w-10 items-center justify-center rounded-xl ${dark ? "bg-white/10" : "bg-[#edf2f8]"}`}><Icon className="h-5 w-5" /></div>
        {note && <span className={`text-[11px] ${dark ? "text-white/55" : "text-slate-400"}`}>{note}</span>}
      </div>
      <p className="mt-5 text-2xl tracking-[-.03em] sm:text-3xl">{value}</p>
      <p className={`mt-1 text-sm ${dark ? "text-white/60" : "text-slate-500"}`}>{label}</p>
    </div>
  );
}

export function AdminDashboard({ orders, onStatusChange, onExit }: AdminDashboardProps) {
  const [section, setSection] = useState<AdminSection>("overview");
  const [mobileMenu, setMobileMenu] = useState(false);
  const [query, setQuery] = useState("");

  const revenue = orders.reduce((sum, order) => sum + order.fee, 0);
  const active = orders.filter((o) => !["COMPLETED", "CANCELLED", "FAILED", "RETURNED"].includes(o.status)).length;
  const matching = orders.filter((o) => o.status === "MATCHING").length;

  const filteredCustomers = useMemo(
    () => customers.filter((item) => `${item.name} ${item.email}`.toLowerCase().includes(query.toLowerCase())),
    [query],
  );
  const filteredShippers = useMemo(
    () => shippers.filter((item) => item.name.toLowerCase().includes(query.toLowerCase())),
    [query],
  );

  const navItems: { id: AdminSection; label: string; icon: typeof LayoutDashboard }[] = [
    { id: "overview", label: "Overview", icon: LayoutDashboard },
    { id: "orders", label: "Orders", icon: ClipboardList },
    { id: "shippers", label: "Shippers", icon: Truck },
    { id: "customers", label: "Customers", icon: UserRound },
    { id: "complaints", label: "Complaints", icon: AlertTriangle },
  ];

  const selectSection = (value: AdminSection) => {
    setSection(value);
    setMobileMenu(false);
    setQuery("");
  };

  return (
    <main className="min-h-screen bg-[#f3f4f5] text-[#14284b]">
      <header className="sticky top-0 z-50 border-b border-slate-200/80 bg-white/95 backdrop-blur-xl">
        <div className="flex h-16 items-center justify-between px-4 md:px-6">
          <div className="flex items-center gap-3">
            <button onClick={() => setMobileMenu((value) => !value)} className="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-100 lg:hidden" aria-label="Open admin menu">
              {mobileMenu ? <X className="h-5 w-5" /> : <Menu className="h-5 w-5" />}
            </button>
            <div className="flex items-end gap-2">
              <span className="text-xl tracking-[-.05em]">SOMI</span>
              <span className="pb-0.5 text-[8px] tracking-[.2em] text-slate-400">ADMIN</span>
            </div>
            <span className="hidden rounded-full bg-[#edf2f8] px-2.5 py-1 text-[10px] font-medium uppercase tracking-wider text-[#14284b]/60 sm:inline-flex">Internal</span>
          </div>
          <div className="flex items-center gap-2">
            <button className="hidden h-9 w-9 items-center justify-center rounded-full border border-slate-200 bg-white sm:flex"><Bell className="h-4 w-4" /></button>
            <div className="hidden items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs sm:flex"><ShieldCheck className="h-4 w-4" /> Administrator</div>
            <button onClick={onExit} className="flex items-center gap-2 rounded-full bg-[#14284b] px-4 py-2 text-xs text-white"><LogOut className="h-4 w-4" /> Exit</button>
          </div>
        </div>
      </header>

      <div className="mx-auto flex max-w-[1500px]">
        <aside className={`${mobileMenu ? "fixed inset-x-0 top-16 z-40 block" : "hidden"} border-b border-slate-200 bg-white p-3 lg:sticky lg:top-16 lg:block lg:h-[calc(100vh-4rem)] lg:w-64 lg:shrink-0 lg:border-b-0 lg:border-r lg:p-4`}>
          <div className="rounded-2xl bg-slate-50 p-2">
            {navItems.map(({ id, label, icon: Icon }) => (
              <button key={id} onClick={() => selectSection(id)} className={`mb-1 flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left text-sm transition last:mb-0 ${section === id ? "bg-[#14284b] text-white shadow-sm" : "text-slate-600 hover:bg-white hover:text-[#14284b]"}`}>
                <Icon className="h-4 w-4" />{label}
                {id === "complaints" && <span className="ml-auto rounded-full bg-amber-100 px-2 py-0.5 text-[10px] text-amber-700">2</span>}
              </button>
            ))}
          </div>
          <div className="mt-4 rounded-2xl border border-slate-200 bg-white p-4">
            <div className="flex items-center gap-2 text-xs font-medium"><Settings className="h-4 w-4" />System status</div>
            <div className="mt-3 flex items-center gap-2 text-xs text-slate-500"><span className="h-2 w-2 rounded-full bg-emerald-500" />All core services operational</div>
            <p className="mt-2 text-[11px] text-slate-400">Internal tools are not linked from the public website.</p>
          </div>
        </aside>

        <section className="min-w-0 flex-1 px-4 py-6 sm:px-6 md:px-8 md:py-8">
          <div className="mb-7 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
              <p className="text-[10px] font-medium uppercase tracking-[.22em] text-[#14284b]/45">Internal operations</p>
              <h1 className="mt-2 text-2xl tracking-[-.04em] sm:text-3xl">{navItems.find((item) => item.id === section)?.label}</h1>
              <p className="mt-1 text-sm text-slate-500">Manage Somi delivery operations from one internal workspace.</p>
            </div>
            {section !== "overview" && section !== "orders" && (
              <label className="flex w-full max-w-sm items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2.5">
                <Search className="h-4 w-4 text-slate-400" />
                <input value={query} onChange={(e) => setQuery(e.target.value)} placeholder={`Search ${section}...`} className="w-full bg-transparent text-sm outline-none" />
              </label>
            )}
          </div>

          {section === "overview" && (
            <>
              <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <StatCard icon={PackageSearch} label="Total orders" value={String(orders.length)} dark note="All time" />
                <StatCard icon={Activity} label="Active orders" value={String(active)} note="Live" />
                <StatCard icon={CircleDollarSign} label="Gross delivery value" value={formatVnd(revenue)} note="Demo data" />
                <StatCard icon={Users} label="Active shippers" value="5.2K+" note="Network" />
              </div>

              <div className="mt-5 grid gap-5 xl:grid-cols-[1.4fr_.8fr]">
                <div className="rounded-[1.75rem] border border-slate-200 bg-white p-5 sm:p-6">
                  <div className="flex items-center justify-between gap-3"><div><h2 className="text-lg">Live operations</h2><p className="mt-1 text-xs text-slate-500">Current delivery workload and matching activity.</p></div><button onClick={() => selectSection("orders")} className="flex items-center gap-1 text-xs font-medium">View orders <ArrowUpRight className="h-3.5 w-3.5" /></button></div>
                  <div className="mt-5 grid gap-3 sm:grid-cols-3">
                    <div className="rounded-2xl bg-slate-50 p-4"><p className="text-2xl">{matching}</p><p className="mt-1 text-xs text-slate-500">Waiting for shipper</p></div>
                    <div className="rounded-2xl bg-slate-50 p-4"><p className="text-2xl">{orders.filter((o) => o.status === "IN_TRANSIT").length}</p><p className="mt-1 text-xs text-slate-500">In transit</p></div>
                    <div className="rounded-2xl bg-slate-50 p-4"><p className="text-2xl">{orders.filter((o) => o.status === "DELIVERED").length}</p><p className="mt-1 text-xs text-slate-500">Delivered</p></div>
                  </div>
                  <div className="mt-5 space-y-2">
                    {orders.slice(0, 4).map((order) => <div key={order.id} className="flex items-center justify-between gap-3 rounded-xl border border-slate-100 px-3 py-3 text-xs"><div className="min-w-0"><p className="font-medium">{order.id}</p><p className="truncate text-slate-400">{order.pickup} → {order.dropoff}</p></div><span className="shrink-0 rounded-full bg-slate-100 px-2.5 py-1 text-[10px] text-slate-600">{order.status}</span></div>)}
                  </div>
                </div>
                <div className="rounded-[1.75rem] border border-slate-200 bg-white p-5 sm:p-6">
                  <div className="flex items-center gap-2"><AlertTriangle className="h-4 w-4" /><h2 className="text-lg">Needs attention</h2></div>
                  <div className="mt-5 space-y-3">
                    {complaints.filter((item) => item.status !== "RESOLVED").map((item) => <div key={item.id} className="rounded-2xl border border-amber-100 bg-amber-50/50 p-4"><div className="flex items-center justify-between"><span className="text-xs font-medium">{item.id}</span><span className="text-[10px] text-amber-700">{item.status}</span></div><p className="mt-2 text-sm">{item.type}</p><p className="mt-1 text-xs text-slate-500">{item.order} · {item.time}</p></div>)}
                  </div>
                </div>
              </div>
            </>
          )}

          {section === "orders" && <OrderManagement orders={orders} onStatusChange={onStatusChange} />}

          {section === "shippers" && (
            <div className="overflow-hidden rounded-[1.75rem] border border-slate-200 bg-white">
              <div className="overflow-x-auto"><table className="w-full min-w-[720px] text-left text-sm"><thead><tr className="border-b border-slate-100 text-[10px] uppercase tracking-wider text-slate-400"><th className="p-4">Shipper</th><th className="p-4">Status</th><th className="p-4">Rating</th><th className="p-4">Completed</th><th className="p-4">Verification</th></tr></thead><tbody>{filteredShippers.map((shipper) => <tr key={shipper.name} className="border-b border-slate-50 last:border-0"><td className="p-4 font-medium">{shipper.name}</td><td className="p-4"><span className={`rounded-full px-2.5 py-1 text-[10px] ${shipper.status === "ONLINE" ? "bg-emerald-50 text-emerald-700" : shipper.status === "REVIEW" ? "bg-amber-50 text-amber-700" : "bg-slate-100 text-slate-500"}`}>{shipper.status}</span></td><td className="p-4">{shipper.rating}</td><td className="p-4 text-slate-500">{shipper.completed}</td><td className="p-4">{shipper.verified ? <span className="flex items-center gap-1 text-emerald-700"><ShieldCheck className="h-4 w-4" />Verified</span> : <span className="text-amber-700">Needs review</span>}</td></tr>)}</tbody></table></div>
            </div>
          )}

          {section === "customers" && (
            <div className="overflow-hidden rounded-[1.75rem] border border-slate-200 bg-white">
              <div className="overflow-x-auto"><table className="w-full min-w-[650px] text-left text-sm"><thead><tr className="border-b border-slate-100 text-[10px] uppercase tracking-wider text-slate-400"><th className="p-4">Customer</th><th className="p-4">Email</th><th className="p-4">Orders</th><th className="p-4">Status</th></tr></thead><tbody>{filteredCustomers.map((customer) => <tr key={customer.email} className="border-b border-slate-50 last:border-0"><td className="p-4 font-medium">{customer.name}</td><td className="p-4 text-slate-500">{customer.email}</td><td className="p-4">{customer.orders}</td><td className="p-4"><span className="rounded-full bg-emerald-50 px-2.5 py-1 text-[10px] text-emerald-700">{customer.status}</span></td></tr>)}</tbody></table></div>
            </div>
          )}

          {section === "complaints" && (
            <div className="overflow-hidden rounded-[1.75rem] border border-slate-200 bg-white">
              <div className="overflow-x-auto"><table className="w-full min-w-[760px] text-left text-sm"><thead><tr className="border-b border-slate-100 text-[10px] uppercase tracking-wider text-slate-400"><th className="p-4">Report</th><th className="p-4">Order</th><th className="p-4">Issue</th><th className="p-4">Reporter</th><th className="p-4">Status</th><th className="p-4">Time</th></tr></thead><tbody>{complaints.map((item) => <tr key={item.id} className="border-b border-slate-50 last:border-0"><td className="p-4 font-medium">{item.id}</td><td className="p-4 text-slate-500">{item.order}</td><td className="p-4">{item.type}</td><td className="p-4 text-slate-500">{item.reporter}</td><td className="p-4"><span className={`rounded-full px-2.5 py-1 text-[10px] ${item.status === "RESOLVED" ? "bg-emerald-50 text-emerald-700" : "bg-amber-50 text-amber-700"}`}>{item.status}</span></td><td className="p-4 text-slate-400">{item.time}</td></tr>)}</tbody></table></div>
            </div>
          )}
        </section>
      </div>
    </main>
  );
}
