import type { CabinetLead } from '@/stores/leads'
import type { OrderRow } from '@/stores/market'

export type Outcome = 'approved' | 'rejected' | 'delivered' | 'pending'

export type PeriodKey = 'today' | 'yesterday' | '7' | '30' | 'month'

export interface Purchase {
  lead: CabinetLead
  at: number
  model: string
  cost: number
  outcome: Outcome
}

export interface PurchaseTotals {
  leads: number
  pending: number
  approved: number
  rejected: number
  delivered: number
  spent: number
  approvalRate: number
  averageCost: number
  costPerApproved: number
}

export interface Period {
  from: number
  to: number
  previousFrom: number
  previousTo: number
  unit: 'hour' | 'day'
  title: string
  versus: string
}

export interface Bucket {
  from: number
  to: number
  label: string
  tick: string
}

export const HOUR = 3_600_000
export const DAY = 24 * HOUR

export interface OutcomeView {
  value: Outcome
  label: string
  color: string
  tone: string
  note: string
}

export const outcomes: OutcomeView[] = [
  { value: 'approved', label: 'Approved', color: '#2f9e5b', tone: 'success', note: 'Deposit confirmed by the partner' },
  { value: 'rejected', label: 'Rejected', color: '#e0607e', tone: 'error', note: 'Invalid, not interested or unreachable' },
  { value: 'delivered', label: 'Delivered', color: '#7ea6f5', tone: 'success', note: 'CPL lead delivered and paid' },
  { value: 'pending', label: 'In work', color: '#c7bffc', tone: 'primary', note: 'Waiting for the partner decision' },
]

export function outcomeView(outcome: Outcome): OutcomeView {
  return outcomes.find((item) => item.value === outcome) ?? outcomes[3]!
}

export const periodItems: { title: string, value: PeriodKey }[] = [
  { title: 'Today', value: 'today' },
  { title: 'Yesterday', value: 'yesterday' },
  { title: 'Last 7 days', value: '7' },
  { title: 'Last 30 days', value: '30' },
  { title: 'This month', value: 'month' },
]

export function purchasesOf(leads: CabinetLead[], orders: OrderRow[]): Purchase[] {
  const models = new Map(orders.map((order) => [order.id, order.paymentModel]))
  return leads
    .map((lead) => ({
      lead,
      at: Date.parse(lead.soldAt ?? lead.createdAt),
      model: lead.orderId ? models.get(lead.orderId) ?? '' : '',
      cost: (lead.partnerPriceCents ?? lead.priceCents) / 100,
      outcome: outcomeOf(lead.status),
    }))
    .sort((left, right) => left.at - right.at)
}

export function outcomeOf(status: string): Outcome {
  if (status === 'approved' || status === 'rejected' || status === 'delivered') {
    return status
  }
  return 'pending'
}

export function totalsOf(list: Purchase[]): PurchaseTotals {
  const counts = { leads: 0, pending: 0, approved: 0, rejected: 0, delivered: 0, spent: 0 }
  for (const purchase of list) {
    counts.leads += 1
    counts[purchase.outcome] += 1
    counts.spent += purchase.cost
  }
  const decided = counts.pending + counts.approved + counts.rejected
  return {
    ...counts,
    approvalRate: decided ? counts.approved / decided : 0,
    averageCost: counts.leads ? counts.spent / counts.leads : 0,
    costPerApproved: counts.approved ? counts.spent / counts.approved : 0,
  }
}

export type BreakdownGroup = 'country' | 'traffic' | 'model' | 'time'

export interface BreakdownRow extends PurchaseTotals {
  key: string
  order: string | number
  share: number
}

export function breakdownOf(list: Purchase[], group: BreakdownGroup, unit: Period['unit']): BreakdownRow[] {
  const groups = new Map<string, { order: string | number, items: Purchase[] }>()
  for (const purchase of list) {
    const [key, order] = groupKey(purchase, group, unit)
    const entry = groups.get(key) ?? { order, items: [] }
    entry.items.push(purchase)
    groups.set(key, entry)
  }
  const spent = totalsOf(list).spent
  return [...groups.entries()].map(([key, entry]) => {
    const totals = totalsOf(entry.items)
    return { key, order: entry.order, ...totals, share: spent ? totals.spent / spent : 0 }
  })
}

function groupKey(purchase: Purchase, group: BreakdownGroup, unit: Period['unit']): [string, string | number] {
  if (group === 'country') return [purchase.lead.country, purchase.lead.country]
  if (group === 'traffic') return [purchase.lead.trafficSource, purchase.lead.trafficSource]
  if (group === 'model') return [purchase.model, purchase.model]
  const start = unit === 'hour' ? Math.floor(purchase.at / HOUR) * HOUR : startOfDay(purchase.at)
  return [String(start), start]
}

export function between(list: Purchase[], from: number, to: number): Purchase[] {
  return list.filter((purchase) => purchase.at >= from && purchase.at < to)
}

export function startOfDay(at: number): number {
  const day = new Date(at)
  day.setHours(0, 0, 0, 0)
  return day.getTime()
}

export function periodOf(key: PeriodKey, now: number): Period {
  const today = startOfDay(now)
  const end = now + 1
  if (key === 'today') {
    return { from: today, to: end, previousFrom: today - DAY, previousTo: end - DAY, unit: 'hour', title: 'Today', versus: 'vs yesterday, same time' }
  }
  if (key === 'yesterday') {
    return { from: today - DAY, to: today, previousFrom: today - 2 * DAY, previousTo: today - DAY, unit: 'hour', title: 'Yesterday', versus: 'vs the day before' }
  }
  if (key === 'month') {
    const first = new Date(today)
    first.setDate(1)
    const from = first.getTime()
    return { from, to: end, previousFrom: from - (end - from), previousTo: from, unit: 'day', title: 'This month', versus: 'vs the same days before' }
  }
  const days = Number(key)
  const from = today - (days - 1) * DAY
  return { from, to: end, previousFrom: from - days * DAY, previousTo: from, unit: 'day', title: `Last ${days} days`, versus: `vs previous ${days} days` }
}

export function bucketsOf(period: Period): Bucket[] {
  const buckets: Bucket[] = []
  if (period.unit === 'hour') {
    for (let hour = 0; hour < 24; hour++) {
      const from = period.from + hour * HOUR
      if (from >= period.to) break
      buckets.push({ from, to: from + HOUR, label: `${clock(hour)}–${clock(hour + 1)}`, tick: clock(hour) })
    }
    return buckets
  }
  for (let from = period.from; from < period.to; from = nextDay(from)) {
    const day = new Date(from)
    buckets.push({
      from,
      to: nextDay(from),
      label: day.toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short' }),
      tick: day.toLocaleDateString('en-GB', { day: 'numeric', month: 'short' }),
    })
  }
  return buckets
}

export function percent(value: number): string {
  return `${(value * 100).toFixed(1)}%`
}

export function timeOf(at: number): string {
  return new Date(at).toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' })
}

export function momentOf(at: number, now: number): string {
  const today = startOfDay(now)
  if (at >= today) return timeOf(at)
  if (at >= today - DAY) return `Yesterday ${timeOf(at)}`
  return `${new Date(at).toLocaleDateString('en-GB', { day: 'numeric', month: 'short' })} ${timeOf(at)}`
}

function nextDay(at: number): number {
  const day = new Date(at)
  day.setDate(day.getDate() + 1)
  return day.getTime()
}

function clock(hour: number): string {
  return `${String(hour).padStart(2, '0')}:00`
}
