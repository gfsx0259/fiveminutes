<template>
  <div class="kpi-tile">
    <div class="kpi-tile__label">
      <v-icon :icon="icon" size="16" />
      {{ label }}
    </div>
    <div class="kpi-tile__row">
      <div class="kpi-tile__value">{{ value }}</div>
      <SparkLine :values="trend" :color="color" />
    </div>
    <div class="kpi-tile__foot">
      <slot />
      <span>{{ foot }}</span>
    </div>
  </div>
</template>

<script setup lang="ts">
import SparkLine from '@/components/charts/SparkLine.vue'

withDefaults(defineProps<{
  icon: string
  label: string
  value: string
  trend: number[]
  foot: string
  color?: string
}>(), {
  color: '#5b45e8',
})
</script>

<style scoped>
.kpi-tile {
  background: #fff;
  border: 1px solid var(--lm-border);
  border-radius: var(--lm-radius);
  box-shadow: var(--lm-shadow);
  min-width: 0;
  padding: 14px 16px 12px;
}

.kpi-tile__label {
  align-items: center;
  color: var(--lm-muted);
  display: flex;
  font-size: 12px;
  font-weight: 600;
  gap: 6px;
}

.kpi-tile__label .v-icon {
  color: var(--lm-primary);
}

.kpi-tile__row {
  align-items: flex-end;
  display: flex;
  gap: 8px;
  justify-content: space-between;
  margin-top: 6px;
}

.kpi-tile__value {
  font-size: 24px;
  font-variant-numeric: tabular-nums;
  font-weight: 700;
  letter-spacing: -0.5px;
  white-space: nowrap;
}

.kpi-tile__foot {
  align-items: center;
  color: var(--lm-muted);
  display: flex;
  flex-wrap: wrap;
  font-size: 12px;
  gap: 6px;
  margin-top: 8px;
}
</style>
