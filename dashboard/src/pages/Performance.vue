<template>
  <div>
    <div class="mb-4">
      <h1>Performance</h1>
      <p v-if="isBuyer">Leads you mined, grouped by country</p>
      <p v-else>Organization leads, grouped by country</p>
      <p class="performance__hint">
        <template v-if="isBuyer">
          Sold means matched to a partner order. Net earnings are catalog price minus the platform fee. CR is approved ÷
          sold leads with a conversion outcome.
        </template>
        <template v-else>
          Partner sales are catalog prices on billable leads. Platform profit is the buyer-side fee. CR matches the buyer
          view.
        </template>
      </p>
    </div>

    <v-alert v-if="leadStore.error" type="error" variant="tonal" class="mb-4">{{ leadStore.error }}</v-alert>

    <div class="report-totals">
      <div>
        <span>Leads</span>
        <strong>{{ summary.leads.toLocaleString('en-US') }}</strong>
      </div>
      <template v-if="isBuyer">
        <div>
          <span>Sold</span>
          <strong>{{ summary.sold?.toLocaleString('en-US') ?? '0' }}</strong>
        </div>
        <div>
          <span>CR</span>
          <strong>{{ cr }}</strong>
        </div>
        <div>
          <span>Net earnings</span>
          <strong>{{ dollars(summary.earnings ?? 0) }}</strong>
        </div>
      </template>
      <template v-else>
        <div>
          <span>Sold</span>
          <strong>{{ summary.sold?.toLocaleString('en-US') ?? '0' }}</strong>
        </div>
        <div>
          <span>CR</span>
          <strong>{{ cr }}</strong>
        </div>
        <div>
          <span>Partner sales</span>
          <strong>{{ dollars(summary.partnerSales ?? 0) }}</strong>
        </div>
        <div>
          <span>Platform profit</span>
          <strong>{{ dollars(summary.profit ?? 0) }}</strong>
        </div>
      </template>
    </div>

    <UiTableCard title="Countries">
      <template #actions>
        <UiButton variant="outline" icon="mdi-download" :disabled="rows.length === 0" @click="exportCsv">
          Export
        </UiButton>
      </template>
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
          <CountryPick :code="item.country" />
        </template>
        <template #item.crValue="{ item }">
          {{ item.cr }}
        </template>
        <template #item.earnings="{ item }">
          {{ dollars(item.earnings) }}
        </template>
        <template #item.epl="{ item }">
          {{ dollars(item.epl) }}
        </template>
        <template #item.spent="{ item }">
          {{ dollars(item.spent) }}
        </template>
        <template #item.cpl="{ item }">
          {{ dollars(item.cpl) }}
        </template>
        <template #item.partnerSales="{ item }">
          {{ dollars(item.partnerSales) }}
        </template>
        <template #item.profit="{ item }">
          {{ dollars(item.profit) }}
        </template>
        <template #item.epp="{ item }">
          {{ dollars(item.epp) }}
        </template>
        <template #no-data>
          <div class="py-8 text-medium-emphasis">
            Лидов пока нет
          </div>
        </template>
      </UiDataTable>
    </UiTableCard>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import CountryPick from '@/components/CountryPick.vue'
import UiButton from '@/components/ui/UiButton.vue'
import UiDataTable from '@/components/ui/UiDataTable.vue'
import UiTableCard from '@/components/ui/UiTableCard.vue'
import { countryName } from '@/countries'
import { exportTableCsv, cellToString } from '@/tableCsvExport'
import { dollars } from '@/money'
import { useLeadStore, type CabinetLead } from '@/stores/leads'
import { useSessionStore } from '@/stores/session'
import { prepareTableHeaders } from '@/tableHeaders'

const session = useSessionStore()
const leadStore = useLeadStore()
const role = computed(() => session.payload?.user?.role ?? '')
const isBuyer = computed(() => role.value === 'buyer')
const isAdmin = computed(() => role.value === 'admin')

if (role.value === 'partner') {
  void useRouter().replace('/reports')
}

