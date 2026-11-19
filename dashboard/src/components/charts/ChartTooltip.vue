<template>
  <Teleport to="body">
    <div v-show="visible" ref="box" class="chart-tooltip" :style="{ transform: `translate(${left}px, ${top}px)` }">
      <b>{{ title }}</b>
      <div v-for="row in rows" :key="row.label" class="chart-tooltip__row">
        <span><i v-if="row.color" :style="{ background: row.color }" />{{ row.label }}</span>
        <span>{{ row.value }}</span>
      </div>
    </div>
  </Teleport>
</template>

<script setup lang="ts">
import { nextTick, ref, watch } from 'vue'
import type { TooltipRow } from '@/charts'

const props = defineProps<{
  visible: boolean
  x: number
  y: number
  title: string
  rows: TooltipRow[]
}>()

const box = ref<HTMLElement | null>(null)
const left = ref(0)
const top = ref(0)

watch(() => [props.x, props.y, props.visible], async () => {
  await nextTick()
  const width = box.value?.offsetWidth ?? 0
  const height = box.value?.offsetHeight ?? 0
  left.value = props.x + 14 + width > window.innerWidth - 8 ? props.x - width - 14 : props.x + 14
  top.value = props.y + 14 + height > window.innerHeight - 8 ? props.y - height - 14 : props.y + 14
})
</script>

<style scoped>
.chart-tooltip {
  background: #10132d;
  border-radius: 10px;
  box-shadow: 0 10px 30px #10153544;
  color: #fff;
  font-size: 12px;
  left: 0;
  line-height: 1.6;
  min-width: 170px;
  padding: 9px 12px;
  pointer-events: none;
  position: fixed;
  top: 0;
  z-index: 3000;
}

.chart-tooltip b {
  display: block;
  margin-bottom: 3px;
}

.chart-tooltip__row {
  display: flex;
  gap: 18px;
  justify-content: space-between;
}

.chart-tooltip__row span:first-child {
  align-items: center;
  color: #bfc5e5;
  display: inline-flex;
  gap: 6px;
}

.chart-tooltip__row span:last-child {
  font-variant-numeric: tabular-nums;
  font-weight: 600;
}

.chart-tooltip i {
  border-radius: 2px;
  display: inline-block;
  height: 8px;
  width: 8px;
}
</style>
