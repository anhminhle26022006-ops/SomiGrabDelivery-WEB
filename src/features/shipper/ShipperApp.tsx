import { useState, type ReactNode } from "react";
import { Bell, CircleUserRound, Home, LogOut, Menu, Truck, Wallet, X, ClipboardList, History, UserRound, LifeBuoy, UserRoundX, Languages } from "lucide-react";
import type { DeliveryOfferData } from "../../shared/domain/types";
import { useLanguage } from "../../shared/i18n";
import { useShipperCopy } from "./i18n";
import { ShipperHome } from "./pages/ShipperHome";
import { ShipperRegistration } from "./pages/ShipperRegistration";
import { DeliveryOffers } from "./pages/DeliveryOffers";
import { OfferDetail } from "./pages/OfferDetail";
import { ActiveDelivery } from "./pages/ActiveDelivery";
import { DeliveryHistory } from "./pages/DeliveryHistory";
import { Earnings } from "./pages/Earnings";
import { Account } from "./pages/Account";
import { Chat } from "./pages/Chat";
import { SupportCenter } from "./pages/SupportCenter";
import { CancelAccount } from "./pages/CancelAccount";
import { Withdraw } from "./pages/Withdraw";

interface ShipperAppProps { offers: DeliveryOfferData[]; onAccept: (id: string) => void; onReject: (id: string) => void; onExit: () => void; }
type View = "dashboard" | "offers" | "detail" | "active" | "history" | "earnings" | "account" | "registration" | "chat" | "support" | "cancelAccount" | "withdraw";

