<template>
  <FormDialog
    :model-value="open"
    title="Create order"
    hint="Open a day order with your targeting and volume — leads are generated for you."
    submit-label="Create order"
    submit-icon="mdi-cart-outline"
    :error="formError"
    :loading="busy"
    :max-width="680"
    :cluster-max-width="1240"
    :aside-open="showStockNudge && Boolean(stockNudge)"
    @update:model-value="open = $event"
    @submit="create"
    @aside-dismiss="stockNudgeDismissed = true"
  >
    <template v-if="showStockNudge && stockNudge" #aside>
      <StockOrderNudge
        ref="stockNudgeRef"
        :available="stockNudge.available"
        :fresh="stockNudge.fresh"
        :aged="stockNudge.aged"
        :expired="stockNudge.expired"
        :conversion="stockNudge.conversion"
        :price-cents="stockNudge.priceCents"
        :zone-prices="stockNudge.zonePrices"
        :zone-ages="stockNudge.zoneAges"
        @buy="openStockBuy"
      />
    </template>
    <PresetOfferSummary
      v-if="matchedPreset"
      :heading="`Preset ${presetDisplayId(matchedPreset.id)}`"
      :vertical="matchedPreset.vertical"
      :country="matchedPreset.country"
      :traffic-source="matchedPreset.trafficSource"
      :payment-model="matchedPreset.paymentModel"
      :payout-cents="props.initialBasePriceCents ?? matchedPreset.basePriceCents"
      amount-label="Price"
      :conversion="matchedPreset.cr"
    />

    <template v-if="!fieldsLocked">
      <UiField
        label="Vertical *"
        :icon="vertical ? verticalIcon(vertical) : 'mdi-shape-outline'"
        :error="fieldErrors.vertical"
      >
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
        <UiField label="Country *" icon="mdi-earth" :error="fieldErrors.country">
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
        <UiField label="Traffic source *" icon="mdi-traffic-light" :error="fieldErrors.traffic">
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

    <UiField label="Quantity *" icon="mdi-counter" :error="fieldErrors.volume">
      <UiInput v-model="volume" type="number" placeholder="10" />
    </UiField>

    <UiField :label="priceLabel" icon="mdi-currency-usd" :error="fieldErrors.price">
      <UiInput v-model="price" type="number" :placeholder="pricePlaceholder" step="0.01" />
    </UiField>
    <p v-if="matchedPreset" class="order-form__floor-hint">
      Base price for this preset: {{ dollars(matchedPreset.basePriceCents / 100) }}
    </p>

    <UiField label="Date" :error="fieldErrors.serviceOn">
      <div class="order-form__date">
        <UiSegmented v-model="serviceQuick" :items="serviceQuickItems" class="order-form__segmented" />
        <UiDateField v-model="serviceOn" label="Date" class="order-form__date-field" :min="earliestServiceDay" />
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
    <OrderTeamField v-if="isPartnerOrder" v-model="teamId" />
    <UiField label="Timezone" icon="mdi-earth" :error="fieldErrors.timezone">
      <UiSelect
        v-model="timezone"
        :items="zoneItems"
        searchable
        search-placeholder="Search timezone"
        placeholder="Timezone"
      />
    </UiField>

    <OrderCheckoutSummary
      v-if="showCheckoutSummary"
      :price-per-lead-cents="priceCents"
      :quantity="volumeQty"
      :balance-cents="balanceCents"
      :team-budget="session.spend?.team ?? false"
      total-label="Total cost"
    />
  </FormDialog>
</template>

<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import CountryFlag from '@/components/CountryFlag.vue'
import FormDialog from '@/components/FormDialog.vue'
import OrderCheckoutSummary from '@/components/OrderCheckoutSummary.vue'
import PresetOfferSummary from '@/components/PresetOfferSummary.vue'
import StockOrderNudge from '@/components/StockOrderNudge.vue'
import UiDateField from '@/components/ui/UiDateField.vue'
import UiField from '@/components/ui/UiField.vue'
import UiInput from '@/components/ui/UiInput.vue'
import UiSegmented from '@/components/ui/UiSegmented.vue'
import UiSelect from '@/components/ui/UiSelect.vue'
import UiTimeField from '@/components/ui/UiTimeField.vue'
import { showToast } from '@/composables/useToast'
import { defaultOpensForServiceDate } from '@/timeDefaults'
import { browserTimeZone, timeZoneOptions } from '@/timezones'
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
import type { StockBuyZone } from '@/stockModalIntent'
import { orderDisplayId, presetDisplayId } from '@/orderDisplay'
import { useMarketStore, type PresetRow } from '@/stores/market'
import { useSessionStore } from '@/stores/session'
import OrderTeamField from '@/components/OrderTeamField.vue'

