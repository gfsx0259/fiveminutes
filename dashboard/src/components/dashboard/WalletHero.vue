<template>
  <section class="wallet-hero">
    <svg class="wallet-hero__wave" viewBox="0 0 400 160" preserveAspectRatio="none" aria-hidden="true">
      <path d="M0 150C120 160 170 40 400 10L400 160L0 160Z" fill="#6847e8" fill-opacity=".18" />
      <path d="M10 140C140 150 200 60 400 40" fill="none" stroke="#8f74ff" stroke-opacity=".35" />
    </svg>
    <div>
      <div class="wallet-hero__label"><v-icon icon="mdi-wallet-outline" size="16" />Wallet balance</div>
      <div class="wallet-hero__amount">{{ whole }}<small>.{{ cents }}</small></div>
      <div class="wallet-hero__runway" :class="{ 'wallet-hero__runway--low': low }">
        <v-icon :icon="low ? 'mdi-alert-outline' : 'mdi-timer-sand'" size="14" />
        {{ runway }}
      </div>
    </div>
    <div class="wallet-hero__stats">
      <div class="wallet-hero__stat">
        <span>Charged today</span>
        <strong>{{ dollars(chargedToday.amount) }}</strong>
        <em>{{ chargedToday.count }} {{ chargedToday.count === 1 ? 'lead' : 'leads' }} since 00:00</em>
      </div>
      <div class="wallet-hero__stat">
        <span>Reserved by open orders</span>
        <strong>{{ dollars(reserved) }}</strong>
        <em>{{ open.length }} open {{ open.length === 1 ? 'order' : 'orders' }}, {{ left }} leads left</em>
      </div>
      <div class="wallet-hero__stat">
        <span>Last deposit</span>
        <strong>{{ lastDeposit ? dollars(lastDeposit.amountCents / 100) : '—' }}</strong>
        <em>{{ lastDeposit ? momentOf(Date.parse(lastDeposit.createdAt), now) : 'No deposits yet' }}</em>
      </div>
    </div>
    <div class="wallet-hero__actions">
      <UiButton variant="ghost" class="wallet-hero__add" icon="mdi-plus" :loading="wallet.busy" @click="wallet.deposit()">
        Add funds
      </UiButton>
      <UiButton variant="ghost" class="wallet-hero__withdraw" :disabled="wallet.busy" @click="wallet.withdraw()">
        Withdraw
      </UiButton>
    </div>
  </section>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import UiButton from '@/components/ui/UiButton.vue'
import { dollars, wholeDollars } from '@/money'
import { leadsLeft, openOrdersOf, reservedOf } from '@/orderBook'
import { between, DAY, momentOf, startOfDay, totalsOf, type Purchase } from '@/purchases'
import type { OrderRow } from '@/stores/market'
import { useSessionStore } from '@/stores/session'
import { useWalletStore } from '@/stores/wallet'

const props = defineProps<{
  purchases: Purchase[]
  orders: OrderRow[]
  now: number
}>()

const session = useSessionStore()
const wallet = useWalletStore()

const balance = computed(() => (session.payload?.user?.balanceCents ?? 0) / 100)
const whole = computed(() => dollars(balance.value).split('.')[0])
const cents = computed(() => dollars(balance.value).split('.')[1] ?? '00')

const dailySpend = computed(() => {
  const today = startOfDay(props.now)
  return totalsOf(between(props.purchases, today - 6 * DAY, props.now + 1)).spent / 7
})

const days = computed(() => (dailySpend.value > 0 ? balance.value / dailySpend.value : Infinity))
const low = computed(() => days.value < 2)
const runway = computed(() => (Number.isFinite(days.value)
  ? `Enough for ≈ ${days.value.toFixed(1)} days at your average spend of the last 7 days (${wholeDollars(dailySpend.value)}/day)`
  : 'No spend in the last 7 days'))

const chargedToday = computed(() => {
  const today = startOfDay(props.now)
  const charges = wallet.transactions.filter((transaction) => transaction.kind === 'partner_lead_charge'
    && transaction.direction === 'debit'
    && Date.parse(transaction.createdAt) >= today)
  return { amount: charges.reduce((sum, transaction) => sum + transaction.amountCents / 100, 0), count: charges.length }
})

