<template>
  <FormDialog
    :model-value="modelValue"
    :title="`Budget · ${team.name}`"
    hint="The team and everything below it cannot open orders beyond this amount. Bought leads and money reserved by open orders count."
    :error="error"
    submit-label="Save budget"
    submit-icon="mdi-check"
    :loading="busy"
    :max-width="520"
    @update:model-value="emit('update:modelValue', $event)"
    @submit="submit"
  >
    <div class="team-budget__figures">
      <div><small>{{ periodNote }}</small><strong>{{ money(budget?.spentCents ?? 0) }}</strong></div>
      <div><small>Reserved by open orders</small><strong>{{ money(budget?.reservedCents ?? 0) }}</strong></div>
      <div><small>Left</small><strong :class="{ 'team-budget__over': (budget?.leftCents ?? 0) < 0 }">{{ budget?.budgetCents == null ? 'No limit' : money(budget.leftCents) }}</strong></div>
    </div>

    <label class="team-budget__unlimited">
      <input v-model="unlimited" type="checkbox">
      <span>No limit, the team spends from the account balance</span>
    </label>

    <template v-if="!unlimited">
      <UiField label="Budget, $">
        <UiInput v-model="amount" type="number" min="0" step="1" placeholder="5000" />
      </UiField>
      <UiField label="Resets">
        <UiSegmented v-model="period" :items="periodItems" />
      </UiField>
    </template>

    <div v-if="history.length" class="team-budget__history">
      <small>History</small>
      <div v-for="change in history" :key="change.changedAt" class="team-budget__change">
        <span>{{ change.budgetCents === null ? 'No limit' : `${money(change.budgetCents)} ${periodWord(change.period)}` }}</span>
        <span>{{ change.changedBy }}</span>
        <span>{{ when(change.changedAt) }}</span>
      </div>
    </div>
  </FormDialog>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { api, apiPut } from '@/api'
import FormDialog from '@/components/FormDialog.vue'
import UiField from '@/components/ui/UiField.vue'
import UiInput from '@/components/ui/UiInput.vue'
import UiSegmented from '@/components/ui/UiSegmented.vue'
import { dollars } from '@/money'
import type { BudgetPeriodId, TeamUnit } from '@/team'

interface BudgetChange {
  budgetCents: number | null
  period: BudgetPeriodId
  changedBy: string
  changedAt: string
}

const periodItems = [
  { title: 'Never', value: 'total' },
  { title: 'Monthly', value: 'month' },
  { title: 'Weekly', value: 'week' },
]

const props = defineProps<{
  modelValue: boolean
  team: TeamUnit
}>()

const emit = defineEmits<{
  'update:modelValue': [value: boolean]
  saved: []
}>()

const unlimited = ref(true)
const period = ref<BudgetPeriodId>('total')
const budget = computed(() => props.team.budget)
const periodNote = computed(() => {
  if (!budget.value || budget.value.budgetCents === null || budget.value.period === 'total') return 'Spent'
  const resets = budget.value.resetsAt ? new Date(budget.value.resetsAt).toLocaleDateString('en-GB', { day: 'numeric', month: 'short' }) : ''
  return `Spent this ${budget.value.period}${resets ? `, resets ${resets}` : ''}`
})

function periodWord(value: BudgetPeriodId): string {
  return value === 'month' ? 'monthly' : value === 'week' ? 'weekly' : 'total'
}
const amount = ref('')
const history = ref<BudgetChange[]>([])
const busy = ref(false)
const error = ref('')

function money(cents: number | null): string {
  return dollars((cents ?? 0) / 100)
}

function when(iso: string): string {
  return new Date(iso).toLocaleString('en-GB', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' })
}

async function loadHistory(): Promise<void> {
  const response = await api(`/team/units/${props.team.id}/budget`)
  history.value = response.ok ? (await response.json() as { changes: BudgetChange[] }).changes : []
}

async function submit(): Promise<void> {
  const dollarsAmount = Number(amount.value)
  if (!unlimited.value && (!Number.isFinite(dollarsAmount) || dollarsAmount < 0)) {
    error.value = 'Enter the budget in dollars'
    return
  }
  busy.value = true
  error.value = ''
  try {
    await apiPut(`/team/units/${props.team.id}/budget`, {
      budget_cents: unlimited.value ? null : Math.round(dollarsAmount * 100),
      period: unlimited.value ? 'total' : period.value,
    })
    emit('saved')
    emit('update:modelValue', false)
  } catch (err) {
    error.value = err instanceof Error ? err.message : 'Failed to save the budget'
  } finally {
    busy.value = false
  }
}

watch(
  () => props.modelValue,
  (open) => {
    if (!open) {
      return
    }
    error.value = ''
    const current = props.team.budget
    unlimited.value = current?.budgetCents == null
    amount.value = current?.budgetCents == null ? '' : String(current.budgetCents / 100)
    period.value = current?.period ?? 'total'
    void loadHistory()
  },
  { immediate: true },
)
</script>

<style scoped>
.team-budget__figures {
  display: grid;
  gap: 10px;
  grid-template-columns: repeat(3, minmax(0, 1fr));
}

.team-budget__figures div {
  background: var(--lm-surface-soft, var(--lm-lavender));
  border-radius: 10px;
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding: 10px 12px;
}

.team-budget__figures small,
.team-budget__history small {
  color: var(--lm-muted);
  font-size: 12px;
}

.team-budget__over {
  color: #c2405f;
}

.team-budget__unlimited {
  align-items: center;
  cursor: pointer;
  display: flex;
  font-size: 13px;
  gap: 8px;
}

.team-budget__unlimited input {
  accent-color: var(--lm-primary);
  height: 16px;
  width: 16px;
}

.team-budget__history {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.team-budget__change {
  display: grid;
  font-size: 13px;
  gap: 8px;
  grid-template-columns: 1fr 1.6fr 1fr;
}
</style>