const props = defineProps<{
  initialPresetId?: string | null
  /** When opening from Buy Leads, use the row price (store presets can lag behind warehouse). */
  initialBasePriceCents?: number | null
  initialVolume?: number | null
  initialPriceDollars?: number | null
  initialServiceOn?: string | null
}>()

export type OrderFormDraft = {
  presetId: string
  qty: number
  price: number
  day: string
}

const open = defineModel<boolean>({ default: false })
const emit = defineEmits<{
  created: []
  stockPurchased: []
  draft: [value: OrderFormDraft | null]
  buyStock: [value: { presetId: string; qty: number; zone: StockBuyZone }]
}>()

const market = useMarketStore()
const session = useSessionStore()
const route = useRoute()
const router = useRouter()
const busy = ref(false)
const stockNudgeRef = ref<InstanceType<typeof StockOrderNudge> | null>(null)
const stockNudgeDismissed = ref(false)
const formError = ref('')
const fieldErrors = ref<Record<string, string>>({})
const vertical = ref<string | null>(null)
const country = ref<string | null>(null)
const trafficSource = ref<string | null>(null)
const volume = ref('10')
const price = ref('')
const timezone = ref(browserTimeZone())
const serviceOn = ref(utcDay(0))
const serviceQuick = ref('today')
const opensAt = ref(defaultOpensForServiceDate(utcDay(0), utcDay(0)))
const closesAt = ref('23:59')

const earliestServiceDay = computed(() => utcDay(0))
const zoneItems = computed(() => timeZoneOptions(timezone.value))

const serviceQuickItems = [
  { title: 'Today', value: 'today' },
  { title: 'Tomorrow', value: 'tomorrow' },
  { title: 'Custom', value: 'custom' },
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
  if (props.initialPresetId) {
    return market.presets.find((p) => p.id === props.initialPresetId)
  }
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
  const base = matchedPreset.value?.basePriceCents
  if (base == null) {
    return '0'
  }
  return presetPriceInput(matchedPreset.value!)
})

function presetPriceInput(preset: PresetRow, centsOverride?: number): string {
  const cents = centsOverride ?? preset.basePriceCents
  if (!Number.isFinite(cents) || cents <= 0) {
    return ''
  }
  const amount = cents / 100
  return Number.isInteger(amount) ? String(amount) : amount.toFixed(2)
}

function applyLinkedFields(): void {
  if (props.initialVolume != null && props.initialVolume > 0) {
    volume.value = String(Math.floor(props.initialVolume))
  }
  if (props.initialPriceDollars != null && props.initialPriceDollars > 0) {
    price.value = String(props.initialPriceDollars)
  }
  if (props.initialServiceOn) {
    const floor = utcDay(0)
    serviceOn.value = props.initialServiceOn < floor ? floor : props.initialServiceOn
    syncServiceQuickFromDate()
    opensAt.value = defaultOpensForServiceDate(props.initialServiceOn, utcDay(0))
  }
}

async function syncFormToInitialPreset(): Promise<void> {
  if (!open.value) {
    return
  }
  if (market.presets.length === 0) {
    await market.loadPresets()
  }
  formError.value = ''
  fieldErrors.value = {}
  const initial = props.initialPresetId
    ? market.presets.find((p) => p.id === props.initialPresetId)
    : undefined
  if (props.initialPresetId) {
    if (!initial) {
      return
    }
    vertical.value = initial.vertical
    country.value = initial.country
    trafficSource.value = initial.trafficSource
    volume.value = '10'
    price.value = presetPriceInput(initial, props.initialBasePriceCents ?? undefined)
    timezone.value = browserTimeZone()
    serviceOn.value = utcDay(0)
    serviceQuick.value = 'today'
    opensAt.value = defaultOpensForServiceDate(utcDay(0), utcDay(0))
    closesAt.value = '23:59'
    applyLinkedFields()
    return
  }
  volume.value = '10'
  price.value = ''
  timezone.value = browserTimeZone()
  serviceOn.value = utcDay(0)
  serviceQuick.value = 'today'
  opensAt.value = defaultOpensForServiceDate(utcDay(0), utcDay(0))
  closesAt.value = '23:59'
  await nextTick()
  const preset = matchedPreset.value
  if (preset) {
    price.value = presetPriceInput(preset)
  }
  applyLinkedFields()
}

