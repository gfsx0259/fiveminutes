<template>
  <FormDialog
    :model-value="open"
    title="Buy from stock"
    :hint="hint"
    submit-label="Buy"
    :error="formError"
    :loading="busy"
    :max-width="600"
    @update:model-value="open = $event"
    @submit="buy"
  >
    <p v-if="target" class="stock-buy__summary">
      <CountryFlag :code="target.country" :size="16" />
      {{ target.summary }}
      <span class="stock-buy__available">{{ available }} in stock</span>
    </p>
    <div class="ui-form__row stock-buy__controls">
      <UiField label="Volume">
        <UiInput v-model="volume" type="number" :placeholder="String(available || 1)" />
      </UiField>
      <UiField label="Model">
        <UiSelect v-model="model" :items="paymentModels" placeholder="CPL" />
      </UiField>
    </div>

    <div v-if="checkoutPlan.length" class="stock-buy__breakdown">
      <div class="stock-buy__breakdown-head">
        <span>Preset</span>
        <span class="stock-buy__num">Qty</span>
        <span class="stock-buy__num">{{ modelLabel }}</span>
        <span class="stock-buy__num">Subtotal</span>
      </div>
      <div v-for="(line, index) in checkoutPlan" :key="lineKey(line, index)" class="stock-buy__breakdown-row">
        <span class="stock-buy__line-label">{{ stockLeafDescription(line.leaf) }}</span>
        <span class="stock-buy__num stock-buy__qty">{{ line.volume }}</span>
        <span class="stock-buy__num">{{ dollars(unitPrice(line) / 100) }}</span>
        <span class="stock-buy__num stock-buy__subtotal">{{ dollars(lineSubtotal(line) / 100) }}</span>
      </div>
      <div class="stock-buy__breakdown-total">
        <span>{{ checkoutPlan.length > 1 ? 'Total' : 'Charge' }}</span>
        <span />
        <span />
        <span class="stock-buy__num stock-buy__grand">{{ dollars(totalCents / 100) }}</span>
      </div>
    </div>
    <p v-else-if="target" class="stock-buy__note">Set volume to see the purchase breakdown.</p>

    <p v-if="isBundle && checkoutPlan.length > 1" class="stock-buy__note">
      Leads are taken preset by preset, newest first, until the volume is reached.
    </p>
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
import UiSelect from '@/components/ui/UiSelect.vue'
import { paymentModels } from '@/marketOptions'
import { dollars } from '@/money'
import {
  catalogPriceDollars,
  fillPlan,
  lineSubtotalCents,
  planSubtotalCents,
  priceCents,
  stockAvailable,
  stockLeafDescription,
  type StockBuyTarget,
  type StockPlanLine,
} from '@/stockBuy'
import { useMarketStore } from '@/stores/market'

const props = defineProps<{
  target: StockBuyTarget | null
}>()

const open = defineModel<boolean>({ default: false })
const emit = defineEmits<{
  bought: []
}>()

const market = useMarketStore()
const busy = ref(false)
const formError = ref('')
const volume = ref('')
const model = ref<'cpl' | 'cpa'>('cpl')
const notice = ref(false)
const noticeText = ref('')

const available = computed(() => (props.target ? stockAvailable(props.target.leaves) : 0))
const isBundle = computed(() => (props.target?.leaves.length ?? 0) > 1)

const volumeQty = computed(() => {
  const qty = Number(volume.value)
  if (!Number.isFinite(qty) || qty < 1) {
    return 0
  }
  return Math.min(Math.floor(qty), available.value)
})

const checkoutPlan = computed(() => {
  if (!props.target || volumeQty.value < 1) {
    return []
  }
  return fillPlan(props.target.leaves, volumeQty.value)
})

const modelLabel = computed(() => model.value.toUpperCase())

const totalCents = computed(() => planSubtotalCents(checkoutPlan.value, model.value))

const hint = computed(
  () => 'Warehouse leads are billed at each preset catalog price for the payment model you choose.',
)

watch(open, (value) => {
  if (!value || !props.target) {
    return
  }
  formError.value = ''
  volume.value = String(available.value)
  model.value = 'cpl'
})

function lineKey(line: StockPlanLine, index: number): string {
  return `${line.leaf.presetId}|${line.leaf.gender}|${index}`
}

function unitPrice(line: StockPlanLine): number {
  return priceCents(line.leaf, model.value)
}

function lineSubtotal(line: StockPlanLine): number {
  return lineSubtotalCents(line, model.value)
}

async function buy(): Promise<void> {
  const target = props.target
  if (!target) {
    return
  }
  const qty = volumeQty.value
  if (qty < 1) {
    formError.value = `Volume must be between 1 and ${available.value}`
    return
  }

  const plan = fillPlan(target.leaves, qty)
  if (plan.length === 0) {
    formError.value = 'Nothing is available to buy'
    return
  }

  busy.value = true
  formError.value = ''
  let orders = 0
  let supplied = 0
  try {
    for (const line of plan) {
      const cents = priceCents(line.leaf, model.value)
      const result = await market.createStockOrder({
        preset_id: line.leaf.presetId,
        geo: line.leaf.country,
        volume: line.volume,
        price: catalogPriceDollars(cents),
        payment_model: model.value,
        kind: 'stock',
        stock_gender: line.leaf.gender,
      })
      orders += 1
      supplied += result.supplied ?? line.volume
    }
    noticeText.value = orders > 1
      ? `${orders} stock orders · ${supplied} leads · ${dollars(totalCents.value / 100)}`
      : `Stock order · ${supplied} leads · ${dollars(totalCents.value / 100)}`
    notice.value = true
    open.value = false
    emit('bought')
  } catch (error) {
    formError.value = error instanceof Error ? error.message : 'The stock order was not created'
    if (orders > 0) {
      emit('bought')
    }
  } finally {
    busy.value = false
  }
}
</script>

<style scoped>
.stock-buy__summary {
  align-items: center;
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin: 0 0 12px;
}

.stock-buy__available {
  color: var(--lm-muted);
  font-size: 13px;
}

.stock-buy__controls {
  margin-bottom: 16px;
}

.stock-buy__breakdown {
  background: #faf9ff;
  border: 1px solid #e8e4f8;
  border-radius: 10px;
  font-size: 13px;
  margin-bottom: 12px;
  overflow: hidden;
}

.stock-buy__breakdown-head,
.stock-buy__breakdown-row,
.stock-buy__breakdown-total {
  align-items: baseline;
  column-gap: 12px;
  display: grid;
  grid-template-columns: minmax(0, 1fr) 48px 72px 88px;
  padding: 10px 14px;
}

.stock-buy__breakdown-head {
  background: rgb(91 69 232 / 6%);
  color: var(--lm-muted);
  font-size: 11px;
  font-weight: 600;
  letter-spacing: 0.03em;
  text-transform: uppercase;
}

.stock-buy__breakdown-row {
  border-top: 1px solid #edeaf8;
}

.stock-buy__breakdown-total {
  background: rgb(91 69 232 / 8%);
  border-top: 1px solid #dcd6f5;
  font-weight: 700;
  grid-template-columns: minmax(0, 1fr) 48px 72px 88px;
}

.stock-buy__num {
  font-variant-numeric: tabular-nums;
  text-align: right;
}

.stock-buy__line-label {
  line-height: 1.35;
  min-width: 0;
}

.stock-buy__grand {
  font-size: 15px;
}

.stock-buy__note {
  color: var(--lm-muted);
  font-size: 13px;
  margin: 0 0 8px;
}
</style>