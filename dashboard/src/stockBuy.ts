import { genderLabel, trafficLabel } from '@/marketOptions'
import type { StockLeaf } from '@/stores/market'

export type StockBuyTarget = {
  country: string
  summary: string
  leaves: StockLeaf[]
}

export type StockPlanLine = {
  leaf: StockLeaf
  volume: number
}

export function stockAvailable(leaves: StockLeaf[]): number {
  return leaves.reduce((sum, leaf) => sum + leaf.available, 0)
}

export function fillPlan(leaves: StockLeaf[], quantity: number): StockPlanLine[] {
  const plan: StockPlanLine[] = []
  let remaining = quantity
  for (const leaf of leaves) {
    if (remaining < 1) {
      break
    }
    const volume = Math.min(remaining, leaf.available)
    if (volume > 0) {
      plan.push({ leaf, volume })
      remaining -= volume
    }
  }
  return plan
}

export function stockLeafDescription(leaf: StockLeaf): string {
  const parts = [trafficLabel(leaf.trafficSource)]
  if (leaf.age) {
    parts.push(leaf.age)
  }
  parts.push(genderLabel(leaf.gender))
  return parts.join(' · ')
}

export function priceCents(leaf: StockLeaf, model: 'cpl' | 'cpa'): number {
  return model === 'cpa' ? leaf.cpaPriceCents : leaf.cplPriceCents
}

export function lineSubtotalCents(line: StockPlanLine, model: 'cpl' | 'cpa'): number {
  return line.volume * priceCents(line.leaf, model)
}

export function planSubtotalCents(plan: StockPlanLine[], model: 'cpl' | 'cpa'): number {
  return plan.reduce((sum, line) => sum + lineSubtotalCents(line, model), 0)
}

export function catalogPriceDollars(cents: number): number {
  return Math.round(cents / 100)
}