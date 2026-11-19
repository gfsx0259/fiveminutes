<template>
  <div class="chart-legend">
    <span v-for="item in items" :key="item.label">
      <i :class="{ 'chart-legend__line': item.line }" :style="{ background: item.color }" />{{ item.label }}
    </span>
  </div>
</template>

<script setup lang="ts">
defineProps<{
  items: { label: string, color: string, line?: boolean }[]
}>()
</script>

<style scoped>
.chart-legend {
  color: var(--lm-muted);
  display: flex;
  flex-wrap: wrap;
  font-size: 12px;
  gap: 14px;
}

.chart-legend span {
  align-items: center;
  display: inline-flex;
  gap: 6px;
}

.chart-legend i {
  border-radius: 3px;
  display: inline-block;
  height: 10px;
  width: 10px;
}

.chart-legend .chart-legend__line {
  border-radius: 2px;
  height: 3px;
  width: 14px;
}
</style>
