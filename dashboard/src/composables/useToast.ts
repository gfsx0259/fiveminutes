import { ref } from 'vue'

export type ToastContent = {
  title: string
  lines?: string[]
}

const open = ref(false)
const content = ref<ToastContent>({ title: '', lines: [] })
const timeoutMs = ref(2500)

export function useToastState() {
  return { open, content, timeoutMs }
}

export function showToast(text: string | ToastContent): void {
  if (typeof text === 'string') {
    content.value = { title: text, lines: [] }
    timeoutMs.value = 2500
  } else {
    content.value = { title: text.title, lines: text.lines ?? [] }
    timeoutMs.value = text.lines?.length ? 4500 : 2800
  }
  open.value = true
}