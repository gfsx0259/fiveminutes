import { ref } from 'vue'

export interface ConfirmRequest {
  title: string
  text: string
  confirmLabel?: string
  danger?: boolean
  choices?: { value: string; label: string; hint?: string }[]
}

interface PendingConfirm extends ConfirmRequest {
  resolve: (answer: string | null) => void
}

const pending = ref<PendingConfirm | null>(null)

export function useConfirmState() {
  return pending
}

export function askConfirm(request: ConfirmRequest): Promise<string | null> {
  pending.value?.resolve(null)
  return new Promise((resolve) => {
    pending.value = { ...request, resolve }
  })
}

export async function confirmAction(request: ConfirmRequest): Promise<boolean> {
  return (await askConfirm(request)) !== null
}

export function settleConfirm(answer: string | null): void {
  const current = pending.value
  pending.value = null
  current?.resolve(answer)
}
