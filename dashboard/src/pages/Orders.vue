<template>
  <div>
    <div class="page-head">
      <div>
        <h1>{{ isPartner ? 'My orders' : 'Orders' }}</h1>
        <p v-if="isPartner">Day and stock orders you opened. Create new day orders from Buy leads.</p>
        <p v-else>Day orders for today or tomorrow. Partner payout is at or above the preset floor; the platform fee is charged to the buyer.</p>
      </div>
      <UiButton v-if="isPartner" variant="outline" icon="mdi-cart-outline" @click="goBuy">Buy leads</UiButton>
      <UiButton v-else variant="outline" icon="mdi-plus" @click="show">Create</UiButton>
    </div>

    <OrderFormDialog v-if="!isPartner" v-model="open" />

    <v-alert v-if="actionError || market.error" type="error" variant="tonal" class="mb-4">
      {{ actionError || market.error }}
    </v-alert>

    <section class="ui-filter-panel orders-filter-panel" aria-label="Order filters">
      <div class="orders-filters__toolbar">
        <div class="orders-filters__main">
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
          <UiLabeledField label="Type">
            <UiSelect
              v-model="filterOrderType"
              :items="orderTypeItems"
              placeholder="Any type"
              empty-label="Any type"
              clearable
            />
          </UiLabeledField>
          <UiField label="Date" icon="mdi-calendar-range">
            <UiDateField v-model="filterServiceOn" label="Date" class="orders-filter-date" />
          </UiField>
        </div>
        <div class="orders-filters__toolbar-end">
          <UiButton
            variant="outline"
            :icon="advancedOpen ? 'mdi-chevron-up' : 'mdi-tune-variant'"
            @click="advancedOpen = !advancedOpen"
          >
            More filters
            <span v-if="advancedFiltersCount > 0" class="orders-filters__badge">{{ advancedFiltersCount }}</span>
          </UiButton>
          <UiButton variant="ghost" icon="mdi-refresh" @click="resetFilters">Reset</UiButton>
        </div>
      </div>
      <div v-show="advancedOpen" class="orders-filters__advanced">
        <UiLabeledField label="Status">
          <UiSelect
            v-model="filterStatus"
            :items="statusItems"
            placeholder="Any status"
            empty-label="Any status"
            clearable
          />
        </UiLabeledField>
      </div>
    </section>

    <p v-if="!market.loading" class="orders-count">{{ filteredOrders.length }} orders</p>

    <UiTableCard title="Orders">
      <template #actions>
        <UiButton variant="outline" icon="mdi-refresh" :loading="market.loading" @click="refresh()">
          Refresh
        </UiButton>
      </template>
      <UiDataTable
        v-model:page="page"
        v-model:items-per-page="itemsPerPage"
        class="orders-table"
        :headers="headers"
        :items="filteredOrders"
        :loading="market.loading"
        :items-per-page-options="[10, 25, 50]"
        item-value="id"
        hover
      >
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
        <template #item.orderActions="{ item }">
          <div class="order-actions ui-table-cell-actions">
            <UiButton
              v-if="isPartner"
              class="order-actions__btn"
              variant="outline"
              @click="goOrderLeads(item.id)"
            >
              My leads
            </UiButton>
            <v-menu
              v-if="orderHasLifecycleActions(item)"
              location="bottom end"
              content-class="order-row-menu"
            >
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
                  v-if="item.status === 'in_progress'"
                  type="button"
                  :disabled="acting === item.id"
                  @click="act(item.id, 'pause')"
                >
                  Pause
                </button>
                <button
                  v-if="item.status === 'paused'"
                  type="button"
                  :disabled="acting === item.id"
                  @click="act(item.id, 'resume')"
                >
                  Resume
                </button>
                <button
                  v-if="item.status !== 'finished'"
                  type="button"
                  :disabled="acting === item.id"
                  @click="act(item.id, 'finish')"
                >
                  Finish
                </button>
              </div>
            </v-menu>
            <button
              v-else
              type="button"
              class="order-actions__more"
              disabled
              aria-disabled="true"
              :title="isPartner ? 'No more actions' : 'No actions'"
            >
              <v-icon icon="mdi-dots-horizontal" size="20" />
            </button>
          </div>
        </template>
        <template #no-data>
          <div class="py-8 text-medium-emphasis">Заказов пока нет</div>
        </template>
        <template #bottom>
          <UiTablePagination
            :page="page"
            :page-count="pageCount"
            :items-length="filteredOrders.length"
            :items-per-page="itemsPerPage"
            :items-per-page-options="[10, 25, 50]"
            @update:page="page = $event"
            @update:items-per-page="itemsPerPage = $event"
          />
        </template>
      </UiDataTable>
    </UiTableCard>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import CountryMultiSelect from '@/components/CountryMultiSelect.vue'
import CountryPick from '@/components/CountryPick.vue'
import OrderFormDialog from '@/components/OrderFormDialog.vue'
import StatusChip from '@/components/StatusChip.vue'
import StockVolumeBar from '@/components/StockVolumeBar.vue'
import UiButton from '@/components/ui/UiButton.vue'
import UiDataTable from '@/components/ui/UiDataTable.vue'
import UiDateField from '@/components/ui/UiDateField.vue'
import UiField from '@/components/ui/UiField.vue'
import UiLabeledField from '@/components/ui/UiLabeledField.vue'
import UiSelect from '@/components/ui/UiSelect.vue'
import UiTableCard from '@/components/ui/UiTableCard.vue'
import UiTablePagination from '@/components/ui/UiTablePagination.vue'
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
import { showToast } from '@/composables/useToast'
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

