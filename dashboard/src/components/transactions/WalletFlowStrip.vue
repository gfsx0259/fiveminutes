<template>
  <section class="flow-strip">
    <template v-for="line in flow.lines" :key="line.type.value">
      <button
        type="button"
        class="flow-strip__cell flow-strip__cell--type"
        :class="{ 'flow-strip__cell--on': active === line.type.value, 'flow-strip__cell--empty': line.count === 0 }"
        :style="{ '--tone': line.type.color }"
        :aria-pressed="active === line.type.value"
        :title="line.type.note"
        @click="emit('pick', active === line.type.value ? '' : line.type.value)"
      >
        <span class="flow-strip__label">
          <span class="flow-strip__dot"><v-icon :icon="line.type.icon" size="13" /></span>
          {{ line.type.plural }}
        </span>
        <strong>{{ line.amount > 0 ? (line.type.incoming ? '+' : '−') : '' }}{{ dollars(line.amount) }}</strong>
        <small>{{ countLabel(line.count) }}</small>
      </button>
    </template>

    <div class="flow-strip__cell flow-strip__cell--closing">
      <span class="flow-strip__label">
        <v-icon icon="mdi-wallet-outline" size="15" />
        Balance
      </span>
      <strong>{{ dollars(flow.closing) }}</strong>
      <small>{{ closingNote }}</small>
    </div>
  </section>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { dollars } from '@/money'
import type { Period } from '@/purchases'
import type { EntryType, WalletFlow } from '@/walletEntries'

const props = defineProps<{
  flow: WalletFlow
  period: Period
  now: number
  active: EntryType | ''
}>()

const emit = defineEmits<{
  pick: [type: EntryType | '']
}>()

const closingNote = computed(() => (props.period.to > props.now ? 'Now' : `At the end of ${dayOf(props.period.to - 1)}`))

function dayOf(at: number): string {
  return new Date(at).toLocaleDateString('en-GB', { day: 'numeric', month: 'short' })
}

function countLabel(count: number): string {
  if (count === 0) return 'None in this period'
  return `${count.toLocaleString('en-US')} ${count === 1 ? 'transaction' : 'transactions'}`
}
</script>

<style scoped>
.flow-strip {
  align-items: stretch;
  background: #fff;
  border: 1px solid var(--lm-border);
  border-radius: var(--lm-radius);
  box-shadow: var(--lm-shadow);
  display: grid;
  gap: 12px;
  grid-template-columns: repeat(3, minmax(0, 1fr)) minmax(0, 1.15fr);
  margin-bottom: 16px;
  padding: 14px;
}

.flow-strip__cell {
  background: #faf9ff;
  border: 1px solid var(--lm-lavender);
  border-radius: 12px;
  color: inherit;
  display: flex;
  flex-direction: column;
  font: inherit;
  gap: 4px;
  min-width: 0;
  padding: 12px 14px;
  text-align: left;
}

.flow-strip__cell strong {
  font-size: 20px;
  font-variant-numeric: tabular-nums;
  font-weight: 700;
  letter-spacing: -0.3px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.flow-strip__cell small {
  color: var(--lm-muted);
  font-size: 12px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.flow-strip__label {
  align-items: center;
  color: var(--lm-muted);
  display: flex;
  font-size: 12px;
  font-weight: 600;
  gap: 6px;
  min-width: 0;
  white-space: nowrap;
}

.flow-strip__cell--type {
  border-top: 3px solid var(--tone);
  cursor: pointer;
  transition: background-color 0.15s ease, border-color 0.15s ease, box-shadow 0.15s ease;
}

.flow-strip__cell--type:hover {
  background: #fff;
  box-shadow: var(--lm-shadow);
}

.flow-strip__cell--type strong {
  color: var(--tone);
}

.flow-strip__cell--on {
  background: #fff;
  border-color: var(--tone);
  box-shadow: 0 0 0 3px color-mix(in srgb, var(--tone) 16%, transparent);
}

.flow-strip__cell--empty strong {
  color: #9aa0c3;
}

.flow-strip__dot {
  align-items: center;
  background: color-mix(in srgb, var(--tone) 14%, #fff);
  border-radius: 6px;
  color: var(--tone);
  display: inline-flex;
  height: 20px;
  justify-content: center;
  width: 20px;
}

.flow-strip__cell--closing {
  background: linear-gradient(135deg, #2a1f8f, #4b30c4);
  border-color: transparent;
  color: #fff;
}

.flow-strip__cell--closing .flow-strip__label,
.flow-strip__cell--closing small {
  color: #cfd2f5;
}

@media (max-width: 900px) {
  .flow-strip {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 600px) {
  .flow-strip {
    gap: 8px;
    padding: 10px;
  }

  .flow-strip__cell strong {
    font-size: 17px;
  }
}
</style>
