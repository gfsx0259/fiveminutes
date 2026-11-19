<template>
  <FormDialog
    :model-value="open"
    title="Create order"
    hint="Create a new order to purchase leads from the marketplace."
    submit-label="Create order"
    submit-icon="mdi-cart-outline"
    :error="formError"
    :loading="busy"
    :max-width="680"
    @update:model-value="open = $event"
    @submit="create"
  >
    <PresetOfferSummary
      v-if="matchedPreset"
      :heading="`Preset ${presetDisplayId(matchedPreset.id)}`"
      :vertical="matchedPreset.vertical"
      :country="matchedPreset.country"
      :traffic-source="matchedPreset.trafficSource"
      :payment-model="matchedPreset.paymentModel"
      :payout-cents="matchedPreset.floorPriceCents"
      amount-label="Price"
      :conversion="matchedPreset.cr"
    />

    <template v-if="!fieldsLocked">
      <UiField label="Vertical *" :icon="vertical ? verticalIcon(vertical) : 'mdi-shape-outline'">
        <UiSelect
          v-model="vertical"
          :items="verticalItems"
          placeholder="Select vertical"
          conceal-field-icon
        >
          <template #value="{ items }">
            <span v-if="items.length === 0" class="order-form__placeholder">Select vertical</span>
            <span v-else class="order-form__pick">
              <v-icon :icon="verticalIcon(String(items[0].value))" :color="verticalColor(String(items[0].value))" size="16" />
              <span>{{ items[0].title }}</span>
            </span>
          </template>
          <template #option="{ item }">
            <v-icon :icon="verticalIcon(String(item.value))" :color="verticalColor(String(item.value))" size="16" />
            <span>{{ item.title }}</span>
          </template>
        </UiSelect>
      </UiField>

      <div class="ui-form__row">
        <UiField label="Country *" icon="mdi-earth">
          <UiSelect
            v-model="country"
            :items="countryItems"
            placeholder="Country"
            searchable
            search-placeholder="Search country"
            :disabled="!vertical"
            conceal-field-icon
          >
            <template #value="{ items }">
              <span v-if="items.length === 0" class="order-form__placeholder">Country</span>
              <span v-else class="order-form__pick">
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
        <UiField label="Traffic source *" icon="mdi-traffic-light">
          <UiSelect
            v-model="trafficSource"
            :items="trafficItems"
            placeholder="Source"
            :disabled="!country"
            conceal-field-icon
          >
            <template #value="{ items }">
              <span v-if="items.length === 0" class="order-form__placeholder">Source</span>
              <span v-else class="order-form__pick">
                <v-icon :icon="trafficIcon(String(items[0].value))" :color="trafficColor(String(items[0].value))" size="16" />
                <span>{{ items[0].title }}</span>
              </span>
            </template>
            <template #option="{ item }">
              <v-icon :icon="trafficIcon(String(item.value))" :color="trafficColor(String(item.value))" size="16" />
              <span>{{ item.title }}</span>
            </template>
          </UiSelect>
        </UiField>
      </div>
    </template>

    <UiField label="Quantity *" icon="mdi-counter">
      <UiInput v-model="volume" type="number" placeholder="1000" />
    </UiField>

    <UiField :label="priceLabel" icon="mdi-currency-usd">
      <UiInput v-model="price" type="number" :placeholder="pricePlaceholder" step="0.01" />
    </UiField>
    <p v-if="matchedPreset" class="order-form__floor-hint">
      Catalog floor for this preset: {{ dollars(matchedPreset.floorPriceCents / 100) }}
    </p>

    <UiField label="Date">
      <div class="order-form__date">
        <UiSegmented v-model="serviceQuick" :items="serviceQuickItems" class="order-form__segmented" />
        <UiDateField v-model="serviceOn" label="Date" class="order-form__date-field" />
      </div>
    </UiField>
    <div class="ui-form__row">
      <UiField label="Opens" icon="mdi-clock-time-four-outline">
        <UiTimeField v-model="opensAt" label="Opens" />
      </UiField>
      <UiField label="Closes" icon="mdi-clock-time-four-outline">
        <UiTimeField v-model="closesAt" label="Closes" :minute-step="15" :extra-minutes="[59]" />
      </UiField>
    </div>

    <OrderCheckoutSummary
      v-if="showCheckoutSummary"
      :price-per-lead-cents="priceCents"
      :quantity="volumeQty"
      :balance-cents="balanceCents"
      total-label="Total cost"
    />
  </FormDialog>
  <v-snackbar v-model="notice" location="bottom center" color="success" :timeout="4000">
    Заказ {{ createdId }} создан
  </v-snackbar>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import CountryFlag from '@/components/CountryFlag.vue'
