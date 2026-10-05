import { motion } from "motion/react";
import { ArrowRight, ArrowUpRight, Check, CreditCard, MapPin, Menu, Package, RotateCcw, Sparkles, Star, Truck, Users, X } from "lucide-react";
import { useState } from "react";
import { HERO_VIDEO_URL } from "./config/app";
import { useLanguage } from "./i18n";
import { LanguageSwitcher } from "./components/LanguageSwitcher";
import { LegalModal } from "../features/auth/LegalModal";

export type PublicPage = "home" | "how" | "customers" | "shippers" | "pricing";
type LegalDocument = "terms" | "privacy";
interface PublicSiteProps { page: PublicPage; onPageChange: (page: PublicPage) => void; onSendPackage: () => void; onBecomeShipper: () => void; signedIn: boolean; onSignIn: () => void; onSignOut: () => void; }

const icons = {
  how: [Package, CreditCard, MapPin],
  customers: [Package, CreditCard, Users],
  shippers: [Check, CreditCard, Star],
  pricing: [Package, MapPin, RotateCcw],
};

export function PublicSite({ page, onPageChange, onSendPackage, onBecomeShipper, signedIn, onSignIn, onSignOut }: PublicSiteProps) {
  const { t, lang } = useLanguage();
  const [menuOpen, setMenuOpen] = useState(false);
  const [legalDocument, setLegalDocument] = useState<LegalDocument | null>(null);
  const nav = [
    ["how", t.nav.how], ["customers", t.nav.customers], ["shippers", t.nav.shippers], ["pricing", t.nav.pricing],
  ] as const;
  const changePage = (next: PublicPage) => { setMenuOpen(false); onPageChange(next); window.scrollTo({ top: 0, behavior: "smooth" }); };

  return <main className="relative min-h-screen overflow-x-clip bg-[#f0f0f0] text-[#14284b]">
    <header className={page === "home" ? "absolute inset-x-0 top-0 z-50 border-b border-transparent bg-transparent" : "sticky top-0 z-50 border-b border-slate-200/80 bg-[#f0f0f0]/90 backdrop-blur-xl"}>
      <div className="mx-auto flex h-16 max-w-[1536px] items-center justify-between px-5 md:h-[76px] md:px-10">
        <button onClick={() => changePage("home")} className="flex items-baseline gap-2 font-semibold text-[#14284b]">
          <span className="text-xl tracking-[-.06em] md:text-2xl">SOMI</span><span className="text-[8px] tracking-[.22em] text-[#14284b]/55">DELIVERY</span>
        </button>
        <nav className="hidden items-center gap-7 md:flex">
          {nav.map(([key, label]) => <button key={key} onClick={() => changePage(key)} className={`text-[14px] font-bold tracking-[-.01em] transition hover:opacity-60 ${page === key ? "text-[#14284b]" : "text-[#14284b]/90"}`}>{label}</button>)}
        </nav>
        <div className="flex items-center gap-2">
          <div className="hidden sm:block"><LanguageSwitcher /></div>
          <button onClick={signedIn ? onSignOut : onSignIn} className="hidden items-center gap-2 rounded-full bg-[#14284b] px-4 py-2.5 text-xs font-bold text-white shadow-lg shadow-[#14284b]/15 sm:flex"><ArrowUpRight className="h-3.5 w-3.5" />{signedIn ? t.nav.signOut : t.nav.signIn}</button>
          <button onClick={() => setMenuOpen(v => !v)} className="rounded-full bg-white p-2.5 shadow-sm md:hidden" aria-label="Open menu">{menuOpen ? <X className="h-5 w-5"/> : <Menu className="h-5 w-5"/>}</button>
        </div>
      </div>
      {menuOpen && <div className="mx-4 mb-3 rounded-3xl border border-slate-200 bg-white p-2 shadow-xl md:hidden">
        {nav.map(([key,label]) => <button key={key} onClick={() => changePage(key)} className="block w-full rounded-2xl px-4 py-3 text-left text-sm font-bold">{label}</button>)}
        <div className="my-1 flex items-center justify-between rounded-2xl bg-[#f0f0f0] p-2">
          <span className="px-2 text-xs font-medium text-slate-500">{t.nav.language}</span>
          <LanguageSwitcher />
        </div>
        <button onClick={signedIn ? onSignOut : onSignIn} className="mt-1 flex w-full items-center justify-center gap-2 rounded-2xl bg-[#14284b] px-4 py-3 text-sm text-white"><ArrowUpRight className="h-4 w-4"/>{signedIn ? t.nav.signOut : t.nav.signIn}</button>
      </div>}
    </header>

    {page === "home" ? <HomeContent onSendPackage={onSendPackage} onBecomeShipper={onBecomeShipper} onHowItWorks={() => changePage("how")} /> : <InfoContent page={page} onSendPackage={onSendPackage} onBecomeShipper={onBecomeShipper} />}
    <footer className="border-t border-slate-200 bg-white px-5 py-7 md:px-10">
      <div className="mx-auto flex max-w-[1536px] flex-col gap-4 text-xs text-slate-500 sm:flex-row sm:items-center sm:justify-between">
        <span>© 2026 Somi Delivery</span>
        <div className="flex flex-wrap items-center gap-x-5 gap-y-2">
          <button type="button" onClick={() => setLegalDocument("terms")} className="font-semibold transition hover:text-[#14284b]">{lang === "vi" ? "Điều khoản sử dụng" : "Terms of Service"}</button>
          <button type="button" onClick={() => setLegalDocument("privacy")} className="font-semibold transition hover:text-[#14284b]">{lang === "vi" ? "Chính sách bảo mật" : "Privacy Policy"}</button>
        </div>
      </div>
    </footer>
    {legalDocument && <LegalModal document={legalDocument} onClose={() => setLegalDocument(null)} />}
  </main>;
}

