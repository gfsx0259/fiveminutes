import { computed, type MaybeRefOrGetter, toValue } from 'vue'
import { useDisplayPreferencesStore } from '@/stores/displayPreferences'

/** CSS class for list pages (Stock, Orders, Leads journal) from display preferences. */
export function useTableListLayout(active: MaybeRefOrGetter<boolean> = true) {
  const prefs = useDisplayPreferencesStore()

  const tableListLayoutClass = computed(() => {
    if (!toValue(active)) {
      return {}
    }
    const cls = prefs.tableListLayoutClass
    return { [cls]: true }
  })

  return { tableListLayoutClass }
}