export function orderDisplayId(id: string): string {
  const tail = id.replace(/-/g, '').slice(0, 5).toUpperCase()
  return `OR-${tail}`
}

export function presetDisplayId(id: string): string {
  const tail = id.replace(/-/g, '').slice(0, 5).toUpperCase()
  return `PR-${tail}`
}

export function leadDisplayId(uuid: string): string {
  const tail = uuid.replace(/-/g, '').slice(0, 5).toUpperCase()
  return `LD-${tail}`
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