<template>
  <v-dialog :model-value="modelValue" max-width="720" @update:model-value="emit('update:modelValue', $event)">
    <v-card>
      <v-card-title class="lead-detail__title">
        <span>Lead details</span>
        <UiButton
          v-if="lead"
          variant="outline"
          icon="mdi-content-copy"
          @click="copyJson"
        >
          Copy JSON
        </UiButton>
      </v-card-title>
      <v-card-text>
        <div v-if="loading" class="lead-detail__loading">
          <v-progress-circular indeterminate color="primary" size="36" width="3" />
        </div>
        <v-alert v-else-if="error" type="error" variant="tonal" density="compact">
          {{ error }}
        </v-alert>
        <v-table v-else-if="lead" density="compact" class="lead-detail__table">
          <tbody>
            <tr v-for="field in visibleFields" :key="field.key">
              <th>{{ field.label }}</th>
              <td>
                <StatusChip v-if="field.key === 'status'" :status="lead.status" :label="statusLabel(lead.status)" />
                <span v-else-if="field.key === 'country'" class="lead-detail__country">
                  <CountryFlag :code="lead.country" />
                  {{ lead.country || '—' }}
                </span>
                <span v-else>{{ format(field.key) }}</span>
              </td>
            </tr>
          </tbody>
        </v-table>
      </v-card-text>
      <v-card-text v-if="rejectOpen" class="pt-0">
        <v-textarea v-model="rejectReason" label="Reject reason" rows="3" />
        <v-alert v-if="convertError" type="error" variant="tonal" density="compact" class="mt-2">
          {{ convertError }}
        </v-alert>
      </v-card-text>
      <v-card-actions class="lead-detail__actions">
        <template v-if="canConvert && !rejectOpen">
          <UiButton variant="primary" :loading="convertBusy" @click="approveLead">Approve (deposit)</UiButton>
          <UiButton variant="outline" :loading="convertBusy" @click="rejectOpen = true">Reject</UiButton>
        </template>
        <template v-else-if="canConvert && rejectOpen">
          <UiButton variant="outline" @click="rejectOpen = false">Cancel</UiButton>
          <UiButton variant="primary" :loading="convertBusy" @click="rejectLead">Confirm reject</UiButton>
        </template>
        <UiButton variant="outline" @click="emit('update:modelValue', false)">Close</UiButton>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup lang="ts">
import CountryFlag from '@/components/CountryFlag.vue'
import StatusChip from '@/components/StatusChip.vue'
import UiButton from '@/components/ui/UiButton.vue'
import { apiPost } from '@/api'
import { dollars } from '@/money'
import type { CabinetLead } from '@/stores/leads'
import { useSessionStore } from '@/stores/session'
import { computed, ref, watch } from 'vue'

const session = useSessionStore()
const role = computed(() => session.payload?.user?.role)

const props = defineProps<{
  modelValue: boolean
  lead: CabinetLead | null
  loading?: boolean
  error?: string
  openReject?: boolean
}>()

const emit = defineEmits<{
  'update:modelValue': [value: boolean]
  converted: [lead: CabinetLead]
}>()

const convertBusy = ref(false)
const convertError = ref('')
const rejectOpen = ref(false)
const rejectReason = ref('')

watch(() => props.modelValue, (open) => {
  if (!open) {
    rejectOpen.value = false
    rejectReason.value = ''
    convertError.value = ''
    return
  }
  if (props.openReject) {
    rejectOpen.value = true
  }
})

watch(() => props.openReject, (openReject) => {
  if (props.modelValue && openReject) {
    rejectOpen.value = true
  }
})

const canConvert = computed(() => {
  if (!props.lead || props.lead.status !== 'sold') return false
  const r = role.value
  return r === 'partner' || r === 'buyer' || r === 'admin'
})

