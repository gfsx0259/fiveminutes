<template>
  <div class="orders-page" :class="tableListLayoutClass">
    <div class="page-head">
      <div>
        <h1>{{ isPartner ? 'My orders' : 'Orders' }}</h1>
        <p v-if="isPartner">Day and stock orders you opened. Create new day orders from Buy leads.</p>
        <p v-else>Day orders for today or tomorrow. Partner payout is at or above the preset floor; the platform fee is charged to the buyer.</p>
      </div>
      <UiButton v-if="!isPartner && canManageOrders" variant="outline" icon="mdi-plus" @click="show">Create</UiButton>
    </div>

    <OrderFormDialog v-if="!isPartner" v-model="open" />

    <CollapsibleFilterPanel
      class="orders-filter-panel"
      aria-label="Order filters"
      :active-count="activeFilterCount"
    >
      <template #mobile-actions>
        <UiButton variant="ghost" icon="mdi-refresh" @click="resetFilters">Reset</UiButton>
      </template>
      <div class="orders-filters__toolbar">
        <div class="orders-filters__main">
          <UiLabeledField label="Order ID">
            <UiInput v-model="filterOrderId" class="orders-filter-order-id" placeholder="OR-… or uuid" />
          </UiLabeledField>
          <UiLabeledField label="Scope">
            <UiSelect v-model="filterScope" :items="scopeItems" />
          </UiLabeledField>
          <UiField label="Date" icon="mdi-calendar-range">
            <UiDateField v-model="filterServiceOn" label="Date" class="orders-filter-date" />
          </UiField>
          <UiLabeledField label="Type">
            <UiSelect
              v-model="filterOrderType"
              :items="orderTypeItems"
              placeholder="Any type"
              empty-label="Any type"
              clearable
            />
          </UiLabeledField>
        </div>
        <div v-if="!filterPanelCompact" class="orders-filters__toolbar-end">
          <UiButton
            variant="outline"
            :icon="advancedOpen ? 'mdi-chevron-up' : 'mdi-tune-variant'"
            @click="advancedOpen = !advancedOpen"
          >
            More filters
            <span v-if="advancedFiltersCount > 0" class="orders-filters__badge">{{ advancedFiltersCount }}</span>
          </UiButton>
          <UiButton class="filter-panel-reset" variant="ghost" icon="mdi-refresh" @click="resetFilters">Reset</UiButton>
        </div>
      </div>
      <div v-show="filterPanelCompact || advancedOpen" class="orders-filters__advanced">
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
        <UiLabeledField label="Country">
          <CountryMultiSelect v-model="filterCountries" />
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
        <UiLabeledField label="Payment model">
          <UiSelect
            v-model="filterPaymentModel"
            :items="paymentModels"
            placeholder="Any model"
            empty-label="Any model"
            clearable
          />
        </UiLabeledField>
      </div>
    </CollapsibleFilterPanel>

    <UiTableCard title="Orders">
      <template #actions>
        <TableCardToolbar
          :refresh-loading="market.loading"
          :export-disabled="filteredOrders.length === 0 || !session.can('reports.export')"
          @refresh="refresh()"
          @export="exportCsv"
        />
      </template>
      <UiDataTable
        v-model:page="page"
        v-model:items-per-page="itemsPerPage"
        class="orders-table"
        :headers="headers"
        :items="filteredOrders"
        :loading="market.loading"
        :items-per-page-options="[100, 200, 500]"
        item-value="id"
        hover
        fixed-header
        :cell-copy-value="orderTableCellCopy"
      >
        <template #item.id="{ item }">
          <span class="order-id">{{ orderDisplayId(item.id) }}</span>
        </template>
        <template #item.teamId="{ item }">
          <span class="order-team">{{ teamLabel(item) }}</span>
        </template>
        <template #item.openedBy="{ item }">
          <span class="order-opened-by" :title="item.openedBy || undefined">{{ item.openedBy || '—' }}</span>
        </template>
        <template #item.vertical="{ item }">
          <span class="order-entity">
            <v-icon :icon="verticalIcon(item.vertical)" :color="verticalColor(item.vertical)" size="18" />
            {{ verticalLabel(item.vertical) }}
          </span>
        </template>
        <template #item.geo="{ item }">
          <CountryPick :code="item.geo" label="name" />
        </template>
        <template #item.trafficSource="{ item }">
          <span class="order-entity">
            <v-icon :icon="trafficIcon(item.trafficSource)" :color="trafficColor(item.trafficSource)" size="18" />
            {{ trafficLabel(item.trafficSource) }}
          </span>
        </template>
        <template #item.paymentModel="{ item }">
          <ModelTag :model="item.paymentModel" />
        </template>
        <template #item.orderType="{ item }">
          <StatusChip
            :status="item.stock ? 'stock' : 'live'"
            :label="orderTypeLabel(item)"
            :tone="item.stock ? 'default' : 'lavender'"
          />
        </template>
        <template #item.whenDay="{ item }">
          <span class="order-when">{{ item.whenDay || '—' }}</span>
        </template>
        <template #item.supplied="{ item }">
          <StockVolumeBar
            :value="item.supplied"
            :max="item.volume"
            :parent="item.volume"
          />
        </template>
        <template #item.orderCostCents="{ item }">
          <v-tooltip v-if="item.stock && item.supplied > 0" location="top" text="Sum of per-lead prices (zone discounts apply on stock).">
            <template #activator="{ props: tip }">
              <span v-bind="tip" class="order-cost order-cost--hint">{{ orderCostLabel(item) }}</span>
            </template>
          </v-tooltip>
          <span v-else class="order-cost">{{ orderCostLabel(item) }}</span>
        </template>
        <template v-if="isAdmin" #item.liveInjectScheduled="{ item }">
          <span v-if="!item.stock && item.liveInjectScheduled">{{ item.liveInjectScheduled }}</span>
          <span v-else class="text-medium-emphasis">—</span>
        </template>
        <template v-if="isAdmin" #item.liveInjectNextDueAt="{ item }">
          <span v-if="!item.stock && item.liveInjectNextDueAt" class="order-live-due">{{ formatIsoDate(item.liveInjectNextDueAt) }}</span>
          <span v-else class="text-medium-emphasis">—</span>
        </template>
        <template #item.priceCents="{ item }">
          {{ dollars(item.priceCents / 100) }}
        </template>
        <template #item.partnerPriceCents="{ item }">
          {{ dollars(item.partnerPriceCents / 100) }}
        </template>
        <template #item.status="{ item }">
          <StatusChip :status="item.status" />
        </template>
        <template #item.orderActions="{ item }">
          <div class="order-actions ui-table-cell-actions" @click.stop>
            <v-btn
              v-if="isPartner"
              class="order-actions__icon ui-table-cell-actions__btn"
              icon="mdi-format-list-bulleted"
              variant="text"
              size="small"
              :aria-label="`My leads for order ${orderDisplayId(item.id)}`"
              title="My leads"
              @click="goOrderLeads(item.id)"
            />
            <v-menu location="bottom end" content-class="order-row-menu">
              <template #activator="{ props: menuProps }">
                <button
                  type="button"
                  class="order-actions__more"
                  :disabled="acting === item.id"
                  :aria-label="`More actions for order ${orderDisplayId(item.id)}`"
                  title="More"
                  v-bind="menuProps"
                >
                  <v-icon icon="mdi-dots-horizontal" size="20" />
                </button>
              </template>
              <div class="order-row-menu__list">
                <button
                  v-if="isPartner"
                  type="button"
                  class="order-row-menu__item ui-table-cell-actions__menu-only"
                  @click="goOrderLeads(item.id)"
                >
                  <v-icon icon="mdi-format-list-bulleted" size="18" class="order-row-menu__icon" />
                  My leads
                </button>
                <button type="button" class="order-row-menu__item" @click="copyOrderUuid(item.id)">
                  <v-icon icon="mdi-identifier" size="18" class="order-row-menu__icon" />
                  Copy order UUID
                </button>
                <button
                  v-if="canMoveOrders"
                  type="button"
                  class="order-row-menu__item"
                  @click="openMove(item)"
                >
                  <v-icon icon="mdi-folder-move-outline" size="18" class="order-row-menu__icon" />
                  Move to team
                </button>
                <button
                  v-if="canManageOrders && item.status === 'in_progress'"
                  type="button"
                  class="order-row-menu__item"
                  :disabled="acting === item.id"
                  @click="act(item.id, 'pause')"
                >
                  <v-icon icon="mdi-pause-circle-outline" size="18" class="order-row-menu__icon" />
                  Pause
                </button>
                <button
                  v-if="canManageOrders && item.status === 'paused'"
                  type="button"
                  class="order-row-menu__item"
                  :disabled="acting === item.id"
                  @click="act(item.id, 'resume')"
                >
                  <v-icon icon="mdi-play-circle-outline" size="18" class="order-row-menu__icon" />
                  Resume
                </button>
                <button
                  v-if="canManageOrders && item.status !== 'finished'"
                  type="button"
                  class="order-row-menu__item"
                  :disabled="acting === item.id"
                  @click="act(item.id, 'finish')"
                >
                  <v-icon icon="mdi-flag-checkered" size="18" class="order-row-menu__icon" />
                  Finish
                </button>
              </div>
            </v-menu>
          </div>
        </template>
        <template #no-data>
          <div class="py-8 text-medium-emphasis">No orders yet</div>
        </template>
        <template #bottom>
          <UiTablePagination
            :page="page"
            :page-count="pageCount"
            :items-length="filteredOrders.length"
            :items-per-page="itemsPerPage"
            :items-per-page-options="[100, 200, 500]"
            @update:page="page = $event"
            @update:items-per-page="itemsPerPage = $event"
          />
        </template>
      </UiDataTable>
    </UiTableCard>
    <OrderTeamDialog
      v-if="team.roster && moving"
      v-model="moveOpen"
      :roster="team.roster"
      :order-id="moving.id"
      :current="moving.teamId ?? null"
      @saved="onMoved"
    />
  </div>
