import { motion } from "motion/react";
import { ArrowUpRight, ChevronRight, Menu, PackageCheck, Sparkles, Truck, Users, X } from "lucide-react";
import { useState } from "react";
import { HERO_VIDEO_URL } from "../config/app";

type HeroView = "customer" | "shipper";
interface HeroProps { onNavigate?: (view: HeroView) => void; signedIn?: boolean; onSignIn?: () => void; onSignOut?: () => void; }

export function Hero({ onNavigate, signedIn = false, onSignIn, onSignOut }: HeroProps) {
  const [menuOpen, setMenuOpen] = useState(false);
  const go = (view: HeroView) => { setMenuOpen(false); onNavigate?.(view); };

  return (
    <div className="w-full min-h-screen bg-[#f0f0f0] p-0 sm:p-3 md:p-5">
      <section className="relative mx-auto min-h-[100svh] sm:min-h-[calc(100vh-1.5rem)] md:min-h-[calc(100vh-2.5rem)] w-full max-w-[1536px] overflow-hidden rounded-none sm:rounded-[1.5rem] md:rounded-[3rem] bg-[#dce5e8]">
        {HERO_VIDEO_URL && (
          <video
            autoPlay
            muted
            loop
            playsInline
            preload="metadata"
            className="absolute inset-0 z-0 h-full w-full object-cover object-center"
            src={HERO_VIDEO_URL}
          />
        )}

        <div className="absolute inset-0 z-[1] bg-gradient-to-b from-white/65 via-white/38 to-[#14284b]/60" />
        <div className="absolute inset-0 z-[1] bg-[radial-gradient(circle_at_50%_30%,rgba(255,255,255,.32),transparent_38%)]" />

        <div className="relative z-10 flex min-h-[100svh] w-full flex-col">
          <nav className="relative flex w-full shrink-0 items-center justify-between px-5 py-5 md:px-10 md:py-6">
            <div className="flex-1">
              <span className="tracking-tighter text-xl text-[#14284b] md:text-2xl">SOMI</span>
              <span className="ml-2 hidden text-[9px] tracking-[.2em] text-[#14284b]/70 sm:inline">DELIVERY</span>
            </div>

            <ul className="hidden items-center gap-8 text-sm font-medium text-[#14284b] md:flex">
              <li><button onClick={() => go("customer")} className="transition hover:opacity-60">How It Works</button></li>
              <li><button onClick={() => go("customer")} className="transition hover:opacity-60">For Customers</button></li>
              <li><button onClick={() => go("shipper")} className="transition hover:opacity-60">For Shippers</button></li>
              <li><button onClick={() => go("customer")} className="transition hover:opacity-60">Pricing</button></li>
            </ul>

            <div className="flex flex-1 justify-end gap-2">
              <motion.button
                whileTap={{ scale: .98 }}
                onClick={() => signedIn ? onSignOut?.() : onSignIn?.()}
                className="hidden items-center gap-2 rounded-full bg-[#14284b] pl-2 pr-5 py-2 text-white shadow-lg shadow-[#14284b]/20 sm:flex"
              >
                <span className="rounded-full bg-white/20 p-1.5"><ArrowUpRight className="h-4 w-4" /></span>
                {signedIn ? "Sign out" : "Sign in"}
              </motion.button>

              <button
                onClick={() => setMenuOpen((v) => !v)}
                className="rounded-full bg-white/80 p-2.5 text-[#14284b] shadow-sm backdrop-blur-md md:hidden"
                aria-label="Open menu"
              >
                {menuOpen ? <X className="h-5 w-5" /> : <Menu className="h-5 w-5" />}
              </button>
            </div>

            {menuOpen && (
              <div className="absolute right-5 top-[4.6rem] z-50 w-52 rounded-3xl border border-white/70 bg-white/95 p-2 shadow-2xl backdrop-blur-xl md:hidden">
                <button onClick={() => go("customer")} className="w-full rounded-2xl px-4 py-3 text-left text-sm text-[#14284b] hover:bg-slate-100">How It Works</button>
                <button onClick={() => go("customer")} className="w-full rounded-2xl px-4 py-3 text-left text-sm text-[#14284b] hover:bg-slate-100">For Customers</button>
                <button onClick={() => go("shipper")} className="w-full rounded-2xl px-4 py-3 text-left text-sm text-[#14284b] hover:bg-slate-100">For Shippers</button>
                <button onClick={() => go("customer")} className="w-full rounded-2xl px-4 py-3 text-left text-sm text-[#14284b] hover:bg-slate-100">Pricing</button>
              </div>
            )}
          </nav>

          {/* Main hero content: normal flow on mobile so blocks never overlap. */}
          <div className="mx-auto flex w-full max-w-4xl flex-1 flex-col items-center px-5 pt-10 pb-4 text-center sm:px-6 sm:pt-8 md:pt-12">
            <motion.div
              initial={{ opacity: 0, y: 16 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ duration: .6 }}
              className="mb-4 flex shrink-0 items-center gap-2 rounded-full border border-white/70 bg-white/75 px-3.5 py-2 shadow-sm backdrop-blur-md"
            >
              <Sparkles className="h-4 w-4 text-[#14284b]" />
              <span className="text-xs font-medium text-[#14284b] sm:text-sm">Flexible Delivery Network</span>
            </motion.div>

            <motion.h1
              initial={{ opacity: 0, scale: .98 }}
              animate={{ opacity: 1, scale: 1 }}
              transition={{ duration: .8, delay: .2 }}
              className="mb-4 max-w-[390px] text-[42px] font-normal leading-[1.0] tracking-[-0.04em] text-[#102448] drop-shadow-[0_2px_10px_rgba(255,255,255,.75)] sm:max-w-none sm:text-5xl md:text-6xl lg:text-[80px]"
            >
              Deliver More.<br />Earn on Your Time.
            </motion.h1>

            <motion.p
              initial={{ opacity: 0 }}
              animate={{ opacity: 1 }}
              transition={{ duration: .8, delay: .4 }}
              className="max-w-[360px] text-[15px] font-medium leading-6 text-[#102448] drop-shadow-[0_1px_6px_rgba(255,255,255,.8)] sm:max-w-xl sm:text-base md:text-lg"
            >
              Somi connects customers with trusted part-time shippers, making every delivery simple, flexible, and transparent.
            </motion.p>

            <div className="mt-7 flex w-full max-w-[390px] shrink-0 flex-col gap-3 sm:w-auto sm:max-w-none sm:flex-row">
              <motion.button
                onClick={() => go("customer")}
                whileTap={{ scale: .98 }}
                className="w-full rounded-full bg-[#14284b] px-7 py-3.5 font-semibold text-white shadow-xl shadow-[#14284b]/25 sm:w-auto"
              >
                Send a Package
              </motion.button>

              <motion.button
                onClick={() => go("shipper")}
                whileTap={{ scale: .98 }}
                className="hidden w-full rounded-full border border-white bg-white/80 px-7 py-3.5 text-[#14284b] shadow-lg backdrop-blur-md sm:block sm:w-auto"
              >
                Become a Shipper
              </motion.button>
            </div>

            {/* Mobile cards stay in normal flow. Desktop returns to positioned cards. */}
            <div className="mt-auto w-full max-w-[460px] space-y-4 pt-5 sm:hidden">
              <motion.div
                initial={{ y: 12, opacity: 0 }}
                animate={{ y: 0, opacity: 1 }}
                transition={{ duration: .7, delay: .3 }}
                className="rounded-[28px] border border-white/70 bg-white/75 p-5 text-left shadow-xl backdrop-blur-xl"
              >
                <div className="flex items-start justify-between">
                  <div>
                    <div className="text-3xl text-[#14284b]">5.2K+</div>
                    <div className="text-[10px] uppercase tracking-wider text-[#14284b]/70">Active Shippers</div>
                  </div>
                  <div className="rounded-full bg-white/80 p-2.5">
                    <Users className="h-5 w-5 text-[#14284b]" />
                  </div>
                </div>

                <button
                  onClick={() => go("shipper")}
                  className="mt-4 flex items-center gap-2 rounded-full bg-white pl-1.5 pr-4 py-2 text-sm text-[#14284b] shadow-sm"
                >
                  <span className="rounded-full bg-[#14284b]/10 p-1"><ArrowUpRight className="h-4 w-4" /></span>
                  Become a Shipper
                </button>
              </motion.div>

              <motion.div
                initial={{ y: 12, opacity: 0 }}
                animate={{ y: 0, opacity: 1 }}
                transition={{ duration: .7, delay: .45 }}
                className="flex items-center gap-3 rounded-[28px] bg-[#f0f0f0]/95 p-4 text-left shadow-xl"
              >
                <div className="flex h-11 w-11 shrink-0 items-center justify-center rounded-full border border-[#14284b]/10 bg-[#14284b]/5">
                  <Truck className="h-5 w-5 text-[#14284b]/80" />
                </div>
                <div className="min-w-0 flex-1">
                  <div className="text-base text-[#14284b]/95">Delivery Network</div>
                  <button onClick={() => go("customer")} className="mt-1 flex items-center gap-1 text-[#14284b]/65">
                    <span className="text-[11px]">Explore how Somi works</span>
                    <ChevronRight className="h-4 w-4" />
                  </button>
                </div>
              </motion.div>
            </div>
          </div>

          {/* Desktop-only floating cards. */}
          <motion.div
            initial={{ x: -20, opacity: 0 }}
            animate={{ x: 0, opacity: 1 }}
            transition={{ duration: .8, delay: .2 }}
            className="absolute bottom-6 left-6 hidden min-w-[165px] rounded-[1.5rem] border border-white/70 bg-white/60 p-4 shadow-xl backdrop-blur-xl sm:block lg:bottom-10 lg:left-10 lg:p-5"
          >
            <div className="flex items-start justify-between">
              <div>
                <div className="text-2xl text-[#14284b] md:text-3xl">5.2K+</div>
                <div className="text-[10px] uppercase tracking-wider text-[#14284b]/70 md:text-xs">Active Shippers</div>
              </div>
              <div className="rounded-full bg-white/70 p-2"><Users className="h-4 w-4 text-[#14284b]" /></div>
            </div>
            <button onClick={() => go("shipper")} className="mt-3 flex items-center gap-2 rounded-full bg-white pl-1.5 pr-4 py-1.5 text-xs text-[#14284b] shadow-sm">
              <span className="rounded-full bg-[#14284b]/10 p-1"><ArrowUpRight className="h-4 w-4" /></span>
              Become a Shipper
            </button>
          </motion.div>

          <motion.div
            initial={{ y: 20, opacity: 0 }}
            animate={{ y: 0, opacity: 1 }}
            transition={{ duration: .8, delay: .4 }}
            className="absolute bottom-0 right-0 hidden items-center gap-3 rounded-tl-[1.5rem] bg-[#f0f0f0] p-3 pl-5 pt-5 sm:flex sm:gap-4 sm:rounded-tl-[2rem] sm:p-4 sm:pl-10 sm:pt-6 md:gap-6 md:rounded-tl-[3.5rem] md:p-6 md:pl-14 md:pt-8"
          >
            <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full border border-[#14284b]/10 bg-[#14284b]/5 md:h-14 md:w-14">
              <Truck className="text-[#14284b]/80" />
            </div>
            <div>
              <div className="text-base text-[#14284b]/95 md:text-xl">Delivery Network</div>
              <button onClick={() => go("customer")} className="flex items-center gap-1 text-[#14284b]/65">
                <span className="text-[11px] sm:text-xs md:text-[15px]">Explore how Somi works</span>
                <ChevronRight className="h-4 w-4" />
              </button>
            </div>
          </motion.div>

          <div className="absolute bottom-32 left-4 right-4 hidden rounded-2xl border border-white/70 bg-[#14284b]/90 p-3 text-white shadow-xl backdrop-blur-xl">
            <div className="flex items-center justify-between"><span className="text-xs font-medium">Fast matching</span><PackageCheck className="h-4 w-4" /></div>
            <div className="mt-1 text-[11px] text-white/70">Nearby shippers receive delivery offers.</div>
          </div>
        </div>
      </section>
    </div>
  );
}
