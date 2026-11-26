<template>
  <div class="transactions">
    <div class="page-head">
      <div>
        <h1>Transactions</h1>
        <p>{{ isBuyer ? 'Every movement of your wallet: lead payouts, deposits and withdrawals.' : 'Every movement of your wallet: deposits, lead charges and withdrawals.' }}</p>
      </div>
      <PeriodPicker v-model="choice" :now="now" />
    </div>

    <WalletFlowStrip :flow="flow" :period="period" :now="now" :active="filterType" @pick="filterType = $event" />

    <div class="transactions__grid">
      <UiCard title="Money flow" :subtitle="chartSubtitle">
        <template #actions>
          <ChartLegend :items="legend" />
        </template>
        <ComboChart
          :labels="flowBuckets.map((item) => item.bucket.label)"
          :ticks="flowBuckets.map((item) => item.bucket.tick)"
          :bars="chartTypes.map((type) => ({ label: type.plural, color: type.color, values: flowBuckets.map((item) => item.totals[type.value]) }))"
          :line="{ label: 'Balance', color: BALANCE_COLOR, values: flowBuckets.map((item) => item.balance) }"
          :left-format="compactDollars"
          :right-format="compactDollars"
          :tooltip="flowTooltip"
          :height="236"
        />
      </UiCard>

      <UiCard title="Balance now" :subtitle="isBuyer ? 'Payouts land here as your leads are paid' : 'What is free to spend on new leads'">
        <div class="transactions__now">
          <strong class="transactions__balance">{{ dollars(balance) }}</strong>
          <dl v-if="isBuyer" class="transactions__reserve">
            <div>
              <dt>Paid to you in this period</dt>
              <dd>{{ dollars(payouts.amount) }}</dd>
            </div>
            <div>
              <dt>Leads paid</dt>
              <dd>{{ payouts.count.toLocaleString('en-US') }}</dd>
            </div>
            <div>
              <dt>Average payout</dt>
              <dd>{{ payouts.count ? dollars(payouts.amount / payouts.count) : '—' }}</dd>
            </div>
          </dl>
          <dl v-else class="transactions__reserve">
            <div>
              <dt>Reserved by open orders</dt>
              <dd>{{ dollars(reserved) }}</dd>
            </div>
            <div>
              <dt>Free after reserve</dt>
              <dd :class="{ 'transactions__short': free < 0 }">{{ free < 0 ? '−' : '' }}{{ dollars(Math.abs(free)) }}</dd>
            </div>
          </dl>
          <p v-if="!isBuyer" class="transactions__hint" :class="{ 'transactions__hint--warn': free < 0 }">
            <v-icon :icon="free < 0 ? 'mdi-alert-circle-outline' : 'mdi-information-outline'" size="15" />
            {{ free < 0 ? 'Open orders need more than your balance. Add funds so they keep receiving leads.' : 'Open orders are not charged upfront. Each lead is charged when it arrives.' }}
          </p>
          <div class="transactions__actions">
            <template v-if="session.can('wallet.manage')">
              <UiButton variant="primary" icon="mdi-plus" :loading="wallet.busy" @click="wallet.deposit()">Add funds</UiButton>
              <UiButton variant="outline" icon="mdi-arrow-top-right" :disabled="wallet.busy || balance <= 0" @click="wallet.withdraw()">Withdraw</UiButton>
            </template>
          </div>
        </div>
      </UiCard>
    </div>

    <CollapsibleFilterPanel class="transactions__filters" aria-label="Transaction filters" :active-count="activeFilterCount">
      <template #mobile-actions>
        <UiButton variant="ghost" icon="mdi-refresh" @click="resetFilters">Reset</UiButton>
      </template>
      <div class="transactions__filter-row">
        <div class="transactions__filter-main" :class="{ 'transactions__filter-main--buyer': isBuyer }">
          <UiLabeledField label="Type">
            <UiSelect v-model="filterTypeOrNull" :items="typeItems" placeholder="All types" empty-label="All types" clearable />
          </UiLabeledField>
          <UiLabeledField label="Search">
            <UiInput v-model="filterSearch" placeholder="Lead, email, LD-… or OR-…" />
          </UiLabeledField>
          <UiLabeledField v-if="!isBuyer" label="Payment model">
            <UiSelect v-model="filterModel" :items="paymentModels" placeholder="Any model" empty-label="Any model" clearable />
          </UiLabeledField>
          <UiLabeledField label="Country">
            <CountryMultiSelect v-model="filterCountries" />
          </UiLabeledField>
        </div>
        <UiButton class="filter-panel-reset" variant="ghost" icon="mdi-refresh" @click="resetFilters">Reset</UiButton>
      </div>
    </CollapsibleFilterPanel>

    <UiTableCard :title="tableTitle">
      <template #actions>
        <TableCardToolbar
          :refresh-loading="wallet.loading"
          :export-disabled="filtered.length === 0 || !session.can('reports.export')"
          @refresh="refresh()"
          @export="exportCsv"
        />
      </template>
      <UiDataTable
        v-model:page="page"
        v-model:items-per-page="itemsPerPage"
        v-model:sort-by="sortBy"
        class="transactions__table"
        :headers="headers"
        :items="filtered"
        :loading="wallet.loading && wallet.transactions.length === 0"
        :items-per-page-options="[100, 200, 500]"
        item-value="id"
        hover
        :cell-copy-value="cellCopy"
      >
        <template #item.at="{ item }">
          <span class="tx-date">
            <strong>{{ dayLabel(item.at) }}</strong>
            <small>{{ timeOf(item.at) }}</small>
          </span>
        </template>
        <template #item.type="{ item }">
          <span class="tx-type" :style="{ '--tone': entryTypeView(item.type).color }">
            <span class="tx-type__icon"><v-icon :icon="entryTypeView(item.type).icon" size="15" /></span>
            {{ entryTypeView(item.type).label }}
          </span>
        </template>
        <template #item.details="{ item }">
          <span class="tx-details">
            <button v-if="item.leadUuid" type="button" class="tx-details__lead" @click="openLead(item)">
              <strong>{{ leadName(item) }}</strong>
              <span>{{ leadDisplayId(item.leadUuid) }}</span>
            </button>
            <strong v-else>{{ item.type === 'deposit' ? 'Funds added' : 'Funds withdrawn' }}</strong>
            <small>{{ detailNote(item) }}</small>
          </span>
        </template>
        <template #item.country="{ item }">
          <CountryPick :code="item.country" />
        </template>
        <template #item.model="{ item }">
          <ModelTag :model="item.model" />
        </template>
        <template #item.orderId="{ item }">
          <router-link v-if="item.orderId" class="tx-order" :to="{ path: '/leads', query: { order: item.orderId } }">
            {{ orderDisplayId(item.orderId) }}
          </router-link>
          <span v-else class="tx-muted">—</span>
        </template>
        <template #item.signed="{ item }">
          <span class="tx-amount" :class="{ 'tx-amount--in': item.signed > 0 }">{{ signedDollars(item.signed) }}</span>
        </template>
        <template #item.balanceAfter="{ item }">
          <span class="tx-balance">{{ dollars(item.balanceAfter) }}</span>
        </template>
        <template #no-data>
          <div class="py-8 text-medium-emphasis">{{ wallet.transactions.length ? 'No transactions for this period and filters' : 'No transactions yet' }}</div>
        </template>
        <template #bottom>
          <UiTablePagination
            :page="page"
            :page-count="pageCount"
            :items-length="filtered.length"
            :items-per-page="itemsPerPage"
            :items-per-page-options="[100, 200, 500]"
            @update:page="page = $event"
            @update:items-per-page="itemsPerPage = $event"
          />
        </template>
      </UiDataTable>
    </UiTableCard>

    <LeadDetailDialog v-model="detailOpen" :lead="detailLead" :loading="detailLoading" :error="detailError" />
  </div>
