<template>
  <div>
    <div class="mb-4">
      <h1>Performance</h1>
      <p v-if="isBuyer">Leads you mined, grouped by country</p>
      <p v-else-if="isPartner">Leads you purchased, grouped by country</p>
      <p v-else>Organization leads, grouped by country</p>
      <p class="performance__hint">
        <template v-if="isBuyer">
          Sold means matched to a partner order. Net earnings are catalog price minus the platform fee. CR is approved ÷
          sold leads with a conversion outcome.
        </template>
        <template v-else-if="isPartner">
          Spent is the preset catalog price per lead. CR is approved ÷ purchased leads with a conversion outcome.
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
      <template v-if="isPartner">
        <div>
          <span>CR</span>
          <strong>{{ cr }}</strong>
        </div>
        <div>
          <span>Spent</span>
          <strong>{{ dollars(summary.spent ?? 0) }}</strong>
        </div>
      </template>
      <template v-else-if="isBuyer">
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
            {{ isPartner ? 'Купленных лидов пока нет' : 'Лидов пока нет' }}
          </div>
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
import { useLeadStore, type CabinetLead } from '@/stores/leads'
import { useSessionStore } from '@/stores/session'

const session = useSessionStore()
const leadStore = useLeadStore()
const role = computed(() => session.payload?.user?.role ?? '')
const isBuyer = computed(() => role.value === 'buyer')
const isPartner = computed(() => role.value === 'partner')
const isAdmin = computed(() => role.value === 'admin')

const buyerHeaders = [
  { title: 'Type', key: 'country' },
  { title: 'Leads', key: 'leads', align: 'end' as const },
  { title: 'Sold', key: 'sold', align: 'end' as const },
  { title: 'Approved', key: 'approved', align: 'end' as const },
  { title: 'Rejected', key: 'rejected', align: 'end' as const },
  { title: 'CR', key: 'crValue', align: 'end' as const },
  { title: 'Net earnings', key: 'earnings', align: 'end' as const },
  { title: 'EPL', key: 'epl', align: 'end' as const },
]

const partnerHeaders = [
  { title: 'Type', key: 'country' },
  { title: 'Leads', key: 'leads', align: 'end' as const },
  { title: 'Approved', key: 'approved', align: 'end' as const },
  { title: 'Rejected', key: 'rejected', align: 'end' as const },
  { title: 'CR', key: 'crValue', align: 'end' as const },
  { title: 'Spent', key: 'spent', align: 'end' as const },
  { title: 'CPL', key: 'cpl', align: 'end' as const },
]

const adminHeaders = [
  { title: 'Type', key: 'country' },
  { title: 'Leads', key: 'leads', align: 'end' as const },
  { title: 'Sold', key: 'sold', align: 'end' as const },
  { title: 'Approved', key: 'approved', align: 'end' as const },
  { title: 'Rejected', key: 'rejected', align: 'end' as const },
  { title: 'CR', key: 'crValue', align: 'end' as const },
  { title: 'Partner sales', key: 'partnerSales', align: 'end' as const },
  { title: 'Platform profit', key: 'profit', align: 'end' as const },
  { title: 'EPP', key: 'epp', align: 'end' as const },
]

const headers = computed(() => {
  if (isPartner.value) return partnerHeaders
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

function partnerSpentCents(lead: CabinetLead): number {
  return lead.partnerPriceCents ?? lead.priceCents
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

function aggregatePartner() {
  const grouped = new Map<string, { leads: number; awaiting: number; approved: number; rejected: number; spent: number }>()
  for (const lead of leadStore.leads) {
    const country = lead.country || '—'
    const row = grouped.get(country) ?? { leads: 0, awaiting: 0, approved: 0, rejected: 0, spent: 0 }
    row.leads += 1
    row.spent += partnerSpentCents(lead) / 100
    if (isSoldStatus(lead.status)) {
      row.awaiting += 1
    }
    if (lead.status === 'approved') {
      row.approved += 1
    }
    if (lead.status === 'rejected') {
      row.rejected += 1
    }
    grouped.set(country, row)
  }

  return [...grouped.entries()]
    .map(([country, row]) => {
      const crBase = row.awaiting + row.approved + row.rejected
      return {
        country,
        leads: row.leads,
        approved: row.approved,
        rejected: row.rejected,
        spent: row.spent,
        cr: formatCr(row.approved, row.awaiting, row.rejected),
        crValue: crBase === 0 ? 0 : (row.approved / crBase) * 100,
        cpl: row.leads === 0 ? 0 : row.spent / row.leads,
      }
    })
    .sort((left, right) => right.leads - left.leads)
}

const rows = computed(() => {
  if (isPartner.value) return aggregatePartner()
  if (isAdmin.value) return aggregateAdmin()
  return aggregateBuyer()
})

const summary = computed(() => {
  if (isPartner.value) {
    const spent = rows.value.reduce((sum, row) => sum + (row.spent as number), 0)
    return { leads: rows.value.reduce((sum, row) => sum + row.leads, 0), spent }
  }
  if (isAdmin.value) {
    return rows.value.reduce(
      (sum, row) => ({
        leads: sum.leads + row.leads,
        sold: sum.sold + row.sold,
        approved: sum.approved + row.approved,
        rejected: sum.rejected + row.rejected,
        partnerSales: sum.partnerSales + row.partnerSales,
        profit: sum.profit + row.profit,
      }),
      { leads: 0, sold: 0, approved: 0, rejected: 0, partnerSales: 0, profit: 0 },
    )
  }
  return rows.value.reduce(
    (sum, row) => ({
      leads: sum.leads + row.leads,
      sold: sum.sold + row.sold,
      approved: sum.approved + row.approved,
      rejected: sum.rejected + row.rejected,
      earnings: sum.earnings + row.earnings,
    }),
    { leads: 0, sold: 0, approved: 0, rejected: 0, earnings: 0 },
  )
})

const cr = computed(() => {
  if (isPartner.value) {
    let awaiting = 0
    let approved = 0
    let rejected = 0
    for (const lead of leadStore.leads) {
      if (isSoldStatus(lead.status)) awaiting += 1
      if (lead.status === 'approved') approved += 1
      if (lead.status === 'rejected') rejected += 1
    }
    return formatCr(approved, awaiting, rejected)
  }
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
  max-width: 52rem;
}
</style>