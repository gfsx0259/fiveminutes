<template>
  <div class="reports">
    <div class="page-head">
      <div>
        <h1>Reports</h1>
        <p>Group and filter your leads. LeadMarket numbers and the numbers from your statuses are shown apart.</p>
      </div>
      <div class="reports__head-actions">
        <PeriodPicker v-model="choice" :now="now" />
        <UiButton variant="outline" icon="mdi-download" :disabled="tree.length === 0 || !session.can('reports.export')" @click="exportCsv">Export CSV</UiButton>
      </div>
    </div>

    <section class="ui-filter-panel reports__settings">
      <div class="reports__setting">
        <span class="reports__setting-title">Presets</span>
        <ReportPresets :groups="groups" :conditions="conditions" :columns="columns" @apply="applyPreset" />
      </div>

      <div class="reports__setting">
        <span class="reports__setting-title">Group by</span>
        <GroupLevels v-model="groups" />
      </div>

      <div class="reports__setting">
        <span class="reports__setting-title">Conditions</span>
        <div class="reports__conditions">
          <ReportConditionRow
            v-for="(condition, index) in conditions"
            :key="index"
            :condition="condition"
            :first="index === 0"
            :value-items="valuesOf(inPeriodAll, condition.field)"
            @update="conditions[index] = $event"
            @remove="conditions.splice(index, 1)"
          />
          <div class="reports__condition-actions">
            <UiButton variant="ghost" icon="mdi-plus" @click="addCondition">Add condition</UiButton>
            <UiButton v-if="conditions.length" variant="ghost" icon="mdi-delete-sweep-outline" class="reports__clear" @click="conditions = []">Clear</UiButton>
            <span v-if="!conditions.length" class="reports__hint">No conditions, all your leads are counted</span>
          </div>
        </div>
      </div>
    </section>

    <div class="reports__summary">
      <section v-for="block in summary" :key="block.key" class="reports__block" :data-block="block.key">
        <header>
          <strong>{{ block.label }}</strong>
          <span>{{ block.hint }}</span>
        </header>
        <div class="reports__tiles">
          <div v-for="tile in block.tiles" :key="tile.label" class="reports__tile" :title="tile.hint">
            <span class="reports__tile-label">{{ tile.label }}</span>
            <strong>{{ tile.value }}</strong>
            <DeltaChip v-bind="tile.delta" />
          </div>
        </div>
      </section>
    </div>

    <UiCard class="reports__table" title="Performance report" :subtitle="`${period.title} · open a row to see the next level, click a column to sort`">
      <ReportTable v-model:columns="columns" :tree="tree" :totals="current" :groups="groups" />
    </UiCard>
  </div>
</template>

<script setup lang="ts">
import { allowExport } from '@/exports'
import { computed, onMounted, ref, watch } from 'vue'
import { useLoadErrorToast } from '@/composables/useLoadErrorToast'
import DeltaChip from '@/components/DeltaChip.vue'
import PeriodPicker from '@/components/PeriodPicker.vue'
import GroupLevels from '@/components/reports/GroupLevels.vue'
import ReportConditionRow from '@/components/reports/ReportConditionRow.vue'
import ReportPresets from '@/components/reports/ReportPresets.vue'
import ReportTable from '@/components/reports/ReportTable.vue'
import UiButton from '@/components/ui/UiButton.vue'
import UiCard from '@/components/ui/UiCard.vue'
import { downloadCsv } from '@/csv'
import { dollars, wholeDollars } from '@/money'
import {
  between,
  choicePeriodOf,
  percent,
  purchasesOf,
  totalsOf,
  type PeriodChoice,
} from '@/purchases'
import {
  blocks,
  dimensionLabel,
  dimensions,
  matches,
  metrics,
  reportTree,
  valuesOf,
  type PresetState,
  type ReportCondition,
  type ReportDimension,
  type ReportNode,
} from '@/reportFields'
import { useLeadStore } from '@/stores/leads'
import { useMarketStore } from '@/stores/market'
import { useTeamStore } from '@/stores/team'
import { ACCOUNT_LEVEL, teamPath } from '@/team'
import { useSessionStore } from '@/stores/session'

const COLUMNS_KEY = 'rider:reports:columns'
const STATE_KEY = 'rider:reports:state'

const leadStore = useLeadStore()
const market = useMarketStore()
const session = useSessionStore()

const choice = ref<PeriodChoice>({ key: '7', from: '', to: '' })
const saved = savedState()
const groups = ref<ReportDimension[]>(saved.groups)
const conditions = ref<ReportCondition[]>(saved.conditions)
const columns = ref<string[]>(savedColumns())
const now = ref(Date.now())

