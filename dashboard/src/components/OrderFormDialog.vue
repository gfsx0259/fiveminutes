<template>
  <FormDialog
    :model-value="open"
    title="Create order"
    hint="Today or tomorrow. The country comes from the preset."
    submit-label="Save"
    :error="formError"
    :loading="busy"
    :max-width="640"
    @update:model-value="open = $event"
    @submit="create"
  >
    <UiField label="Preset">
      <UiSelect v-model="presetId" :items="presetItems" placeholder="Preset" searchable search-placeholder="Search preset">
        <template #value="{ items }">
          <span v-if="items.length === 0">Preset</span>
          <span v-else class="preset-pick">
            <CountryFlag :code="countryOf(items[0].value)" :size="16" />
            <span>{{ items[0].title }}</span>
          </span>
        </template>
        <template #option="{ item }">
          <CountryFlag :code="countryOf(item.value)" :size="16" />
          <span>{{ item.title }}</span>
        </template>
      </UiSelect>
    </UiField>
    <div class="ui-form__row">
      <UiField label="Volume">
        <UiInput v-model="volume" type="number" placeholder="10" />
      </UiField>
      <UiField label="Model">
        <UiSelect v-model="model" :items="paymentModels" placeholder="CPL" />
      </UiField>
    </div>
    <UiField :label="priceLabel">
      <UiInput v-model="price" type="number" placeholder="50" />
    </UiField>
    <UiField label="Date">
      <UiSelect v-model="serviceOn" :items="dates" placeholder="Today" />
    </UiField>
    <div class="ui-form__row">
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
import { genderLabel, paymentModels, trafficLabel } from '@/marketOptions'
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
const presetId = ref<string | null>(null)
const volume = ref('10')
const price = ref('50')
const model = ref('cpl')
const serviceOn = ref(utcDay(0))
const opensAt = ref('00:00')
const closesAt = ref('23:59')

const dates = computed(() => [
  { title: `Today · ${shown(utcDay(0))}`, value: utcDay(0) },
  { title: `Tomorrow · ${shown(utcDay(1))}`, value: utcDay(1) },
])

const presetItems = computed(() => market.presets.map((preset) => ({
  title: `${preset.country} · ${trafficLabel(preset.trafficSource)} · ${preset.age} · ${genderLabel(preset.gender)} · CPL ${dollars(preset.cplPriceCents / 100)} · CPA ${dollars(preset.cpaPriceCents / 100)}`,
  value: preset.id,
})))

const priceLabel = computed(() => {
  const preset = selected()
  if (!preset) return 'Price, USD'
  const cents = model.value === 'cpa' ? preset.cpaPriceCents : preset.cplPriceCents
  return `Price, USD · from ${dollars(cents / 100)}`
})

watch(open, (value) => {
  if (!value) return
  formError.value = ''
  presetId.value = props.initialPresetId ?? null
  volume.value = '10'
  model.value = 'cpl'
  price.value = String(floor())
  serviceOn.value = utcDay(0)
  opensAt.value = '00:00'
  closesAt.value = '23:59'
})

watch([presetId, model], () => {
  if (!open.value) return
  price.value = String(floor())
})

function selected() {
  return market.presets.find((item) => item.id === presetId.value)
}

function countryOf(id: string | number): string {
  return market.presets.find((item) => item.id === String(id))?.country ?? ''
}

function floor(): number {
  const preset = selected()
  if (!preset) return 50
  return (model.value === 'cpa' ? preset.cpaPriceCents : preset.cplPriceCents) / 100
}

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
  const preset = selected()
  if (!preset) {
    formError.value = 'Выберите пресет'
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
      payment_model: model.value,
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
