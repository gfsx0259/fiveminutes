import { defineStore } from 'pinia'
import { ref } from 'vue'
import { apiOrigin } from '@/host'

export interface Organization {
  id: string
  name: string
  apex: string
}

export interface AppUser {
  id: string
  email: string
  role: string
  organization: Organization | null
}

export interface SessionPayload {
  session: Record<string, unknown>
  user: AppUser | null
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

  return { payload, missing, loading, error, load }
})
