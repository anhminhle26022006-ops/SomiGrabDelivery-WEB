import type { Order } from "../../shared/domain/types";
import { AdminDashboard } from "./pages/AdminDashboard";

interface AdminAppProps {
  orders: Order[];
  onStatusChange: (id: string, status: Order["status"]) => void;
  onExit: () => void;
}

/** Admin-only application entry point. Keep Admin UI changes inside features/admin. */
export function AdminApp({ orders, onStatusChange, onExit }: AdminAppProps) {
  return <AdminDashboard orders={orders} onStatusChange={onStatusChange} onExit={onExit} />;
}