import FormDialog from '@/components/FormDialog.vue'
import OrderCheckoutSummary from '@/components/OrderCheckoutSummary.vue'
import PresetOfferSummary from '@/components/PresetOfferSummary.vue'
import UiDateField from '@/components/ui/UiDateField.vue'
import UiField from '@/components/ui/UiField.vue'
import UiInput from '@/components/ui/UiInput.vue'
import UiSegmented from '@/components/ui/UiSegmented.vue'
import UiSelect from '@/components/ui/UiSelect.vue'
import UiTimeField from '@/components/ui/UiTimeField.vue'
import { defaultOpensForServiceDate } from '@/timeDefaults'
import { ISO2_COUNTRIES } from '@/countries'
import {
  trafficColor,
  trafficIcon,
  trafficLabel,
  verticalColor,
  verticalIcon,
  verticalLabel,
  verticals,
} from '@/marketOptions'
import { dollars } from '@/money'
import { presetDisplayId } from '@/orderDisplay'
import { useMarketStore, type PresetRow } from '@/stores/market'
import { useSessionStore } from '@/stores/session'

const props = defineProps<{
  initialPresetId?: string | null
}>()

const open = defineModel<boolean>({ default: false })
const emit = defineEmits<{
  created: []
}>()

const market = useMarketStore()
const session = useSessionStore()
const busy = ref(false)
const formError = ref('')
const notice = ref(false)
const createdId = ref('')

const vertical = ref<string | null>(null)
const country = ref<string | null>(null)
const trafficSource = ref<string | null>(null)
const volume = ref('1000')
const price = ref('50')
const serviceOn = ref(utcDay(0))
const serviceQuick = ref('today')
const opensAt = ref(defaultOpensForServiceDate(utcDay(0), utcDay(0)))
const closesAt = ref('23:59')

const serviceQuickItems = [
  { title: 'Today', value: 'today' },
  { title: 'Tomorrow', value: 'tomorrow' },
]

const fieldsLocked = computed(() => Boolean(props.initialPresetId))

function syncServiceQuickFromDate(): void {
  const iso = serviceOn.value
  if (iso === utcDay(0)) {
    serviceQuick.value = 'today'
  } else if (iso === utcDay(1)) {
    serviceQuick.value = 'tomorrow'
  } else {
    serviceQuick.value = 'custom'
  }
}

watch(serviceQuick, (value) => {
  if (value === 'today') {
    serviceOn.value = utcDay(0)
  } else if (value === 'tomorrow') {
    serviceOn.value = utcDay(1)
  }
})

watch(serviceOn, () => {
  if (!open.value) {
    return
  }
  syncServiceQuickFromDate()
})

function presetsMatching(partial: {
  vertical?: string | null
  country?: string | null
  trafficSource?: string | null
}): PresetRow[] {
  return market.presets.filter((preset) => {
    if (partial.vertical && preset.vertical !== partial.vertical) {
      return false
    }
    if (partial.country && preset.country !== partial.country) {
      return false
    }
    if (partial.trafficSource && preset.trafficSource !== partial.trafficSource) {
      return false
    }
    return true
  })
}

const verticalItems = computed(() => {
  const codes = new Set(market.presets.map((p) => p.vertical))
  return verticals.filter((v) => codes.has(v.value))
})

const countryItems = computed(() => {
  const codes = [...new Set(presetsMatching({ vertical: vertical.value }).map((p) => p.country))]
  return codes
    .map((code) => {
      const name = ISO2_COUNTRIES.find((c) => c.code === code)?.name ?? code
      return { title: name, value: code }
    })
    .sort((a, b) => a.title.localeCompare(b.title))
})

const trafficItems = computed(() => {
  const codes = [
    ...new Set(
      presetsMatching({ vertical: vertical.value, country: country.value }).map((p) => p.trafficSource),
    ),
  ]
  return codes.map((code) => ({ title: trafficLabel(code), value: code }))
})

