import {
  parseStockShareAssist,
  type StockShareAssist,
  STOCK_SHARE_ASSIST_KEYS,
} from '@/stockModalIntent'

export const TABLE_VIEW_MARK = 'view' as const

const LIST_SEP = ','

export function joinList(values: string[]): string | undefined {
  const trimmed = values.map((v) => v.trim()).filter((v) => v !== '')
  if (trimmed.length === 0) {
    return undefined
  }
  return trimmed.join(LIST_SEP)
}

export function splitList(raw: unknown): string[] {
  if (typeof raw !== 'string' || raw.trim() === '') {
    return []
  }
  return raw.split(LIST_SEP).map((part) => part.trim()).filter((part) => part !== '')
}

function put(out: Record<string, string>, key: string, value: string | undefined): void {
  if (value != null && value !== '') {
    out[key] = value
  }
}

function readString(raw: unknown): string {
  return typeof raw === 'string' ? raw.trim() : ''
}

export function tableViewShareAssist(user: { id: string; role: string } | null | undefined): StockShareAssist | undefined {
  if (!user || user.role !== 'partner') {
    return undefined
  }
  return { asUserId: user.id, forRole: 'partner' }
}

export function queryWithoutShareAssist(query: Record<string, unknown>): Record<string, string> {
  const out: Record<string, string> = {}
  for (const [key, value] of Object.entries(query)) {
    if (STOCK_SHARE_ASSIST_KEYS.includes(key as (typeof STOCK_SHARE_ASSIST_KEYS)[number])) {
      continue
    }
    if (typeof value === 'string' && value.trim() !== '') {
      out[key] = value
    }
  }
  return out
}

export function isTableViewShareQuery(query: Record<string, unknown>): boolean {
  return query[TABLE_VIEW_MARK] === '1'
}

export function buildTableShareUrl(
  origin: string,
  pathname: string,
  filterQuery: Record<string, string>,
  assist?: StockShareAssist,
): string {
  const query: Record<string, string> = {
    [TABLE_VIEW_MARK]: '1',
    ...filterQuery,
  }
  if (assist) {
    query.as = assist.asUserId
    query.for = assist.forRole
  }
  const params = new URLSearchParams(query)
  return `${origin}${pathname}?${params.toString()}`
}

/** Buy leads (/stock) */
export type StockTableShareState = {
  tab: 'stock' | 'orders'
  verticals: string[]
  countries: string[]
  traffics: string[]
  model: string | null
  cr: string | null
  availability: string | null
}

export function stockTableShareToQuery(state: StockTableShareState): Record<string, string> {
  const out: Record<string, string> = {}
  if (state.tab === 'stock') {
    out.tab = 'stock'
  }
  put(out, 'fv', joinList(state.verticals))
  put(out, 'fc', joinList(state.countries))
  put(out, 'ft', joinList(state.traffics))
  put(out, 'fm', state.model ?? undefined)
  put(out, 'fcr', state.cr ?? undefined)
  put(out, 'fa', state.availability ?? undefined)
  return out
}

function queryLists(query: Record<string, unknown>, keys: readonly string[]): boolean {
  return keys.some((key) => {
    const value = query[key]
    return typeof value === 'string' && value.trim() !== ''
  })
}

export function assistQuery(
  user: { id: string; role: string } | null | undefined,
): Record<string, string> {
  const assist = tableViewShareAssist(user)
  if (!assist) {
    return {}
  }
  return { as: assist.asUserId, for: assist.forRole }
}

export function applyStockTableShareFromQuery(
  query: Record<string, unknown>,
  target: StockTableShareState,
): boolean {
  if (!isTableViewShareQuery(query) && !queryLists(query, ['tab', 'fv', 'fc', 'ft', 'fm', 'fcr', 'fa'])) {
    return false
  }
  const tab = readString(query.tab)
  target.tab = tab === 'stock' ? 'stock' : 'orders'
  target.verticals = splitList(query.fv)
  target.countries = splitList(query.fc)
  target.traffics = splitList(query.ft)
  target.model = readString(query.fm) || null
  target.cr = readString(query.fcr) || null
  target.availability = readString(query.fa) || null
  return true
}

export const STOCK_TABLE_SHARE_QUERY_KEYS = [
  TABLE_VIEW_MARK,
  'tab',
  'fv',
  'fc',
  'ft',
  'fm',
  'fcr',
  'fa',
] as const

/** My orders (/orders) */
export type OrdersTableShareState = {
  orderId: string
  scope: 'active' | 'past' | 'all'
  verticals: string[]
  countries: string[]
  traffics: string[]
  paymentModel: string | null
  orderType: 'live' | 'stock' | null
  serviceOn: string
}

