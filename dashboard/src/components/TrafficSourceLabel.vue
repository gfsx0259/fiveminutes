<template>
  <span class="traffic-source">
    <v-icon :icon="trafficIcon(source)" :color="trafficColor(source)" size="16" />
    {{ trafficLabel(source) }}
  </span>
</template>

<script setup lang="ts">
import { trafficColor, trafficIcon, trafficLabel } from '@/marketOptions'

defineProps<{
  source: string
}>()
</script>

<style scoped>
.traffic-source {
  align-items: center;
  display: inline-flex;
  gap: 6px;
  white-space: nowrap;
}
</style>
