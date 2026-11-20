<template>
  <div class="stock-page">
    <div class="page-head">
      <div>
        <h1>Buy Leads</h1>
        <p>Purchase verified affiliate leads from trusted traffic suppliers.</p>
      </div>
    </div>

    <v-alert v-if="market.error" type="error" variant="tonal" class="mb-4">{{ market.error }}</v-alert>

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

    <section class="ui-filter-panel stock-filter-panel">
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
          <UiButton variant="ghost" icon="mdi-refresh" @click="resetFilters">Reset</UiButton>
        </div>
      </div>
    </section>

    <UiTableCard title="Offers">
      <template #actions>
        <UiButton variant="outline" icon="mdi-refresh" :loading="market.loading" @click="refresh()">
          Refresh
        </UiButton>
        <UiButton variant="outline" icon="mdi-download" :disabled="filtered.length === 0" @click="exportCsv">
          Export
        </UiButton>
      </template>
      <UiDataTable
      v-model:page="page"
      v-model:items-per-page="itemsPerPage"
      class="stock-market-table"
      :headers="headers"
      :items="filtered"
      :loading="market.loading"
      :items-per-page-options="[10, 25, 50]"
      item-value="presetId"
      hover
    >
      <template #header.dailyCap="{ column }">
        <span class="stock-th-hint">
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
      <template #item.dailyCap="{ item }">
        <StockVolumeBar
          v-if="item.dailyCapVolume > 0"
          :value="item.dailyCapSupplied"
          :max="item.dailyCapVolume"
          :parent="item.dailyCapVolume"
        />
        <span v-else class="stock-dim">—</span>
      </template>
      <template #item.updatedAt="{ item }">
        {{ relativeUpdated(item.updatedAt) }}
      </template>
      <template #item.actions="{ item }">
        <div class="stock-actions ui-table-cell-actions">
          <UiButton
            class="stock-actions__btn ui-table-cell-actions__btn"
            variant="primary"
            icon="mdi-cart"
            :disabled="item.available < 1"
            @click="openBuy(item)"
          >
            Buy stock
          </UiButton>
          <UiButton class="stock-actions__btn ui-table-cell-actions__btn" variant="outline" @click="openOrder(item)">
            Make order
          </UiButton>
          <v-menu location="bottom end" content-class="stock-row-menu">
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
                type="button"
                class="ui-table-cell-actions__menu-only"
                :disabled="item.available < 1"
                @click="openBuy(item)"
              >
                <v-icon icon="mdi-cart" size="16" />
                Buy stock
              </button>
              <button type="button" class="ui-table-cell-actions__menu-only" @click="openOrder(item)">
                <v-icon icon="mdi-file-document-outline" size="16" />
                Make order
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
          :items-per-page-options="[10, 25, 50]"
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
      @bought="onBuyFinished"
    />
    <OrderFormDialog
      v-model="orderOpen"
      shareable
      :initial-preset-id="orderPresetId"
      :initial-base-price-cents="orderBasePriceCents"
      :initial-volume="orderInitialVolume"
      :initial-price-dollars="orderInitialPrice"
      :initial-service-on="orderInitialDay"
      @created="onOrderCreated"
    />

  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { showToast } from '@/composables/useToast'
import CountryMultiSelect from '@/components/CountryMultiSelect.vue'
import CountryPick from '@/components/CountryPick.vue'
import OrderFormDialog from '@/components/OrderFormDialog.vue'
import StockBuyDialog from '@/components/StockBuyDialog.vue'
import StockVolumeBar from '@/components/StockVolumeBar.vue'
import UiButton from '@/components/ui/UiButton.vue'
import UiDataTable from '@/components/ui/UiDataTable.vue'
import UiLabeledField from '@/components/ui/UiLabeledField.vue'
import UiSelect from '@/components/ui/UiSelect.vue'
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
import { parseStockModalIntent, STOCK_MODAL_QUERY_KEYS, STOCK_SHARE_ASSIST_KEYS } from '@/stockModalIntent'
import { offerId, type StockBuyTarget } from '@/stockBuy'
import { countryName } from '@/countries'
import { exportTableCsv, cellToString } from '@/tableCsvExport'
import { prepareTableHeaders } from '@/tableHeaders'
import { useCompactTableActions } from '@/viewport'
import { useMarketStore, type StockOfferRow } from '@/stores/market'

const route = useRoute()
const router = useRouter()
const market = useMarketStore()

const filterVerticals = ref<string[]>([])
const filterCountries = ref<string[]>([])
const filterTraffics = ref<string[]>([])
const filterModel = ref<string | null>(null)
const filterCr = ref<string | null>(null)
const filterAvailability = ref<string | null>(null)

const page = ref(1)
const itemsPerPage = ref(25)

