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

export function stockModalIntentToQuery(intent: StockModalIntent): Record<string, string> {
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

export function stockModalShareUrl(origin: string, pathname: string, intent: StockModalIntent): string {
  const params = new URLSearchParams(stockModalIntentToQuery(intent))
  return `${origin}${pathname}?${params.toString()}`
}