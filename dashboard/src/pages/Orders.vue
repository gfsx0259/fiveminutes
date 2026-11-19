<template>
  <div>
    <div class="page-head">
      <div>
        <h1>{{ isPartner ? 'My orders' : 'Orders' }}</h1>
        <p v-if="isPartner">Day and stock orders you opened. Create new day orders from Buy leads.</p>
        <p v-else>Day orders for today or tomorrow. Partner payout is at or above the preset floor; the platform fee is charged to the buyer.</p>
      </div>
      <UiButton v-if="isPartner" variant="outline" icon="mdi-warehouse" @click="goBuy">Buy leads</UiButton>
      <UiButton v-else variant="outline" icon="mdi-plus" @click="show">Create</UiButton>
    </div>

    <OrderFormDialog v-if="!isPartner" v-model="open" />

    <v-alert v-if="actionError || market.error" type="error" variant="tonal" class="mb-4">
      {{ actionError || market.error }}
    </v-alert>

    <section class="ui-filter-panel orders-filter-panel" aria-label="Order filters">
      <div class="orders-filters__row">
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
        <UiLabeledField label="Geography">
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
        <UiLabeledField label="When">
          <UiInput v-model="filterServiceOn" type="date" class="orders-filter-date" />
        </UiLabeledField>
        <UiLabeledField label="Status">
          <UiSelect
            v-model="filterStatus"
            :items="statusItems"
            placeholder="Any status"
            empty-label="Any status"
            clearable
          />
        </UiLabeledField>
        <div class="orders-filter-panel__reset">
          <UiButton variant="ghost" icon="mdi-refresh" @click="resetFilters">Reset</UiButton>
        </div>
      </div>
    </section>

    <p v-if="!market.loading" class="orders-count">{{ filteredOrders.length }} orders</p>

    <UiTableCard title="Orders">
      <template #actions>
        <UiButton variant="outline" icon="mdi-refresh" :loading="market.loading" @click="refresh()">
          Refresh
        </UiButton>
      </template>
      <UiDataTable :headers="headers" :items="filteredOrders" :loading="market.loading" item-value="id" hover>
        <template #item.id="{ item }">
          <span class="order-id">{{ orderDisplayId(item.id) }}</span>
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
          {{ modelLabel(item.paymentModel) }}
        </template>
        <template #item.orderType="{ item }">
          <span class="order-type" :class="item.stock ? 'order-type--stock' : 'order-type--live'">
            {{ orderTypeLabel(item) }}
          </span>
        </template>
        <template #item.whenDay="{ item }">
          <span class="order-when">{{ item.whenDay || '—' }}</span>
        </template>
        <template #item.supplied="{ item }">
          <StockVolumeBar
            :value="item.supplied"
            :max="item.volume"
            :parent="item.volume"
            :bar-full="Boolean(item.stock)"
          />
        </template>
        <template #item.orderCostCents="{ item }">
          <span class="order-cost">{{ orderCostLabel(item) }}</span>
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
        <template #item.actions="{ item }">
          <div class="order-actions ui-table-cell-actions">
            <UiButton v-if="item.status === 'in_progress'" variant="outline" :loading="acting === item.id" @click="act(item.id, 'pause')">
              Pause
            </UiButton>
            <UiButton v-if="item.status === 'paused'" variant="outline" :loading="acting === item.id" @click="act(item.id, 'resume')">
              Resume
            </UiButton>
            <UiButton v-if="item.status !== 'finished'" variant="ghost" :loading="acting === item.id" @click="act(item.id, 'finish')">
              Finish
            </UiButton>
          </div>
        </template>
        <template #item.orderLeads="{ item }">
          <UiButton
            v-if="isPartner"
            variant="outline"
            class="order-leads-btn"
            @click="goOrderLeads(item.id)"
          >
            My leads
          </UiButton>
        </template>
        <template #no-data>
          <div class="py-8 text-medium-emphasis">Заказов пока нет</div>
        </template>
      </UiDataTable>
    </UiTableCard>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import CountryMultiSelect from '@/components/CountryMultiSelect.vue'
import CountryPick from '@/components/CountryPick.vue'
import OrderFormDialog from '@/components/OrderFormDialog.vue'
import StatusChip from '@/components/StatusChip.vue'
import StockVolumeBar from '@/components/StockVolumeBar.vue'
import UiButton from '@/components/ui/UiButton.vue'
import UiDataTable from '@/components/ui/UiDataTable.vue'
import UiInput from '@/components/ui/UiInput.vue'
import UiLabeledField from '@/components/ui/UiLabeledField.vue'
import UiSelect from '@/components/ui/UiSelect.vue'
import UiTableCard from '@/components/ui/UiTableCard.vue'
import {
  modelLabel,
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
import { orderDisplayId, orderWhenDay } from '@/orderDisplay'
import { useMarketStore, type OrderRow } from '@/stores/market'
import { useSessionStore } from '@/stores/session'

const router = useRouter()
const session = useSessionStore()
const market = useMarketStore()
const isPartner = computed(() => session.payload?.user?.role === 'partner')
const open = ref(false)
const acting = ref('')
const actionError = ref('')

const filterVerticals = ref<string[]>([])
const filterCountries = ref<string[]>([])
const filterTraffics = ref<string[]>([])
const filterServiceOn = ref('')
const filterStatus = ref<string | null>(null)

const verticalItems = computed(() => verticals.map((v) => ({ title: v.title, value: v.value })))

const statusItems = [
  { title: 'In progress', value: 'in_progress' },
  { title: 'Paused', value: 'paused' },
  { title: 'Finished', value: 'finished' },
]

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
    if (filterVerticals.value.length > 0 && !filterVerticals.value.includes(row.vertical)) {
      return false
    }
    if (filterCountries.value.length > 0 && !filterCountries.value.includes(row.geo)) {
      return false
    }
    if (filterTraffics.value.length > 0 && !filterTraffics.value.includes(row.trafficSource)) {
      return false
    }
    if (filterServiceOn.value && row.whenDay !== filterServiceOn.value) {
      return false
    }
    if (filterStatus.value && row.status !== filterStatus.value) {
      return false
    }
    return true
  })
})

