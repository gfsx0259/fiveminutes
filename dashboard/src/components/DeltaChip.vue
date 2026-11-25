<template>
  <span v-if="text" class="delta-chip" :data-tone="tone">
    <v-icon :icon="rising ? 'mdi-arrow-up' : 'mdi-arrow-down'" size="14" />{{ text }}
  </span>
  <span v-else class="delta-chip" data-tone="flat">{{ empty }}</span>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = withDefaults(defineProps<{
  current: number
  previous: number
  points?: boolean
  good?: 'up' | 'down' | 'none'
}>(), {
  points: false,
  good: 'up',
})

const change = computed(() => {
  if (props.points) return (props.current - props.previous) * 100
  if (!props.previous) return null
  return (props.current - props.previous) / props.previous * 100
})

const rising = computed(() => (change.value ?? 0) >= 0)

const text = computed(() => {
  if (change.value === null) return ''
  return `${Math.abs(change.value).toFixed(1)}${props.points ? ' pp' : '%'}`
})

const empty = computed(() => (props.current ? 'new' : '—'))

const tone = computed(() => {
  const value = change.value ?? 0
  if (props.good === 'none' || Math.abs(value) < (props.points ? 0.05 : 0.5)) return 'flat'
  return (value > 0) === (props.good === 'up') ? 'up' : 'down'
})
</script>

<style scoped>
.delta-chip {
  align-items: center;
  border-radius: 12px;
  display: inline-flex;
  font-size: 11px;
  font-weight: 500;
  gap: 1px;
  line-height: 1.25;
  padding: 3px 8px 3px 4px;
  white-space: nowrap;
}

.delta-chip[data-tone='up'] {
  background: #e5f6e9;
  color: #23743e;
}

.delta-chip[data-tone='down'] {
  background: #ffe7eb;
  color: #b82732;
}

.delta-chip[data-tone='flat'] {
  background: #eeedf8;
  color: #65648a;
}
</style>
