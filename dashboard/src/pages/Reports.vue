<template>
  <div class="reports">
    <div class="page-head">
      <div>
        <h1>Reports</h1>
        <p>Leads you purchased, partner outcomes and what they cost.</p>
      </div>
      <UiButton variant="outline" icon="mdi-download" :disabled="rows.length === 0" @click="exportCsv">Export CSV</UiButton>
    </div>

    <v-alert v-if="problem" type="error" variant="tonal" class="mb-4">{{ problem }}</v-alert>

    <section class="ui-filter-panel reports__filters">
      <UiField label="Period" icon="mdi-calendar-range">
        <UiSelect v-model="periodKey" :items="periodItems" />
      </UiField>
      <UiField label="Country" icon="mdi-earth">
        <UiSelect
          v-model="country"
          :items="countryItems"
          placeholder="All countries"
          empty-label="All countries"
          searchable
          search-placeholder="Search country"
          clearable
        >
          <template #option="{ item }">
            <CountryFlag :code="String(item.value)" :size="16" />
            <span>{{ item.title }}</span>
          </template>
        </UiSelect>
      </UiField>
      <UiField label="Traffic" icon="mdi-bullhorn-outline">
        <UiSelect v-model="traffic" :items="trafficSources" placeholder="All traffic" empty-label="All traffic" clearable />
      </UiField>
      <UiField label="Model" icon="mdi-cash-multiple">
        <UiSelect v-model="model" :items="paymentModels" placeholder="All models" empty-label="All models" clearable />
      </UiField>
      <UiButton variant="ghost" @click="clearFilters">Clear</UiButton>
    </section>

    <div class="reports__totals">
      <div v-for="tile in tiles" :key="tile.label" class="reports__total">
        <span class="reports__total-label">{{ tile.label }}</span>
        <strong>{{ tile.value }}</strong>
        <DeltaChip v-bind="tile.delta" />
      </div>
    </div>

    <UiCard title="Outcomes over time" :subtitle="chartSubtitle">
      <template #actions>
        <ChartLegend :items="legend" />
      </template>
      <ComboChart
        :labels="buckets.map((bucket) => bucket.label)"
        :ticks="buckets.map((bucket) => bucket.tick)"
        :bars="outcomes.map((item) => ({ label: item.label, color: item.color, values: trend.map((totals) => totals[item.value]) }))"
        :line="{ label: 'CR', color: '#3412b9', values: trend.map((totals) => totals.approvalRate) }"
        :line-max="rateMax"
        :left-format="(value) => String(Math.round(value))"
        :right-format="(value) => `${Math.round(value * 100)}%`"
        :tooltip="outcomeTooltip"
      />
    </UiCard>

    <UiCard class="reports__breakdown" title="Breakdown" subtitle="Click a column to sort">
      <template #actions>
        <UiSegmented v-model="group" :items="groupItems" />
      </template>
      <BreakdownTable :rows="rows" :totals="current" :group="group as BreakdownGroup" :unit="period.unit" />
    </UiCard>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import ChartLegend from '@/components/charts/ChartLegend.vue'
import ComboChart from '@/components/charts/ComboChart.vue'
import CountryFlag from '@/components/CountryFlag.vue'
import DeltaChip from '@/components/DeltaChip.vue'
import BreakdownTable from '@/components/reports/BreakdownTable.vue'
import UiButton from '@/components/ui/UiButton.vue'
import UiCard from '@/components/ui/UiCard.vue'
import UiField from '@/components/ui/UiField.vue'
import UiSegmented from '@/components/ui/UiSegmented.vue'
import UiSelect from '@/components/ui/UiSelect.vue'
import type { TooltipRow } from '@/charts'
import { countryName } from '@/countries'
import { downloadCsv } from '@/csv'
import { modelLabel, paymentModels, trafficLabel, trafficSources } from '@/marketOptions'
import { dollars, wholeDollars } from '@/money'
import {
  between,
  breakdownOf,
  bucketsOf,
  outcomes,
  percent,
  periodItems,
  periodOf,
  purchasesOf,
  totalsOf,
  type BreakdownGroup,
  type PeriodKey,
} from '@/purchases'
import { useLeadStore } from '@/stores/leads'
import { useMarketStore } from '@/stores/market'

const leadStore = useLeadStore()
const market = useMarketStore()

const periodKey = ref<string>('7')
const country = ref<string | null>(null)
const traffic = ref<string | null>(null)
const model = ref<string | null>(null)
const group = ref<string>('country')
const now = ref(Date.now())

const legend = [
  ...outcomes.map((item) => ({ label: item.label, color: item.color })),
  { label: 'CR', color: '#3412b9', line: true },
]

const purchases = computed(() => purchasesOf(leadStore.leads, market.orders))
const period = computed(() => periodOf(periodKey.value as PeriodKey, now.value))

const countryItems = computed(() => [...new Set(purchases.value.map((purchase) => purchase.lead.country).filter(Boolean))]
  .map((code) => ({ title: countryName(code), value: code }))
  .sort((left, right) => left.title.localeCompare(right.title)))

const matching = computed(() => purchases.value.filter((purchase) => (!country.value || purchase.lead.country === country.value)
  && (!traffic.value || purchase.lead.trafficSource === traffic.value)
  && (!model.value || purchase.model === model.value)))

