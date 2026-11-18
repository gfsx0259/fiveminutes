<template>
  <div>
    <div class="page-head">
      <div>
        <h1>Presets</h1>
        <p>Country, traffic criteria and the CPL and CPA prices. An order picks one model.</p>
      </div>
      <UiButton v-if="isAdmin" variant="outline" icon="mdi-plus" @click="show">Create</UiButton>
    </div>

    <FormDialog
      v-model="open"
      title="Create preset"
      hint="A lead matches country and source. Age is optional — leave empty for any age. All covers every gender. CPL and CPA each have their own price."
      submit-label="Save"
      :error="formError"
      :loading="busy"
      @submit="create"
    >
      <UiField label="Country">
        <UiSelect v-model="country" :items="countries" placeholder="Country" searchable search-placeholder="Search country">
          <template #value="{ items }">
            <span v-if="items.length === 0">Country</span>
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
      <UiField label="Traffic source">
        <UiSelect v-model="trafficSource" :items="trafficSources" placeholder="Traffic source" />
      </UiField>
      <div class="ui-form__row">
        <UiField label="Age (optional)">
          <UiInput v-model="age" placeholder="Any age, or 25-34" />
        </UiField>
        <UiField label="Gender">
          <UiSelect v-model="gender" :items="genders" placeholder="Gender" />
        </UiField>
      </div>
      <UiField label="CR, %">
        <UiInput v-model="cr" type="number" placeholder="20" />
      </UiField>
      <div class="ui-form__row">
        <UiField label="CPL, USD">
          <UiInput v-model="cplPrice" type="number" placeholder="40" />
        </UiField>
        <UiField label="CPA, USD">
          <UiInput v-model="cpaPrice" type="number" placeholder="80" />
        </UiField>
      </div>
    </FormDialog>

    <section v-if="isAdmin" class="ui-filter-panel presets__markup-panel">
      <h2 class="presets__markup-title">Platform fee</h2>
      <p class="presets__markup-hint">
        Percent withheld from the buyer on each sold lead. Partners pay the preset catalog price.
      </p>
      <div class="presets__markup-row">
        <UiField label="Buyer fee, %" class="presets__markup-field">
          <UiInput v-model="markupPercent" type="number" min="0" max="100" placeholder="10" />
        </UiField>
        <UiButton variant="outline" :loading="markupBusy" @click="saveMarkup">Save</UiButton>
      </div>
      <p v-if="markupError" class="presets__markup-error">{{ markupError }}</p>
    </section>

    <v-alert v-if="market.error" type="error" variant="tonal" class="mb-4">{{ market.error }}</v-alert>

    <UiTableCard title="Presets">
      <template #actions>
        <UiButton variant="outline" icon="mdi-refresh" :loading="market.loading" @click="market.loadPresets()">
          Refresh
        </UiButton>
      </template>
      <UiDataTable :headers="headers" :items="market.presets" :loading="market.loading" item-value="id" hover>
        <template #item.country="{ item }">
          <span class="country-pick">
            <CountryFlag :code="item.country" :size="16" />
            {{ item.country }}
          </span>
        </template>
        <template #item.trafficSource="{ item }">
          {{ trafficLabel(item.trafficSource) }}
        </template>
        <template #item.age="{ item }">
          {{ item.age ? item.age : 'Any age' }}
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
        <template v-if="isAdmin" #item.actions="{ item }">
          <div class="preset-row-actions">
            <v-menu location="bottom end" content-class="preset-row-menu">
              <template #activator="{ props: menuProps }">
                <button
                  type="button"
                  class="preset-row-actions__btn"
                  v-bind="menuProps"
                  :aria-label="`Действия для пресета ${item.country}`"
                  title="Ещё"
                >
                  <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="5" cy="12" r="1" />
                    <circle cx="12" cy="12" r="1" />
                    <circle cx="19" cy="12" r="1" />
                  </svg>
                </button>
              </template>
              <div class="preset-row-menu__list">
                <button type="button" class="preset-row-menu__danger" @click="confirmDelete(item)">
                  <v-icon icon="mdi-delete" size="16" />
                  Delete
                </button>
              </div>
            </v-menu>
          </div>
        </template>
        <template #no-data>
          <div class="py-8 text-medium-emphasis">Пресетов пока нет</div>
        </template>
      </UiDataTable>
    </UiTableCard>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { api } from '@/api'
import CountryFlag from '@/components/CountryFlag.vue'
import FormDialog from '@/components/FormDialog.vue'
import UiButton from '@/components/ui/UiButton.vue'
import UiDataTable from '@/components/ui/UiDataTable.vue'
import UiField from '@/components/ui/UiField.vue'
import UiInput from '@/components/ui/UiInput.vue'
import UiSelect from '@/components/ui/UiSelect.vue'
import UiTableCard from '@/components/ui/UiTableCard.vue'
import { COUNTRY_SELECT_ITEMS } from '@/countries'
import { genders, genderLabel, trafficLabel, trafficSources } from '@/marketOptions'
import { dollars } from '@/money'
import { useMarketStore, type PresetRow } from '@/stores/market'
import { useSessionStore } from '@/stores/session'

