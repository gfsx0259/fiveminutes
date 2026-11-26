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

/* CPL — lavender, CPA — yellow (pairs live in styles/tokens.css). */
.model-tag[data-model='cpl'] {
  background: var(--lavender-soft);
  color: var(--lavender-strong);
}

.model-tag[data-model='cpa'] {
  background: var(--yellow-soft);
  color: var(--yellow-strong);
}

.model-tag--empty {
  color: var(--lm-muted);
}
</style>
