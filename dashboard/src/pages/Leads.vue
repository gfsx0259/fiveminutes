<template>
  <div class="leads-page" :class="tableListLayoutClass">
    <div class="page-head">
      <div>
        <h1>{{ pageTitle }}</h1>
        <p>{{ leadLine }}</p>
      </div>
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

    <CollapsibleFilterPanel
      v-if="showJournal"
      class="leads-filter-panel"
      aria-label="Lead filters"
      :active-count="activeFilterCount"
    >
      <template #mobile-actions>
        <UiButton variant="ghost" icon="mdi-refresh" @click="resetFilters">Reset</UiButton>
      </template>
      <div class="leads-filters__toolbar">
        <div v-if="isPartner" class="leads-filters__main leads-filters__main--partner">
          <UiLabeledField label="Lead ID">
            <UiInput
              v-model="filterLeadId"
              class="lead-order-filter-input"
              placeholder="LD-… or uuid"
            />
          </UiLabeledField>
          <UiLabeledField label="Country">
            <CountryMultiSelect v-model="filterCountries" />
          </UiLabeledField>
          <UiLabeledField label="Email">
            <UiInput v-model="filterEmail" placeholder="Contains…" />
          </UiLabeledField>
          <UiLabeledField label="Partner status">
            <UiInput v-model="filterClientStatus" placeholder="Contains…" />
          </UiLabeledField>
          <UiLabeledField label="Deposit">
            <UiSelect
              v-model="filterDeposit"
              :items="depositFilterItems"
              placeholder="Any"
              empty-label="Any"
              clearable
            />
          </UiLabeledField>
        </div>
        <div v-else class="leads-filters__main">
          <UiLabeledField label="Lead ID">
            <UiInput
              v-model="filterLeadId"
              class="lead-order-filter-input"
              placeholder="LD-… or uuid"
            />
          </UiLabeledField>
          <UiLabeledField label="Country">
            <CountryMultiSelect v-model="filterCountries" />
          </UiLabeledField>
          <UiLabeledField label="Vertical">
            <UiSelect
              v-model="filterVerticals"
              :items="verticalItems"
              placeholder="All verticals"
              empty-label="All verticals"
              multiple
              clearable
            >
              <template #value="{ items }">
                <span v-if="items.length === 0" class="stock-select__placeholder">All verticals</span>
                <span v-else class="stock-select-picks">
                  <span v-for="item in items" :key="String(item.value)" class="stock-select-pick">
                    <v-icon :icon="verticalIcon(String(item.value))" :color="verticalColor(String(item.value))" size="14" />
                    {{ item.title }}
                  </span>
                </span>
              </template>
              <template #option="{ item }">
                <v-icon :icon="verticalIcon(String(item.value))" :color="verticalColor(String(item.value))" size="16" />
                <span>{{ item.title }}</span>
              </template>
            </UiSelect>
          </UiLabeledField>
          <UiLabeledField label="Traffic source">
            <UiSelect
              v-model="filterTraffics"
              :items="trafficSources"
              placeholder="All sources"
              empty-label="All sources"
              multiple
              clearable
            >
              <template #value="{ items }">
                <span v-if="items.length === 0" class="stock-select__placeholder">All sources</span>
                <span v-else class="stock-select-picks">
                  <span v-for="item in items" :key="String(item.value)" class="stock-select-pick">
                    <v-icon :icon="trafficIcon(String(item.value))" :color="trafficColor(String(item.value))" size="14" />
                    {{ item.title }}
                  </span>
                </span>
              </template>
              <template #option="{ item }">
                <v-icon :icon="trafficIcon(String(item.value))" :color="trafficColor(String(item.value))" size="16" />
                <span>{{ item.title }}</span>
              </template>
            </UiSelect>
          </UiLabeledField>
        </div>
        <div class="leads-filters__toolbar-end">
          <UiButton
            variant="outline"
            :icon="advancedOpen ? 'mdi-chevron-up' : 'mdi-tune-variant'"
            @click="advancedOpen = !advancedOpen"
          >
            More filters
            <span v-if="advancedFiltersCount > 0" class="leads-filters__badge">{{ advancedFiltersCount }}</span>
          </UiButton>
          <UiButton class="filter-panel-reset" variant="ghost" icon="mdi-refresh" @click="resetFilters">Reset</UiButton>
        </div>
      </div>
      <div
        v-show="advancedOpen"
        class="leads-filters__advanced"
        :class="{ 'leads-filters__advanced--partner': isPartner }"
      >
        <template v-if="isPartner">
          <UiLabeledField label="Vertical">
            <UiSelect
              v-model="filterVerticals"
              :items="verticalItems"
              placeholder="All verticals"
              empty-label="All verticals"
              multiple
              clearable
            >
              <template #value="{ items }">
                <span v-if="items.length === 0" class="stock-select__placeholder">All verticals</span>
                <span v-else class="stock-select-picks">
                  <span v-for="item in items" :key="String(item.value)" class="stock-select-pick">
                    <v-icon :icon="verticalIcon(String(item.value))" :color="verticalColor(String(item.value))" size="14" />
                    {{ item.title }}
                  </span>
                </span>
              </template>
              <template #option="{ item }">
                <v-icon :icon="verticalIcon(String(item.value))" :color="verticalColor(String(item.value))" size="16" />
                <span>{{ item.title }}</span>
              </template>
            </UiSelect>
          </UiLabeledField>
          <UiLabeledField label="Traffic source">
            <UiSelect
              v-model="filterTraffics"
              :items="trafficSources"
              placeholder="All sources"
              empty-label="All sources"
              multiple
              clearable
            >
              <template #value="{ items }">
                <span v-if="items.length === 0" class="stock-select__placeholder">All sources</span>
                <span v-else class="stock-select-picks">
                  <span v-for="item in items" :key="String(item.value)" class="stock-select-pick">
                    <v-icon :icon="trafficIcon(String(item.value))" :color="trafficColor(String(item.value))" size="14" />
                    {{ item.title }}
                  </span>
                </span>
              </template>
              <template #option="{ item }">
                <v-icon :icon="trafficIcon(String(item.value))" :color="trafficColor(String(item.value))" size="16" />
                <span>{{ item.title }}</span>
              </template>
            </UiSelect>
          </UiLabeledField>
          <UiLabeledField label="Gender">
            <UiSelect
              v-model="filterGender"
              :items="genderFilterItems"
              placeholder="Any gender"
              empty-label="Any gender"
              clearable
            />
          </UiLabeledField>
          <UiLabeledField label="Age">
            <UiInput v-model="filterAge" placeholder="Contains…" />
          </UiLabeledField>
        </template>
        <template v-else>
          <UiLabeledField label="Phone">
            <UiInput v-model="filterPhone" placeholder="Contains…" />
          </UiLabeledField>
          <UiLabeledField label="Email">
            <UiInput v-model="filterEmail" placeholder="Contains…" />
          </UiLabeledField>
        </template>
      </div>
    </CollapsibleFilterPanel>

    <v-alert v-if="showJournal && leadStore.error" type="error" variant="tonal" class="mb-4">{{ leadStore.error }}</v-alert>

    <LeadDetailDialog
      v-model="detailOpen"
      :lead="detailLead"
      :loading="detailLoading"
      :error="detailError"
      :open-reject="detailRejectFlow"
      @update:model-value="onDetailOpenChange"
      @converted="onLeadConverted"
    />

    <UiTableCard v-if="showJournal" :title="pageTitle">
      <template #actions>
        <TableCardToolbar
          :refresh-loading="leadStore.loading"
          :export-disabled="filtered.length === 0"
          :share-disabled="!canShareTableView"
          @refresh="leadStore.load()"
          @export="exportCsv"
          @share="copyShareLink"
        />
      </template>
      <UiDataTable
        v-model:page="page"
        v-model:items-per-page="itemsPerPage"
        :headers="headers"
        :items="filtered"
        :loading="leadStore.loading"
        :items-per-page-options="[50, 100, 200]"
        item-value="uuid"
        hover
        fixed-header
        class="lead-table"
        @click:row="onRow"
      >
        <template #item.leadId="{ item }">
          <span class="lead-id">{{ leadDisplayId(item.uuid) }}</span>
        </template>
        <template #item.vertical="{ item }">
          <span v-if="item.vertical" class="lead-entity">
            <v-icon :icon="verticalIcon(item.vertical)" :color="verticalColor(item.vertical)" size="18" />
            {{ verticalLabel(item.vertical) }}
          </span>
          <span v-else class="text-medium-emphasis">—</span>
        </template>
        <template #item.country="{ item }">
          <CountryPick :code="item.country" label="name" />
        </template>
        <template #item.trafficSource="{ item }">
          <span v-if="item.trafficSource" class="lead-entity">
            <v-icon :icon="trafficIcon(item.trafficSource)" :color="trafficColor(item.trafficSource)" size="18" />
            {{ trafficLabel(item.trafficSource) }}
          </span>
          <span v-else class="text-medium-emphasis">—</span>
        </template>
        <template #item.firstName="{ item }">
          {{ item.firstName || '—' }}
        </template>
        <template #item.lastName="{ item }">
          {{ item.lastName || '—' }}
        </template>
        <template #item.email="{ item }">
          <span class="lead-email" :title="item.email || undefined">{{ item.email || '—' }}</span>
        </template>
        <template #item.ip="{ item }">
          <span class="lead-ip">{{ item.ip || '—' }}</span>
        </template>
        <template #item.liveInject="{ item }">
          <div v-if="item.liveInjectStatus" class="lead-inject">
            <StatusChip
              :status="item.liveInjectStatus"
              :title="liveInjectHint(item)"
            />
            <span
              v-if="item.liveInjectStatus === 'scheduled' && item.liveInjectDueAt"
              class="lead-inject__due text-medium-emphasis"
            >
              {{ formatIsoDate(item.liveInjectDueAt) }}
            </span>
          </div>
          <span v-else class="text-medium-emphasis">—</span>
        </template>
        <template #item.status="{ item }">
          <StatusChip :status="item.status" :label="statusLabel(item.status ?? '')" />
        </template>
        <template #item.partnerDelivery="{ item }">
          <StatusChip
            v-if="item.deliveryError"
            status="failed"
            tone="error"
            label="Error"
          />
          <StatusChip
            v-else-if="item.isDelivered"
            status="delivered"
            tone="success"
            label="Delivered"
          />
          <span v-else class="text-medium-emphasis">—</span>
        </template>
        <template #item.clientStatus="{ item }">
          <span v-if="item.isDelivered && item.clientStatus" class="lead-client-status">{{ item.clientStatus }}</span>
          <span v-else class="text-medium-emphasis">—</span>
        </template>
        <template #item.clientDeposit="{ item }">
          <StatusChip
            v-if="item.isDelivered"
            :status="item.clientDeposit ? 'success' : 'default'"
            :tone="item.clientDeposit ? 'success' : 'default'"
            :label="item.clientDeposit ? 'Yes' : 'No'"
          />
          <span v-else class="text-medium-emphasis">—</span>
        </template>
        <template #item.actions="{ item }">
          <div class="lead-actions ui-table-cell-actions" @click.stop>
            <UiButton class="lead-actions__btn ui-table-cell-actions__btn" variant="outline" @click="openLead(item)">
              View
            </UiButton>
            <span class="lead-actions__menu">
              <v-menu location="bottom end" content-class="lead-row-menu">
                <template #activator="{ props: menuProps }">
                  <button
                    type="button"
                    class="lead-actions__more"
                    :disabled="acting === item.uuid"
                    :aria-label="`More actions for lead ${leadDisplayId(item.uuid)}`"
                    title="More"
                    v-bind="menuProps"
                  >
                    <v-icon icon="mdi-dots-horizontal" size="20" />
                  </button>
                </template>
              <div class="lead-row-menu__list">
                <button type="button" class="lead-row-menu__item ui-table-cell-actions__menu-only" @click="openLead(item)">
                  <v-icon icon="mdi-eye-outline" size="18" class="lead-row-menu__icon" />
                  View lead
                </button>
                <button type="button" class="lead-row-menu__item" @click="copyLeadUuid(item.uuid)">
                  <v-icon icon="mdi-identifier" size="18" class="lead-row-menu__icon" />
                  Copy lead UUID
                </button>
                <button type="button" class="lead-row-menu__item" @click="copyWithLabel(item.phone, 'Phone')">
                  <v-icon icon="mdi-phone" size="18" class="lead-row-menu__icon" />
                  Copy phone
                </button>
                <button type="button" class="lead-row-menu__item" @click="copyWithLabel(item.email, 'Email')">
                  <v-icon icon="mdi-email-outline" size="18" class="lead-row-menu__icon" />
                  Copy email
                </button>
                <button
                  v-if="leadCanConvert(item)"
                  type="button"
                  class="lead-row-menu__item"
                  :disabled="acting === item.uuid"
                  @click="approveLeadRow(item)"
                >
                  <v-icon icon="mdi-check-circle-outline" size="18" class="lead-row-menu__icon" />
                  Approve (deposit)
                </button>
                <button
                  v-if="leadCanConvert(item)"
                  type="button"
                  class="lead-row-menu__item"
                  @click="openLeadReject(item)"
                >
                  <v-icon icon="mdi-close-circle-outline" size="18" class="lead-row-menu__icon" />
                  Reject…
                </button>
                <button
                  v-if="leadCanMockDeliver(item)"
                  type="button"
                  class="lead-row-menu__item"
                  :disabled="acting === item.uuid"
                  @click="deliverLeadRow(item)"
                >
                  <v-icon icon="mdi-truck-delivery-outline" size="18" class="lead-row-menu__icon" />
                  Deliver (mock)
                </button>
              </div>
              </v-menu>
            </span>
          </div>
        </template>
        <template #no-data>
          <div class="py-8 text-medium-emphasis">{{ isBuyer ? 'No leads yet' : 'No purchased leads yet' }}</div>
        </template>
        <template #bottom>
          <UiTablePagination
            :page="page"
            :page-count="pageCount"
            :items-length="filtered.length"
            :items-per-page="itemsPerPage"
            :items-per-page-options="[50, 100, 200]"
            @update:page="page = $event"
            @update:items-per-page="itemsPerPage = $event"
          />
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
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { api, apiPost } from '@/api'
import CountryMultiSelect from '@/components/CountryMultiSelect.vue'
import CountryPick from '@/components/CountryPick.vue'
import LeadDetailDialog from '@/components/LeadDetailDialog.vue'
import StatusChip from '@/components/StatusChip.vue'
import CollapsibleFilterPanel from '@/components/ui/CollapsibleFilterPanel.vue'
import UiButton from '@/components/ui/UiButton.vue'
import UiDataTable from '@/components/ui/UiDataTable.vue'
import UiInput from '@/components/ui/UiInput.vue'
import UiLabeledField from '@/components/ui/UiLabeledField.vue'
import UiSelect from '@/components/ui/UiSelect.vue'
import TableCardToolbar from '@/components/ui/TableCardToolbar.vue'
import UiTableCard from '@/components/ui/UiTableCard.vue'
import UiTablePagination from '@/components/ui/UiTablePagination.vue'
import { countFilledFilters } from '@/filterCount'
import { prepareTableHeaders } from '@/tableHeaders'
import { useCompactTableActions } from '@/viewport'
import { showToast } from '@/composables/useToast'
import { downloadCsv } from '@/csv'
import { apiOrigin } from '@/host'
import { sampleLead, sampleLeadJson } from '@/leadBody'
import {
  genders,
  trafficColor,
  trafficIcon,
  trafficLabel,
  trafficSources,
  verticalColor,
  verticalIcon,
  verticalLabel,
  verticals,
} from '@/marketOptions'
import {
  leadDisplayId,
  matchesLeadDisplayId,
  matchesOrderDisplayId,
  orderDisplayId,
  verticalFromAffSub,
} from '@/orderDisplay'
import {
  applyLeadsTableShareFromQuery,
  buildTableShareUrl,
  isTableViewShareQuery,
  LEADS_TABLE_SHARE_QUERY_KEYS,
  leadsTableShareToQuery,
  shouldDeferTableShareAssist,
  tableViewShareAssist,
} from '@/tableViewShare'
import { useLeadStore, type CabinetLead } from '@/stores/leads'
import { useMarketStore, type OrderRow } from '@/stores/market'
import { useSessionStore } from '@/stores/session'
import { useTableListLayout } from '@/composables/useTableListLayout'