const session = useSessionStore()
const market = useMarketStore()
const isAdmin = computed(() => session.payload?.user?.role === 'admin')
const markupPercent = ref('10')
const markupBusy = ref(false)
const markupError = ref('')
const open = ref(false)
const busy = ref(false)
const formError = ref('')
const countries = COUNTRY_SELECT_ITEMS
const country = ref<string | null>(null)
const trafficSource = ref<string | null>(null)
const age = ref('')
const gender = ref<string | null>('all')
const cr = ref('20')
const cplPrice = ref('40')
const cpaPrice = ref('80')

const headers = computed(() => {
  const base = [
    { title: 'Country', key: 'country' },
    { title: 'Traffic', key: 'trafficSource' },
    { title: 'Age', key: 'age' },
    { title: 'Gender', key: 'gender' },
    { title: 'CR', key: 'cr' },
    { title: 'CPL', key: 'cplPriceCents' },
    { title: 'CPA', key: 'cpaPriceCents' },
  ]
  if (isAdmin.value) {
    base.push({ title: '', key: 'actions', sortable: false, align: 'end' as const, width: 48 })
  }
  return base
})

function show(): void {
  formError.value = ''
  country.value = null
  trafficSource.value = null
  age.value = ''
  gender.value = 'all'
  cr.value = '20'
  cplPrice.value = '40'
  cpaPrice.value = '80'
  open.value = true
}

async function confirmDelete(item: PresetRow): Promise<void> {
  const label = [item.country, trafficLabel(item.trafficSource), item.age || 'any age'].join(' · ')
  if (!confirm(`Удалить пресет ${label}?`)) {
    return
  }
  try {
    await market.deletePreset(item.id)
  } catch (error) {
    market.error = error instanceof Error ? error.message : 'Пресет не удалился'
  }
}

async function create(): Promise<void> {
  if (!country.value || !trafficSource.value || !gender.value) {
    formError.value = 'Выберите страну, источник и пол'
    return
  }
  busy.value = true
  formError.value = ''
  try {
    await market.createPreset({
      country: country.value,
      traffic_source: trafficSource.value,
      age: age.value.trim(),
      gender: gender.value,
      cr: Number(cr.value),
      cpl_price: Number(cplPrice.value),
      cpa_price: Number(cpaPrice.value),
    })
    open.value = false
  } catch (error) {
    formError.value = error instanceof Error ? error.message : 'Пресет не создался'
  } finally {
    busy.value = false
  }
}

watch(
  () => session.payload?.user?.organization?.markupPercent,
  (value) => {
    if (value != null) {
      markupPercent.value = String(value)
    }
  },
  { immediate: true },
)

async function saveMarkup(): Promise<void> {
  markupBusy.value = true
  markupError.value = ''
  try {
    const response = await api('/organization/markup', {
      method: 'POST',
      body: JSON.stringify({ markup_percent: Number(markupPercent.value) }),
    })
    if (!response.ok) {
      const body = await response.json().catch(() => ({})) as { error?: string }
      markupError.value = body.error ?? `Не сохранилось: ${response.status}`
      return
    }
    const body = await response.json() as { markupPercent?: number }
    if (session.payload?.user?.organization && body.markupPercent != null) {
      session.payload.user.organization.markupPercent = body.markupPercent
    }
  } catch {
    markupError.value = 'Не сохранилось'
  } finally {
    markupBusy.value = false
  }
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
  min-width: 0;
}

.country-pick > span {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.presets__markup-title {
  color: #101024;
  font-size: var(--lm-font-section);
  font-weight: 700;
  letter-spacing: -0.5px;
  margin: 0 0 8px;
}

.presets__markup-hint {
  color: var(--lm-muted);
  font-size: 13px;
  margin: 0 0 16px;
}

.presets__markup-row {
  align-items: flex-end;
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
}

.presets__markup-field {
  flex: 0 0 auto;
  min-width: 11rem;
  width: 11rem;
}

.presets__markup-error {
  color: rgb(var(--v-theme-error));
  font-size: 13px;
  margin: 8px 0 0;
}

.preset-row-actions {
  align-items: center;
  color: var(--lm-primary-deep);
  display: flex;
  gap: 6px;
  justify-content: flex-end;
}

.preset-row-actions__btn {
  align-items: center;
  appearance: none;
  background: transparent;
  border: 0;
  border-radius: 5px;
  color: inherit;
  cursor: pointer;
  display: flex;
  padding: 3px;
}

.preset-row-actions__btn:hover,
.preset-row-actions__btn[aria-expanded='true'] {
  background: var(--lm-lavender);
}

.preset-row-menu__list {
  background: #fff;
  border: 1px solid var(--lm-border);
  border-radius: 12px;
  box-shadow: 0 8px 24px #27206414;
  display: flex;
  flex-direction: column;
  gap: 3px;
  min-width: 140px;
  padding: 6px;
}

.preset-row-menu__list > button {
  align-items: center;
  appearance: none;
  background: transparent;
  border: 0;
  border-radius: 8px;
  color: var(--lm-foreground);
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

.preset-row-menu__list > button:hover,
.preset-row-menu__list > button:focus-visible {
  background: var(--lm-lavender);
  color: var(--lm-primary-deep);
  font-weight: 600;
  outline: none;
}

.preset-row-menu__list > button.preset-row-menu__danger:hover,
.preset-row-menu__list > button.preset-row-menu__danger:focus-visible {
  background: #fde8ec;
  color: #c42f50;
}
</style>

<style>
.preset-row-menu.v-overlay__content {
  background: transparent;
  box-shadow: none;
}
</style>
