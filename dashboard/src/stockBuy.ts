import { offerDisplayId } from '@/orderDisplay'
import type { StockOfferRow } from '@/stores/market'

export type StockBuyTarget = StockOfferRow

export function offerId(row: StockBuyTarget): string {
  return offerDisplayId(row.presetId)
}