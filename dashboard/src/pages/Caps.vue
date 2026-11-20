<template>
  <div>
    <div class="mb-4">
      <h1>Caps</h1>
      <p>{{ isBuyer ? 'Today and tomorrow.' : 'Today, tomorrow and the warehouse.' }} Names of buyers stay out of this list.</p>
    </div>

    <section class="ui-filter-panel">
      <div class="lead-filters">
        <UiField label="Date" icon="mdi-calendar">
          <UiSelect v-model="when" :items="whenItems" placeholder="All dates" empty-label="All dates" clearable />
        </UiField>
        <UiField v-if="when === 'date'" label="On">
          <UiDateField v-model="onDate" label="On" />
        </UiField>
        <UiField label="Platform" icon="mdi-bullhorn-outline">
          <UiSelect v-model="platform" :items="platforms" placeholder="All platforms" empty-label="All platforms" clearable />
        </UiField>
        <UiField label="Gender" icon="mdi-gender-male-female">
          <UiSelect v-model="gender" :items="genders" placeholder="All genders" empty-label="All genders" clearable />
        </UiField>
      </div>
      <div v-if="filtering" class="lead-filters__actions">
        <UiButton variant="ghost" @click="clearFilters">Clear</UiButton>
      </div>
    </section>

    <v-alert v-if="market.error" type="error" variant="tonal" class="mb-4">{{ market.error }}</v-alert>

    <UiTableCard title="Caps">
      <template #actions>
        <UiButton variant="outline" icon="mdi-refresh" :loading="market.loading" @click="market.loadCaps()">
          Refresh
        </UiButton>
        <UiButton variant="outline" icon="mdi-download" :disabled="rows.length === 0" @click="exportCsv">
          Export
        </UiButton>
      </template>
      <UiDataTable :headers="headers" :items="rows" :loading="market.loading" item-value="key" hover>
        <template #item.trafficSource="{ item }">
          {{ trafficLabel(item.trafficSource) }}
        </template>
        <template #item.gender="{ item }">
          {{ genderLabel(item.gender) }}
        </template>
        <template #item.paymentModel="{ item }">
          {{ modelLabel(item.paymentModel) }}
        </template>
        <template #item.geo="{ item }">
          <CountryPick :code="item.geo" />
        </template>
        <template #item.when="{ item }">
          {{ item.stock ? 'Stock' : item.serviceOn }}
        </template>
        <template #item.supplied="{ item }">
          {{ item.supplied }} / {{ item.volume }}
        </template>
        <template #item.actions="{ item }">
          <UiButton v-if="isBuyer" variant="outline" @click="offer(item)">Create offer</UiButton>
        </template>
        <template #no-data>
          <div class="py-8 text-medium-emphasis">{{ empty }}</div>
        </template>
      </UiDataTable>
    </UiTableCard>

    <OfferFormDialog
      v-if="isBuyer"
      v-model="open"
      :initial-preset-id="presetId"
      :initial-volume="volume"
      :initial-service-on="serviceOn"
    />
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import CountryPick from '@/components/CountryPick.vue'
import OfferFormDialog from '@/components/OfferFormDialog.vue'
import UiButton from '@/components/ui/UiButton.vue'
import UiDataTable from '@/components/ui/UiDataTable.vue'
import UiDateField from '@/components/ui/UiDateField.vue'
import UiField from '@/components/ui/UiField.vue'
import UiSelect from '@/components/ui/UiSelect.vue'
import UiTableCard from '@/components/ui/UiTableCard.vue'
import { countryName } from '@/countries'
import { exportTableCsv, cellToString } from '@/tableCsvExport'
import { genderLabel, modelLabel, trafficLabel } from '@/marketOptions'
import { useMarketStore, type CapRow } from '@/stores/market'
import { useSessionStore } from '@/stores/session'
import { prepareTableHeaders } from '@/tableHeaders'