const balanceCents = computed(() => session.spend?.cents ?? null)
const teamId = ref<string | undefined>(undefined)
const isPartnerOrder = computed(() => session.isPartner)

const volumeQty = computed(() => {
  const qty = Number(volume.value)
  if (!Number.isFinite(qty) || qty < 1) {
    return 0
  }
  return Math.floor(qty)
})

const stockNudge = computed(() => {
  const preset = matchedPreset.value
  if (!preset) {
    return null
  }
  const row = market.stock.find((offer) => offer.presetId === preset.id)
  if (!row || row.available < 1) {
    return null
  }
  return {
    presetId: preset.id,
    available: row.available,
    fresh: row.stockFreshness?.fresh ?? 0,
    aged: row.stockFreshness?.good ?? 0,
    expired: row.stockFreshness?.expired ?? 0,
    conversion: preset.cr,
    priceCents: props.initialBasePriceCents ?? preset.basePriceCents,
    zonePrices: {
      fresh: shelfZoneUnitCents('fresh', props.initialBasePriceCents ?? preset.basePriceCents),
      good: shelfZoneUnitCents('good', props.initialBasePriceCents ?? preset.basePriceCents),
      expired: shelfZoneUnitCents('expired', props.initialBasePriceCents ?? preset.basePriceCents),
    },
    zoneAges: {
      fresh: shelfZoneAge('fresh'),
      good: shelfZoneAge('good'),
      expired: shelfZoneAge('expired'),
    },
  }
})

function shelfZoneAge(zoneId: string): { startMinutes: number; endMinutes: number | null } | undefined {
  const zone = market.shelfAgePolicy?.zones.find((row) => row.id === zoneId)
  if (!zone) {
    return undefined
  }
  return { startMinutes: zone.startMinutes, endMinutes: zone.endMinutes }
}

function shelfZoneUnitCents(zoneId: string, baseCents: number): number {
  if (baseCents < 1) {
    return 0
  }
  const zone = market.shelfAgePolicy?.zones.find((row) => row.id === zoneId)
  if (!zone) {
    return baseCents
  }
  const discount = Math.max(0, Math.min(100, zone.discountPercent))
  return Math.round((baseCents * (100 - discount)) / 100)
}

const showStockNudge = computed(() => Boolean(stockNudge.value) && !stockNudgeDismissed.value)

function openStockBuy(zone: StockBuyZone): void {
  const preset = matchedPreset.value
  if (!preset) {
    return
  }
  const cap = stockNudgeRef.value?.volumeOf(zone) ?? stockNudge.value?.available ?? 0
  const qty = volumeQty.value > 0 ? Math.min(volumeQty.value, Math.max(cap, 1)) : 0
  emit('buyStock', { presetId: preset.id, qty, zone })
  if (route.path === '/stock') {
    return
  }
  const query: Record<string, string> = {
    action: 'buy',
    preset: preset.id,
    zone,
  }
  if (qty > 0) {
    query.qty = String(qty)
  }
  open.value = false
  void router.push({ path: '/stock', query })
}

const priceCents = computed(() => {
  const p = Number(price.value)
  if (!Number.isFinite(p) || p <= 0) {
    return 0
  }
  return Math.round(p * 100)
})

const showCheckoutSummary = computed(() => Boolean(matchedPreset.value) && volumeQty.value > 0 && priceCents.value > 0)

function positiveNumber(raw: string): number {
  const parsed = Number(raw)
  if (!Number.isFinite(parsed) || parsed <= 0) {
    return 0
  }
  return parsed
}

watch(
  [open, volume, price, serviceOn, () => matchedPreset.value?.id ?? ''],
  () => {
    if (!open.value) {
      emit('draft', null)
      return
    }
    const preset = matchedPreset.value
    if (!preset) {
      emit('draft', null)
      return
    }
    const qty = positiveNumber(volume.value)
    emit('draft', {
      presetId: preset.id,
      qty: qty > 0 ? Math.floor(qty) : 0,
      price: positiveNumber(price.value),
      day: serviceOn.value,
    })
  },
)

watch(open, (value) => {
  if (!value) {
    stockNudgeDismissed.value = false
    return
  }
  teamId.value = undefined
  stockNudgeDismissed.value = false
  if (market.stock.length === 0) {
    void market.loadWarehouse()
  }
  void syncFormToInitialPreset()
})

watch(
  () => [
    props.initialPresetId,
    props.initialBasePriceCents,
    props.initialVolume,
    props.initialPriceDollars,
    props.initialServiceOn,
  ] as const,
  () => {
    if (!open.value || !props.initialPresetId) {
      return
    }
    void syncFormToInitialPreset()
  },
)

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