type LeadTableRow = CabinetLead & { vertical: string }

const route = useRoute()
const router = useRouter()
const session = useSessionStore()
const leadStore = useLeadStore()
const market = useMarketStore()
const isBuyer = computed(() => session.payload?.user?.role === 'buyer')
const isPartner = computed(() => session.payload?.user?.role === 'partner')
const canShareTableView = computed(() => Boolean(tableViewShareAssist(session.payload?.user)))
const compactTableActions = useCompactTableActions()
const tab = ref<'journal' | 'send'>('journal')
const showJournal = computed(() => !isBuyer.value || tab.value === 'journal')
const { tableListLayoutClass } = useTableListLayout(showJournal)
const pageTitle = computed(() => (isPartner.value ? 'My leads' : 'Leads'))
const filterLeadId = ref('')
/** Set from /leads?order= when opened from My orders; not shown in Lead ID field. */
const routeOrderFilter = ref('')

const page = ref(1)
const itemsPerPage = ref(50)

const filterVerticals = ref<string[]>([])
const filterCountries = ref<string[]>([])
const filterTraffics = ref<string[]>([])
const filterPhone = ref('')
const filterEmail = ref('')
const filterClientStatus = ref('')
const filterDeposit = ref<'yes' | 'no' | null>(null)
const filterGender = ref<string | null>(null)
const filterAge = ref('')
const advancedOpen = ref(false)

