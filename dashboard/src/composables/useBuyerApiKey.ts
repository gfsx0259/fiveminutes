import { ref } from 'vue'
import { api } from '@/api'
import { showErrorToast } from '@/composables/useToast'

const apiKey = ref('')
const loaded = ref(false)
const busy = ref(false)

/** The buyer's lead-delivery key, shared by every integration surface. */
export function useBuyerApiKey() {
  async function load(force = false): Promise<void> {
    if (loaded.value && !force) return
    try {
      const response = await api('/api-key')
      if (!response.ok) {
        showErrorToast(`The API key was not read: ${response.status}`)
        return
      }
      const payload = await response.json() as { apiKey?: string | null }
      apiKey.value = payload.apiKey ?? ''
      loaded.value = true
    } catch {
      showErrorToast('The API key was not read')
    }
  }

  async function generate(): Promise<void> {
    busy.value = true
    try {
      const response = await api('/api-key', { method: 'POST' })
      if (!response.ok) {
        showErrorToast(`The API key was not created: ${response.status}`)
        return
      }
      const payload = await response.json() as { apiKey?: string }
      apiKey.value = payload.apiKey ?? ''
      loaded.value = true
    } catch {
      showErrorToast('The API key was not created')
    } finally {
      busy.value = false
    }
  }

  return { apiKey, busy, load, generate }
}