watch(columns, (value) => keep(COLUMNS_KEY, value))
watch([groups, conditions], () => keep(STATE_KEY, { groups: groups.value, conditions: conditions.value }), { deep: true })

function keep(key: string, value: unknown): void {
  try {
    localStorage.setItem(key, JSON.stringify(value))
  } catch {
    return
  }
}

const teams = useTeamStore()
const purchases = computed(() => purchasesOf(leadStore.leads, market.orders, market.presets).map((purchase) => ({
  ...purchase,
  team: teamPath(teams.roster?.teams ?? [], purchase.teamId) || ACCOUNT_LEVEL,
})))
const period = computed(() => choicePeriodOf(choice.value, now.value))
const inPeriodAll = computed(() => between(purchases.value, period.value.from, period.value.to))
const matching = computed(() => purchases.value.filter((purchase) => matches(purchase, conditions.value)))
const inPeriod = computed(() => between(matching.value, period.value.from, period.value.to))
const current = computed(() => totalsOf(inPeriod.value))
const previous = computed(() => totalsOf(between(matching.value, period.value.previousFrom, period.value.previousTo)))
const tree = computed(() => reportTree(inPeriod.value, groups.value))

const summary = computed(() => {
  const shown = current.value
  const before = previous.value
  const tiles = {
    market: [
      { label: 'Leads', hint: 'Leads delivered to your orders', value: count(shown.leads), delta: { current: shown.leads, previous: before.leads } },
      { label: 'Spent', hint: 'What you paid for these leads', value: wholeDollars(shown.spent), delta: { current: shown.spent, previous: before.spent, good: 'none' as const } },
      { label: 'Avg cost / lead', hint: 'Spent ÷ leads', value: dollars(shown.averageCost), delta: { current: shown.averageCost, previous: before.averageCost, good: 'down' as const } },
    ],
    own: [
      { label: 'CR', hint: 'Approved ÷ CPA leads', value: percent(shown.approvalRate), delta: { current: shown.approvalRate, previous: before.approvalRate, points: true } },
      { label: 'Approved', hint: 'You confirmed the deposit', value: count(shown.approved), delta: { current: shown.approved, previous: before.approved } },
      { label: 'Rejected', hint: 'You marked the lead as bad', value: count(shown.rejected), delta: { current: shown.rejected, previous: before.rejected, good: 'down' as const } },
      { label: 'Waiting', hint: 'CPA leads without your status', value: count(shown.pending), delta: { current: shown.pending, previous: before.pending, good: 'none' as const } },
      {
        label: 'Cost / approved',
        hint: 'Spent ÷ approved',
        value: shown.approved ? wholeDollars(shown.costPerApproved) : '—',
        delta: shown.approved && before.approved
          ? { current: shown.costPerApproved, previous: before.costPerApproved, good: 'down' as const }
          : { current: 0, previous: 0 },
      },
    ],
  }
  return blocks.map((block) => ({ ...block, tiles: tiles[block.key] }))
})

const problem = computed(() => leadStore.error || market.error)

function savedState(): { groups: ReportDimension[], conditions: ReportCondition[] } {
  const fallback = { groups: ['country' as const], conditions: [] }
  try {
    const state = JSON.parse(localStorage.getItem(STATE_KEY) ?? 'null') as { groups?: unknown, conditions?: unknown } | null
    const known = new Set(dimensions.map((item) => item.key))
    const groupsOk = Array.isArray(state?.groups) && state.groups.length > 0 && state.groups.every((group) => known.has(group as ReportDimension))
    const conditionsOk = Array.isArray(state?.conditions) && state.conditions.every((condition) => {
      const value = condition as Partial<ReportCondition>
      return known.has(value.field as ReportDimension) && (value.operator === 'in' || value.operator === 'not_in') && Array.isArray(value.values)
    })
    if (groupsOk && conditionsOk) return { groups: state.groups as ReportDimension[], conditions: state.conditions as ReportCondition[] }
  } catch {
    return fallback
  }
  return fallback
}

function savedColumns(): string[] {
  const fallback = metrics.filter((metric) => metric.visible).map((metric) => metric.key)
  try {
    const saved = JSON.parse(localStorage.getItem(COLUMNS_KEY) ?? 'null') as unknown
    if (Array.isArray(saved) && saved.every((key) => metrics.some((metric) => metric.key === key))) {
      return saved as string[]
    }
  } catch {
    return fallback
  }
  return fallback
}

function applyPreset(preset: PresetState): void {
  groups.value = preset.groups
  conditions.value = preset.conditions
  if (preset.columns) columns.value = preset.columns
}

