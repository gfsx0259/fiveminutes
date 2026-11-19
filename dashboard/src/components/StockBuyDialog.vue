<template>
  <FormDialog
    :model-value="open"
    title="Buy leads"
    hint="Fixed catalog price for this warehouse offer."
    submit-label="Confirm purchase"
    :error="formError"
    :loading="busy"
    :max-width="560"
    @update:model-value="open = $event"
    @submit="buy"
  >
    <template v-if="target">
      <div class="stock-buy__card">
        <div class="stock-buy__card-row">
          <CountryFlag :code="target.country" :size="18" />
          <span class="stock-buy__card-title">{{ verticalLabel(target.vertical) }} · {{ target.country }}</span>
          <span class="stock-buy__pill">{{ modelLabel(target.paymentModel) }}</span>
        </div>
        <p class="stock-buy__meta">
          {{ trafficLabel(target.trafficSource) }}
          <span v-if="target.conversion > 0"> · CR {{ target.conversion }}%</span>
        </p>
        <p class="stock-buy__payout">Payout per lead: <strong>{{ dollars(target.stockPriceCents / 100) }}</strong></p>
      </div>

      <UiField label="Quantity">
        <UiInput v-model="volume" type="number" :placeholder="String(target.available)" />
      </UiField>
      <p class="stock-buy__hint">Available: {{ target.available }}</p>

      <div class="stock-buy__totals">
        <div class="stock-buy__line">
          <span>Total cost</span>
          <strong>{{ dollars(totalCents / 100) }}</strong>
        </div>
        <div class="stock-buy__line">
          <span>Wallet balance</span>
          <span>{{ dollars(balanceCents / 100) }}</span>
        </div>
        <v-alert v-if="insufficient > 0" type="warning" variant="tonal" density="compact" class="mt-2">
          Insufficient balance by {{ dollars(insufficient / 100) }}
        </v-alert>
      </div>
    </template>
  </FormDialog>
  <v-snackbar v-model="notice" location="bottom center" color="success" :timeout="4000">
    {{ noticeText }}
  </v-snackbar>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import CountryFlag from '@/components/CountryFlag.vue'
import FormDialog from '@/components/FormDialog.vue'
import UiField from '@/components/ui/UiField.vue'
import UiInput from '@/components/ui/UiInput.vue'
import { modelLabel, trafficLabel, verticalLabel } from '@/marketOptions'
import { dollars } from '@/money'
import type { StockBuyTarget } from '@/stockBuy'
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

const insufficient = computed(() => Math.max(0, totalCents.value - balanceCents.value))

watch(open, (value) => {
  if (!value || !props.target) {
    return
  }
  formError.value = ''
  volume.value = String(Math.min(props.target.available, 1000))
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
.stock-buy__card {
  background: #faf9ff;
  border: 1px solid #e8e4f8;
  border-radius: 12px;
  margin-bottom: 16px;
  padding: 14px 16px;
}

.stock-buy__card-row {
  align-items: center;
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.stock-buy__card-title {
  font-weight: 600;
}

.stock-buy__pill {
  background: rgb(91 69 232 / 12%);
  border-radius: 999px;
  color: #5b45e8;
  font-size: 12px;
  font-weight: 600;
  padding: 2px 10px;
}

.stock-buy__meta,
.stock-buy__payout,
.stock-buy__hint {
  color: var(--lm-muted);
  font-size: 13px;
  margin: 8px 0 0;
}

.stock-buy__payout strong {
  color: var(--lm-text);
}

.stock-buy__totals {
  background: #f6f4fc;
  border-radius: 10px;
  margin-top: 16px;
  padding: 12px 14px;
}

.stock-buy__line {
  display: flex;
  font-size: 14px;
  justify-content: space-between;
  padding: 4px 0;
}
</style>