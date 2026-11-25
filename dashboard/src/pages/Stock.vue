<template>
  <div class="stock-page" :class="tableListLayoutClass">
    <div class="page-head">
      <div>
        <h1>Buy Leads</h1>
        <p>Purchase verified affiliate leads from trusted traffic suppliers.</p>
      </div>
    </div>

    <section class="stock-chips" aria-label="Vertical filters">
      <button
        type="button"
        class="stock-chip"
        :class="{ 'stock-chip--all': filterVerticals.length === 0 }"
        @click="filterVerticals = []"
      >
        All
      </button>
      <button
        v-for="item in verticals"
        :key="item.value"
        type="button"
        class="stock-chip"
        :class="{ 'stock-chip--active': isVerticalChipActive(item.value) }"
        @click="selectVerticalChip(item.value)"
      >
        <v-icon :icon="verticalIcon(item.value)" :color="verticalColor(item.value)" size="18" />
        {{ item.title }}
      </button>
    </section>

    <CollapsibleFilterPanel class="stock-filter-panel" :active-count="activeFilterCount">
      <template #mobile-actions>
        <UiButton variant="ghost" icon="mdi-refresh" @click="resetFilters">Reset</UiButton>
      </template>
      <div class="stock-filters__row">
        <UiLabeledField label="Country">
          <CountryMultiSelect v-model="filterCountries" />
        </UiLabeledField>
        <UiLabeledField label="Traffic Source">
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
            v-model="filterModel"
            :items="paymentModels"
            placeholder="All models"
            empty-label="All models"
            clearable
          />
        </UiLabeledField>
        <UiLabeledField label="Conversion">
          <UiSelect v-model="filterCr" :items="crItems" placeholder="Any" empty-label="Any" clearable />
        </UiLabeledField>
        <UiLabeledField label="Availability">
          <UiSelect v-model="filterAvailability" :items="availabilityItems" placeholder="Any" empty-label="Any" clearable />
        </UiLabeledField>
        <div class="stock-filter-panel__reset">
          <UiButton class="filter-panel-reset" variant="ghost" icon="mdi-refresh" @click="resetFilters">Reset</UiButton>
        </div>
      </div>
    </CollapsibleFilterPanel>

    <v-tabs v-model="viewTab" color="primary" class="stock-view-tabs">
      <v-tab value="orders">Orders</v-tab>
      <v-tab value="stock">Stock</v-tab>
    </v-tabs>

    <UiTableCard
      :title="viewTab === 'stock' ? 'In stock' : 'Offers'"
      :subtitle="viewTab === 'stock' ? 'Instant delivery from the warehouse shelf' : 'Schedule a day order from a preset'"
    >
      <template #actions>
        <TableCardToolbar
          :refresh-loading="market.loading"
          :export-disabled="filtered.length === 0"
          @refresh="refresh()"
          @export="exportCsv"
        />
      </template>
      <UiDataTable
      v-model:page="page"
      v-model:items-per-page="itemsPerPage"
      class="stock-market-table"
      :headers="tableHeaders"
      :items="filtered"
      :loading="market.loading"
      :items-per-page-options="[50, 100, 200]"
      item-value="presetId"
      hover
      fixed-header
      :cell-copy-value="stockTableCellCopy"
    >
      <template #header.dailyCap="{ column }">
        <span v-if="viewTab === 'orders'" class="stock-th-hint">
          <span>{{ column.title }}</span>
          <v-tooltip location="top" max-width="300">
            <template #activator="{ props: tipProps }">
              <span
                class="stock-th-hint__icon"
                v-bind="tipProps"
                tabindex="0"
                role="button"
                aria-label="What Live today means"
                @click.stop
              >
                <v-icon icon="mdi-information-outline" size="14" />
              </span>
            </template>
            <span>Leads supplied from your live today orders</span>
          </v-tooltip>
        </span>
        <span v-else>{{ column.title }}</span>
      </template>
      <template #header.stockFreshness="{ column }">
        <span v-if="viewTab === 'stock'" class="stock-th-hint">
          <span>{{ column.title }}</span>
          <v-tooltip location="top" max-width="280" content-class="stock-freshness-hint">
            <template #activator="{ props: tipProps }">
              <span
                class="stock-th-hint__icon"
                v-bind="tipProps"
                tabindex="0"
                role="button"
                aria-label="Stock freshness legend"
                @click.stop
              >
                <v-icon icon="mdi-information-outline" size="14" />
              </span>
            </template>
            <div class="stock-freshness-hint__body">
              <p class="stock-freshness-hint__lead">Share of shelf leads by age</p>
              <ul class="stock-freshness-hint__list">
                <li>
                  <i class="stock-freshness-hint__dot stock-freshness-hint__dot--fresh" aria-hidden="true" />
                  Fresh — less than 1 hour
                </li>
                <li>
                  <i class="stock-freshness-hint__dot stock-freshness-hint__dot--good" aria-hidden="true" />
                  Aged — mid shelf age
                </li>
                <li>
                  <i class="stock-freshness-hint__dot stock-freshness-hint__dot--expired" aria-hidden="true" />
                  Expired — oldest shelf age
                </li>
              </ul>
            </div>
          </v-tooltip>
        </span>
        <span v-else>{{ column.title }}</span>
      </template>
      <template #item.offerId="{ item }">
        <span class="stock-offer-id">{{ offerId(item) }}</span>
      </template>
      <template #item.country="{ item }">
        <div class="stock-geo-cell">
          <CountryPick :code="item.country" label="name" />
        </div>
      </template>
      <template #item.vertical="{ item }">
        <span class="stock-entity">
          <v-icon :icon="verticalIcon(item.vertical)" :color="verticalColor(item.vertical)" size="18" />
          {{ verticalLabel(item.vertical) }}
        </span>
      </template>
      <template #item.trafficSource="{ item }">
        <span class="stock-entity">
          <v-icon :icon="trafficIcon(item.trafficSource)" :color="trafficColor(item.trafficSource)" size="18" />
          {{ trafficLabel(item.trafficSource) }}
        </span>
      </template>
      <template #item.paymentModel="{ item }">
        {{ modelLabel(item.paymentModel) }}
      </template>
      <template #item.conversion="{ item }">
        <span
          class="stock-cr"
          :class="item.conversion >= 8 ? 'stock-cr--high' : 'stock-cr--low'"
        >{{ item.conversion }}%</span>
      </template>
      <template #item.basePriceCents="{ item }">
        <span class="stock-payout">{{ payoutDollars(item.basePriceCents) }}</span>
      </template>
      <template #item.available="{ item }">
        <span class="stock-qty">{{ item.available }}</span>
      </template>
      <template #item.stockFreshness="{ item }">
        <div class="stock-freshness-cell">
          <StockFreshnessBar :freshness="item.stockFreshness" />
        </div>
      </template>
      <template #item.dailyCap="{ item }">
        <div class="stock-live-today-cell">
          <StockVolumeBar
            v-if="item.dailyCapVolume > 0"
            :value="item.dailyCapSupplied"
            :max="item.dailyCapVolume"
            :parent="item.dailyCapVolume"
          />
          <span v-else class="stock-dim">—</span>
        </div>
      </template>
      <template #item.actions="{ item }">
        <div class="stock-actions ui-table-cell-actions" :class="{ 'stock-actions--solo': !compactTableActions }">
          <UiButton
            v-if="viewTab === 'stock'"
            class="stock-actions__btn ui-table-cell-actions__btn"
            variant="primary"
            icon="mdi-cart-outline"
            :disabled="item.available < 1"
            @click="openBuy(item)"
          >
            Buy
          </UiButton>
          <UiButton
            v-else
            class="stock-actions__btn ui-table-cell-actions__btn"
            variant="primary"
            icon="mdi-calendar-plus"
            @click="openOrder(item)"
          >
            Order
          </UiButton>
          <v-menu v-if="compactTableActions" location="bottom end" content-class="stock-row-menu">
            <template #activator="{ props: menuProps }">
              <button
                type="button"
                class="stock-actions__more"
                v-bind="menuProps"
                :aria-label="`More actions for offer ${offerId(item)}`"
                title="More"
              >
                <v-icon icon="mdi-dots-horizontal" size="20" />
              </button>
            </template>
            <div class="stock-row-menu__list">
              <button
                v-if="viewTab === 'stock'"
                type="button"
                class="ui-table-cell-actions__menu-only"
                :disabled="item.available < 1"
                @click="openBuy(item)"
              >
                <v-icon icon="mdi-cart-outline" size="16" />
                Buy
              </button>
              <button
                v-else
                type="button"
                class="ui-table-cell-actions__menu-only"
                @click="openOrder(item)"
              >
                <v-icon icon="mdi-calendar-plus" size="16" />
                Order
              </button>
              <button type="button" @click="copyPresetId(item.presetId)">
                <v-icon icon="mdi-content-copy" size="16" />
                Copy preset ID
              </button>
            </div>
          </v-menu>
        </div>
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

    <StockBuyDialog
      v-model="buyOpen"
      :target="buyTarget"
      :initial-qty="buyInitialQty"
      :initial-zone="buyInitialZone"
      @draft="onBuyDraft"
      @bought="onBuyFinished"
    />
    <OrderFormDialog
      v-model="orderOpen"
      :initial-preset-id="orderPresetId"
      :initial-base-price-cents="orderBasePriceCents"
      :initial-volume="orderInitialVolume"
      :initial-price-dollars="orderInitialPrice"
      :initial-service-on="orderInitialDay"
      @created="onOrderCreated"
      @draft="onOrderDraft"
      @buy-stock="onBuyStockFromOrder"
      @stock-purchased="onStockPurchasedFromOrder"
    />

  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useLoadErrorToast } from '@/composables/useLoadErrorToast'
