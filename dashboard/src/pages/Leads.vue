<template>
  <div>
    <div class="mb-4">
      <h1>{{ pageTitle }}</h1>
      <p>{{ leadLine }}</p>
    </div>

    <v-tabs v-if="isBuyer" v-model="tab" color="primary" class="lead-tabs">
      <v-tab value="journal">Leads</v-tab>
      <v-tab value="send">Send Lead</v-tab>
    </v-tabs>

    <section v-if="showJournal && isBuyer" class="ui-filter-panel key-panel">
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

    <section v-if="showJournal" class="ui-filter-panel">
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

    <v-alert v-if="showJournal && leadStore.error" type="error" variant="tonal" class="mb-4">{{ leadStore.error }}</v-alert>

    <LeadDetailDialog
      v-model="detailOpen"
      :lead="detailLead"
      :loading="detailLoading"
      :error="detailError"
      @converted="onLeadConverted"
    />

    <UiTableCard v-if="showJournal" :title="pageTitle">
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
        class="lead-table"
        @click:row="onRow"
      >
        <template #item.status="{ item }">
          <StatusChip :status="item.status" :label="statusLabel(item.status)" />
        </template>
        <template #item.country="{ item }">
          <CountryPick :code="item.country" />
        </template>
        <template #item.gender="{ item }">
          {{ genderLabel(item.gender) }}
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
        <template #item.actions="{ item }">
          <UiButton variant="ghost" icon="mdi-eye-outline" @click.stop="openLead(item)">View</UiButton>
        </template>
        <template #no-data>
          <div class="py-8 text-medium-emphasis">{{ isBuyer ? 'Лидов пока нет' : 'Купленных лидов пока нет' }}</div>
        </template>
      </UiDataTable>
    </UiTableCard>

    <template v-if="isBuyer && tab === 'send'">
      <v-alert v-if="!apiKey && !keyError" type="warning" variant="tonal" class="mb-4">
        Create an API key on the Leads tab first.
      </v-alert>
      <v-alert v-if="sendResult" type="success" variant="tonal" class="mb-4">{{ sendResult }}</v-alert>
      <v-alert v-if="sendError" type="error" variant="tonal" class="mb-4">{{ sendError }}</v-alert>

      <section class="ui-filter-panel">
        <p class="send-lead__hint">
          The lead is posted with your API key. Change the JSON and send it. <code>gender</code> is optional.
        </p>
        <label class="send-lead__label" for="lead-body">Body</label>
        <textarea id="lead-body" v-model="body" class="send-lead__body" spellcheck="false" />
        <div class="send-lead__actions">
          <UiButton variant="outline" :disabled="sending" @click="resetBody">Reset</UiButton>
          <UiButton :loading="sending" :disabled="!apiKey" @click="sendLead">Send Lead</UiButton>
        </div>
      </section>
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { api } from '@/api'
import CountryPick from '@/components/CountryPick.vue'
import LeadDetailDialog from '@/components/LeadDetailDialog.vue'
import StatusChip from '@/components/StatusChip.vue'
import UiButton from '@/components/ui/UiButton.vue'
import UiDataTable from '@/components/ui/UiDataTable.vue'
import UiField from '@/components/ui/UiField.vue'
import UiSelect from '@/components/ui/UiSelect.vue'
import UiTableCard from '@/components/ui/UiTableCard.vue'
import { apiOrigin } from '@/host'
import { sampleLead, sampleLeadJson } from '@/leadBody'
import { genderLabel } from '@/marketOptions'
import { useLeadStore, type CabinetLead } from '@/stores/leads'
import { useSessionStore } from '@/stores/session'

const session = useSessionStore()
const leadStore = useLeadStore()
const isBuyer = computed(() => session.payload?.user?.role === 'buyer')
const isPartner = computed(() => session.payload?.user?.role === 'partner')
const tab = ref<'journal' | 'send'>('journal')
const showJournal = computed(() => !isBuyer.value || tab.value === 'journal')
const pageTitle = computed(() => (isPartner.value ? 'My leads' : 'Leads'))
const leadLine = computed(() => {
  const role = session.payload?.user?.role
  if (role === 'partner') return 'Leads you purchased and their conversion status.'
  if (role === 'admin') return 'Leads of this organization'
  return 'Leads you mined'
})
const apiKey = ref('')
const keyBusy = ref(false)
const keyError = ref('')
const body = ref(sampleLeadJson())
const sending = ref(false)
const sendError = ref('')
const sendResult = ref('')
const status = ref<string | null>(null)
const country = ref<string | null>(null)
const detailOpen = ref(false)
const detailLead = ref<CabinetLead | null>(null)
const detailLoading = ref(false)
const detailError = ref('')

const isAdmin = computed(() => session.payload?.user?.role === 'admin')

