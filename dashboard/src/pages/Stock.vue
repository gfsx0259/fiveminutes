<template>
  <div class="stock-page">
    <div class="stock-intro">
      <h1>Buy Leads</h1>
      <p>Purchase verified affiliate leads from trusted traffic suppliers.</p>
    </div>

    <v-alert v-if="market.error" type="error" variant="tonal" class="mb-4">{{ market.error }}</v-alert>

    <section class="stock-chips" aria-label="Vertical filters">
      <button
        type="button"
        class="stock-chip"
        :class="{ 'stock-chip--all': filterVertical === '' }"
        @click="filterVertical = ''"
      >
        All
      </button>
      <button
        v-for="item in verticals"
        :key="item.value"
        type="button"
        class="stock-chip"
        :class="{ 'stock-chip--active': filterVertical === item.value }"
        @click="filterVertical = item.value"
      >
        <v-icon :icon="verticalIcon(item.value)" :color="verticalColor(item.value)" size="16" />
        {{ item.title }}
      </button>
    </section>

    <section class="ui-filter-panel stock-filter-panel">
      <div class="stock-filters__row">
        <UiLabeledField label="Geography">
          <UiSelect
            v-model="filterCountry"
            :items="countryItems"
            placeholder="All GEOs"
            empty-label="All GEOs"
            clearable
          />
        </UiLabeledField>
        <UiLabeledField label="Vertical">
          <UiSelect
            v-model="filterVerticalSelect"
            :items="verticalItems"
            placeholder="All verticals"
            empty-label="All verticals"
            clearable
          />
        </UiLabeledField>
        <UiLabeledField label="Traffic Source">
          <UiSelect
            v-model="filterTraffic"
            :items="trafficSources"
            placeholder="All sources"
            empty-label="All sources"
            clearable
          />
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

    <p v-if="!market.loading" class="stock-count">{{ filtered.length }} offers found</p>

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
      <template #item.offerId="{ item }">
        <span class="stock-offer-id">{{ offerId(item) }}</span>
      </template>
      <template #item.country="{ item }">
        <CountryPick :code="item.country" />
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
        <span class="stock-cr" :class="{ 'stock-cr--high': item.conversion >= 8 }">
          {{ item.conversion }}%
        </span>
      </template>
      <template #item.stockPriceCents="{ item }">
        <span class="stock-payout">{{ dollars(item.stockPriceCents / 100) }}</span>
      </template>
      <template #item.available="{ item }">
        <StockVolumeBar
          :value="item.available"
          :max="qtyMax(item)"
          :show-bar="item.available > 0"
        />
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
        <div class="stock-actions">
          <UiButton
            class="stock-actions__btn"
            variant="primary"
            icon="mdi-cart"
            :disabled="item.available < 1"
            @click="openBuy(item)"
          >
            Buy
          </UiButton>
          <UiButton class="stock-actions__btn" variant="outline" @click="openOrder(item)">
            Make offer
          </UiButton>
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

    <StockBuyDialog v-model="buyOpen" :target="buyTarget" @bought="refresh" />
    <OrderFormDialog v-model="orderOpen" :initial-preset-id="orderPresetId" @created="onOrderCreated" />
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import CountryPick from '@/components/CountryPick.vue'
import OrderFormDialog from '@/components/OrderFormDialog.vue'
import StockBuyDialog from '@/components/StockBuyDialog.vue'
import StockVolumeBar from '@/components/StockVolumeBar.vue'
import UiButton from '@/components/ui/UiButton.vue'
import UiDataTable from '@/components/ui/UiDataTable.vue'
import UiLabeledField from '@/components/ui/UiLabeledField.vue'
import UiSelect from '@/components/ui/UiSelect.vue'
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
import { dollars } from '@/money'
import { offerId, type StockBuyTarget } from '@/stockBuy'
import { useMarketStore, type StockOfferRow } from '@/stores/market'
import { ISO2_COUNTRIES } from '@/countries'

const market = useMarketStore()

const filterVertical = ref('')
const filterCountry = ref<string | null>(null)
const filterTraffic = ref<string | null>(null)
const filterModel = ref<string | null>(null)
const filterCr = ref<string | null>(null)
const filterAvailability = ref<string | null>(null)

const page = ref(1)
const itemsPerPage = ref(10)

const buyOpen = ref(false)
const buyTarget = ref<StockBuyTarget | null>(null)
const orderOpen = ref(false)
const orderPresetId = ref<string | null>(null)

