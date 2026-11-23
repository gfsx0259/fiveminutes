export type StockPickZoneId = '' | 'fresh' | 'good' | 'expired'

export const defaultStockPickZone = (): StockPickZoneId => ''

/** API + orders: any zone → backend uses oldest_first; only zone filter is sent. */
export function stockPickApiFields(pickZone: StockPickZoneId): Record<string, string> {
  if (pickZone === '') {
    return {}
  }
  return { pick_zone: pickZone }
}