</template>

<script setup lang="ts">
import { allowExport } from '@/exports'
import { computed, onMounted, ref, watch } from 'vue'
import OrderTeamDialog from '@/components/OrderTeamDialog.vue'
import { useTeamStore } from '@/stores/team'
import { ACCOUNT_LEVEL, teamPath } from '@/team'
import { useRoute, useRouter } from 'vue-router'
import CountryMultiSelect from '@/components/CountryMultiSelect.vue'
import CountryPick from '@/components/CountryPick.vue'
import OrderFormDialog from '@/components/OrderFormDialog.vue'
import ModelTag from '@/components/ModelTag.vue'
import StatusChip from '@/components/StatusChip.vue'
import StockVolumeBar from '@/components/StockVolumeBar.vue'
import CollapsibleFilterPanel from '@/components/ui/CollapsibleFilterPanel.vue'
import UiButton from '@/components/ui/UiButton.vue'
import UiDataTable from '@/components/ui/UiDataTable.vue'
import UiDateField from '@/components/ui/UiDateField.vue'
import UiField from '@/components/ui/UiField.vue'
import UiInput from '@/components/ui/UiInput.vue'
import UiLabeledField from '@/components/ui/UiLabeledField.vue'
import UiSelect from '@/components/ui/UiSelect.vue'
import TableCardToolbar from '@/components/ui/TableCardToolbar.vue'
import UiTableCard from '@/components/ui/UiTableCard.vue'
import UiTablePagination from '@/components/ui/UiTablePagination.vue'
import { countryName } from '@/countries'
import { exportTableCsv, cellToString } from '@/tableCsvExport'
import { countFilledFilters } from '@/filterCount'
import { prepareTableHeaders } from '@/tableHeaders'
import { tableCellCopyFromExport } from '@/tableCellCopy'
import { useCompactTableActions, useFilterPanelCollapse } from '@/viewport'
import {
  modelLabel,
  paymentModels,
  trafficColor,
  trafficIcon,
  trafficLabel,
  trafficSources,
  verticalColor,
  verticalIcon,
  verticalLabel,
  verticals,
} from '@/marketOptions'
import { dollars, wholeDollars } from '@/money'
import { useLoadErrorToast } from '@/composables/useLoadErrorToast'
import { showErrorToast, showToast } from '@/composables/useToast'
import { matchesOrderDisplayId, orderDisplayId, orderWhenDay } from '@/orderDisplay'
import { replacePageQuery, sameQuery } from '@/pageQuery'
import {
  applyOrdersTableShareFromQuery,
  assistQuery,
  ordersTableShareToQuery,
  shouldDeferTableShareAssist,
} from '@/tableViewShare'
import { useMarketStore, type OrderRow } from '@/stores/market'
import { useSessionStore } from '@/stores/session'
import { useTableListLayout } from '@/composables/useTableListLayout'