const market = useMarketStore()
const session = useSessionStore()
const isBuyer = computed(() => session.payload?.user?.role === 'buyer')
const open = ref(false)
const presetId = ref<string | null>(null)
const volume = ref('10')
const serviceOn = ref<string | null>(null)
const when = ref<string | null>(null)
const onDate = ref('')
const platform = ref<string | null>(null)
const gender = ref<string | null>(null)

const headers = computed(() => prepareTableHeaders(
  [
    { title: 'Traffic', key: 'trafficSource' },
    { title: 'Age', key: 'age', align: 'center' as const },
    { title: 'Gender', key: 'gender' },
    { title: 'Model', key: 'paymentModel' },
    { title: 'CR', key: 'cr', align: 'center' as const },
    { title: 'Geo', key: 'geo' },
    { title: 'Date', key: 'when' },
    { title: 'Supplied', key: 'supplied', align: 'end' as const },
    ...(isBuyer.value ? [{ title: '', key: 'actions', sortable: false, align: 'end' as const }] : []),
  ],
  {
    trafficSource: 132,
    age: 72,
    gender: 88,
    paymentModel: 100,
    cr: 72,
    geo: 120,
    when: 108,
    supplied: 96,
    actions: 120,
  },
))

const whenItems = [
  { title: 'Today', value: 'today' },
  { title: 'Tomorrow', value: 'tomorrow' },
  { title: 'On date', value: 'date' },
]

const listed = computed(() => market.caps.filter((cap) => !isBuyer.value || !cap.stock))
const platforms = computed(() =>
  [...new Set(listed.value.map((cap) => cap.trafficSource).filter(Boolean))]
    .sort()
    .map((value) => ({ title: trafficLabel(value), value })),
)
const genders = computed(() =>
  [...new Set(listed.value.map((cap) => cap.gender).filter(Boolean))]
    .sort()
    .map((value) => ({ title: genderLabel(value), value })),
)
const filtering = computed(() => Boolean(when.value || platform.value || gender.value))

const filtered = computed(() => listed.value.filter((cap) => {
  if (platform.value && cap.trafficSource !== platform.value) return false
  if (gender.value && cap.gender !== gender.value) return false
  return matchesWhen(cap)
}))

const rows = computed(() => filtered.value.map((cap) => ({
  ...cap,
  key: `${cap.presetId}|${cap.paymentModel}|${cap.geo}|${cap.serviceOn ?? 'stock'}`,
})))

const empty = computed(() => filtering.value && listed.value.length > 0 ? 'Нет кап по этим фильтрам' : 'Открытых кап нет')

function exportCsv(): void {
  exportTableCsv('caps.csv', headers.value, rows.value, capExportCell)
}

function capExportCell(key: string, item: CapRow): string {
  switch (key) {
    case 'trafficSource':
      return trafficLabel(item.trafficSource)
    case 'gender':
      return genderLabel(item.gender)
    case 'paymentModel':
      return modelLabel(item.paymentModel)
    case 'geo':
      return countryName(item.geo)
    case 'when':
      return item.stock ? 'Stock' : (item.serviceOn ?? '')
    case 'supplied':
      return `${item.supplied} / ${item.volume}`
    default:
      return cellToString((item as Record<string, unknown>)[key])
  }
}

function matchesWhen(cap: CapRow): boolean {
  if (!when.value) return true
  if (cap.stock) return false
  const day = when.value === 'today' ? utcDay(0) : when.value === 'tomorrow' ? utcDay(1) : onDate.value
  return day !== '' && cap.serviceOn === day
}

function utcDay(offset: number): string {
  const date = new Date()
  date.setUTCDate(date.getUTCDate() + offset)
  return date.toISOString().slice(0, 10)
}

async function offer(cap: CapRow): Promise<void> {
  if (market.presets.length === 0) await market.loadPresets()
  presetId.value = cap.presetId
  volume.value = String(Math.max(cap.volume - cap.supplied, 1))
  serviceOn.value = cap.serviceOn
  open.value = true
}

function clearFilters(): void {
  when.value = null
  onDate.value = ''
  platform.value = null
  gender.value = null
}

onMounted(() => {
  void market.loadCaps()
})
</script>
