<template>
  <span v-if="model" class="model-tag" :data-model="model">{{ modelLabel(model) }}</span>
  <span v-else class="model-tag model-tag--empty">—</span>
</template>

<script setup lang="ts">
import { modelLabel } from '@/marketOptions'

defineProps<{
  model: string
}>()
</script>

<style scoped>
.model-tag {
  align-items: center;
  border-radius: 12px;
  display: inline-flex;
  font-size: 11px;
  font-weight: 500;
  line-height: 1.25;
  padding: 4px 8px;
}

/* Same palette as StatusChip (buyer prototype badges). */
.model-tag[data-model='cpl'] {
  background: #e8efff;
  color: #345caa;
}

.model-tag[data-model='cpa'] {
  background: #eeebff;
  color: #3412b9;
}

.model-tag--empty {
  color: var(--lm-muted);
}
</style>