const { tableListLayoutClass } = useTableListLayout()

const route = useRoute()
const router = useRouter()
const session = useSessionStore()

const market = useMarketStore()
const pageReady = ref(false)
const isPartner = computed(() => session.payload?.user?.role === 'partner')
const team = useTeamStore()
const canMoveOrders = computed(() => isPartner.value && Boolean(team.roster?.viewer.lead))
const moveOpen = ref(false)
const moving = ref<OrderRow | null>(null)

function teamLabel(item: OrderRow): string {
  return teamPath(team.roster?.teams ?? [], item.teamId) || ACCOUNT_LEVEL
}

function openMove(item: OrderRow): void {
  moving.value = item
  moveOpen.value = true
}

async function onMoved(): Promise<void> {
  showToast('Order moved')
  await refresh()
}
const canManageOrders = computed(() => session.can('orders.manage'))
const isAdmin = computed(() => session.payload?.user?.role === 'admin')
const compactTableActions = useCompactTableActions()
const open = ref(false)
const acting = ref('')
const actionError = ref('')

const marketLoadError = computed(() => market.error)
useLoadErrorToast(marketLoadError, () => { void market.loadOrders() })

watch(actionError, (message) => {
  if (message) {
    showErrorToast(message)
  }
})

const page = ref(1)
const itemsPerPage = ref(100)

