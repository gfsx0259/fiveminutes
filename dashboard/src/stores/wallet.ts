import { defineStore } from 'pinia'
import { ref } from 'vue'
import { api, apiPost } from '@/api'
import { useSessionStore } from '@/stores/session'

export interface WalletTransaction {
  id: string
  kind: string
  direction: 'credit' | 'debit'
  amountCents: number
  leadUuid: string | null
  orderId: string | null
  createdAt: string
}

export const useWalletStore = defineStore('wallet', () => {
  const session = useSessionStore()
  const transactions = ref<WalletTransaction[]>([])
  const loading = ref(false)
  const busy = ref(false)
  const error = ref('')

  async function load(): Promise<void> {
    loading.value = true
    try {
      const response = await api('/wallet/transactions')
      if (!response.ok) {
        error.value = `Failed to load wallet history: ${response.status}`
        return
      }
      const body = await response.json() as { transactions?: WalletTransaction[] }
      transactions.value = body.transactions ?? []
    } catch {
      error.value = 'Failed to load wallet history'
    } finally {
      loading.value = false
    }
  }

  async function deposit(): Promise<void> {
    await adjust('/wallet/deposit')
  }

  async function withdraw(): Promise<void> {
    await adjust('/wallet/withdraw')
  }

  async function adjust(path: string): Promise<void> {
    busy.value = true
    error.value = ''
    try {
      const data = await apiPost<{ balanceCents: number }>(path, {})
      if (session.payload?.user) {
        session.payload.user.balanceCents = data.balanceCents
      }
      await load()
    } catch (failure) {
      error.value = failure instanceof Error ? failure.message : 'Кошелёк не изменился'
    } finally {
      busy.value = false
    }
  }

  return { transactions, loading, busy, error, load, deposit, withdraw }
})
