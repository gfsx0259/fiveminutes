<template>
  <div class="wallet-activity">
    <p v-if="entries.length === 0" class="wallet-activity__empty">No wallet activity yet</p>
    <div v-for="entry in entries" :key="entry.key" class="wallet-activity__row">
      <span class="wallet-activity__icon" :data-flow="entry.incoming ? 'in' : 'out'">
        <v-icon :icon="entry.icon" size="17" />
      </span>
      <div class="wallet-activity__text">
        <div class="wallet-activity__title">{{ entry.title }}</div>
        <div class="wallet-activity__meta">{{ entry.meta }}</div>
      </div>
      <span class="wallet-activity__amount" :class="{ 'wallet-activity__amount--in': entry.incoming }">
        {{ entry.incoming ? '+' : '−' }}{{ dollars(entry.amount) }}
      </span>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { dollars } from '@/money'
import { HOUR, momentOf, timeOf, type Purchase } from '@/purchases'
import type { WalletTransaction } from '@/stores/wallet'

const props = defineProps<{
  transactions: WalletTransaction[]
  purchases: Purchase[]
  now: number
}>()

interface Entry {
  key: string
  at: number
  incoming: boolean
  amount: number
  icon: string
  title: string
  meta: string
}

const countries = computed(() => new Map(props.purchases.map((purchase) => [purchase.lead.uuid, purchase.lead.country])))

const entries = computed(() => {
  const charges = new Map<number, WalletTransaction[]>()
  const rows: Entry[] = []
  for (const transaction of props.transactions) {
    const at = Date.parse(transaction.createdAt)
    if (transaction.kind === 'partner_lead_charge') {
      const hour = Math.floor(at / HOUR) * HOUR
      charges.set(hour, [...(charges.get(hour) ?? []), transaction])
      continue
    }
    const incoming = transaction.direction === 'credit'
    rows.push({
      key: transaction.id,
      at,
      incoming,
      amount: transaction.amountCents / 100,
      icon: incoming ? 'mdi-arrow-bottom-left' : 'mdi-arrow-top-right',
      title: incoming ? 'Deposit' : 'Withdrawal',
      meta: momentOf(at, props.now),
    })
  }
  for (const [hour, list] of charges) {
    const geos = [...new Set(list.map((transaction) => countries.value.get(transaction.leadUuid ?? '') ?? '').filter(Boolean))]
    rows.push({
      key: `charges-${hour}`,
      at: hour + HOUR - 1,
      incoming: false,
      amount: list.reduce((sum, transaction) => sum + transaction.amountCents / 100, 0),
      icon: 'mdi-cart-arrow-down',
      title: `Lead charges · ${timeOf(hour)}–${timeOf(hour + HOUR)}`,
      meta: [`${list.length} ${list.length === 1 ? 'lead' : 'leads'}`, geos.slice(0, 3).join(', ') + (geos.length > 3 ? ` +${geos.length - 3}` : '')]
        .filter(Boolean)
        .join(' · '),
    })
  }
  return rows.sort((left, right) => right.at - left.at).slice(0, 6)
})
</script>

<style scoped>
.wallet-activity {
  display: flex;
  flex-direction: column;
}

.wallet-activity__row {
  align-items: center;
  border-bottom: 1px solid var(--lm-lavender);
  display: grid;
  gap: 10px;
  grid-template-columns: 32px minmax(0, 1fr) auto;
  padding: 10px 2px;
}

.wallet-activity__row:last-child {
  border-bottom: 0;
}

.wallet-activity__icon {
  align-items: center;
  background: var(--lm-lavender);
  border-radius: 10px;
  color: var(--lm-primary-deep);
  display: flex;
  height: 32px;
  justify-content: center;
  width: 32px;
}

.wallet-activity__icon[data-flow='in'] {
  background: #e5f6ea;
  color: #1d7a3a;
}

.wallet-activity__text {
  min-width: 0;
}

.wallet-activity__title {
  font-size: 13px;
  font-weight: 600;
}

.wallet-activity__meta {
  color: var(--lm-muted);
  font-size: 12px;
  margin-top: 2px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.wallet-activity__amount {
  font-size: 13px;
  font-variant-numeric: tabular-nums;
  font-weight: 700;
}

.wallet-activity__amount--in {
  color: #1d7a3a;
}

.wallet-activity__empty {
  color: var(--lm-muted);
  font-size: 13px;
  margin: 8px 2px;
}
</style>
