<template>
  <FormDialog
    :model-value="open"
    title="Buy leads"
    hint="Purchase leads from the selected offer."
    submit-label="Confirm purchase"
    submit-icon="mdi-cart-outline"
    :error="formError"
    :loading="busy"
    :max-width="640"
    shareable
    @update:model-value="open = $event"
    @submit="buy"
    @share="copyShareLink"
  >
    <template v-if="target">
      <PresetOfferSummary
        :heading="`Offer ${offerId(target)}`"
        :badge="target.available > 0 ? 'Active' : undefined"
        :vertical="target.vertical"
        :country="target.country"
        :traffic-source="target.trafficSource"
        :payment-model="target.paymentModel"
        :payout-cents="target.basePriceCents"
        amount-label="Price"
        :conversion="target.conversion"
      />

      <UiField label="Quantity *" icon="mdi-counter" :error="fieldErrors.volume">
        <UiInput v-model="volume" type="number" :placeholder="String(target.available)" />
      </UiField>
      <p class="stock-buy__caps">
        Available: <strong>{{ target.available }}</strong>
      </p>

      <section v-if="volumeQty > 0" class="stock-buy__pricing" aria-label="Shelf pricing">
        <header class="stock-buy__pricing-head">
          <span>Shelf pricing</span>
          <v-progress-circular v-if="quoteLoading" indeterminate color="primary" size="18" width="2" />
        </header>

        <div v-if="quoteReady" class="stock-buy__pricing-scroll">
          <table class="stock-buy__pricing-table">
            <thead>
              <tr>
                <th>Zone</th>
                <th>Qty</th>
                <th class="stock-buy__col-hide-narrow">Catalog</th>
                <th>Off</th>
                <th>Unit</th>
                <th>Sum</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="line in quoteLines"
                :key="line.zoneId"
                :class="`stock-buy__zone stock-buy__zone--${line.zoneId}`"
              >
                <td>{{ line.label }}</td>
                <td>{{ line.count }}</td>
                <td class="stock-buy__muted stock-buy__col-hide-narrow">
                  {{ dollars(target.basePriceCents / 100) }}
                </td>
                <td>
                  <span v-if="line.discountPercent > 0" class="stock-buy__pct">−{{ line.discountPercent }}%</span>
                  <span v-else class="stock-buy__muted">—</span>
                </td>
                <td>{{ dollars(line.unitPriceCents / 100) }}</td>
                <td>{{ dollars(line.lineTotalCents / 100) }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <p v-else-if="quoteLoading" class="stock-buy__pricing-hint">Calculating shelf price…</p>
        <v-alert
          v-else-if="quoteError"
          type="warning"
          variant="tonal"
          density="compact"
          class="stock-buy__pricing-alert"
        >
          Shelf price unavailable ({{ quoteError }}). Showing catalog price until quote loads.
        </v-alert>
        <p v-else class="stock-buy__pricing-hint stock-buy__muted">
          Enter quantity to see age-zone discounts.
        </p>
      </section>

      <OrderCheckoutSummary
        :price-label="quoteReady ? 'Avg. per lead' : 'Price per lead'"
        :price-per-lead-cents="effectiveUnitCents"
        :quantity="volumeQty"
        :balance-cents="balanceCents"
        :total-cents-override="totalCents"
        :catalog-total-cents="quoteReady ? catalogTotalCents : null"
      />
    </template>
  </FormDialog>
  <v-snackbar v-model="notice" location="bottom center" color="success" :timeout="4000">
    {{ noticeText }}
  </v-snackbar>
</template>

<script setup lang="ts">
import { computed, ref, watch, watchEffect } from 'vue'
import type { StockQuoteLine } from '@/stores/market'
import FormDialog from '@/components/FormDialog.vue'
import OrderCheckoutSummary from '@/components/OrderCheckoutSummary.vue'
import PresetOfferSummary from '@/components/PresetOfferSummary.vue'
import UiField from '@/components/ui/UiField.vue'
import UiInput from '@/components/ui/UiInput.vue'
import { showToast } from '@/composables/useToast'
import { dollars } from '@/money'
import { stockModalShareUrl } from '@/stockModalIntent'
import { offerId, type StockBuyTarget } from '@/stockBuy'
import { useMarketStore } from '@/stores/market'
import { useSessionStore } from '@/stores/session'

const props = defineProps<{
  target: StockBuyTarget | null
  initialQty?: number | null
}>()

const open = defineModel<boolean>({ default: false })
const emit = defineEmits<{
  bought: []
}>()

const market = useMarketStore()
const session = useSessionStore()
const busy = ref(false)
const formError = ref('')
const fieldErrors = ref<Record<string, string>>({})
const volume = ref('')
const notice = ref(false)
const noticeText = ref('')
const quoteLines = ref<StockQuoteLine[]>([])
const quoteTotalCents = ref(0)
const quoteLoading = ref(false)
const quoteError = ref('')

const balanceCents = computed(() => session.payload?.user?.balanceCents ?? 0)

const volumeQty = computed(() => {
  const cap = props.target?.available ?? 0
  const qty = Number(volume.value)
  if (!Number.isFinite(qty) || qty < 1) {
    return 0
  }
  return Math.min(Math.floor(qty), cap)
})

const totalCents = computed(() => {
  if (!props.target || volumeQty.value < 1) {
    return 0
  }
  if (quoteTotalCents.value > 0) {
    return quoteTotalCents.value
  }
  return volumeQty.value * props.target.basePriceCents
})

const effectiveUnitCents = computed(() => {
  if (volumeQty.value < 1) {
    return 0
  }
  return Math.round(totalCents.value / volumeQty.value)
})

const quoteReady = computed(() => quoteTotalCents.value > 0 && quoteLines.value.length > 0)

const catalogTotalCents = computed(() => {
  if (!props.target || volumeQty.value < 1) {
    return 0
  }
  return volumeQty.value * props.target.basePriceCents
})

function syncToTarget(): void {
  if (!open.value || !props.target) {
    return
  }
  formError.value = ''
  fieldErrors.value = {}
  const fromLink = props.initialQty != null && props.initialQty > 0 ? Math.floor(props.initialQty) : null
  const defaultQty = fromLink ?? Math.min(props.target.available, 1000)
  volume.value = String(Math.max(1, defaultQty))
}

async function copyShareLink(): Promise<void> {
  const target = props.target
  if (!target) {
    return
  }
  const raw = Number(volume.value)
  const qty = Number.isFinite(raw) && raw > 0 ? Math.floor(raw) : undefined
  const sharer = session.payload?.user
  const assist = sharer
    ? { asUserId: sharer.id, forRole: 'partner' as const }
    : undefined
  const url = stockModalShareUrl(window.location.origin, window.location.pathname, {
    action: 'buy',
    presetId: target.presetId,
    qty,
  }, assist)
  try {
    await navigator.clipboard.writeText(url)
    showToast('Link copied')
  } catch {
    showToast('Copy unavailable')
  }
}

watch(open, (value) => {
  if (!value) {
    return
  }
  syncToTarget()
})

watch(() => [props.target?.presetId, props.initialQty] as const, () => {
  syncToTarget()
})

watch(volume, () => {
  if ('volume' in fieldErrors.value) {
    const next = { ...fieldErrors.value }
    delete next.volume
    fieldErrors.value = next
  }
})

watchEffect((onCleanup) => {
  const target = props.target
  const qty = volumeQty.value
  if (!open.value || !target || qty < 1) {
    quoteLines.value = []
    quoteTotalCents.value = 0
    quoteError.value = ''
    return
  }
  let cancelled = false
  onCleanup(() => {
    cancelled = true
  })
  quoteLoading.value = true
  quoteError.value = ''
  void market.fetchStockQuote(target.presetId, qty).then((quote) => {
    if (cancelled) {
      return
    }
    quoteLines.value = quote.lines
    quoteTotalCents.value = quote.totalCents
    quoteError.value = ''
  }).catch((error: unknown) => {
    if (!cancelled) {
      quoteLines.value = []
      quoteTotalCents.value = 0
      quoteError.value = error instanceof Error ? error.message : 'request failed'
    }
  }).finally(() => {
    if (!cancelled) {
      quoteLoading.value = false
    }
  })
})

function validateBuy(target: StockBuyTarget): number | null {
  formError.value = ''
  const errors: Record<string, string> = {}
  const raw = Number(volume.value)
  if (!Number.isFinite(raw) || raw < 1) {
    errors.volume = 'Enter a valid quantity'
  } else if (raw > target.available) {
    errors.volume = `Volume must be between 1 and ${target.available}`
  }
  fieldErrors.value = errors
  if (Object.keys(errors).length > 0) {
    return null
  }
  const qty = volumeQty.value
  if (totalCents.value > balanceCents.value) {
    formError.value = 'Insufficient wallet balance'
    return null
  }
  return qty
}

async function buy(): Promise<void> {
  const target = props.target
  if (!target) {
    return
  }
  const qty = validateBuy(target)
  if (qty == null) {
    return
  }

  busy.value = true
  formError.value = ''
  fieldErrors.value = {}
  try {
    const result = await market.createStockOrder({
      preset_id: target.presetId,
      geo: target.country,
      volume: qty,
      price: Math.round(target.basePriceCents / 100),
      payment_model: target.paymentModel,
      kind: 'stock',
    })
    const supplied = result.supplied ?? qty
    noticeText.value = `Stock order · ${supplied} leads · ${dollars(totalCents.value / 100)}`
    notice.value = true
    open.value = false
    emit('bought')
    await session.load()
  } catch (error) {
    formError.value = error instanceof Error ? error.message : 'The stock order was not created'
  } finally {
    busy.value = false
  }
}
</script>

<style scoped>
.stock-buy__caps {
  color: #8b90a8;
  font-size: 13px;
  margin: 4px 0 10px;
}

.stock-buy__caps strong {
  color: #303861;
}

.stock-buy__pricing {
  background: #f6f4fc;
  border-radius: 10px;
  margin-bottom: 10px;
  padding: 10px;
}

.stock-buy__pricing-scroll {
  -webkit-overflow-scrolling: touch;
  overflow-x: auto;
  width: 100%;
}

.stock-buy__pricing-head {
  align-items: center;
  color: #303861;
  display: flex;
  font-size: 14px;
  font-weight: 600;
  gap: 8px;
  justify-content: space-between;
  margin-bottom: 8px;
}

.stock-buy__pricing-table {
  border-collapse: collapse;
  font-size: 13px;
  width: 100%;
}

.stock-buy__pricing-table th,
.stock-buy__pricing-table td {
  padding: 6px 4px;
  text-align: left;
}

.stock-buy__pricing-table th {
  color: #8b90a8;
  font-weight: 500;
}

.stock-buy__muted {
  color: #8b90a8;
}

.stock-buy__pct {
  color: #2e9b6a;
  font-weight: 600;
}

.stock-buy__zone--expired td:first-child {
  color: #b33a3a;
}

.stock-buy__pricing-hint {
  font-size: 13px;
  margin: 0;
}

.stock-buy__pricing-alert {
  margin: 0;
}

@media (max-width: 767px) {
  .stock-buy__caps {
    text-align: center;
  }

  .stock-buy__col-hide-narrow {
    display: none;
  }

  .stock-buy__pricing {
    padding: 8px;
  }

  .stock-buy__pricing-head {
    font-size: 13px;
    margin-bottom: 6px;
  }

  .stock-buy__pricing-table {
    font-size: 12px;
    min-width: 280px;
  }

  .stock-buy__pricing-table th,
  .stock-buy__pricing-table td {
    padding: 5px 3px;
  }
}

</style>