import { showToast } from '@/composables/useToast'
import CountryMultiSelect from '@/components/CountryMultiSelect.vue'
import CountryPick from '@/components/CountryPick.vue'
import OrderFormDialog from '@/components/OrderFormDialog.vue'
import type { OrderFormDraft } from '@/components/OrderFormDialog.vue'
import StockBuyDialog from '@/components/StockBuyDialog.vue'
import StockFreshnessBar from '@/components/StockFreshnessBar.vue'
import StockVolumeBar from '@/components/StockVolumeBar.vue'
import CollapsibleFilterPanel from '@/components/ui/CollapsibleFilterPanel.vue'
import UiButton from '@/components/ui/UiButton.vue'
import UiDataTable from '@/components/ui/UiDataTable.vue'
import UiLabeledField from '@/components/ui/UiLabeledField.vue'
import UiSelect from '@/components/ui/UiSelect.vue'
import TableCardToolbar from '@/components/ui/TableCardToolbar.vue'
import UiTableCard from '@/components/ui/UiTableCard.vue'
import UiTablePagination from '@/components/ui/UiTablePagination.vue'
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
import { replacePageQuery, sameQuery } from '@/pageQuery'
import { parseStockModalIntent, type StockBuyZone } from '@/stockModalIntent'
import type { StockPickZoneId } from '@/stockPick'
import {
  applyStockTableShareFromQuery,
  assistQuery,
  shouldDeferTableShareAssist,
  stockTableShareToQuery,
} from '@/tableViewShare'
import { useSessionStore } from '@/stores/session'
import { offerId, type StockBuyTarget } from '@/stockBuy'
import { countryName } from '@/countries'
import { stockFreshnessTooltip } from '@/stockFreshness'
import { exportTableCsv, cellToString } from '@/tableCsvExport'
import { countFilledFilters } from '@/filterCount'
import { prepareTableHeaders } from '@/tableHeaders'
import { tableCellCopyFromExport } from '@/tableCellCopy'
import { useCompactTableActions } from '@/viewport'
import { useMarketStore, type StockOfferRow } from '@/stores/market'
import { useTableListLayout } from '@/composables/useTableListLayout'