function HomeContent({ onSendPackage, onBecomeShipper, onHowItWorks }: { onSendPackage: () => void; onBecomeShipper: () => void; onHowItWorks: () => void }) {
  const { t } = useLanguage();
  return <>
    <section className="relative mx-auto min-h-[calc(100svh-4rem)] max-w-[1536px] overflow-hidden bg-[#dce5e8] md:min-h-[calc(100vh-4rem)]">
      {HERO_VIDEO_URL && <video autoPlay muted loop playsInline preload="metadata" className="absolute inset-0 h-full w-full object-cover" src={HERO_VIDEO_URL}/>} 
      <div className="absolute inset-0 bg-gradient-to-b from-white/65 via-white/38 to-[#14284b]/60"/>
      <div className="relative z-10 mx-auto flex min-h-[100svh] max-w-4xl flex-col items-center px-5 pt-20 text-center md:pt-24">
        <div className="mb-4 flex items-center gap-2 rounded-full border border-white/70 bg-white/75 px-3.5 py-2 shadow-sm backdrop-blur-md"><Sparkles className="h-4 w-4"/><span className="text-xs font-medium sm:text-sm">{t.hero.badge}</span></div>
        <h1 className="mb-5 text-[44px] font-normal leading-[.98] tracking-[-.05em] sm:text-6xl lg:text-[80px]">{t.hero.title1}<br/>{t.hero.title2}</h1>
        <p className="max-w-2xl text-sm leading-6 text-[#14284b]/75 sm:text-base md:text-lg">{t.hero.description}</p>
        <div className="mt-8 flex flex-col gap-3 sm:flex-row"><button onClick={onSendPackage} className="rounded-full bg-[#14284b] px-7 py-3.5 text-sm font-semibold text-white shadow-xl">{t.nav.send}</button><button onClick={onBecomeShipper} className="rounded-full bg-white/85 px-7 py-3.5 text-sm text-[#14284b] shadow-lg">{t.nav.become}</button></div>
        <div className="mt-auto grid w-full gap-4 pb-8 pt-10 text-left sm:grid-cols-2">
          <div className="rounded-[28px] border border-white/70 bg-white/75 p-5 shadow-xl backdrop-blur-xl"><div className="flex items-start justify-between"><div><div className="text-3xl">5.2K+</div><div className="text-[10px] uppercase tracking-wider text-[#14284b]/60">{t.hero.active}</div></div><Users className="h-5 w-5"/></div><button onClick={onBecomeShipper} className="mt-4 flex items-center gap-2 rounded-full bg-white px-4 py-2 text-sm shadow-sm"><ArrowRight className="h-4 w-4"/>{t.nav.become}</button></div>
          <div className="flex items-center gap-4 rounded-[28px] bg-[#f0f0f0]/95 p-5 shadow-xl"><div className="flex h-12 w-12 items-center justify-center rounded-full bg-[#14284b]/5"><Truck/></div><div><div className="text-lg">{t.hero.network}</div><button onClick={onHowItWorks} className="mt-1 flex items-center gap-1 text-sm font-semibold text-[#14284b]/70 transition hover:text-[#14284b]">{t.hero.explore}<ArrowRight className="h-4 w-4"/></button></div></div>
        </div>
      </div>
    </section>
  </>;
}