</template>

<script setup lang="ts">
import { allowExport } from '@/exports'
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import ChartLegend from '@/components/charts/ChartLegend.vue'
import ComboChart from '@/components/charts/ComboChart.vue'
import CountryMultiSelect from '@/components/CountryMultiSelect.vue'
import CountryPick from '@/components/CountryPick.vue'
import LeadDetailDialog from '@/components/LeadDetailDialog.vue'
import ModelTag from '@/components/ModelTag.vue'
import PeriodPicker from '@/components/PeriodPicker.vue'
import WalletFlowStrip from '@/components/transactions/WalletFlowStrip.vue'
import CollapsibleFilterPanel from '@/components/ui/CollapsibleFilterPanel.vue'
import TableCardToolbar from '@/components/ui/TableCardToolbar.vue'
import UiButton from '@/components/ui/UiButton.vue'
import UiCard from '@/components/ui/UiCard.vue'
import UiDataTable from '@/components/ui/UiDataTable.vue'
import UiInput from '@/components/ui/UiInput.vue'
import UiLabeledField from '@/components/ui/UiLabeledField.vue'
import UiSelect from '@/components/ui/UiSelect.vue'
import UiTableCard from '@/components/ui/UiTableCard.vue'
import UiTablePagination from '@/components/ui/UiTablePagination.vue'
import type { TooltipRow } from '@/charts'
import { useLoadErrorToast } from '@/composables/useLoadErrorToast'
import { countryName } from '@/countries'
import { countFilledFilters } from '@/filterCount'
import { modelLabel, paymentModels } from '@/marketOptions'
import { compactDollars, dollars } from '@/money'
import { reservedOf } from '@/orderBook'
import { leadDisplayId, matchesOrderDisplayId, orderDisplayId } from '@/orderDisplay'
import { bucketsOf, choicePeriodOf, timeOf, type PeriodChoice } from '@/purchases'
import { useLeadStore, type CabinetLead } from '@/stores/leads'
import { useMarketStore } from '@/stores/market'
import { useSessionStore } from '@/stores/session'
import { useWalletStore } from '@/stores/wallet'
import { exportTableCsv } from '@/tableCsvExport'
import { tableCellCopyFromExport } from '@/tableCellCopy'
import { prepareTableHeaders } from '@/tableHeaders'
import {
  entryTypesFor,
  entryTypeView,
  flowBucketsOf,
  walletEntriesOf,
  walletFlowOf,
  type EntryType,
  type WalletEntry,
} from '@/walletEntries'