const { tableListLayoutClass } = useTableListLayout()

const route = useRoute()
const router = useRouter()
const session = useSessionStore()
const market = useMarketStore()

const filterVerticals = ref<string[]>([])
const filterCountries = ref<string[]>([])
const filterTraffics = ref<string[]>([])
const filterModel = ref<string | null>(null)
const filterCr = ref<string | null>(null)
const filterAvailability = ref<string | null>(null)

const activeFilterCount = computed(() => countFilledFilters({
  verticals: filterVerticals.value,
  countries: filterCountries.value,
  traffics: filterTraffics.value,
  model: filterModel.value,
  cr: filterCr.value,
  availability: filterAvailability.value,
}))

const viewTab = ref<'stock' | 'orders'>('orders')
const page = ref(1)
const itemsPerPage = ref(50)

const buyOpen = ref(false)
const buyTarget = ref<StockBuyTarget | null>(null)
const buyInitialQty = ref<number | null>(null)
const buyInitialZone = ref<StockBuyZone | ''>('')
const buyDraft = ref<{ qty: number; zone: StockPickZoneId }>({ qty: 0, zone: '' })
const orderOpen = ref(false)
const orderPresetId = ref<string | null>(null)
const orderBasePriceCents = ref<number | null>(null)
const orderInitialVolume = ref<number | null>(null)
const orderInitialPrice = ref<number | null>(null)
const orderInitialDay = ref<string | null>(null)
const orderDraft = ref<OrderFormDraft | null>(null)
const applyingModalLink = ref(false)
const pageReady = ref(false)
const compactTableActions = useCompactTableActions()

const crItems = [
  { title: '8% and above', value: 'high' },
  { title: 'Below 8%', value: 'low' },
]

const availabilityItems = [
  { title: 'In stock', value: 'in_stock' },
  { title: 'Out of stock', value: 'empty' },
]

