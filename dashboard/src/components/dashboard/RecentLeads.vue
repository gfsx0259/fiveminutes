<template>
  <div class="recent-leads">
    <UiDataTable
      :headers="headers"
      :cell-copy-value="recentLeadCellCopy"
      :items="rows"
      :items-per-page="-1"
      hide-default-footer
      item-value="uuid"
      hover
    >
      <template #item.at="{ item }">
        <span class="recent-leads__muted">{{ momentOf(item.at, now) }}</span>
      </template>
      <template #item.name="{ item }">
        <b>{{ item.name }}</b> <span class="recent-leads__muted">#{{ item.uuid.slice(0, 6) }}</span>
      </template>
      <template #item.country="{ item }">
        <CountryPick :code="item.country" />
      </template>
      <template #item.trafficSource="{ item }">
        <TrafficSourceLabel :source="item.trafficSource" />
      </template>
      <template #item.model="{ item }">
        <ModelTag :model="item.model" />
      </template>
      <template #item.cost="{ item }">
        {{ dollars(item.cost) }}
      </template>
      <template #item.outcome="{ item }">
        <StatusChip :status="item.outcome" :label="outcomeView(item.outcome).label" :tone="outcomeView(item.outcome).tone" />
      </template>
      <template #no-data>
        <div class="recent-leads__empty">No purchased leads yet</div>
      </template>
    </UiDataTable>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import CountryPick from '@/components/CountryPick.vue'
import ModelTag from '@/components/ModelTag.vue'
import StatusChip from '@/components/StatusChip.vue'
import TrafficSourceLabel from '@/components/TrafficSourceLabel.vue'
import UiDataTable from '@/components/ui/UiDataTable.vue'
import { dollars } from '@/money'
import { momentOf, outcomeView, type Purchase } from '@/purchases'
import { prepareTableHeaders } from '@/tableHeaders'
import { copyTextFromExportCell } from '@/tableCellCopy'

const props = defineProps<{
  purchases: Purchase[]
  now: number
}>()

const headers = prepareTableHeaders(
  [
    { title: 'Time', key: 'at', sortable: false },
    { title: 'Lead', key: 'name', sortable: false },
    { title: 'Country', key: 'country', sortable: false },
    { title: 'Traffic', key: 'trafficSource', sortable: false },
    { title: 'Model', key: 'model', sortable: false },
    { title: 'Price', key: 'cost', sortable: false, align: 'end' as const },
    { title: 'Status', key: 'outcome', sortable: false },
  ],
  {
    at: 130,
    name: 160,
    country: 96,
    trafficSource: 132,
    model: 88,
    cost: 88,
    outcome: 120,
  },
)

type RecentLeadRow = {
  uuid: string
  at: number
  name: string
  country: string
  trafficSource: string
  model: string
  cost: number
  outcome: string
}

function recentLeadCellCopy(key: string, item: Record<string, unknown>): string | null {
  const row = item as RecentLeadRow
  switch (key) {
    case 'at':
      return copyTextFromExportCell(momentOf(row.at, props.now))
    case 'name':
      return row.name
    case 'country':
      return row.country
    case 'trafficSource':
      return row.trafficSource
    case 'model':
      return row.model
    case 'cost':
      return copyTextFromExportCell(dollars(row.cost))
    default:
      return null
  }
}

const rows = computed((): RecentLeadRow[] => props.purchases.slice(-7).reverse().map((purchase) => ({
  uuid: purchase.lead.uuid,
  at: purchase.at,
  name: `${purchase.lead.firstName} ${purchase.lead.lastName.charAt(0)}.`.trim(),
  country: purchase.lead.country,
  trafficSource: purchase.lead.trafficSource,
  model: purchase.model,
  cost: purchase.cost,
  outcome: purchase.outcome,
})))
</script>

<style scoped>
.recent-leads :deep(.ui-data-table-shell),
.recent-leads :deep(.v-table__wrapper) {
  min-height: 0;
}

.recent-leads :deep(.v-table__wrapper > table) {
  min-width: 760px;
}

.recent-leads__muted {
  color: var(--lm-muted);
}

.recent-leads__empty {
  color: var(--lm-muted);
  padding: 32px 0;
}
</style>
