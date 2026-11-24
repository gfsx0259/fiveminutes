import { ref } from 'vue'

export type ToastTone = 'default' | 'error'

export type ToastContent = {
  title: string
  lines?: string[]
  tone?: ToastTone
}

const open = ref(false)
const content = ref<ToastContent>({ title: '', lines: [], tone: 'default' })
const timeoutMs = ref(2500)
const refreshHandler = ref<(() => void) | null>(null)

export function useToastState() {
  return { open, content, timeoutMs, refreshHandler }
}

export function hideToast(): void {
  open.value = false
  refreshHandler.value = null
}

export function dismissErrorToastIfOpen(): void {
  if (content.value.tone === 'error') {
    hideToast()
  }
}

export function showToast(text: string | ToastContent): void {
  refreshHandler.value = null
  if (typeof text === 'string') {
    content.value = { title: text, lines: [], tone: 'default' }
    timeoutMs.value = 2500
  } else {
    content.value = {
      title: text.title,
      lines: text.lines ?? [],
      tone: text.tone ?? 'default',
    }
    timeoutMs.value = text.lines?.length ? 4500 : 2800
  }
  open.value = true
}

export function showErrorToast(message: string, onRefresh?: () => void): void {
  content.value = { title: message, lines: [], tone: 'error' }
  refreshHandler.value = onRefresh ?? null
  timeoutMs.value = onRefresh ? -1 : 6000
  open.value = true
}