const baseHeaderColumns = [
  { title: 'Offer ID', key: 'offerId', sortable: false, align: 'start' as const },
  { title: 'Vertical', key: 'vertical', align: 'start' as const },
  { title: 'Country', key: 'country', align: 'start' as const },
  { title: 'Traffic Source', key: 'trafficSource', align: 'start' as const },
  { title: 'Payment model', key: 'paymentModel', align: 'center' as const },
  { title: 'Conversion', key: 'conversion', align: 'center' as const },
  { title: 'Price', key: 'basePriceCents', align: 'end' as const },
]

const stockHeaders = computed(() => prepareTableHeaders(
  [
    ...baseHeaderColumns,
    { title: 'Quantity', key: 'available', align: 'end' as const },
    {
      title: 'Freshness',
      key: 'stockFreshness',
      align: 'center' as const,
      headerProps: { class: 'stock-col-freshness' },
      cellProps: { class: 'stock-col-freshness' },
    },
    { title: '', key: 'actions', sortable: false, align: 'end' as const },
  ],
  {
    offerId: 96,
    vertical: 108,
    country: 124,
    trafficSource: 132,
    paymentModel: 116,
    conversion: 100,
    basePriceCents: 88,
    available: 80,
    stockFreshness: 148,
    actions: compactTableActions.value ? 56 : 126,
  },
))

const orderHeaders = computed(() => prepareTableHeaders(
  [
    ...baseHeaderColumns,
    {
      title: 'Live today',
      key: 'dailyCap',
      align: 'center' as const,
      headerProps: { class: 'stock-col-live-today' },
      cellProps: { class: 'stock-col-live-today' },
    },
    { title: '', key: 'actions', sortable: false, align: 'end' as const },
  ],
  {
    offerId: 96,
    vertical: 108,
    country: 124,
    trafficSource: 132,
    paymentModel: 116,
    conversion: 100,
    basePriceCents: 88,
    dailyCap: 148,
    actions: compactTableActions.value ? 56 : 134,
  },
))

const tableHeaders = computed(() => (
  viewTab.value === 'stock' ? stockHeaders.value : orderHeaders.value
))

const filtered = computed(() => {
  return market.stock.filter((row) => {
    if (filterVerticals.value.length > 0 && !filterVerticals.value.includes(row.vertical)) {
      return false
    }
    if (filterCountries.value.length > 0 && !filterCountries.value.includes(row.country)) {
      return false
    }
    if (filterTraffics.value.length > 0 && !filterTraffics.value.includes(row.trafficSource)) {
      return false
    }
    if (filterModel.value && row.paymentModel !== filterModel.value) {
      return false
    }
    if (filterCr.value === 'high' && row.conversion < 8) {
      return false
    }
    if (filterCr.value === 'low' && row.conversion >= 8) {
      return false
    }
    if (filterAvailability.value === 'in_stock' && row.available < 1) {
      return false
    }
    if (filterAvailability.value === 'empty' && row.available >= 1) {
      return false
    }
    return true
  })
})

const pageCount = computed(() => Math.max(1, Math.ceil(filtered.value.length / itemsPerPage.value)))

watch(filtered, () => {
  if (page.value > pageCount.value) {
    page.value = pageCount.value
  }
})

watch(viewTab, () => {
  page.value = 1
})

function exportCsv(): void {
  const name = viewTab.value === 'stock' ? 'stock.csv' : 'presets.csv'
  exportTableCsv(name, tableHeaders.value, filtered.value, stockExportCell)
}

function stockExportCell(key: string, item: StockOfferRow): string {
  switch (key) {
    case 'offerId':
      return offerId(item)
    case 'vertical':
      return verticalLabel(item.vertical)
    case 'country':
      return countryName(item.country)
    case 'trafficSource':
      return trafficLabel(item.trafficSource)
    case 'paymentModel':
      return modelLabel(item.paymentModel)
    case 'conversion':
      return `${item.conversion}%`
    case 'basePriceCents':
      return payoutDollars(item.basePriceCents)
    case 'stockFreshness':
      return stockFreshnessTooltip(item.stockFreshness).replace(/\n/g, '; ')
    case 'dailyCap':
      return item.dailyCapVolume > 0 ? `${item.dailyCapSupplied} / ${item.dailyCapVolume}` : '—'
    default:
      return cellToString((item as Record<string, unknown>)[key])
  }
}

const stockTableCellCopy = tableCellCopyFromExport<StockOfferRow>(stockExportCell)

function payoutDollars(cents: number): string {
  return cents % 100 === 0 ? wholeDollars(cents / 100) : dollars(cents / 100)
}

function isVerticalChipActive(value: string): boolean {
  return filterVerticals.value.length === 1 && filterVerticals.value[0] === value
}

/** Chips set exactly one vertical (shortcut); use the dropdown for multi-select. */
function selectVerticalChip(value: string): void {
  if (isVerticalChipActive(value)) {
    filterVerticals.value = []
    return
  }
  filterVerticals.value = [value]
}

