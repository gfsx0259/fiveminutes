<template>
  <div>
    <div class="page-head">
      <div>
        <h1>Presets</h1>
        <p>Create a day order for a preset. Your bid must be at least the preset base price.</p>
      </div>
    </div>

    <section class="ui-filter-panel">
      <div class="lead-filters">
        <UiField label="Country" icon="mdi-earth">
          <UiSelect v-model="country" :items="countryItems" placeholder="All countries" empty-label="All countries" searchable search-placeholder="Search country" clearable>
            <template #value="{ items }">
              <span v-if="items.length === 0">All countries</span>
              <span v-else class="country-pick">
                <CountryFlag :code="String(items[0].value)" :size="16" />
                <span>{{ items[0].title }}</span>
              </span>
            </template>
            <template #option="{ item }">
              <CountryFlag :code="String(item.value)" :size="16" />
              <span>{{ item.title }}</span>
            </template>
          </UiSelect>
        </UiField>
        <UiField label="Platform" icon="mdi-bullhorn-outline">
          <UiSelect v-model="platform" :items="platforms" placeholder="All platforms" empty-label="All platforms" clearable />
        </UiField>
        <UiField label="Vertical" icon="mdi-shape-outline">
          <UiSelect v-model="verticalFilter" :items="verticalItems" placeholder="All verticals" empty-label="All verticals" clearable />
        </UiField>
        <UiField label="Model" icon="mdi-cash-multiple">
          <UiSelect v-model="modelFilter" :items="modelItems" placeholder="All models" empty-label="All models" clearable />
        </UiField>
      </div>
      <div v-if="filtering" class="lead-filters__actions">
        <UiButton variant="ghost" @click="clearFilters">Clear</UiButton>
      </div>
    </section>

    <UiTableCard title="Presets">
      <template #actions>
        <UiButton variant="outline" icon="mdi-refresh" :loading="market.loading" @click="market.loadPresets()">
          Refresh
        </UiButton>
        <UiButton variant="outline" icon="mdi-download" :disabled="rows.length === 0" @click="exportCsv">
          Export
        </UiButton>
      </template>
      <UiDataTable
        :headers="headers"
        :items="rows"
        :loading="market.loading"
        item-value="id"
        hover
        :cell-copy-value="presetTableCellCopy"
      >
        <template #item.country="{ item }">
          <CountryPick :code="item.country" />
        </template>
        <template #item.trafficSource="{ item }">
          {{ trafficLabel(item.trafficSource) }}
        </template>
        <template #item.vertical="{ item }">
          {{ verticalLabel(item.vertical) }}
        </template>
        <template #item.paymentModel="{ item }">
          {{ modelLabel(item.paymentModel) }}
        </template>
        <template #item.basePriceCents="{ item }">
          {{ dollars(item.basePriceCents / 100) }}
        </template>
        <template #item.actions="{ item }">
          <UiButton variant="outline" @click="order(item.id)">Create order</UiButton>
        </template>
        <template #no-data>
          <div class="py-8 text-medium-emphasis">{{ empty }}</div>
        </template>
      </UiDataTable>
    </UiTableCard>

    <OrderFormDialog
      v-model="open"
      :initial-preset-id="presetId"
      @stock-purchased="onStockPurchased"
    />
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useLoadErrorToast } from '@/composables/useLoadErrorToast'
import CountryFlag from '@/components/CountryFlag.vue'
import CountryPick from '@/components/CountryPick.vue'
import OrderFormDialog from '@/components/OrderFormDialog.vue'
import UiButton from '@/components/ui/UiButton.vue'
import UiDataTable from '@/components/ui/UiDataTable.vue'
import UiField from '@/components/ui/UiField.vue'
import UiSelect from '@/components/ui/UiSelect.vue'
import UiTableCard from '@/components/ui/UiTableCard.vue'
import { COUNTRY_SELECT_ITEMS, countryName } from '@/countries'
import { exportTableCsv, cellToString } from '@/tableCsvExport'
import { modelLabel, trafficLabel, verticalLabel } from '@/marketOptions'
import { dollars } from '@/money'
import { useMarketStore, type PresetRow } from '@/stores/market'
import { prepareTableHeaders } from '@/tableHeaders'
import { tableCellCopyFromExport } from '@/tableCellCopy'