type OrderListScope = 'active' | 'past' | 'all'

const filterOrderId = ref('')
const filterScope = ref<OrderListScope>('active')
const filterVerticals = ref<string[]>([])
const filterCountries = ref<string[]>([])
const filterTraffics = ref<string[]>([])
const filterPaymentModel = ref<string | null>(null)
const filterOrderType = ref<'live' | 'stock' | null>(null)
const filterServiceOn = ref('')
const filterPanelCompact = useFilterPanelCollapse()
const advancedOpen = ref(false)

const scopeItems = [
  { title: 'Active', value: 'active' as const },
  { title: 'Past', value: 'past' as const },
  { title: 'All', value: 'all' as const },
]

const orderTypeItems = [
  { title: 'Live', value: 'live' as const },
  { title: 'Stock', value: 'stock' as const },
]

const advancedFiltersCount = computed(() => (
  filterVerticals.value.length
  + filterCountries.value.length
  + filterTraffics.value.length
  + (filterPaymentModel.value ? 1 : 0)
))

const activeFilterCount = computed(() => (
  countFilledFilters({
    orderId: filterOrderId.value,
    serviceOn: filterServiceOn.value,
    orderType: filterOrderType.value,
    scope: filterScope.value === 'active' ? '' : filterScope.value,
  }) + advancedFiltersCount.value
))

