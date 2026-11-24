<template>
  <div class="ui-data-table-shell" :class="{ 'ui-data-table-shell--loading': isLoading }">
    <v-data-table
      class="ui-data-table"
      :class="{ 'ui-data-table--cell-copy': cellCopy }"
      v-bind="tableAttrs"
      :cell-props="mergedCellProps"
      :loading="loading"
      :sort-asc-icon="sortAscIcon"
      :sort-desc-icon="sortDescIcon"
      :sort-icon="sortHintIcon"
    >
      <template #loader>
        <span />
      </template>

      <template v-if="!slots.loading" #loading>
        <div class="ui-data-table__loading-placeholder" />
      </template>

      <template v-for="name in passthroughSlots" :key="name" #[name]="scope">
        <slot :name="name" v-bind="scope ?? {}" />
      </template>
    </v-data-table>

    <div v-if="isLoading" class="ui-data-table__overlay">
      <v-progress-circular indeterminate color="primary" size="40" width="3" />
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, h, useAttrs, useSlots } from 'vue'
import { showToast } from '@/composables/useToast'
import { copyText } from '@/copyText'
import {
  TABLE_CELL_COPY_SKIP_KEYS,
  isTableCellCopyTarget,
  resolveTableCellCopyText,
  type TableCellCopyResolver,
} from '@/tableCellCopy'

defineOptions({ inheritAttrs: false })

type CellPropsInput = Record<string, unknown> | undefined
type CellPropsFn = (data: {
  item: Record<string, unknown>
  column: { key?: string }
  index: number
}) => CellPropsInput

const props = withDefaults(defineProps<{
  loading?: boolean | string
  cellCopy?: boolean
  cellCopyExclude?: string[]
  cellCopyValue?: TableCellCopyResolver
}>(), {
  loading: false,
  cellCopy: true,
  cellCopyExclude: () => [],
})

const attrs = useAttrs()
const slots = useSlots()

const tableAttrs = computed(() => {
  const { cellProps: _cellProps, ...rest } = attrs
  return rest
})

const externalCellProps = computed(() => attrs.cellProps as CellPropsFn | CellPropsInput | undefined)

function mergedCellProps(data: {
  item: Record<string, unknown>
  column: { key?: string }
  index: number
}): CellPropsInput {
  const external = externalCellProps.value
  const base = typeof external === 'function' ? external(data) : external
  const key = data.column.key ?? ''
  if (!props.cellCopy || !key || TABLE_CELL_COPY_SKIP_KEYS.has(key) || props.cellCopyExclude.includes(key)) {
    return base
  }

  const baseClass = base?.class
  return {
    ...base,
    class: [
      'ui-table-cell-copy',
      baseClass,
    ],
    onClick: (event: MouseEvent) => {
      if (typeof base?.onClick === 'function') {
        base.onClick(event)
      }
      if (event.defaultPrevented || !isTableCellCopyTarget(event.target)) {
        return
      }
      const text = resolveTableCellCopyText(key, data.item, props.cellCopyValue)
      if (!text) {
        return
      }
      void copyText(text).then((ok) => {
        showToast(ok ? 'Copied' : 'Copy unavailable')
      })
    },
  }
}

const isLoading = computed(() => props.loading !== false && props.loading != null && props.loading !== '')

const passthroughSlots = computed(() =>
  Object.keys(slots).filter((name) => name !== 'loader'),
)

const arrow = (paths: string[]) => () => h('svg', {
  xmlns: 'http://www.w3.org/2000/svg',
  viewBox: '0 0 24 24',
  fill: 'none',
  stroke: 'currentColor',
  'stroke-width': 2,
  'stroke-linecap': 'round',
  'stroke-linejoin': 'round',
  'aria-hidden': 'true',
}, paths.map(d => h('path', { d })))

const sortAscIcon = arrow(['m5 12 7-7 7 7', 'M12 19V5'])
const sortDescIcon = arrow(['M12 5v14', 'm19 12-7 7-7-7'])
const sortHintIcon = arrow(['m21 16-4 4-4-4', 'M17 20V4', 'm3 8 4-4 4 4', 'M7 4v16'])
</script>

<style scoped>
.ui-data-table-shell {
  max-width: 100%;
  min-height: 350px;
  min-width: 0;
  position: relative;
  width: 100%;
}

.ui-data-table :deep(.v-data-table),
.ui-data-table :deep(.v-table) {
  width: 100%;
}

.ui-data-table:not(.resizable-cols) :deep(.v-table__wrapper) {
  overflow-x: auto;
  -webkit-overflow-scrolling: touch;
  overscroll-behavior-x: contain;
}

.ui-data-table:not(.resizable-cols) :deep(.v-table__wrapper > table) {
  min-width: 100%;
  table-layout: auto;
  width: max-content;
}

.ui-data-table-shell--loading .ui-data-table :deep(tbody) {
  opacity: 0.45;
  pointer-events: none;
  transition: opacity 0.15s ease;
}

.ui-data-table__overlay {
  align-items: center;
  display: flex;
  inset: 32px 0 0;
  justify-content: center;
  pointer-events: none;
  position: absolute;
  z-index: 4;
}

.ui-data-table__loading-placeholder {
  min-height: 280px;
}

.ui-data-table {
  background: transparent;
  color: var(--lm-foreground);
}

.ui-data-table :deep(.v-table) {
  --v-table-row-height: var(--lm-table-row-height);
  background: #fff;
  border: 0;
  color: var(--lm-foreground);
  font-size: var(--lm-font-table);
}