function InfoContent({ page, onSendPackage, onBecomeShipper }: { page: Exclude<PublicPage,"home">; onSendPackage: () => void; onBecomeShipper: () => void }) {
  const { t } = useLanguage();
  const content = t.pages[page];
  const pageIcons = icons[page];
  return <section className="mx-auto max-w-[1536px] px-4 py-12 sm:px-6 sm:py-16 md:px-10 md:py-20 lg:px-12">
    <div className="mx-auto max-w-5xl text-center"><p className="text-[9px] font-medium tracking-[.2em] text-[#14284b]/55 sm:text-[10px]">{content.label}</p><h1 className="mx-auto mt-4 max-w-4xl text-[2.15rem] font-normal leading-[1.02] tracking-[-.055em] sm:text-5xl md:text-6xl lg:text-[4rem]">{content.title}</h1><p className="mx-auto mt-5 max-w-2xl text-sm leading-6 text-[#14284b]/65 sm:text-base">{content.desc}</p></div>
    <div className="mx-auto mt-10 grid max-w-5xl gap-3 sm:mt-12 md:grid-cols-3">
      {content.cards.map(([title,text], index) => { const Icon = pageIcons[index]; return <motion.article key={title} initial={{opacity:0,y:12}} animate={{opacity:1,y:0}} transition={{delay:index*.08}} className="min-h-[250px] rounded-[1.6rem] border border-slate-200 bg-white p-6 sm:p-7 md:min-h-[250px]"><div className="flex items-start justify-between"><div className="flex h-11 w-11 items-center justify-center rounded-2xl bg-[#edf2f8]"><Icon className="h-5 w-5"/></div><span className="text-xs text-[#14284b]/45">0{index+1}</span></div><div className="mt-14 sm:mt-16"><h2 className="text-lg sm:text-xl">{title}</h2><p className="mt-2 text-sm leading-6 text-[#14284b]/60">{text}</p></div></motion.article>; })}
    </div>
    <div className="mx-auto mt-10 flex max-w-5xl flex-col sm:mt-14 items-center justify-between gap-6 rounded-[1.6rem] bg-[#0f2f63] px-7 py-8 text-white sm:flex-row sm:px-9"><h2 className="text-2xl tracking-[-.03em] sm:text-3xl">{content.cta}</h2><button onClick={page === "shippers" ? onBecomeShipper : onSendPackage} className="flex items-center gap-2 rounded-full bg-white px-6 py-3 text-sm font-medium text-[#14284b]">{page === "shippers" ? t.nav.become : t.nav.send}<ArrowRight className="h-4 w-4"/></button></div>
  </section>;
}