watch(advancedFiltersCount, (count) => {
  if (count > 0) {
    advancedOpen.value = true
  }
}, { immediate: true })

const verticalItems = computed(() => verticals.map((v) => ({ title: v.title, value: v.value })))

const ordersForTable = computed(() => {
  const presetVertical = new Map(market.presets.map((preset) => [preset.id, preset.vertical]))
  return market.orders.map((row) => ({
    ...row,
    vertical: row.vertical || presetVertical.get(row.presetId) || '',
    whenDay: orderWhenDay(row),
  }))
})

const filteredOrders = computed(() => {
  return ordersForTable.value.filter((row) => {
    if (filterScope.value === 'active' && row.status === 'finished') {
      return false
    }
    if (filterScope.value === 'past' && row.status !== 'finished') {
      return false
    }
    if (!matchesOrderDisplayId(row.id, filterOrderId.value)) {
      return false
    }
    if (filterVerticals.value.length > 0 && !filterVerticals.value.includes(row.vertical)) {
      return false
    }
    if (filterCountries.value.length > 0 && !filterCountries.value.includes(row.geo)) {
      return false
    }
    if (filterTraffics.value.length > 0 && !filterTraffics.value.includes(row.trafficSource)) {
      return false
    }
    if (filterOrderType.value === 'live' && row.stock) {
      return false
    }
    if (filterOrderType.value === 'stock' && !row.stock) {
      return false
    }
    if (filterServiceOn.value && row.whenDay !== filterServiceOn.value) {
      return false
    }
    if (filterPaymentModel.value && row.paymentModel !== filterPaymentModel.value) {
      return false
    }
    return true
  })
})

const pageCount = computed(() => Math.max(1, Math.ceil(filteredOrders.value.length / itemsPerPage.value)))

watch(filteredOrders, () => {
  if (page.value > pageCount.value) {
    page.value = pageCount.value
  }
})

const ORDERS_FILTER_QUERY_KEYS = ['view', 'oid', 'scope', 'fv', 'fc', 'ft', 'fpm', 'otyp', 'svc'] as const

function filterQuery(): Record<string, string> {
  return ordersTableShareToQuery({
    orderId: filterOrderId.value,
    scope: filterScope.value,
    verticals: filterVerticals.value,
    countries: filterCountries.value,
    traffics: filterTraffics.value,
    paymentModel: filterPaymentModel.value,
    orderType: filterOrderType.value,
    serviceOn: filterServiceOn.value,
  })
}

function currentPageQuery(): Record<string, string> {
  return {
    ...filterQuery(),
    ...assistQuery(session.payload?.user),
  }
}

function publishPageQuery(): void {
  if (!pageReady.value || session.loading) {
    return
  }
  if (shouldDeferTableShareAssist(session.payload?.user, route.query)) {
    return
  }
  replacePageQuery(router, route, currentPageQuery())
}

function resetFilters(): void {
  filterOrderId.value = ''
  filterScope.value = 'active'
  filterVerticals.value = []
  filterCountries.value = []
  filterTraffics.value = []
  filterPaymentModel.value = null
  filterOrderType.value = null
  filterServiceOn.value = ''
  advancedOpen.value = false
}

function applyOrdersTableShareLink(): void {
  if (shouldDeferTableShareAssist(session.payload?.user, route.query)) {
    return
  }
  const state = {
    orderId: '',
    scope: 'active' as OrderListScope,
    verticals: [] as string[],
    countries: [] as string[],
    traffics: [] as string[],
    paymentModel: null as string | null,
    orderType: null as 'live' | 'stock' | null,
    serviceOn: '',
  }
  if (!applyOrdersTableShareFromQuery(route.query, state)) {
    return
  }
  filterOrderId.value = state.orderId
  filterScope.value = state.scope
  filterVerticals.value = state.verticals
  filterCountries.value = state.countries
  filterTraffics.value = state.traffics
  filterPaymentModel.value = state.paymentModel
  filterOrderType.value = state.orderType
  filterServiceOn.value = state.serviceOn
  page.value = 1
}

