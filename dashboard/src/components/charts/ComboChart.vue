<template>
  <div ref="root" class="combo-chart" :style="{ height: `${height}px` }">
    <svg v-if="width > 0" :width="width" :height="height" :viewBox="`0 0 ${width} ${height}`" role="img">
      <rect
        v-if="hover !== null"
        :x="left + band * hover"
        :y="PAD_TOP"
        :width="band"
        :height="plotHeight"
        rx="6"
        fill="#f1eeff"
      />
      <g v-for="tick in grid" :key="tick.step">
        <line :x1="left" :x2="right" :y1="tick.y" :y2="tick.y" :stroke="tick.step ? '#efedfb' : '#dcd8f3'" />
        <text class="combo-chart__axis" :x="left - 10" :y="tick.y + 4" text-anchor="end">{{ tick.left }}</text>
        <text v-if="line" class="combo-chart__axis" :x="right + 10" :y="tick.y + 4">{{ tick.right }}</text>
      </g>
      <path v-for="segment in segments" :key="segment.key" :d="segment.path" :fill="segment.color" />
      <template v-if="line && points.length">
        <path
          :d="linePath"
          fill="none"
          :stroke="line.color"
          stroke-width="2.2"
          stroke-linejoin="round"
          stroke-linecap="round"
        />
        <circle
          v-for="(point, index) in points"
          :key="index"
          :cx="point.x"
          :cy="point.y"
          :r="labels.length > 20 ? 2.4 : 3.4"
          fill="#fff"
          :stroke="line.color"
          stroke-width="2"
        />
      </template>
      <text
        v-for="tick in xTicks"
        :key="tick.index"
        class="combo-chart__axis"
        :x="tick.x"
        :y="height - 7"
        text-anchor="middle"
      >
        {{ tick.label }}
      </text>
      <rect
        v-for="(_, index) in labels"
        :key="`hit-${index}`"
        :x="left + band * index"
        :y="PAD_TOP"
        :width="band"
        :height="plotHeight"
        fill="transparent"
        @mousemove="point(index, $event)"
        @mouseleave="hover = null"
      />
    </svg>
    <ChartTooltip
      :visible="hover !== null"
      :x="pointer.x"
      :y="pointer.y"
      :title="hover === null ? '' : labels[hover] ?? ''"
      :rows="hover === null ? [] : tooltip(hover)"
    />
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'
import ChartTooltip from '@/components/charts/ChartTooltip.vue'
import { niceMax, type ChartSeries, type TooltipRow } from '@/charts'

const PAD_TOP = 10
const PAD_BOTTOM = 26
const PAD_LEFT = 52

const props = withDefaults(defineProps<{
  labels: string[]
  ticks: string[]
  bars: ChartSeries[]
  line?: ChartSeries | null
  lineMax?: number | null
  leftFormat: (value: number) => string
  rightFormat?: (value: number) => string
  tooltip: (index: number) => TooltipRow[]
  height?: number
}>(), {
  line: null,
  lineMax: null,
  rightFormat: (value: number) => String(Math.round(value)),
  height: 270,
})

const root = ref<HTMLElement | null>(null)
const width = ref(0)
const hover = ref<number | null>(null)
const pointer = ref({ x: 0, y: 0 })
let observer: ResizeObserver | null = null

const left = PAD_LEFT
const right = computed(() => width.value - (props.line ? 46 : 12))
const plotHeight = computed(() => props.height - PAD_TOP - PAD_BOTTOM)
const band = computed(() => (right.value - left) / Math.max(props.labels.length, 1))
const barWidth = computed(() => Math.max(3, Math.min(30, band.value * 0.62)))
const leftMax = computed(() => niceMax(Math.max(0, ...props.labels.map((_, index) => props.bars.reduce((sum, series) => sum + (series.values[index] ?? 0), 0)))))
const rightMax = computed(() => props.lineMax ?? niceMax(Math.max(0, ...(props.line?.values ?? []))))
const bottom = computed(() => PAD_TOP + plotHeight.value)

const grid = computed(() => [0, 1, 2, 3, 4].map((step) => ({
  step,
  y: bottom.value - plotHeight.value * step / 4,
  left: props.leftFormat(leftMax.value * step / 4),
  right: props.rightFormat(rightMax.value * step / 4),
})))

const segments = computed(() => props.labels.flatMap((_, index) => {
  const x = left + band.value * index + (band.value - barWidth.value) / 2
  const parts = props.bars
    .map((series) => ({ color: series.color, size: plotHeight.value * (series.values[index] ?? 0) / leftMax.value }))
    .filter((part) => part.size > 0)
  let y = bottom.value
  return parts.map((part, position) => {
    y -= part.size
    const radius = position === parts.length - 1 ? Math.min(4, barWidth.value / 2, part.size) : 0
    return { key: `${index}-${position}`, color: part.color, path: column(x, y, barWidth.value, part.size, radius) }
  })
}))

const points = computed(() => (props.line?.values ?? []).map((value, index) => ({
  x: left + band.value * index + band.value / 2,
  y: bottom.value - plotHeight.value * value / rightMax.value,
})))

const linePath = computed(() => points.value.map((p, index) => `${index ? 'L' : 'M'}${p.x.toFixed(1)} ${p.y.toFixed(1)}`).join(''))

const xTicks = computed(() => {
  const step = Math.max(1, Math.ceil(props.labels.length / Math.max(1, Math.floor((right.value - left) / 60))))
  return props.ticks
    .map((label, index) => ({ index, label, x: left + band.value * index + band.value / 2 }))
    .filter((tick) => tick.index % step === 0)
})

function column(x: number, y: number, w: number, h: number, r: number): string {
  return `M${x} ${y + h}V${y + r}Q${x} ${y} ${x + r} ${y}H${x + w - r}Q${x + w} ${y} ${x + w} ${y + r}V${y + h}Z`
}

function point(index: number, event: MouseEvent): void {
  hover.value = index
  pointer.value = { x: event.clientX, y: event.clientY }
}

onMounted(() => {
  observer = new ResizeObserver(([entry]) => {
    width.value = Math.floor(entry?.contentRect.width ?? 0)
  })
  if (root.value) observer.observe(root.value)
})

onUnmounted(() => observer?.disconnect())
</script>

<style scoped>
.combo-chart {
  min-width: 0;
  position: relative;
}

.combo-chart svg {
  display: block;
}

.combo-chart__axis {
  fill: #6b7099;
  font-family: Inter, sans-serif;
  font-size: 11px;
}
</style>
