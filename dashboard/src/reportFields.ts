import { countryName } from '@/countries'
import { modelLabel, trafficLabel, verticalLabel } from '@/marketOptions'
import { dollars } from '@/money'
import { HOUR, outcomeView, percent, startOfDay, timeOf, totalsOf, type Purchase, type PurchaseTotals } from '@/purchases'

export type ReportDimension = 'team' | 'openedBy' | 'country' | 'vertical' | 'traffic' | 'model' | 'status' | 'day' | 'hour'

export interface DimensionView {
  key: ReportDimension
  label: string
  filterable: boolean
}

export const dimensions: DimensionView[] = [
  { key: 'team', label: 'Team', filterable: true },
  { key: 'openedBy', label: 'Opened by', filterable: true },
  { key: 'country', label: 'Country', filterable: true },
  { key: 'traffic', label: 'Traffic source', filterable: true },
  { key: 'vertical', label: 'Vertical', filterable: true },
  { key: 'model', label: 'Payment model', filterable: true },
  { key: 'status', label: 'Status', filterable: true },
  { key: 'day', label: 'Day', filterable: false },
  { key: 'hour', label: 'Hour of day', filterable: false },
]

export function dimensionLabel(key: ReportDimension): string {
  return dimensions.find((item) => item.key === key)?.label ?? key
}

export function valueOf(purchase: Purchase, dimension: ReportDimension): string {
  if (dimension === 'team') return purchase.team
  if (dimension === 'openedBy') return purchase.openedBy
  if (dimension === 'country') return purchase.lead.country || ''
  if (dimension === 'traffic') return purchase.lead.trafficSource || ''
  if (dimension === 'vertical') return purchase.vertical || ''
  if (dimension === 'model') return purchase.model || ''
  if (dimension === 'status') return purchase.outcome
  if (dimension === 'day') return String(startOfDay(purchase.at))
  return String(new Date(purchase.at).getHours())
}

export function nameOf(dimension: ReportDimension, value: string): string {
  if (value === '') return '—'
  if (dimension === 'team' || dimension === 'openedBy') return value
  if (dimension === 'country') return `${value} · ${countryName(value)}`
  if (dimension === 'traffic') return trafficLabel(value)
  if (dimension === 'vertical') return verticalLabel(value)
  if (dimension === 'model') return modelLabel(value)
  if (dimension === 'status') return outcomeView(value as Purchase['outcome']).label
  if (dimension === 'day') return new Date(Number(value)).toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' })
  const start = startOfDay(Date.now()) + Number(value) * HOUR
  return `${timeOf(start)}–${timeOf(start + HOUR)}`
}

function sortKey(dimension: ReportDimension, value: string): number | string {
  return dimension === 'day' || dimension === 'hour' ? Number(value) : nameOf(dimension, value)
}

export type ConditionOperator = 'in' | 'not_in'

export interface ReportCondition {
  field: ReportDimension
  operator: ConditionOperator
  values: string[]
}

export function matches(purchase: Purchase, conditions: ReportCondition[]): boolean {
  return conditions.every((condition) => {
    if (condition.values.length === 0) return true
    const hit = condition.values.includes(valueOf(purchase, condition.field))
    return condition.operator === 'in' ? hit : !hit
  })
}

export function valuesOf(purchases: Purchase[], dimension: ReportDimension): { title: string, value: string }[] {
  return [...new Set(purchases.map((purchase) => valueOf(purchase, dimension)))]
    .map((value) => ({ title: nameOf(dimension, value), value }))
    .sort((left, right) => String(sortKey(dimension, left.value)).localeCompare(String(sortKey(dimension, right.value)), undefined, { numeric: true }))
}

export interface ReportNode {
  id: string
  dimension: ReportDimension
  value: string
  name: string
  order: number | string
  totals: PurchaseTotals
  share: number
  children: ReportNode[]
}