const headers = computed(() => {
  const rows: { title: string; key: string; sortable?: boolean; width?: number }[] = [
    { title: 'Status', key: 'status' },
    { title: 'Phone', key: 'phone' },
    { title: 'Email', key: 'email' },
    { title: 'Country', key: 'country' },
    { title: 'Gender', key: 'gender' },
    { title: 'IP', key: 'ip' },
    { title: 'Aff Sub', key: 'affSub' },
  ]
  if (isAdmin.value) {
    rows.push(
      { title: 'Buyer', key: 'buyerEmail' },
      { title: 'Partner', key: 'sellerEmail' },
    )
  }
  rows.push(
    { title: 'Created At', key: 'createdAt' },
    { title: '', key: 'actions', sortable: false, width: 100 },
  )
  return rows
})

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
    `  -d '${JSON.stringify(sampleLead)}'`,
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

function onRow(_event: PointerEvent, row: { item: CabinetLead }): void {
  void openLead(row.item)
}

async function openLead(item: CabinetLead): Promise<void> {
  detailOpen.value = true
  detailLead.value = null
  detailError.value = ''
  detailLoading.value = true
  const lead = await leadStore.loadOne(item.uuid)
  detailLoading.value = false
  if (lead === null) {
    detailError.value = 'Lead was not loaded'
    return
  }
  detailLead.value = lead
}

function onLeadConverted(lead: CabinetLead): void {
  detailLead.value = lead
  void leadStore.load()
  void session.load()
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

function resetBody(): void {
  body.value = sampleLeadJson()
  sendError.value = ''
  sendResult.value = ''
}

async function sendLead(): Promise<void> {
  sendError.value = ''
  sendResult.value = ''
  let payload: unknown
  try {
    payload = JSON.parse(body.value)
  } catch {
    sendError.value = 'The JSON does not parse'
    return
  }
  if (payload === null || typeof payload !== 'object' || Array.isArray(payload)) {
    sendError.value = 'The body must be a JSON object'
    return
  }

  sending.value = true
  try {
    const response = await fetch(`${apiOrigin(window.location.hostname)}/v1/lead`, {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        Authorization: `Bearer ${apiKey.value}`,
      },
      body: JSON.stringify(payload),
    })
    const decoded = await response.json().catch(() => null)
    if (!response.ok) {
      sendError.value = sendFailure(decoded, response.status)
      return
    }
    const uuid = decoded && typeof decoded === 'object' && 'uuid' in decoded ? String(decoded.uuid) : ''
    sendResult.value = uuid ? `Lead accepted: ${uuid}` : 'Lead accepted'
    void leadStore.load()
  } catch {
    sendError.value = 'The lead was not sent'
  } finally {
    sending.value = false
  }
}

function sendFailure(decoded: unknown, status: number): string {
  if (decoded && typeof decoded === 'object') {
    const record = decoded as { error?: unknown; errors?: Record<string, unknown> }
    if (record.errors) {
      return Object.values(record.errors).map(String).join(' ')
    }
    if (typeof record.error === 'string' && record.error !== '') {
      return record.error
    }
  }
  return `The lead was not accepted: ${status}`
}

async function copyKey(): Promise<void> {
  if (!apiKey.value) return
  await navigator.clipboard.writeText(apiKey.value)
}

function exportCsv(): void {
  const header = isAdmin.value
    ? ['Status', 'Phone', 'Email', 'Country', 'Gender', 'IP', 'Aff Sub', 'Buyer', 'Partner', 'Created At']
    : ['Status', 'Phone', 'Email', 'Country', 'Gender', 'IP', 'Aff Sub', 'Created At']
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
  const row = [lead.status, lead.phone, lead.email, lead.country, lead.gender ? genderLabel(lead.gender) : '', lead.ip, lead.affSub]
  if (isAdmin.value) {
    row.push(lead.buyerEmail ?? '', lead.sellerEmail ?? '')
  }
  row.push(lead.createdAt)
  return row
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

.lead-table :deep(tbody tr) {
  cursor: pointer;
}

.lead-tabs {
  margin-bottom: 16px;
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

.send-lead__hint {
  color: var(--lm-muted);
  font-size: 14px;
  margin: 0 0 16px;
}

.send-lead__label {
  color: var(--lm-foreground);
  display: block;
  font-size: 13px;
  font-weight: 600;
  margin-bottom: 8px;
}

.send-lead__body {
  background: #f7f7ff;
  border: 1px solid #cdd0e6;
  border-radius: 10px;
  box-sizing: border-box;
  color: #303861;
  font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
  font-size: 13px;
  line-height: 1.5;
  min-height: 280px;
  padding: 12px;
  resize: vertical;
  width: 100%;
}

.send-lead__body:focus {
  border-color: #9486ff;
  outline: none;
}

.send-lead__actions {
  display: flex;
  gap: 8px;
  justify-content: flex-end;
  margin-top: 16px;
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
