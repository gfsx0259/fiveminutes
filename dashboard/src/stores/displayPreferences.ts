import { defineStore } from 'pinia'
import { computed, ref } from 'vue'

export type TableScrollMode = 'viewport' | 'page'

const STORAGE_KEY = 'rider.tableScrollMode'

function readStoredMode(): TableScrollMode {
  const raw = localStorage.getItem(STORAGE_KEY)
  return raw === 'page' ? 'page' : 'viewport'
}

export const useDisplayPreferencesStore = defineStore('displayPreferences', () => {
  const tableScrollMode = ref<TableScrollMode>(readStoredMode())

  const tableListLayoutClass = computed(() =>
    tableScrollMode.value === 'page' ? 'cabinet-page--page-scroll' : 'cabinet-page--fill',
  )

  function setTableScrollMode(mode: TableScrollMode) {
    tableScrollMode.value = mode
    localStorage.setItem(STORAGE_KEY, mode)
  }

  return {
    tableScrollMode,
    tableListLayoutClass,
    setTableScrollMode,
  }
})