const depositFilterItems = [
  { title: 'Yes', value: 'yes' as const },
  { title: 'No', value: 'no' as const },
]

const genderFilterItems = computed(() =>
  genders.filter((item) => item.value !== 'all').map((item) => ({ title: item.title, value: item.value })),
)

const advancedFiltersCount = computed(() => {
  if (isPartner.value) {
    return filterVerticals.value.length
      + filterTraffics.value.length
      + (filterGender.value ? 1 : 0)
      + (filterAge.value.trim() !== '' ? 1 : 0)
  }
  let count = 0
  if (filterPhone.value.trim() !== '') {
    count += 1
  }
  if (filterEmail.value.trim() !== '') {
    count += 1
  }
  return count
})

const activeFilterCount = computed(() => (
  countFilledFilters({
    leadId: filterLeadId.value,
    countries: filterCountries.value,
    email: filterEmail.value,
    clientStatus: filterClientStatus.value,
    deposit: filterDeposit.value,
    phone: filterPhone.value,
  }) + advancedFiltersCount.value
))

watch(advancedFiltersCount, (count) => {
  if (count > 0) {
    advancedOpen.value = true
  }
}, { immediate: true })

const leadLine = computed(() => {
  const role = session.payload?.user?.role
  let line = 'Leads you mined'
  if (role === 'partner') line = 'Leads you purchased, client delivery, and feedback from your buyer.'
  else if (role === 'admin') line = 'Leads of this organization'
  if (routeOrderFilter.value !== '') {
    return `${line} Filtered by order ${orderDisplayId(routeOrderFilter.value)}.`
  }
  if (filterLeadId.value.trim() !== '') {
    return `${line} Filtered by lead ${filterLeadId.value.trim()}.`
  }
  return line
})