function resetFilters(): void {
  filterVerticals.value = []
  filterCountries.value = []
  filterTraffics.value = []
  filterServiceOn.value = ''
  filterStatus.value = null
}

function orderTypeLabel(item: OrderRow): string {
  return item.stock ? 'Stock' : 'Live'
}

const partnerLeadColumns = [
  { title: 'Order ID', key: 'id', sortable: false, align: 'start' as const },
  { title: 'Vertical', key: 'vertical', align: 'start' as const },
  { title: 'GEO', key: 'geo', align: 'start' as const },
  { title: 'Traffic source', key: 'trafficSource', align: 'start' as const },
  { title: 'Model', key: 'paymentModel', align: 'center' as const },
  { title: 'Type', key: 'orderType', align: 'center' as const },
  { title: 'When', key: 'whenDay', align: 'center' as const },
  { title: 'Supplied', key: 'supplied', align: 'center' as const, width: '9rem' },
  { title: 'Order cost', key: 'orderCostCents', align: 'end' as const },
]

const headers = computed(() => {
  if (isPartner.value) {
    return [
      ...partnerLeadColumns,
      { title: '', key: 'actions', sortable: false, align: 'end' as const },
      { title: 'Status', key: 'status', align: 'center' as const },
      { title: '', key: 'orderLeads', sortable: false, align: 'end' as const, width: '7rem' },
    ]
  }
  return [
    ...partnerLeadColumns,
    { title: 'Catalog price', key: 'priceCents', align: 'end' as const },
    { title: 'Partner pays', key: 'partnerPriceCents', align: 'end' as const },
    { title: '', key: 'actions', sortable: false, align: 'end' as const },
    { title: 'Status', key: 'status', align: 'center' as const },
  ]
})

function orderCostCents(item: OrderRow): number {
  return item.partnerPriceCents * item.volume
}

function orderCostLabel(item: OrderRow): string {
  const cents = orderCostCents(item)
  return cents % 100 === 0 ? wholeDollars(cents / 100) : dollars(cents / 100)
}

async function refresh(): Promise<void> {
  await market.loadPresets()
  await market.loadOrders()
}

function show(): void {
  open.value = true
}

function goBuy(): void {
  void router.push('/stock')
}

function goOrderLeads(orderId: string): void {
  void router.push({ path: '/leads', query: { order: orderId } })
}

async function act(id: string, action: 'pause' | 'resume' | 'finish'): Promise<void> {
  acting.value = id
  actionError.value = ''
  try {
    await market.actOrder(id, action)
  } catch (error) {
    actionError.value = error instanceof Error ? error.message : 'Статус не сменился'
  } finally {
    acting.value = ''
  }
}

onMounted(() => {
  void refresh()
})
</script>

<style scoped>
.orders-filter-panel {
  margin-bottom: 16px;
}

.orders-filters__row {
  align-items: end;
  display: grid;
  gap: 14px 12px;
  grid-template-columns: repeat(5, minmax(0, 1fr)) auto;
}

.orders-filter-date {
  display: block;
  width: 100%;
}

.orders-filter-panel__reset {
  align-self: end;
  display: flex;
  justify-content: flex-end;
  padding-bottom: 1px;
}

.orders-filter-panel__reset :deep(.ui-btn--ghost) {
  font-size: 14px;
  height: auto !important;
  min-height: var(--lm-control-height) !important;
  padding: 0 12px !important;
}

.orders-filter-panel__reset :deep(.ui-btn--ghost .v-btn__prepend .v-icon) {
  font-size: 18px !important;
  height: 18px !important;
  margin-top: 0 !important;
  width: 18px !important;
}

.orders-filter-panel__reset :deep(.ui-btn--ghost .v-btn__content) {
  line-height: 1;
}

.orders-count {
  color: #5c6078;
  font-size: 13px;
  font-weight: 600;
  margin: 0 0 12px;
}

@media (max-width: 1280px) {
  .orders-filters__row {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }

  .orders-filter-panel__reset {
    grid-column: 1 / -1;
    justify-content: flex-end;
  }
}

@media (max-width: 700px) {
  .orders-filters__row {
    grid-template-columns: minmax(0, 1fr);
  }

  .orders-filter-panel__reset {
    grid-column: auto;
    justify-content: stretch;
  }

  .orders-filter-panel__reset .ui-btn {
    width: 100%;
  }
}

.order-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  justify-content: flex-end;
}

.order-leads-btn {
  font-size: 12px !important;
  padding-inline: 12px !important;
  white-space: nowrap;
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

.order-type {
  border-radius: 8px;
  display: inline-block;
  font-size: 12px;
  font-weight: 700;
  line-height: 1.2;
  padding: 4px 10px;
}

.order-type--live {
  background: #efecff;
  color: #3512bc;
}

.order-type--stock {
  background: #eef0f6;
  color: #3d446e;
}
</style>