const matchedPreset = computed(() => {
  if (!vertical.value || !country.value || !trafficSource.value) {
    return undefined
  }
  return presetsMatching({
    vertical: vertical.value,
    country: country.value,
    trafficSource: trafficSource.value,
  })[0]
})

const priceLabel = computed(() => 'Price (per lead) *')

const pricePlaceholder = computed(() => {
  const floor = matchedPreset.value?.floorPriceCents
  if (floor == null) {
    return '50'
  }
  return String(floor / 100)
})

const balanceCents = computed(() => session.payload?.user?.balanceCents ?? 0)

const volumeQty = computed(() => {
  const qty = Number(volume.value)
  if (!Number.isFinite(qty) || qty < 1) {
    return 0
  }
  return Math.floor(qty)
})

const priceCents = computed(() => {
  const p = Number(price.value)
  if (!Number.isFinite(p) || p <= 0) {
    return 0
  }
  return Math.round(p * 100)
})

const showCheckoutSummary = computed(() => Boolean(matchedPreset.value) && volumeQty.value > 0 && priceCents.value > 0)

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
  volume.value = '1000'
  price.value = initial ? String(initial.floorPriceCents / 100) : '50'
  serviceOn.value = utcDay(0)
  serviceQuick.value = 'today'
  opensAt.value = defaultOpensForServiceDate(utcDay(0), utcDay(0))
  closesAt.value = '23:59'
})

watch(serviceOn, (iso) => {
  if (!open.value || !iso) {
    return
  }
  opensAt.value = defaultOpensForServiceDate(iso, utcDay(0))
})

watch(vertical, (value, old) => {
  if (!open.value || value === old) {
    return
  }
  if (fieldsLocked.value) {
    return
  }
  if (country.value && !presetsMatching({ vertical: value, country: country.value }).length) {
    country.value = null
    trafficSource.value = null
  }
})

watch(country, (value, old) => {
  if (!open.value || value === old) {
    return
  }
  if (fieldsLocked.value) {
    return
  }
  if (
    trafficSource.value
    && !presetsMatching({ vertical: vertical.value, country: value, trafficSource: trafficSource.value }).length
  ) {
    trafficSource.value = null
  }
})

watch(matchedPreset, (preset) => {
  if (!open.value || !preset) {
    return
  }
  const floor = preset.floorPriceCents / 100
  const current = Number(price.value)
  if (!Number.isFinite(current) || current < floor) {
    price.value = String(floor)
  }
})

function utcDay(offset: number): string {
  const date = new Date()
  date.setUTCDate(date.getUTCDate() + offset)
  return date.toISOString().slice(0, 10)
}

async function create(): Promise<void> {
  const preset = matchedPreset.value
  if (!preset) {
    formError.value = 'Select vertical, country and traffic source that match a catalog preset'
    return
  }
  const qty = Number(volume.value)
  if (!Number.isFinite(qty) || qty < 1) {
    formError.value = 'Enter a valid quantity'
    return
  }
  const priceNum = Number(price.value)
  const floor = preset.floorPriceCents / 100
  if (!Number.isFinite(priceNum) || priceNum < floor) {
    formError.value = `Price must be at least ${dollars(floor)}`
    return
  }
  if (!serviceOn.value) {
    formError.value = 'Choose service date'
    return
  }
  busy.value = true
  formError.value = ''
  try {
    createdId.value = await market.createOrder({
      preset_id: preset.id,
      geo: preset.country,
      volume: qty,
      price: priceNum,
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
.order-form__pick {
  align-items: center;
  display: inline-flex;
  gap: 8px;
  min-width: 0;
}

.order-form__pick > span:last-child {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.order-form__placeholder {
  color: var(--lm-muted);
}

.order-form__floor-hint {
  color: #8b90a8;
  font-size: 12px;
  margin: -8px 0 12px;
}

.order-form__date {
  display: grid;
  gap: 10px;
  width: 100%;
}

.order-form__date :deep(.order-form__segmented.ui-segmented) {
  display: flex;
  width: 100%;
}

.order-form__segmented :deep(.ui-segmented__item) {
  flex: 1 1 0;
  min-width: 0;
  text-align: center;
}

.order-form__date-field {
  display: block;
  width: 100%;
}

.order-form__date-field :deep(.ui-date) {
  width: 100%;
}
</style>