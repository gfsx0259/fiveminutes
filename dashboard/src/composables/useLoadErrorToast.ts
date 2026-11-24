import { type ComputedRef, type Ref, watch } from 'vue'
import { dismissErrorToastIfOpen, showErrorToast } from '@/composables/useToast'

type MaybeRef<T> = Ref<T> | ComputedRef<T>

export function useLoadErrorToast(
  error: MaybeRef<string>,
  onRefresh: () => void | Promise<void>,
  options?: { when?: MaybeRef<boolean> },
): void {
  watch(
    () => {
      const enabled = options?.when ? options.when.value : true
      return [enabled, error.value] as const
    },
    ([enabled, message]) => {
      if (!enabled || !message) {
        dismissErrorToastIfOpen()
        return
      }
      showErrorToast(message, () => {
        void onRefresh()
      })
    },
    { immediate: true },
  )
}