watch(matchedPreset, (preset, previous) => {
  if (!open.value || !preset) {
    return
  }
  if (!previous || previous.id !== preset.id) {
    price.value = presetPriceInput(preset)
    return
  }
  const base = preset.basePriceCents / 100
  const current = Number(price.value)
  if (!Number.isFinite(current) || current < base) {
    price.value = presetPriceInput(preset)
  }
})

function utcDay(offset: number): string {
  const parts = new Intl.DateTimeFormat('en-CA', {
    timeZone: timezone.value || 'UTC',
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
  }).formatToParts(new Date())
  const year = Number(parts.find((part) => part.type === 'year')?.value)
  const month = Number(parts.find((part) => part.type === 'month')?.value)
  const day = Number(parts.find((part) => part.type === 'day')?.value)
  return new Date(Date.UTC(year, month - 1, day + offset)).toISOString().slice(0, 10)
}

watch(timezone, () => {
  if (serviceQuick.value === 'today') {
    serviceOn.value = utcDay(0)
  } else if (serviceQuick.value === 'tomorrow') {
    serviceOn.value = utcDay(1)
  }
})

function clearFieldError(key: string): void {
  if (!(key in fieldErrors.value)) {
    return
  }
  const next = { ...fieldErrors.value }
  delete next[key]
  fieldErrors.value = next
}

watch(vertical, () => clearFieldError('vertical'))
watch(country, () => clearFieldError('country'))
watch(trafficSource, () => clearFieldError('traffic'))
watch(volume, () => clearFieldError('volume'))
watch(price, () => clearFieldError('price'))
watch(serviceOn, () => clearFieldError('serviceOn'))

function validateForm(): PresetRow | null {
  formError.value = ''
  const errors: Record<string, string> = {}
  const preset = matchedPreset.value

  if (!preset && !fieldsLocked.value) {
    const combo =
      'Select vertical, country and traffic source that match a catalog preset'
    if (!vertical.value) {
      errors.vertical = 'Select vertical'
    }
    if (!country.value) {
      errors.country = 'Select country'
    }
    if (!trafficSource.value) {
      errors.traffic = 'Select traffic source'
    }
    if (vertical.value && country.value && trafficSource.value) {
      errors.traffic = combo
    }
  } else if (!preset) {
    formError.value =
      'Select vertical, country and traffic source that match a catalog preset'
    fieldErrors.value = {}
    return null
  }

  const qty = Number(volume.value)
  if (!Number.isFinite(qty) || qty < 1) {
    errors.volume = 'Enter a valid quantity'
  }

  if (preset) {
    const priceNum = Number(price.value)
    const baseCents = props.initialBasePriceCents ?? preset.basePriceCents
    const base = baseCents / 100
    if (!Number.isFinite(priceNum) || priceNum < base) {
      errors.price = `Price must be at least ${dollars(base)}`
    }
  }

  if (!timezone.value) {
    errors.timezone = 'Choose a timezone'
  }

  if (!serviceOn.value) {
    errors.serviceOn = 'Choose service date'
  } else if (serviceOn.value < utcDay(0)) {
    errors.serviceOn = 'Date cannot be in the past'
  }

  fieldErrors.value = errors
  if (Object.keys(errors).length > 0 || !preset) {
    return null
  }
  return preset
}

async function create(): Promise<void> {
  const preset = validateForm()
  if (!preset) {
    return
  }
  const qty = Number(volume.value)
  const priceNum = Number(price.value)
  busy.value = true
  formError.value = ''
  fieldErrors.value = {}
  try {
    const orderId = await market.createOrder({
      preset_id: preset.id,
      geo: preset.country,
      volume: qty,
      price: priceNum,
      payment_model: preset.paymentModel,
      kind: 'day',
      service_on: serviceOn.value,
      opens_at: opensAt.value,
      closes_at: closesAt.value,
      timezone: timezone.value,
      team_id: teamId.value ?? '',
    })
    const countryName =
      ISO2_COUNTRIES.find((c) => c.code === preset.country)?.name ?? preset.country
    showToast({
      title: 'Order created',
      lines: [
        orderDisplayId(orderId),
        `Preset ${presetDisplayId(preset.id)} · ${verticalLabel(preset.vertical)} · ${countryName} · ${trafficLabel(preset.trafficSource)}`,
        `${qty.toLocaleString('en-US')} leads at ${dollars(priceNum)} each`,
      ],
    })
    open.value = false
    emit('created')
  } catch (error) {
    formError.value = error instanceof Error ? error.message : 'Order could not be created'
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