function syncOrderFromRoute(): void {
  const raw = route.query.order
  if (typeof raw === 'string' && raw !== '') {
    routeOrderFilter.value = raw
    return
  }
  if (route.query.order === undefined) {
    routeOrderFilter.value = ''
  }
}

function clearTableViewShareQuery(): void {
  const query = { ...route.query }
  let dirty = false
  for (const key of LEADS_TABLE_SHARE_QUERY_KEYS) {
    if (query[key] != null) {
      delete query[key]
      dirty = true
    }
  }
  if (dirty) {
    void router.replace({ path: route.path, query })
  }
}

async function copyShareLink(): Promise<void> {
  const assist = tableViewShareAssist(session.payload?.user)
  if (!assist) {
    return
  }
  const filterQuery = leadsTableShareToQuery({
    orderFilter: routeOrderFilter.value,
    leadId: filterLeadId.value,
    verticals: filterVerticals.value,
    countries: filterCountries.value,
    traffics: filterTraffics.value,
    phone: filterPhone.value,
    email: filterEmail.value,
    clientStatus: filterClientStatus.value,
    deposit: filterDeposit.value,
    gender: filterGender.value,
    age: filterAge.value,
  })
  const url = buildTableShareUrl(window.location.origin, route.path, filterQuery, assist)
  try {
    await navigator.clipboard.writeText(url)
    showToast('Link copied')
  } catch {
    showToast('Copy unavailable')
  }
}

