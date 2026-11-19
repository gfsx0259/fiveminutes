<template>
  <div class="breakdown-table">
    <UiDataTable
      v-model:sort-by="sortBy"
      :headers="headers"
      :items="rows"
      :items-per-page="-1"
      hide-default-footer
      item-value="key"
      hover
    >
      <template #item.key="{ item }">
        <span v-if="group === 'country'" class="breakdown-table__country">
          <CountryPick :code="item.key" />
          <span class="breakdown-table__muted">{{ countryName(item.key) }}</span>
        </span>
        <TrafficSourceLabel v-else-if="group === 'traffic'" :source="item.key" />
        <ModelTag v-else-if="group === 'model'" :model="item.key" />
        <span v-else>{{ timeLabel(item) }}</span>
      </template>
      <template #item.approvalRate="{ item }">
        <span class="breakdown-table__meter">
          {{ percent(item.approvalRate) }}
          <span class="breakdown-table__bar breakdown-table__bar--rate"><i :style="{ width: `${item.approvalRate / maxRate * 100}%` }" /></span>
        </span>
      </template>
      <template #item.spent="{ item }">{{ dollars(item.spent) }}</template>
      <template #item.averageCost="{ item }">{{ dollars(item.averageCost) }}</template>
      <template #item.costPerApproved="{ item }">
        <span v-if="item.approved">{{ dollars(item.costPerApproved) }}</span>
        <span v-else class="breakdown-table__muted">—</span>
      </template>
      <template #item.share="{ item }">
        <span class="breakdown-table__meter">
          {{ Math.round(item.share * 100) }}%
          <span class="breakdown-table__bar"><i :style="{ width: `${item.share * 100}%` }" /></span>
        </span>
      </template>
      <template #body.append>
        <tr v-if="rows.length > 1" class="breakdown-table__total">
          <td>Total</td>
          <td class="breakdown-table__num">{{ totals.leads.toLocaleString('en-US') }}</td>
          <td class="breakdown-table__num">{{ totals.delivered.toLocaleString('en-US') }}</td>
          <td class="breakdown-table__num">{{ totals.pending.toLocaleString('en-US') }}</td>
          <td class="breakdown-table__num">{{ totals.approved.toLocaleString('en-US') }}</td>
          <td class="breakdown-table__num">{{ totals.rejected.toLocaleString('en-US') }}</td>
          <td class="breakdown-table__num">{{ percent(totals.approvalRate) }}</td>
          <td class="breakdown-table__num">{{ dollars(totals.spent) }}</td>
          <td class="breakdown-table__num">{{ dollars(totals.averageCost) }}</td>
          <td class="breakdown-table__num">{{ totals.approved ? dollars(totals.costPerApproved) : '—' }}</td>
          <td class="breakdown-table__num">100%</td>
        </tr>
      </template>
      <template #no-data>
        <div class="breakdown-table__empty">No purchased leads for these filters</div>
      </template>
    </UiDataTable>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import CountryPick from '@/components/CountryPick.vue'
import ModelTag from '@/components/ModelTag.vue'
import TrafficSourceLabel from '@/components/TrafficSourceLabel.vue'
import UiDataTable from '@/components/ui/UiDataTable.vue'
import { countryName } from '@/countries'
import { dollars } from '@/money'
import { HOUR, percent, timeOf, type BreakdownGroup, type BreakdownRow, type Period, type PurchaseTotals } from '@/purchases'

const props = defineProps<{
  rows: BreakdownRow[]
  totals: PurchaseTotals
  group: BreakdownGroup
  unit: Period['unit']
}>()

const sortBy = ref<{ key: string, order: 'asc' | 'desc' }[]>([{ key: 'spent', order: 'desc' }])

watch(() => props.group, (group) => {
  sortBy.value = [group === 'time' ? { key: 'key', order: 'asc' } : { key: 'spent', order: 'desc' }]
})

const dimension = computed(() => ({ country: 'Country', traffic: 'Traffic', model: 'Model', time: props.unit === 'hour' ? 'Hour' : 'Day' })[props.group])

const headers = computed(() => [
  { title: dimension.value, key: 'key', width: 200, sortRaw: (left: BreakdownRow, right: BreakdownRow) => compare(left.order, right.order) },
  { title: 'Leads', key: 'leads', align: 'end' as const },
  { title: 'Delivered', key: 'delivered', align: 'end' as const },
  { title: 'In work', key: 'pending', align: 'end' as const },
  { title: 'Approved', key: 'approved', align: 'end' as const },
  { title: 'Rejected', key: 'rejected', align: 'end' as const },
  { title: 'Approval rate', key: 'approvalRate', align: 'end' as const, width: 130 },
  { title: 'Spent', key: 'spent', align: 'end' as const, width: 120 },
  { title: 'Avg cost / lead', key: 'averageCost', align: 'end' as const, width: 130 },
  { title: 'Cost / approved', key: 'costPerApproved', align: 'end' as const, width: 130 },
  { title: 'Share of spend', key: 'share', align: 'end' as const, width: 130 },
])

const maxRate = computed(() => Math.max(0.01, ...props.rows.map((row) => row.approvalRate)))

function compare(left: string | number, right: string | number): number {
  if (left === right) return 0
  return left > right ? 1 : -1
}

function timeLabel(row: BreakdownRow): string {
  const start = Number(row.order)
  if (props.unit === 'hour') return `${timeOf(start)}–${timeOf(start + HOUR)}`
  return new Date(start).toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short' })
}
</script>

<style scoped>
.breakdown-table :deep(.ui-data-table__overlay) {
  inset: 32px 0 0;
}

.breakdown-table :deep(.v-table__wrapper > table) {
  min-width: 1240px;
}

.breakdown-table__country {
  align-items: center;
  display: inline-flex;
  font-weight: 600;
  gap: 8px;
}

.breakdown-table__muted {
  color: var(--lm-muted);
  font-weight: 400;
}

.breakdown-table__meter {
  align-items: center;
  display: inline-flex;
  gap: 8px;
  justify-content: flex-end;
}

.breakdown-table__bar {
  background: var(--lm-lavender);
  border-radius: 999px;
  display: inline-block;
  height: 6px;
  overflow: hidden;
  width: 56px;
}

.breakdown-table__bar i {
  background: linear-gradient(90deg, #6650ee, #5339e5);
  border-radius: 999px;
  display: block;
  height: 100%;
}

.breakdown-table__bar--rate i {
  background: linear-gradient(90deg, #5fc489, #2f9e5b);
}

.breakdown-table__total td {
  background: #faf9ff;
  border-top: 1px solid #dcd6f5;
  font-weight: 700;
}

.breakdown-table__num {
  font-variant-numeric: tabular-nums;
  text-align: right;
}

.breakdown-table__empty {
  color: var(--lm-muted);
  padding: 40px 0;
}
</style>
