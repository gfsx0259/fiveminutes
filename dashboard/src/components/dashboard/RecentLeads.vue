<template>
  <div class="recent-leads">
    <UiDataTable
      :headers="headers"
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

const props = defineProps<{
  purchases: Purchase[]
  now: number
}>()

const headers = [
  { title: 'Time', key: 'at', sortable: false, width: 130 },
  { title: 'Lead', key: 'name', sortable: false },
  { title: 'Country', key: 'country', sortable: false, width: 90 },
  { title: 'Traffic', key: 'trafficSource', sortable: false, width: 130 },
  { title: 'Model', key: 'model', sortable: false, width: 80 },
  { title: 'Price', key: 'cost', sortable: false, align: 'end' as const, width: 100 },
  { title: 'Status', key: 'outcome', sortable: false, width: 130 },
]

const rows = computed(() => props.purchases.slice(-7).reverse().map((purchase) => ({
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
