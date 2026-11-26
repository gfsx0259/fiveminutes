<template>
  <span
    v-if="status"
    class="status-chip"
    :data-tone="chipTone"
    :title="title || undefined"
  >
    {{ label }}
  </span>
  <span v-else class="text-medium-emphasis">—</span>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{
  status?: string | null
  title?: string | null
  label?: string
  tone?: string
}>()

const toneMap: Record<string, string> = {
  lead: 'lavender',
  reject: 'error',
  sold: 'success',
  completed: 'success',
  approved: 'success',
  in_stock: 'lavender',
  in_delivery: 'orange',
  reserved: 'warning',
  scheduled: 'warning',
  published: 'primary',
  failed: 'error',
  skipped: 'default',
  in_progress: 'primary',
  running: 'primary',
  paused: 'warning',
  finished: 'success',
  done: 'success',
  cancelled: 'default',
  rejected: 'error',
  declined: 'error',
  delivered: 'success',
  pushed: 'primary',
  pushing: 'warning',
  pending: 'warning',
  sending: 'warning',
  undelivered: 'error',
  abandoned: 'error',
  success: 'success',
  fail: 'error',
  direct: 'success',
  unavailable: 'error',
  not_interested: 'error',
  invalid_data: 'error',
  invalid: 'error',
  fraud: 'error',
  ftd: 'success',
  'lead-pushed': 'primary',
  'lead-declined': 'error',
  new: 'warning',
  open: 'primary',
  taken: 'success',
  closed: 'default',
  'Call Back': 'primary',
  'No Answer': 'warning',
  'No Interest': 'error',
  'Wrong Info': 'error',
}

const labelMap: Record<string, string> = {
  in_progress: 'In progress',
  running: 'Running',
  paused: 'Paused',
  finished: 'Finished',
  done: 'Done',
  cancelled: 'Cancelled',
  completed: 'Completed',
  sold: 'Sold',
  rejected: 'Rejected',
  in_stock: 'In stock',
  in_delivery: 'In delivery',
  reserved: 'Reserved',
  scheduled: 'Scheduled',
  published: 'Published',
  failed: 'Failed',
  skipped: 'Skipped',
  declined: 'Declined',
  not_interested: 'Not interested',
  invalid_data: 'Invalid data',
  invalid: 'Invalid',
  fraud: 'Fraud',
}

const chipTone = computed(() => props.tone || toneMap[props.status || ''] || 'default')

const label = computed(() => {
  if (props.label) return props.label
  const status = props.status || ''
  return labelMap[status] || status
})
</script>

<style scoped>
/* Palette and sizing follow the buyer prototype (demands status badges). */
.status-chip {
  align-items: center;
  border-radius: 12px;
  box-sizing: border-box;
  display: inline-flex;
  font-size: 11px;
  font-weight: 500;
  line-height: 1.25;
  padding: 4px 8px;
  white-space: nowrap;
}

.status-chip[data-tone='primary'] {
  background: #e8efff;
  color: #345caa;
}

.status-chip[data-tone='lavender'] {
  background: var(--lavender-soft);
  color: var(--lavender-strong);
}

.status-chip[data-tone='orange'] {
  background: #ffeadb;
  color: #b4501a;
}

.status-chip[data-tone='success'] {
  background: var(--success-soft);
  color: var(--success-strong);
}

.status-chip[data-tone='error'] {
  background: #ffe7eb;
  color: #b82732;
}

.status-chip[data-tone='warning'] {
  background: var(--yellow-soft);
  color: var(--yellow-strong);
}

.status-chip[data-tone='default'] {
  background: #eeedf8;
  color: #65648a;
}
</style>
