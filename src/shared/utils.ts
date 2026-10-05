export function calculateDeliveryFee(distanceKm: number, weightKg: number, express = false) {
  const base = 30000;
  const distanceFee = Math.max(distanceKm - 3, 0) * 5000;
  const weightFee = Math.max(weightKg - 2, 0) * 2500;
  const expressFee = express ? 12000 : 0;
  return Math.round((base + distanceFee + weightFee + expressFee) / 1000) * 1000;
}

export function formatVnd(value: number) {
  return new Intl.NumberFormat("vi-VN", { style: "currency", currency: "VND", maximumFractionDigits: 0 }).format(value);
}

export function createOrderId() {
  return `SOMI-${Date.now().toString().slice(-6)}`;
}