const market = useMarketStore()

const presetsLoadError = computed(() => market.error)
useLoadErrorToast(presetsLoadError, () => { void market.loadPresets() })
const open = ref(false)
const presetId = ref<string | null>(null)
const country = ref<string | null>(null)
const platform = ref<string | null>(null)
const verticalFilter = ref<string | null>(null)
const modelFilter = ref<string | null>(null)

const headers = prepareTableHeaders(
  [
    { title: 'Country', key: 'country' },
    { title: 'Vertical', key: 'vertical' },
    { title: 'Traffic', key: 'trafficSource' },
    { title: 'Model', key: 'paymentModel' },
    { title: 'CR', key: 'cr', align: 'center' as const },
    { title: 'Price', key: 'basePriceCents', align: 'end' as const },
    { title: '', key: 'actions', sortable: false, align: 'end' as const },
  ],
  {
    country: 108,
    vertical: 108,
    trafficSource: 132,
    paymentModel: 100,
    cr: 72,
    basePriceCents: 88,
    actions: 120,
  },
)

const countryItems = computed(() => {
  const codes = new Set(market.presets.map((preset) => preset.country))
  return COUNTRY_SELECT_ITEMS.filter((item) => codes.has(item.value))
})

const platforms = computed(() => distinct((preset) => preset.trafficSource, trafficLabel))
const verticalItems = computed(() => distinct((preset) => preset.vertical, verticalLabel))
const modelItems = computed(() => distinct((preset) => preset.paymentModel, modelLabel))
const filtering = computed(() => Boolean(country.value || platform.value || verticalFilter.value || modelFilter.value))

const rows = computed(() => market.presets.filter((preset) => {
  if (country.value && preset.country !== country.value) return false
  if (platform.value && preset.trafficSource !== platform.value) return false
  if (verticalFilter.value && preset.vertical !== verticalFilter.value) return false
  if (modelFilter.value && preset.paymentModel !== modelFilter.value) return false
  return true
}))

const empty = computed(() => filtering.value && market.presets.length > 0
  ? 'Нет пресетов по этим фильтрам'
  : 'Пресетов пока нет')

function distinct(pick: (preset: PresetRow) => string, label: (value: string) => string) {
  return [...new Set(market.presets.map(pick).filter(Boolean))]
    .sort()
    .map((value) => ({ title: label(value), value }))
}

function exportCsv(): void {
  exportTableCsv('presets.csv', headers, rows.value, presetExportCell)
}

function presetExportCell(key: string, item: PresetRow): string {
  switch (key) {
    case 'country':
      return countryName(item.country)
    case 'trafficSource':
      return trafficLabel(item.trafficSource)
    case 'vertical':
      return verticalLabel(item.vertical)
    case 'paymentModel':
      return modelLabel(item.paymentModel)
    case 'cr':
      return `${item.cr}%`
    case 'basePriceCents':
      return dollars(item.basePriceCents / 100)
    default:
      return cellToString((item as Record<string, unknown>)[key])
  }
}

const presetTableCellCopy = tableCellCopyFromExport<PresetRow>(presetExportCell)

function clearFilters(): void {
  country.value = null
  platform.value = null
  verticalFilter.value = null
  modelFilter.value = null
}

function order(id: string): void {
  presetId.value = id
  open.value = true
}

function onStockPurchased(): void {
  void market.loadPresets()
}

onMounted(() => {
  void market.loadPresets()
})
</script>

<style scoped>
.country-pick {
  align-items: center;
  display: inline-flex;
  gap: 8px;
}
</style>
