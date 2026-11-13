<template>
  <div>
    <div class="mb-4">
      <h1>Leads</h1>
      <p>{{ isBuyer ? 'Leads you mined' : 'Leads you bought' }}</p>
    </div>

    <section v-if="isBuyer" class="ui-filter-panel key-panel">
      <div class="key-panel__head">
        <div>
          <h2>API key</h2>
          <p>The buyer posts a lead with this key. A new key replaces the previous one.</p>
        </div>
        <UiButton :loading="keyBusy" @click="generateKey">
          {{ apiKey ? 'Replace key' : 'Generate' }}
        </UiButton>
      </div>
      <p v-if="keyError" class="key-panel__error">{{ keyError }}</p>
      <div v-else-if="apiKey" class="key-panel__value">
        <input class="ui-input" :value="apiKey" readonly>
        <UiButton variant="outline" @click="copyKey">Copy</UiButton>
      </div>
      <pre v-if="apiKey" class="key-panel__example">{{ example }}</pre>
    </section>

    <section class="ui-filter-panel">
      <div class="lead-filters">
        <UiField label="Status" icon="mdi-list-status">
          <UiSelect v-model="status" :items="statuses" placeholder="All statuses" empty-label="All statuses" clearable />
        </UiField>
        <UiField label="Country" icon="mdi-earth">
          <UiSelect v-model="country" :items="countries" placeholder="All countries" empty-label="All countries" clearable />
        </UiField>
      </div>
      <div v-if="filteredOut" class="lead-filters__actions">
        <UiButton variant="ghost" @click="clearFilters">Clear</UiButton>
      </div>
    </section>

    <v-alert v-if="leadStore.error" type="error" variant="tonal" class="mb-4">{{ leadStore.error }}</v-alert>

    <UiTableCard title="Leads">
      <template #actions>
        <UiButton variant="outline" icon="mdi-refresh" :loading="leadStore.loading" @click="leadStore.load()">
          Refresh
        </UiButton>
        <UiButton variant="outline" icon="mdi-download" @click="exportCsv">Export</UiButton>
      </template>
      <UiDataTable
        :headers="headers"
        :items="filtered"
        :loading="leadStore.loading"
        :items-per-page="25"
        :items-per-page-options="[25, 50, 100]"
        item-value="uuid"
        hover
      >
        <template #item.status="{ item }">
          <StatusChip :status="item.status" :label="statusLabel(item.status)" />
        </template>
        <template #item.country="{ item }">
          {{ item.country ? `${flag(item.country)} ${item.country}` : '—' }}
        </template>
        <template #item.email="{ item }">
          {{ item.email || '—' }}
        </template>
        <template #item.affSub="{ item }">
          {{ item.affSub || '—' }}
        </template>
        <template #item.createdAt="{ item }">
          {{ when(item.createdAt) }}
        </template>
        <template #no-data>
          <div class="py-8 text-medium-emphasis">{{ isBuyer ? 'Лидов пока нет' : 'Купленных лидов пока нет' }}</div>
        </template>
      </UiDataTable>
    </UiTableCard>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { api } from '@/api'
import StatusChip from '@/components/StatusChip.vue'
import UiButton from '@/components/ui/UiButton.vue'
import UiDataTable from '@/components/ui/UiDataTable.vue'
import UiField from '@/components/ui/UiField.vue'
import UiSelect from '@/components/ui/UiSelect.vue'
import UiTableCard from '@/components/ui/UiTableCard.vue'
import { apiOrigin } from '@/host'
import { flag } from '@/money'
import { useLeadStore, type CabinetLead } from '@/stores/leads'
import { useSessionStore } from '@/stores/session'

const session = useSessionStore()
const leadStore = useLeadStore()
const isBuyer = computed(() => session.payload?.user?.role === 'buyer')
const apiKey = ref('')
const keyBusy = ref(false)
const keyError = ref('')
const status = ref<string | null>(null)
const country = ref<string | null>(null)

const headers = [
  { title: 'Status', key: 'status' },
  { title: 'Phone', key: 'phone' },
  { title: 'Email', key: 'email' },
  { title: 'Country', key: 'country' },
  { title: 'IP', key: 'ip' },
  { title: 'Aff Sub', key: 'affSub' },
  { title: 'Buyer', key: 'buyerEmail' },
  { title: 'Created At', key: 'createdAt' },
]

const statusLabels: Record<string, string> = {
  new: 'New',
  sold: 'Sold',
  in_stock: 'In stock',
  delivered: 'Delivered',
  approved: 'Approved',
  rejected: 'Rejected',
}

