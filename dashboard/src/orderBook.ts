import type { OrderRow } from '@/stores/market'

export function openOrdersOf(orders: OrderRow[]): OrderRow[] {
  return orders.filter((order) => !order.stock && order.status !== 'finished')
}

export function leadsLeft(order: OrderRow): number {
  return Math.max(0, order.volume - order.supplied)
}

export function reservedOf(orders: OrderRow[]): number {
  return openOrdersOf(orders).reduce((sum, order) => sum + leadsLeft(order) * order.partnerPriceCents / 100, 0)
}

export function localDay(at: number): string {
  const day = new Date(at)
  const month = String(day.getMonth() + 1).padStart(2, '0')
  return `${day.getFullYear()}-${month}-${String(day.getDate()).padStart(2, '0')}`
}

export function isScheduled(order: OrderRow, now: number): boolean {
  return order.serviceOn !== null && order.serviceOn > localDay(now)
}
