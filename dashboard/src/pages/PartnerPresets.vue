<template>
  <div>
    <div class="page-head">
      <div>
        <h1>Presets</h1>
        <p>Leads this organization offers. An order is for today or tomorrow.</p>
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
        <UiField label="Gender" icon="mdi-gender-male-female">
          <UiSelect v-model="gender" :items="genderItems" placeholder="All genders" empty-label="All genders" clearable />
        </UiField>
        <UiField label="Age" icon="mdi-account-outline">
          <UiSelect v-model="age" :items="ages" placeholder="All ages" empty-label="All ages" clearable />
        </UiField>
      </div>
      <div v-if="filtering" class="lead-filters__actions">
        <UiButton variant="ghost" @click="clearFilters">Clear</UiButton>
      </div>
    </section>

    <v-alert v-if="market.error" type="error" variant="tonal" class="mb-4">{{ market.error }}</v-alert>

    <UiTableCard title="Presets">
      <template #actions>
        <UiButton variant="outline" icon="mdi-refresh" :loading="market.loading" @click="market.loadPresets()">
          Refresh
        </UiButton>
      </template>
      <UiDataTable :headers="headers" :items="rows" :loading="market.loading" item-value="id" hover>
        <template #item.country="{ item }">
          <span class="country-pick">
            <CountryFlag :code="item.country" :size="16" />
            {{ item.country }}
          </span>
        </template>
        <template #item.trafficSource="{ item }">
          {{ trafficLabel(item.trafficSource) }}
        </template>
        <template #item.gender="{ item }">
          {{ genderLabel(item.gender) }}
        </template>
        <template #item.cplPriceCents="{ item }">
          {{ dollars(item.cplPriceCents / 100) }}
        </template>
        <template #item.cpaPriceCents="{ item }">
          {{ dollars(item.cpaPriceCents / 100) }}
        </template>
        <template #item.actions="{ item }">
          <UiButton variant="outline" @click="order(item.id)">Create order</UiButton>
        </template>
        <template #no-data>
          <div class="py-8 text-medium-emphasis">{{ empty }}</div>
        </template>
      </UiDataTable>
    </UiTableCard>

    <OrderFormDialog v-model="open" :initial-preset-id="presetId" />
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import CountryFlag from '@/components/CountryFlag.vue'
import OrderFormDialog from '@/components/OrderFormDialog.vue'
import UiButton from '@/components/ui/UiButton.vue'
import UiDataTable from '@/components/ui/UiDataTable.vue'
import UiField from '@/components/ui/UiField.vue'
import UiSelect from '@/components/ui/UiSelect.vue'
import UiTableCard from '@/components/ui/UiTableCard.vue'
import { COUNTRY_SELECT_ITEMS } from '@/countries'
import { genderLabel, trafficLabel } from '@/marketOptions'
import { dollars } from '@/money'
import { useMarketStore, type PresetRow } from '@/stores/market'

const market = useMarketStore()
const open = ref(false)
const presetId = ref<string | null>(null)
const country = ref<string | null>(null)
const platform = ref<string | null>(null)
const gender = ref<string | null>(null)
const age = ref<string | null>(null)

const headers = [
  { title: 'Country', key: 'country' },
  { title: 'Traffic', key: 'trafficSource' },
  { title: 'Age', key: 'age' },
  { title: 'Gender', key: 'gender' },
  { title: 'CR', key: 'cr' },
  { title: 'CPL', key: 'cplPriceCents' },
  { title: 'CPA', key: 'cpaPriceCents' },
  { title: '', key: 'actions', sortable: false },
]

const countryItems = computed(() => {
  const codes = new Set(market.presets.map((preset) => preset.country))
  return COUNTRY_SELECT_ITEMS.filter((item) => codes.has(item.value))
})

const platforms = computed(() => distinct((preset) => preset.trafficSource, trafficLabel))
const genderItems = computed(() => distinct((preset) => preset.gender, genderLabel))
const ages = computed(() => distinct((preset) => preset.age, (value) => value))
const filtering = computed(() => Boolean(country.value || platform.value || gender.value || age.value))

const rows = computed(() => market.presets.filter((preset) => {
  if (country.value && preset.country !== country.value) return false
  if (platform.value && preset.trafficSource !== platform.value) return false
  if (gender.value && preset.gender !== gender.value) return false
  if (age.value && preset.age !== age.value) return false
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

function clearFilters(): void {
  country.value = null
  platform.value = null
  gender.value = null
  age.value = null
}

function order(id: string): void {
  presetId.value = id
  open.value = true
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
