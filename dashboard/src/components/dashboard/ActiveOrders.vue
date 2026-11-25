<template>
  <div class="active-orders">
    <p v-if="rows.length === 0" class="active-orders__empty">No active orders. Create one to receive fresh leads.</p>
    <div v-for="row in rows" :key="row.order.id" class="active-orders__row">
      <div>
        <div class="active-orders__title">
          <CountryPick :code="row.order.geo" />
          <span class="active-orders__dot">·</span>
          <TrafficSourceLabel :source="row.order.trafficSource" />
          <ModelTag :model="row.order.paymentModel" />
        </div>
        <div class="active-orders__meta">
          #{{ row.order.id.slice(0, 8) }} · {{ row.day }} {{ row.order.opensAt }}–{{ row.order.closesAt }} {{ row.order.timezone || 'UTC' }} · {{ dollars(row.order.partnerPriceCents / 100) }} per lead
        </div>
      </div>
      <div>
        <div class="active-orders__progress" :data-state="row.state">
          <i :style="{ width: `${row.share}%` }" />
        </div>
        <div class="active-orders__numbers">
          <span><b>{{ row.order.supplied }}</b> / {{ row.order.volume }} delivered</span>
          <span><b>{{ wholeDollars(row.order.supplied * row.order.partnerPriceCents / 100) }}</b> spent</span>
        </div>
      </div>
      <div class="active-orders__side">
        <StatusChip :status="row.state" :label="stateLabels[row.state]" :tone="stateTones[row.state]" />
        <button
          type="button"
          class="active-orders__action"
          :title="row.order.status === 'paused' ? 'Resume' : 'Pause'"
          :disabled="acting === row.order.id"
          @click="act(row.order)"
        >
          <v-icon :icon="row.order.status === 'paused' ? 'mdi-play' : 'mdi-pause'" size="18" />
        </button>
      </div>
    </div>
    <router-link v-if="hidden > 0" class="active-orders__more" to="/orders">
      +{{ hidden }} more {{ hidden === 1 ? 'order' : 'orders' }}
      <v-icon icon="mdi-arrow-right" size="14" />
    </router-link>
    <p v-if="error" class="active-orders__error">{{ error }}</p>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import CountryPick from '@/components/CountryPick.vue'
import ModelTag from '@/components/ModelTag.vue'
import StatusChip from '@/components/StatusChip.vue'
import TrafficSourceLabel from '@/components/TrafficSourceLabel.vue'
import { dollars, wholeDollars } from '@/money'
import { isScheduled, localDay, openOrdersOf } from '@/orderBook'
import { DAY } from '@/purchases'
import { useMarketStore, type OrderRow } from '@/stores/market'

type OrderState = 'in_progress' | 'paused' | 'scheduled'

const props = withDefaults(defineProps<{
  orders: OrderRow[]
  now: number
  limit?: number
}>(), {
  limit: 5,
})

const market = useMarketStore()
const acting = ref('')
const error = ref('')

const stateLabels: Record<OrderState, string> = { in_progress: 'Running', paused: 'Paused', scheduled: 'Scheduled' }
const stateTones: Record<OrderState, string> = { in_progress: 'primary', paused: 'warning', scheduled: 'lavender' }

const stateRank: Record<OrderState, number> = { in_progress: 0, paused: 1, scheduled: 2 }

const ordered = computed(() => openOrdersOf(props.orders)
  .map((order) => {
    const state: OrderState = order.status === 'paused' ? 'paused' : isScheduled(order, props.now) ? 'scheduled' : 'in_progress'
    const share = order.volume ? order.supplied / order.volume * 100 : 0
    return { order, state, share: order.supplied ? Math.max(share, 2) : 0, day: dayOf(order) }
  })
  .sort((left, right) => stateRank[left.state] - stateRank[right.state]
    || (left.state === 'scheduled'
      ? `${left.order.serviceOn}${left.order.opensAt}`.localeCompare(`${right.order.serviceOn}${right.order.opensAt}`)
      : `${left.order.serviceOn}${left.order.closesAt}`.localeCompare(`${right.order.serviceOn}${right.order.closesAt}`))))

const rows = computed(() => ordered.value.slice(0, props.limit))
const hidden = computed(() => ordered.value.length - rows.value.length)

function dayOf(order: OrderRow): string {
  if (order.serviceOn === localDay(props.now)) return 'Today'
  if (order.serviceOn === localDay(props.now + DAY)) return 'Tomorrow'
  return order.serviceOn ?? ''
}

async function act(order: OrderRow): Promise<void> {
  acting.value = order.id
  error.value = ''
  try {
    await market.actOrder(order.id, order.status === 'paused' ? 'resume' : 'pause')
  } catch (failure) {
    error.value = failure instanceof Error ? failure.message : 'Статус не сменился'
  } finally {
    acting.value = ''
  }
}
</script>

<style scoped>
.active-orders {
  display: flex;
  flex-direction: column;
}

.active-orders__row {
  align-items: center;
  border-bottom: 1px solid var(--lm-lavender);
  display: grid;
  font-size: 13px;
  gap: 16px;
  grid-template-columns: minmax(0, 1.25fr) minmax(0, 1.5fr) 132px;
  padding: 10px 2px;
}

.active-orders__row:last-of-type {
  border-bottom: 0;
}

.active-orders__title {
  align-items: center;
  display: flex;
  flex-wrap: nowrap;
  overflow: hidden;
  white-space: nowrap;
  font-weight: 600;
  gap: 8px;
}

.active-orders__dot {
  color: var(--lm-muted);
}

.active-orders__meta {
  color: var(--lm-muted);
  font-size: 12px;
  margin-top: 4px;
}

.active-orders__progress {
  background: var(--lm-lavender);
  border-radius: 999px;
  height: 6px;
  overflow: hidden;
}

.active-orders__progress i {
  background: linear-gradient(90deg, #6650ee, #5339e5);
  border-radius: 999px;
  display: block;
  height: 100%;
}

.active-orders__progress[data-state='paused'] i {
  background: #d9b25a;
}

.active-orders__progress[data-state='scheduled'] i {
  background: #c7bffc;
}

.active-orders__numbers {
  color: var(--lm-muted);
  display: flex;
  font-size: 12px;
  justify-content: space-between;
  margin-top: 6px;
}

.active-orders__numbers b {
  color: var(--lm-foreground);
  font-variant-numeric: tabular-nums;
}

.active-orders__side {
  align-items: center;
  display: flex;
  gap: 6px;
  justify-content: space-between;
}

.active-orders__action {
  align-items: center;
  background: none;
  border: 0;
  border-radius: 8px;
  color: var(--lm-primary-deep);
  cursor: pointer;
  display: inline-flex;
  height: 30px;
  justify-content: center;
  width: 30px;
}

.active-orders__action:hover {
  background: #efecff;
}

.active-orders__action:disabled {
  cursor: default;
  opacity: 0.5;
}

.active-orders__empty,
.active-orders__more {
  align-items: center;
  align-self: flex-start;
  color: var(--lm-primary-deep);
  display: inline-flex;
  font-size: 13px;
  font-weight: 600;
  gap: 2px;
  margin: 10px 0 0 12px;
  text-decoration: none;
}

.active-orders__more:hover {
  text-decoration: underline;
}

.active-orders__error {
  color: var(--lm-muted);
  font-size: 13px;
  margin: 8px 2px;
}

.active-orders__error {
  color: #c42f50;
}

@media (max-width: 767px) {
  .active-orders__row {
    gap: 10px;
    grid-template-columns: minmax(0, 1fr);
  }
}
</style>
