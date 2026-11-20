export type StockBuyIntent = {
  action: 'buy'
  presetId: string
  qty?: number
}

export type StockOrderIntent = {
  action: 'order'
  presetId: string
  qty?: number
  /** Bid in dollars (UI field). */
  price?: number
  /** Service day YYYY-MM-DD. */
  day?: string
}

export type StockModalIntent = StockBuyIntent | StockOrderIntent

export const STOCK_MODAL_QUERY_KEYS = ['action', 'preset', 'qty', 'price', 'day'] as const
export const STOCK_SHARE_ASSIST_KEYS = ['as', 'for'] as const

export type StockShareAssist = {
  asUserId: string
  forRole: 'partner' | 'buyer'
}

export function stockModalIntentToQuery(
  intent: StockModalIntent,
  assist?: StockShareAssist,
): Record<string, string> {
  const query: Record<string, string> = {
    action: intent.action,
    preset: intent.presetId,
  }
  if (intent.qty != null && intent.qty > 0) {
    query.qty = String(Math.floor(intent.qty))
  }
  if (intent.action === 'order') {
    if (intent.price != null && intent.price > 0) {
      query.price = String(intent.price)
    }
    if (intent.day) {
      query.day = intent.day
    }
  }
  if (assist) {
    query.as = assist.asUserId
    query.for = assist.forRole
  }
  return query
}

export function parseStockModalIntent(query: Record<string, unknown>): StockModalIntent | null {
  const action = query.action
  if (action !== 'buy' && action !== 'order') {
    return null
  }
  const preset = typeof query.preset === 'string' ? query.preset.trim() : ''
  if (preset === '') {
    return null
  }

  let qty: number | undefined
  const qtyRaw = query.qty
  if (typeof qtyRaw === 'string' && qtyRaw.trim() !== '') {
    const parsed = Number(qtyRaw)
    if (Number.isFinite(parsed) && parsed > 0) {
      qty = Math.floor(parsed)
    }
  }

  if (action === 'buy') {
    return { action: 'buy', presetId: preset, qty }
  }

  let price: number | undefined
  const priceRaw = query.price
  if (typeof priceRaw === 'string' && priceRaw.trim() !== '') {
    const parsed = Number(priceRaw)
    if (Number.isFinite(parsed) && parsed > 0) {
      price = parsed
    }
  }

  const dayRaw = query.day
  const day = typeof dayRaw === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(dayRaw) ? dayRaw : undefined

  return { action: 'order', presetId: preset, qty, price, day }
}

export function parseStockShareAssist(query: Record<string, unknown>): StockShareAssist | null {
  const asRaw = query.as
  const asUserId = typeof asRaw === 'string' ? asRaw.trim().toLowerCase() : ''
  if (!/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/.test(asUserId)) {
    return null
  }
  const forRaw = query.for
  const forRole = forRaw === 'buyer' ? 'buyer' : 'partner'

  return { asUserId, forRole }
}

export function stockModalShareUrl(
  origin: string,
  pathname: string,
  intent: StockModalIntent,
  assist?: StockShareAssist,
): string {
  const params = new URLSearchParams(stockModalIntentToQuery(intent, assist))
  return `${origin}${pathname}?${params.toString()}`
}

export function stockModalQueryWithoutAssist(query: Record<string, unknown>): Record<string, string> {
  const out: Record<string, string> = {}
  for (const key of STOCK_MODAL_QUERY_KEYS) {
    const value = query[key]
    if (typeof value === 'string' && value.trim() !== '') {
      out[key] = value
    }
  }
  return out
}