const BALANCE_COLOR = '#1fb58f'

const session = useSessionStore()
const wallet = useWalletStore()
const leadStore = useLeadStore()
const market = useMarketStore()

const isBuyer = computed(() => session.payload?.user?.role === 'buyer')
const types = computed(() => entryTypesFor(session.payload?.user?.role))
const chartTypes = computed(() => types.value.filter((type) => type.value !== 'deposit'))
const legend = computed(() => [
  ...chartTypes.value.map((type) => ({ label: type.plural, color: type.color })),
  { label: 'Balance', color: BALANCE_COLOR, line: true },
])

const choice = ref<PeriodChoice>({ key: '30', from: '', to: '' })
const now = ref(Date.now())
let clock: ReturnType<typeof setInterval> | undefined

const period = computed(() => choicePeriodOf(choice.value, now.value))
const entries = computed(() => walletEntriesOf(wallet.transactions, leadStore.leads, market.orders))
const flow = computed(() => walletFlowOf(entries.value, period.value.from, period.value.to, types.value))
const inPeriod = computed(() => entries.value.filter((entry) => entry.at >= period.value.from && entry.at < period.value.to))
const flowBuckets = computed(() => flowBucketsOf(entries.value, bucketsOf(period.value)))

const balance = computed(() => (session.payload?.user?.balanceCents ?? 0) / 100)
const reserved = computed(() => reservedOf(market.orders))
const free = computed(() => balance.value - reserved.value)
const payouts = computed(() => flow.value.lines.find((line) => line.type.value === 'payout') ?? { amount: 0, count: 0 })

const chartSubtitle = computed(() => `${period.value.title}, ${period.value.unit === 'hour' ? 'by hour' : 'by day'} · balance at the end of each ${period.value.unit}`)

const filterType = ref<EntryType | ''>('')
const filterSearch = ref('')
const filterModel = ref<string | null>(null)
const filterCountries = ref<string[]>([])

const filterTypeOrNull = computed({
  get: () => filterType.value || null,
  set: (value: string | null) => {
    filterType.value = (value ?? '') as EntryType | ''
  },
})

const typeItems = computed(() => types.value.map((type) => ({ title: type.plural, value: type.value })))

const activeFilterCount = computed(() => countFilledFilters({
  type: filterType.value,
  search: filterSearch.value,
  model: filterModel.value,
}) + filterCountries.value.length)

const filtered = computed(() => {
  const search = filterSearch.value.trim().toLowerCase()
  return inPeriod.value.filter((entry) => {
    if (filterType.value && entry.type !== filterType.value) return false
    if (filterModel.value && entry.model !== filterModel.value) return false
    if (filterCountries.value.length && !filterCountries.value.includes(entry.country)) return false
    if (search && !matchesSearch(entry, search)) return false
    return true
  })
})

const tableTitle = computed(() => `Transactions · ${filtered.value.length.toLocaleString('en-US')}`)

const headers = computed(() => prepareTableHeaders([
  { title: 'Date', key: 'at' },
  { title: 'Type', key: 'type' },
  { title: 'Details', key: 'details', sortable: false },
  { title: 'Country', key: 'country' },
  ...(isBuyer.value ? [] : [{ title: 'Model', key: 'model', align: 'center' as const }]),
  { title: 'Order', key: 'orderId', sortable: false },
  { title: 'Amount', key: 'signed', align: 'end' as const },
  { title: 'Balance after', key: 'balanceAfter', align: 'end' as const },
], { at: 120, type: 150, details: 240, country: 90, orderId: 100, signed: 120, balanceAfter: 130 }))

