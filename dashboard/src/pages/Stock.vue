<template>
  <div class="stock-page">
    <div class="page-head">
      <div>
        <h1>Buy leads</h1>
        <p>Browse segments, buy from stock, or open a day order at or above the floor price.</p>
      </div>
      <UiButton variant="outline" icon="mdi-refresh" :loading="market.loading" @click="refresh">
        Refresh
      </UiButton>
    </div>

    <v-alert v-if="market.error" type="error" variant="tonal" class="mb-4">{{ market.error }}</v-alert>

    <section class="stock-filters">
      <div class="stock-chips">
        <button
          type="button"
          class="stock-chip"
          :class="{ 'stock-chip--active': filterVertical === '' }"
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
          {{ item.title }}
        </button>
      </div>
      <div class="stock-filters__row">
        <UiSelect v-model="filterCountry" :items="countryItems" placeholder="All GEOs" clearable />
        <UiSelect v-model="filterTraffic" :items="trafficSources" placeholder="All sources" clearable />
        <UiSelect v-model="filterModel" :items="paymentModels" placeholder="All models" clearable />
      </div>
    </section>

    <p v-if="!market.loading" class="stock-count">{{ filtered.length }} offers found</p>

    <UiTableCard title="Market">
      <UiDataTable
        :headers="headers"
        :items="filtered"
        :loading="market.loading"
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
          {{ verticalLabel(item.vertical) }}
        </template>
        <template #item.trafficSource="{ item }">
          {{ trafficLabel(item.trafficSource) }}
        </template>
        <template #item.paymentModel="{ item }">
          {{ modelLabel(item.paymentModel) }}
        </template>
        <template #item.conversion="{ item }">
          <span class="stock-cr">{{ item.conversion }}%</span>
        </template>
        <template #item.stockPriceCents="{ item }">
          {{ dollars(item.stockPriceCents / 100) }}
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
              variant="primary"
              icon="mdi-cart"
              :disabled="item.available < 1"
              @click="openBuy(item)"
            >
              Buy
            </UiButton>
            <UiButton variant="outline" icon="mdi-calendar-plus" @click="openOrder(item)">
              Order
            </UiButton>
          </div>
        </template>
      </UiDataTable>
    </UiTableCard>

    <StockBuyDialog v-model="buyOpen" :target="buyTarget" @bought="refresh" />
    <OrderFormDialog v-model="orderOpen" :initial-preset-id="orderPresetId" @created="onOrderCreated" />
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import CountryPick from '@/components/CountryPick.vue'
import OrderFormDialog from '@/components/OrderFormDialog.vue'
import StockBuyDialog from '@/components/StockBuyDialog.vue'
import StockVolumeBar from '@/components/StockVolumeBar.vue'
import UiButton from '@/components/ui/UiButton.vue'
import UiDataTable from '@/components/ui/UiDataTable.vue'
import UiSelect from '@/components/ui/UiSelect.vue'
import UiTableCard from '@/components/ui/UiTableCard.vue'
import { modelLabel, paymentModels, trafficLabel, trafficSources, verticalLabel, verticals } from '@/marketOptions'
import { dollars } from '@/money'
import { offerId, type StockBuyTarget } from '@/stockBuy'
import { useMarketStore, type StockOfferRow } from '@/stores/market'
import { ISO2_COUNTRIES } from '@/countries'

const market = useMarketStore()

const filterVertical = ref('')
const filterCountry = ref<string | null>(null)
const filterTraffic = ref<string | null>(null)
const filterModel = ref<string | null>(null)

const buyOpen = ref(false)
const buyTarget = ref<StockBuyTarget | null>(null)
const orderOpen = ref(false)
const orderPresetId = ref<string | null>(null)

const countryItems = computed(() => ISO2_COUNTRIES.map((c) => ({ title: c.name, value: c.code })))

const headers = [
  { title: 'Offer', key: 'offerId', sortable: false, width: '7%' },
  { title: 'GEO', key: 'country', width: '8%' },
  { title: 'Vertical', key: 'vertical', width: '9%' },
  { title: 'Source', key: 'trafficSource', width: '10%' },
  { title: 'Model', key: 'paymentModel', width: '7%' },
  { title: 'CR', key: 'conversion', align: 'end' as const, width: '6%' },
  { title: 'Stock payout', key: 'stockPriceCents', align: 'end' as const, width: '9%' },
  { title: 'Quantity', key: 'available', width: '12%' },
  { title: 'Daily cap', key: 'dailyCap', width: '12%' },
  { title: 'Updated', key: 'updatedAt', width: '10%' },
  { title: '', key: 'actions', sortable: false, align: 'end' as const, width: '10%' },
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
    return true
  })
})

function qtyMax(row: StockOfferRow): number {
  return Math.max(row.available, row.dailyCapVolume, 1)
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
  min-width: 0;
  width: 100%;
}

.stock-filters {
  margin-bottom: 16px;
  width: 100%;
}

.stock-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-bottom: 12px;
}

.stock-chip {
  background: rgb(91 69 232 / 8%);
  border: 1px solid transparent;
  border-radius: 999px;
  color: var(--lm-primary-deep);
  cursor: pointer;
  font-size: 13px;
  font-weight: 600;
  padding: 6px 14px;
}

.stock-chip--active {
  background: rgb(91 69 232 / 18%);
  border-color: rgb(91 69 232 / 35%);
}

.stock-filters__row {
  display: grid;
  gap: 12px;
  grid-template-columns: repeat(3, minmax(0, 1fr));
}

@media (max-width: 900px) {
  .stock-filters__row {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 520px) {
  .stock-filters__row {
    grid-template-columns: minmax(0, 1fr);
  }
}

.stock-count {
  color: var(--lm-muted);
  font-size: 14px;
  margin: 0 0 12px;
}

.stock-offer-id {
  color: var(--lm-muted);
  font-size: 12px;
  font-variant-numeric: tabular-nums;
  font-weight: 600;
}

.stock-cr {
  background: rgb(46 125 50 / 12%);
  border-radius: 999px;
  color: #2e7d32;
  display: inline-block;
  font-size: 12px;
  font-weight: 700;
  padding: 2px 8px;
}

.stock-dim {
  color: var(--lm-muted);
  font-size: 13px;
}

.stock-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  justify-content: flex-end;
}
</style>