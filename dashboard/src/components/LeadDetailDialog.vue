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
        <div v-else-if="lead" class="lead-detail__sections">
          <section
            v-for="section in detailSections"
            :key="section.id"
            class="lead-detail__section"
          >
            <h3 class="lead-detail__section-title">{{ section.title }}</h3>
            <v-table density="compact" class="lead-detail__table">
              <tbody>
                <tr v-for="row in section.rows" :key="row.key">
                  <th>{{ row.label }}</th>
                  <td>
                    <StatusChip
                      v-if="row.kind === 'status'"
                      :status="lead.status"
                      :label="statusLabel(lead.status ?? '')"
                    />
                    <template v-else-if="row.kind === 'delivery'">
                      <StatusChip
                        v-if="lead.deliveryError"
                        status="failed"
                        tone="error"
                        label="Error"
                      />
                      <StatusChip
                        v-else-if="lead.isDelivered"
                        status="delivered"
                        tone="success"
                        label="Delivered"
                      />
                      <span v-else class="text-medium-emphasis">—</span>
                    </template>
                    <template v-else-if="row.kind === 'clientDeposit'">
                      <StatusChip
                        v-if="lead.isDelivered"
                        :status="lead.clientDeposit ? 'success' : 'default'"
                        :tone="lead.clientDeposit ? 'success' : 'default'"
                        :label="lead.clientDeposit ? 'Yes' : 'No'"
                      />
                      <span v-else class="text-medium-emphasis">—</span>
                    </template>
                    <span v-else-if="row.kind === 'country'" class="lead-detail__country">
                      <CountryFlag :code="rowValue(row)" />
                      <CopyableText
                        v-if="rowCopyValue(row)"
                        :text="rowCopyValue(row)"
                      >{{ rowValue(row) }}</CopyableText>
                      <span v-else class="text-medium-emphasis">—</span>
                    </span>
                    <span v-else-if="row.kind === 'vertical'" class="lead-detail__entity">
                      <v-icon
                        v-if="rowValue(row) !== '—'"
                        :icon="verticalIcon(rowValue(row))"
                        :color="verticalColor(rowValue(row))"
                        size="18"
                      />
                      <CopyableText
                        v-if="rowCopyValue(row)"
                        :text="rowCopyValue(row)"
                      >{{ verticalLabel(rowValue(row)) }}</CopyableText>
                      <span v-else class="text-medium-emphasis">—</span>
                    </span>
                    <template v-else-if="row.kind === 'model'">
                      <CopyableText
                        v-if="rowCopyValue(row)"
                        class="lead-detail__model"
                        :text="rowCopyValue(row)"
                      ><ModelTag :model="presetValues?.paymentModel ?? ''" /></CopyableText>
                      <span v-else class="text-medium-emphasis">—</span>
                    </template>
                    <CopyableText
                      v-else-if="rowCopyValue(row)"
                      :text="rowCopyValue(row)"
                      :truncate="row.field === 'email' || row.field === 'uuid'"
                      :max-width="row.field === 'uuid' ? '100%' : '280px'"
                    >{{ rowValue(row) }}</CopyableText>
                    <span v-else class="text-medium-emphasis">{{ rowValue(row) }}</span>
                  </td>
                </tr>
              </tbody>
            </v-table>
          </section>
        </div>
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
import CopyableText from '@/components/CopyableText.vue'
import CountryFlag from '@/components/CountryFlag.vue'
import ModelTag from '@/components/ModelTag.vue'
import StatusChip from '@/components/StatusChip.vue'
import UiButton from '@/components/ui/UiButton.vue'
import { apiPost } from '@/api'
import { modelLabel, verticalColor, verticalIcon, verticalLabel, trafficLabel } from '@/marketOptions'
import { dollars } from '@/money'
import { leadDisplayId, offerDisplayId, orderDisplayId, verticalFromAffSub } from '@/orderDisplay'
import { HIDDEN_CONTACT, type CabinetLead } from '@/stores/leads'
import { useMarketStore } from '@/stores/market'
import { useSessionStore } from '@/stores/session'
import { computed, ref, watch } from 'vue'

type RowKind = 'text' | 'status' | 'delivery' | 'clientDeposit' | 'country' | 'vertical' | 'model'

interface DetailRow {
  key: string
  label: string
  kind: RowKind
  source: 'preset' | 'lead' | 'ops'
  field?: string
}

interface DetailSection {
  id: string
  title: string
  rows: DetailRow[]
}

const session = useSessionStore()
const market = useMarketStore()
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
  if (!props.lead || props.lead.status !== 'sold' || !session.can('leads.manage')) return false
  const r = role.value
  return r === 'partner' || r === 'buyer' || r === 'admin'
})

const matchedOrder = computed(() => {
  const orderId = props.lead?.orderId
  if (!orderId) return null
  return market.orders.find((order) => order.id === orderId) ?? null
})

