import { ref } from 'vue'

const open = ref(false)
const message = ref('')

export function useToastState() {
  return { open, message }
}

export function showToast(text: string): void {
  message.value = text
  open.value = true
}