const perfMin = {
  country: 124,
  leads: 88,
  sold: 80,
  approved: 96,
  rejected: 96,
  crValue: 72,
  earnings: 120,
  epl: 88,
  partnerSales: 120,
  profit: 120,
  epp: 88,
}

const buyerHeaders = prepareTableHeaders(
  [
    { title: 'Type', key: 'country' },
    { title: 'Leads', key: 'leads', align: 'end' as const },
    { title: 'Sold', key: 'sold', align: 'end' as const },
    { title: 'Approved', key: 'approved', align: 'end' as const },
    { title: 'Rejected', key: 'rejected', align: 'end' as const },
    { title: 'CR', key: 'crValue', align: 'end' as const },
    { title: 'Net earnings', key: 'earnings', align: 'end' as const },
    { title: 'EPL', key: 'epl', align: 'end' as const },
  ],
  perfMin,
)

const adminHeaders = prepareTableHeaders(
  [
    { title: 'Type', key: 'country' },
    { title: 'Leads', key: 'leads', align: 'end' as const },
    { title: 'Sold', key: 'sold', align: 'end' as const },
    { title: 'Approved', key: 'approved', align: 'end' as const },
    { title: 'Rejected', key: 'rejected', align: 'end' as const },
    { title: 'CR', key: 'crValue', align: 'end' as const },
    { title: 'Partner sales', key: 'partnerSales', align: 'end' as const },
    { title: 'Platform profit', key: 'profit', align: 'end' as const },
    { title: 'EPP', key: 'epp', align: 'end' as const },
  ],
  perfMin,
)

const headers = computed(() => {
  if (isAdmin.value) return adminHeaders
  return buyerHeaders
})

function isSoldStatus(status: string): boolean {
  return status === 'sold'
}

function countsTowardMoney(status: string): boolean {
  return status === 'sold' || status === 'approved' || status === 'rejected' || status === 'delivered'
}

function formatCr(approved: number, sold: number, rejected: number): string {
  const base = sold + approved + rejected
  if (base === 0) {
    return '0.00%'
  }
  return `${((approved / base) * 100).toFixed(2)}%`
}

function markupPercent(): number {
  return session.payload?.user?.organization?.markupPercent ?? 10
}

function platformFeeCents(catalogCents: number, feeCents?: number): number {
  if (feeCents != null) {
    return feeCents
  }
  if (catalogCents <= 0) {
    return 0
  }
  const percent = markupPercent()
  return Math.floor((catalogCents * percent + 50) / 100)
}

function buyerNetCents(lead: CabinetLead): number {
  if (lead.buyerNetCents != null) {
    return lead.buyerNetCents
  }
  return lead.priceCents - platformFeeCents(lead.priceCents)
}

function aggregateBuyer() {
  const grouped = new Map<string, { leads: number; sold: number; approved: number; rejected: number; earnings: number }>()
  for (const lead of leadStore.leads) {
    const country = lead.country || '—'
    const row = grouped.get(country) ?? { leads: 0, sold: 0, approved: 0, rejected: 0, earnings: 0 }
    row.leads += 1
    if (isSoldStatus(lead.status)) {
      row.sold += 1
    }
    if (lead.status === 'approved') {
      row.approved += 1
    }
    if (lead.status === 'rejected') {
      row.rejected += 1
    }
    if (countsTowardMoney(lead.status)) {
      row.earnings += buyerNetCents(lead) / 100
    }
    grouped.set(country, row)
  }

  return [...grouped.entries()]
    .map(([country, row]) => {
      const crBase = row.sold + row.approved + row.rejected
      return {
        country,
        ...row,
        cr: formatCr(row.approved, row.sold, row.rejected),
        crValue: crBase === 0 ? 0 : (row.approved / crBase) * 100,
        epl: row.leads === 0 ? 0 : row.earnings / row.leads,
      }
    })
    .sort((left, right) => right.leads - left.leads)
}