const inPeriod = computed(() => between(matching.value, period.value.from, period.value.to))
const current = computed(() => totalsOf(inPeriod.value))
const previous = computed(() => totalsOf(between(matching.value, period.value.previousFrom, period.value.previousTo)))
const buckets = computed(() => bucketsOf(period.value))
const trend = computed(() => buckets.value.map((bucket) => totalsOf(between(inPeriod.value, bucket.from, bucket.to))))
const rateMax = computed(() => Math.min(1, Math.max(0.2, Math.ceil(Math.max(0, ...trend.value.map((totals) => totals.approvalRate)) * 10) / 10)))
const rows = computed(() => breakdownOf(inPeriod.value, group.value as BreakdownGroup, period.value.unit))

const groupItems = computed(() => [
  { title: 'Country', value: 'country' },
  { title: 'Traffic', value: 'traffic' },
  { title: 'Model', value: 'model' },
  { title: period.value.unit === 'hour' ? 'Hour' : 'Day', value: 'time' },
])

const chartSubtitle = computed(() => `${period.value.title}, ${period.value.unit === 'hour' ? 'by hour' : 'by day'} · deltas ${period.value.versus}`)

const tiles = computed(() => {
  const shown = current.value
  const before = previous.value
  return [
    { label: 'Leads', value: count(shown.leads), delta: { current: shown.leads, previous: before.leads } },
    { label: 'Delivered', value: count(shown.delivered), delta: { current: shown.delivered, previous: before.delivered, good: 'none' as const } },
    { label: 'In work', value: count(shown.pending), delta: { current: shown.pending, previous: before.pending, good: 'none' as const } },
    { label: 'Approved', value: count(shown.approved), delta: { current: shown.approved, previous: before.approved } },
    { label: 'Rejected', value: count(shown.rejected), delta: { current: shown.rejected, previous: before.rejected, good: 'down' as const } },
    { label: 'Approval rate', value: percent(shown.approvalRate), delta: { current: shown.approvalRate, previous: before.approvalRate, points: true } },
    { label: 'Spent', value: wholeDollars(shown.spent), delta: { current: shown.spent, previous: before.spent, good: 'none' as const } },
    { label: 'Avg cost / lead', value: dollars(shown.averageCost), delta: { current: shown.averageCost, previous: before.averageCost, good: 'down' as const } },
    {
      label: 'Cost / approved',
      value: shown.approved ? wholeDollars(shown.costPerApproved) : '—',
      delta: shown.approved && before.approved
        ? { current: shown.costPerApproved, previous: before.costPerApproved, good: 'down' as const }
        : { current: 0, previous: 0 },
    },
  ]
})

const problem = computed(() => leadStore.error || market.error)

function count(value: number): string {
  return value.toLocaleString('en-US')
}

function outcomeTooltip(index: number): TooltipRow[] {
  const totals = trend.value[index]
  if (!totals) return []
  return [
    ...outcomes.map((item) => ({ label: item.label, value: count(totals[item.value]), color: item.color })),
    { label: 'CR', value: percent(totals.approvalRate), color: '#3412b9' },
    { label: 'Spent', value: dollars(totals.spent) },
  ]
}

function clearFilters(): void {
  periodKey.value = '7'
  country.value = null
  traffic.value = null
  model.value = null
}

function exportCsv(): void {
  const header = ['Group', 'Leads', 'Delivered', 'In work', 'Approved', 'Rejected', 'Approval rate %', 'Spent', 'Avg cost / lead', 'Cost / approved', 'Share of spend %']
  const label = (key: string, order: string | number): string => {
    if (group.value === 'country') return countryName(key)
    if (group.value === 'traffic') return trafficLabel(key)
    if (group.value === 'model') return modelLabel(key)
    return new Date(Number(order)).toISOString()
  }
  downloadCsv(`reports-${group.value}.csv`, [header, ...rows.value.map((row) => [
    label(row.key, row.order),
    String(row.leads),
    String(row.delivered),
    String(row.pending),
    String(row.approved),
    String(row.rejected),
    (row.approvalRate * 100).toFixed(2),
    row.spent.toFixed(2),
    row.averageCost.toFixed(2),
    row.approved ? row.costPerApproved.toFixed(2) : '',
    (row.share * 100).toFixed(1),
  ])])
}

async function refresh(): Promise<void> {
  now.value = Date.now()
  await Promise.all([leadStore.load(), market.loadOrders()])
}

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

.reports__filters {
  align-items: end;
  display: grid;
  gap: 16px;
  grid-template-columns: repeat(4, minmax(0, 1fr)) auto;
  padding-top: 18px;
}

.reports__totals {
  display: grid;
  gap: 12px;
  grid-template-columns: repeat(9, minmax(0, 1fr));
  margin-bottom: 12px;
}

.reports__total {
  background: #fff;
  border: 1px solid var(--lm-border);
  border-radius: var(--lm-radius);
  box-shadow: var(--lm-shadow);
  min-width: 0;
  padding: 14px 16px;
}

.reports__total-label {
  color: var(--lm-muted);
  display: block;
  font-size: 12px;
  font-weight: 600;
  white-space: nowrap;
}

.reports__total strong {
  display: block;
  font-size: 22px;
  font-variant-numeric: tabular-nums;
  font-weight: 700;
  letter-spacing: -0.4px;
  margin: 4px 0 6px;
  white-space: nowrap;
}

.reports__breakdown {
  margin-top: 12px;
}

@media (max-width: 1500px) {
  .reports__totals {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
}

@media (max-width: 1100px) {
  .reports__filters {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 767px) {
  .reports__filters,
  .reports__totals {
    grid-template-columns: minmax(0, 1fr);
  }
}
</style>