function applyLeadsTableShareLink(): void {
  if (shouldDeferTableShareAssist(session.payload?.user, route.query)) {
    return
  }
  const state = {
    orderFilter: '',
    leadId: '',
    verticals: [] as string[],
    countries: [] as string[],
    traffics: [] as string[],
    phone: '',
    email: '',
    clientStatus: '',
    deposit: null as 'yes' | 'no' | null,
    gender: null as string | null,
    age: '',
  }
  if (!applyLeadsTableShareFromQuery(route.query, state)) {
    return
  }
  routeOrderFilter.value = state.orderFilter
  filterLeadId.value = state.leadId
  filterVerticals.value = state.verticals
  filterCountries.value = state.countries
  filterTraffics.value = state.traffics
  filterPhone.value = state.phone
  filterEmail.value = state.email
  filterClientStatus.value = state.clientStatus
  filterDeposit.value = state.deposit
  filterGender.value = state.gender
  filterAge.value = state.age
  page.value = 1
}

watch(
  () => [route.query, session.loading, session.payload?.user?.id] as const,
  () => {
    if (session.loading) {
      return
    }
    if (isTableViewShareQuery(route.query)) {
      applyLeadsTableShareLink()
      return
    }
    syncOrderFromRoute()
  },
  { immediate: true },
)
const apiKey = ref('')
const keyBusy = ref(false)
const keyError = ref('')
const body = ref(sampleLeadJson())
const sending = ref(false)
const sendError = ref('')
const sendResult = ref('')
const detailOpen = ref(false)
const detailLead = ref<CabinetLead | null>(null)
const detailLoading = ref(false)
const detailError = ref('')
const detailRejectFlow = ref(false)
const acting = ref('')

const isAdmin = computed(() => session.payload?.user?.role === 'admin')

const verticalItems = computed(() => verticals.map((v) => ({ title: v.title, value: v.value })))

const headers = computed(() => {
  const rows: { title: string; key: string; sortable?: boolean; width?: number; align?: 'start' | 'center' | 'end' }[] = [
    { title: 'Lead ID', key: 'leadId', sortable: false },
    { title: 'Vertical', key: 'vertical' },
    { title: 'Country', key: 'country' },
    { title: 'Traffic source', key: 'trafficSource' },
    { title: 'First name', key: 'firstName' },
    { title: 'Last name', key: 'lastName' },
    { title: 'Phone', key: 'phone' },
    { title: 'Email', key: 'email', width: 232 },
    { title: 'IP', key: 'ip' },
  ]
  if (isAdmin.value) {
    rows.push(
      { title: 'Buyer', key: 'buyerEmail' },
      { title: 'Partner', key: 'sellerEmail' },
      { title: 'Day inject', key: 'liveInject', align: 'center' },
    )
  }
  if (isPartner.value) {
    rows.push(
      { title: 'Delivery', key: 'partnerDelivery', align: 'center' },
      { title: 'Client status', key: 'clientStatus' },
      { title: 'Deposit', key: 'clientDeposit', align: 'center' },
    )
  } else {
    rows.push(
      { title: 'Status', key: 'status', align: 'center' },
    )
  }
  rows.push(
    { title: '', key: 'actions', sortable: false, align: 'end' as const },
  )
  return prepareTableHeaders(rows, {
    leadId: 108,
    vertical: 100,
    country: 112,
    trafficSource: 124,
    firstName: 100,
    lastName: 100,
    phone: 112,
    email: 200,
    ip: 120,
    buyerEmail: 160,
    sellerEmail: 160,
    liveInject: 128,
    partnerDelivery: 108,
    clientStatus: 116,
    clientDeposit: 96,
    status: 100,
    actions: compactTableActions.value ? 56 : 148,
  })
})

const statusLabels: Record<string, string> = {
  new: 'New',
  sold: 'Sold',
  in_stock: 'In stock',
  delivered: 'Delivered',
  approved: 'Approved',
  rejected: 'Rejected',
}

function formatIsoDate(iso: string): string {
  const date = new Date(iso)
  if (Number.isNaN(date.getTime())) {
    return iso
  }
  return new Intl.DateTimeFormat(undefined, { dateStyle: 'short', timeStyle: 'short' }).format(date)
}

