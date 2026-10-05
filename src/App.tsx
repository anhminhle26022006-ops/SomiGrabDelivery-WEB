import { useMemo, useState } from "react";
import type { DeliveryOfferData, Order } from "./shared/domain/types";
import { AuthScreen, type AuthRole } from "./features/auth/AuthScreen";
import { CustomerApp } from "./features/customer/CustomerApp";
import { ShipperApp } from "./features/shipper/ShipperApp";
import { AdminApp } from "./features/admin/AdminApp";
import { LanguageProvider } from "./shared/i18n";
import { PublicSite, type PublicPage } from "./shared/PublicSite";

type View = "landing" | "auth" | "customer" | "shipper";
type AccountRole = "customer" | "shipper" | null;

const initialOrders: Order[] = [
  { id: "SOMI-240125", pickup: "District 1", dropoff: "Binh Thanh", distanceKm: 4, weightKg: 2, fee: 35000, service: "STANDARD", status: "IN_TRANSIT", customer: "Demo Customer", shipper: "Minh", createdAt: "10:24" },
  { id: "SOMI-240126", pickup: "Phu Nhuan", dropoff: "District 3", distanceKm: 3, weightKg: 1.5, fee: 42000, service: "EXPRESS", status: "MATCHING", customer: "Demo Customer", createdAt: "10:31" },
];

const ADMIN_PATHS = new Set(["/internal/admin", "/admin"]);

export default function App() {
  return <LanguageProvider><AppContent /></LanguageProvider>;
}

/** Thin application router/orchestrator. Role UI lives in its own feature folder to reduce team conflicts. */
function AppContent() {
  const [view, setView] = useState<View>("landing");
  const [publicPage, setPublicPage] = useState<PublicPage>("home");
  const [role, setRole] = useState<AccountRole>(null);
  const [orders, setOrders] = useState<Order[]>(initialOrders);
  const signedIn = role !== null;

  const offers = useMemo<DeliveryOfferData[]>(
    () => orders
      .filter(order => order.status === "MATCHING")
      .map(order => ({ order, shipper: "Demo Shipper", etaMinutes: Math.max(8, Math.round(order.distanceKm * 4)) })),
    [orders],
  );

  if (ADMIN_PATHS.has(window.location.pathname)) {
    return <AdminApp
      orders={orders}
      onStatusChange={(id, status) => setOrders(current => current.map(order => order.id === id ? { ...order, status } : order))}
      onExit={() => { window.location.href = "/"; }}
    />;
  }

  function openAuth() { setView("auth"); }
  function handleAuth(nextRole: AuthRole) { setRole(nextRole); setView(nextRole); }
  function signOut() { setRole(null); setView("landing"); setPublicPage("home"); }
  function requireCustomer() { if (!signedIn) { openAuth(); return; } if (role === "customer") setView("customer"); }
  function requireShipper() { if (!signedIn) { openAuth(); return; } if (role === "shipper") setView("shipper"); }
  function addOrder(order: Order) { setOrders(current => [{ ...order, status: "MATCHING" }, ...current]); setView("customer"); }
  function updateStatus(id: string, status: Order["status"]) { setOrders(current => current.map(order => order.id === id ? { ...order, status } : order)); }
  function exitRole() { setView("landing"); setPublicPage("home"); }

  if (view === "landing") return <PublicSite page={publicPage} onPageChange={setPublicPage} onSendPackage={requireCustomer} onBecomeShipper={requireShipper} signedIn={signedIn} onSignIn={openAuth} onSignOut={signOut} />;
  if (view === "auth") return <AuthScreen onSuccess={handleAuth} onBack={() => setView("landing")} />;
  if (view === "customer") return <CustomerApp orders={orders} onCreate={addOrder} onExit={exitRole} />;
  return <ShipperApp offers={offers} onAccept={(id) => updateStatus(id, "ASSIGNED")} onReject={(id) => updateStatus(id, "NO_SHIPPER_FOUND")} onExit={exitRole} />;
}
