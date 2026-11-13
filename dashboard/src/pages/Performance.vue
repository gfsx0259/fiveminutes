<template>
  <div>
    <div class="mb-4">
      <h1>Performance</h1>
      <p>{{ isBuyer ? 'Leads you mined, grouped by country' : 'Leads you bought, grouped by country' }}</p>
    </div>

    <v-alert v-if="leadStore.error" type="error" variant="tonal" class="mb-4">{{ leadStore.error }}</v-alert>

    <div class="report-totals">
      <div>
        <span>Leads</span>
        <strong>{{ totals.leads.toLocaleString('en-US') }}</strong>
      </div>
      <div>
        <span>Sold</span>
        <strong>{{ totals.sold.toLocaleString('en-US') }}</strong>
      </div>
      <div>
        <span>CR</span>
        <strong>{{ cr }}</strong>
      </div>
      <div>
        <span>Lead revenue</span>
        <strong>{{ dollars(totals.revenue) }}</strong>
      </div>
    </div>

    <UiTableCard title="Countries">
      <UiDataTable
        :headers="headers"
        :items="rows"
        :loading="leadStore.loading"
        items-per-page="-1"
        hide-default-footer
        item-value="country"
        hover
      >
        <template #item.country="{ item }">
          {{ flag(item.country) }} {{ item.country }}
        </template>
        <template #item.crValue="{ item }">
          {{ item.cr }}
        </template>
        <template #item.revenue="{ item }">
          {{ dollars(item.revenue) }}
        </template>
        <template #item.epl="{ item }">
          {{ dollars(item.epl) }}
        </template>
        <template #no-data>
          <div class="py-8 text-medium-emphasis">{{ isBuyer ? 'Лидов пока нет' : 'Купленных лидов пока нет' }}</div>
        </template>
      </UiDataTable>
    </UiTableCard>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted } from 'vue'
import UiDataTable from '@/components/ui/UiDataTable.vue'
import UiTableCard from '@/components/ui/UiTableCard.vue'
import { dollars, flag } from '@/money'
import { useLeadStore } from '@/stores/leads'
import { useSessionStore } from '@/stores/session'

const session = useSessionStore()
const leadStore = useLeadStore()
const isBuyer = computed(() => session.payload?.user?.role === 'buyer')

const headers = [
  { title: 'Type', key: 'country' },
  { title: 'Leads', key: 'leads', align: 'end' as const },
  { title: 'Sold', key: 'sold', align: 'end' as const },
  { title: 'Delivered', key: 'delivered', align: 'end' as const },
  { title: 'Approved', key: 'approved', align: 'end' as const },
  { title: 'Rejected', key: 'rejected', align: 'end' as const },
  { title: 'CR', key: 'crValue', align: 'end' as const },
  { title: 'Lead Revenue', key: 'revenue', align: 'end' as const },
  { title: 'EPL', key: 'epl', align: 'end' as const },
]

const rows = computed(() => {
  const grouped = new Map<string, { leads: number, sold: number, delivered: number, approved: number, rejected: number, revenue: number }>()
  for (const lead of leadStore.leads) {
    const country = lead.country || '—'
    const row = grouped.get(country) ?? { leads: 0, sold: 0, delivered: 0, approved: 0, rejected: 0, revenue: 0 }
    row.leads += 1
    if (lead.status === 'delivered') row.delivered += 1
    if (lead.status === 'approved') row.approved += 1
    if (lead.status === 'rejected') row.rejected += 1
    if (lead.status === 'delivered' || lead.status === 'approved') row.sold += 1
    row.revenue += lead.priceCents / 100
    grouped.set(country, row)
  }

  return [...grouped.entries()]
    .map(([country, row]) => ({
      country,
      ...row,
      cr: row.sold === 0 ? '0.00%' : `${(row.approved / row.sold * 100).toFixed(2)}%`,
      crValue: row.sold === 0 ? 0 : row.approved / row.sold * 100,
      epl: row.leads === 0 ? 0 : row.revenue / row.leads,
    }))
    .sort((left, right) => right.leads - left.leads)
})

const totals = computed(() => rows.value.reduce((sum, row) => ({
  leads: sum.leads + row.leads,
  sold: sum.sold + row.sold,
  approved: sum.approved + row.approved,
  revenue: sum.revenue + row.revenue,
}), { leads: 0, sold: 0, approved: 0, revenue: 0 }))

const cr = computed(() => {
  if (totals.value.sold === 0) return '0.00%'
  return `${(totals.value.approved / totals.value.sold * 100).toFixed(2)}%`
})

onMounted(() => {
  if (leadStore.leads.length === 0) void leadStore.load()
})
</script>
