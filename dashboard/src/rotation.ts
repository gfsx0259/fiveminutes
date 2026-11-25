import { countryName } from '@/countries'
import { genderLabel, modelLabel, trafficLabel, verticalLabel } from '@/marketOptions'

export const rotationAxisOptions = [
  { title: 'Vertical', value: 'vertical' },
  { title: 'Country', value: 'country' },
  { title: 'Traffic source', value: 'traffic_source' },
  { title: 'Payment model', value: 'payment_model' },
  { title: 'Age', value: 'age' },
  { title: 'Gender', value: 'gender' },
]

export interface RotationOrder {
  id: string
  stock: boolean
  serviceOn: string | null
  volume: number
  supplied: number
  partnerEmail: string
  opensAt: string
  closesAt: string
}

export interface RotationPreset {
  id: string
  country: string
  trafficSource: string
  age: string
  gender: string
  vertical: string
  paymentModel: string
  orders: RotationOrder[]
}

export interface RotationNode {
  axis: string
  value: string
  children?: RotationNode[]
  presets?: RotationPreset[]
}

export function rotationAxisLabel(axis: string): string {
  return rotationAxisOptions.find((item) => item.value === axis)?.title ?? axis
}

export function rotationValueLabel(axis: string, value: string): string {
  if (axis === 'vertical') {
    return verticalLabel(value)
  }
  if (axis === 'country') {
    return countryName(value)
  }
  if (axis === 'traffic_source') {
    return trafficLabel(value)
  }
  if (axis === 'payment_model') {
    return modelLabel(value)
  }
  if (axis === 'age') {
    return value || 'Any age'
  }
  if (axis === 'gender') {
    return genderLabel(value)
  }
  return value
}

export interface RotationCap {
  supplied: number
  volume: number
}

/** Supplied and volume of every in-progress order under a folder or preset. */
export function ordersCap(orders: RotationOrder[]): RotationCap {
  return orders.reduce(
    (cap, order) => ({
      supplied: cap.supplied + order.supplied,
      volume: cap.volume + order.volume,
    }),
    { supplied: 0, volume: 0 },
  )
}

export function nodeCap(node: RotationNode): RotationCap {
  const cap = { supplied: 0, volume: 0 }
  for (const child of node.children ?? []) {
    const nested = nodeCap(child)
    cap.supplied += nested.supplied
    cap.volume += nested.volume
  }
  for (const preset of node.presets ?? []) {
    const nested = ordersCap(preset.orders)
    cap.supplied += nested.supplied
    cap.volume += nested.volume
  }
  return cap
}

export function presetRemainder(preset: RotationPreset, axes: string[]): string {
  const parts: string[] = []
  if (!axes.includes('vertical')) {
    parts.push(verticalLabel(preset.vertical))
  }
  if (!axes.includes('country')) {
    parts.push(preset.country)
  }
  if (!axes.includes('traffic_source')) {
    parts.push(trafficLabel(preset.trafficSource))
  }
  if (!axes.includes('payment_model')) {
    parts.push(modelLabel(preset.paymentModel))
  }
  if (!axes.includes('age')) {
    parts.push(preset.age || 'Any age')
  }
  if (!axes.includes('gender')) {
    parts.push(genderLabel(preset.gender))
  }
  return parts.join(' · ')
}