const STOCK_FILTER_QUERY_KEYS = ['view', 'tab', 'fv', 'fc', 'ft', 'fm', 'fcr', 'fa'] as const

function filterQuery(): Record<string, string> {
  return stockTableShareToQuery({
    tab: viewTab.value,
    verticals: filterVerticals.value,
    countries: filterCountries.value,
    traffics: filterTraffics.value,
    model: filterModel.value,
    cr: filterCr.value,
    availability: filterAvailability.value,
  })
}

function modalQuery(): Record<string, string> {
  if (buyOpen.value && buyTarget.value) {
    const query: Record<string, string> = {
      action: 'buy',
      preset: buyTarget.value.presetId,
    }
    if (buyDraft.value.qty > 0) {
      query.qty = String(buyDraft.value.qty)
    }
    const zone = buyDraft.value.zone
    if (zone === 'fresh' || zone === 'good' || zone === 'expired') {
      query.zone = zone
    }
    return query
  }
  if (orderOpen.value) {
    const presetId = orderDraft.value?.presetId || orderPresetId.value
    if (!presetId) {
      return {}
    }
    const query: Record<string, string> = { action: 'order', preset: presetId }
    const qty = orderDraft.value?.qty ?? 0
    const price = orderDraft.value?.price ?? 0
    const day = orderDraft.value?.day ?? ''
    if (qty > 0) {
      query.qty = String(Math.floor(qty))
    }
    if (price > 0) {
      query.price = String(price)
    }
    if (day !== '') {
      query.day = day
    }
    return query
  }
  return {}
}

function currentPageQuery(): Record<string, string> {
  return {
    ...filterQuery(),
    ...modalQuery(),
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
  filterVerticals.value = []
  filterCountries.value = []
  filterTraffics.value = []
  filterModel.value = null
  filterCr.value = null
  filterAvailability.value = null
  page.value = 1
}

function applyStockTableShareLink(): void {
  if (shouldDeferTableShareAssist(session.payload?.user, route.query)) {
    return
  }
  const state = {
    tab: viewTab.value,
    verticals: [] as string[],
    countries: [] as string[],
    traffics: [] as string[],
    model: null as string | null,
    cr: null as string | null,
    availability: null as string | null,
  }
  if (!applyStockTableShareFromQuery(route.query, state)) {
    return
  }
  viewTab.value = state.tab
  filterVerticals.value = state.verticals
  filterCountries.value = state.countries
  filterTraffics.value = state.traffics
  filterModel.value = state.model
  filterCr.value = state.cr
  filterAvailability.value = state.availability
  page.value = 1
}

function routeFilterQuery(): Record<string, string> {
  const out: Record<string, string> = {}
  for (const key of STOCK_FILTER_QUERY_KEYS) {
    const value = route.query[key]
    if (typeof value === 'string' && value !== '') {
      out[key] = value
    }
  }
  return out
}

function openBuy(row: StockOfferRow): void {
  orderOpen.value = false
  orderDraft.value = null
  buyInitialQty.value = null
  buyInitialZone.value = ''
  buyDraft.value = { qty: 0, zone: '' }
  buyTarget.value = row
  buyOpen.value = true
}

function openOrder(row: StockOfferRow): void {
  buyOpen.value = false
  buyTarget.value = null
  buyDraft.value = { qty: 0, zone: '' }
  orderDraft.value = null
  orderInitialVolume.value = null
  orderInitialPrice.value = null
  orderInitialDay.value = null
  orderPresetId.value = row.presetId
  orderBasePriceCents.value = row.basePriceCents
  orderOpen.value = true
}

function onBuyDraft(draft: { qty: number; zone: StockPickZoneId }): void {
  buyDraft.value = draft
}

function onOrderDraft(draft: OrderFormDraft | null): void {
  orderDraft.value = draft
}

function onBuyStockFromOrder(payload: { presetId: string; qty: number; zone: StockBuyZone }): void {
  const row = market.stock.find((offer) => offer.presetId === payload.presetId)
  if (!row) {
    return
  }
  orderOpen.value = false
  orderDraft.value = null
  buyInitialQty.value = payload.qty > 0 ? payload.qty : null
  buyInitialZone.value = payload.zone
  buyDraft.value = { qty: payload.qty > 0 ? payload.qty : 0, zone: payload.zone }
  buyTarget.value = row
  viewTab.value = 'stock'
  buyOpen.value = true
}

function onStockPurchasedFromOrder(): void {
  void market.loadWarehouse()
  void market.loadOrders()
}

async function applyStockModalLink(): Promise<void> {
  const intent = parseStockModalIntent(route.query)
  if (!intent || applyingModalLink.value || buyOpen.value || orderOpen.value) {
    return
  }
  applyingModalLink.value = true
  try {
    if (market.stock.length === 0) {
      await market.loadWarehouse()
    }
    const row = market.stock.find((offer) => offer.presetId === intent.presetId)
    if (!row) {
      showToast('Offer not found or no longer available')
      return
    }
    if (intent.action === 'buy') {
      viewTab.value = 'stock'
      buyInitialQty.value = intent.qty ?? null
      buyInitialZone.value = intent.zone ?? ''
      buyDraft.value = { qty: intent.qty ?? 0, zone: intent.zone ?? '' }
      buyTarget.value = row
      buyOpen.value = true
      return
    }
    viewTab.value = 'orders'
    orderPresetId.value = row.presetId
    orderBasePriceCents.value = row.basePriceCents
    orderInitialVolume.value = intent.qty ?? null
    orderInitialPrice.value = intent.price ?? null
    orderInitialDay.value = intent.day ?? null
    orderDraft.value = {
      presetId: row.presetId,
      qty: intent.qty ?? 0,
      price: intent.price ?? 0,
      day: intent.day ?? '',
    }
    orderOpen.value = true
  } finally {
    applyingModalLink.value = false
  }
}

watch(
  () => [route.query, session.loading, session.payload?.user?.id, session.payload?.user?.role] as const,
  () => {
    if (session.loading || shouldDeferTableShareAssist(session.payload?.user, route.query)) {
      return
    }
    if (!sameQuery(routeFilterQuery(), filterQuery())) {
      applyStockTableShareLink()
    }
    void applyStockModalLink().finally(() => {
      pageReady.value = true
      publishPageQuery()
    })
  },
  { immediate: true },
)

watch(
  [filterVerticals, filterCountries, filterTraffics, filterModel, filterCr, filterAvailability, viewTab, buyOpen, orderOpen, buyDraft, orderDraft],
  () => {
    publishPageQuery()
  },
)

async function copyPresetId(presetId: string): Promise<void> {
  try {
    await navigator.clipboard.writeText(presetId)
    showToast('Preset ID copied')
  } catch {
    showToast('Copy unavailable')
  }
}

function onOrderCreated(): void {
  void refresh()
}

function onBuyFinished(): void {
  void refresh()
}

async function refresh(): Promise<void> {
  await market.loadWarehouse()
}

const marketLoadError = computed(() => market.error)
useLoadErrorToast(marketLoadError, () => { void refresh() })

onMounted(async () => {
  void market.loadPresets()
  await refresh()
  await applyStockModalLink()
})
</script>

<style scoped>
.stock-page {
  display: flex;
  flex-direction: column;
  gap: 0;
  min-width: 0;
  width: 100%;
}

.stock-view-tabs {
  margin-bottom: 12px;
}

.stock-actions--solo {
  justify-content: flex-end;
}

.stock-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-bottom: 14px;
  max-width: 100%;
  min-width: 0;
}