const countryItems = computed(() => ISO2_COUNTRIES.map((c) => ({ title: c.name, value: c.code })))
const verticalItems = computed(() => verticals.map((v) => ({ title: v.title, value: v.value })))

const crItems = [
  { title: '8% and above', value: 'high' },
  { title: 'Below 8%', value: 'low' },
]

const availabilityItems = [
  { title: 'In stock', value: 'in_stock' },
  { title: 'Out of stock', value: 'empty' },
]

const filterVerticalSelect = computed({
  get: () => filterVertical.value || null,
  set: (value: string | null) => {
    filterVertical.value = value ?? ''
  },
})

const headers = [
  { title: 'Offer ID', key: 'offerId', sortable: false, width: '8%' },
  { title: 'GEO', key: 'country', width: '8%' },
  { title: 'Vertical', key: 'vertical', width: '10%' },
  { title: 'Traffic Source', key: 'trafficSource', width: '11%' },
  { title: 'Model', key: 'paymentModel', width: '6%' },
  { title: 'CR', key: 'conversion', align: 'end' as const, width: '6%' },
  { title: 'Payout', key: 'stockPriceCents', align: 'end' as const, width: '8%' },
  { title: 'Quantity', key: 'available', width: '11%' },
  { title: 'Daily cap', key: 'dailyCap', width: '11%' },
  { title: 'Updated', key: 'updatedAt', width: '9%' },
  { title: '', key: 'actions', sortable: false, align: 'end' as const, width: '12%' },
]

const filtered = computed(() => {
  return market.stock.filter((row) => {
    if (filterVertical.value && row.vertical !== filterVertical.value) {
      return false
    }
    if (filterCountry.value && row.country !== filterCountry.value) {
      return false
    }
    if (filterTraffic.value && row.trafficSource !== filterTraffic.value) {
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

function qtyMax(row: StockOfferRow): number {
  return Math.max(row.available, row.dailyCapVolume, 1)
}

function resetFilters(): void {
  filterVertical.value = ''
  filterCountry.value = null
  filterTraffic.value = null
  filterModel.value = null
  filterCr.value = null
  filterAvailability.value = null
  page.value = 1
}

function openBuy(row: StockOfferRow): void {
  buyTarget.value = row
  buyOpen.value = true
}

function openOrder(row: StockOfferRow): void {
  orderPresetId.value = row.presetId
  orderOpen.value = true
}

function onOrderCreated(): void {
  void refresh()
}

function relativeUpdated(iso: string | null): string {
  if (!iso) {
    return '—'
  }
  const at = new Date(iso).getTime()
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

onMounted(() => {
  void market.loadPresets()
  void refresh()
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

.stock-intro {
  margin-bottom: 4px;
}

.stock-intro h1 {
  margin-bottom: 6px;
}

.stock-intro p {
  color: #5c6078;
  font-size: 15px;
  line-height: 1.45;
  margin: 0 0 12px;
  max-width: 48rem;
}

.stock-count {
  color: #5c6078;
  font-size: 13px;
  font-weight: 600;
  margin: 0 0 10px;
}

.stock-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-bottom: 14px;
}

.stock-chip {
  align-items: center;
  background: #fff;
  border: 1px solid #e4e1f5;
  border-radius: 999px;
  color: #3512bc;
  cursor: pointer;
  display: inline-flex;
  font-size: 13px;
  font-weight: 600;
  gap: 6px;
  padding: 7px 14px;
  transition: border-color 0.15s ease, background 0.15s ease;
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
  grid-template-columns: repeat(6, minmax(0, 1fr)) auto;
}

.stock-filter-panel__reset {
  display: flex;
  justify-content: flex-end;
  padding-bottom: 1px;
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
  color: #5c6078;
  font-size: 13px;
  font-variant-numeric: tabular-nums;
  font-weight: 600;
}

.stock-cr--high {
  color: #2e7d32;
  font-weight: 700;
}

.stock-payout {
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
  display: inline-flex;
  flex-wrap: nowrap;
  gap: 6px;
  justify-content: flex-end;
}

.stock-actions__btn {
  flex-shrink: 0;
  font-size: 12px !important;
  height: 32px !important;
  min-width: 0 !important;
  padding-inline: 12px !important;
  white-space: nowrap;
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
  overflow: visible;
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
  height: 52px;
  vertical-align: middle;
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