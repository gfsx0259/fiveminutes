import { defineStore } from 'pinia'
import { ref } from 'vue'
import { api } from '@/api'

export interface SlotRow {
  id: string
  geo: string
  volume: number
  priceCents: number
  pricingModel: string
  deadline: string
  status: string
  buyerId: string
  buyerEmail: string
  createdAt: string
}

export interface DemandRow {
  id: string
  geo: string
  volume: number
  priceCents: number
  pricingModel: string
  serviceOn: string
  opensAt: string
  closesAt: string
  status: string
  partnerId: string
  partnerEmail: string
  createdAt: string
}

export const useMarketStore = defineStore('market', () => {
  const slots = ref<SlotRow[]>([])
  const demands = ref<DemandRow[]>([])
  const loading = ref(false)
  const error = ref('')

  async function loadSlots(): Promise<void> {
    loading.value = true
    error.value = ''
    try {
      const response = await api('/slots')
      if (!response.ok) {
        error.value = `Слоты не прочитались: ${response.status}`
        return
      }
      const body = await response.json() as { slots?: SlotRow[] }
      slots.value = body.slots ?? []
    } catch {
      error.value = 'Слоты не прочитались'
    } finally {
      loading.value = false
    }
  }

  async function loadDemands(): Promise<void> {
    loading.value = true
    error.value = ''
    try {
      const response = await api('/demands')
      if (!response.ok) {
        error.value = `Заявки не прочитались: ${response.status}`
        return
      }
      const body = await response.json() as { demands?: DemandRow[] }
      demands.value = body.demands ?? []
    } catch {
      error.value = 'Заявки не прочитались'
    } finally {
      loading.value = false
    }
  }

  async function createSlot(body: Record<string, string | number>): Promise<string> {
    return submit('/slots', body)
  }

  async function createDemand(body: Record<string, string | number>): Promise<string> {
    return submit('/demands', body)
  }

  async function acceptSlot(id: string): Promise<string> {
    return submit(`/slots/${id}/accept`, {})
  }

  async function acceptDemand(id: string): Promise<string> {
    return submit(`/demands/${id}/accept`, {})
  }

  async function submit(path: string, body: Record<string, string | number>): Promise<string> {
    const response = await api(path, { method: 'POST', body: JSON.stringify(body) })
    const payload = await response.json().catch(() => ({})) as { error?: string, errors?: Record<string, string> }
    if (!response.ok) {
      const details = payload.errors ? Object.values(payload.errors).join(' ') : payload.error
      throw new Error(details || `Запрос не прошёл: ${response.status}`)
    }
    return ''
  }

  return {
    slots,
    demands,
    loading,
    error,
    loadSlots,
    loadDemands,
    createSlot,
    createDemand,
    acceptSlot,
    acceptDemand,
  }
})
