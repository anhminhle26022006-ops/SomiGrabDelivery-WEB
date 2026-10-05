import type { OrderStatus } from "./orderStatus";

export type ServiceType = "STANDARD" | "EXPRESS";

export type Order = {
  id: string;
  pickup: string;
  dropoff: string;
  distanceKm: number;
  weightKg: number;
  fee: number;
  service: ServiceType;
  status: OrderStatus;
  customer: string;
  shipper?: string;
  createdAt: string;
};

export type DeliveryOfferData = {
  order: Order;
  shipper: string;
  etaMinutes: number;
};
