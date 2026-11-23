export type StockPickPriority = 'oldest_first' | 'newest_first'

export type StockPickZoneId = '' | 'fresh' | 'good' | 'expired'

export interface StockPickPrefs {
  pickPriority: StockPickPriority
  pickZone: StockPickZoneId
}

export const defaultStockPickPrefs = (): StockPickPrefs => ({
  pickPriority: 'oldest_first',
  pickZone: '',
})

export function stockPickApiFields(prefs: StockPickPrefs): Record<string, string> {
  const out: Record<string, string> = {
    pick_priority: prefs.pickPriority,
  }
  if (prefs.pickZone !== '') {
    out.pick_zone = prefs.pickZone
  }
  return out
}