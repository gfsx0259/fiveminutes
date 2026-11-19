<template>
  <FormDialog
    :model-value="open"
    title="Create order"
    hint="Create a new order to purchase leads from the marketplace."
    submit-label="Create order"
    :error="formError"
    :loading="busy"
    :max-width="640"
    @update:model-value="open = $event"
    @submit="create"
  >
    <UiField label="Vertical">
      <UiSelect v-model="vertical" :items="verticals" placeholder="Vertical" />
    </UiField>
    <UiField label="GEO">
      <UiSelect v-model="country" :items="countryItems" placeholder="Country" searchable search-placeholder="Search country">
        <template #value="{ items }">
          <span v-if="items.length === 0">Country</span>
          <span v-else class="preset-pick">
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
    <UiField label="Model">
      <UiSelect v-model="model" :items="paymentModels" placeholder="CPL" />
    </UiField>
    <UiField label="Traffic source">
      <UiSelect v-model="trafficSource" :items="trafficSources" placeholder="Source" />
    </UiField>
    <UiField :label="priceLabel">
      <UiInput v-model="price" type="number" placeholder="50" />
    </UiField>
    <UiField label="Date">
      <UiSelect v-model="serviceOn" :items="dates" placeholder="Today" />
    </UiField>
    <div class="ui-form__row">
      <UiField label="Quantity">
        <UiInput v-model="volume" type="number" placeholder="1000" />
      </UiField>
      <UiField label="Opens">
        <UiInput v-model="opensAt" type="time" />
      </UiField>
      <UiField label="Closes">
        <UiInput v-model="closesAt" type="time" />
      </UiField>
    </div>
  </FormDialog>
  <v-snackbar v-model="notice" location="bottom center" color="success" :timeout="4000">
    Заказ {{ createdId }} создан
  </v-snackbar>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import CountryFlag from '@/components/CountryFlag.vue'
import FormDialog from '@/components/FormDialog.vue'
import UiField from '@/components/ui/UiField.vue'
import UiInput from '@/components/ui/UiInput.vue'
import UiSelect from '@/components/ui/UiSelect.vue'
import { ISO2_COUNTRIES } from '@/countries'
import { paymentModels, trafficSources, verticals } from '@/marketOptions'
import { dollars } from '@/money'
import { useMarketStore } from '@/stores/market'

const props = defineProps<{
  initialPresetId?: string | null
}>()

const open = defineModel<boolean>({ default: false })
const emit = defineEmits<{
  created: []
}>()

const market = useMarketStore()
const busy = ref(false)
const formError = ref('')
const notice = ref(false)
const createdId = ref('')

const vertical = ref<string | null>(null)
const country = ref<string | null>(null)
const trafficSource = ref<string | null>(null)
const model = ref('cpl')
const volume = ref('1000')
const price = ref('50')
const serviceOn = ref(utcDay(0))
const opensAt = ref('00:00')
const closesAt = ref('23:59')

const countryItems = computed(() => ISO2_COUNTRIES.map((c) => ({ title: c.name, value: c.code })))

const dates = computed(() => [
  { title: `Today · ${shown(utcDay(0))}`, value: utcDay(0) },
  { title: `Tomorrow · ${shown(utcDay(1))}`, value: utcDay(1) },
])

const matchedPreset = computed(() => {
  return market.presets.find((preset) => {
    if (vertical.value && preset.vertical !== vertical.value) return false
    if (country.value && preset.country !== country.value) return false
    if (trafficSource.value && preset.trafficSource !== trafficSource.value) return false
    if (model.value && preset.paymentModel !== model.value) return false
    return true
  })
})

const priceLabel = computed(() => {
  const preset = matchedPreset.value
  if (!preset) {
    return 'Payout per lead, USD'
  }
  return `Payout per lead, USD · from ${dollars(preset.floorPriceCents / 100)}`
})

watch(open, (value) => {
  if (!value) {
    return
  }
  if (market.presets.length === 0) {
    void market.loadPresets()
  }
  formError.value = ''
  const initial = props.initialPresetId
    ? market.presets.find((p) => p.id === props.initialPresetId)
    : undefined
  vertical.value = initial?.vertical ?? null
  country.value = initial?.country ?? null
  trafficSource.value = initial?.trafficSource ?? null
  model.value = initial?.paymentModel ?? 'cpl'
  volume.value = '1000'
  price.value = initial ? String(Math.round(initial.floorPriceCents / 100)) : '50'
  serviceOn.value = utcDay(0)
  opensAt.value = '00:00'
  closesAt.value = '23:59'
})

watch([vertical, country, trafficSource, model], () => {
  if (!open.value) {
    return
  }
  const preset = matchedPreset.value
  if (preset) {
    price.value = String(Math.round(preset.floorPriceCents / 100))
  }
})

function utcDay(offset: number): string {
  const date = new Date()
  date.setUTCDate(date.getUTCDate() + offset)
  return date.toISOString().slice(0, 10)
}

function shown(iso: string): string {
  const [year, month, day] = iso.split('-')
  return `${day}.${month}.${year}`
}

async function create(): Promise<void> {
  const preset = matchedPreset.value
  if (!preset) {
    formError.value = 'No preset matches vertical, GEO, source and model'
    return
  }
  if (!country.value) {
    formError.value = 'Select GEO'
    return
  }
  busy.value = true
  formError.value = ''
  try {
    createdId.value = await market.createOrder({
      preset_id: preset.id,
      geo: preset.country,
      volume: Number(volume.value),
      price: Number(price.value),
      payment_model: preset.paymentModel,
      kind: 'day',
      service_on: serviceOn.value,
      opens_at: opensAt.value,
      closes_at: closesAt.value,
    })
    notice.value = true
    open.value = false
    emit('created')
  } catch (error) {
    formError.value = error instanceof Error ? error.message : 'Заказ не создался'
  } finally {
    busy.value = false
  }
}
</script>

<style scoped>
.preset-pick {
  align-items: center;
  display: inline-flex;
  gap: 8px;
  min-width: 0;
}

.preset-pick > span {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
</style>