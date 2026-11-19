<template>
  <FormDialog
    :model-value="open"
    title="Buy leads"
    hint="Purchase leads from the selected offer."
    submit-label="Confirm purchase"
    submit-icon="mdi-cart-outline"
    :error="formError"
    :loading="busy"
    :max-width="680"
    @update:model-value="open = $event"
    @submit="buy"
  >
    <template v-if="target">
      <PresetOfferSummary
        :heading="`Offer ${offerId(target)}`"
        :badge="target.available > 0 ? 'Active' : undefined"
        :vertical="target.vertical"
        :country="target.country"
        :traffic-source="target.trafficSource"
        :payment-model="target.paymentModel"
        :payout-cents="target.stockPriceCents"
        amount-label="Price"
        :conversion="target.conversion"
      />

      <UiField label="Quantity *" icon="mdi-counter">
        <UiInput v-model="volume" type="number" :placeholder="String(target.available)" />
      </UiField>
      <p class="stock-buy__caps">
        Available: <strong>{{ target.available }}</strong>
        <span
          v-if="target.dailyCapVolume > 0"
          title="Leads supplied from your live today orders"
        >
          · Live today: <strong>{{ target.dailyCapSupplied }}/{{ target.dailyCapVolume }}</strong>
        </span>
      </p>

      <OrderCheckoutSummary
        :price-per-lead-cents="target.stockPriceCents"
        :quantity="volumeQty"
        :balance-cents="balanceCents"
      />
    </template>
  </FormDialog>
  <v-snackbar v-model="notice" location="bottom center" color="success" :timeout="4000">
    {{ noticeText }}
  </v-snackbar>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import FormDialog from '@/components/FormDialog.vue'
import OrderCheckoutSummary from '@/components/OrderCheckoutSummary.vue'
import PresetOfferSummary from '@/components/PresetOfferSummary.vue'
import UiField from '@/components/ui/UiField.vue'
import UiInput from '@/components/ui/UiInput.vue'
import { dollars } from '@/money'
import { offerId, type StockBuyTarget } from '@/stockBuy'
import { useMarketStore } from '@/stores/market'
import { useSessionStore } from '@/stores/session'

const props = defineProps<{
  target: StockBuyTarget | null
}>()

const open = defineModel<boolean>({ default: false })
const emit = defineEmits<{
  bought: []
}>()

const market = useMarketStore()
const session = useSessionStore()
const busy = ref(false)
const formError = ref('')
const volume = ref('')
const notice = ref(false)
const noticeText = ref('')

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
  return volumeQty.value * props.target.stockPriceCents
})

watch(open, (value) => {
  if (!value || !props.target) {
    return
  }
  formError.value = ''
  const defaultQty = Math.min(props.target.available, 1000)
  volume.value = String(defaultQty > 0 ? defaultQty : 1)
})

async function buy(): Promise<void> {
  const target = props.target
  if (!target) {
    return
  }
  const qty = volumeQty.value
  if (qty < 1) {
    formError.value = `Volume must be between 1 and ${target.available}`
    return
  }
  if (totalCents.value > balanceCents.value) {
    formError.value = 'Insufficient wallet balance'
    return
  }

  busy.value = true
  formError.value = ''
  try {
    const result = await market.createStockOrder({
      preset_id: target.presetId,
      geo: target.country,
      volume: qty,
      price: Math.round(target.stockPriceCents / 100),
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
  margin: 6px 0 14px;
}

.stock-buy__caps strong {
  color: #303861;
}

</style>