<template>
  <div class="ui-table-pagination">
    <div class="ui-table-pagination__left">
      <div class="ui-table-pagination__per-page">
        <span class="ui-table-pagination__per-page-label">Per page</span>
        <UiSelect
          class="ui-table-pagination__per-page-select"
          :model-value="itemsPerPage"
          :items="perPageItems"
          :menu-min-width="128"
          @update:model-value="onPerPageSelect"
        />
      </div>
      <span class="ui-table-pagination__range">{{ rangeLabel }}</span>
    </div>
    <nav class="ui-table-pagination__nav" aria-label="Pagination">
      <button
        type="button"
        class="ui-table-pagination__arrow"
        :disabled="page <= 1"
        aria-label="Previous page"
        @click="go(page - 1)"
      >
        <v-icon icon="mdi-chevron-left" size="18" />
      </button>
      <template v-for="(token, idx) in tokens" :key="`${token}-${idx}`">
        <span v-if="token === '…'" class="ui-table-pagination__ellipsis">…</span>
        <button
          v-else
          type="button"
          class="ui-table-pagination__page"
          :class="{ 'ui-table-pagination__page--active': token === page }"
          @click="go(token)"
        >
          {{ token }}
        </button>
      </template>
      <button
        type="button"
        class="ui-table-pagination__arrow"
        :disabled="page >= pageCount"
        aria-label="Next page"
        @click="go(page + 1)"
      >
        <v-icon icon="mdi-chevron-right" size="18" />
      </button>
    </nav>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import UiSelect from '@/components/ui/UiSelect.vue'

const props = withDefaults(defineProps<{
  page: number
  pageCount: number
  itemsLength: number
  itemsPerPage: number
  itemsPerPageOptions?: number[]
}>(), {
  itemsPerPageOptions: () => [50, 100, 200],
})

const emit = defineEmits<{
  'update:page': [page: number]
  'update:itemsPerPage': [n: number]
}>()

const perPageItems = computed(() =>
  props.itemsPerPageOptions.map((n) => ({ title: String(n), value: n })),
)

const rangeLabel = computed(() => {
  if (props.itemsLength === 0) {
    return '0 offers'
  }
  const start = (props.page - 1) * props.itemsPerPage + 1
  const end = Math.min(props.page * props.itemsPerPage, props.itemsLength)
  return `${start}–${end} of ${props.itemsLength}`
})

const tokens = computed((): Array<number | '…'> => {
  const total = Math.max(1, props.pageCount)
  const current = Math.min(Math.max(1, props.page), total)
  if (total <= 7) {
    return Array.from({ length: total }, (_, i) => i + 1)
  }
  const pages: Array<number | '…'> = [1]
  if (current > 3) {
    pages.push('…')
  }
  const windowStart = Math.max(2, current - 1)
  const windowEnd = Math.min(total - 1, current + 1)
  for (let p = windowStart; p <= windowEnd; p += 1) {
    pages.push(p)
  }
  if (current < total - 2) {
    pages.push('…')
  }
  pages.push(total)
  return pages
})

function go(next: number): void {
  const clamped = Math.min(Math.max(1, next), Math.max(1, props.pageCount))
  if (clamped !== props.page) {
    emit('update:page', clamped)
  }
}

function onPerPageSelect(value: unknown): void {
  const n = Number(value)
  if (Number.isFinite(n) && n > 0 && n !== props.itemsPerPage) {
    emit('update:itemsPerPage', n)
    emit('update:page', 1)
  }
}
</script>

<style scoped>
.ui-table-pagination {
  align-items: center;
  border-top: 1px solid #f2f0ff;
  display: flex;
  flex-wrap: wrap;
  gap: 16px;
  justify-content: space-between;
  margin-top: 4px;
  padding: 16px 4px 4px;
  width: 100%;
}

.ui-table-pagination__left {
  align-items: center;
  color: #5c6078;
  display: flex;
  flex-wrap: wrap;
  font-size: 13px;
  gap: 16px;
}

.ui-table-pagination__per-page {
  align-items: center;
  display: inline-flex;
  flex-shrink: 0;
  gap: 8px;
}

.ui-table-pagination__per-page-label {
  font-weight: 500;
  white-space: nowrap;
}

.ui-table-pagination__per-page-select {
  width: 76px;
}

.ui-table-pagination__per-page-select :deep(.ui-select) {
  font-size: 13px;
  font-weight: 600;
  min-height: 32px;
  padding: 4px 8px 4px 10px;
}

.ui-table-pagination__range {
  font-variant-numeric: tabular-nums;
}

.ui-table-pagination__nav {
  align-items: center;
  display: inline-flex;
  gap: 6px;
}

.ui-table-pagination__arrow,
.ui-table-pagination__page {
  align-items: center;
  background: #fff;
  border: 1px solid #e4e1f5;
  border-radius: 8px;
  color: #3512bc;
  cursor: pointer;
  display: inline-flex;
  font-size: 13px;
  font-weight: 600;
  height: 32px;
  justify-content: center;
  min-width: 32px;
  padding: 0 8px;
}

.ui-table-pagination__arrow:disabled {
  cursor: default;
  opacity: 0.4;
}

.ui-table-pagination__page--active {
  background: #5b45e8;
  border-color: #5b45e8;
  color: #fff;
}

.ui-table-pagination__ellipsis {
  color: #8b90a8;
  font-size: 13px;
  padding: 0 2px;
  user-select: none;
}

@media (max-width: 767px) {
  .ui-table-pagination {
    align-items: center;
    flex-direction: column;
    gap: 12px;
    justify-content: center;
  }

  .ui-table-pagination__left {
    justify-content: center;
    text-align: center;
  }

  /* Page size stays at the last desktop choice; switching 25→100 on phone is rare. */
  .ui-table-pagination__per-page {
    display: none;
  }

  .ui-table-pagination__nav {
    justify-content: center;
    width: 100%;
  }
}
</style>