function routeFilterQuery(): Record<string, string> {
  const out: Record<string, string> = {}
  for (const key of ORDERS_FILTER_QUERY_KEYS) {
    const value = route.query[key]
    if (typeof value === 'string' && value !== '') {
      out[key] = value
    }
  }
  return out
}

watch(
  () => [route.query, session.loading, session.payload?.user?.id, session.payload?.user?.role] as const,
  () => {
    if (session.loading || shouldDeferTableShareAssist(session.payload?.user, route.query)) {
      return
    }
    if (!sameQuery(routeFilterQuery(), filterQuery())) {
      applyOrdersTableShareLink()
    }
    pageReady.value = true
    publishPageQuery()
  },
  { immediate: true },
)

watch(
  [filterOrderId, filterScope, filterVerticals, filterCountries, filterTraffics, filterPaymentModel, filterOrderType, filterServiceOn],
  () => {
    publishPageQuery()
  },
)

function orderTypeLabel(item: OrderRow): string {
  return item.stock ? 'Stock' : 'Live'
}

async function exportCsv(): Promise<void> {
  if (!(await allowExport('orders', filteredOrders.value.length))) {
    return
  }
  exportTableCsv('orders.csv', headers.value, filteredOrders.value, orderExportCell)
}

function orderExportCell(key: string, item: OrderRow): string {
  switch (key) {
    case 'id':
      return orderDisplayId(item.id)
    case 'vertical':
      return verticalLabel(item.vertical)
    case 'geo':
      return countryName(item.geo)
    case 'trafficSource':
      return trafficLabel(item.trafficSource)
    case 'paymentModel':
      return modelLabel(item.paymentModel)
    case 'orderType':
      return orderTypeLabel(item)
    case 'whenDay':
      return item.whenDay || '—'
    case 'supplied':
      return `${item.supplied} / ${item.volume}`
    case 'orderCostCents':
      return orderCostLabel(item)
    case 'liveInjectScheduled':
      return !item.stock && item.liveInjectScheduled ? String(item.liveInjectScheduled) : '—'
    case 'liveInjectNextDueAt':
      return !item.stock && item.liveInjectNextDueAt ? formatIsoDate(item.liveInjectNextDueAt) : '—'
    case 'priceCents':
      return dollars(item.priceCents / 100)
    case 'partnerPriceCents':
      return dollars(item.partnerPriceCents / 100)
    case 'status':
      return item.status
    case 'teamId':
      return teamLabel(item)
    case 'openedBy':
      return item.openedBy || '—'
    default:
      return cellToString((item as Record<string, unknown>)[key])
  }
}

const orderTableCellCopy = tableCellCopyFromExport<OrderRow>(orderExportCell)

const partnerLeadColumns = [
  { title: 'Order ID', key: 'id', sortable: false, align: 'start' as const },
  { title: 'Vertical', key: 'vertical', align: 'start' as const },
  { title: 'Country', key: 'geo', align: 'start' as const },
  { title: 'Traffic source', key: 'trafficSource', align: 'start' as const },
  { title: 'Payment model', key: 'paymentModel', align: 'center' as const },
  { title: 'Type', key: 'orderType', align: 'center' as const },
  { title: 'Date', key: 'whenDay', align: 'center' as const },
  { title: 'Supplied', key: 'supplied', align: 'center' as const, width: '13rem' },
  { title: 'Order cost', key: 'orderCostCents', align: 'end' as const },
]

const orderColumnMins: Record<string, number> = {
  orderUuid: 108,
  vertical: 100,
  geo: 120,
  trafficSource: 128,
  paymentModel: 112,
  orderType: 88,
  whenDay: 108,
  supplied: 168,
  orderCostCents: 108,
  liveInjectScheduled: 108,
  liveInjectNextDueAt: 140,
  priceCents: 108,
  partnerPriceCents: 116,
  status: 100,
  orderActions: 88,
  teamId: 140,
  openedBy: 170,
}