const fields = [
  { key: 'uuid', label: 'ID' },
  { key: 'status', label: 'Status' },
  { key: 'phone', label: 'Phone' },
  { key: 'email', label: 'Email' },
  { key: 'firstName', label: 'First name' },
  { key: 'lastName', label: 'Last name' },
  { key: 'country', label: 'Country' },
  { key: 'trafficSource', label: 'Traffic source' },
  { key: 'age', label: 'Age' },
  { key: 'gender', label: 'Gender' },
  { key: 'ip', label: 'IP' },
  { key: 'affSub', label: 'Aff sub' },
  { key: 'uniqId', label: 'Uniq id' },
  { key: 'priceCents', label: 'Price' },
  { key: 'buyerEmail', label: 'Buyer' },
  { key: 'sellerEmail', label: 'Partner' },
  { key: 'orderId', label: 'Order' },
  { key: 'createdAt', label: 'Created' },
  { key: 'updatedAt', label: 'Updated' },
  { key: 'soldAt', label: 'Sold' },
] as const

const visibleFields = computed(() => fields.filter((field) => {
  if (field.key === 'buyerEmail' && role.value !== 'admin') {
    return false
  }
  if (field.key === 'sellerEmail' && role.value !== 'admin') {
    return false
  }
  if (field.key === 'orderId' && role.value === 'buyer') {
    return false
  }
  return true
}))

const statusLabels: Record<string, string> = {
  new: 'New',
  sold: 'Sold',
  in_stock: 'In stock',
  delivered: 'Delivered',
  approved: 'Approved',
  rejected: 'Rejected',
}

function statusLabel(value: string): string {
  return statusLabels[value] ?? value.charAt(0).toUpperCase() + value.slice(1)
}

function genderLabel(value: string): string {
  if (value === 'm') return 'Male'
  if (value === 'f') return 'Female'
  if (value === 'all') return 'All'
  return value || '—'
}

function trafficLabel(value: string): string {
  if (!value) return '—'
  return value.charAt(0).toUpperCase() + value.slice(1)
}

function when(value: string | null | undefined): string {
  if (!value) return '—'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return value
  return date.toLocaleString(undefined, { hour12: false })
}

function format(key: typeof fields[number]['key']): string {
  const lead = props.lead
  if (!lead) return '—'
  if (key === 'priceCents') return dollars(lead.priceCents / 100)
  if (key === 'gender') return genderLabel(lead.gender)
  if (key === 'trafficSource') return trafficLabel(lead.trafficSource)
  if (key === 'createdAt' || key === 'updatedAt' || key === 'soldAt') {
    return when(lead[key])
  }
  const value = lead[key as keyof CabinetLead]
  if (value === null || value === undefined || value === '') return '—'
  return String(value)
}

async function copyJson(): Promise<void> {
  if (!props.lead) return
  await navigator.clipboard.writeText(JSON.stringify(props.lead, null, 2))
}

async function approveLead(): Promise<void> {
  if (!props.lead) return
  convertBusy.value = true
  convertError.value = ''
  try {
    const data = await apiPost<{ lead: CabinetLead }>(`/leads/${props.lead.uuid}/approve`, {})
    emit('converted', data.lead)
    rejectOpen.value = false
  } catch (error) {
    convertError.value = error instanceof Error ? error.message : 'Approve failed'
  } finally {
    convertBusy.value = false
  }
}

async function rejectLead(): Promise<void> {
  if (!props.lead || !rejectReason.value.trim()) {
    convertError.value = 'Reason is required'
    return
  }
  convertBusy.value = true
  convertError.value = ''
  try {
    const data = await apiPost<{ lead: CabinetLead }>(`/leads/${props.lead.uuid}/reject`, {
      reason: rejectReason.value.trim(),
    })
    emit('converted', data.lead)
    rejectOpen.value = false
  } catch (error) {
    convertError.value = error instanceof Error ? error.message : 'Reject failed'
  } finally {
    convertBusy.value = false
  }
}
</script>

<style scoped>
.lead-detail__title {
  align-items: center;
  display: flex;
  gap: 12px;
  justify-content: space-between;
}

.lead-detail__loading {
  display: flex;
  justify-content: center;
  padding: 32px;
}

.lead-detail__table :deep(th),
.lead-detail__table :deep(td) {
  padding-block: 10px;
  vertical-align: middle;
}

.lead-detail__table :deep(th) {
  color: var(--lm-muted);
  font-size: 13px;
  font-weight: 600;
  line-height: 1.35;
  padding-right: 16px;
  white-space: nowrap;
  width: 140px;
}

.lead-detail__table :deep(td) {
  font-size: 13px;
  line-height: 1.35;
  word-break: break-word;
}

.lead-detail__country {
  align-items: center;
  display: inline-flex;
  gap: 8px;
}
</style>