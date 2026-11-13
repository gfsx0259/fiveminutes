import { defineStore } from 'pinia'
import { ref } from 'vue'
import { api } from '@/api'

export interface CabinetLead {
  uuid: string
  buyerId: string
  buyerEmail: string
  sellerId: string | null
  status: string
  phone: string
  email: string
  firstName: string
  lastName: string
  country: string
  ip: string
  affSub: string
  priceCents: number
  createdAt: string
}

export const useLeadStore = defineStore('leads', () => {
  const leads = ref<CabinetLead[]>([])
  const loading = ref(false)
  const error = ref('')

  async function load(): Promise<void> {
    loading.value = true
    error.value = ''
    try {
      const response = await api('/leads')
      if (response.status === 403) {
        error.value = 'Эта сессия не входит в организацию. Журнал открывается байером или партнёром этого апекса.'
        return
      }
      if (!response.ok) {
        error.value = `Лиды не прочитались: ${response.status}`
        return
      }
      const body = await response.json() as { leads?: CabinetLead[] }
      leads.value = body.leads ?? []
    } catch {
      error.value = 'Лиды не прочитались'
    } finally {
      loading.value = false
    }
  }

  return { leads, loading, error, load }
})
