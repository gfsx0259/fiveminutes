import { verticalFromAffSub } from '@/orderDisplay'
import type { CabinetLead } from '@/stores/leads'
import type { OrderRow } from '@/stores/market'

export type Outcome = 'approved' | 'rejected' | 'delivered' | 'pending'

export type PeriodKey = 'today' | 'yesterday' | '7' | '30' | 'month'

export interface Purchase {
  lead: CabinetLead
  vertical: string
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
  { value: 'approved', label: 'Approved', color: '#2f9e5b', tone: 'success', note: 'You confirmed the deposit' },
  { value: 'rejected', label: 'Rejected', color: '#e0607e', tone: 'error', note: 'You marked it invalid, not interested or unreachable' },
  { value: 'delivered', label: 'Delivered', color: '#7ea6f5', tone: 'success', note: 'CPL lead, paid on delivery, no status needed' },
  { value: 'pending', label: 'Waiting for status', color: '#c7bffc', tone: 'primary', note: 'Waiting for your status' },
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

function leadVertical(
  lead: CabinetLead,
  orderById: Map<string, OrderRow>,
  presetVertical: Map<string, string>,
): string {
  const orderId = lead.orderId
  if (orderId) {
    const order = orderById.get(orderId)
    if (order) {
      const fromOrder = order.vertical || presetVertical.get(order.presetId) || ''
      if (fromOrder !== '') {
        return fromOrder
      }
    }
  }
  return verticalFromAffSub(lead.affSub)
}

export function purchasesOf(
  leads: CabinetLead[],
  orders: OrderRow[],
  presets: { id: string, vertical: string }[] = [],
): Purchase[] {
  const models = new Map(orders.map((order) => [order.id, order.paymentModel]))
  const orderById = new Map(orders.map((order) => [order.id, order]))
  const presetVertical = new Map(presets.map((preset) => [preset.id, preset.vertical]))
  return leads
    .map((lead) => ({
      lead,
      vertical: leadVertical(lead, orderById, presetVertical),
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

function dayOf(iso: string): number {
  const [year, month, day] = iso.split('-').map(Number)
  return new Date(year ?? 1970, (month ?? 1) - 1, day ?? 1).getTime()
}

export function rangeOf(fromIso: string, toIso: string): Period {
  const [first, last] = fromIso <= toIso ? [fromIso, toIso] : [toIso, fromIso]
  const from = dayOf(first)
  const to = nextDay(dayOf(last))
  const days = Math.round((to - from) / DAY)
  const label = (at: number) => new Date(at).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })
  return {
    from,
    to,
    previousFrom: from - (to - from),
    previousTo: from,
    unit: days === 1 ? 'hour' : 'day',
    title: days === 1 ? label(from) : `${label(from)} – ${label(to - 1)}`,
    versus: days === 1 ? 'vs the day before' : `vs previous ${days} days`,
  }
}

export interface PeriodChoice {
  key: PeriodKey | 'custom'
  from: string
  to: string
}

export function choicePeriodOf(choice: PeriodChoice, now: number): Period {
  return choice.key === 'custom' ? rangeOf(choice.from, choice.to) : periodOf(choice.key, now)
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
