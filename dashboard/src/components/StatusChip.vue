<template>
  <span
    v-if="status"
    class="status-chip"
    :data-tone="tone"
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

const tone = computed(() => toneMap[props.status || ''] || 'default')

const label = computed(() => {
  if (props.label) return props.label
  const status = props.status || ''
  return labelMap[status] || status
})
</script>

<style scoped>
.status-chip {
  align-items: center;
  border-radius: 999px;
  box-sizing: border-box;
  display: inline-flex;
  font-size: 12px;
  font-weight: 600;
  line-height: 1.2;
  min-height: 26px;
  padding: 6px 14px;
  white-space: nowrap;
}

.status-chip[data-tone='primary'] {
  background: #e0edff;
  color: #2549c7;
}

.status-chip[data-tone='lavender'] {
  background: var(--lm-lavender);
  color: #51487b;
}

.status-chip[data-tone='orange'] {
  background: #ffedd9;
  color: #c45c12;
}

.status-chip[data-tone='success'] {
  background: #e5f6ea;
  color: #1d7a3a;
}

.status-chip[data-tone='error'] {
  background: #ffe2e8;
  color: #c42f50;
}

.status-chip[data-tone='warning'] {
  background: #fff1d6;
  color: #9a6408;
}

.status-chip[data-tone='default'] {
  background: var(--lm-lavender);
  color: #5e568a;
}
</style>