const headers = computed(() => {
  const orderActionsMin = compactTableActions.value ? 56 : (isPartner.value ? 88 : 72)
  const actionMins = { ...orderColumnMins, orderActions: orderActionsMin }
  if (isPartner.value) {
    return prepareTableHeaders([
      ...partnerLeadColumns,
      { title: 'Team', key: 'teamId', align: 'start' as const },
      { title: 'Opened by', key: 'openedBy', align: 'start' as const },
      { title: 'Status', key: 'status', align: 'center' as const },
      { title: '', key: 'orderActions', sortable: false, align: 'end' as const },
    ], actionMins)
  }
  const adminCols = isAdmin.value
    ? [
      { title: 'Live scheduled', key: 'liveInjectScheduled', align: 'center' as const },
      { title: 'Next live sale', key: 'liveInjectNextDueAt', align: 'center' as const },
    ]
    : []
  return prepareTableHeaders([
    ...partnerLeadColumns,
    ...adminCols,
    { title: 'Catalog price', key: 'priceCents', align: 'end' as const },
    { title: 'Partner pays', key: 'partnerPriceCents', align: 'end' as const },
    { title: 'Status', key: 'status', align: 'center' as const },
    { title: '', key: 'orderActions', sortable: false, align: 'end' as const },
  ], actionMins)
})

function resolvedOrderCostCents(item: OrderRow): number | null {
  if (item.orderCostCents != null && item.orderCostCents > 0) {
    return item.orderCostCents
  }
  return null
}

function orderCostLabel(item: OrderRow): string {
  const cents = resolvedOrderCostCents(item)
  if (cents === null) {
    return '—'
  }
  return cents % 100 === 0 ? wholeDollars(cents / 100) : dollars(cents / 100)
}

function formatIsoDate(iso: string): string {
  const date = new Date(iso)
  if (Number.isNaN(date.getTime())) {
    return iso
  }
  return new Intl.DateTimeFormat(undefined, { dateStyle: 'short', timeStyle: 'short' }).format(date)
}

async function refresh(): Promise<void> {
  await market.loadPresets()
  await market.loadOrders()
}

function show(): void {
  open.value = true
}

function goOrderLeads(orderId: string): void {
  void router.push({ path: '/leads', query: { order: orderId } })
}

async function copyOrderUuid(orderId: string): Promise<void> {
  try {
    await navigator.clipboard.writeText(orderId)
    showToast('Order UUID copied')
  } catch {
    showToast('Copy unavailable')
  }
}

const orderActionToasts: Record<'pause' | 'resume' | 'finish', string> = {
  pause: 'Order paused',
  resume: 'Order resumed',
  finish: 'Order finished',
}

async function act(id: string, action: 'pause' | 'resume' | 'finish'): Promise<void> {
  acting.value = id
  actionError.value = ''
  try {
    await market.actOrder(id, action)
    showToast(orderActionToasts[action])
  } catch (error) {
    actionError.value = error instanceof Error ? error.message : 'Статус не сменился'
  } finally {
    acting.value = ''
  }
}

onMounted(() => {
  void refresh()
  if (isPartner.value) {
    void team.load()
  }
})
</script>

<style scoped>
.orders-filter-panel {
  margin-bottom: 16px;
}

.order-team {
  font-weight: 600;
}

