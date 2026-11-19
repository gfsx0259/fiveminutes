import type { StockOfferRow } from '@/stores/market'

export type StockBuyTarget = StockOfferRow

export function offerId(row: StockBuyTarget): string {
  const tail = row.presetId.replace(/-/g, '').slice(0, 5).toUpperCase()
  return `OF-${tail}`
}