export function orderDisplayId(id: string): string {
  const tail = id.replace(/-/g, '').slice(0, 5).toUpperCase()
  return `OR-${tail}`
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