.stock-chip {
  align-items: center;
  background: #fff;
  border: 1px solid #e4e1f5;
  border-radius: 999px;
  box-sizing: border-box;
  color: #3512bc;
  cursor: pointer;
  display: inline-flex;
  font-size: 14px;
  font-weight: 600;
  gap: 8px;
  height: 42px;
  line-height: 1;
  padding: 0 16px;
  transition: border-color 0.15s ease, background 0.15s ease;
  white-space: nowrap;
}

.stock-chip--all {
  background: linear-gradient(120deg, #6650ee, #5339e5);
  border-color: transparent;
  color: #fff;
}

.stock-chip--active {
  background: rgb(91 69 232 / 12%);
  border-color: #b8abf7;
}

.stock-filter-panel {
  margin-bottom: 16px;
}

.stock-filters__row {
  align-items: end;
  display: grid;
  gap: 14px 12px;
  grid-template-columns: repeat(5, minmax(0, 1fr)) auto;
}

.stock-filter-panel__reset {
  align-self: end;
  display: flex;
  justify-content: flex-end;
  padding-bottom: 1px;
}

.stock-filter-panel__reset :deep(.ui-btn--ghost) {
  font-size: 14px;
  height: auto !important;
  min-height: var(--lm-control-height) !important;
  padding: 0 12px !important;
}

.stock-filter-panel__reset :deep(.ui-btn--ghost .v-btn__prepend .v-icon) {
  font-size: 18px !important;
  height: 18px !important;
  margin-top: 0 !important;
  width: 18px !important;
}

.stock-filter-panel__reset :deep(.ui-btn--ghost .v-btn__content) {
  line-height: 1;
}

.stock-geo-cell {
  max-width: 100%;
  min-width: 0;
  overflow: hidden;
}

.stock-entity {
  align-items: center;
  display: inline-flex;
  gap: 8px;
  min-width: 0;
}

.stock-offer-id {
  color: #5c6078;
  font-size: 12px;
  font-variant-numeric: tabular-nums;
  font-weight: 600;
}

.stock-cr {
  border-radius: 8px;
  display: inline-block;
  font-size: 12px;
  font-variant-numeric: tabular-nums;
  font-weight: 600;
  line-height: 1.2;
  min-width: 2.75rem;
  padding: 4px 10px;
  text-align: center;
}

.stock-cr--low {
  background: #eef0f6;
  color: #3d446e;
}

.stock-cr--high {
  background: #e5f6ea;
  color: #1d7a3a;
  font-weight: 700;
}

.stock-payout {
  color: #070916;
  font-size: 13px;
  font-variant-numeric: tabular-nums;
  font-weight: 700;
}

.stock-qty {
  color: #070916;
  font-size: 13px;
  font-variant-numeric: tabular-nums;
  font-weight: 700;
}

.stock-dim {
  color: var(--lm-muted);
  font-size: 13px;
}

.stock-actions {
  align-items: center;
  display: inline-flex;
  flex-wrap: nowrap;
  gap: 2px;
  justify-content: flex-end;
}

.stock-actions__btn {
  flex-shrink: 0;
  font-size: 12px !important;
  height: 34px !important;
  letter-spacing: 0 !important;
  min-height: 34px !important;
  min-width: 0 !important;
  padding-inline: 12px !important;
  white-space: nowrap;
}

.stock-actions__btn :deep(.v-btn__prepend) {
  margin-inline-end: 4px;
}

.stock-actions__more {
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

.stock-actions__more:hover,
.stock-actions__more[aria-expanded='true'] {
  background: #efecff;
  color: #3512bc;
}

.stock-th-hint {
  align-items: center;
  display: inline-flex;
  gap: 4px;
  justify-content: center;
}

.stock-live-today-cell,
.stock-freshness-cell {
  align-items: center;
  display: flex;
  justify-content: center;
  margin-inline: auto;
  width: 100%;
}

.stock-live-today-cell .stock-vol,
.stock-freshness-cell .stock-freshness {
  width: 100%;
}

.stock-th-hint__icon {
  align-items: center;
  color: #8b90a8;
  cursor: help;
  display: inline-flex;
  line-height: 0;
}

.stock-th-hint__icon:hover,
.stock-th-hint__icon:focus-visible {
  color: #3512bc;
  outline: none;
}

.stock-row-menu__list {
  background: #fff;
  border: 1px solid #e4e1f5;
  border-radius: 12px;
  box-shadow: 0 8px 24px rgb(39 32 100 / 8%);
  display: flex;
  flex-direction: column;
  gap: 3px;
  min-width: 168px;
  padding: 6px;
}

.stock-row-menu__list > button {
  align-items: center;
  appearance: none;
  background: transparent;
  border: 0;
  border-radius: 8px;
  color: #070916;
  cursor: pointer;
  display: flex;
  font: inherit;
  font-size: 13px;
  gap: 9px;
  line-height: 20px;
  min-height: 36px;
  padding: 7px 9px;
  text-align: left;
  width: 100%;
}

.stock-row-menu__list > button:hover,
.stock-row-menu__list > button:focus-visible {
  background: #efecff;
  color: #3512bc;
  font-weight: 600;
  outline: none;
}

@media (max-width: 1280px) {
  .stock-filters__row {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }

  .stock-filter-panel__reset {
    grid-column: 1 / -1;
    justify-content: flex-end;
  }
}

@media (max-width: 700px) {
  .stock-filters__row {
    grid-template-columns: minmax(0, 1fr);
  }

  .stock-filter-panel__reset {
    grid-column: auto;
    justify-content: stretch;
  }

  .stock-filter-panel__reset .ui-btn {
    width: 100%;
  }

  .stock-actions {
    flex-wrap: wrap;
  }
}

@media (max-width: 480px) {
  .stock-chips {
    flex-wrap: nowrap;
    gap: 6px;
    margin-bottom: 12px;
    -webkit-overflow-scrolling: touch;
    overflow-x: auto;
    overscroll-behavior-x: contain;
    padding-bottom: 2px;
    scrollbar-width: none;
  }

  .stock-chips::-webkit-scrollbar {
    display: none;
  }

  .stock-chip {
    flex-shrink: 0;
    font-size: 13px;
    gap: 6px;
    height: 36px;
    padding: 0 12px;
  }

  .stock-chip :deep(.v-icon) {
    font-size: 16px !important;
    height: 16px !important;
    width: 16px !important;
  }

  .stock-filter-panel {
    margin-bottom: 12px;
  }

  .stock-filters__row {
    gap: 10px;
  }
}
</style>

<style>
.stock-market-table.ui-data-table-shell {
  min-height: 200px;
}

.stock-market-table .ui-data-table :deep(.v-table) {
  background: transparent;
}

.stock-market-table .ui-data-table :deep(.v-table__wrapper) {
  background: transparent;
  max-height: none;
  min-height: 0;
}

.stock-market-table .ui-data-table :deep(thead th) {
  background: transparent !important;
  border-bottom: 1px solid #e4e1f5 !important;
  color: #5c6078 !important;
  font-size: 11px !important;
  font-weight: 700 !important;
  height: 36px !important;
  letter-spacing: 0.02em;
  text-transform: uppercase;
}

.stock-market-table .ui-data-table :deep(th.v-data-table-column--align-center .v-data-table-header__content) {
  justify-content: center;
  width: 100%;
}

.stock-market-table .ui-data-table :deep(th.stock-col-live-today),
.stock-market-table .ui-data-table :deep(td.stock-col-live-today),
.stock-market-table .ui-data-table :deep(th.stock-col-freshness),
.stock-market-table .ui-data-table :deep(td.stock-col-freshness) {
  max-width: 152px;
  min-width: 148px;
  width: 148px;
}

.stock-market-table .ui-data-table :deep(th.stock-col-live-today .v-data-table-header__content),
.stock-market-table .ui-data-table :deep(th.stock-col-freshness .v-data-table-header__content) {
  justify-content: center;
}

.stock-market-table .ui-data-table :deep(th.stock-col-live-today .stock-th-hint),
.stock-market-table .ui-data-table :deep(th.stock-col-freshness .stock-th-hint) {
  width: 100%;
}

.stock-market-table .ui-data-table :deep(th.v-data-table-column--align-end .v-data-table-header__content) {
  justify-content: flex-end;
  width: 100%;
}

.stock-market-table .ui-data-table :deep(thead th:first-child),
.stock-market-table .ui-data-table :deep(thead th:last-child) {
  border-radius: 0;
}

.stock-market-table .ui-data-table :deep(tbody tr) {
  background: #fff;
  box-shadow: 0 1px 2px rgb(16 24 40 / 5%);
}

.stock-market-table .ui-data-table :deep(tbody td) {
  background: #fff !important;
  border-bottom: 8px solid #f8f8ff !important;
  min-height: 52px;
  padding-block: 9px;
  vertical-align: middle;
}

.stock-market-table .ui-data-table :deep(tbody tr:last-child td) {
  border-bottom: 0 !important;
}

.stock-market-table .ui-data-table :deep(thead .ui-col-sticky-end) {
  background: #fff !important;
}

.stock-market-table .ui-data-table :deep(tbody .ui-col-sticky-end) {
  background: #fff !important;
}

.stock-market-table .ui-data-table :deep(tbody tr:hover .ui-col-sticky-end) {
  background: #fff !important;
}

.stock-market-table .ui-data-table :deep(tbody td.ui-col-sticky-end) {
  white-space: normal;
}

.stock-market-table .ui-data-table :deep(tbody tr:hover td) {
  background: #fff !important;
}

.stock-market-table .ui-data-table :deep(tbody tr td:first-child) {
  border-radius: 10px 0 0 10px;
}

.stock-market-table .ui-data-table :deep(tbody tr td:last-child) {
  border-radius: 0 10px 10px 0;
}

.stock-market-table .ui-data-table :deep(.v-data-table-footer) {
  display: none;
}
</style>

<style>
.stock-row-menu.v-overlay__content {
  background: transparent;
  box-shadow: none;
}

.stock-freshness-hint.v-overlay__content {
  padding: 12px 14px !important;
}

.stock-freshness-hint__body {
  display: block;
  max-width: 248px;
}

.stock-freshness-hint__lead {
  font-size: 13px;
  font-weight: 600;
  line-height: 1.35;
  margin: 0 0 8px;
}

.stock-freshness-hint__list {
  display: flex;
  flex-direction: column;
  gap: 6px;
  list-style: none;
  margin: 0;
  padding: 0;
}

.stock-freshness-hint__list li {
  align-items: center;
  display: flex;
  font-size: 12px;
  gap: 8px;
  line-height: 1.35;
}

.stock-freshness-hint__dot {
  border-radius: 999px;
  display: inline-block;
  flex-shrink: 0;
  height: 8px;
  width: 8px;
}

.stock-freshness-hint__dot--fresh {
  background: var(--stock-fresh);
}

.stock-freshness-hint__dot--good {
  background: var(--stock-aged);
}

.stock-freshness-hint__dot--expired {
  background: var(--stock-expired);
}
</style>