export function ordersTableShareToQuery(state: OrdersTableShareState): Record<string, string> {
  const out: Record<string, string> = {}
  put(out, 'oid', state.orderId.trim() || undefined)
  if (state.scope !== 'active') {
    out.scope = state.scope
  }
  put(out, 'fv', joinList(state.verticals))
  put(out, 'fc', joinList(state.countries))
  put(out, 'ft', joinList(state.traffics))
  put(out, 'fpm', state.paymentModel ?? undefined)
  put(out, 'otyp', state.orderType ?? undefined)
  put(out, 'svc', state.serviceOn.trim() || undefined)
  return out
}

export function applyOrdersTableShareFromQuery(
  query: Record<string, unknown>,
  target: OrdersTableShareState,
): boolean {
  if (!isTableViewShareQuery(query) && !queryLists(query, ['oid', 'scope', 'fv', 'fc', 'ft', 'fpm', 'otyp', 'svc'])) {
    return false
  }
  target.orderId = readString(query.oid)
  const scope = readString(query.scope)
  target.scope = scope === 'past' || scope === 'all' ? scope : 'active'
  target.verticals = splitList(query.fv)
  target.countries = splitList(query.fc)
  target.traffics = splitList(query.ft)
  target.paymentModel = readString(query.fpm) || null
  const otyp = readString(query.otyp)
  target.orderType = otyp === 'live' || otyp === 'stock' ? otyp : null
  target.serviceOn = readString(query.svc)
  return true
}

export const ORDERS_TABLE_SHARE_QUERY_KEYS = [
  TABLE_VIEW_MARK,
  'oid',
  'scope',
  'fv',
  'fc',
  'ft',
  'fpm',
  'otyp',
  'svc',
] as const

/** My leads (/leads) */
export type LeadsTableShareState = {
  orderFilter: string
  leadId: string
  verticals: string[]
  countries: string[]
  traffics: string[]
  phone: string
  email: string
  clientStatus: string
  deposit: 'yes' | 'no' | null
  gender: string | null
  age: string
}

export function leadsTableShareToQuery(state: LeadsTableShareState): Record<string, string> {
  const out: Record<string, string> = {}
  put(out, 'order', state.orderFilter.trim() || undefined)
  put(out, 'lid', state.leadId.trim() || undefined)
  put(out, 'fv', joinList(state.verticals))
  put(out, 'fc', joinList(state.countries))
  put(out, 'ft', joinList(state.traffics))
  put(out, 'fph', state.phone.trim() || undefined)
  put(out, 'fem', state.email.trim() || undefined)
  put(out, 'fcs', state.clientStatus.trim() || undefined)
  put(out, 'fdp', state.deposit ?? undefined)
  put(out, 'fgen', state.gender ?? undefined)
  put(out, 'fage', state.age.trim() || undefined)
  return out
}

export function applyLeadsTableShareFromQuery(
  query: Record<string, unknown>,
  target: LeadsTableShareState,
): boolean {
  if (!isTableViewShareQuery(query) && !queryLists(query, ['order', 'lid', 'fv', 'fc', 'ft', 'fph', 'fem', 'fcs', 'fdp', 'fgen', 'fage'])) {
    return false
  }
  target.orderFilter = readString(query.order)
  target.leadId = readString(query.lid)
  target.verticals = splitList(query.fv)
  target.countries = splitList(query.fc)
  target.traffics = splitList(query.ft)
  target.phone = readString(query.fph)
  target.email = readString(query.fem)
  target.clientStatus = readString(query.fcs)
  const dep = readString(query.fdp)
  target.deposit = dep === 'yes' || dep === 'no' ? dep : null
  target.gender = readString(query.fgen) || null
  target.age = readString(query.fage)
  return true
}

export const LEADS_TABLE_SHARE_QUERY_KEYS = [
  TABLE_VIEW_MARK,
  'order',
  'lid',
  'fv',
  'fc',
  'ft',
  'fph',
  'fem',
  'fcs',
  'fdp',
  'fgen',
  'fage',
] as const

export function assistPresent(query: Record<string, unknown>): boolean {
  return parseStockShareAssist(query) != null
}

/** Wait for admin impersonation before reading shared table filters from the URL. */
export function shouldDeferTableShareAssist(
  user: { id: string; role: string } | null | undefined,
  query: Record<string, unknown>,
): boolean {
  const assist = parseStockShareAssist(query)
  if (!assist) {
    return false
  }
  if (!user) {
    return true
  }
  if (user.id === assist.asUserId && user.role === assist.forRole) {
    return false
  }
  return user.role === 'admin'
}