const matchedPreset = computed(() => {
  const order = matchedOrder.value
  if (!order) return null
  return market.presets.find((preset) => preset.id === order.presetId) ?? null
})

const presetValues = computed(() => {
  const lead = props.lead
  const order = matchedOrder.value
  const preset = matchedPreset.value
  if (!lead) {
    return null
  }
  const vertical = order?.vertical || preset?.vertical || verticalFromAffSub(lead.affSub)
  return {
    vertical: vertical || '—',
    country: order?.geo || preset?.country || '—',
    trafficSource: order?.trafficSource || preset?.trafficSource || '—',
    age: order?.age || preset?.age || '—',
    gender: order?.gender || preset?.gender || '—',
    paymentModel: order?.paymentModel || preset?.paymentModel || '—',
    priceCents: order?.priceCents ?? lead.priceCents,
    orderDisplay: order ? orderDisplayId(order.id) : '—',
  }
})

const presetRows: DetailRow[] = [
  { key: 'vertical', label: 'Vertical', kind: 'vertical', source: 'preset', field: 'vertical' },
  { key: 'country', label: 'Country', kind: 'country', source: 'preset', field: 'country' },
  { key: 'trafficSource', label: 'Traffic source', kind: 'text', source: 'preset', field: 'trafficSource' },
  { key: 'age', label: 'Age', kind: 'text', source: 'preset', field: 'age' },
  { key: 'gender', label: 'Gender', kind: 'text', source: 'preset', field: 'gender' },
  { key: 'paymentModel', label: 'Payment model', kind: 'model', source: 'preset', field: 'paymentModel' },
  { key: 'priceCents', label: 'Catalog price', kind: 'text', source: 'preset', field: 'priceCents' },
]

const leadRows: DetailRow[] = [
  { key: 'shortId', label: 'Short ID', kind: 'text', source: 'lead', field: 'leadId' },
  { key: 'longId', label: 'Long ID', kind: 'text', source: 'lead', field: 'uuid' },
  { key: 'firstName', label: 'First name', kind: 'text', source: 'lead', field: 'firstName' },
  { key: 'lastName', label: 'Last name', kind: 'text', source: 'lead', field: 'lastName' },
  { key: 'phone', label: 'Phone', kind: 'text', source: 'lead', field: 'phone' },
  { key: 'email', label: 'Email', kind: 'text', source: 'lead', field: 'email' },
  { key: 'country', label: 'Country', kind: 'country', source: 'lead', field: 'country' },
  { key: 'age', label: 'Age', kind: 'text', source: 'lead', field: 'age' },
  { key: 'gender', label: 'Gender', kind: 'text', source: 'lead', field: 'gender' },
  { key: 'ip', label: 'IP', kind: 'text', source: 'lead', field: 'ip' },
  { key: 'affSub', label: 'Aff sub', kind: 'text', source: 'lead', field: 'affSub' },
]

const presetSectionRows = computed((): DetailRow[] => {
  if (role.value === 'partner') {
    return [
      { key: 'offerId', label: 'Offer ID', kind: 'text', source: 'preset', field: 'offerId' },
      ...presetRows,
    ]
  }
  return presetRows
})

const detailSections = computed((): DetailSection[] => {
  const r = role.value
  const opsRows: DetailRow[] = []
  const presetTitle = r === 'partner' ? 'Offer' : 'Preset'

  if (r === 'partner') {
    opsRows.push(
      { key: 'delivery', label: 'Delivery', kind: 'delivery', source: 'ops' },
      { key: 'clientStatus', label: 'Client status', kind: 'text', source: 'ops', field: 'clientStatus' },
      { key: 'clientDeposit', label: 'Deposit', kind: 'clientDeposit', source: 'ops' },
    )
  } else {
    opsRows.push({ key: 'status', label: 'Status', kind: 'status', source: 'ops' })
    if (props.lead?.isDelivered || props.lead?.deliveryError) {
      opsRows.push(
        { key: 'delivery', label: 'Delivery', kind: 'delivery', source: 'ops' },
        { key: 'clientStatus', label: 'Client status', kind: 'text', source: 'ops', field: 'clientStatus' },
        { key: 'clientDeposit', label: 'Deposit', kind: 'clientDeposit', source: 'ops' },
      )
    }
  }

  if (r !== 'buyer') {
    opsRows.push({ key: 'orderId', label: 'Order', kind: 'text', source: 'ops', field: 'orderId' })
  }
  if (r === 'admin') {
    opsRows.push(
      { key: 'buyerEmail', label: 'Buyer', kind: 'text', source: 'ops', field: 'buyerEmail' },
      { key: 'sellerEmail', label: 'Partner', kind: 'text', source: 'ops', field: 'sellerEmail' },
    )
  }

  opsRows.push(
    { key: 'priceCents', label: 'Price', kind: 'text', source: 'ops', field: 'priceCents' },
    { key: 'createdAt', label: 'Created', kind: 'text', source: 'ops', field: 'createdAt' },
    { key: 'updatedAt', label: 'Updated', kind: 'text', source: 'ops', field: 'updatedAt' },
    { key: 'soldAt', label: 'Sold', kind: 'text', source: 'ops', field: 'soldAt' },
  )

  return [
    { id: 'preset', title: presetTitle, rows: presetSectionRows.value },
    { id: 'lead', title: 'Lead', rows: leadRows },
    { id: 'ops', title: 'Operational', rows: opsRows },
  ]
})

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