.order-opened-by {
  color: var(--lm-muted);
  display: inline-block;
  max-width: 170px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.orders-filter-panel :deep(.ui-filter-panel__body) {
  display: grid;
  gap: 12px;
}

.orders-filters__toolbar {
  align-items: flex-end;
  display: flex;
  gap: 12px;
  min-width: 0;
}

.orders-filters__main {
  align-items: flex-end;
  display: grid;
  flex: 1 1 auto;
  gap: 12px;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  min-width: 0;
}

.orders-filters__toolbar-end {
  align-items: center;
  display: flex;
  flex: 0 0 auto;
  flex-wrap: nowrap;
  gap: 8px;
  height: var(--lm-control-height);
  padding-bottom: 1px;
}

.orders-filters__toolbar-end :deep(.ui-btn--ghost) {
  font-size: 14px;
  height: auto !important;
  min-height: var(--lm-control-height) !important;
  padding: 0 12px !important;
}

.orders-filters__badge {
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

.orders-filters__advanced {
  align-items: flex-end;
  display: grid;
  gap: 12px;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  min-width: 0;
  width: 100%;
}

.orders-filter-order-id {
  width: 100%;
}

.orders-filter-date {
  display: block;
  width: 100%;
}

.orders-table :deep(.v-data-table-footer) {
  display: none;
}

.orders-table :deep(tbody td .order-actions) {
  flex-flow: row nowrap !important;
  white-space: nowrap;
  width: max-content;
}

@media (max-width: 1280px) {
  .orders-filter-panel :deep(.ui-filter-panel__body) {
    display: flex;
    flex-direction: column;
    gap: 12px;
  }

  .orders-filters__toolbar {
    display: contents;
  }

  .orders-filters__main {
    grid-template-columns: repeat(2, minmax(0, 1fr));
    order: 1;
    width: 100%;
  }

  .orders-filters__advanced {
    grid-template-columns: repeat(2, minmax(0, 1fr));
    order: 2;
  }
}

@media (max-width: 767px) {
  .orders-filters__main {
    grid-template-columns: minmax(0, 1fr);
  }

  .orders-filters__advanced {
    grid-template-columns: minmax(0, 1fr);
  }
}

.order-actions {
  align-items: center;
  display: inline-flex;
  flex-wrap: nowrap;
  gap: 2px;
  justify-content: flex-end;
}

.order-actions__icon {
  flex-shrink: 0;
}

.order-actions__more {
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

.order-actions__more:hover:not(:disabled),
.order-actions__more:focus-visible:not(:disabled),
.order-actions__more[aria-expanded='true'] {
  background: #efecff;
  color: #3512bc;
  outline: none;
}

.order-actions__more:disabled {
  cursor: default;
  opacity: 0.38;
  pointer-events: none;
}

.order-row-menu__list {
  background: #fff;
  border: 1px solid #e6e7f2;
  border-radius: 12px;
  box-shadow: 0 8px 24px rgb(16 24 40 / 10%);
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 148px;
  padding: 6px;
}

.order-row-menu__list > button {
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

.order-row-menu__item {
  align-items: center;
  display: flex;
  gap: 8px;
}

.order-row-menu__icon {
  color: #6b628a;
  flex-shrink: 0;
}

.order-row-menu__list > button:hover:not(:disabled),
.order-row-menu__list > button:focus-visible:not(:disabled) {
  background: #efecff;
  color: #3512bc;
  outline: none;
}

.order-row-menu__list > button:hover:not(:disabled) .order-row-menu__icon,
.order-row-menu__list > button:focus-visible:not(:disabled) .order-row-menu__icon {
  color: #3512bc;
}

.order-row-menu__list > button:disabled {
  cursor: default;
  opacity: 0.5;
}

.order-id {
  color: #5c6078;
  font-size: 12px;
  font-variant-numeric: tabular-nums;
  font-weight: 600;
}

.order-entity {
  align-items: center;
  display: inline-flex;
  gap: 8px;
  min-width: 0;
}

.order-cost {
  color: #070916;
  font-size: 13px;
  font-variant-numeric: tabular-nums;
  font-weight: 700;
}

.order-when {
  font-size: 13px;
  font-variant-numeric: tabular-nums;
  font-weight: 600;
}

</style>

<style>
.order-row-menu.v-overlay__content {
  background: transparent;
  border: 0;
  box-shadow: none;
  overflow: visible;
  padding: 0;
}
</style>