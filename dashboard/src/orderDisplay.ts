export function orderDisplayId(id: string): string {
  const tail = id.replace(/-/g, '').slice(0, 5).toUpperCase()
  return `OR-${tail}`
}

function matchesShortId(display: string, prefix: string, rawId: string, query: string): boolean {
  const q = query.trim().toLowerCase()
  if (q === '') {
    return true
  }
  const needle = display.toLowerCase()
  if (needle.includes(q) || rawId.toLowerCase().includes(q)) {
    return true
  }
  const compact = q.replace(new RegExp(`^${prefix}-`), '')
  if (compact !== q && needle.replace(new RegExp(`^${prefix}-`), '').includes(compact)) {
    return true
  }
  return false
}

/** Client-side filter: short id (OR-…), prefix without OR-, or substring of full uuid. */
export function matchesOrderDisplayId(orderId: string, query: string): boolean {
  if (orderId === '') {
    return query.trim() === ''
  }
  return matchesShortId(orderDisplayId(orderId), 'or', orderId, query)
}

export function leadDisplayId(uuid: string): string {
  const tail = uuid.replace(/-/g, '').slice(0, 5).toUpperCase()
  return `LD-${tail}`
}

/** Client-side filter: short id (LD-…), prefix without LD-, or substring of lead uuid. */
export function matchesLeadDisplayId(leadUuid: string, query: string): boolean {
  return matchesShortId(leadDisplayId(leadUuid), 'ld', leadUuid, query)
}

export function presetDisplayId(id: string): string {
  const tail = id.replace(/-/g, '').slice(0, 5).toUpperCase()
  return `PR-${tail}`
}

/** Partner-facing preset label in Buy leads (OF-…). */
export function offerDisplayId(presetId: string): string {
  const tail = presetId.replace(/-/g, '').slice(0, 5).toUpperCase()
  return `OF-${tail}`
}

/** Demo and mined leads often encode vertical in aff sub, e.g. aff-gambling. */
export function verticalFromAffSub(affSub: string): string {
  const trimmed = affSub.trim()
  if (trimmed.startsWith('aff-')) {
    return trimmed.slice(4)
  }
  return ''
}

/** Live orders use service day; stock orders use the day the order was opened (createdAt). */
export function orderWhenDay(order: {
  stock: boolean
  serviceOn: string | null
  createdAt: string
}): string {
  if (order.serviceOn) {
    return order.serviceOn
  }
  if (!order.createdAt) {
    return ''
  }
  return order.createdAt.slice(0, 10)
}