<template>
  <svg class="spark-line" :viewBox="`0 0 ${WIDTH} ${HEIGHT}`" preserveAspectRatio="none" aria-hidden="true">
    <defs>
      <linearGradient :id="gradient" x1="0" y1="0" x2="0" y2="1">
        <stop offset="0" :stop-color="color" stop-opacity=".22" />
        <stop offset="1" :stop-color="color" stop-opacity="0" />
      </linearGradient>
    </defs>
    <path :d="`${path}L${WIDTH} ${HEIGHT}L0 ${HEIGHT}Z`" :fill="`url(#${gradient})`" />
    <path
      :d="path"
      fill="none"
      :stroke="color"
      stroke-width="1.8"
      stroke-linejoin="round"
      stroke-linecap="round"
      vector-effect="non-scaling-stroke"
    />
  </svg>
</template>

<script setup lang="ts">
import { computed, useId } from 'vue'

const WIDTH = 96
const HEIGHT = 34

const props = withDefaults(defineProps<{
  values: number[]
  color?: string
}>(), {
  color: '#5b45e8',
})

const gradient = `spark-${useId()}`

const path = computed(() => {
  const values = props.values.length > 1 ? props.values : [props.values[0] ?? 0, props.values[0] ?? 0]
  const max = Math.max(...values)
  const min = Math.min(...values)
  return values
    .map((value, index) => {
      const x = index * (WIDTH / (values.length - 1))
      const y = HEIGHT - 3 - ((value - min) / ((max - min) || 1)) * (HEIGHT - 6)
      return `${index ? 'L' : 'M'}${x.toFixed(1)} ${y.toFixed(1)}`
    })
    .join('')
})
</script>

<style scoped>
.spark-line {
  flex-shrink: 0;
  height: 34px;
  width: 96px;
}
</style>