const sortBy = ref<{ key: string, order: 'asc' | 'desc' }[]>([{ key: 'at', order: 'desc' }])
const page = ref(1)
const itemsPerPage = ref(100)
const pageCount = computed(() => Math.max(1, Math.ceil(filtered.value.length / itemsPerPage.value)))

watch(filtered, () => {
  if (page.value > pageCount.value) page.value = pageCount.value
})

const detailOpen = ref(false)
const detailLead = ref<CabinetLead | null>(null)
const detailLoading = ref(false)
const detailError = ref('')

const problem = computed(() => wallet.error || leadStore.error || (isBuyer.value ? '' : market.error))

useLoadErrorToast(problem, () => { void refresh() })

function matchesSearch(entry: WalletEntry, search: string): boolean {
  const lead = entry.lead
  const haystack = [
    lead ? `${lead.firstName} ${lead.lastName}` : '',
    lead?.email ?? '',
    lead?.phone ?? '',
    entry.leadUuid,
    entry.leadUuid ? leadDisplayId(entry.leadUuid) : '',
  ].join(' ').toLowerCase()
  if (haystack.includes(search)) return true
  return entry.orderId !== '' && matchesOrderDisplayId(entry.orderId, search)
}

function leadName(entry: WalletEntry): string {
  const name = `${entry.lead?.firstName ?? ''} ${entry.lead?.lastName ?? ''}`.trim()
  return name || 'Lead'
}

function detailNote(entry: WalletEntry): string {
  if (entry.type === 'deposit') return 'Wallet top-up'
  if (entry.type === 'withdrawal') return 'Taken out of the wallet'
  if (entry.type === 'payout') return 'Paid for your lead'
  if (entry.stock) return 'Bought from stock'
  if (entry.model === 'cpa') return 'CPA lead sold to you'
  if (entry.model === 'cpl') return 'CPL lead delivered'
  return 'Lead charged'
}

function dayLabel(at: number): string {
  return new Date(at).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })
}

function signedDollars(value: number): string {
  return `${value > 0 ? '+' : '−'}${dollars(Math.abs(value))}`
}

function flowTooltip(index: number): TooltipRow[] {
  const item = flowBuckets.value[index]
  if (!item) return []
  return [
    ...types.value.map((type) => ({
      label: type.plural,
      value: `${type.incoming ? '+' : '−'}${dollars(item.totals[type.value])}`,
      color: type.color,
    })),
    { label: 'Balance', value: dollars(item.balance), color: BALANCE_COLOR },
  ]
}

function exportCell(key: string, entry: WalletEntry): string {
  switch (key) {
    case 'at':
      return `${dayLabel(entry.at)} ${timeOf(entry.at)}`
    case 'type':
      return entryTypeView(entry.type).label
    case 'details':
      return entry.leadUuid ? `${leadName(entry)} ${leadDisplayId(entry.leadUuid)}` : detailNote(entry)
    case 'country':
      return entry.country ? countryName(entry.country) : ''
    case 'model':
      return entry.model ? modelLabel(entry.model) : ''
    case 'orderId':
      return entry.orderId ? orderDisplayId(entry.orderId) : ''
    case 'signed':
      return signedDollars(entry.signed)
    case 'balanceAfter':
      return dollars(entry.balanceAfter)
    default:
      return ''
  }
}

const cellCopy = tableCellCopyFromExport(exportCell)

async function exportCsv(): Promise<void> {
  if (!(await allowExport('transactions', filtered.value.length))) {
    return
  }
  exportTableCsv('transactions.csv', headers.value, filtered.value, exportCell)
}

function resetFilters(): void {
  filterType.value = ''
  filterSearch.value = ''
  filterModel.value = null
  filterCountries.value = []
}

async function openLead(entry: WalletEntry): Promise<void> {
  detailOpen.value = true
  detailLead.value = entry.lead
  detailError.value = ''
  detailLoading.value = true
  const lead = await leadStore.loadOne(entry.leadUuid)
  detailLoading.value = false
  if (lead) {
    detailLead.value = lead
  } else if (!entry.lead) {
    detailError.value = 'This lead is no longer in your journal'
  }
}

async function refresh(): Promise<void> {
  now.value = Date.now()
  await Promise.all([
    wallet.load(),
    leadStore.load(),
    isBuyer.value ? Promise.resolve() : market.loadOrders(),
  ])
}

