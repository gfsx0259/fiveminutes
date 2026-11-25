<template>
  <div class="report-table">
    <div class="report-table__toolbar">
      <div class="report-table__path">
        <template v-for="(dimension, level) in groups" :key="level">
          <v-icon v-if="level > 0" icon="mdi-chevron-right" size="16" />
          <span class="report-table__chip">{{ dimensionLabel(dimension) }}</span>
        </template>
      </div>
      <div class="report-table__actions">
        <UiButton v-if="groups.length > 1" variant="ghost" icon="mdi-unfold-more-horizontal" @click="expandAll">Expand all</UiButton>
        <UiButton v-if="groups.length > 1" variant="ghost" icon="mdi-unfold-less-horizontal" @click="opened = new Set()">Collapse all</UiButton>
        <v-menu :close-on-content-click="false" location="bottom end" offset="6">
          <template #activator="{ props: menuProps }">
            <UiButton v-bind="menuProps" variant="outline" icon="mdi-table-column">Columns</UiButton>
          </template>
          <div class="report-table__columns">
            <div v-for="block in blocks" :key="block.key" class="report-table__columns-block">
              <strong :data-block="block.key">{{ block.label }}</strong>
              <label v-for="metric in metrics.filter((item) => item.block === block.key)" :key="metric.key">
                <input v-model="visible" type="checkbox" :value="metric.key">
                <span>{{ metric.label }}</span>
                <small>{{ metric.hint }}</small>
              </label>
            </div>
          </div>
        </v-menu>
      </div>
    </div>

    <div class="report-table__scroll">
      <table>
        <thead>
          <tr class="report-table__blocks">
            <th class="report-table__sticky" />
            <th
              v-for="block in shownBlocks"
              :key="block.key"
              :colspan="block.span"
              :data-block="block.key"
              :title="block.hint"
            >
              {{ block.label }}
            </th>
          </tr>
          <tr>
            <th class="report-table__sticky report-table__th" @click="sortBy('name')">
              {{ dimensionLabel(groups[0] ?? 'country') }}
              <v-icon v-if="sort.key === 'name'" :icon="sort.desc ? 'mdi-arrow-down' : 'mdi-arrow-up'" size="14" />
            </th>
            <th
              v-for="metric in columns"
              :key="metric.key"
              class="report-table__th report-table__num"
              :data-block="metric.block"
              :class="{ 'report-table__edge': metric.key === edge }"
              :title="metric.hint"
              @click="sortBy(metric.key)"
            >
              <v-icon v-if="sort.key === metric.key" :icon="sort.desc ? 'mdi-arrow-down' : 'mdi-arrow-up'" size="14" />
              {{ metric.label }}
            </th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in rows" :key="row.node.id" :class="`report-table__row--level-${row.level}`">
            <td class="report-table__sticky report-table__copy" @click="copyCell($event, row.node.name)">
              <div class="report-table__name" :style="{ paddingLeft: `${row.level * 20}px` }">
              <button v-if="row.node.children.length" type="button" class="report-table__toggle" @click="toggle(row.node.id)">
                <v-icon :icon="opened.has(row.node.id) ? 'mdi-minus-box-outline' : 'mdi-plus-box-outline'" size="16" />
              </button>
              <span v-else class="report-table__toggle" />
              <CountryFlag v-if="row.node.dimension === 'country' && row.node.value" :code="row.node.value" :size="16" />
              <span class="report-table__label" :title="row.node.name">{{ row.node.name }}</span>
              <small v-if="row.level > 0">{{ dimensionLabel(row.node.dimension) }}</small>
              </div>
            </td>
            <td
              v-for="metric in columns"
              :key="metric.key"
              class="report-table__num report-table__copy"
              :class="{ 'report-table__edge': metric.key === edge }"
              @click="copyCell($event, cell(metric, row.node.totals, row.node.share))"
            >
              {{ cell(metric, row.node.totals, row.node.share) }}
            </td>
          </tr>
          <tr v-if="rows.length === 0">
            <td :colspan="columns.length + 1" class="report-table__empty">No leads for this period and conditions</td>
          </tr>
        </tbody>
        <tfoot v-if="rows.length">
          <tr>
            <td class="report-table__sticky">Total</td>
            <td v-for="metric in columns" :key="metric.key" class="report-table__num" :class="{ 'report-table__edge': metric.key === edge }">
              {{ cell(metric, totals, 1) }}
            </td>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import CountryFlag from '@/components/CountryFlag.vue'
