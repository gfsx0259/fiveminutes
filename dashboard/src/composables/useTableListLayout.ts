import { computed, type MaybeRefOrGetter, toValue } from 'vue'

/** List pages fill the viewport and scroll inside the table. */
export function useTableListLayout(active: MaybeRefOrGetter<boolean> = true) {
  const tableListLayoutClass = computed(() => {
    if (!toValue(active)) {
      return {}
    }
    return { 'cabinet-page--fill': true }
  })

  return { tableListLayoutClass }
}
