import { defineStore } from 'pinia'
import { ref } from 'vue'
import { api } from '@/api'
import type { TeamRoster } from '@/team'

export const useTeamStore = defineStore('team', () => {
  const roster = ref<TeamRoster | null>(null)
  const loading = ref(false)
  const error = ref('')

  async function load(): Promise<void> {
    loading.value = true
    error.value = ''
    try {
      const response = await api('/team')
      const data = await response.json() as TeamRoster & { error?: string }
      if (!response.ok) {
        throw new Error(data.error ?? `Request failed: ${response.status}`)
      }
      roster.value = data
    } catch (err) {
      error.value = err instanceof Error ? err.message : 'Failed to load the team'
    } finally {
      loading.value = false
    }
  }

  return { roster, loading, error, load }
})
