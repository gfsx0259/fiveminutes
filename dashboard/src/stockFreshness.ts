import type { StockFreshness } from '@/stores/market'

export type StockFreshnessPercents = {
  fresh: number
  good: number
  expired: number
}

export function stockFreshnessTotal(freshness: StockFreshness | undefined): number {
  if (!freshness) {
    return 0
  }
  return freshness.fresh + freshness.good + freshness.expired
}

export function stockFreshnessPercents(freshness: StockFreshness | undefined): StockFreshnessPercents {
  const total = stockFreshnessTotal(freshness)
  if (total < 1) {
    return { fresh: 0, good: 0, expired: 0 }
  }
  const fresh = Math.round((freshness!.fresh / total) * 100)
  const good = Math.round((freshness!.good / total) * 100)
  const expired = Math.max(0, 100 - fresh - good)
  return { fresh, good, expired }
}

export function stockFreshnessTooltip(freshness: StockFreshness | undefined): string {
  if (!freshness || stockFreshnessTotal(freshness) < 1) {
    return 'No stock'
  }
  const total = stockFreshnessTotal(freshness)
  const pct = stockFreshnessPercents(freshness)
  return [
    `${total} leads on shelf`,
    `Fresh: ${freshness.fresh} (${pct.fresh}%)`,
    `Good: ${freshness.good} (${pct.good}%)`,
    `Expired: ${freshness.expired} (${pct.expired}%)`,
  ].join('\n')
}