const page = ref(1)
const itemsPerPage = ref(25)

const filterVerticals = ref<string[]>([])
const filterCountries = ref<string[]>([])
const filterTraffics = ref<string[]>([])
const filterOrderType = ref<'live' | 'stock' | null>(null)
const filterServiceOn = ref('')
const filterStatus = ref<string | null>(null)
const advancedOpen = ref(false)

const orderTypeItems = [
  { title: 'Live', value: 'live' as const },
  { title: 'Stock', value: 'stock' as const },
]

const advancedFiltersCount = computed(() => (filterStatus.value ? 1 : 0))

watch(advancedFiltersCount, (count) => {
  if (count > 0) {
    advancedOpen.value = true
  }
}, { immediate: true })

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
    if (filterOrderType.value === 'live' && row.stock) {
      return false
    }
    if (filterOrderType.value === 'stock' && !row.stock) {
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

const pageCount = computed(() => Math.max(1, Math.ceil(filteredOrders.value.length / itemsPerPage.value)))

watch(filteredOrders, () => {
  if (page.value > pageCount.value) {
    page.value = pageCount.value
  }
})

function resetFilters(): void {
  filterVerticals.value = []
  filterCountries.value = []
  filterTraffics.value = []
  filterOrderType.value = null
  filterServiceOn.value = ''
  filterStatus.value = null
  advancedOpen.value = false
}

function orderTypeLabel(item: OrderRow): string {
  return item.stock ? 'Stock' : 'Live'
}

const partnerLeadColumns = [
  { title: 'Order ID', key: 'id', sortable: false, align: 'start' as const },
  { title: 'Vertical', key: 'vertical', align: 'start' as const },
  { title: 'Country', key: 'geo', align: 'start' as const },
  { title: 'Traffic source', key: 'trafficSource', align: 'start' as const },
  { title: 'Payment model', key: 'paymentModel', align: 'center' as const },
  { title: 'Type', key: 'orderType', align: 'center' as const },
  { title: 'Date', key: 'whenDay', align: 'center' as const },
  { title: 'Supplied', key: 'supplied', align: 'center' as const, width: '9rem' },
  { title: 'Order cost', key: 'orderCostCents', align: 'end' as const },
]

const headers = computed(() => {
  if (isPartner.value) {
    return [
      ...partnerLeadColumns,
      { title: 'Status', key: 'status', align: 'center' as const },
      { title: '', key: 'orderActions', sortable: false, align: 'end' as const, width: '9.5rem' },
    ]
  }
  return [
    ...partnerLeadColumns,
    { title: 'Catalog price', key: 'priceCents', align: 'end' as const },
    { title: 'Partner pays', key: 'partnerPriceCents', align: 'end' as const },
    { title: 'Status', key: 'status', align: 'center' as const },
    { title: '', key: 'orderActions', sortable: false, align: 'end' as const, width: '3.5rem' },
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

function orderHasLifecycleActions(item: OrderRow): boolean {
  return item.status !== 'finished'
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
})
</script>

<style scoped>
.orders-filter-panel {
  display: grid;
  gap: 12px;
  margin-bottom: 16px;
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
  grid-template-columns: repeat(5, minmax(0, 1fr));
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
  grid-template-columns: minmax(0, 1fr);
  max-width: 240px;
  min-width: 0;
}

.orders-filter-date {
  display: block;
  width: 100%;
}

.orders-count {
  color: #5c6078;
  font-size: 13px;
  font-weight: 600;
  margin: 0 0 12px;
}

.orders-table :deep(.v-data-table-footer) {
  display: none;
}

@media (max-width: 1280px) {
  .orders-filters__toolbar {
    flex-wrap: wrap;
  }

  .orders-filters__main {
    grid-template-columns: repeat(2, minmax(0, 1fr));
    width: 100%;
  }

  .orders-filters__toolbar-end {
    margin-left: auto;
  }

  .orders-filters__advanced {
    max-width: none;
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 700px) {
  .orders-filters__main,
  .orders-filters__advanced {
    grid-template-columns: minmax(0, 1fr);
  }

  .orders-filters__toolbar-end {
    display: grid;
    gap: 8px;
    grid-template-columns: 1fr;
    height: auto;
    width: 100%;
  }

  .orders-filters__toolbar-end :deep(.ui-btn) {
    width: 100%;
  }
}

.order-actions {
  align-items: center;
  display: inline-flex;
  flex-wrap: nowrap;
  gap: 8px;
  justify-content: flex-end;
}

.order-actions__btn {
  flex-shrink: 0;
  font-size: 12px !important;
  height: 34px !important;
  letter-spacing: 0 !important;
  min-height: 34px !important;
  min-width: 0 !important;
  padding-inline: 12px !important;
  white-space: nowrap;
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

.order-row-menu__list > button:hover:not(:disabled),
.order-row-menu__list > button:focus-visible:not(:disabled) {
  background: #efecff;
  color: #3512bc;
  outline: none;
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

<style>
.order-row-menu.v-overlay__content {
  background: transparent;
  border: 0;
  box-shadow: none;
  overflow: visible;
  padding: 0;
}
</style>