function aggregateAdmin() {
  const grouped = new Map<
    string,
    { leads: number; sold: number; approved: number; rejected: number; partnerSales: number; profit: number }
  >()
  for (const lead of leadStore.leads) {
    const country = lead.country || '—'
    const row = grouped.get(country) ?? { leads: 0, sold: 0, approved: 0, rejected: 0, partnerSales: 0, profit: 0 }
    row.leads += 1
    if (isSoldStatus(lead.status)) {
      row.sold += 1
    }
    if (lead.status === 'approved') {
      row.approved += 1
    }
    if (lead.status === 'rejected') {
      row.rejected += 1
    }
    if (countsTowardMoney(lead.status)) {
      row.partnerSales += lead.priceCents / 100
      row.profit += platformFeeCents(lead.priceCents, lead.platformFeeCents) / 100
    }
    grouped.set(country, row)
  }

  return [...grouped.entries()]
    .map(([country, row]) => {
      const crBase = row.sold + row.approved + row.rejected
      return {
        country,
        ...row,
        cr: formatCr(row.approved, row.sold, row.rejected),
        crValue: crBase === 0 ? 0 : (row.approved / crBase) * 100,
        epp: row.leads === 0 ? 0 : row.profit / row.leads,
      }
    })
    .sort((left, right) => right.leads - left.leads)
}

const rows = computed(() => {
  if (isAdmin.value) return aggregateAdmin()
  return aggregateBuyer()
})

function exportCsv(): void {
  exportTableCsv('performance.csv', headers.value, rows.value, performanceExportCell)
}

function performanceExportCell(key: string, item: (typeof rows.value)[number]): string {
  switch (key) {
    case 'country':
      return countryName(item.country)
    case 'crValue':
      return item.cr
    case 'earnings':
    case 'epl':
    case 'partnerSales':
    case 'profit':
    case 'epp':
      return dollars((item as Record<string, number>)[key] ?? 0)
    default:
      return cellToString((item as Record<string, unknown>)[key])
  }
}

type PerfSummary = {
  leads: number
  sold?: number
  approved?: number
  rejected?: number
  earnings?: number
  partnerSales?: number
  profit?: number
}

const summary = computed((): PerfSummary => {
  if (isAdmin.value) {
    return rows.value.reduce(
      (sum, row) => {
        const adminRow = row as {
          leads: number
          sold: number
          approved: number
          rejected: number
          partnerSales: number
          profit: number
        }
        return {
          leads: sum.leads + adminRow.leads,
          sold: (sum.sold ?? 0) + adminRow.sold,
          approved: (sum.approved ?? 0) + adminRow.approved,
          rejected: (sum.rejected ?? 0) + adminRow.rejected,
          partnerSales: (sum.partnerSales ?? 0) + adminRow.partnerSales,
          profit: (sum.profit ?? 0) + adminRow.profit,
        }
      },
      { leads: 0, sold: 0, approved: 0, rejected: 0, partnerSales: 0, profit: 0 },
    )
  }
  return rows.value.reduce(
    (sum, row) => {
      const buyerRow = row as {
        leads: number
        sold: number
        approved: number
        rejected: number
        earnings: number
      }
      return {
        leads: sum.leads + buyerRow.leads,
        sold: (sum.sold ?? 0) + buyerRow.sold,
        approved: (sum.approved ?? 0) + buyerRow.approved,
        rejected: (sum.rejected ?? 0) + buyerRow.rejected,
        earnings: (sum.earnings ?? 0) + buyerRow.earnings,
      }
    },
    { leads: 0, sold: 0, approved: 0, rejected: 0, earnings: 0 },
  )
})

const cr = computed(() => {
  const s = summary.value as { approved: number; sold: number; rejected: number }
  return formatCr(s.approved, s.sold, s.rejected)
})

onMounted(() => {
  if (leadStore.leads.length === 0) {
    void leadStore.load()
  }
})
</script>

<style scoped>
.performance__hint {
  color: var(--lm-muted);
  font-size: 13px;
  margin: 6px 0 0;
  max-width: none;
}
</style>