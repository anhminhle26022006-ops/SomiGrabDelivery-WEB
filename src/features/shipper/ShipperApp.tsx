import { useState } from "react";
import { Bell, CircleUserRound, Home, LogOut, Menu, Truck, Wallet, X } from "lucide-react";
import type { DeliveryOfferData } from "../../shared/domain/types";
import { useLanguage } from "../../shared/i18n";
import { ShipperHome } from "./pages/ShipperHome";

interface ShipperAppProps {
  offers: DeliveryOfferData[];
  onAccept: (id: string) => void;
  onReject: (id: string) => void;
  onExit: () => void;
}

/** Shipper-only application shell. Keep Shipper UI changes inside features/shipper. */
export function ShipperApp({ offers, onAccept, onReject, onExit }: ShipperAppProps) {
  const { t } = useLanguage();
  const [mobileMenu, setMobileMenu] = useState(false);

  return (
    <main className="min-h-screen bg-[#f0f0f0] pb-20 md:pb-0">
      <header className="sticky top-0 z-40 border-b border-white/70 bg-[#f0f0f0]/90 px-4 py-3 backdrop-blur-xl md:px-6 md:py-4">
        <div className="mx-auto flex max-w-6xl items-center justify-between gap-4">
          <button onClick={onExit} className="flex items-center gap-2 text-[#14284b]">
            <span className="font-semibold tracking-[-.04em] text-xl md:text-2xl">SOMI</span>
            <span className="hidden text-[8px] tracking-[.2em] text-[#14284b]/50 sm:inline">DELIVERY</span>
          </button>
          <nav className="hidden items-center gap-1 rounded-full bg-white p-1 shadow-sm md:flex">
            <button className="flex items-center gap-2 rounded-full bg-[#14284b] px-4 py-2 text-xs font-bold text-white"><Home className="h-4 w-4" />Dashboard</button>
            <button className="flex items-center gap-2 rounded-full px-4 py-2 text-xs font-bold text-slate-600"><Truck className="h-4 w-4" />Delivery Offers</button>
            <button className="flex items-center gap-2 rounded-full px-4 py-2 text-xs font-bold text-slate-600"><Wallet className="h-4 w-4" />Earnings</button>
          </nav>
          <div className="flex items-center gap-2">
            <button className="hidden h-10 w-10 items-center justify-center rounded-full bg-white text-[#14284b] shadow-sm sm:flex" aria-label="Notifications"><Bell className="h-4 w-4" /></button>
            <button className="hidden items-center gap-2 rounded-full bg-white px-3 py-2 text-xs font-bold text-[#14284b] shadow-sm sm:flex"><CircleUserRound className="h-4 w-4" />Account</button>
            <button onClick={onExit} className="hidden items-center gap-2 rounded-full bg-[#14284b] px-4 py-2 text-xs font-bold text-white sm:flex"><LogOut className="h-4 w-4" />{t.nav.signOut}</button>
            <button onClick={() => setMobileMenu(value => !value)} className="flex h-10 w-10 items-center justify-center rounded-full bg-white text-[#14284b] shadow-sm md:hidden" aria-label="Open menu">{mobileMenu ? <X className="h-5 w-5" /> : <Menu className="h-5 w-5" />}</button>
          </div>
        </div>
        {mobileMenu && <div className="mx-auto mt-3 grid max-w-6xl gap-2 rounded-3xl border border-white/80 bg-white/95 p-3 shadow-xl md:hidden">
          <button className="flex items-center gap-3 rounded-2xl bg-[#14284b] px-4 py-3 text-left text-sm font-bold text-white"><Home className="h-4 w-4" />Dashboard</button>
          <button className="flex items-center gap-3 rounded-2xl px-4 py-3 text-left text-sm font-bold text-slate-600"><Truck className="h-4 w-4" />Delivery Offers</button>
          <button className="flex items-center gap-3 rounded-2xl px-4 py-3 text-left text-sm font-bold text-slate-600"><Wallet className="h-4 w-4" />Earnings</button>
          <button onClick={onExit} className="flex items-center gap-3 rounded-2xl px-4 py-3 text-left text-sm font-bold text-red-600"><LogOut className="h-4 w-4" />{t.nav.signOut}</button>
        </div>}
      </header>
      <ShipperHome offers={offers} onAccept={onAccept} onReject={onReject} />
    </main>
  );
}

