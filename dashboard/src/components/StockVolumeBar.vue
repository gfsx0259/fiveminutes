<template>
  <div class="stock-vol" :class="{ 'stock-vol--solo': !showBar }">
    <template v-if="showBar">
      <div class="stock-vol__labels" :style="{ '--fill': `${fill}%` }">
        <span class="stock-vol__count">{{ countLabel }}</span>
      </div>
      <div class="stock-vol__track">
        <div class="stock-vol__fill" :style="{ width: `${fill}%` }" />
      </div>
    </template>
    <span v-else class="stock-vol__count stock-vol__count--solo">{{ countLabel }}</span>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = withDefaults(
  defineProps<{
    value: number
    max: number
    /** When set, label can show share of parent (`value / parent` if partial). */
    parent?: number
    showBar?: boolean
  }>(),
  { showBar: true },
)

const countLabel = computed(() => {
  if (props.parent !== undefined && props.parent > 0 && props.value < props.parent) {
    return `${props.value} / ${props.parent}`
  }
  return String(props.value)
})

const fill = computed(() => {
  if (props.max < 1 || props.value < 1) {
    return 0
  }
  const ratio = props.value / props.max
  return Math.round(Math.max(6, Math.min(100, ratio * 100)))
})
</script>

<style scoped>
.stock-vol {
  display: flex;
  flex-direction: column;
  gap: 4px;
  width: 100%;
}

.stock-vol--solo {
  gap: 0;
  width: auto;
}

.stock-vol__labels {
  height: 14px;
  overflow: visible;
  position: relative;
  width: 100%;
}

.stock-vol__count {
  color: var(--lm-primary-deep);
  font-size: 12px;
  font-variant-numeric: tabular-nums;
  font-weight: 700;
  left: calc(var(--fill) / 2);
  line-height: 1;
  position: absolute;
  transform: translateX(-50%);
  white-space: nowrap;
}

.stock-vol__count--solo {
  position: static;
  transform: none;
}

.stock-vol__track {
  background: rgb(91 69 232 / 10%);
  border-radius: 999px;
  height: 8px;
  overflow: hidden;
  width: 100%;
}

.stock-vol__fill {
  background: linear-gradient(90deg, rgb(91 69 232 / 55%), rgb(91 69 232 / 85%));
  border-radius: 999px;
  height: 100%;
  min-width: 4px;
  transition: width 0.25s ease;
}
</style>