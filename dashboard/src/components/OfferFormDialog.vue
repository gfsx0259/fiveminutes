<template>
  <FormDialog
    :model-value="open"
    title="Create offer"
    hint="Today or tomorrow. The country comes from the preset."
    submit-label="Save"
    :error="formError"
    :loading="busy"
    @update:model-value="open = $event"
    @submit="create"
  >
    <UiField label="Preset">
      <UiSelect v-model="presetId" :items="presetItems" placeholder="Preset" searchable search-placeholder="Search preset" />
    </UiField>
    <UiField label="Volume">
      <UiInput v-model="volume" type="number" placeholder="10" />
    </UiField>
    <UiField label="Date">
      <UiSelect v-model="serviceOn" :items="dates" placeholder="Today" />
    </UiField>
  </FormDialog>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import FormDialog from '@/components/FormDialog.vue'
import UiField from '@/components/ui/UiField.vue'
import UiInput from '@/components/ui/UiInput.vue'
import UiSelect from '@/components/ui/UiSelect.vue'
import { genderLabel, trafficLabel } from '@/marketOptions'
import { dollars, flag } from '@/money'
import { useMarketStore } from '@/stores/market'

const props = defineProps<{
  initialPresetId?: string | null
  initialVolume?: string
  initialServiceOn?: string | null
}>()

const open = defineModel<boolean>({ default: false })

const market = useMarketStore()
const busy = ref(false)
const formError = ref('')
const presetId = ref<string | null>(null)
const volume = ref('10')
const serviceOn = ref(utcDay(0))

const dates = computed(() => [
  { title: `Today · ${shown(utcDay(0))}`, value: utcDay(0) },
  { title: `Tomorrow · ${shown(utcDay(1))}`, value: utcDay(1) },
])

const presetItems = computed(() => market.presets.map((preset) => ({
  title: `${flag(preset.country)} ${preset.country} · ${trafficLabel(preset.trafficSource)} · ${preset.age} · ${genderLabel(preset.gender)} · CPL ${dollars(preset.cplPriceCents / 100)} · CPA ${dollars(preset.cpaPriceCents / 100)}`,
  value: preset.id,
})))

watch(open, (value) => {
  if (!value) return
  if (market.presets.length === 0) void market.loadPresets()
  formError.value = ''
  presetId.value = props.initialPresetId ?? null
  volume.value = props.initialVolume || '10'
  serviceOn.value = props.initialServiceOn || utcDay(0)
})

function shown(iso: string): string {
  const [year, month, day] = iso.split('-')
  return `${day}.${month}.${year}`
}

function utcDay(offset: number): string {
  const date = new Date()
  date.setUTCDate(date.getUTCDate() + offset)
  return date.toISOString().slice(0, 10)
}

async function create(): Promise<void> {
  const preset = market.presets.find((item) => item.id === presetId.value)
  if (!preset) {
    formError.value = 'Выберите пресет'
    return
  }
  busy.value = true
  formError.value = ''
  try {
    await market.createOffer({
      preset_id: preset.id,
      geo: preset.country,
      volume: Number(volume.value),
      kind: 'day',
      service_on: serviceOn.value,
    })
    open.value = false
  } catch (error) {
    formError.value = error instanceof Error ? error.message : 'Предложение не создалось'
  } finally {
    busy.value = false
  }
}
</script>
