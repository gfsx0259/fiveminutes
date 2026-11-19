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
  border-radius: 6px;
  display: inline-flex;
  font-size: 12px;
  font-weight: 700;
  height: 22px;
  padding: 0 8px;
}

.model-tag[data-model='cpl'] {
  background: #e0edff;
  color: #2549c7;
}

.model-tag[data-model='cpa'] {
  background: #f3e8ff;
  color: #7a2fc4;
}

.model-tag--empty {
  color: var(--lm-muted);
}
</style>