const open = computed(() => openOrdersOf(props.orders))
const reserved = computed(() => reservedOf(props.orders))
const left = computed(() => open.value.reduce((sum, order) => sum + leadsLeft(order), 0).toLocaleString('en-US'))

const lastDeposit = computed(() => wallet.transactions.find((transaction) => transaction.kind === 'adjustment' && transaction.direction === 'credit'))
</script>

<style scoped>
.wallet-hero {
  align-items: center;
  background:
    radial-gradient(ellipse at 92% 140%, #6847e8d0, transparent 55%),
    radial-gradient(ellipse at 35% -60%, #4b30c466, transparent 60%),
    linear-gradient(120deg, #191e3a, #221d4f);
  border-radius: var(--lm-radius);
  box-shadow: 0 10px 30px #1a154a29;
  color: #fff;
  display: grid;
  gap: 24px;
  grid-template-columns: minmax(230px, 1fr) minmax(0, 2fr) auto;
  overflow: hidden;
  padding: 20px 24px;
  position: relative;
}

.wallet-hero > :not(.wallet-hero__wave) {
  position: relative;
}

.wallet-hero__wave {
  height: 100%;
  opacity: 0.55;
  pointer-events: none;
  position: absolute;
  right: 0;
  top: 0;
  width: 55%;
}

.wallet-hero__label {
  align-items: center;
  color: #bfc5e5;
  display: flex;
  font-size: 12px;
  font-weight: 600;
  gap: 6px;
}

.wallet-hero__amount {
  font-size: 34px;
  font-variant-numeric: tabular-nums;
  font-weight: 700;
  letter-spacing: -1px;
  margin-top: 6px;
}

.wallet-hero__amount small {
  color: #cfd2f5;
  font-size: 20px;
  font-weight: 600;
}

.wallet-hero__runway {
  align-items: center;
  background: rgb(255 255 255 / 10%);
  border-radius: 999px;
  color: #dfe2ff;
  display: inline-flex;
  font-size: 12px;
  gap: 6px;
  margin-top: 8px;
  padding: 4px 10px;
}

.wallet-hero__runway--low {
  background: #ffb4c433;
  color: #ffd6df;
}

.wallet-hero__stats {
  display: grid;
  gap: 12px;
  grid-template-columns: repeat(3, minmax(0, 1fr));
}

.wallet-hero__stat {
  background: rgb(255 255 255 / 6%);
  border: 1px solid rgb(255 255 255 / 10%);
  border-radius: 12px;
  min-width: 0;
  padding: 12px 14px;
}

.wallet-hero__stat span {
  color: #bfc5e5;
  display: block;
  font-size: 12px;
  font-weight: 600;
}

.wallet-hero__stat strong {
  display: block;
  font-size: 18px;
  font-variant-numeric: tabular-nums;
  font-weight: 700;
  margin-top: 4px;
}

.wallet-hero__stat em {
  color: #a9afd6;
  display: block;
  font-size: 12px;
  font-style: normal;
  margin-top: 2px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.wallet-hero__actions {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.wallet-hero__actions :deep(.wallet-hero__add) {
  background: #fff !important;
  color: #3412b9 !important;
}

.wallet-hero__actions :deep(.wallet-hero__add:hover) {
  background: #f3f0ff !important;
}

.wallet-hero__actions :deep(.wallet-hero__withdraw) {
  background: rgb(255 255 255 / 10%) !important;
  border: 1px solid rgb(255 255 255 / 20%) !important;
  color: #fff !important;
}

@media (max-width: 1280px) {
  .wallet-hero {
    grid-template-columns: minmax(0, 1fr) auto;
  }

  .wallet-hero__stats {
    grid-column: 1 / -1;
    grid-row: 2;
  }

  .wallet-hero__actions {
    flex-direction: row;
  }
}

@media (max-width: 767px) {
  .wallet-hero {
    grid-template-columns: minmax(0, 1fr);
    padding: 18px;
  }

  .wallet-hero__stats {
    grid-row: auto;
    grid-template-columns: minmax(0, 1fr);
  }

  .wallet-hero__actions :deep(.v-btn) {
    flex: 1;
  }
}
</style>