import UiButton from '@/components/ui/UiButton.vue'
import { showToast } from '@/composables/useToast'
import { copyText } from '@/copyText'
import type { PurchaseTotals } from '@/purchases'
import { blocks, dimensionLabel, metrics, type MetricView, type ReportDimension, type ReportNode } from '@/reportFields'
import { copyTextFromExportCell, isTableCellCopyTarget } from '@/tableCellCopy'

const props = defineProps<{
  tree: ReportNode[]
  totals: PurchaseTotals
  groups: ReportDimension[]
}>()

const visible = defineModel<string[]>('columns', { required: true })

const opened = ref(new Set<string>())
const sort = ref<{ key: string, desc: boolean }>({ key: 'spent', desc: true })

const columns = computed(() => metrics.filter((metric) => visible.value.includes(metric.key)))

const edge = computed(() => columns.value.find((metric) => metric.block === 'own')?.key ?? '')

const shownBlocks = computed(() => blocks
  .map((block) => ({ ...block, span: columns.value.filter((metric) => metric.block === block.key).length }))
  .filter((block) => block.span > 0))

watch(() => props.groups.join('>'), () => {
  opened.value = new Set()
  const timeline = props.groups[0] === 'day' || props.groups[0] === 'hour'
  sort.value = timeline ? { key: 'name', desc: false } : { key: 'spent', desc: true }
}, { immediate: true })

function sortValue(node: ReportNode): number | string {
  if (sort.value.key === 'name') return node.order
  const metric = metrics.find((item) => item.key === sort.value.key)
  return metric?.value(node.totals, node.share) ?? -1
}

function sorted(nodes: ReportNode[]): ReportNode[] {
  const direction = sort.value.desc ? -1 : 1
  return [...nodes].sort((left, right) => {
    const a = sortValue(left)
    const b = sortValue(right)
    if (typeof a === 'number' && typeof b === 'number') return (a - b) * direction
    return String(a).localeCompare(String(b), undefined, { numeric: true }) * direction
  })
}

const rows = computed(() => {
  const list: { node: ReportNode, level: number }[] = []
  const walk = (nodes: ReportNode[], level: number): void => {
    for (const node of sorted(nodes)) {
      list.push({ node, level })
      if (opened.value.has(node.id)) walk(node.children, level + 1)
    }
  }
  walk(props.tree, 0)
  return list
})

function sortBy(key: string): void {
  sort.value = sort.value.key === key ? { key, desc: !sort.value.desc } : { key, desc: key !== 'name' }
}

function toggle(id: string): void {
  const next = new Set(opened.value)
  if (next.has(id)) next.delete(id)
  else next.add(id)
  opened.value = next
}

function expandAll(): void {
  const next = new Set<string>()
  const walk = (nodes: ReportNode[]): void => {
    for (const node of nodes) {
      if (node.children.length) {
        next.add(node.id)
        walk(node.children)
      }
    }
  }
  walk(props.tree)
  opened.value = next
}

function cell(metric: MetricView, totals: PurchaseTotals, share: number): string {
  const value = metric.value(totals, share)
  return value === null ? '—' : metric.format(value)
}

function copyCell(event: MouseEvent, text: string): void {
  const value = copyTextFromExportCell(text)
  if (!value || !isTableCellCopyTarget(event.target)) return
  void copyText(value).then((ok) => {
    showToast(ok ? 'Copied' : 'Copy unavailable')
  })
}
</script>

<style scoped>
.report-table__copy {
  cursor: pointer;
}

.report-table__copy:hover {
  color: #3512bc;
}

.report-table__toolbar {
  align-items: center;
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  justify-content: space-between;
  margin-bottom: 10px;
}

.report-table__path {
  align-items: center;
  color: var(--lm-muted);
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
}

