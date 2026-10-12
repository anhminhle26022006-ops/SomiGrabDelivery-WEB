/**
 * SOMI frontend interactions.
 * The committed browser file is public/assets/js/app.js.
 * Compile this source with TypeScript when making changes:
 *   npx tsc resources/ts/app.ts --target ES2020 --lib DOM,ES2020 --module none --outFile public/assets/js/app.js
 *
 * No React, Vite, framework runtime, backend, or database is used.
 */
type DemoRole = "customer" | "shipper";
type DeliveryStatus = "PENDING" | "MATCHING" | "ASSIGNED" | "PICKING_UP" | "IN_TRANSIT" | "DELIVERED" | "COMPLETED" | "CANCELLED";

interface DemoOrder {
  id: string;
  pickup: string;
  dropoff: string;
  distance: number;
  weight: number;
  fee: number;
  service: "STANDARD" | "EXPRESS";
  status: DeliveryStatus;
}

const SOMI_STORAGE = {
  language: "somi-language",
  role: "somi-demo-role",
  orders: "somi-demo-orders",
} as const;

function readDemoRole(): DemoRole | null {
  const role = localStorage.getItem(SOMI_STORAGE.role);
  return role === "customer" || role === "shipper" ? role : null;
}

function readDemoOrders(): DemoOrder[] {
  const raw = localStorage.getItem(SOMI_STORAGE.orders);
  if (!raw) return [];
  try {
    const parsed: unknown = JSON.parse(raw);
    return Array.isArray(parsed) ? parsed as DemoOrder[] : [];
  } catch {
    return [];
  }
}

function saveDemoOrders(orders: DemoOrder[]): void {
  localStorage.setItem(SOMI_STORAGE.orders, JSON.stringify(orders));
}

function estimateDeliveryFee(distanceKm: number, express: boolean): number {
  const distance = Math.max(1, distanceKm);
  return 30000 + Math.max(0, distance - 3) * 5000 + (express ? 7000 : 0);
}

function escapeText(value: string): string {
  const element = document.createElement("span");
  element.textContent = value;
  return element.innerHTML;
}

document.addEventListener("DOMContentLoaded", () => {
  // app.js contains the active compiled browser implementation.
  // These typed domain helpers are kept here as the source of truth for future refactors.
  void readDemoRole;
  void readDemoOrders;
  void saveDemoOrders;
  void estimateDeliveryFee;
  void escapeText;
});
