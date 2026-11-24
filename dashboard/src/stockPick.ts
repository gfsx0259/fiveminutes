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

/** User-facing quote/API errors when a zone filter is active. */
export function formatStockQuoteError(message: string, pickZone: StockPickZoneId): string {
  const text = message.trim()
  if (/not enough leads in the .+ zone/i.test(text)) {
    return text
  }
  if (/not enough leads/i.test(text)) {
    if (pickZone !== '') {
      const zone = stockZoneDisplayLabel(pickZone)
      return `Not enough leads in the ${zone} zone. Try another zone, Any zone, or a lower quantity.`
    }
    return 'Not enough leads for this quantity.'
  }
  return text || 'Quote request failed'
}

/** API + orders: any zone → backend uses oldest_first; only zone filter is sent. */
export function stockPickApiFields(pickZone: StockPickZoneId): Record<string, string> {
  if (pickZone === '') {
    return {}
  }
  return { pick_zone: pickZone }
}