const buyOpen = ref(false)
const buyTarget = ref<StockBuyTarget | null>(null)
const buyInitialQty = ref<number | null>(null)
const orderOpen = ref(false)
const orderPresetId = ref<string | null>(null)
const orderBasePriceCents = ref<number | null>(null)
const orderInitialVolume = ref<number | null>(null)
const orderInitialPrice = ref<number | null>(null)
const orderInitialDay = ref<string | null>(null)
const applyingModalLink = ref(false)
const compactTableActions = useCompactTableActions()

const crItems = [
  { title: '8% and above', value: 'high' },
  { title: 'Below 8%', value: 'low' },
]

const availabilityItems = [
  { title: 'In stock', value: 'in_stock' },
  { title: 'Out of stock', value: 'empty' },
]

const headers = computed(() => prepareTableHeaders(
  [
    { title: 'Offer ID', key: 'offerId', sortable: false, align: 'start' as const },
    { title: 'Vertical', key: 'vertical', align: 'start' as const },
    { title: 'Country', key: 'country', align: 'start' as const },
    { title: 'Traffic Source', key: 'trafficSource', align: 'start' as const },
    { title: 'Payment model', key: 'paymentModel', align: 'center' as const },
    { title: 'Conversion', key: 'conversion', align: 'center' as const },
    { title: 'Price', key: 'basePriceCents', align: 'end' as const },
    { title: 'Quantity', key: 'available', align: 'end' as const },
    { title: 'Live today', key: 'dailyCap', align: 'center' as const },
    { title: 'Updated', key: 'updatedAt', align: 'center' as const },
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
    available: 92,
    dailyCap: 148,
    updatedAt: 100,
    actions: compactTableActions.value ? 56 : 272,
  },
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

function exportCsv(): void {
  exportTableCsv('stock.csv', headers.value, filtered.value, stockExportCell)
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
    case 'dailyCap':
      return item.dailyCapVolume > 0 ? `${item.dailyCapSupplied} / ${item.dailyCapVolume}` : '—'
    case 'updatedAt':
      return item.updatedAt ?? ''
    default:
      return cellToString((item as Record<string, unknown>)[key])
  }
}

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

function resetFilters(): void {
  filterVerticals.value = []
  filterCountries.value = []
  filterTraffics.value = []
  filterModel.value = null
  filterCr.value = null
  filterAvailability.value = null
  page.value = 1
}

function clearStockModalQuery(): void {
  const keys = [...STOCK_MODAL_QUERY_KEYS, ...STOCK_SHARE_ASSIST_KEYS]
  if (!keys.some((key) => route.query[key] != null)) {
    return
  }
  const query = { ...route.query }
  for (const key of keys) {
    delete query[key]
  }
  void router.replace({ path: route.path, query })
}

function resetModalLinkState(): void {
  buyInitialQty.value = null
  orderInitialVolume.value = null
  orderInitialPrice.value = null
  orderInitialDay.value = null
}

function openBuy(row: StockOfferRow): void {
  resetModalLinkState()
  buyTarget.value = row
  buyOpen.value = true
}

function openOrder(row: StockOfferRow): void {
  resetModalLinkState()
  orderPresetId.value = row.presetId
  orderBasePriceCents.value = row.basePriceCents
  orderOpen.value = true
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
      clearStockModalQuery()
      return
    }
    if (intent.action === 'buy') {
      buyInitialQty.value = intent.qty ?? null
      buyTarget.value = row
      buyOpen.value = true
      return
    }
    orderPresetId.value = row.presetId
    orderBasePriceCents.value = row.basePriceCents
    orderInitialVolume.value = intent.qty ?? null
    orderInitialPrice.value = intent.price ?? null
    orderInitialDay.value = intent.day ?? null
    orderOpen.value = true
  } finally {
    applyingModalLink.value = false
  }
}

watch(
  () => route.query,
  () => {
    void applyStockModalLink()
  },
)

watch([buyOpen, orderOpen], ([buy, order]) => {
  if (!buy && !order) {
    clearStockModalQuery()
    resetModalLinkState()
  }
})

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

function relativeUpdated(iso: string | null): string {
  if (!iso) {
    return '—'
  }
  const at = new Date(iso).getTime()
  if (!Number.isFinite(at) || at < Date.UTC(2000, 0, 1)) {
    return '—'
  }
  const diff = Date.now() - at
  const min = Math.floor(diff / 60_000)
  if (min < 1) {
    return 'just now'
  }
  if (min < 60) {
    return `${min} min ago`
  }
  const hours = Math.floor(min / 60)
  if (hours < 48) {
    return `${hours} h ago`
  }
  return new Date(iso).toLocaleDateString()
}

async function refresh(): Promise<void> {
  await market.loadWarehouse()
}

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
  gap: 8px;
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

.stock-market-table .ui-data-table :deep(tbody td:last-child) {
  padding-inline: 12px 10px;
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
</style>