function when(value: string | null | undefined): string {
  if (!value) return '—'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return value
  return date.toLocaleString(undefined, { hour12: false })
}

function rowValue(row: DetailRow): string {
  const lead = props.lead
  if (!lead) return '—'

  if (row.source === 'preset') {
    const preset = presetValues.value
    if (!preset || !row.field) return '—'
    if (row.field === 'offerId') {
      const id = matchedPreset.value?.id
      return id ? offerDisplayId(id) : '—'
    }
    if (row.field === 'trafficSource') return trafficLabel(preset.trafficSource)
    if (row.field === 'gender') return genderLabel(preset.gender)
    if (row.field === 'paymentModel') return modelLabel(preset.paymentModel)
    if (row.field === 'priceCents') return dollars(preset.priceCents / 100)
    const value = preset[row.field as keyof typeof preset]
    return value === undefined || value === null || value === '' ? '—' : String(value)
  }

  if (row.source === 'lead' && row.field) {
    if (row.field === 'leadId') return leadDisplayId(lead.uuid)
    if (row.field === 'uuid') return lead.uuid
    if (row.field === 'gender') return genderLabel(lead.gender)
    if (row.field === 'trafficSource') return trafficLabel(lead.trafficSource)
    if ((row.field === 'phone' || row.field === 'email') && lead.contactsHidden) return HIDDEN_CONTACT
    const value = lead[row.field as keyof CabinetLead]
    if (value === null || value === undefined || value === '') return '—'
    return String(value)
  }

  if (row.source === 'ops' && row.field) {
    if (row.field === 'priceCents') return dollars(lead.priceCents / 100)
    if (row.field === 'clientStatus') {
      if (!lead.isDelivered) return '—'
      return lead.clientStatus?.trim() || '—'
    }
    if (row.field === 'orderId') {
      if (!lead.orderId) return '—'
      return orderDisplayId(lead.orderId)
    }
    if (row.field === 'createdAt' || row.field === 'updatedAt' || row.field === 'soldAt') {
      return when(lead[row.field])
    }
    const value = lead[row.field as keyof CabinetLead]
    if (value === null || value === undefined || value === '') return '—'
    return String(value)
  }

  return '—'
}

function rowCopyValue(row: DetailRow): string | null {
  if (row.kind === 'status' || row.kind === 'delivery' || row.kind === 'clientDeposit') {
    return null
  }
  const lead = props.lead
  const displayed = rowValue(row)
  if (displayed === '—' || displayed === '' || ((row.field === 'phone' || row.field === 'email') && lead?.contactsHidden)) {
    return null
  }

  if (row.field === 'uuid' && lead) {
    return lead.uuid
  }
  if (row.field === 'orderId' && lead?.orderId) {
    return lead.orderId
  }
  if (row.field === 'offerId' && matchedPreset.value) {
    return matchedPreset.value.id
  }
  if (row.field === 'priceCents') {
    if (row.source === 'preset') {
      const cents = presetValues.value?.priceCents
      return cents === undefined ? null : String(cents / 100)
    }
    if (lead) {
      return String(lead.priceCents / 100)
    }
  }

  return displayed
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

.lead-detail__sections {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.lead-detail__section {
  background: #f8f7fc;
  border: 1px solid #e6e7f2;
  border-radius: 12px;
  overflow: hidden;
}

.lead-detail__section-title {
  color: #51487b;
  font-size: 12px;
  font-weight: 700;
  letter-spacing: 0.04em;
  line-height: 1.2;
  margin: 0;
  padding: 10px 14px;
  text-transform: uppercase;
}

.lead-detail__table {
  background: #fff;
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
  padding-left: 14px;
  padding-right: 16px;
  white-space: nowrap;
  width: 140px;
}

.lead-detail__table :deep(td) {
  font-size: 13px;
  line-height: 1.35;
  padding-right: 14px;
  word-break: break-word;
}

.lead-detail__table :deep(td .copyable) {
  border-radius: 4px;
  padding: 1px 2px;
  transition: background 0.12s ease, color 0.12s ease;
}

.lead-detail__table :deep(td .copyable:hover) {
  background: #efecff;
}

/* Badge keeps its own colors: no wrapper padding or hover background. */
.lead-detail__table :deep(td .copyable.lead-detail__model),
.lead-detail__table :deep(td .copyable.lead-detail__model:hover) {
  background: transparent;
  padding: 0;
}

.lead-detail__country,
.lead-detail__entity {
  align-items: center;
  display: inline-flex;
  gap: 8px;
}
</style>