function liveInjectHint(item: CabinetLead): string | undefined {
  if (item.liveInjectStatus === 'published' && item.status === 'in_stock') {
    return 'Published, but still in stock — order may not have matched yet.'
  }
  if (item.liveInjectStatus === 'failed') {
    return 'Error while selling into the day order.'
  }
  if (item.liveInjectStatus === 'scheduled' && item.liveInjectDueAt) {
    return `Scheduled for ${formatIsoDate(item.liveInjectDueAt)}`
  }
  return undefined
}

function statusLabel(value: string): string {
  return statusLabels[value] ?? value.charAt(0).toUpperCase() + value.slice(1)
}

function resolveLeadVertical(
  lead: CabinetLead,
  orderById: Map<string, OrderRow>,
  presetVertical: Map<string, string>,
): string {
  const orderId = lead.orderId
  if (orderId) {
    const order = orderById.get(orderId)
    if (order) {
      const fromOrder = order.vertical || presetVertical.get(order.presetId) || ''
      if (fromOrder !== '') {
        return fromOrder
      }
    }
  }
  return verticalFromAffSub(lead.affSub)
}

const leadsForTable = computed((): LeadTableRow[] => {
  const presetVertical = new Map(market.presets.map((preset) => [preset.id, preset.vertical]))
  const orderById = new Map(market.orders.map((order) => [order.id, order]))
  return leadStore.leads.map((lead) => ({
    ...lead,
    vertical: resolveLeadVertical(lead, orderById, presetVertical),
  }))
})

function contains(value: string, query: string): boolean {
  const q = query.trim().toLowerCase()
  if (q === '') {
    return true
  }
  return value.toLowerCase().includes(q)
}

const filtered = computed(() => leadsForTable.value.filter((lead) => {
  if (routeOrderFilter.value !== '') {
    if (!lead.orderId || !matchesOrderDisplayId(lead.orderId, routeOrderFilter.value)) {
      return false
    }
  }
  if (!matchesLeadDisplayId(lead.uuid, filterLeadId.value)) {
    return false
  }
  if (filterVerticals.value.length > 0 && !filterVerticals.value.includes(lead.vertical)) {
    return false
  }
  if (filterCountries.value.length > 0 && !filterCountries.value.includes(lead.country)) {
    return false
  }
  if (filterTraffics.value.length > 0 && !filterTraffics.value.includes(lead.trafficSource)) {
    return false
  }
  if (!contains(lead.phone, filterPhone.value)) {
    return false
  }
  if (!contains(lead.email, filterEmail.value)) {
    return false
  }
  if (!contains(lead.clientStatus ?? '', filterClientStatus.value)) {
    return false
  }
  if (filterDeposit.value === 'yes' && !lead.clientDeposit) {
    return false
  }
  if (filterDeposit.value === 'no' && lead.clientDeposit) {
    return false
  }
  if (filterGender.value && lead.gender !== filterGender.value) {
    return false
  }
  if (!contains(lead.age, filterAge.value)) {
    return false
  }
  return true
}))

const pageCount = computed(() => Math.max(1, Math.ceil(filtered.value.length / itemsPerPage.value)))

watch(filtered, () => {
  if (page.value > pageCount.value) {
    page.value = pageCount.value
  }
})

function resetFilters(): void {
  filterLeadId.value = ''
  routeOrderFilter.value = ''
  filterVerticals.value = []
  filterCountries.value = []
  filterTraffics.value = []
  filterPhone.value = ''
  filterEmail.value = ''
  filterClientStatus.value = ''
  filterDeposit.value = null
  filterGender.value = null
  filterAge.value = ''
  advancedOpen.value = false
  clearTableViewShareQuery()
}

const example = computed(() => {
  const origin = apiOrigin(window.location.hostname)
  return [
    `curl -X POST ${origin}/v1/lead \\`,
    `  -H 'Authorization: Bearer ${apiKey.value}' \\`,
    `  -H 'Content-Type: application/json' \\`,
    `  -d '${JSON.stringify(sampleLead)}'`,
  ].join('\n')
})

function onRow(_event: PointerEvent, row: { item: LeadTableRow }): void {
  void openLead(row.item)
}

function onDetailOpenChange(open: boolean): void {
  if (!open) {
    detailRejectFlow.value = false
  }
}

function leadCanConvert(lead: CabinetLead): boolean {
  if (lead.status !== 'sold') {
    return false
  }
  const role = session.payload?.user?.role
  return role === 'partner' || role === 'buyer' || role === 'admin'
}

function leadCanMockDeliver(lead: CabinetLead): boolean {
  if (lead.isDelivered || lead.deliveryError) {
    return false
  }
  const role = session.payload?.user?.role
  if (role === 'partner') {
    return true
  }
  if (role === 'admin') {
    return Boolean(lead.sellerId)
  }
  return false
}