function statusLabel(value: string): string {
  return statusLabels[value] ?? value.charAt(0).toUpperCase() + value.slice(1)
}

const statuses = computed(() => [...new Set(leadStore.leads.map((lead) => lead.status))].sort().map((value) => ({
  title: statusLabel(value),
  value,
})))
const countries = computed(() => [...new Set(leadStore.leads.map((lead) => lead.country).filter(Boolean))].sort())

const filtered = computed(() => leadStore.leads.filter((lead) => {
  if (status.value && lead.status !== status.value) return false
  if (country.value && lead.country !== country.value) return false
  return true
}))

const filteredOut = computed(() => filtered.value.length !== leadStore.leads.length)

const example = computed(() => {
  const origin = apiOrigin(window.location.hostname)
  return [
    `curl -X POST ${origin}/v1/lead \\`,
    `  -H 'Authorization: Bearer ${apiKey.value}' \\`,
    `  -H 'Content-Type: application/json' \\`,
    `  -d '{"phone":"+4915112345678","country_code":"DE","ip":"192.0.2.10","aff_sub":"demo"}'`,
  ].join('\n')
})

function clearFilters(): void {
  status.value = null
  country.value = null
}

function when(value: string): string {
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return value
  return date.toLocaleString(undefined, { hour12: false })
}

async function loadKey(): Promise<void> {
  if (!isBuyer.value) return
  const response = await api('/api-key')
  if (!response.ok) {
    keyError.value = `Ключ не прочитался: ${response.status}`
    return
  }
  const body = await response.json() as { apiKey?: string | null }
  apiKey.value = body.apiKey ?? ''
}

async function generateKey(): Promise<void> {
  keyBusy.value = true
  keyError.value = ''
  try {
    const response = await api('/api-key', { method: 'POST' })
    if (!response.ok) {
      keyError.value = `Ключ не создался: ${response.status}`
      return
    }
    const body = await response.json() as { apiKey?: string }
    apiKey.value = body.apiKey ?? ''
  } catch {
    keyError.value = 'Ключ не создался'
  } finally {
    keyBusy.value = false
  }
}

async function copyKey(): Promise<void> {
  if (!apiKey.value) return
  await navigator.clipboard.writeText(apiKey.value)
}

function exportCsv(): void {
  const header = ['Status', 'Phone', 'Email', 'Country', 'IP', 'Aff Sub', 'Buyer', 'Created At']
  const lines = [header, ...filtered.value.map(csvRow)].map((row) => row.map(csvCell).join(',')).join('\n')
  const blob = new Blob([lines], { type: 'text/csv;charset=utf-8' })
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = 'leads.csv'
  link.click()
  URL.revokeObjectURL(url)
}

function csvRow(lead: CabinetLead): string[] {
  return [lead.status, lead.phone, lead.email, lead.country, lead.ip, lead.affSub, lead.buyerEmail, lead.createdAt]
}

function csvCell(value: string): string {
  return `"${value.replaceAll('"', '""')}"`
}

onMounted(() => {
  void leadStore.load()
  void loadKey()
})
</script>

<style scoped>
.key-panel__head {
  align-items: flex-start;
  display: flex;
  gap: 16px;
  justify-content: space-between;
}

.key-panel__head h2 {
  font-size: 20px;
  letter-spacing: -0.5px;
  margin: 0;
}

.key-panel__head p,
.key-panel__error {
  color: var(--lm-muted);
  font-size: 14px;
  margin: 4px 0 0;
}

.key-panel__error {
  color: #c42f50;
}

.key-panel__value {
  display: grid;
  gap: 8px;
  grid-template-columns: minmax(0, 1fr) auto;
  margin-top: 16px;
}

.key-panel__value input {
  background: #fff;
  border: 1px solid #cdd0e6;
  border-radius: 9px;
  box-sizing: border-box;
  color: var(--lm-foreground);
  font: inherit;
  font-size: 13px;
  height: var(--lm-control-height);
  min-width: 0;
  padding: 0 12px;
  width: 100%;
}

.key-panel__example {
  background: #f7f7ff;
  border-radius: 10px;
  color: #303861;
  font-size: 12px;
  margin: 12px 0 0;
  overflow-x: auto;
  padding: 12px;
  white-space: pre-wrap;
}

@media (max-width: 700px) {
  .key-panel__head,
  .key-panel__value {
    grid-template-columns: minmax(0, 1fr);
  }

  .key-panel__head {
    display: grid;
  }
}
</style>