function addCondition(): void {
  const used = new Set(conditions.value.map((condition) => condition.field))
  const field = dimensions.find((item) => item.filterable && !used.has(item.key))?.key ?? 'country'
  conditions.value = [...conditions.value, { field, operator: 'in', values: [] }]
}

function count(value: number): string {
  return value.toLocaleString('en-US')
}

async function exportCsv(): Promise<void> {
  if (!(await allowExport('reports', tree.value.length))) {
    return
  }
  const shown = metrics.filter((metric) => columns.value.includes(metric.key))
  const header = [...groups.value.map(dimensionLabel), ...shown.map((metric) => metric.label)]
  const lines: string[][] = []
  const walk = (nodes: ReportNode[], path: string[]): void => {
    for (const node of nodes) {
      const names = [...path, node.name]
      lines.push([
        ...groups.value.map((_, index) => names[index] ?? ''),
        ...shown.map((metric) => {
          const value = metric.value(node.totals, node.share)
          return value === null ? '' : String(Math.round(value * 10000) / 10000)
        }),
      ])
      walk(node.children, names)
    }
  }
  walk(tree.value, [])
  downloadCsv(`reports-${groups.value.join('-')}.csv`, [header, ...lines])
}

async function refresh(): Promise<void> {
  now.value = Date.now()
  await Promise.all([leadStore.load(), market.loadOrders(), market.loadPresets(), teams.load()])
}

useLoadErrorToast(problem, () => { void refresh() })

onMounted(() => {
  void refresh()
})
</script>

<style scoped>
.page-head h1,
.page-head p {
  margin-bottom: 0;
  margin-top: 0;
}

.page-head p {
  margin-top: 3px;
}

.reports__head-actions {
  align-items: center;
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  justify-content: flex-end;
}

.reports__settings {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.reports__setting {
  align-items: flex-start;
  display: grid;
  gap: 12px;
  grid-template-columns: 110px minmax(0, 1fr);
}

.reports__setting-title {
  color: var(--lm-muted);
  font-size: 12px;
  font-weight: 700;
  line-height: 36px;
  text-transform: uppercase;
}

.reports__clear {
  color: #c2405f !important;
}

.reports__conditions {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.reports__condition-actions {
  align-items: center;
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
}

.reports__hint {
  color: var(--lm-muted);
  font-size: 12px;
  margin-left: 6px;
}

.reports__summary {
  display: grid;
  gap: 12px;
  grid-template-columns: minmax(0, 3fr) minmax(0, 5fr);
  margin-bottom: 12px;
}

.reports__block {
  --block-accent: #5b45e8;
  --block-soft: #f1eeff;
  background: #fff;
  border: 1px solid var(--lm-border);
  border-radius: var(--lm-radius);
  border-top: 3px solid var(--block-accent);
  box-shadow: var(--lm-shadow);
  min-width: 0;
  padding: 12px 14px 14px;
}

.reports__block[data-block='own'] {
  --block-accent: var(--success-strong);
  --block-soft: var(--success-soft);
}

.reports__block header {
  align-items: baseline;
  display: flex;
  flex-wrap: wrap;
  gap: 4px 10px;
  margin-bottom: 10px;
}

.reports__block header strong {
  color: var(--block-accent);
  font-size: 13px;
}

.reports__block header span {
  color: var(--lm-muted);
  font-size: 12px;
}

.reports__tiles {
  display: grid;
  gap: 8px;
  grid-template-columns: repeat(auto-fit, minmax(128px, 1fr));
}

.reports__tile {
  background: var(--block-soft);
  border-radius: 10px;
  min-width: 0;
  padding: 10px 12px;
}

.reports__tile-label {
  color: var(--lm-muted);
  display: block;
  font-size: 12px;
  font-weight: 600;
}

.reports__tile strong {
  display: block;
  font-size: clamp(17px, 1.5vw, 22px);
  font-variant-numeric: tabular-nums;
  font-weight: 700;
  letter-spacing: -0.4px;
  margin: 3px 0 6px;
  overflow-wrap: anywhere;
}

.reports__table {
  margin-top: 0;
}

@media (max-width: 1100px) {
  .reports__summary {
    grid-template-columns: minmax(0, 1fr);
  }
}

@media (max-width: 767px) {
  .reports__setting {
    grid-template-columns: minmax(0, 1fr);
    gap: 6px;
  }

  .reports__setting-title {
    line-height: 1.4;
  }

  .reports__head-actions {
    justify-content: stretch;
    width: 100%;
  }
}
</style>