async function deliverLeadRow(item: CabinetLead): Promise<void> {
  acting.value = item.uuid
  try {
    await apiPost<{ lead: CabinetLead }>(`/leads/${item.uuid}/deliver`, {})
    void leadStore.load()
  } catch {
    showToast('Delivery mock failed')
  } finally {
    acting.value = ''
  }
}

async function copyWithLabel(value: string | null | undefined, label: string): Promise<void> {
  if (!value) {
    return
  }
  try {
    await navigator.clipboard.writeText(value)
    showToast(`${label} copied`)
  } catch {
    showToast('Copy unavailable. Open lead details to select the value.')
  }
}

async function copyLeadUuid(uuid: string): Promise<void> {
  await copyWithLabel(uuid, 'Lead UUID')
}

async function approveLeadRow(item: CabinetLead): Promise<void> {
  acting.value = item.uuid
  try {
    await apiPost<{ lead: CabinetLead }>(`/leads/${item.uuid}/approve`, {})
    void leadStore.load()
    void session.load()
  } catch {
    // Conversion errors surface in the detail dialog if the user opens it.
  } finally {
    acting.value = ''
  }
}

function openLeadReject(item: CabinetLead): void {
  void openLead(item, { reject: true })
}

async function openLead(item: CabinetLead, options?: { reject?: boolean }): Promise<void> {
  detailRejectFlow.value = options?.reject ?? false
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
  try {
    await navigator.clipboard.writeText(apiKey.value)
    showToast('API key copied')
  } catch {
    showToast('Copy unavailable')
  }
}

function exportCsv(): void {
  const header = isAdmin.value
    ? ['Lead ID', 'Vertical', 'Country', 'Traffic source', 'First name', 'Last name', 'Phone', 'Email', 'IP', 'Buyer', 'Partner', 'Day inject', 'Status']
    : ['Lead ID', 'Vertical', 'Country', 'Traffic source', 'First name', 'Last name', 'Phone', 'Email', 'IP', 'Status']
  downloadCsv('leads.csv', [header, ...filtered.value.map(csvRow)])
}

function csvRow(lead: LeadTableRow): string[] {
  const row = [
    leadDisplayId(lead.uuid),
    lead.vertical ? verticalLabel(lead.vertical) : '',
    lead.country,
    lead.trafficSource ? trafficLabel(lead.trafficSource) : '',
    lead.firstName,
    lead.lastName,
    lead.phone,
    lead.email,
    lead.ip,
  ]
  if (isAdmin.value) {
    row.push(lead.buyerEmail ?? '', lead.sellerEmail ?? '')
    const inject = lead.liveInjectStatus
      ? [lead.liveInjectStatus, lead.liveInjectDueAt ? formatIsoDate(lead.liveInjectDueAt) : ''].filter(Boolean).join(' ')
      : '—'
    row.push(inject)
  }
  row.push(lead.status ?? '')
  return row
}

async function loadMarketContext(): Promise<void> {
  if (isBuyer.value) {
    return
  }
  await Promise.all([market.loadPresets(), market.loadOrders()])
}

onMounted(() => {
  void leadStore.load()
  void loadKey()
  void loadMarketContext()
})
</script>

<style scoped>
.key-panel__head {
  align-items: flex-start;
  display: flex;
  gap: 16px;
  justify-content: space-between;
}

.leads-filter-panel {
  margin-bottom: 16px;
}

.leads-filter-panel :deep(.ui-filter-panel__body) {
  display: grid;
  gap: 12px;
}

.leads-filters__toolbar {
  align-items: flex-end;
  display: flex;
  gap: 12px;
  min-width: 0;
}

.leads-filters__main {
  align-items: flex-end;
  display: grid;
  flex: 1 1 auto;
  gap: 12px;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  min-width: 0;
}

.leads-filters__main.leads-filters__main--partner {
  grid-template-columns: repeat(5, minmax(0, 1fr));
}

.leads-filters__toolbar-end {
  align-items: center;
  display: flex;
  flex: 0 0 auto;
  flex-wrap: nowrap;
  gap: 8px;
  height: var(--lm-control-height);
  padding-bottom: 1px;
}

.leads-filters__toolbar-end :deep(.ui-btn--ghost) {
  font-size: 14px;
  height: auto !important;
  min-height: var(--lm-control-height) !important;
  padding: 0 12px !important;
}

.leads-filters__badge {
  align-items: center;
  background: #3512bc;
  border-radius: 999px;
  color: #fff;
  display: inline-flex;
  font-size: 11px;
  font-weight: 700;
  height: 18px;
  justify-content: center;
  margin-left: 6px;
  min-width: 18px;
  padding: 0 5px;
}

.leads-filters__advanced {
  align-items: flex-end;
  display: grid;
  gap: 12px;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  min-width: 0;
  width: 100%;
}

.leads-filters__advanced.leads-filters__advanced--partner {
  grid-template-columns: repeat(4, minmax(0, 1fr));
}

.lead-order-filter-input {
  font-variant-numeric: tabular-nums;
  font-weight: 600;
}

.lead-id {
  color: #5c6078;
  font-size: 12px;
  font-variant-numeric: tabular-nums;
  font-weight: 600;
}

