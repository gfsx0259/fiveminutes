import { defineStore } from 'pinia'
import { ref } from 'vue'
import { api } from '@/api'

export interface CabinetLead {
  uuid: string
  buyerId?: string
  buyerEmail?: string
  sellerId?: string | null
  sellerEmail?: string
  orderId?: string | null
  status?: string
  isDelivered?: boolean
  deliveryError?: boolean
  clientStatus?: string
  clientDeposit?: boolean
  phone: string
  email: string
  contactsHidden?: boolean
  firstName: string
  lastName: string
  country: string
  trafficSource: string
  age: string
  gender: string
  ip: string
  affSub: string
  uniqId: string
  priceCents: number
  buyerNetCents?: number
  platformFeeCents?: number
  partnerPriceCents?: number
  createdAt: string
  updatedAt: string
  soldAt: string | null
  liveInjectStatus?: string
  liveInjectDueAt?: string
  liveInjectOrderId?: string
}

export const HIDDEN_CONTACT = 'Hidden'

function withContacts(lead: CabinetLead): CabinetLead {
  return { ...lead, phone: lead.phone ?? '', email: lead.email ?? '' }
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
        error.value = `Failed to load leads: ${response.status}`
        return
      }
      const body = await response.json() as { leads?: CabinetLead[] }
      leads.value = (body.leads ?? []).map(withContacts)
    } catch {
      error.value = 'Failed to load leads'
    } finally {
      loading.value = false
    }
  }

  async function loadOne(uuid: string): Promise<CabinetLead | null> {
    try {
      const response = await api(`/leads/${uuid}`)
      if (!response.ok) {
        return null
      }
      const body = await response.json() as { lead?: CabinetLead }
      return body.lead ? withContacts(body.lead) : null
    } catch {
      return null
    }
  }

  return { leads, loading, error, load, loadOne }
})
