export type StockPickZoneId = '' | 'fresh' | 'good' | 'expired'

const STOCK_ZONE_DISPLAY: Record<string, string> = {
  fresh: 'Fresh',
  good: 'Aged',
  expired: 'Expired',
  live: 'Live',
}

/** User-facing zone label (API id `good` → Aged). */
export function stockZoneDisplayLabel(zoneId: string, apiLabel?: string): string {
  if (zoneId === 'good' || apiLabel === 'Good') {
    return STOCK_ZONE_DISPLAY.good
  }
  return apiLabel ?? STOCK_ZONE_DISPLAY[zoneId] ?? zoneId
}

export const defaultStockPickZone = (): StockPickZoneId => ''

/** API + orders: any zone → backend uses oldest_first; only zone filter is sent. */
export function stockPickApiFields(pickZone: StockPickZoneId): Record<string, string> {
  if (pickZone === '') {
    return {}
  }
  return { pick_zone: pickZone }
}