.lead-ip {
  font-size: 12px;
  font-variant-numeric: tabular-nums;
  white-space: nowrap;
}

.lead-email {
  display: inline-block;
  max-width: 100%;
  overflow: hidden;
  text-overflow: ellipsis;
  vertical-align: middle;
  white-space: nowrap;
}

.lead-inject {
  display: inline-flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 4px;
}

.lead-inject__due {
  font-size: 0.75rem;
}

.lead-entity {
  align-items: center;
  display: inline-flex;
  gap: 8px;
  min-width: 0;
}

.lead-table :deep(.v-data-table-footer) {
  display: none;
}

.lead-table :deep(tbody td .lead-actions) {
  flex-wrap: nowrap !important;
  white-space: nowrap;
  width: max-content;
}

.lead-actions {
  align-items: center;
  display: inline-flex;
  flex-direction: row;
  flex-wrap: nowrap;
  gap: 8px;
  justify-content: flex-end;
}

.lead-actions__menu {
  display: inline-flex;
  flex-shrink: 0;
  vertical-align: middle;
}

.lead-actions__btn {
  flex-shrink: 0;
  font-size: 12px !important;
  height: 34px !important;
  letter-spacing: 0 !important;
  min-height: 34px !important;
  min-width: 0 !important;
  padding-inline: 12px !important;
  white-space: nowrap;
}

.lead-actions__more {
  align-items: center;
  appearance: none;
  background: transparent;
  border: 0;
  border-radius: 8px;
  color: #5c6078;
  cursor: pointer;
  display: inline-flex;
  flex-shrink: 0;
  height: 34px;
  justify-content: center;
  margin: 0;
  padding: 0;
  width: 28px;
}

.lead-actions__more:hover:not(:disabled),
.lead-actions__more:focus-visible:not(:disabled),
.lead-actions__more[aria-expanded='true'] {
  background: #efecff;
  color: #3512bc;
  outline: none;
}

.lead-actions__more:disabled {
  cursor: default;
  opacity: 0.38;
  pointer-events: none;
}

.lead-row-menu__list {
  background: #fff;
  border: 1px solid #e6e7f2;
  border-radius: 12px;
  box-shadow: 0 8px 24px rgb(16 24 40 / 10%);
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 168px;
  padding: 6px;
}

.lead-row-menu__list > button {
  appearance: none;
  background: transparent;
  border: 0;
  border-radius: 8px;
  color: #070916;
  cursor: pointer;
  font: inherit;
  font-size: 13px;
  font-weight: 600;
  line-height: 20px;
  min-height: 36px;
  padding: 7px 12px;
  text-align: left;
  width: 100%;
}

.lead-row-menu__item {
  align-items: center;
  display: flex;
  gap: 8px;
}

.lead-row-menu__icon {
  color: #6b628a;
  flex-shrink: 0;
}

.lead-row-menu__list > button:hover:not(:disabled),
.lead-row-menu__list > button:focus-visible:not(:disabled) {
  background: #efecff;
  color: #3512bc;
  outline: none;
}

.lead-row-menu__list > button:hover:not(:disabled) .lead-row-menu__icon,
.lead-row-menu__list > button:focus-visible:not(:disabled) .lead-row-menu__icon {
  color: #3512bc;
}

.lead-row-menu__list > button:disabled {
  cursor: default;
  opacity: 0.5;
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

@media (max-width: 1280px) {
  .leads-filters__toolbar {
    flex-wrap: wrap;
  }

  .leads-filters__main {
    grid-template-columns: repeat(2, minmax(0, 1fr));
    width: 100%;
  }

  .leads-filters__main.leads-filters__main--partner {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .leads-filters__toolbar-end {
    margin-left: auto;
  }

  .leads-filters__advanced {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .leads-filters__advanced.leads-filters__advanced--partner {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 767px) {
  .key-panel__head,
  .key-panel__value {
    grid-template-columns: minmax(0, 1fr);
  }

  .key-panel__head {
    display: grid;
  }

  .leads-filter-panel :deep(.ui-filter-panel__body) {
    display: flex;
    flex-direction: column;
    gap: 12px;
  }

  .leads-filters__toolbar {
    display: contents;
  }

  .leads-filters__main,
  .leads-filters__main.leads-filters__main--partner {
    order: 1;
    grid-template-columns: minmax(0, 1fr);
    width: 100%;
  }

  .leads-filters__advanced,
  .leads-filters__advanced.leads-filters__advanced--partner {
    order: 2;
    grid-template-columns: minmax(0, 1fr);
    width: 100%;
  }

  .leads-filters__toolbar-end {
    order: 3;
    border-top: 1px solid #ece8ff;
    display: grid;
    gap: 8px;
    grid-template-columns: 1fr;
    height: auto;
    margin-left: 0;
    margin-top: 4px;
    padding-top: 12px;
    width: 100%;
  }

  .leads-filters__toolbar-end :deep(.ui-btn) {
    width: 100%;
  }
}
</style>

<style>
.lead-row-menu.v-overlay__content {
  background: transparent;
  border: 0;
  box-shadow: none;
  overflow: visible;
  padding: 0;
}
</style>