.ui-data-table :deep(.v-table__wrapper) {
  border-radius: 10px;
  max-height: min(550px, 70vh);
  min-height: 350px;
}

.ui-data-table:not(.resizable-cols) :deep(thead th .v-data-table-header__content) {
  white-space: nowrap;
}

.ui-data-table:not(.resizable-cols) :deep(tbody td) {
  white-space: nowrap;
}

.ui-data-table.resizable-cols :deep(.v-table__wrapper > table) {
  table-layout: fixed;
}

.ui-data-table.resizable-cols :deep(th[data-column]:not([data-column='actions'])::after) {
  content: '';
  cursor: col-resize;
  height: 100%;
  position: absolute;
  right: 0;
  top: 0;
  width: 8px;
}

.ui-data-table :deep(thead th) {
  background: var(--lm-lavender) !important;
  border-bottom: 0 !important;
  box-shadow: none !important;
  color: var(--lm-foreground) !important;
  font-size: 12px !important;
  font-weight: 600 !important;
  height: 32px !important;
  position: sticky;
  text-align: left !important;
  top: 0;
  white-space: nowrap;
  z-index: 2;
}

.ui-data-table :deep(thead) {
  position: relative;
}

.ui-data-table :deep(.v-data-table-progress) {
  display: none !important;
}

.ui-data-table :deep(thead th:first-child) {
  border-radius: 10px 0 0 0;
}

.ui-data-table :deep(thead th:last-child) {
  border-radius: 0 10px 0 0;
}

.ui-data-table :deep(.v-data-table-header__content) {
  gap: 5px;
  justify-content: flex-start;
}

.ui-data-table :deep(th.v-data-table-column--align-center .v-data-table-header__content) {
  justify-content: center;
  width: 100%;
}

.ui-data-table :deep(th.v-data-table-column--align-end .v-data-table-header__content) {
  justify-content: flex-end;
  width: 100%;
}

.ui-data-table :deep(.v-data-table-header__sort-icon.v-icon) {
  color: inherit;
  flex: 0 0 13px;
  font-size: 13px;
  height: 13px;
  max-width: 13px;
  min-width: 13px;
  opacity: 1;
  overflow: hidden;
  width: 13px;
}

.ui-data-table :deep(.v-data-table-header__sort-icon svg) {
  display: block;
  height: 100%;
  width: 100%;
}

.ui-data-table :deep(th:not(.v-data-table__th--sorted) .v-data-table-header__sort-icon.v-icon) {
  opacity: 0;
}

.ui-data-table :deep(th:not(.v-data-table__th--sorted):hover .v-data-table-header__sort-icon.v-icon),
.ui-data-table :deep(th:not(.v-data-table__th--sorted):focus-visible .v-data-table-header__sort-icon.v-icon) {
  opacity: 0.65;
}

.ui-data-table :deep(tbody td) {
  border-bottom: 1px solid var(--lm-lavender) !important;
  box-sizing: border-box;
  font-size: var(--lm-font-table);
  height: var(--lm-table-row-height);
  line-height: 1.25;
  min-height: var(--lm-table-row-height);
  padding-block: 0;
  padding-inline: 16px;
  text-align: left;
  vertical-align: middle;
}

.ui-data-table :deep(tbody tr:last-child td) {
  border-bottom: 0 !important;
}

/* Compact row actions; overrides .lm-page default 40px control height */
.ui-data-table :deep(tbody td .ui-btn.v-btn:not(.v-btn--icon)) {
  height: 30px !important;
  min-height: 30px !important;
}

.ui-data-table :deep(tbody td .ui-table-cell-actions) {
  align-items: center;
  display: inline-flex;
  flex-flow: row nowrap;
  gap: 2px;
  justify-content: flex-end;
  max-width: 100%;
  white-space: nowrap;
}

.ui-data-table :deep(tbody tr:hover td) {
  background: #faf8ff !important;
}

.ui-data-table :deep(tbody tr:hover td::after) {
  background: transparent !important;
  content: none !important;
}

.ui-data-table :deep(thead th.v-data-table-column--align-center),
.ui-data-table :deep(tbody td.v-data-table-column--align-center) {
  text-align: center !important;
}

.ui-data-table :deep(thead th.v-data-table-column--align-end),
.ui-data-table :deep(tbody td.v-data-table-column--align-end) {
  text-align: end !important;
}

.ui-data-table :deep(.v-btn--icon) {
  color: var(--lm-primary-deep);
}

/* Actions stay visible while scrolling wide tables */
.ui-data-table :deep(.ui-col-sticky-end) {
  background: #fff;
  box-shadow: none;
  padding-inline: 4px 10px !important;
  position: sticky;
  right: 0;
  z-index: 1;
}

.ui-data-table :deep(thead .ui-col-sticky-end) {
  background: var(--lm-lavender) !important;
  z-index: 3;
}

.ui-data-table :deep(tbody tr:hover .ui-col-sticky-end) {
  background: #faf8ff !important;
}

.ui-data-table--cell-copy :deep(tbody td.ui-table-cell-copy) {
  cursor: pointer;
}

.ui-data-table--cell-copy :deep(tbody td.ui-table-cell-copy:hover) {
  color: #3512bc;
}

@media (max-width: 1280px) {
  .ui-data-table :deep(.ui-col-sticky-end) {
    padding-inline: 6px 8px !important;
  }
}
</style>
