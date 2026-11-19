import { defineStore } from 'pinia'
import { ref } from 'vue'
import { api } from '@/api'

export interface PresetRow {
  id: string
  country: string
  trafficSource: string
  age: string
  gender: string
  vertical: string
  paymentModel: string
  cr: number
  floorPriceCents: number
  stockPriceCents: number
  createdAt: string
}

export interface OrderRow {
  id: string
  presetId: string
  vertical?: string
  trafficSource: string
  age: string
  gender: string
  geo: string
  volume: number
  supplied: number
  priceCents: number
  paymentModel: string
  partnerPriceCents: number
  stock: boolean
  serviceOn: string | null
  opensAt: string
  closesAt: string
  status: string
  partnerId: string
  partnerEmail: string
  createdAt: string
}

export interface CapRow {
  presetId: string
  trafficSource: string
  age: string
  gender: string
  paymentModel: string
  cr: number
  geo: string
  stock: boolean
  serviceOn: string | null
  volume: number
  supplied: number
}

export interface StockOfferRow {
  presetId: string
  vertical: string
  country: string
  trafficSource: string
  paymentModel: string
  conversion: number
  stockPriceCents: number
  floorPriceCents: number
  available: number
  dailyCapVolume: number
  dailyCapSupplied: number
  updatedAt: string | null
}

const warehouseDemoDailyCaps: ReadonlyArray<{ volume: number, supplied: number }> = [
  { volume: 70, supplied: 10 },
  { volume: 100, supplied: 35 },
  { volume: 50, supplied: 50 },
]

function applyWarehouseDemoDailyCaps(rows: StockOfferRow[]): StockOfferRow[] {
  if (!import.meta.env.DEV) {
    return rows
  }
  if (rows.some((row) => row.dailyCapVolume > 0)) {
    return rows
  }
  return rows.map((row, index) => {
    const sample = warehouseDemoDailyCaps[index]
    if (!sample) {
      return row
    }
    return {
      ...row,
      dailyCapVolume: sample.volume,
      dailyCapSupplied: sample.supplied,
    }
  })
}

export interface OfferRow {
  id: string
  presetId: string
  trafficSource: string
  age: string
  gender: string
  geo: string
  volume: number
  supplied: number
  stock: boolean
  serviceOn: string | null
  status: string
  buyerId: string
  buyerEmail: string
  createdAt: string
}

export const useMarketStore = defineStore('market', () => {
  const presets = ref<PresetRow[]>([])
  const orders = ref<OrderRow[]>([])
  const caps = ref<CapRow[]>([])
  const offers = ref<OfferRow[]>([])
  const stock = ref<StockOfferRow[]>([])
  const loading = ref(false)
  const error = ref('')

  async function loadPresets(): Promise<void> {
    await load('/presets', 'presets', presets, 'Пресеты не прочитались')
  }

  async function loadOrders(): Promise<void> {
    await load('/orders', 'orders', orders, 'Заказы не прочитались')
  }

  async function loadCaps(): Promise<void> {
    await load('/caps', 'caps', caps, 'Капы не прочитались')
  }

  async function loadOffers(): Promise<void> {
    await load('/offers', 'offers', offers, 'Предложения не прочитались')
  }

  async function loadWarehouse(): Promise<void> {
    loading.value = true
    error.value = ''
    try {
      const response = await api('/warehouse')
      if (!response.ok) {
        error.value = `Склад не прочитался: ${response.status}`
        return
      }
      const body = await response.json() as { stock?: StockOfferRow[] }
      stock.value = applyWarehouseDemoDailyCaps(body.stock ?? [])
    } catch {
      error.value = 'Склад не прочитался'
    } finally {
      loading.value = false
    }
  }

  async function createPreset(body: Record<string, string | number>): Promise<void> {
    await submit('/presets', body)
    await loadPresets()
  }

  async function deletePreset(id: string): Promise<void> {
    const response = await api(`/presets/${id}`, { method: 'DELETE' })
    const payload = await response.json().catch(() => ({})) as { error?: string }
    if (!response.ok) {
      throw new Error(payload.error ?? `Не удалилось: ${response.status}`)
    }
    await loadPresets()
  }

  async function createOrder(body: Record<string, string | number>): Promise<string> {
    const payload = await submit('/orders', body)
    await loadOrders()
    return payload.id ?? ''
  }

  async function createStockOrder(body: Record<string, string | number>): Promise<{ id?: string, supplied?: number }> {
    const payload = await submit('/orders', body)
    return payload as { id?: string, supplied?: number }
  }

  async function actOrder(id: string, action: 'pause' | 'resume' | 'finish'): Promise<void> {
    await submit(`/orders/${id}/${action}`, {})
    await loadOrders()
  }

  async function createOffer(body: Record<string, string | number>): Promise<void> {
    await submit('/offers', body)
    await loadOffers()
  }

  async function moderateOffer(id: string, action: 'approve' | 'reject'): Promise<void> {
    await submit(`/offers/${id}/${action}`, {})
    await loadOffers()
  }

  async function load<T>(path: string, key: string, target: { value: T[] }, message: string): Promise<void> {
    loading.value = true
    error.value = ''
    try {
      const response = await api(path)
      if (!response.ok) {
        error.value = `${message}: ${response.status}`
        return
      }
      const body = await response.json() as Record<string, T[]>
      target.value = body[key] ?? []
    } catch {
      error.value = message
    } finally {
      loading.value = false
    }
  }

  async function submit(path: string, body: Record<string, string | number>): Promise<{ id?: string, supplied?: number }> {
    const response = await api(path, { method: 'POST', body: JSON.stringify(body) })
    const payload = await response.json().catch(() => ({})) as { id?: string, supplied?: number, error?: string, errors?: Record<string, string> }
    if (!response.ok) {
      const details = payload.errors ? Object.values(payload.errors).join(' ') : payload.error
      throw new Error(details || `Запрос не прошёл: ${response.status}`)
    }
    return payload
  }

  return {
    presets,
    orders,
    caps,
    offers,
    stock,
    loading,
    error,
    loadPresets,
    loadOrders,
    loadCaps,
    loadOffers,
    loadWarehouse,
    createPreset,
    deletePreset,
    createOrder,
    createStockOrder,
    actOrder,
    createOffer,
    moderateOffer,
  }
})