.report-table__chip {
  background: var(--lm-lavender);
  border-radius: 999px;
  color: var(--lm-primary-deep);
  font-size: 12px;
  font-weight: 700;
  padding: 4px 10px;
}

.report-table__actions {
  align-items: center;
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.report-table__columns {
  background: #fff;
  border: 1px solid var(--lm-border);
  border-radius: 12px;
  box-shadow: 0 12px 32px #27206424;
  display: grid;
  gap: 16px;
  grid-template-columns: repeat(2, minmax(220px, 1fr));
  padding: 14px;
}

.report-table__columns-block {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.report-table__columns-block strong {
  color: #5b45e8;
  font-size: 12px;
}

.report-table__columns-block strong[data-block='own'] {
  color: #2f9e5b;
}

.report-table__columns-block label {
  column-gap: 8px;
  cursor: pointer;
  display: grid;
  font-size: 13px;
  grid-template-columns: 16px 1fr;
}

.report-table__columns-block label input {
  accent-color: var(--lm-primary);
  margin-top: 2px;
}

.report-table__columns-block small {
  color: var(--lm-muted);
  font-size: 11px;
  grid-column: 2;
}

.report-table__scroll {
  border: 1px solid var(--lm-border);
  border-radius: 12px;
  overflow-x: auto;
}

table {
  border-collapse: separate;
  border-spacing: 0;
  font-size: 13px;
  min-width: 100%;
}

th,
td {
  border-bottom: 1px solid var(--lm-lavender);
  padding: 0 12px;
  white-space: nowrap;
}

thead th {
  background: #f4f2ff;
  color: #2b2a45;
  font-size: 12px;
  font-weight: 700;
  height: 34px;
  text-align: left;
}

.report-table__blocks th {
  color: #5b45e8;
  font-size: 11px;
  height: 26px;
  letter-spacing: 0.3px;
  text-align: center;
  text-transform: uppercase;
}

.report-table__blocks th[data-block='own'] {
  background: #eaf6ee;
  color: #2f9e5b;
}

thead th[data-block='own'] {
  background: #f1f9f3;
}

.report-table__edge,
.report-table__blocks th[data-block='own'] {
  border-left: 2px solid #d6eedd;
}

.report-table__th {
  cursor: pointer;
  user-select: none;
}

.report-table__th:hover {
  color: var(--lm-primary-deep);
}

.report-table__num {
  font-variant-numeric: tabular-nums;
  text-align: right;
}

thead .report-table__num {
  text-align: right;
}

tbody td {
  height: 40px;
}

tbody tr:hover td {
  background: #faf9ff;
}

.report-table__sticky {
  background: #fff;
  border-right: 1px solid var(--lm-border);
  left: 0;
  min-width: 260px;
  position: sticky;
  z-index: 1;
}

thead .report-table__sticky {
  background: #f4f2ff;
  z-index: 2;
}

.report-table__name {
  align-items: center;
  display: flex;
  gap: 6px;
  max-width: 380px;
}

.report-table__name small {
  color: var(--lm-muted);
  font-size: 11px;
}

.report-table__label {
  font-weight: 600;
  overflow: hidden;
  text-overflow: ellipsis;
}

.report-table__row--level-1 td {
  background: #fcfcff;
}

.report-table__row--level-2 td {
  background: #f9f8ff;
}

.report-table__row--level-1 .report-table__label,
.report-table__row--level-2 .report-table__label {
  font-weight: 500;
}

.report-table__toggle {
  align-items: center;
  background: none;
  border: 0;
  color: var(--lm-primary);
  cursor: pointer;
  display: inline-flex;
  flex: 0 0 18px;
  height: 18px;
  justify-content: center;
  padding: 0;
}

tfoot td {
  background: #f4f2ff;
  border-bottom: 0;
  font-weight: 700;
  height: 40px;
}

.report-table__empty {
  color: var(--lm-muted);
  padding: 32px 12px;
  text-align: center;
}

@media (max-width: 767px) {
  .report-table__sticky {
    max-width: 150px;
    min-width: 150px;
  }

  .report-table__name {
    max-width: 130px;
  }

  .report-table__name small {
    display: none;
  }

  .report-table__columns {
    grid-template-columns: minmax(0, 1fr);
  }
}
</style>
