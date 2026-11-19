<template>
  <div class="donut-chart">
    <svg viewBox="0 0 170 170" width="170" height="170" aria-hidden="true">
      <circle :r="RADIUS" cx="85" cy="85" fill="none" stroke="#f1eeff" stroke-width="18" />
      <circle
        v-for="arc in arcs"
        :key="arc.color"
        :r="RADIUS"
        cx="85"
        cy="85"
        fill="none"
        :stroke="arc.color"
        stroke-width="18"
        :stroke-dasharray="`${arc.length} ${CIRCUMFERENCE - arc.length}`"
        :stroke-dashoffset="-arc.offset"
        transform="rotate(-90 85 85)"
      />
    </svg>
    <div class="donut-chart__center">
      <strong>{{ value }}</strong>
      <span>{{ caption }}</span>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const RADIUS = 70
const CIRCUMFERENCE = 2 * Math.PI * RADIUS

const props = defineProps<{
  segments: { value: number, color: string }[]
  value: string
  caption: string
}>()

const arcs = computed(() => {
  const total = props.segments.reduce((sum, segment) => sum + segment.value, 0) || 1
  let offset = 0
  return props.segments.flatMap((segment) => {
    const length = CIRCUMFERENCE * segment.value / total
    const start = offset
    offset += length
    return length > 0 ? [{ color: segment.color, offset: start, length: Math.max(0, length - 3) }] : []
  })
})
</script>

<style scoped>
.donut-chart {
  flex-shrink: 0;
  height: 170px;
  position: relative;
  width: 170px;
}

.donut-chart__center {
  align-items: center;
  display: flex;
  flex-direction: column;
  inset: 0;
  justify-content: center;
  position: absolute;
}

.donut-chart__center strong {
  font-size: 28px;
  font-variant-numeric: tabular-nums;
  font-weight: 700;
  letter-spacing: -0.6px;
}

.donut-chart__center span {
  color: var(--lm-muted);
  font-size: 12px;
  font-weight: 600;
}
</style>
