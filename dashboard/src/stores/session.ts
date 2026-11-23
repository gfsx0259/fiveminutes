import { defineStore } from 'pinia'
import { ref } from 'vue'
import { apiDelete, apiPost } from '@/api'
import { apiOrigin, cabinetOrigin } from '@/host'
import type { ShelfAgePolicy } from '@/stores/market'

export interface Organization {
  id: string
  name: string
  apex: string
  markupPercent?: number
  balanceCents?: number | null
  shelfAgePolicy?: ShelfAgePolicy
}

export interface AppUser {
  id: string
  email: string
  role: string
  balanceCents?: number | null
  organization: Organization | null
}

export interface ImpersonationState {
  active: boolean
  actor: { id: string; email: string }
}

export interface SessionPayload {
  session: Record<string, unknown>
  user: AppUser | null
  impersonation?: ImpersonationState | null
}

export const useSessionStore = defineStore('session', () => {
  const payload = ref<SessionPayload | null>(null)
  const missing = ref(false)
  const loading = ref(true)
  const error = ref('')

  async function load(): Promise<void> {
    loading.value = true
    missing.value = false
    error.value = ''
    try {
      const response = await fetch(`${apiOrigin(window.location.hostname)}/session`, {
        credentials: 'include',
      })
      if (response.status === 401) {
        missing.value = true
        payload.value = null
        return
      }
      if (!response.ok) {
        error.value = `Сессия не прочиталась: ${response.status}`
        return
      }
      payload.value = await response.json() as SessionPayload
    } catch {
      error.value = 'Сессия не прочиталась'
    } finally {
      loading.value = false
    }
  }

  async function startImpersonation(
    userId: string,
    role?: string,
    options?: { redirect?: boolean },
  ): Promise<void> {
    await apiPost('/impersonate', { userId })
    await load()
    if (options?.redirect === false) {
      return
    }
    const nextRole = role ?? payload.value?.user?.role ?? 'partner'
    window.location.href = cabinetOrigin(window.location.hostname, nextRole)
  }

  async function stopImpersonation(): Promise<void> {
    await apiDelete('/impersonate')
    await load()
    window.location.href = `${cabinetOrigin(window.location.hostname, 'admin')}/users`
  }

  return { payload, missing, loading, error, load, startImpersonation, stopImpersonation }
})