export function reportTree(purchases: Purchase[], groups: ReportDimension[], parent = ''): ReportNode[] {
  const [dimension, ...rest] = groups
  if (!dimension) return []
  const buckets = new Map<string, Purchase[]>()
  for (const purchase of purchases) {
    const value = valueOf(purchase, dimension)
    const bucket = buckets.get(value)
    if (bucket) bucket.push(purchase)
    else buckets.set(value, [purchase])
  }
  const spent = totalsOf(purchases).spent
  return [...buckets.entries()].map(([value, list]) => {
    const id = `${parent}/${dimension}:${value}`
    const totals = totalsOf(list)
    return {
      id,
      dimension,
      value,
      name: nameOf(dimension, value),
      order: sortKey(dimension, value),
      totals,
      share: spent ? totals.spent / spent : 0,
      children: reportTree(list, rest, id),
    }
  })
}

export type MetricBlock = 'market' | 'own'

export interface MetricView {
  key: string
  label: string
  block: MetricBlock
  hint: string
  visible: boolean
  value: (totals: PurchaseTotals, share: number) => number | null
  format: (value: number) => string
}

const count = (value: number): string => value.toLocaleString('en-US')

export const blocks: { key: MetricBlock, label: string, hint: string }[] = [
  { key: 'market', label: 'Delivered by LeadMarket', hint: 'Counted by LeadMarket, does not depend on your statuses' },
  { key: 'own', label: 'Your statuses', hint: 'Comes from the statuses you set on CPA leads' },
]

export const metrics: MetricView[] = [
  { key: 'leads', label: 'Leads', block: 'market', hint: 'Leads delivered to your orders', visible: true, value: (t) => t.leads, format: count },
  { key: 'delivered', label: 'CPL leads', block: 'market', hint: 'CPL leads, paid on delivery', visible: true, value: (t) => t.delivered, format: count },
  { key: 'cpa', label: 'CPA leads', block: 'market', hint: 'CPA leads, they need your status', visible: false, value: (t) => t.leads - t.delivered, format: count },
  { key: 'spent', label: 'Spent', block: 'market', hint: 'What you paid for these leads', visible: true, value: (t) => t.spent, format: dollars },
  { key: 'averageCost', label: 'Avg cost / lead', block: 'market', hint: 'Spent ÷ leads', visible: true, value: (t) => t.averageCost, format: dollars },
  { key: 'share', label: 'Share of spend', block: 'market', hint: 'Part of the spend in the row above', visible: true, value: (_, share) => share, format: (value) => `${(value * 100).toFixed(1)}%` },
  { key: 'pending', label: 'Waiting', block: 'own', hint: 'CPA leads without your status yet', visible: true, value: (t) => t.pending, format: count },
  { key: 'approved', label: 'Approved', block: 'own', hint: 'You confirmed the deposit', visible: true, value: (t) => t.approved, format: count },
  { key: 'rejected', label: 'Rejected', block: 'own', hint: 'You marked the lead as bad', visible: true, value: (t) => t.rejected, format: count },
  { key: 'approvalRate', label: 'CR', block: 'own', hint: 'Approved ÷ CPA leads (approved, rejected and waiting)', visible: true, value: (t) => t.approvalRate, format: percent },
  { key: 'costPerApproved', label: 'Cost / approved', block: 'own', hint: 'Spent ÷ approved', visible: true, value: (t) => (t.approved ? t.costPerApproved : null), format: dollars },
]

export interface ReportPreset {
  key: string
  label: string
  groups: ReportDimension[]
  conditions?: ReportCondition[]
  columns?: string[]
}

export type PresetState = Required<Pick<ReportPreset, 'groups' | 'conditions'>> & Pick<ReportPreset, 'columns'>

export const defaultPresets: ReportPreset[] = [
  { key: 'countries', label: 'Countries', groups: ['country'] },
  { key: 'teams', label: 'Teams', groups: ['team', 'openedBy'] },
  { key: 'days', label: 'Days', groups: ['day'] },
  { key: 'sources', label: 'Traffic sources', groups: ['traffic', 'country'] },
  { key: 'verticals', label: 'Verticals', groups: ['vertical', 'country'] },
  { key: 'statuses', label: 'Statuses', groups: ['status', 'country'] },
  { key: 'country-days', label: 'Country by day', groups: ['country', 'day'] },
]

export const MAX_GROUPS = 3