export function ShipperApp({ offers, onAccept, onReject, onExit }: ShipperAppProps) {
  const { lang } = useLanguage();
  const c = useShipperCopy(lang);
  const [mobileMenu, setMobileMenu] = useState(false);
  const [accountMenu, setAccountMenu] = useState(false);
  const [view, setView] = useState<View>("dashboard");
  const [selectedOfferId, setSelectedOfferId] = useState<string>();
  const selectedOffer = offers.find(offer => offer.order.id === selectedOfferId);
  const go = (next: View) => { setView(next); setMobileMenu(false); setAccountMenu(false); };
  const viewOffer = (id: string) => { setSelectedOfferId(id); go("detail"); };
  const accept = (id: string) => { onAccept(id); go("active"); };
  const nav = [
    ["dashboard", Home, c.nav.dashboard], ["offers", Truck, c.nav.offers], ["active", ClipboardList, c.nav.active], ["history", History, c.nav.history], ["earnings", Wallet, c.nav.earnings], ["account", CircleUserRound, c.nav.account],
  ] as const;
  return <main className="min-h-screen bg-[#f0f0f0] pb-20 md:pb-0">
    <header className="sticky top-0 z-40 border-b border-white/70 bg-[#f0f0f0]/90 px-4 py-3 backdrop-blur-xl md:px-6 md:py-4">
      <div className="mx-auto flex w-full max-w-[1800px] items-center gap-3 sm:gap-4">
        <button onClick={()=>go("dashboard")} className="flex shrink-0 items-center gap-2 text-[#14284b]">
          <span className="text-xl font-semibold tracking-[-.04em] md:text-2xl">SOMI</span><span className="hidden text-[8px] tracking-[.2em] text-[#14284b]/50 sm:inline">DELIVERY</span>
        </button>
        <nav aria-label="Shipper navigation" className="hidden min-w-0 flex-1 items-center justify-center gap-1 bg-transparent md:flex">
          {nav.slice(0,5).map(([id,Icon,label])=><NavButton key={id} active={view===id} onClick={()=>go(id)} icon={<Icon className="h-4 w-4 shrink-0"/>}>{label}</NavButton>)}
        </nav>
        <div className="flex shrink-0 items-center gap-2">
          <button className="hidden h-10 w-10 items-center justify-center rounded-full bg-white text-[#14284b] shadow-sm sm:flex" aria-label={c.nav.notifications}><Bell className="h-4 w-4"/></button>
          <div className="relative block">
            <button onClick={()=>setAccountMenu(value=>!value)} aria-label={c.nav.account} aria-expanded={accountMenu} className="flex h-10 w-10 items-center justify-center rounded-full bg-white text-[#14284b] shadow-sm transition hover:bg-slate-50"><CircleUserRound className="h-5 w-5"/></button>
            {accountMenu && <div className="absolute right-0 top-12 z-50 w-[min(15rem,calc(100vw-2rem))] overflow-hidden rounded-2xl border border-slate-100 bg-white p-2 shadow-xl">
              <div className="flex items-center gap-3 border-b border-slate-100 px-3 py-3"><span className="flex h-10 w-10 items-center justify-center rounded-full bg-[#14284b]/10"><CircleUserRound className="h-5 w-5 text-[#14284b]"/></span><span><strong className="block text-sm text-[#14284b]">Nguyễn Minh</strong><small className="text-xs text-slate-500">{lang === "vi" ? "Tài khoản Shipper" : "Shipper account"}</small></span></div>
              <MenuItem icon={<UserRound className="h-4 w-4"/>} onClick={()=>go("account")}>{lang === "vi" ? "Thông tin cá nhân" : "Personal information"}</MenuItem>
              <MenuItem icon={<Truck className="h-4 w-4"/>} onClick={()=>go("account")}>{lang === "vi" ? "Thông tin phương tiện" : "Vehicle information"}</MenuItem>
              <MenuItem icon={<Bell className="h-4 w-4"/>} onClick={()=>go("account")}>{lang === "vi" ? "Thông báo" : "Notifications"}</MenuItem>
              <MenuItem icon={<Languages className="h-4 w-4"/>} onClick={()=>go("account")}>{lang === "vi" ? "Ngôn ngữ" : "Language"}</MenuItem>
              <MenuItem icon={<LifeBuoy className="h-4 w-4"/>} onClick={()=>go("support")}>{lang === "vi" ? "Trung tâm hỗ trợ" : "Support Center"}</MenuItem>
              <MenuItem icon={<UserRoundX className="h-4 w-4"/>} onClick={()=>go("cancelAccount")}>{lang === "vi" ? "Hủy tài khoản" : "Cancel Account"}</MenuItem>
              <div className="my-1 border-t border-slate-100"/><MenuItem danger icon={<LogOut className="h-4 w-4"/>} onClick={onExit}>{c.nav.signOut}</MenuItem>
            </div>}
          </div>
          <button onClick={()=>setMobileMenu(value=>!value)} className="flex h-10 w-10 items-center justify-center rounded-full bg-white text-[#14284b] shadow-sm md:hidden" aria-label={mobileMenu?c.nav.closeMenu:c.nav.openMenu}>{mobileMenu?<X className="h-5 w-5"/>:<Menu className="h-5 w-5"/>}</button>
        </div>
      </div>
      {mobileMenu&&<div className="mx-auto mt-3 grid max-w-7xl gap-2 rounded-3xl border border-white/80 bg-white/95 p-3 shadow-xl md:hidden">
        {nav.map(([id,Icon,label])=><NavButton key={id} active={view===id} onClick={()=>go(id)} icon={<Icon className="h-4 w-4"/>}>{label}</NavButton>)}
        <NavButton active={view==="registration"} onClick={()=>go("registration")} icon={<ClipboardList className="h-4 w-4"/>}>{c.nav.registration}</NavButton>
        <button onClick={()=>go("account")} className="flex items-center gap-3 rounded-2xl px-4 py-3 text-left text-sm font-semibold text-[#14284b]"><CircleUserRound className="h-4 w-4"/>{c.nav.account}</button>
        <button onClick={()=>go("support")} className="flex items-center gap-3 rounded-2xl px-4 py-3 text-left text-sm font-semibold text-[#14284b]"><LifeBuoy className="h-4 w-4"/>{lang === "vi" ? "Trung tâm hỗ trợ" : "Support Center"}</button>
        <button onClick={()=>go("cancelAccount")} className="flex items-center gap-3 rounded-2xl px-4 py-3 text-left text-sm font-semibold text-red-600"><UserRoundX className="h-4 w-4"/>{lang === "vi" ? "Hủy tài khoản" : "Cancel Account"}</button>
        <button onClick={onExit} className="flex items-center gap-3 rounded-2xl px-4 py-3 text-left text-sm font-bold text-red-600"><LogOut className="h-4 w-4"/>{c.nav.signOut}</button>
      </div>}
    </header>
    {view === "dashboard" && <ShipperHome lang={lang} offers={offers} onAccept={accept} onReject={onReject} onViewDetails={viewOffer}/>} 
    {view === "offers" && <DeliveryOffers lang={lang} offers={offers} onView={viewOffer}/>} 
    {view === "detail" && <OfferDetail lang={lang} offer={selectedOffer} onBack={()=>go("offers")} onAccept={accept} onReject={onReject}/>} 
    {view === "active" && <ActiveDelivery lang={lang} onOffers={()=>go("offers")} onChat={()=>go("chat")}/>} 
    {view === "history" && <DeliveryHistory lang={lang}/>} 
    {view === "earnings" && <Earnings lang={lang} onWithdraw={()=>go("withdraw")}/>} 
    {view === "account" && <Account lang={lang} onRegistration={()=>go("registration")} onExit={onExit} onSupport={()=>go("support")} onCancelAccount={()=>go("cancelAccount")}/>} 
    {view === "registration" && <ShipperRegistration lang={lang} onFinish={()=>go("dashboard")}/>}
    {view === "chat" && <Chat lang={lang} onBack={()=>go("active")}/>}
    {view === "support" && <SupportCenter lang={lang} onChat={()=>go("chat")}/>}
    {view === "cancelAccount" && <CancelAccount lang={lang} onBack={()=>go("account")}/>}
    {view === "withdraw" && <Withdraw lang={lang} onBack={()=>go("earnings")}/>} 
  </main>;
}
function NavButton({active,onClick,icon,children}:{active:boolean;onClick:()=>void;icon:ReactNode;children:ReactNode}){return <button onClick={onClick} className={`flex items-center gap-2 whitespace-nowrap rounded-full px-3 py-2 text-xs font-bold transition ${active?'bg-[#14284b] text-white':'text-slate-600 hover:bg-slate-50'}`}>{icon}{children}</button>}
function MenuItem({onClick,icon,children,danger=false}:{onClick:()=>void;icon:ReactNode;children:ReactNode;danger?:boolean}){return <button onClick={onClick} className={`flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left text-sm transition hover:bg-slate-50 ${danger?'text-red-600':'text-slate-700'}`}>{icon}<span className="flex-1">{children}</span></button>}