onMounted(() => {
  void refresh()
  clock = setInterval(() => {
    now.value = Date.now()
  }, 60_000)
})

onUnmounted(() => {
  if (clock !== undefined) clearInterval(clock)
})
</script>

<style scoped>
.transactions__grid {
  display: grid;
  gap: 16px;
  grid-template-columns: minmax(0, 2fr) minmax(280px, 1fr);
  margin-bottom: 16px;
}

.transactions__now {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.transactions__balance {
  font-size: 32px;
  font-variant-numeric: tabular-nums;
  font-weight: 700;
  letter-spacing: -0.8px;
}

.transactions__reserve {
  border-top: 1px solid var(--lm-lavender);
  display: flex;
  flex-direction: column;
  margin: 0;
}

.transactions__reserve > div {
  align-items: baseline;
  border-bottom: 1px solid var(--lm-lavender);
  display: flex;
  font-size: 13px;
  justify-content: space-between;
  padding: 10px 0;
}

.transactions__reserve dt {
  color: var(--lm-muted);
}

.transactions__reserve dd {
  font-variant-numeric: tabular-nums;
  font-weight: 700;
  margin: 0;
}

.transactions__short {
  color: #c2405f;
}

.transactions__hint {
  align-items: flex-start;
  background: var(--lm-lavender);
  border-radius: 10px;
  color: var(--lm-muted);
  display: flex;
  font-size: 12px;
  gap: 6px;
  line-height: 1.45;
  margin: 0;
  padding: 9px 11px;
}

.transactions__hint--warn {
  background: #fdecef;
  color: #a3304d;
}

.transactions__actions {
  display: flex;
  gap: 8px;
}

.transactions__actions > * {
  flex: 1 1 0;
}

.transactions__filter-row {
  align-items: flex-end;
  display: flex;
  gap: 12px;
  min-width: 0;
}

.transactions__filter-main {
  align-items: flex-end;
  display: grid;
  flex: 1 1 auto;
  gap: 12px;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  min-width: 0;
}

.transactions__filter-main--buyer {
  grid-template-columns: repeat(3, minmax(0, 1fr));
}

.tx-date {
  display: flex;
  flex-direction: column;
  line-height: 1.25;
}

.tx-date strong {
  font-weight: 600;
}

.tx-date small,
.tx-details small,
.tx-muted {
  color: var(--lm-muted);
  font-size: 12px;
}

.tx-type {
  align-items: center;
  color: var(--tone);
  display: inline-flex;
  font-weight: 600;
  gap: 8px;
  white-space: nowrap;
}

.tx-type__icon {
  align-items: center;
  background: color-mix(in srgb, var(--tone) 13%, #fff);
  border-radius: 8px;
  display: inline-flex;
  height: 26px;
  justify-content: center;
  width: 26px;
}

.tx-details {
  display: flex;
  flex-direction: column;
  line-height: 1.3;
  min-width: 0;
}

.tx-details__lead {
  align-items: baseline;
  background: none;
  border: 0;
  color: inherit;
  cursor: pointer;
  display: inline-flex;
  font: inherit;
  gap: 6px;
  padding: 0;
  text-align: left;
}

.tx-details__lead strong {
  font-weight: 600;
}

.tx-details__lead span {
  color: var(--lm-primary);
  font-size: 12px;
  font-weight: 600;
}

.tx-details__lead:hover strong {
  color: var(--lm-primary-deep);
  text-decoration: underline;
}

.tx-order {
  color: var(--lm-primary);
  font-weight: 600;
  text-decoration: none;
}

.tx-order:hover {
  text-decoration: underline;
}

.tx-amount {
  font-variant-numeric: tabular-nums;
  font-weight: 700;
  white-space: nowrap;
}

.tx-amount--in {
  color: var(--success-strong);
}

.tx-balance {
  color: var(--lm-muted);
  font-variant-numeric: tabular-nums;
  white-space: nowrap;
}

@media (max-width: 1100px) {
  .transactions__grid {
    grid-template-columns: minmax(0, 1fr);
  }

  .transactions__filter-row {
    flex-wrap: wrap;
  }

  .transactions__filter-main,
  .transactions__filter-main--buyer {
    grid-template-columns: repeat(2, minmax(0, 1fr));
    width: 100%;
  }

  .transactions__filter-row > .filter-panel-reset {
    margin-left: auto;
  }
}

@media (max-width: 480px) {
  .transactions__filter-main,
  .transactions__filter-main--buyer {
    grid-template-columns: minmax(0, 1fr);
  }
}
</style>
